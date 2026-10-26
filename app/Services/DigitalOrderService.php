<?php

namespace App\Services;

use App\Models\DigitalCardCode;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderFinancial;
use App\Models\ProductProviderMapping;
use App\Services\Supplier\FoxReload\FoxReloadClient;
use App\Utils\CustomerManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class DigitalOrderService
{
    protected FoxReloadClient $client;
    protected bool $isMock;

    public function __construct(?FoxReloadClient $client = null)
    {
        $this->client = $client ?? new FoxReloadClient();
        $this->isMock = (bool) config('suppliers.suppliers.foxreload.is_mock', env('FOXRELOAD_IS_MOCK', true));
    }

    /**
     * Fulfill a paid digital order
     *
     * @param int $orderId
     * @return array
     */
    public function fulfillOrder(int $orderId): array
    {
        $order = Order::with(['details.product', 'customer'])->find($orderId);

        if (!$order) {
            Log::error("DigitalOrderService: Order #{$orderId} not found");
            return ['success' => false, 'error' => 'Order not found'];
        }

        // Idempotency: skip if already delivered or refunded
        if (in_array($order->fulfillment_status, ['delivered', 'refunded'])) {
            return [
                'success' => true,
                'status' => $order->fulfillment_status,
                'message' => "Order #{$orderId} is already {$order->fulfillment_status}"
            ];
        }

        $order->update([
            'fulfillment_status' => 'purchasing_from_supplier'
        ]);

        $totalCost = 0.0;
        $allDelivered = true;
        $failedReason = null;

        foreach ($order->details as $detail) {
            $mapping = ProductProviderMapping::where('product_id', $detail->product_id)
                ->where('is_active', 1)
                ->first();

            $costPerUnit = $mapping ? (float)$mapping->cost_price : ((float)$detail->price * 0.80);
            $itemTotalCost = $costPerUnit * (int)$detail->qty;
            $totalCost += $itemTotalCost;

            // Step 1: In live mode, verify FoxReload balance
            if (!$this->isMock) {
                $usdBalance = $this->client->getUsdBalance();
                if ($usdBalance !== null && $usdBalance < $itemTotalCost) {
                    Log::critical("FoxReload balance insufficient ({$usdBalance} < {$itemTotalCost}) for order #{$order->id}");
                    return $this->handleSupplierFailure($order, "Supplier balance insufficient");
                }
            }

            // Step 2: Attempt procurement from FoxReload
            $externalItemId = $mapping?->external_item_id;
            $codes = [];

            if ($externalItemId) {
                $itemsPayload = [
                    [
                        'itemId' => $externalItemId,
                        'quantity' => (int)$detail->qty,
                    ]
                ];

                $idempotencyKey = "order-{$order->id}-detail-{$detail->id}";
                $createRes = $this->client->createOrder($itemsPayload, isMock: $this->isMock, idempotencyKey: $idempotencyKey);

                if (!empty($createRes['success']) && !empty($createRes['data']['id'])) {
                    $foxOrderId = $createRes['data']['id'];
                    $order->update(['foxreload_order_id' => $foxOrderId]);

                    // Trigger payment on FoxReload
                    $this->client->payOrder($foxOrderId);

                    // Poll status
                    $pollRes = $this->client->pollOrderStatus($foxOrderId, maxAttempts: 5, delaySeconds: 2);
                    $foxStatus = $pollRes['data']['status'] ?? null;

                    if ($foxStatus === 'completed') {
                        $foxItems = $pollRes['data']['items'] ?? [];
                        foreach ($foxItems as $fItem) {
                            $extData = $fItem['externalData'] ?? [];
                            if (is_array($extData)) {
                                foreach ($extData as $codeStr) {
                                    if (is_string($codeStr) && trim($codeStr) !== '') {
                                        $codes[] = trim($codeStr);
                                    }
                                }
                            }
                        }
                    } elseif (in_array($foxStatus, ['failed', 'cancelled'])) {
                        $itemErr = $pollRes['data']['items'][0]['error'] ?? 'FoxReload order failed';
                        Log::warning("FoxReload order {$foxOrderId} failed: {$itemErr}");
                        return $this->handleSupplierFailure($order, $itemErr);
                    } else {
                        // Timed out or pending manual action
                        Log::warning("FoxReload order {$foxOrderId} status is {$foxStatus}, setting supplier_pending_review");
                        $order->update(['fulfillment_status' => 'supplier_pending_review']);
                        return [
                            'success' => false,
                            'status' => 'supplier_pending_review',
                            'message' => 'Supplier order pending confirmation'
                        ];
                    }
                }
            }

            // Step 3: Mock fallback for local testing if FoxReload is mock or unconfigured
            if (empty($codes) && $this->isMock) {
                Log::info("Generating simulated mock codes for order #{$order->id}, detail #{$detail->id}");
                for ($i = 0; $i < (int)$detail->qty; $i++) {
                    $codes[] = 'TEST-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
                }
            }

            // Step 4: Save delivered card codes encrypted
            if (!empty($codes)) {
                foreach ($codes as $code) {
                    DigitalCardCode::create([
                        'order_id' => $order->id,
                        'order_detail_id' => $detail->id,
                        'user_id' => $order->customer_id,
                        'card_code' => $code,
                        'delivered_at' => now(),
                    ]);
                }
            } else {
                $allDelivered = false;
                $failedReason = "No codes delivered for product #{$detail->product_id}";
                break;
            }
        }

        if (!$allDelivered) {
            return $this->handleSupplierFailure($order, $failedReason ?? 'Fulfillment incomplete');
        }

        // Step 5: Mark order delivered
        $order->update([
            'order_status' => 'delivered',
            'fulfillment_status' => 'delivered',
        ]);

        // Step 6: Record order financials
        $sellingPrice = (float)$order->order_amount;
        $gatewayFee = 0.0;

        // If PayPal capture response fee is available on payment request
        $netProfit = round($sellingPrice - $totalCost - $gatewayFee, 4);

        OrderFinancial::updateOrCreate(
            ['order_id' => $order->id],
            [
                'selling_price' => $sellingPrice,
                'cost_price' => $totalCost,
                'gateway_fee' => $gatewayFee,
                'net_profit' => $netProfit,
                'currency' => 'USD',
            ]
        );

        Log::info("Order #{$order->id} fulfilled successfully with " . count($order->digitalCardCodes) . " codes");

        return [
            'success' => true,
            'status' => 'delivered',
            'order_id' => $order->id,
        ];
    }

    /**
     * Handle supplier failure: mark order failed and refund to customer wallet
     */
    protected function handleSupplierFailure(Order $order, string $reason): array
    {
        Log::warning("DigitalOrderService: Fulfilling order #{$order->id} failed: {$reason}. Refunding to wallet.");

        $order->update([
            'order_status' => 'failed',
            'fulfillment_status' => 'supplier_failed',
            'order_note' => "Supplier failed: {$reason}",
        ]);

        // Auto-refund 100% of order_amount to user wallet
        if ($order->customer_id && (float)$order->order_amount > 0) {
            CustomerManager::create_wallet_transaction(
                $order->customer_id,
                (float)$order->order_amount,
                'order_refund',
                (string)$order->id,
                ['payment_method' => 'wallet_refund']
            );

            $order->update([
                'order_status' => 'refunded',
                'fulfillment_status' => 'refunded',
            ]);
        }

        return [
            'success' => false,
            'status' => 'refunded',
            'error' => $reason,
        ];
    }
}
