<?php

use App\Models\FlashDeal;
use App\Models\FlashDealProduct;
use App\Models\Product;
use App\Models\Review;
use App\Utils\ProductManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

if (!function_exists('getOverallRating')) {
    function getOverallRating(object|array|null $reviews): array
    {
        if (empty($reviews)) {
            return [0, 0];
        }

        $totalRating = count($reviews);
        $rating = 0;
        foreach ($reviews as $review) {
            $rating += $review->rating;
        }
        if ($totalRating == 0) {
            $overallRating = 0;
        } else {
            $overallRating = number_format($rating / $totalRating, 2);
        }

        return [$overallRating, $totalRating];
    }
}

if (!function_exists('getRating')) {
    function getRating(object|array $reviews): array
    {
        $rating5 = 0;
        $rating4 = 0;
        $rating3 = 0;
        $rating2 = 0;
        $rating1 = 0;
        foreach ($reviews as $review) {
            if ($review->rating == 5) {
                $rating5 += 1;
            }
            if ($review->rating == 4) {
                $rating4 += 1;
            }
            if ($review->rating == 3) {
                $rating3 += 1;
            }
            if ($review->rating == 2) {
                $rating2 += 1;
            }
            if ($review->rating == 1) {
                $rating1 += 1;
            }
        }
        return [$rating5, $rating4, $rating3, $rating2, $rating1];
    }
}

if (!function_exists('getProductDiscount')) {
    /**
     * @param object|array $product
     * @param string|float|int $price
     * @return float
     */
    function getProductDiscount(object|array $product, string|float|int $price): float
    {
        $discount = 0;
        if (isset($product['discount_type']) && $product['discount_type'] == 'percent') {
            $discount = ($price * $product['discount']) / 100;
        } elseif (isset($product['discount_type']) && $product['discount_type'] == 'flat') {
            $discount = $product['discount'];
        }

        return floatval($discount);
    }
}

if (!function_exists('getPriceRangeWithDiscount')) {
    function getPriceRangeWithDiscount(array|object $product, string|null $type = 'web'): float|string
    {
        $productUnitPrice = $product->unit_price;
        foreach (json_decode($product->variation) as $key => $variation) {
            if ($key == 0) {
                $productUnitPrice = $variation->price;
            }
        }

        if ($product->digitalVariation && count($product->digitalVariation) > 0) {
            $digitalVariations = $product->digitalVariation->toArray();
            $productUnitPrice = $digitalVariations[0]['price'];
        }

        if ($type == 'panel') {
            if (isset($product['clearanceSale']) && $product['clearanceSale']) {
                $discountAmount = getProductPriceByType(product: $product, type: 'discounted_amount', result: 'value', price: $productUnitPrice, from: 'panel');
                $productDiscountedPrice = setCurrencySymbol(amount: usdToDefaultCurrency(amount: $productUnitPrice - $discountAmount), currencyCode: getCurrencyCode());
                return '<span class="discounted-unit-price text-primary fs-30 fw-bold lh-1">' . $productDiscountedPrice . '</span>' . '<del class="product-total-unit-price align-middle text-muted opacity-75 fs-20 fw-semibold lh-1">' . setCurrencySymbol(amount: usdToDefaultCurrency(amount: $productUnitPrice), currencyCode: getCurrencyCode()) . '</del>';
            } elseif ($product->discount > 0) {
                $amount = $productUnitPrice - getProductDiscount(product: $product, price: $productUnitPrice);
                $productDiscountedPrice = setCurrencySymbol(amount: usdToDefaultCurrency(amount: $amount), currencyCode: getCurrencyCode());
                return '<span class="discounted-unit-price text-primary fs-30 fw-bold lh-1">' . $productDiscountedPrice . '</span>' . '<del class="product-total-unit-price align-middle text-muted opacity-75 fs-20 fw-semibold lh-1">' . setCurrencySymbol(amount: usdToDefaultCurrency(amount: $productUnitPrice), currencyCode: getCurrencyCode()) . '</del>';
            } else {
                return '<span class="discounted-unit-price text-primary fs-30 fw-bold lh-1">' . setCurrencySymbol(amount: usdToDefaultCurrency(amount: $productUnitPrice), currencyCode: getCurrencyCode()) . '</span>';
            }
        } else {
            if (isset($product['clearanceSale']) && $product['clearanceSale']) {
                $discountAmount = getProductPriceByType(product: $product, type: 'discounted_amount', result: 'value', price: $productUnitPrice);
                $productDiscountedPrice = webCurrencyConverter(amount: $productUnitPrice - $discountAmount);
                return '<del class="product-total-unit-price align-middle text-muted opacity-75 fs-20 fw-semibold lh-1">' . webCurrencyConverter(amount: $productUnitPrice) . '</del>' . '<span class="discounted-unit-price text-primary fs-30 fw-bold lh-1">' . $productDiscountedPrice . '</span>';

            } elseif ($product->discount > 0) {
                $productDiscountedPrice = webCurrencyConverter(amount: $productUnitPrice - getProductDiscount(product: $product, price: $productUnitPrice));
                return '<del class="product-total-unit-price align-middle text-muted opacity-75 fs-20 fw-semibold lh-1">' . webCurrencyConverter(amount: $productUnitPrice) . '</del>' . '<span class="discounted-unit-price text-primary fs-30 fw-bold lh-1">' . $productDiscountedPrice . '</span>';
            } else {
                return '<span class="discounted-unit-price text-primary fs-30 fw-bold lh-1">' . webCurrencyConverter(amount: $productUnitPrice) . '</span>';
            }

        }
    }
}

if (!function_exists('getRatingCount')) {
    function getRatingCount($product_id, $rating)
    {
        return Review::where(['product_id' => $product_id, 'rating' => $rating])->whereNull('delivery_man_id')->count();
    }
}

if (!function_exists('units')) {
    function units(): array
    {
        return ['kg', 'pc', 'gms', 'ltrs', 'pair', 'oz', 'lb'];
    }
}

if (!function_exists('getVendorProductsCount')) {
    function getVendorProductsCount(string $type, string $panel = 'admin', int|null $userId = null): int
    {
        $array = Cache::remember(
            CACHE_FOR_ADMIN_SIDEBAR_ALL_PRODUCT_LIST,
            CACHE_FOR_7_DAYS,
            function () {
                $counts = Product::where('added_by', 'seller')
                    ->selectRaw("
                        SUM(CASE WHEN request_status = 0 THEN 1 ELSE 0 END) AS new_product,
                        SUM(CASE WHEN is_shipping_cost_updated = 0 AND is_shipping_cost_updated = 0 THEN 1 ELSE 0 END) AS product_updated_request,
                        SUM(CASE WHEN request_status = 1 THEN 1 ELSE 0 END) AS approved,
                        SUM(CASE WHEN request_status = 2 AND status = 0 THEN 1 ELSE 0 END) AS denied
                    ")
                    ->first();

                return [
                    'new-product' => (int) $counts->new_product,
                    'product-updated-request' => (int) $counts->product_updated_request,
                    'approved' => (int) $counts->approved,
                    'denied' => (int) $counts->denied,
                    'default' => 0,
                ];
            }
        );
        return match ($type) {
            'new-product' => (int) ($array['new-product'] ?? 0),
            'product-updated-request' => (int) ($array['product-updated-request'] ?? 0),
            'approved' => (int) ($array['approved'] ?? 0),
            'denied' => (int) ($array['denied'] ?? 0),
            default => (int) ($array['default'] ?? 0),
        };
    }
}


if (!function_exists('getAdminProductsCount')) {
    function getAdminProductsCount(string $type): int
    {
        $products = Cache::remember(CACHE_FOR_IN_HOUSE_ALL_PRODUCT_LIST, CACHE_FOR_7_DAYS, function () {
            return DB::table('products')->where(['added_by' => 'admin'])->get();
        });
        return match ($type) {
            'all' => $products->count(),
            'new-product' => $products->where('request_status', 0)->count(),
            'product-updated-request' => $products->whereNotNull('is_shipping_cost_updated')->where('is_shipping_cost_updated', 0)->count(),
            'approved' => $products->where('request_status', 1)->count(),
            'denied' => $products->where('request_status', 2)->where('status', 0)->count(),
        };
    }
}


if (!function_exists('getRestockProductFCMTopic')) {
    function getRestockProductFCMTopic(array|object $restockRequest): string
    {
        return 'restock_' . $restockRequest['id'] . '_product_restock_' . $restockRequest->product_id . '_topic';
    }
}


if (!function_exists('isProductInWishList')) {
    function isProductInWishList(string|int $productId): bool
    {
        if (session('wish_list') && in_array($productId, session('wish_list'))) {
            return true;
        }
        return false;
    }
}

if (!function_exists('isProductInCompareList')) {
    function isProductInCompareList(string|int $productId): bool
    {
        if (session('compare_list') && in_array($productId, session('compare_list'))) {
            return true;
        }
        return false;
    }
}


if (!function_exists('getProductMaxUnitPriceRange')) {
    function getProductMaxUnitPriceRange($type = null): int
    {
        $maxUnitPrice = Cache::remember(CACHE_FOR_PRODUCTS_MAX_UNIT_PRICE, CACHE_FOR_3_HOURS, function () {
            return Product::all()->max('unit_price');
        });

        if ($type == 'web') {
            $maxUnitPrice = webCurrencyConverterOnlyDigit(amount: $maxUnitPrice);
        }

        $ranges = [
            1000 => 500,
            10000 => 1000,
            100000 => 5000,
            1000000 => 10000,
            PHP_INT_MAX => 50000
        ];

        foreach ($ranges as $max => $increment) {
            if ($maxUnitPrice <= $max) {
                return ceil($maxUnitPrice / $increment) * $increment;
            }
        }
        return 0;
    }
}

if (!function_exists('getProductMinUnitPriceRange')) {
    function getProductMinUnitPriceRange($type = null): int
    {
        $minUnitPrice = Cache::remember(CACHE_FOR_PRODUCTS_MIN_UNIT_PRICE, CACHE_FOR_3_HOURS, function () {
            return Product::all()->min('unit_price');
        });

        if ($type == 'web') {
            $minUnitPrice = webCurrencyConverterOnlyDigit(amount: $minUnitPrice);
        }

        if ($minUnitPrice < 10) {
            return 0;
        }

        $ranges = [
            100 => 10,
            1000 => 50,
            10000 => 100,
            100000 => 1000,
            PHP_INT_MAX => 5000
        ];

        foreach ($ranges as $max => $increment) {
            if ($minUnitPrice <= $max) {
                return floor($minUnitPrice / $increment) * $increment;
            }
        }

        return 0;
    }
}

if (!function_exists('getFeaturedDealsProductList')) {
    function getFeaturedDealsProductList()
    {
        $cacheKey = 'cache_for_Featured_deals_products_list_' . getDefaultLanguage();
        $cacheKeys = Cache::get(CACHE_FOR_FEATURED_DEAL_PRODUCTS_LIST, []);
        if (!in_array($cacheKey, $cacheKeys)) {
            $cacheKeys[] = $cacheKey;
            Cache::put(CACHE_FOR_FEATURED_DEAL_PRODUCTS_LIST, $cacheKeys, CACHE_FOR_3_HOURS);
        }

        return Cache::remember($cacheKey, CACHE_FOR_3_HOURS, function () {
            $featuredDealID = FlashDeal::where(['deal_type' => 'feature_deal', 'status' => 1])
                ->whereDate('start_date', '<=', date('Y-m-d'))
                ->whereDate('end_date', '>=', date('Y-m-d'))->pluck('id')->first();
            $featuredDealProductIDs = $featuredDealID ? FlashDealProduct::where('flash_deal_id', $featuredDealID)->pluck('product_id')->toArray() : [];
            return ProductManager::getPriorityWiseFeatureDealQuery(
                query: Product::active()->with(['category', 'clearanceSale' => function ($query) {
                    return $query->active();
                }])->whereIn('id', $featuredDealProductIDs),
                dataLimit: 'all'
            );
        });
    }
}

if (!function_exists('personalizeProducts')) {
    /**
     * Reorder a homepage section's products toward the logged-in customer's interests.
     * Returns the input untouched for guests or when the feature is off. Never mutates the
     * cached source collection (operates on a copy), so it is safe on globally-cached lists.
     */
    function personalizeProducts($products)
    {
        if (empty($products)) {
            return $products;
        }
        $copy = is_object($products) && method_exists($products, 'values') ? $products->values() : $products;
        return app(\App\Services\ProductRecommendationService::class)->reorder($copy);
    }
}

if (!function_exists('isRecognizedCountry')) {
    /**
     * Determine if a string is a genuine world country or region name,
     * while rejecting platform editions, tiers, and non-country suffixes.
     */
    function isRecognizedCountry(?string $country): bool
    {
        if (empty($country)) {
            return false;
        }
        $c = strtolower(trim($country));

        // Non-country blacklist (platforms, editions, tiers, currencies)
        $blacklist = [
            'global', 'worldwide', 'world', 'default', 'package', 'packages', 'standard',
            'deluxe', 'ultimate', 'steam', 'steam pc', 'pc', 'mobile', 'mobile uc', 'uc',
            'basic', 'nitro', 'xbox', 'playstation', 'psn', 'ps4', 'ps5', 'nintendo',
            'switch', 'ios', 'android', 'coins', 'points', 'tokens', 'gems', 'diamonds',
            'credits', 'season pass', 'battle pass', 'game time', 'membership',
            'subscription', 'tier', 'gold', 'silver', 'bronze', 'platinum', 'vip'
        ];
        if (in_array($c, $blacklist, true)) {
            return false;
        }

        // Common regional / country aliases
        $customCountryAliases = [
            'usa', 'us', 'uk', 'gb', 'uae', 'ae', 'ksa', 'sa', 'eu', 'europe',
            'russia', 'korea', 'south korea', 'turkey', 'türkiye',
            'mena', 'middle east and north africa', 'latin america', 'latam',
            'southeast asia', 'sea', 'north america', 'na', 'asia', 'america',
            'taiwan', 'macau', 'hong kong', 'hong kong (sar)', 'hk/mo/tw', 'my/sg',
            'vietnam', 'indonesia', 'philippines', 'cambodia', 'thailand', 'malaysia',
            'singapore', 'japan', 'china', 'qatar', 'oman', 'bahrain', 'kuwait', 'canada'
        ];
        if (in_array($c, $customCountryAliases, true)) {
            return true;
        }

        // Check against official standard COUNTRIES list (app/Library/Constant.php)
        if (defined('COUNTRIES') && is_array(COUNTRIES)) {
            foreach (COUNTRIES as $item) {
                if (strtolower($item['name']) === $c || strtolower($item['code']) === $c) {
                    return true;
                }
            }
        }

        // Check against GlobalConstant::COUNTRIES if available
        if (class_exists(\App\Enums\GlobalConstant::class) && defined('\App\Enums\GlobalConstant::COUNTRIES')) {
            foreach (\App\Enums\GlobalConstant::COUNTRIES as $item) {
                if (strtolower($item['name']) === $c || strtolower($item['code']) === $c) {
                    return true;
                }
            }
        }

        return false;
    }
}

if (!function_exists('getProductCountryIdentifier')) {
    /**
     * Extracts verified country/region identifier from product record or parenthesized name suffix.
     * Returns null if product is not a country-specific variation.
     */
    function getProductCountryIdentifier($product): ?string
    {
        if (!empty($product->country) && isRecognizedCountry($product->country)) {
            return trim($product->country);
        }
        if (preg_match('/\(([^)]+)\)?$/', $product->name, $matches)) {
            $extracted = trim($matches[1]);
            if (isRecognizedCountry($extracted)) {
                return $extracted;
            }
            // Check if extracted string ends with or contains an alias e.g. "Gift Card (US"
            $quickAliases = [
                'usa' => 'USA', 'us' => 'USA', 'united states' => 'USA',
                'uk' => 'United Kingdom', 'gb' => 'United Kingdom', 'great britain' => 'United Kingdom',
                'uae' => 'UAE', 'ae' => 'UAE', 'ksa' => 'Saudi Arabia', 'sa' => 'Saudi Arabia',
                'canada' => 'Canada', 'ca' => 'Canada', 'germany' => 'Germany', 'de' => 'Germany',
                'france' => 'France', 'fr' => 'France', 'brazil' => 'Brazil', 'br' => 'Brazil',
                'turkey' => 'Turkey', 'tr' => 'Turkey', 'australia' => 'Australia', 'au' => 'Australia',
                'egypt' => 'Egypt', 'eg' => 'Egypt', 'europe' => 'Europe', 'eu' => 'Europe'
            ];
            foreach ($quickAliases as $alias => $stdName) {
                if (preg_match('/\b' . preg_quote($alias, '/') . '\b/i', $extracted)) {
                    return $stdName;
                }
            }
        }
        return null;
    }
}

if (!function_exists('getCountryFlagEmoji')) {
    /**
     * Get appropriate flag emoji for country or region name dynamically
     */
    function getCountryFlagEmoji(?string $country): string
    {
        if (empty($country)) {
            return '🌐';
        }
        $r = strtolower(trim($country));

        if (in_array($r, ['global', 'world', 'worldwide'])) return '🌐';
        if ($r === 'usa' || $r === 'united states') return '🇺🇸';
        if ($r === 'uk' || $r === 'great britain') return '🇬🇧';
        if ($r === 'uae') return '🇦🇪';
        if ($r === 'ksa') return '🇸🇦';
        if ($r === 'eu' || $r === 'europe') return '🇪🇺';

        $code = null;
        if (strlen($r) === 2 && ctype_alpha($r)) {
            $code = strtoupper($r);
        } else {
            if (defined('COUNTRIES') && is_array(COUNTRIES)) {
                foreach (COUNTRIES as $item) {
                    if (strtolower($item['name']) === $r) {
                        $code = strtoupper($item['code']);
                        break;
                    }
                }
            }
        }

        // Convert ISO 3166-1 alpha-2 code to Regional Indicator Symbols
        if ($code && strlen($code) === 2 && ctype_alpha($code)) {
            $first = mb_chr(ord($code[0]) + 127397, 'UTF-8');
            $second = mb_chr(ord($code[1]) + 127397, 'UTF-8');
            return $first . $second;
        }

        return '📍';
    }
}

if (!function_exists('resolveDigitalProductExtensionsAndSiblings')) {
    /**
     * Resolves country-grouped extensions and variations for digital products.
     * Supports both:
     * 1) Products with multiple regional keys in digital_product_extensions (e.g. {"Canada": [...], "USA": [...]})
     * 2) Digital products split across sibling products per country (e.g. "Tinder (Canada)", "Tinder (Egypt)", "Apple iTunes (UK)", etc.)
     * 
     * Isolates standalone products and editions (e.g. PUBG Steam PC vs PUBG Mobile UC) into separate pages.
     */
    function resolveDigitalProductExtensionsAndSiblings($product): array
    {
        $existing = is_string($product->digital_product_extensions)
            ? (json_decode($product->digital_product_extensions, true) ?? [])
            : ($product->digital_product_extensions ?? []);

        // 1. Check if already has multi-country keys in digital_product_extensions
        $hasMultiCountry = is_array($existing) && count($existing) > 1;
        if ($hasMultiCountry) {
            $digitalVariationsKeyed = $product->digitalVariation ? $product->digitalVariation->keyBy('variant_key') : collect();
            return [
                'extensions' => $existing,
                'variations' => $digitalVariationsKeyed,
                'siblings_by_country' => collect([$product->country => $product]),
                'is_sibling_grouped' => false,
            ];
        }

        // If not digital, return standard
        if ($product->product_type !== 'digital') {
            return [
                'extensions' => $existing,
                'variations' => $product->digitalVariation ? $product->digitalVariation->keyBy('variant_key') : collect(),
                'siblings_by_country' => collect(),
                'is_sibling_grouped' => false,
            ];
        }

        // 2. Check if this product is a genuine country variation
        $primaryCountry = getProductCountryIdentifier($product);
        if ($primaryCountry === null) {
            // Standalone product or non-country edition (e.g. PUBG Steam PC, Discord Nitro)
            return [
                'extensions' => $existing,
                'variations' => $product->digitalVariation ? $product->digitalVariation->keyBy('variant_key') : collect(),
                'siblings_by_country' => collect(),
                'is_sibling_grouped' => false,
            ];
        }

        // 3. Find sibling products sharing base title and brand
        $baseName = trim(preg_replace('/\s*\([^)]*\)?\s*$/', '', $product->name));
        $baseName = trim(preg_replace('/\s*\(.*$/', '', $baseName));
        if (empty($baseName)) {
            return [
                'extensions' => $existing,
                'variations' => $product->digitalVariation ? $product->digitalVariation->keyBy('variant_key') : collect(),
                'siblings_by_country' => collect(),
                'is_sibling_grouped' => false,
            ];
        }

        $query = \App\Models\Product::with(['digitalVariation'])
            ->where('product_type', 'digital')
            ->where('status', 1)
            ->where('name', 'LIKE', $baseName . '%');

        if (!empty($product->brand_id)) {
            $query->where('brand_id', $product->brand_id);
        }

        // Filter: ONLY include siblings that have a confirmed, recognized country
        $allCandidates = $query->get();
        $siblings = $allCandidates->filter(function($s) {
            return getProductCountryIdentifier($s) !== null;
        });

        if ($siblings->count() <= 1) {
            return [
                'extensions' => $existing,
                'variations' => $product->digitalVariation ? $product->digitalVariation->keyBy('variant_key') : collect(),
                'siblings_by_country' => collect(),
                'is_sibling_grouped' => false,
            ];
        }

        // 4. Merge siblings into country-grouped structure
        $combinedExtensions = [];
        $combinedVariations = collect();
        $siblingsByCountry = collect();

        // Sort so current product comes first
        $sortedSiblings = $siblings->sortBy(function($s) use ($product) {
            return $s->id === $product->id ? 0 : 1;
        });

        foreach ($sortedSiblings as $sibling) {
            $country = getProductCountryIdentifier($sibling) ?? $sibling->name;

            $sExt = is_string($sibling->digital_product_extensions)
                ? (json_decode($sibling->digital_product_extensions, true) ?? [])
                : ($sibling->digital_product_extensions ?? []);

            $packages = [];
            if (is_array($sExt)) {
                foreach ($sExt as $k => $pkgs) {
                    if (is_array($pkgs)) {
                        foreach ($pkgs as $p) {
                            $packages[] = $p;
                        }
                    } elseif (is_string($pkgs)) {
                        $packages[] = $pkgs;
                    }
                }
            }

            $combinedExtensions[$country] = $packages;
            $siblingsByCountry[$country] = $sibling;

            if ($sibling->digitalVariation) {
                foreach ($sibling->digitalVariation as $var) {
                    $combinedVariations->put($var->variant_key, $var);
                    $cleanOption = preg_replace('/\s+/', '-', $var->variant_key);
                    $combinedVariations->put($country . '-' . $cleanOption, $var);
                    $combinedVariations->put($sibling->id . '_' . $var->variant_key, $var);
                }
            }
        }

        return [
            'extensions' => $combinedExtensions,
            'variations' => $combinedVariations,
            'siblings_by_country' => $siblingsByCountry,
            'is_sibling_grouped' => true,
        ];
    }
}


