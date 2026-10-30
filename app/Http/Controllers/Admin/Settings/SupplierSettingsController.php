<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\BaseController;
use App\Models\BusinessSetting;
use App\Models\DigitalCardCode;
use App\Models\ProductProviderMapping;
use App\Services\Supplier\FoxReload\FoxReloadClient;
use App\Traits\SettingsTrait;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class SupplierSettingsController extends BaseController
{
    use SettingsTrait;

    /**
     * Display the FoxReload Supplier & Mock Mode Dashboard.
     *
     * @param Request|null $request
     * @param string|null $type
     * @return View
     */
    public function index(Request|null $request, ?string $type = null): View
    {
        $dbMock = null;
        try {
            $dbMock = BusinessSetting::where('type', 'foxreload_is_mock')->value('value');
        } catch (\Throwable $e) {}

        if ($dbMock !== null) {
            $isMock = filter_var($dbMock, FILTER_VALIDATE_BOOLEAN);
        } else {
            $rawEnv = env('FOXRELOAD_IS_MOCK', true);
            $isMock = filter_var($rawEnv, FILTER_VALIDATE_BOOLEAN);
        }

        $totalMockCodes = 0;
        $totalDeliveredCodes = 0;
        $totalMappedProducts = 0;
        try {
            $totalMockCodes = DigitalCardCode::where('card_code', 'LIKE', 'GBC-%')->count();
            $totalDeliveredCodes = DigitalCardCode::count();
            $totalMappedProducts = ProductProviderMapping::where('is_active', 1)->count();
        } catch (\Throwable $e) {}

        $config = [
            'is_mock' => $isMock,
            'base_url' => env('FOXRELOAD_BASE_URL', 'https://public-api.foxreload.com/api'),
            'api_key' => env('FOXRELOAD_API_KEY', ''),
            'markup_percent' => env('FOXRELOAD_MARKUP_PERCENT', 10),
            'balance_alert' => env('FOXRELOAD_BALANCE_ALERT_THRESHOLD', 50.0),
            'sandbox_gateway' => filter_var(env('PAYMENT_GATEWAY_SANDBOX', false), FILTER_VALIDATE_BOOLEAN),
            'total_mock_codes' => $totalMockCodes,
            'total_delivered_codes' => $totalDeliveredCodes,
            'total_mapped_products' => $totalMappedProducts,
        ];

        return view('admin-views.system-setup.supplier-settings', compact('config'));
    }

    /**
     * Update FoxReload supplier environment credentials and parameters.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function update(Request $request): RedirectResponse
    {
        if (env('APP_MODE') == 'demo') {
            ToastMagic::info(translate('you_can_not_update_this_on_demo_mode'));
            return back();
        }

        try {
            $isMockBool = $request->has('is_mock') && filter_var($request['is_mock'], FILTER_VALIDATE_BOOLEAN);
            $isMockStr = $isMockBool ? 'true' : 'false';

            $this->setEnvironmentValue(envKey: 'FOXRELOAD_IS_MOCK', envValue: $isMockStr);

            if ($request->filled('base_url')) {
                $this->setEnvironmentValue(envKey: 'FOXRELOAD_BASE_URL', envValue: rtrim($request['base_url'], '/'));
            }

            if ($request->filled('api_key')) {
                $this->setEnvironmentValue(envKey: 'FOXRELOAD_API_KEY', envValue: trim($request['api_key']));
            }

            if ($request->filled('markup_percent')) {
                $this->setEnvironmentValue(envKey: 'FOXRELOAD_MARKUP_PERCENT', envValue: (string)$request['markup_percent']);
            }

            if ($request->filled('balance_alert')) {
                $this->setEnvironmentValue(envKey: 'FOXRELOAD_BALANCE_ALERT_THRESHOLD', envValue: (string)$request['balance_alert']);
            }

            $sandboxBool = $request->has('sandbox_gateway') && filter_var($request['sandbox_gateway'], FILTER_VALIDATE_BOOLEAN);
            $this->setEnvironmentValue(envKey: 'PAYMENT_GATEWAY_SANDBOX', envValue: $sandboxBool ? 'true' : 'false');

            try {
                BusinessSetting::updateOrInsert(['type' => 'foxreload_is_mock'], ['value' => $isMockStr]);
            } catch (\Throwable $e) {}

            try {
                Artisan::call('config:clear');
                Artisan::call('cache:clear');
            } catch (\Throwable $e) {}

            ToastMagic::success(translate('Supplier_and_mock_mode_settings_updated_successfully'));
        } catch (Exception $exception) {
            Log::error('SupplierSettingsController update failed: ' . $exception->getMessage());
            ToastMagic::error(translate('Failed_to_update_supplier_settings'));
        }

        return back();
    }

    /**
     * Instant 1-click AJAX toggle for FOXRELOAD_IS_MOCK.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function toggleMockStatus(Request $request): JsonResponse
    {
        if (env('APP_MODE') == 'demo') {
            return response()->json([
                'success' => false,
                'message' => translate('you_can_not_update_this_on_demo_mode')
            ], 403);
        }

        try {
            $statusInput = $request->input('status');
            $targetState = filter_var($statusInput, FILTER_VALIDATE_BOOLEAN);
            $strVal = $targetState ? 'true' : 'false';

            $this->setEnvironmentValue(envKey: 'FOXRELOAD_IS_MOCK', envValue: $strVal);

            try {
                BusinessSetting::updateOrInsert(['type' => 'foxreload_is_mock'], ['value' => $strVal]);
            } catch (\Throwable $e) {}

            try {
                Artisan::call('config:clear');
                Artisan::call('cache:clear');
            } catch (\Throwable $e) {}

            $msg = $targetState
                ? translate('Mock_Mode_is_now_ENABLED._Digital_cards_will_be_simulated_instantly_with_zero_API_cost.')
                : translate('Mock_Mode_is_now_DISABLED._Live_procurement_via_FoxReload_API_is_active.');

            return response()->json([
                'success' => true,
                'is_mock' => $targetState,
                'status_text' => $targetState ? 'Mock Mode (Active)' : 'Live Mode (Active)',
                'message' => $msg
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test FoxReload API connection and fetch real-time wholesale USD balance.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkConnection(Request $request): JsonResponse
    {
        try {
            $startTime = microtime(true);
            $client = new FoxReloadClient();
            $isConnected = $client->testConnection();
            $latencyMs = round((microtime(true) - $startTime) * 1000);
            $usdBalance = null;

            if ($isConnected) {
                $usdBalance = $client->getUsdBalance();
            }

            return response()->json([
                'success' => $isConnected,
                'latency_ms' => $latencyMs,
                'balance_usd' => $usdBalance !== null ? number_format((float)$usdBalance, 2) : null,
                'message' => $isConnected
                    ? translate('Successfully_connected_to_FoxReload_API')
                    : translate('Could_not_connect_to_FoxReload_API._Please_check_your_API_key_and_network_connectivity.')
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
