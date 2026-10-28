<?php

namespace App\Services\Supplier\FoxReload;

use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FoxReloadClient
{
    protected string $baseUrl;
    protected ?string $apiKey;
    protected int $timeout;
    protected bool $isMock;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('suppliers.suppliers.foxreload.base_url', env('FOXRELOAD_BASE_URL', 'https://public-api.foxreload.com/api')), '/');
        $this->apiKey = config('suppliers.suppliers.foxreload.api_key', env('FOXRELOAD_API_KEY', 'dmk_dtuTuHaZtge-jkyoxVyOXa5CBwE4sRNOnMLnonzSeRk'));
        $this->timeout = (int) config('suppliers.suppliers.foxreload.timeout', 30);
        $this->isMock = (bool) config('suppliers.suppliers.foxreload.is_mock', env('FOXRELOAD_IS_MOCK', false));
    }

    /**
     * Get headers for FoxReload API
     */
    protected function getHeaders(): array
    {
        $currency = config('suppliers.suppliers.foxreload.headers.currency', env('FOXRELOAD_CURRENCY', 'usd'));
        $language = config('suppliers.suppliers.foxreload.headers.language', env('FOXRELOAD_LANGUAGE', 'en'));

        return [
            'X-API-Key' => $this->apiKey,
            'X-Currency' => strtolower($currency),
            'X-Language' => strtolower($language),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ];
    }

    /**
     * Build pre-configured HTTP client
     */
    protected function client(): PendingRequest
    {
        return Http::withHeaders($this->getHeaders())
            ->withoutVerifying()
            ->timeout($this->timeout);
    }

    /**
     * Execute Python FoxReload bridge client as a fallback when shell_exec is permitted
     */
    protected function runPythonCommand(array $args): ?array
    {
        if (!function_exists('shell_exec') || !is_callable('shell_exec')) {
            return null;
        }

        $scriptPath = __DIR__ . '/foxreload_client.py';
        if (!file_exists($scriptPath)) {
            return null;
        }

        try {
            $escapedArgs = array_map('escapeshellarg', $args);
            $cmdArgs = implode(' ', $escapedArgs);

            // Try python3 first (standard on Linux), then python (standard on Windows)
            foreach (['python3', 'python'] as $bin) {
                $cmd = "{$bin} " . escapeshellarg($scriptPath) . " {$cmdArgs} 2>&1";
                $output = @shell_exec($cmd);
                if ($output !== null && trim($output) !== '') {
                    $decoded = json_decode(trim($output), true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        return $decoded;
                    }
                }
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    /**
     * Test connection to FoxReload API
     */
    public function testConnection(): bool
    {
        // 1. Primary: Native PHP HTTP request
        try {
            $response = $this->client()->get("{$this->baseUrl}/categories/", ['limit' => 1]);
            if ($response->successful()) {
                return true;
            }
        } catch (\Throwable $e) {
            Log::error("FoxReload testConnection failed: " . $e->getMessage());
        }

        // 2. Secondary fallback: Python bridge
        $py = $this->runPythonCommand(['test-connection']);
        if ($py !== null && isset($py['success'])) {
            return (bool) $py['success'];
        }

        return false;
    }

    /**
     * Fetch categories (supports parentId and pagination cursor)
     */
    public function getCategories(?string $parentId = null, int $limit = 50, ?string $cursor = null): array
    {
        // 1. Primary: Native PHP HTTP request
        try {
            $query = ['limit' => $limit];
            if ($parentId) {
                $query['parentId'] = $parentId;
            }
            if ($cursor) {
                $query['cursor'] = $cursor;
            }

            $response = $this->client()->get("{$this->baseUrl}/categories/", $query);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error("FoxReload getCategories error: " . $response->body());
        } catch (\Throwable $e) {
            Log::error("FoxReload getCategories exception: " . $e->getMessage());
        }

        // 2. Secondary fallback: Python bridge
        $py = $this->runPythonCommand(['get-categories', $parentId ?? 'null', (string) $limit]);
        if ($py !== null && isset($py['items'])) {
            return $py;
        }

        return ['items' => []];
    }

    /**
     * Fetch products for a given category ID
     */
    public function getProducts(string $categoryId, int $limit = 50, int $offset = 0, bool $withStockOnly = true): array
    {
        // 1. Primary: Native PHP HTTP request
        try {
            $query = [
                'categoryId' => $categoryId,
                'limit' => $limit,
                'offset' => $offset,
            ];

            if ($withStockOnly) {
                $query['withStockOnly'] = 'true';
            }

            $response = $this->client()->get("{$this->baseUrl}/products/", $query);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error("FoxReload getProducts error for category {$categoryId}: " . $response->body());
        } catch (\Throwable $e) {
            Log::error("FoxReload getProducts exception for category {$categoryId}: " . $e->getMessage());
        }

        // 2. Secondary fallback: Python bridge
        $py = $this->runPythonCommand(['get-products', $categoryId, (string) $limit, $withStockOnly ? 'true' : 'false']);
        if ($py !== null && isset($py['items'])) {
            return $py;
        }

        return ['items' => []];
    }

    /**
     * Fetch a single category by its ID
     */
    public function getCategory(string $categoryId): ?array
    {
        // 1. Primary: Native PHP HTTP request
        try {
            $response = $this->client()->get("{$this->baseUrl}/categories/{$categoryId}");
            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Throwable $e) {
            Log::warning("FoxReload getCategory error for {$categoryId}: " . $e->getMessage());
        }

        // 2. Secondary fallback: Python bridge
        $py = $this->runPythonCommand(['get-category', $categoryId]);
        if ($py !== null && is_array($py)) {
            return $py;
        }

        return null;
    }

    /**
     * Fetch products grouped by subcategory or as a root group if direct
     *
     * @param string $categoryId
     * @param int $limit
     * @param bool $withStockOnly
     * @param array|null $allowedSubcategories
     * @return array Array of groups: [['category_id' => ..., 'category_name' => ..., 'is_subcategory' => ..., 'items' => [...]]]
     */
    public function getCategoryProductsGrouped(string $categoryId, int $limit = 50, bool $withStockOnly = true, ?array $allowedSubcategories = null): array
    {
        // 1. Direct products in this category
        $direct = $this->getProducts($categoryId, limit: $limit, withStockOnly: $withStockOnly);
        $directItems = $direct['items'] ?? [];

        if (!empty($directItems)) {
            $cat = $this->getCategory($categoryId);
            return [
                [
                    'category_id' => $categoryId,
                    'category_name' => $cat['name'] ?? null,
                    'is_subcategory' => false,
                    'items' => $directItems,
                ],
            ];
        }

        // 2. If no direct products, inspect child subcategories (e.g. PC, Xbox, Regions)
        $children = $this->getCategories(parentId: $categoryId, limit: 50);
        $childCategories = $children['items'] ?? [];

        $groups = [];
        foreach ($childCategories as $child) {
            // If subcategory explicitly indicates zero stock, skip to prevent unnecessary requests
            if ($withStockOnly && isset($child['inStockCount']) && (int) $child['inStockCount'] === 0) {
                continue;
            }

            // If allowed subcategories are configured, skip non-matching child categories early
            if (!empty($allowedSubcategories)) {
                $childName = $child['name'] ?? '';
                $matched = false;
                foreach ($allowedSubcategories as $allowed) {
                    if (stripos($childName, $allowed) !== false) {
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) {
                    continue;
                }
            }

            $childProducts = $this->getProducts($child['id'], limit: $limit, withStockOnly: $withStockOnly);
            $childItems = $childProducts['items'] ?? [];

            if (!empty($childItems)) {
                $groups[] = [
                    'category_id' => $child['id'],
                    'category_name' => $child['name'] ?? null,
                    'is_subcategory' => true,
                    'items' => $childItems,
                ];
            }
        }

        return $groups;
    }

    /**
     * Fetch products for a category or its immediate subcategories if it has children (flat list)
     */
    public function getProductsTree(string $categoryId, int $limit = 50, bool $withStockOnly = true): array
    {
        $grouped = $this->getCategoryProductsGrouped($categoryId, limit: $limit, withStockOnly: $withStockOnly);
        $flat = [];
        foreach ($grouped as $group) {
            foreach ($group['items'] as $item) {
                $item['_fox_category_id'] = $group['category_id'];
                $item['_fox_category_name'] = $group['category_name'];
                $flat[] = $item;
            }
        }

        return $flat;
    }

    /**
     * Get user balances (RUB and USD)
     */
    public function getBalances(): array
    {
        $status = 500;
        $errData = 'Unknown error';

        // 1. Primary: Native PHP HTTP request
        try {
            $response = $this->client()->get("{$this->baseUrl}/access/me/balances/");
            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }
            Log::error("FoxReload getBalances error: " . $response->body());
            $status = $response->status();
            $errData = $response->json() ?? $response->body();
        } catch (\Throwable $e) {
            Log::error("FoxReload getBalances exception: " . $e->getMessage());
            $status = 500;
            $errData = $e->getMessage();
        }

        // 2. Secondary fallback: Python bridge
        $py = $this->runPythonCommand(['get-balances']);
        if ($py !== null && isset($py['success'])) {
            return $py;
        }

        return [
            'success' => false,
            'status' => $status,
            'error' => $errData,
        ];
    }

    /**
     * Get available USD balance
     */
    public function getUsdBalance(): ?float
    {
        $res = $this->getBalances();
        if (!empty($res['success']) && !empty($res['data']) && is_array($res['data'])) {
            foreach ($res['data'] as $b) {
                if (isset($b['currency']) && strtolower($b['currency']) === 'usd') {
                    return (float) ($b['available'] ?? 0);
                }
            }
        }
        return null;
    }

    /**
     * Place an order on FoxReload
     *
     * @param array $items Array of items: [['itemId' => 'product_xxx', 'quantity' => 1, 'note' => [...]]]
     * @param bool|null $isMock Override mock mode; defaults to config/env
     * @param string|null $idempotencyKey Unique merchant order key
     */
    public function createOrder(array $items, ?bool $isMock = null, ?string $idempotencyKey = null): array
    {
        $mockMode = $isMock !== null ? $isMock : $this->isMock;
        $status = 500;
        $errData = 'Unknown error';

        // 1. Primary: Native PHP HTTP request
        try {
            $payload = [
                'items' => $items,
                'isMock' => $mockMode,
            ];
            if ($idempotencyKey) {
                $payload['idempotencyKey'] = (string) $idempotencyKey;
            }

            $response = $this->client()->post("{$this->baseUrl}/orders/", $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            Log::error("FoxReload createOrder failed: " . $response->body());
            $status = $response->status();
            $errData = $response->json() ?? $response->body();
        } catch (\Throwable $e) {
            Log::error("FoxReload createOrder exception: " . $e->getMessage());
            $status = 500;
            $errData = $e->getMessage();
        }

        // 2. Secondary fallback: Python bridge
        $pyArgs = ['create-order', json_encode($items), $mockMode ? 'true' : 'false'];
        if ($idempotencyKey) {
            $pyArgs[] = $idempotencyKey;
        }
        $py = $this->runPythonCommand($pyArgs);
        if ($py !== null && isset($py['success'])) {
            return $py;
        }

        return [
            'success' => false,
            'status' => $status,
            'error' => $errData,
        ];
    }

    /**
     * Trigger payment/confirmation on an active FoxReload order
     * When paying from wholesale balance, pass null/empty to send {}
     */
    public function payOrder(string $orderId, ?string $paymentProvider = null, ?string $paymentMethodType = null): array
    {
        $status = 500;
        $errData = 'Unknown error';

        // 1. Primary: Native PHP HTTP request
        try {
            $payload = [];
            if ($paymentProvider) {
                $payload['paymentProvider'] = $paymentProvider;
            }
            if ($paymentMethodType) {
                $payload['paymentMethodType'] = $paymentMethodType;
            }

            $response = $this->client()->post("{$this->baseUrl}/orders/{$orderId}/pay/", (object) $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            Log::error("FoxReload payOrder failed for order {$orderId}: " . $response->body());
            $status = $response->status();
            $errData = $response->json() ?? $response->body();
        } catch (\Throwable $e) {
            Log::error("FoxReload payOrder exception for order {$orderId}: " . $e->getMessage());
            $status = 500;
            $errData = $e->getMessage();
        }

        // 2. Secondary fallback: Python bridge
        $pyArgs = ['pay-order', $orderId];
        if ($paymentProvider) {
            $pyArgs[] = $paymentProvider;
            if ($paymentMethodType) {
                $pyArgs[] = $paymentMethodType;
            }
        }
        $py = $this->runPythonCommand($pyArgs);
        if ($py !== null && isset($py['success'])) {
            return $py;
        }

        return [
            'success' => false,
            'status' => $status,
            'error' => $errData,
        ];
    }

    /**
     * Get order status and delivered digital codes
     */
    public function getOrderStatus(string $orderId): array
    {
        $status = 500;
        $errData = 'Unknown error';

        // 1. Primary: Native PHP HTTP request (no trailing slash for direct 200)
        try {
            $response = $this->client()->get("{$this->baseUrl}/orders/{$orderId}");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            Log::error("FoxReload getOrderStatus failed for order {$orderId}: " . $response->body());
            $status = $response->status();
            $errData = $response->json() ?? $response->body();
        } catch (\Throwable $e) {
            Log::error("FoxReload getOrderStatus exception for order {$orderId}: " . $e->getMessage());
            $status = 500;
            $errData = $e->getMessage();
        }

        // 2. Secondary fallback: Python bridge
        $py = $this->runPythonCommand(['get-order-status', $orderId]);
        if ($py !== null && isset($py['success'])) {
            return $py;
        }

        return [
            'success' => false,
            'status' => $status,
            'error' => $errData,
        ];
    }

    /**
     * Poll order until it reaches a terminal status ('completed', 'failed', 'cancelled')
     */
    public function pollOrderStatus(string $orderId, int $maxAttempts = 5, int $delaySeconds = 2): array
    {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $result = $this->getOrderStatus($orderId);

            if ($result['success']) {
                $status = $result['data']['status'] ?? 'unknown';

                if (in_array($status, ['completed', 'failed', 'cancelled'])) {
                    return $result;
                }
            }

            if ($attempt < $maxAttempts) {
                sleep($delaySeconds);
            }
        }

        return $this->getOrderStatus($orderId);
    }
}
