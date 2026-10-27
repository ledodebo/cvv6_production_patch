@extends('layouts.front-end.app')

@section('title', translate('order_Complete'))

@push('css_or_js')
<style>
    .digital-order-summary-box {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
        border: 1px solid #edf2f7;
    }
    .digital-voucher-card {
        background: linear-gradient(135deg, #ffffff 0%, #f9fafb 100%);
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        position: relative;
        overflow: hidden;
    }
    .digital-voucher-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.07);
    }
    .digital-voucher-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 6px;
        height: 100%;
        background: #0f9d58;
    }
    .voucher-code-badge {
        background: #f1f5f9;
        border: 1px dashed #cbd5e1;
        border-radius: 8px;
        padding: 10px 16px;
        display: inline-flex;
        align-items: center;
        gap: 12px;
    }
    .fulfillment-pulsing-box {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-radius: 12px;
        padding: 24px;
        text-align: center;
    }
    .pulse-animation {
        display: inline-block;
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: rgba(15, 157, 88, 0.2);
        position: relative;
    }
    .pulse-animation::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 24px;
        height: 24px;
        transform: translate(-50%, -50%);
        border-radius: 50%;
        background: #0f9d58;
        animation: pulseRing 1.5s infinite;
    }
    @keyframes pulseRing {
        0% { transform: translate(-50%, -50%) scale(0.8); opacity: 1; }
        100% { transform: translate(-50%, -50%) scale(1.6); opacity: 0; }
    }
    @media print {
        header, footer, .navbar, .btn, .breadcrumb, .floating-btn-grp, #loading, .no-print {
            display: none !important;
        }
        body, .container {
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            background: #ffffff !important;
        }
        .digital-voucher-card {
            border: 1px solid #000000 !important;
            box-shadow: none !important;
            page-break-inside: avoid;
            margin-bottom: 20px !important;
        }
        .voucher-code-badge {
            border: 1px solid #000000 !important;
            background: #ffffff !important;
        }
        .digital-voucher-card::before {
            display: none !important;
        }
    }
</style>
@endpush

@section('content')
@php
    $orders = collect();
    if (!empty($order_ids)) {
        $orders = \App\Models\Order::whereIn('id', (array)$order_ids)
            ->with(['details.product', 'details.digitalCardCodes'])
            ->get();
    }

    $hasPendingFulfillment = false;
    $primaryOrderId = $orders->first()?->id ?? (is_array($order_ids) ? ($order_ids[0] ?? null) : $order_ids);

    foreach ($orders as $ord) {
        $orderStatus = $ord->fulfillment_status ?? 'pending';
        if (in_array($orderStatus, ['pending', 'purchasing'])) {
            $hasPendingFulfillment = true;
        }
        foreach ($ord->details as $d) {
            $dStatus = $d->fulfillment_status ?? 'pending';
            if (in_array($dStatus, ['pending', 'purchasing'])) {
                $hasPendingFulfillment = true;
            }
        }
    }
@endphp

<div class="container mt-4 mb-5 rtl text-align-direction">
    <div class="row justify-content-center">
        <div class="col-lg-9 col-md-11">

            {{-- 1. Order Success Header --}}
            <div class="digital-order-summary-box p-4 mb-4 text-center">
                <div class="mb-3">
                    <i class="fa fa-check-circle" style="font-size: 64px; color: #0f9d58;"></i>
                </div>
                <h3 class="fw-bold text-dark mb-2">
                    @if(isset($isNewCustomerInSession) && $isNewCustomerInSession)
                        {{ translate('Order_Placed_&_Account_Created_Successfully') }}!
                    @else
                        {{ translate('Order_Placed_Successfully') }}!
                    @endif
                </h3>
                <p class="text-muted fs-14 mb-3">
                    {{ translate('Thank you for your order! Your digital vouchers have been generated below.') }}
                </p>

                {{-- Order Meta Tags --}}
                <div class="d-flex flex-wrap justify-content-center gap-3 pt-2 border-top">
                    <div class="px-3 py-1 bg-light rounded text-start">
                        <small class="text-muted d-block">{{ translate('Order_ID') }}</small>
                        <span class="fw-bold text-dark">
                            @foreach ($orders as $ord)
                                #{{ $ord->id }}@if(!$loop->last), @endif
                            @endforeach
                        </span>
                    </div>
                    <div class="px-3 py-1 bg-light rounded text-start">
                        <small class="text-muted d-block">{{ translate('Date') }}</small>
                        <span class="fw-bold text-dark">{{ now()->format('M d, Y h:i A') }}</span>
                    </div>
                    <div class="px-3 py-1 bg-light rounded text-start">
                        <small class="text-muted d-block">{{ translate('Payment_Method') }}</small>
                        <span class="fw-bold text-uppercase text-dark">
                            {{ str_replace('_', ' ', $orders->first()?->payment_method ?? 'Online') }}
                        </span>
                    </div>
                    <div class="px-3 py-1 bg-light rounded text-start">
                        <small class="text-muted d-block">{{ translate('Total_Amount') }}</small>
                        <span class="fw-bold text-success">
                            {{ webCurrencyConverter($orders->sum('order_amount')) }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- 2. Supplier Procurement Polling Banner (if item is pending/purchasing) --}}
            <div id="fulfillment-polling-banner" class="fulfillment-pulsing-box mb-4 @if(!$hasPendingFulfillment) d-none @endif">
                <div class="pulse-animation mb-3"></div>
                <h5 class="fw-bold text-dark mb-1">{{ translate('Procuring your digital card from supplier...') }}</h5>
                <p class="text-muted fs-13 mb-2">{{ translate('Please wait a moment while your digital codes are being finalized. This usually takes 5 to 15 seconds.') }}</p>
                <div class="progress" style="height: 6px; max-width: 300px; margin: 0 auto;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 100%"></div>
                </div>
            </div>

            {{-- 3. Supplier Failure & Refund Banner (hidden by default) --}}
            <div id="supplier-failed-banner" class="alert alert-danger p-4 mb-4 rounded-3 d-none">
                <div class="d-flex align-items-center gap-3">
                    <i class="tio-error-circle" style="font-size: 36px;"></i>
                    <div class="flex-grow-1">
                        <h5 class="fw-bold mb-1">{{ translate('Unable to procure card from supplier') }}</h5>
                        <p class="mb-0 fs-13">
                            {{ translate('Unable to procure this card from the supplier. Your payment has been automatically refunded to your Wallet.') }}
                        </p>
                    </div>
                    <div>
                        <a href="{{ route('wallet') }}" class="btn btn-warning fw-bold text-dark text-nowrap">
                            <i class="tio-wallet me-1"></i> {{ translate('View_Wallet') }}
                        </a>
                    </div>
                </div>
            </div>

            {{-- 4. Polling Timeout Notice (hidden by default) --}}
            <div id="polling-timeout-notice" class="alert alert-info p-3 mb-4 rounded-3 d-none">
                <div class="d-flex align-items-center gap-2">
                    <i class="tio-info-outset fs-20"></i>
                    <p class="mb-0 fs-13">
                        {{ translate('Your order is taking a little longer than usual to finalize. Your codes will appear in My Orders shortly and we will notify you by email.') }}
                    </p>
                </div>
            </div>

            {{-- 5. Digital Vouchers List Container --}}
            <div id="digital-vouchers-container" class="mb-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="fw-bold text-dark mb-0">
                        <i class="tio-gift me-1 text-primary"></i> {{ translate('Your_Digital_Cards_&_Vouchers') }}
                    </h5>
                    <button type="button" class="btn btn-outline-secondary btn-sm no-print" onclick="printDigitalVouchers()">
                        <i class="tio-print me-1"></i> {{ translate('Print_/_Save_PDF') }}
                    </button>
                </div>

                @php $renderedCodesCount = 0; @endphp
                @foreach ($orders as $order)
                    @foreach ($order->details as $detail)
                        @php
                            $productName = $detail->product ? $detail->product->name : null;
                            if (!$productName && !empty($detail->product_details)) {
                                $pDetails = is_array($detail->product_details) ? $detail->product_details : json_decode($detail->product_details, true);
                                $productName = $pDetails['name'] ?? translate('Digital Card');
                            }
                            $codes = $detail->digitalCardCodes;
                        @endphp

                        @if ($codes->count() > 0)
                            @foreach ($codes as $cardCode)
                                @php
                                    $renderedCodesCount++;
                                    $rawCodeStr = (string)$cardCode->card_code;
                                    $maskedStr = strlen($rawCodeStr) >= 4 ? '••••-••••-••••-' . substr($rawCodeStr, -4) : '••••-••••-••••';
                                @endphp
                                <div class="digital-voucher-card p-4 mb-3"
                                     data-code-id="{{ $cardCode->id }}"
                                     data-reveal-url="{{ route('account-order-digital-codes.reveal', $cardCode->id) }}">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-2 border-bottom">
                                        <div>
                                            <h6 class="fw-bold text-dark mb-1">{{ $productName }}</h6>
                                            @if(!empty($detail->variant))
                                                <span class="badge bg-soft-primary text-primary px-2 py-1 fs-12">
                                                    {{ $detail->variant }}
                                                </span>
                                            @endif
                                        </div>
                                        <div>
                                            <span class="badge bg-soft-success text-success px-3 py-1 fs-12">
                                                <i class="tio-checkmark-circle-outlined me-1"></i> {{ translate('Delivered') }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="row align-items-center g-3">
                                        {{-- Voucher Code Display (Masked only in initial HTML) --}}
                                        <div class="col-md-7">
                                            <label class="text-muted fs-12 d-block mb-1">{{ translate('Card_Code') }}</label>
                                            <div class="voucher-code-badge w-100 justify-content-between">
                                                <span class="voucher-code-display font-monospace fw-bold fs-16 text-dark"
                                                      id="code-text-{{ $cardCode->id }}"
                                                      data-masked="{{ $maskedStr }}">
                                                    {{ $maskedStr }}
                                                </span>
                                                <div class="d-flex gap-2 no-print">
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-primary btn-reveal-code"
                                                            data-reveal-text="{{ translate('Reveal') }}"
                                                            data-hide-text="{{ translate('Hide') }}"
                                                            onclick="revealDigitalCardCode(this, {{ $cardCode->id }}, '{{ route('account-order-digital-codes.reveal', $cardCode->id) }}')">
                                                        <i class="tio-invisible me-1"></i> {{ translate('Reveal') }}
                                                    </button>
                                                    <button type="button"
                                                            class="btn btn-sm btn-primary btn-copy-code"
                                                            data-copied-text="{{ translate('Copied!') }}"
                                                            onclick="copyDigitalCardCode(this, {{ $cardCode->id }}, '{{ route('account-order-digital-codes.reveal', $cardCode->id) }}')">
                                                        <i class="tio-copy me-1"></i> {{ translate('Copy') }}
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- PIN Display (if applicable) --}}
                                        @if (!empty($cardCode->pin))
                                            <div class="col-md-5">
                                                <label class="text-muted fs-12 d-block mb-1">{{ translate('Card_PIN') }}</label>
                                                <div class="voucher-code-badge w-100">
                                                    <span class="voucher-pin-display font-monospace fw-bold fs-16 text-dark">
                                                        ••••
                                                    </span>
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Redemption instructions --}}
                                    <div class="mt-3 pt-2 text-muted fs-12">
                                        <i class="tio-info-outset me-1 text-primary"></i>
                                        {{ translate('How_to_redeem: Log into your account on the provider platform, go to Redeem Gift Card/Code, and paste your code.') }}
                                    </div>
                                </div>
                            @endforeach
                        @else
                            {{-- Placeholder card while waiting for fulfillment job to generate codes --}}
                            <div class="digital-voucher-card p-4 mb-3 placeholder-voucher-card" data-detail-id="{{ $detail->id }}">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="fw-bold text-dark mb-0">{{ $productName }}</h6>
                                    <span class="badge bg-soft-warning text-warning px-2 py-1 fs-12 status-badge">
                                        <i class="tio-time me-1"></i> {{ translate(ucfirst($detail->fulfillment_status ?? 'Purchasing...')) }}
                                    </span>
                                </div>
                                <p class="text-muted fs-13 mb-0">
                                    {{ translate('Your card code is being generated. It will appear here automatically momentarily.') }}
                                </p>
                            </div>
                        @endif
                    @endforeach
                @endforeach
            </div>

            {{-- 6. Navigation Buttons --}}
            <div class="row mt-4 no-print">
                <div class="col-12 text-center d-flex justify-content-center gap-3">
                    <a href="{{ route('account-oder') }}" class="btn btn-primary px-4">
                        <i class="tio-file-text-outlined me-1"></i> {{ translate('View_in_My_Orders') }}
                    </a>
                    <a href="{{ route('home') }}" class="btn btn-outline-secondary px-4">
                        {{ translate('Continue_Shopping') }}
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection

@push('script')
<script>
    "use strict";

    $(document).ready(function () {
        const orderId = "{{ $primaryOrderId }}";
        const hasPending = {{ $hasPendingFulfillment ? 'true' : 'false' }};

        if (hasPending && orderId) {
            let pollAttempts = 0;
            const maxPollAttempts = 10; // 25 seconds total (10 attempts * 2.5s)
            const pollInterval = 2500; // 2.5 seconds

            const pollTimer = setInterval(function () {
                pollAttempts++;

                $.ajax({
                    url: "{{ url('account-order-digital-codes') }}/" + orderId,
                    type: "GET",
                    headers: {
                        "X-CSRF-TOKEN": $('meta[name="_token"]').attr("content")
                    },
                    success: function (res) {
                        if (!res || !res.success) return;

                        const status = res.fulfillment_status;

                        // Case 1: Terminal success (delivered or partially_delivered)
                        if (status === 'delivered' || status === 'partially_delivered') {
                            clearInterval(pollTimer);
                            $("#fulfillment-polling-banner").slideUp();

                            // Reload page or dynamically update vouchers
                            setTimeout(function () {
                                location.reload();
                            }, 500);
                            return;
                        }

                        // Case 2: Terminal failure (supplier_failed)
                        if (status === 'supplier_failed') {
                            clearInterval(pollTimer);
                            $("#fulfillment-polling-banner").slideUp();
                            $("#supplier-failed-banner").removeClass('d-none').hide().slideDown();
                            $(".placeholder-voucher-card").find('.status-badge')
                                .removeClass('bg-soft-warning text-warning')
                                .addClass('bg-soft-danger text-danger')
                                .html('<i class="tio-clear-circle me-1"></i> Failed');
                            return;
                        }

                        // Case 3: Reached timeout without terminal state
                        if (pollAttempts >= maxPollAttempts) {
                            clearInterval(pollTimer);
                            $("#fulfillment-polling-banner").slideUp();
                            $("#polling-timeout-notice").removeClass('d-none').hide().slideDown();
                        }
                    },
                    error: function () {
                        if (pollAttempts >= maxPollAttempts) {
                            clearInterval(pollTimer);
                            $("#fulfillment-polling-banner").slideUp();
                            $("#polling-timeout-notice").removeClass('d-none').hide().slideDown();
                        }
                    }
                });
            }, pollInterval);
        }
    });
</script>
@endpush
