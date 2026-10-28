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
        $this->isMock = (bool) config('suppliers.suppliers.foxreload.is_mock', env('FOXRELOAD_IS_MOCK', false));
    }

    /**
     * Fulfill a paid digital order at the item level (order_details).
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

        // Idempotency: skip if entirely delivered or fully refunded
        if (in_array($order->fulfillment_status, ['delivered', 'refunded'])) {
            return [
                'success' => true,
                'status' => $order->fulfillment_status,
                'message' => "Order #{$orderId} is already {$order->fulfillment_status}"
            ];
        }

        // Filter only order details that have an active ProductProviderMapping (protect file downloads)
        $digitalDetails = $order->details->filter(function ($detail) {
            return ProductProviderMapping::where('product_id', $detail->product_id)
                ->where('is_active', 1)
                ->exists();
        });

        if ($digitalDetails->isEmpty()) {
            return [
                'success' => true,
                'status' => 'not_applicable',
                'message' => 'No supplier-mapped digital cards in order.'
            ];
        }

        $order->update([
            'fulfillment_status' => 'purchasing'
        ]);

        $totalDeliveredCost = '0.00000000';
        $deliveredItemsCount = 0;
        $failedItemsCount = 0;
        $pendingItemsCount = 0;
        $totalDigitalItems = $digitalDetails->count();

        foreach ($digitalDetails as $detail) {
            // Check if already delivered with required quantity of codes
            $existingCodesCount = $detail->digitalCardCodes()->count();
            if ($existingCodesCount >= $detail->qty && $detail->fulfillment_status === 'delivered') {
                $deliveredItemsCount++;
                continue;
            }

            $mapping = null;
            if (!empty($detail->variant)) {
                $mapping = ProductProviderMapping::where('product_id', $detail->product_id)
                    ->where('variant_key', $detail->variant)
                    ->where('is_active', 1)
                    ->first();
            }
            if (!$mapping) {
                $mapping = ProductProviderMapping::where('product_id', $detail->product_id)
                    ->where('is_active', 1)
                    ->first();
            }

            $costPerUnit = $mapping ? (string)$mapping->cost_price : bcmul((string)$detail->price, '0.80', 8);
            $itemTotalCost = bcmul($costPerUnit, (string)$detail->qty, 8);

            // Step 1: Pre-generate & persist deterministic client_reference_id to prevent double-buy
            if (empty($detail->client_reference_id)) {
                $clientRef = "ORD-{$order->id}-DET-{$detail->id}-U-1";
                $detail->update([
                    'client_reference_id' => $clientRef,
                    'fulfillment_status' => 'purchasing'
                ]);
            } else {
                $clientRef = $detail->client_reference_id;
                // If previously stuck in pending review or pending, allow re-evaluation
                if (in_array($detail->fulfillment_status, ['supplier_pending_review', 'pending'])) {
                    $detail->update(['fulfillment_status' => 'purchasing']);
                }
            }

            // Step 2: Instant simulated delivery for Mock Mode
            // FoxReload public API limits mock orders when balance is $0 (MOCK_ORDER_LIMIT_REACHED).
            // When is_mock is true, generate authentic simulated digital codes locally without API dependency.
            if ($this->isMock) {
                Log::info("Mock mode enabled: generating instant simulated codes for order #{$order->id}, detail #{$detail->id}");
                $mockCodes = [];
                for ($i = $existingCodesCount; $i < (int)$detail->qty; $i++) {
                    $mockCodes[] = 'GBC-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
                }

                foreach ($mockCodes as $code) {
                    DigitalCardCode::create([
                        'order_id' => $order->id,
                        'order_detail_id' => $detail->id,
                        'user_id' => $order->customer_id,
                        'card_code' => $code,
                        'pin' => '1234',
                        'delivered_at' => now(),
                    ]);
                }

                $detail->update([
                    'fulfillment_status' => 'delivered',
                    'delivery_status' => 'delivered'
                ]);

                $totalDeliveredCost = bcadd($totalDeliveredCost, $itemTotalCost, 8);
                $deliveredItemsCount++;
                continue;
            }

            // Step 3 (Live Mode): Check FoxReload wholesale balance before purchasing
            $usdBalance = $this->client->getUsdBalance();
            if ($usdBalance !== null && bccomp((string)$usdBalance, $itemTotalCost, 8) < 0) {
                $errMsg = "FoxReload balance insufficient ($" . number_format((float)$usdBalance, 2) . " available, $" . number_format((float)$itemTotalCost, 2) . " required). Please top up your FoxReload wholesale balance.";
                Log::critical("Order #{$order->id}, detail #{$detail->id}: {$errMsg}");
                $this->handleItemFailure($order, $detail, $errMsg);
                $failedItemsCount++;
                continue;
            }

            // Step 4 (Live Mode): Procure from FoxReload
            $externalItemId = $mapping?->external_item_id;
            $codes = [];
            $foxOrderId = $detail->foxreload_order_id;

            if ($externalItemId) {
                // If order was not yet created at FoxReload, create it now
                if (empty($foxOrderId)) {
                    $itemsPayload = [
                        [
                            'itemId' => $externalItemId,
                            'quantity' => (int)$detail->qty,
                        ]
                    ];

                    $createRes = $this->client->createOrder($itemsPayload, isMock: false, idempotencyKey: $clientRef);

                    if (!empty($createRes['success']) && !empty($createRes['data']['id'])) {
                        $foxOrderId = $createRes['data']['id'];
                        $detail->update(['foxreload_order_id' => $foxOrderId]);
                        if (empty($order->foxreload_order_id)) {
                            $order->update(['foxreload_order_id' => $foxOrderId]);
                        }
                        // Pay for order from wholesale balance (empty payload {})
                        $payRes = $this->client->payOrder($foxOrderId);
                        Log::info("FoxReload order #{$foxOrderId} payment response: " . json_encode($payRes));
                        // Allow 1.5s for supplier backend to generate/assign voucher codes
                        usleep(1500000);
                    } else {
                        $rawErr = $createRes['error'] ?? 'FoxReload order creation failed';
                        $errMsg = is_array($rawErr) ? json_encode($rawErr) : (string)$rawErr;
                        Log::warning("FoxReload order creation failed for detail #{$detail->id}: {$errMsg}");
                        $this->handleItemFailure($order, $detail, $errMsg);
                        $failedItemsCount++;
                        continue;
                    }
                }

                if (!empty($foxOrderId)) {
                    // Poll FoxReload up to 3 times (1.5s delay) so codes are captured immediately
                    for ($attempt = 1; $attempt <= 3; $attempt++) {
                        $statusRes = $this->client->getOrderStatus($foxOrderId);
                        $foxStatus = $statusRes['data']['status'] ?? 'pending';
                        $foxItems = $statusRes['data']['items'] ?? [];

                        foreach ($foxItems as $fItem) {
                            $extData = $fItem['externalData'] ?? [];
                            if (is_array($extData)) {
                                foreach ($extData as $codeStr) {
                                    if (is_string($codeStr) && trim($codeStr) !== '' && !in_array(trim($codeStr), $codes)) {
                                        $codes[] = trim($codeStr);
                                    }
                                }
                            }
                        }

                        if (!empty($codes) || in_array($foxStatus, ['failed', 'cancelled'])) {
                            break;
                        }

                        if ($attempt < 3) {
                            usleep(1500000);
                        }
                    }

                    if (empty($codes)) {
                        if (in_array($foxStatus, ['failed', 'cancelled'])) {
                            $rawItemErr = $foxItems[0]['error'] ?? "FoxReload order status: {$foxStatus}";
                            $itemErr = is_array($rawItemErr) ? json_encode($rawItemErr) : (string)$rawItemErr;
                            Log::warning("FoxReload order {$foxOrderId} failed: {$itemErr}");
                            $this->handleItemFailure($order, $detail, $itemErr);
                            $failedItemsCount++;
                            continue;
                        } else {
                            // Paid/processing at supplier, waiting for code generation
                            $pendingItemsCount++;
                            continue;
                        }
                    }
                }
            }

            // Step 5: Save delivered live card codes
            if (!empty($codes)) {
                foreach ($codes as $code) {
                    $pin = null;
                    if (preg_match('/(?:PIN|Pin|pin):\s*([0-9a-zA-Z]+)/', $code, $pinMatch)) {
                        $pin = $pinMatch[1];
                    }

                    DigitalCardCode::create([
                        'order_id' => $order->id,
                        'order_detail_id' => $detail->id,
                        'user_id' => $order->customer_id,
                        'card_code' => $code,
                        'pin' => $pin,
                        'delivered_at' => now(),
                    ]);
                }

                $detail->update([
                    'fulfillment_status' => 'delivered',
                    'delivery_status' => 'delivered'
                ]);

                $totalDeliveredCost = bcadd($totalDeliveredCost, $itemTotalCost, 8);
                $deliveredItemsCount++;
            } else {
                $this->handleItemFailure($order, $detail, "No card codes delivered by supplier");
                $failedItemsCount++;
            }
        }

        // If any item is still pending at supplier, return pending status for queue release(5)
        if ($pendingItemsCount > 0) {
            $order->update(['fulfillment_status' => 'purchasing']);
            return [
                'success' => false,
                'status' => 'pending',
                'order_id' => $order->id,
                'message' => "Order #{$order->id} has {$pendingItemsCount} item(s) pending with supplier"
            ];
        }

        // Update overall order fulfillment status
        if ($deliveredItemsCount === $totalDigitalItems) {
            $order->update([
                'order_status' => 'delivered',
                'fulfillment_status' => 'delivered',
            ]);
            $overallStatus = 'delivered';
        } elseif ($deliveredItemsCount > 0 && $failedItemsCount > 0) {
            $order->update([
                'order_status' => 'confirmed',
                'fulfillment_status' => 'partially_delivered',
            ]);
            $overallStatus = 'partially_delivered';
        } else {
            $order->update([
                'order_status' => 'failed',
                'fulfillment_status' => 'supplier_failed',
            ]);
            $overallStatus = 'supplier_failed';
        }

        // Record or update order financials
        $sellingPrice = (float)$order->order_amount;
        $costPrice = (float)$totalDeliveredCost;
        $gatewayFee = 0.0;
        $netProfit = round($sellingPrice - $costPrice - $gatewayFee, 4);

        OrderFinancial::updateOrCreate(
            ['order_id' => $order->id],
            [
                'selling_price' => $sellingPrice,
                'cost_price' => $costPrice,
                'gateway_fee' => $gatewayFee,
                'net_profit' => $netProfit,
                'currency' => 'USD',
            ]
        );

        Log::info("Order #{$order->id} evaluated. Status: {$overallStatus} (Delivered: {$deliveredItemsCount}, Failed: {$failedItemsCount})");

        return [
            'success' => ($deliveredItemsCount > 0),
            'status' => $overallStatus,
            'order_id' => $order->id,
            'delivered_items' => $deliveredItemsCount,
            'failed_items' => $failedItemsCount
        ];
    }

    /**
     * Handle item-level failure: mark order_detail failed and auto-refund item amount to wallet
     */
    protected function handleItemFailure(Order $order, OrderDetail $detail, string $reason): void
    {
        Log::warning("Fulfillment failed for order #{$order->id}, detail #{$detail->id}: {$reason}. Auto-refunding item to wallet.");

        $detail->update([
            'fulfillment_status' => 'supplier_failed',
            'fulfillment_error' => $reason
        ]);

        // Calculate exact item amount using bcmath (scale 8): (price * qty) + tax - discount
        $itemSubtotal = bcmul((string)$detail->price, (string)$detail->qty, 8);
        $itemTax = (string)($detail->tax ?? 0);
        $itemDiscount = (string)($detail->discount ?? 0);
        $itemRefundAmount = bcsub(bcadd($itemSubtotal, $itemTax, 8), $itemDiscount, 8);

        if (bccomp($itemRefundAmount, '0', 8) <= 0) {
            $itemRefundAmount = $itemSubtotal;
        }

        if ($order->customer_id && bccomp($itemRefundAmount, '0', 8) > 0) {
            CustomerManager::create_wallet_transaction(
                $order->customer_id,
                (float)$itemRefundAmount,
                'order_refund',
                "order_{$order->id}_detail_{$detail->id}",
                ['payment_method' => 'wallet_refund']
            );
        }
    }

    /**
     * Mark pending items as supplier_pending_review after queue polling timeout
     */
    public function markSupplierPendingReview(int $orderId): void
    {
        $order = Order::with('details')->find($orderId);
        if (!$order) return;

        foreach ($order->details as $detail) {
            if ($detail->fulfillment_status === 'purchasing') {
                $detail->update([
                    'fulfillment_status' => 'supplier_pending_review',
                    'fulfillment_error' => 'Supplier order polling timed out after 5 attempts'
                ]);
            }
        }

        $order->update([
            'fulfillment_status' => 'supplier_pending_review'
        ]);

        Log::warning("Order #{$orderId} marked as supplier_pending_review for manual administrator inspection.");
    }
}
