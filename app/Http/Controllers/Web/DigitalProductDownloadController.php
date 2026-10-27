<?php

namespace App\Http\Controllers\Web;

use App\Contracts\Repositories\BusinessSettingRepositoryInterface;
use App\Contracts\Repositories\OrderDetailRepositoryInterface;
use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DigitalProductDownloadController extends Controller
{

    public function __construct(
        private readonly OrderRepositoryInterface $orderRepo,
        private readonly OrderDetailRepositoryInterface $orderDetailRepo,
    )
    {
    }

    /**
     * @param Request|null $request
     * @param string|null $type
     * @return View Index function is the starting point of a controller
     * Index function is the starting point of a controller
     */
    public function index(Request|null $request, ?string $type = null): View
    {
        return $this->getListView($request);
    }

    public function getListView(Request $request): View
    {
        $order = $this->orderRepo->getFirstWhere(params: ['id' => $request['order_id']], relations: ['customer', 'orderDetails.product']);
        $orderDetails = $this->orderDetailRepo->getListWhere(
            filters: ['order_id' => $request['order_id']],
            relations: ['order.customer', 'product'],
            dataLimit: 'all'
        );

        if ($order && $order->customer && $order->customer['email'] == $request['email']) {

            $isDigitalProductReadyCount = 0;
            $isDigitalProductExist = 0;
            foreach($orderDetails as $orderDetail) {
                if ($orderDetail->product->product_type == 'digital') {
                    $isDigitalProductExist++;
                }
                if ($orderDetail->product->product_type == 'digital' && $orderDetail->product->digital_product_type == 'ready_after_sell' && $orderDetail->digital_file_after_sell) {
                    $isDigitalProductReadyCount++;
                }

                if ($orderDetail->product->product_type == 'digital' && $orderDetail->product->digital_product_type == 'ready_product') {
                    $isDigitalProductReadyCount++;
                }
            }

            return view(VIEW_FILE_NAMES['digital_product_download'], [
                'order' => $order,
                'orderDetails' => $orderDetails,
                'isDigitalProductExist' => $isDigitalProductExist,
                'isDigitalProductReadyCount' => $isDigitalProductReadyCount,
            ]);
        } else {
            Toastr::warning(translate('invalid_order'));
            return view(VIEW_FILE_NAMES['digital_product_download']);
        }
    }

    /**
     * Polling endpoint: returns order status and items with MASKED codes (never exposes plaintext codes).
     */
    public function getDigitalCardCodes(Request $request, $orderId): \Illuminate\Http\JsonResponse
    {
        $customerId = auth('customer')->id();
        if (!$customerId) {
            return response()->json(['success' => false, 'message' => translate('Unauthorized')], 401);
        }

        $order = \App\Models\Order::where('id', $orderId)
            ->where('customer_id', $customerId)
            ->with(['details.product', 'details.digitalCardCodes'])
            ->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => translate('Order not found')], 404);
        }

        if (in_array($order->fulfillment_status, ['pending', 'purchasing'])) {
            try {
                app(\App\Services\DigitalOrderService::class)->fulfillOrder($order->id);
                $order->load(['details.product', 'details.digitalCardCodes']);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Immediate fulfillment on polling attempt failed for order #{$orderId}: " . $e->getMessage());
            }
        }

        $items = [];
        $totalItems = 0;
        $deliveredItems = 0;
        $failedItems = 0;

        foreach ($order->details as $detail) {
            $totalItems++;
            $status = $detail->fulfillment_status ?? $order->fulfillment_status ?? 'pending';

            if ($status === 'delivered') {
                $deliveredItems++;
            } elseif ($status === 'supplier_failed') {
                $failedItems++;
            }

            $detailCodes = [];
            foreach ($detail->digitalCardCodes as $cardCode) {
                $rawCode = (string) $cardCode->card_code;
                $masked = strlen($rawCode) >= 4
                    ? '••••-••••-••••-' . substr($rawCode, -4)
                    : '••••-••••-••••';

                $detailCodes[] = [
                    'id' => $cardCode->id,
                    'masked_code' => $masked,
                    'has_pin' => !empty($cardCode->pin),
                    'revealed' => !is_null($cardCode->revealed_at),
                    'delivered_at' => $cardCode->delivered_at ? $cardCode->delivered_at->format('Y-m-d H:i') : null,
                ];
            }

            $productName = $detail->product ? $detail->product->name : null;
            if (!$productName && !empty($detail->product_details)) {
                $detailsArray = is_array($detail->product_details) ? $detail->product_details : json_decode($detail->product_details, true);
                $productName = $detailsArray['name'] ?? translate('Digital Card');
            }

            $items[] = [
                'detail_id' => $detail->id,
                'product_name' => $productName ?? translate('Digital Card'),
                'variant' => $detail->variant,
                'qty' => $detail->qty,
                'fulfillment_status' => $status,
                'codes' => $detailCodes,
            ];
        }

        // Compute aggregated status if order level not set
        $orderFulfillmentStatus = $order->fulfillment_status ?? 'pending';
        if ($totalItems > 0) {
            if ($deliveredItems === $totalItems) {
                $orderFulfillmentStatus = 'delivered';
            } elseif ($failedItems === $totalItems) {
                $orderFulfillmentStatus = 'supplier_failed';
            } elseif ($deliveredItems > 0 && $failedItems > 0) {
                $orderFulfillmentStatus = 'partially_delivered';
            }
        }

        return response()->json([
            'success' => true,
            'order_id' => $order->id,
            'fulfillment_status' => $orderFulfillmentStatus,
            'items' => $items,
            'wallet_refunded' => in_array($orderFulfillmentStatus, ['supplier_failed', 'partially_delivered']),
        ]);
    }

    /**
     * Authenticated Reveal endpoint: reveals single code, sets revealed_at audit timestamp, returns plaintext code & pin.
     */
    public function revealCardCode(Request $request, $codeId): \Illuminate\Http\JsonResponse
    {
        $customerId = auth('customer')->id();
        if (!$customerId) {
            return response()->json(['status' => 0, 'message' => translate('Unauthorized')], 401);
        }

        $cardCode = \App\Models\DigitalCardCode::with('order')->find($codeId);
        if (!$cardCode || !$cardCode->order || $cardCode->order->customer_id != $customerId) {
            return response()->json(['status' => 0, 'message' => translate('Unauthorized or card not found')], 403);
        }

        // Dispute audit trail: record initial reveal timestamp once
        if (is_null($cardCode->revealed_at)) {
            $cardCode->update(['revealed_at' => now()]);
        }

        return response()->json([
            'status' => 1,
            'code' => $cardCode->card_code,
            'pin' => $cardCode->pin,
            'revealed_at' => $cardCode->revealed_at ? $cardCode->revealed_at->toIso8601String() : now()->toIso8601String(),
        ]);
    }
}
