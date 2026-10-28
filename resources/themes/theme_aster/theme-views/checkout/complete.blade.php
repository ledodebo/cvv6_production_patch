@php
    use App\Utils\Helpers;
@endphp
@extends('theme-views.layouts.app')

@section('title', translate('order_Complete').' | '.$web_config['company_name'].' '.translate('ecommerce'))

@push('css_or_js')
<style>
    .digital-order-summary-box {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        border: 1px solid #e9ecef;
    }
    .digital-voucher-card {
        background: linear-gradient(135deg, #ffffff 0%, #fbfcfd 100%);
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        position: relative;
        overflow: hidden;
    }
    .digital-voucher-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
    }
    .digital-voucher-card::before {
        content: '';
        position: absolute;
        top: 0;
        inset-inline-start: 0;
        width: 6px;
        height: 100%;
        background: #0f9d58;
    }
    .voucher-code-badge {
        background: #f8fafc;
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
        width: 24px;
        height: 24px;
        top: 12px;
        left: 12px;
        border-radius: 50%;
        background: #0f9d58;
        animation: pulse 1.5s infinite;
    }
    @keyframes pulse {
        0% { transform: scale(0.8); opacity: 0.8; }
        50% { transform: scale(1.2); opacity: 1; }
        100% { transform: scale(0.8); opacity: 0.8; }
    }
    @media print {
        body * {
            visibility: hidden;
        }
        #digital-vouchers-container, #digital-vouchers-container * {
            visibility: visible;
        }
        #digital-vouchers-container {
            position: absolute;
            left: 0;
            top: 0;
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
        .digital-voucher-card::before, .no-print {
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
    if ($orders->isEmpty() && session()->has('recent_order_ids')) {
        $orders = \App\Models\Order::whereIn('id', (array)session('recent_order_ids'))
            ->with(['details.product', 'details.digitalCardCodes'])
            ->get();
    }
    if ($orders->isEmpty() && session()->has('order_success_ids')) {
        $orders = \App\Models\Order::whereIn('id', (array)session('order_success_ids'))
            ->with(['details.product', 'details.digitalCardCodes'])
            ->get();
    }
    if ($orders->isEmpty() && auth('customer')->check()) {
        $latest = \App\Models\Order::where('customer_id', auth('customer')->id())
            ->latest('id')
            ->with(['details.product', 'details.digitalCardCodes'])
            ->first();
        if ($latest && $latest->created_at->diffInMinutes(now()) < 120) {
            $orders = collect([$latest]);
        }
    }

    $primaryOrderId = $orders->first()?->id ?? (is_array($order_ids) ? ($order_ids[0] ?? null) : $order_ids);

    // Self-fulfillment on render: deliver pending or unfulfilled codes immediately
    if ($orders->isNotEmpty()) {
        foreach ($orders as $ord) {
            $needsFulfillment = false;
            foreach ($ord->details as $d) {
                if ($d->digitalCardCodes->count() < $d->qty && $d->fulfillment_status !== 'supplier_failed') {
                    $needsFulfillment = true;
                    break;
                }
            }
            if ($needsFulfillment || in_array($ord->fulfillment_status ?? 'pending', ['pending', 'purchasing', 'supplier_pending_review'])) {
                try {
                    app(\App\Services\DigitalOrderService::class)->fulfillOrder($ord->id);
                    $ord->refresh();
                    $ord->load(['details.product', 'details.digitalCardCodes']);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("Immediate fulfillment in theme_aster complete view failed for #{$ord->id}: " . $e->getMessage());
                }
            }
        }
    }

    $hasPendingFulfillment = false;
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

<main class="main-content py-4 mb-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9 col-md-11">

                {{-- 1. Order Success Header --}}
                <div class="digital-order-summary-box p-4 mb-4 text-center">
                    <div class="mb-3">
                        <i class="bi bi-check-circle-fill" style="font-size: 56px; color: #0f9d58;"></i>
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
                    <div class="d-flex flex-wrap justify-content-center gap-3 pt-3 border-top">
                        <div class="px-3 py-2 bg-light rounded text-start">
                            <small class="text-muted d-block">{{ translate('Order_ID') }}</small>
                            <span class="fw-bold text-dark">
                                @foreach ($orders as $ord)
                                    #{{ $ord->id }}@if(!$loop->last), @endif
                                @endforeach
                            </span>
                        </div>
                        <div class="px-3 py-2 bg-light rounded text-start">
                            <small class="text-muted d-block">{{ translate('Date') }}</small>
                            <span class="fw-bold text-dark">{{ now()->format('M d, Y h:i A') }}</span>
                        </div>
                        <div class="px-3 py-2 bg-light rounded text-start">
                            <small class="text-muted d-block">{{ translate('Payment_Method') }}</small>
                            <span class="fw-bold text-uppercase text-dark">
                                {{ str_replace('_', ' ', $orders->first()?->payment_method ?? 'Online') }}
                            </span>
                        </div>
                        <div class="px-3 py-2 bg-light rounded text-start">
                            <small class="text-muted d-block">{{ translate('Total_Amount') }}</small>
                            <span class="fw-bold text-success">
                                {{ webCurrencyConverter($orders->sum('order_amount')) }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- 2. Supplier Procurement Polling Banner --}}
                <div id="fulfillment-polling-banner" class="fulfillment-pulsing-box mb-4 @if(!$hasPendingFulfillment) d-none @endif">
                    <div class="pulse-animation mb-3"></div>
                    <h5 class="fw-bold text-dark mb-1">{{ translate('Procuring your digital card from supplier...') }}</h5>
                    <p class="text-muted fs-13 mb-3">{{ translate('Please wait a moment while your digital codes are being finalized. This usually takes 5 to 15 seconds.') }}</p>
                    <div class="progress" style="height: 6px; max-width: 320px; margin: 0 auto;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 100%"></div>
                    </div>
                </div>

                {{-- 3. Supplier Failure & Refund Banner --}}
                @php
                    $isSupplierFailed = ($orders->first()?->fulfillment_status === 'supplier_failed');
                    $supplierFailReason = $orders->first()?->details->first()?->fulfillment_error ?? null;
                @endphp
                <div id="supplier-failed-banner" class="alert alert-danger p-4 mb-4 rounded-3 @if(!$isSupplierFailed) d-none @endif">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-exclamation-triangle-fill fs-1 text-danger"></i>
                        <div class="flex-grow-1">
                            <h5 class="fw-bold mb-1">{{ translate('Unable to procure card from supplier') }}</h5>
                            <p class="mb-0 fs-13">
                                {{ translate('Unable to procure this card from the supplier. Your payment has been automatically refunded to your Wallet.') }}
                                @if(!empty($supplierFailReason))
                                    <br><span class="badge bg-danger-subtle text-danger mt-1 p-1 px-2">{{ $supplierFailReason }}</span>
                                @endif
                            </p>
                        </div>
                        <div>
                            <a href="{{ route('wallet') }}" class="btn btn-warning fw-bold text-dark text-nowrap">
                                <i class="bi bi-wallet2 me-1"></i> {{ translate('View_Wallet') }}
                            </a>
                        </div>
                    </div>
                </div>

                {{-- 4. Polling Timeout Notice --}}
                <div id="polling-timeout-notice" class="alert alert-info p-3 mb-4 rounded-3 d-none">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-info-circle fs-5 text-info"></i>
                        <p class="mb-0 fs-13">
                            {{ translate('Your order is taking a little longer than usual to finalize. Your codes will appear in My Orders shortly and we will notify you by email.') }}
                        </p>
                    </div>
                </div>

                {{-- 5. Digital Vouchers List Container --}}
                <div id="digital-vouchers-container" class="mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="fw-bold text-dark mb-0">
                            <i class="bi bi-gift-fill me-1 text-primary"></i> {{ translate('Your_Digital_Cards_&_Vouchers') }}
                        </h5>
                        <button type="button" class="btn btn-outline-secondary btn-sm no-print" onclick="printDigitalVouchers()">
                            <i class="bi bi-printer me-1"></i> {{ translate('Print_/_Save_PDF') }}
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
                                                    <span class="badge bg-primary-subtle text-primary px-2 py-1 fs-12">
                                                        {{ $detail->variant }}
                                                    </span>
                                                @endif
                                            </div>
                                            <div>
                                                <span class="badge bg-success-subtle text-success px-3 py-1 fs-12">
                                                    <i class="bi bi-check-circle-fill me-1"></i> {{ translate('Delivered') }}
                                                </span>
                                            </div>
                                        </div>

                                        <div class="row align-items-center g-3">
                                            {{-- Voucher Code Display --}}
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
                                                            <i class="bi bi-eye me-1"></i> {{ translate('Reveal') }}
                                                        </button>
                                                        <button type="button"
                                                                class="btn btn-sm btn-primary btn-copy-code"
                                                                data-copied-text="{{ translate('Copied!') }}"
                                                                onclick="copyDigitalCardCode(this, {{ $cardCode->id }}, '{{ route('account-order-digital-codes.reveal', $cardCode->id) }}')">
                                                            <i class="bi bi-clipboard me-1"></i> {{ translate('Copy') }}
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
                                        <div class="mt-3 pt-2 text-muted fs-12 border-top">
                                            <i class="bi bi-info-circle me-1 text-primary"></i>
                                            {{ translate('How_to_redeem: Log into your account on the provider platform, go to Redeem Gift Card/Code, and paste your code.') }}
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                {{-- Placeholder card while waiting for fulfillment job to generate codes --}}
                                <div class="digital-voucher-card p-4 mb-3 placeholder-voucher-card" data-detail-id="{{ $detail->id }}">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="fw-bold text-dark mb-0">{{ $productName }}</h6>
                                        <span class="badge bg-warning-subtle text-warning px-2 py-1 fs-12 status-badge">
                                            <i class="bi bi-clock-history me-1"></i> {{ translate(ucfirst(str_replace('_', ' ', $detail->fulfillment_status ?? 'Purchasing...'))) }}
                                        </span>
                                    </div>
                                    @if($detail->fulfillment_status === 'supplier_pending_review')
                                        <div class="alert alert-warning py-2 px-3 mt-2 mb-0 fs-13 d-flex align-items-center justify-content-between">
                                            <span>
                                                <i class="bi bi-exclamation-circle me-1"></i>
                                                {{ translate('Supplier is processing your voucher. Please click Refresh or check back shortly.') }}
                                            </span>
                                            <button type="button" class="btn btn-sm btn-outline-dark py-0 px-2" onclick="window.location.reload()">
                                                <i class="bi bi-arrow-clockwise me-1"></i> {{ translate('Refresh') }}
                                            </button>
                                        </div>
                                    @else
                                        <p class="text-muted fs-13 mb-0">
                                            {{ translate('Your card code is being generated. It will appear here automatically momentarily.') }}
                                        </p>
                                    @endif
                                </div>
                            @endif
                        @endforeach
                    @endforeach
                </div>

                {{-- 6. Navigation Buttons --}}
                <div class="row mt-4 no-print">
                    <div class="col-12 text-center d-flex justify-content-center gap-3">
                        <a href="{{ route('account-oder') }}" class="btn btn-primary px-4">
                            <i class="bi bi-file-earmark-text me-1"></i> {{ translate('View_in_My_Orders') }}
                        </a>
                        <a href="{{ route('home') }}" class="btn btn-outline-secondary px-4">
                            {{ translate('Continue_Shopping') }}
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</main>
@endsection

@push('script')
<script>
    "use strict";

    window.digitalCardCodeMemoryCache = window.digitalCardCodeMemoryCache || {};

    function getDigitalCsrfToken() {
        return $('meta[name="_token"]').attr('content') ||
               $('meta[name="csrf-token"]').attr('content') ||
               $('input[name="_token"]').val() ||
               '{{ csrf_token() }}';
    }

    window.revealDigitalCardCode = function(btnElement, codeId, revealUrl) {
        const $btn = $(btnElement);
        const $voucherCard = $btn.closest('.digital-voucher-card');
        const $codeDisplay = $voucherCard.find('.voucher-code-display');
        const $pinDisplay = $voucherCard.find('.voucher-pin-display');
        const maskedCode = $codeDisplay.data('masked') || '••••-••••-••••';

        if ($btn.data('revealed') === true) {
            $codeDisplay.text(maskedCode);
            if ($pinDisplay.length) {
                $pinDisplay.text('••••');
            }
            $btn.data('revealed', false);
            $btn.html('<i class="bi bi-eye me-1"></i> ' + ($btn.data('reveal-text') || 'Reveal'));
            return;
        }

        if (window.digitalCardCodeMemoryCache[codeId]) {
            const cached = window.digitalCardCodeMemoryCache[codeId];
            $codeDisplay.text(cached.code);
            if ($pinDisplay.length && cached.pin) {
                $pinDisplay.text(cached.pin);
            }
            $btn.data('revealed', true);
            $btn.html('<i class="bi bi-eye-slash me-1"></i> ' + ($btn.data('hide-text') || 'Hide'));
            return;
        }

        $btn.prop('disabled', true);
        $.ajax({
            url: revealUrl,
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': getDigitalCsrfToken(),
                'Accept': 'application/json'
            },
            data: {
                _token: getDigitalCsrfToken(),
            },
            success: function (res) {
                if (res.status === 1 && res.code) {
                    window.digitalCardCodeMemoryCache[codeId] = {
                        code: res.code,
                        pin: res.pin || null,
                    };
                    $codeDisplay.text(res.code);
                    if ($pinDisplay.length && res.pin) {
                        $pinDisplay.text(res.pin);
                    }
                    $btn.data('revealed', true);
                    $btn.html('<i class="bi bi-eye-slash me-1"></i> ' + ($btn.data('hide-text') || 'Hide'));
                } else {
                    const msg = res.message || 'Unable to reveal card code.';
                    if (typeof toastr !== 'undefined') {
                        toastr.error(msg);
                    } else {
                        alert(msg);
                    }
                }
            },
            error: function (xhr) {
                let msg = 'Failed to reveal digital code. Please verify you are logged in.';
                if (xhr.status === 419) {
                    msg = 'Session expired. Please refresh the page.';
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                if (typeof toastr !== 'undefined') {
                    toastr.error(msg);
                } else {
                    alert(msg);
                }
            },
            complete: function () {
                $btn.prop('disabled', false);
            }
        });
    };

    window.copyDigitalCardCode = function(btnElement, codeId, revealUrl) {
        const $btn = $(btnElement);
        const originalHtml = $btn.html();

        function showCopySuccess() {
            $btn.html('<i class="bi bi-check-circle-fill me-1"></i> ' + ($btn.data('copied-text') || 'Copied!'));
            $btn.removeClass('btn-primary').addClass('btn-success');
            setTimeout(function () {
                $btn.html(originalHtml);
                $btn.removeClass('btn-success').addClass('btn-primary');
            }, 2500);
        }

        function copyTextToClipboard(text) {
            if (navigator.clipboard && window.isSecureContext) {
                return navigator.clipboard.writeText(text);
            } else {
                const textArea = document.createElement("textarea");
                textArea.value = text;
                textArea.style.position = "fixed";
                textArea.style.left = "-999999px";
                textArea.style.top = "-999999px";
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                return new Promise(function(resolve, reject) {
                    document.execCommand('copy') ? resolve() : reject();
                    textArea.remove();
                });
            }
        }

        if (window.digitalCardCodeMemoryCache[codeId]) {
            copyTextToClipboard(window.digitalCardCodeMemoryCache[codeId].code)
                .then(showCopySuccess)
                .catch(function () {
                    alert('Copy failed. Please manually select the code.');
                });
            return;
        }

        $btn.prop('disabled', true);
        $.ajax({
            url: revealUrl,
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': getDigitalCsrfToken(),
                'Accept': 'application/json'
            },
            data: {
                _token: getDigitalCsrfToken(),
            },
            success: function (res) {
                if (res.status === 1 && res.code) {
                    window.digitalCardCodeMemoryCache[codeId] = {
                        code: res.code,
                        pin: res.pin || null,
                    };
                    const $voucherCard = $btn.closest('.digital-voucher-card');
                    $voucherCard.find('.voucher-code-display').text(res.code);
                    if (res.pin) {
                        $voucherCard.find('.voucher-pin-display').text(res.pin);
                    }
                    $voucherCard.find('.btn-reveal-code').data('revealed', true).html('<i class="bi bi-eye-slash me-1"></i> Hide');

                    copyTextToClipboard(res.code)
                        .then(showCopySuccess)
                        .catch(function () {
                            alert('Copy failed. Code is now revealed on screen.');
                        });
                } else {
                    alert(res.message || 'Unable to copy code.');
                }
            },
            error: function () {
                alert('Network error while retrieving code for clipboard.');
            },
            complete: function () {
                $btn.prop('disabled', false);
            }
        });
    };

    window.printDigitalVouchers = function() {
        window.print();
    };

    $(document).ready(function () {
        const orderId = "{{ $primaryOrderId }}";
        const hasPending = {{ $hasPendingFulfillment ? 'true' : 'false' }};

        if (hasPending && orderId) {
            let pollAttempts = 0;
            const maxPollAttempts = 10;
            const pollInterval = 2500;

            const pollTimer = setInterval(function () {
                pollAttempts++;

                $.ajax({
                    url: "{{ url('account-order-digital-codes') }}/" + orderId,
                    type: "GET",
                    headers: {
                        "X-CSRF-TOKEN": getDigitalCsrfToken()
                    },
                    success: function (res) {
                        if (!res || !res.success) return;

                        const status = res.fulfillment_status;

                        if (status === 'delivered' || status === 'partially_delivered') {
                            clearInterval(pollTimer);
                            $("#fulfillment-polling-banner").slideUp();
                            setTimeout(function () {
                                location.reload();
                            }, 500);
                            return;
                        }

                        if (status === 'supplier_failed') {
                            clearInterval(pollTimer);
                            $("#fulfillment-polling-banner").slideUp();
                            $("#supplier-failed-banner").removeClass('d-none').hide().slideDown();
                            $(".placeholder-voucher-card").find('.status-badge')
                                .removeClass('bg-warning-subtle text-warning')
                                .addClass('bg-danger-subtle text-danger')
                                .html('<i class="bi bi-x-circle me-1"></i> Failed');
                            return;
                        }

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
