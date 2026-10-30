@extends('layouts.admin.app')

@section('title', translate('Supplier_&_Mock_Mode_Settings'))

@push('css_or_js')
<style>
    .supplier-status-banner {
        border-radius: 14px;
        transition: all 0.35s ease;
        position: relative;
        overflow: hidden;
    }
    .supplier-status-banner.mock-active {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.12) 0%, rgba(5, 150, 105, 0.06) 100%);
        border: 2px solid #10b981;
    }
    .supplier-status-banner.live-active {
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.12) 0%, rgba(245, 158, 11, 0.08) 100%);
        border: 2px solid #f59e0b;
    }
    .status-pulse-dot {
        width: 14px;
        height: 14px;
        border-radius: 50%;
        display: inline-block;
        position: relative;
    }
    .status-pulse-dot.pulse-green {
        background-color: #10b981;
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
        animation: pulseGreen 1.8s infinite;
    }
    .status-pulse-dot.pulse-amber {
        background-color: #f59e0b;
        box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7);
        animation: pulseAmber 1.8s infinite;
    }
    @keyframes pulseGreen {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
        70% { transform: scale(1.1); box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }
    @keyframes pulseAmber {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7); }
        70% { transform: scale(1.1); box-shadow: 0 0 0 10px rgba(245, 158, 11, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
    }
    .stat-card-clean {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 18px 20px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .stat-card-clean:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px -5px rgba(0, 0, 0, 0.05);
    }
    .toggle-pill-container {
        background: #ffffff;
        border: 1px solid #d1d5db;
        border-radius: 100px;
        padding: 6px 16px;
        display: inline-flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }
    .code-mask-btn {
        cursor: pointer;
        user-select: none;
    }
</style>
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="mb-3 mb-sm-20">
            <h2 class="h1 mb-0 text-capitalize d-flex align-items-center gap-2">
                {{ translate('system_Setup') }}
            </h2>
        </div>

        @include('admin-views.system-setup.system-settings-inline-menu')

        <div class="d-flex flex-column gap-3 gap-sm-20">

            {{-- Dynamic Hero Banner for Mock Mode --}}
            <div id="supplier-banner" class="supplier-status-banner p-20 p-sm-4 {{ $config['is_mock'] ? 'mock-active' : 'live-active' }}">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div class="d-flex align-items-start gap-3">
                        <div class="mt-1">
                            <span id="banner-pulse-dot" class="status-pulse-dot {{ $config['is_mock'] ? 'pulse-green' : 'pulse-amber' }}"></span>
                        </div>
                        <div>
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <h3 id="banner-title" class="mb-0 fw-bold fs-20 {{ $config['is_mock'] ? 'text-success' : 'text-warning' }}">
                                    {{ $config['is_mock'] ? translate('FOXRELOAD_MOCK_MODE_ACTIVE_(SANDBOX)') : translate('FOXRELOAD_LIVE_PRODUCTION_ACTIVE') }}
                                </h3>
                                <span id="banner-badge" class="badge {{ $config['is_mock'] ? 'badge-soft-success text-success' : 'badge-soft-warning text-warning' }} fs-12 px-3 py-1 fw-semibold">
                                    {{ $config['is_mock'] ? 'FOXRELOAD_IS_MOCK=true' : 'FOXRELOAD_IS_MOCK=false' }}
                                </span>
                            </div>
                            <p id="banner-desc" class="mb-0 fs-13 text-secondary" style="max-width: 780px;">
                                @if($config['is_mock'])
                                    <strong>{{ translate('Simulated Testing Mode:') }}</strong> {{ translate('Customer digital gift card orders generate authentic simulated codes instantly without hitting FoxReload API endpoints or deducting your wholesale account USD balance. Perfect for QA, demoing, and frontend tests.') }}
                                @else
                                    <strong>{{ translate('Live Production Mode:') }}</strong> {{ translate('Customer digital gift card orders trigger real purchases from FoxReload Public API. Real wholesale account balance is deducted in real-time and real vendor vouchers are issued.') }}
                                @endif
                            </p>
                        </div>
                    </div>

                    {{-- Quick 1-Click Toggle Widget --}}
                    <div class="d-flex flex-column align-items-md-end gap-2">
                        <div class="toggle-pill-container">
                            <span class="fs-13 fw-bold text-dark" id="toggle-label">
                                {{ $config['is_mock'] ? translate('Mock_Mode_ON') : translate('Live_Mode_ON') }}
                            </span>
                            <label class="switcher mb-0" for="mock-mode-toggle">
                                <input
                                    type="checkbox"
                                    class="switcher_input"
                                    id="mock-mode-toggle"
                                    value="1"
                                    {{ $config['is_mock'] ? 'checked' : '' }}
                                    data-url="{{ route('admin.system-setup.supplier-mock-status') }}">
                                <span class="switcher_control"></span>
                            </label>
                        </div>
                        <small class="text-muted fs-11" id="toggle-hint">
                            <i class="fi fi-sr-info"></i> {{ translate('Changes_take_effect_immediately_in_.env') }}
                        </small>
                    </div>
                </div>
            </div>

            {{-- Stat KPI Row --}}
            <div class="row g-3">
                <div class="col-sm-6 col-lg-3">
                    <div class="stat-card-clean">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fs-12 text-muted fw-medium">{{ translate('Simulated_Mock_Codes') }}</span>
                            <span class="badge bg-success bg-opacity-10 text-success p-2 rounded-circle">
                                <i class="fi fi-sr-shield-check"></i>
                            </span>
                        </div>
                        <h3 class="mb-1 fw-bold fs-24 text-dark">{{ number_format($config['total_mock_codes']) }}</h3>
                        <span class="fs-11 text-muted">{{ translate('Locally_generated_test_vouchers') }}</span>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <div class="stat-card-clean">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fs-12 text-muted fw-medium">{{ translate('Total_Digital_Cards_Issued') }}</span>
                            <span class="badge bg-primary bg-opacity-10 text-primary p-2 rounded-circle">
                                <i class="fi fi-sr-credit-card"></i>
                            </span>
                        </div>
                        <h3 class="mb-1 fw-bold fs-24 text-dark">{{ number_format($config['total_delivered_codes']) }}</h3>
                        <span class="fs-11 text-muted">{{ translate('Vault_encrypted_delivered_codes') }}</span>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <div class="stat-card-clean">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fs-12 text-muted fw-medium">{{ translate('Mapped_Catalog_Products') }}</span>
                            <span class="badge bg-info bg-opacity-10 text-info p-2 rounded-circle">
                                <i class="fi fi-sr-boxes"></i>
                            </span>
                        </div>
                        <h3 class="mb-1 fw-bold fs-24 text-dark">{{ number_format($config['total_mapped_products']) }}</h3>
                        <span class="fs-11 text-muted">{{ translate('Linked_to_FoxReload_supplier_API') }}</span>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <div class="stat-card-clean">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fs-12 text-muted fw-medium">{{ translate('Sandbox_Payment_Gateway') }}</span>
                            <span class="badge {{ $config['sandbox_gateway'] ? 'bg-success' : 'bg-secondary' }} bg-opacity-10 {{ $config['sandbox_gateway'] ? 'text-success' : 'text-secondary' }} p-2 rounded-circle">
                                <i class="fi fi-sr-bolt"></i>
                            </span>
                        </div>
                        <h3 class="mb-1 fw-bold fs-24 {{ $config['sandbox_gateway'] ? 'text-success' : 'text-muted' }}">
                            {{ $config['sandbox_gateway'] ? translate('Enabled') : translate('Disabled') }}
                        </h3>
                        <span class="fs-11 text-muted">{{ translate('1-Click_checkout_testing') }}</span>
                    </div>
                </div>
            </div>

            {{-- Live API Status & Balance Card --}}
            <div class="card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom">
                    <div>
                        <h4 class="card-title mb-1 d-flex align-items-center gap-2">
                            <i class="fi fi-sr-network-cloud text-primary"></i>
                            {{ translate('FoxReload_Wholesale_API_Connectivity_&_Balance') }}
                        </h4>
                        <p class="fs-12 text-muted mb-0">
                            {{ translate('Test_live_connectivity_to_FoxReload_servers_and_inspect_current_USD_available_funds.') }}
                        </p>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm px-3 d-flex align-items-center gap-2" id="check-connection-btn" data-url="{{ route('admin.system-setup.supplier-check-connection') }}">
                        <i class="fi fi-sr-refresh" id="connection-btn-icon"></i>
                        <span id="connection-btn-text">{{ translate('Test_Connection_&_Check_Balance') }}</span>
                    </button>
                </div>
                <div class="card-body">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-4">
                            <div class="p-3 bg-light rounded d-flex flex-column gap-1">
                                <span class="fs-11 text-muted text-uppercase fw-semibold">{{ translate('Connection_Status') }}</span>
                                <div class="d-flex align-items-center gap-2" id="connection-status-pill">
                                    <span class="badge badge-soft-secondary fs-12 px-2 py-1">
                                        <i class="fi fi-sr-bullet"></i> {{ translate('Ready_to_Ping') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 bg-light rounded d-flex flex-column gap-1">
                                <span class="fs-11 text-muted text-uppercase fw-semibold">{{ translate('FoxReload_Wholesale_Balance') }}</span>
                                <div class="fs-18 fw-bold text-dark" id="balance-display">
                                    <span class="text-muted fs-14">---</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 bg-light rounded d-flex flex-column gap-1">
                                <span class="fs-11 text-muted text-uppercase fw-semibold">{{ translate('API_Latency_Response_Time') }}</span>
                                <div class="fs-18 fw-bold text-dark" id="latency-display">
                                    <span class="text-muted fs-14">---</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 d-none" id="connection-feedback-alert">
                        <div class="alert mb-0 fs-12 py-2" id="connection-feedback-text"></div>
                    </div>
                </div>
            </div>

            {{-- Full Supplier Credentials and Controls Form --}}
            <form action="{{ route('admin.system-setup.supplier-settings') }}" method="POST">
                @csrf
                <div class="card">
                    <div class="card-header border-bottom">
                        <h4 class="card-title mb-1 d-flex align-items-center gap-2">
                            <i class="fi fi-sr-settings-sliders text-primary"></i>
                            {{ translate('Environment_Configuration_(.env)') }}
                        </h4>
                        <p class="fs-12 text-muted mb-0">
                            {{ translate('Parameters_saved_here_directly_update_the_corresponding_variables_in_your_.env_file.') }}
                        </p>
                    </div>

                    <div class="card-body">
                        <div class="row g-4">

                            {{-- Mock Mode Radio Selection --}}
                            <div class="col-12">
                                <div class="form-group mb-0">
                                    <label class="form-label text-capitalize fw-bold">
                                        {{ translate('Fulfillment_Mode') }} (<code>FOXRELOAD_IS_MOCK</code>)
                                    </label>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="border rounded p-3 h-100 {{ $config['is_mock'] ? 'border-success bg-success bg-opacity-10' : '' }}" style="cursor: pointer;" onclick="document.getElementById('radio_mock_true').checked = true; updateVisualMode(true);">
                                                <div class="d-flex align-items-start gap-3">
                                                    <input type="radio" class="mt-1" name="is_mock" id="radio_mock_true" value="true" {{ $config['is_mock'] ? 'checked' : '' }}>
                                                    <div>
                                                        <label class="fw-bold mb-1 d-flex align-items-center gap-2 text-dark" for="radio_mock_true" style="cursor: pointer;">
                                                            <span class="badge bg-success text-white fs-11">{{ translate('MOCK_SANDBOX') }}</span>
                                                            {{ translate('Simulated_Gift_Cards') }}
                                                        </label>
                                                        <p class="fs-12 text-secondary mb-0">
                                                            {{ translate('Generate_authentic_simulated_digital_codes_locally_(GBC-XXXX-XXXX-XXXX)._Zero_balance_deducted,_no_external_API_calls.') }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="border rounded p-3 h-100 {{ !$config['is_mock'] ? 'border-warning bg-warning bg-opacity-10' : '' }}" style="cursor: pointer;" onclick="document.getElementById('radio_mock_false').checked = true; updateVisualMode(false);">
                                                <div class="d-flex align-items-start gap-3">
                                                    <input type="radio" class="mt-1" name="is_mock" id="radio_mock_false" value="false" {{ !$config['is_mock'] ? 'checked' : '' }}>
                                                    <div>
                                                        <label class="fw-bold mb-1 d-flex align-items-center gap-2 text-dark" for="radio_mock_false" style="cursor: pointer;">
                                                            <span class="badge bg-warning text-dark fs-11">{{ translate('LIVE_API') }}</span>
                                                            {{ translate('Real_FoxReload_Procurement') }}
                                                        </label>
                                                        <p class="fs-12 text-secondary mb-0">
                                                            {{ translate('Fulfill_orders_by_calling_FoxReload_Public_API_createOrder_endpoint._Debits_live_wholesale_USD_balance.') }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Base URL --}}
                            <div class="col-md-6">
                                <div class="form-group mb-0">
                                    <label class="form-label text-capitalize" for="base_url">
                                        {{ translate('FoxReload_API_Base_URL') }} (<code>FOXRELOAD_BASE_URL</code>)
                                    </label>
                                    <input type="text"
                                           name="base_url"
                                           id="base_url"
                                           class="form-control"
                                           value="{{ $config['base_url'] }}"
                                           placeholder="https://public-api.foxreload.com/api"
                                           required>
                                    <small class="text-muted fs-11">{{ translate('Standard_endpoint:') }} <code>https://public-api.foxreload.com/api</code></small>
                                </div>
                            </div>

                            {{-- API Key --}}
                            <div class="col-md-6">
                                <div class="form-group mb-0">
                                    <label class="form-label text-capitalize d-flex justify-content-between" for="api_key">
                                        <span>{{ translate('FoxReload_Wholesale_API_Key') }} (<code>FOXRELOAD_API_KEY</code>)</span>
                                        <span class="text-primary fs-11 code-mask-btn" id="toggle-key-visibility">
                                            <i class="fi fi-sr-eye" id="key-eye-icon"></i> <span id="key-eye-text">{{ translate('Show') }}</span>
                                        </span>
                                    </label>
                                    <input type="password"
                                           name="api_key"
                                           id="api_key"
                                           class="form-control font-monospace"
                                           value="{{ $config['api_key'] }}"
                                           placeholder="dmk_..."
                                           required>
                                    <small class="text-muted fs-11">{{ translate('Your_secure_DMK_supplier_credential') }}</small>
                                </div>
                            </div>

                            {{-- Profit Markup Percent --}}
                            <div class="col-md-4">
                                <div class="form-group mb-0">
                                    <label class="form-label text-capitalize" for="markup_percent">
                                        {{ translate('Default_Markup_Percentage') }} (%) (<code>FOXRELOAD_MARKUP_PERCENT</code>)
                                    </label>
                                    <div class="input-group">
                                        <input type="number"
                                               step="0.1"
                                               name="markup_percent"
                                               id="markup_percent"
                                               class="form-control"
                                               value="{{ $config['markup_percent'] }}"
                                               placeholder="10">
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <small class="text-muted fs-11">{{ translate('Profit_margin_applied_over_wholesale_cost') }}</small>
                                </div>
                            </div>

                            {{-- Balance Alert Threshold --}}
                            <div class="col-md-4">
                                <div class="form-group mb-0">
                                    <label class="form-label text-capitalize" for="balance_alert">
                                        {{ translate('Low_Balance_Alert_Threshold') }} ($) (<code>FOXRELOAD_BALANCE_ALERT_THRESHOLD</code>)
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number"
                                               step="1"
                                               name="balance_alert"
                                               id="balance_alert"
                                               class="form-control"
                                               value="{{ $config['balance_alert'] }}"
                                               placeholder="50">
                                        <span class="input-group-text">USD</span>
                                    </div>
                                    <small class="text-muted fs-11">{{ translate('Admin_warning_triggers_below_this_USD_amount') }}</small>
                                </div>
                            </div>

                            {{-- Sandbox Gateway Toggle --}}
                            <div class="col-md-4">
                                <div class="form-group mb-0">
                                    <label class="form-label text-capitalize" for="sandbox_gateway">
                                        {{ translate('Customer_Payment_Sandbox_Gateway') }} (<code>PAYMENT_GATEWAY_SANDBOX</code>)
                                    </label>
                                    <div class="d-flex justify-content-between align-items-center border rounded px-3 py-2 bg-light">
                                        <span class="fs-12 fw-medium text-dark">{{ translate('Sandbox_1-Click_Checkout') }}</span>
                                        <label class="switcher mb-0" for="sandbox_gateway_input">
                                            <input type="checkbox"
                                                   class="switcher_input"
                                                   name="sandbox_gateway"
                                                   id="sandbox_gateway_input"
                                                   value="1"
                                                   {{ $config['sandbox_gateway'] ? 'checked' : '' }}>
                                            <span class="switcher_control"></span>
                                        </label>
                                    </div>
                                    <small class="text-muted fs-11">{{ translate('Allows_simulated_checkout_without_real_credit_card/PayPal') }}</small>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="card-footer d-flex justify-content-end gap-3 bg-white border-top">
                        <button type="reset" class="btn btn-secondary px-4">{{ translate('reset') }}</button>
                        <button type="submit" class="btn btn-primary px-4 d-flex align-items-center gap-2">
                            <i class="fi fi-sr-disk"></i>
                            {{ translate('Save_Supplier_Configuration') }}
                        </button>
                    </div>
                </div>
            </form>

            {{-- Quick Guide & Architecture Summary --}}
            <div class="card">
                <div class="card-header border-bottom">
                    <h5 class="mb-0 text-dark d-flex align-items-center gap-2">
                        <i class="fi fi-sr-interrogation text-info"></i>
                        {{ translate('Understanding_Mock_Mode_vs_Live_Procurement') }}
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="p-3 border rounded-8 h-100 bg-light">
                                <h6 class="fw-bold text-success mb-2 d-flex align-items-center gap-2">
                                    <i class="fi fi-sr-shield-check"></i> {{ translate('When_Mock_Mode_is_Turned_ON_(true)') }}
                                </h6>
                                <ul class="fs-12 text-secondary mb-0 ps-3 d-flex flex-column gap-1">
                                    <li>{{ translate('Simulated_vouchers_are_generated_locally_in_the_format') }} <code>GBC-XXXX-XXXX-XXXX</code>.</li>
                                    <li>{{ translate('Fulfillment_is_instantaneous_(<1s)_without_waiting_for_upstream_APIs.') }}</li>
                                    <li>{{ translate('Zero_API_calls_are_made_to_FoxReload,_ensuring_your_wholesale_USD_balance_remains_untouched.') }}</li>
                                    <li>{{ translate('PINs_are_simulated_as') }} <code>1234</code> {{ translate('for_reliable_vault_encryption_and_reveal_UX_testing.') }}</li>
                                    <li>{{ translate('Ideal_for_development,_UI_polishing,_mobile_app_testing,_and_QA_sign-offs.') }}</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 border rounded-8 h-100 bg-light">
                                <h6 class="fw-bold text-warning mb-2 d-flex align-items-center gap-2">
                                    <i class="fi fi-sr-bolt"></i> {{ translate('When_Mock_Mode_is_Turned_OFF_(false)') }}
                                </h6>
                                <ul class="fs-12 text-secondary mb-0 ps-3 d-flex flex-column gap-1">
                                    <li>{{ translate('Every_customer_order_sends_a_live_request_to_FoxReload_public_API.') }}</li>
                                    <li>{{ translate('FoxReload_verifies_wholesale_balance_and_debits_cost_price_in_real-time.') }}</li>
                                    <li>{{ translate('Real_vendor_serials_and_PINs_are_fetched_and_stored_in_the_AES-256_vault.') }}</li>
                                    <li>{{ translate('If_balance_is_insufficient,_automatic_item_failure_and_admin_alerts_are_dispatched.') }}</li>
                                    <li>{{ translate('Requires_active_API_Key_and_funded_FoxReload_balance.') }}</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection

@push('script')
<script>
    "use strict";

    // 1. Instant AJAX Toggle for Mock Mode
    $('#mock-mode-toggle').on('change', function () {
        let isChecked = $(this).is(':checked');
        let toggleUrl = $(this).data('url');

        // Optimistically update UI
        updateVisualMode(isChecked);

        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content') || $('meta[name="csrf-token"]').attr('content')
            }
        });

        $.ajax({
            url: toggleUrl,
            type: 'POST',
            data: {
                status: isChecked ? 1 : 0
            },
            beforeSend: function () {
                $('#toggle-hint').html('<i class="fi fi-sr-spinner fa-spin"></i> ' + '{{ translate("Updating_.env_and_clearing_cache...") }}');
            },
            success: function (response) {
                if (response.success) {
                    toastr.success(response.message, '{{ translate("Supplier_Mode_Updated") }}', {
                        CloseButton: true,
                        ProgressBar: true
                    });
                    $('#toggle-hint').html('<i class="fi fi-sr-check text-success"></i> ' + '{{ translate("Active_in_.env_file") }}');
                } else {
                    toastr.error(response.message || '{{ translate("Failed_to_update_mock_mode") }}');
                    // Revert switch on failure
                    $('#mock-mode-toggle').prop('checked', !isChecked);
                    updateVisualMode(!isChecked);
                    $('#toggle-hint').html('<i class="fi fi-sr-cross text-danger"></i> ' + '{{ translate("Update_failed") }}');
                }
            },
            error: function (xhr) {
                let err = xhr.responseJSON ? xhr.responseJSON.message : '{{ translate("Connection_error") }}';
                toastr.error(err);
                $('#mock-mode-toggle').prop('checked', !isChecked);
                updateVisualMode(!isChecked);
                $('#toggle-hint').html('<i class="fi fi-sr-cross text-danger"></i> ' + '{{ translate("Error_occurred") }}');
            }
        });
    });

    // Function to visually switch hero banner and badges
    function updateVisualMode(isMock) {
        let banner = $('#supplier-banner');
        let dot = $('#banner-pulse-dot');
        let title = $('#banner-title');
        let badge = $('#banner-badge');
        let desc = $('#banner-desc');
        let label = $('#toggle-label');

        if (isMock) {
            banner.removeClass('live-active').addClass('mock-active');
            dot.removeClass('pulse-amber').addClass('pulse-green');
            title.removeClass('text-warning').addClass('text-success').text('{{ translate("FOXRELOAD_MOCK_MODE_ACTIVE_(SANDBOX)") }}');
            badge.removeClass('badge-soft-warning text-warning').addClass('badge-soft-success text-success').text('FOXRELOAD_IS_MOCK=true');
            desc.html('<strong>{{ translate("Simulated Testing Mode:") }}</strong> {{ translate("Customer digital gift card orders generate authentic simulated codes instantly without hitting FoxReload API endpoints or deducting your wholesale account USD balance. Perfect for QA, demoing, and frontend tests.") }}');
            label.text('{{ translate("Mock_Mode_ON") }}');
            $('#radio_mock_true').prop('checked', true);
            $('#radio_mock_false').closest('.border').removeClass('border-warning bg-warning bg-opacity-10');
            $('#radio_mock_true').closest('.border').addClass('border-success bg-success bg-opacity-10');
        } else {
            banner.removeClass('mock-active').addClass('live-active');
            dot.removeClass('pulse-green').addClass('pulse-amber');
            title.removeClass('text-success').addClass('text-warning').text('{{ translate("FOXRELOAD_LIVE_PRODUCTION_ACTIVE") }}');
            badge.removeClass('badge-soft-success text-success').addClass('badge-soft-warning text-warning').text('FOXRELOAD_IS_MOCK=false');
            desc.html('<strong>{{ translate("Live Production Mode:") }}</strong> {{ translate("Customer digital gift card orders trigger real purchases from FoxReload Public API. Real wholesale account balance is deducted in real-time and real vendor vouchers are issued.") }}');
            label.text('{{ translate("Live_Mode_ON") }}');
            $('#radio_mock_false').prop('checked', true);
            $('#radio_mock_true').closest('.border').removeClass('border-success bg-success bg-opacity-10');
            $('#radio_mock_false').closest('.border').addClass('border-warning bg-warning bg-opacity-10');
        }
    }

    // 2. Test FoxReload API Connection & Balance
    $('#check-connection-btn').on('click', function () {
        let btn = $(this);
        let checkUrl = btn.data('url');
        let icon = $('#connection-btn-icon');
        let text = $('#connection-btn-text');
        let statusPill = $('#connection-status-pill');
        let balanceDisplay = $('#balance-display');
        let latencyDisplay = $('#latency-display');
        let alertContainer = $('#connection-feedback-alert');
        let alertText = $('#connection-feedback-text');

        btn.prop('disabled', true);
        icon.addClass('fa-spin');
        text.text('{{ translate("Pinging_FoxReload_Servers...") }}');
        statusPill.html('<span class="badge badge-soft-info fs-12 px-2 py-1"><i class="fi fi-sr-spinner fa-spin"></i> {{ translate("Testing...") }}</span>');

        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content') || $('meta[name="csrf-token"]').attr('content')
            }
        });

        $.ajax({
            url: checkUrl,
            type: 'POST',
            success: function (res) {
                btn.prop('disabled', false);
                icon.removeClass('fa-spin');
                text.text('{{ translate("Test_Connection_&_Check_Balance") }}');

                if (res.success) {
                    statusPill.html('<span class="badge badge-soft-success text-success fs-12 px-2 py-1"><i class="fi fi-sr-check"></i> {{ translate("Connected_Online") }}</span>');
                    latencyDisplay.html('<span class="text-success">' + res.latency_ms + ' ms</span>');
                    if (res.balance_usd !== null) {
                        balanceDisplay.html('<span class="text-primary">$' + res.balance_usd + ' USD</span>');
                    } else {
                        balanceDisplay.html('<span class="text-secondary">{{ translate("N/A_(Check_Key_Scopes)") }}</span>');
                    }

                    alertContainer.removeClass('d-none');
                    alertText.removeClass('alert-danger').addClass('alert-success text-success').html('<i class="fi fi-sr-check"></i> ' + res.message);
                    toastr.success(res.message);
                } else {
                    statusPill.html('<span class="badge badge-soft-danger text-danger fs-12 px-2 py-1"><i class="fi fi-sr-cross"></i> {{ translate("Connection_Failed") }}</span>');
                    latencyDisplay.html('<span class="text-danger">' + (res.latency_ms ? res.latency_ms + ' ms' : 'Timeout') + '</span>');
                    balanceDisplay.html('<span class="text-danger">{{ translate("Unavailable") }}</span>');

                    alertContainer.removeClass('d-none');
                    alertText.removeClass('alert-success').addClass('alert-danger text-danger').html('<i class="fi fi-sr-cross"></i> ' + res.message);
                    toastr.error(res.message);
                }
            },
            error: function (xhr) {
                btn.prop('disabled', false);
                icon.removeClass('fa-spin');
                text.text('{{ translate("Test_Connection_&_Check_Balance") }}');

                statusPill.html('<span class="badge badge-soft-danger text-danger fs-12 px-2 py-1"><i class="fi fi-sr-cross"></i> {{ translate("Error") }}</span>');
                latencyDisplay.html('<span class="text-danger">Failed</span>');
                balanceDisplay.html('<span class="text-danger">Failed</span>');

                alertContainer.removeClass('d-none');
                let err = xhr.responseJSON ? xhr.responseJSON.message : '{{ translate("Connection_failed") }}';
                alertText.removeClass('alert-success').addClass('alert-danger text-danger').html('<i class="fi fi-sr-cross"></i> ' + err);
                toastr.error(err);
            }
        });
    });

    // 3. Toggle API Key Visibility (Show / Hide Eye)
    $('#toggle-key-visibility').on('click', function () {
        let input = $('#api_key');
        let icon = $('#key-eye-icon');
        let text = $('#key-eye-text');

        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('fi-sr-eye').addClass('fi-sr-eye-crossed');
            text.text('{{ translate("Hide") }}');
        } else {
            input.attr('type', 'password');
            icon.removeClass('fi-sr-eye-crossed').addClass('fi-sr-eye');
            text.text('{{ translate("Show") }}');
        }
    });
</script>
@endpush
