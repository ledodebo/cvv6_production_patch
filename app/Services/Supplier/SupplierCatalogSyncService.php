<?php

namespace App\Services\Supplier;

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\DigitalProductVariation;
use App\Models\Product;
use App\Models\ProductProviderMapping;
use App\Services\Supplier\FoxReload\FoxReloadClient;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SupplierCatalogSyncService
{
    public function __construct(
        protected FoxReloadClient $foxReloadClient
    ) {
    }

    /**
     * Sync curated brands and their products from FoxReload
     *
     * @param array|null $brandFilter Optional array of brand keys to sync (e.g. ['pubg', 'brawl_stars'])
     * @return array Stats on imported/updated items
     */
    public function syncCuratedBrands(?array $brandFilter = null): array
    {
        $curatedBrands = config('suppliers.suppliers.foxreload.curated_brands', []);
        $markupPercent = (float) config('suppliers.suppliers.foxreload.markup_percent', 7.0);
        $attributeName = config('suppliers.suppliers.foxreload.variation_attribute_name', 'Package');

        if ($brandFilter) {
            $curatedBrands = array_intersect_key($curatedBrands, array_flip($brandFilter));
        }

        $stats = [
            'categories_created' => 0,
            'brands_created' => 0,
            'products_imported' => 0,
            'products_updated' => 0,
            'errors' => [],
        ];

        // Ensure master variation attribute exists (e.g. 'Package')
        $attribute = Attribute::firstOrCreate(['name' => $attributeName]);

        foreach ($curatedBrands as $brandKey => $brandConfig) {
            $retainedProductIds = [];
            try {
                // 1. Ensure Category exists
                $categoryName = $brandConfig['category'] ?? 'Gaming Cards';
                $categorySlug = Str::slug($categoryName);

                $category = Category::firstOrCreate(
                    ['slug' => $categorySlug],
                    [
                        'name' => $categoryName,
                        'icon' => 'default-icon.png',
                        'icon_storage_type' => 'public',
                        'parent_id' => 0,
                        'position' => 0,
                        'home_status' => 1,
                        'priority' => 1,
                    ]
                );

                if ($category->wasRecentlyCreated) {
                    $stats['categories_created']++;
                }

                // 2. Ensure Brand exists
                $brand = Brand::firstOrCreate(
                    ['slug' => $brandConfig['slug']],
                    [
                        'name' => $brandConfig['name'],
                        'image' => 'brand.png',
                        'image_storage_type' => 'public',
                        'image_alt_text' => $brandConfig['name'],
                        'status' => 1,
                    ]
                );

                if ($brand->wasRecentlyCreated) {
                    $stats['brands_created']++;
                } elseif ($brand->name !== $brandConfig['name']) {
                    $brand->update(['name' => $brandConfig['name']]);
                }

                // 3. Fetch products grouped by subcategory / platform across FoxReload category tree
                $allGroups = [];
                foreach ($brandConfig['foxreload_category_ids'] as $foxCatId) {
                    $groups = $this->foxReloadClient->getCategoryProductsGrouped(
                        $foxCatId,
                        limit: 30,
                        withStockOnly: true,
                        allowedSubcategories: $brandConfig['allowed_subcategories'] ?? null
                    );
                    foreach ($groups as $group) {
                        if (!empty($brandConfig['allowed_subcategories'])) {
                            $subName = $group['category_name'] ?? '';
                            $matched = false;
                            foreach ($brandConfig['allowed_subcategories'] as $allowed) {
                                if (stripos($subName, $allowed) !== false) {
                                    $matched = true;
                                    break;
                                }
                            }
                            if (!$matched) {
                                continue;
                            }
                        }
                        $allGroups[] = $group;
                    }
                }

                if (empty($allGroups)) {
                    continue;
                }

                // Split groups by platform if mixed (e.g. Xbox & PC)
                $finalGroups = [];
                foreach ($allGroups as $group) {
                    $subGroups = $this->splitGroupByPlatformIfNeeded($group, $brandConfig['name']);
                    foreach ($subGroups as $sg) {
                        $finalGroups[] = $sg;
                    }
                }

                $shouldGroupVariations = $brandConfig['group_variations'] ?? config('suppliers.suppliers.foxreload.group_variations_by_default', true);

                if ($shouldGroupVariations) {
                    foreach ($finalGroups as $group) {
                        $this->syncGroupedProductWithVariations(
                            $brandConfig,
                            $brand,
                            $category,
                            $attribute,
                            $group,
                            count($finalGroups),
                            $markupPercent,
                            $stats,
                            $retainedProductIds
                        );
                    }
                } else {
                    foreach ($finalGroups as $group) {
                        $this->syncIndividualProducts(
                            $brandConfig,
                            $brand,
                            $category,
                            $group['items'] ?? [],
                            $markupPercent,
                            $stats,
                            $retainedProductIds
                        );
                    }
                }

                // Clean up any old/stale products for this brand that were not retained in this sync
                if (!empty($retainedProductIds)) {
                    $staleProducts = Product::where('brand_id', $brand->id)
                        ->whereNotIn('id', $retainedProductIds)
                        ->get();

                    foreach ($staleProducts as $dup) {
                        ProductProviderMapping::where('product_id', $dup->id)->delete();
                        DigitalProductVariation::where('product_id', $dup->id)->delete();
                        $dup->delete();
                    }
                }

            } catch (Exception $e) {
                Log::error("Error syncing brand {$brandKey}: " . $e->getMessage());
                $stats['errors'][] = "Brand {$brandKey}: " . $e->getMessage();
            }
        }

        return $stats;
    }

    /**
     * Consolidate an entire brand with all its regional/platform groups into ONE 6valley Master Product
     */
    protected function syncBrandAsSingleMasterProduct(
        array $brandConfig,
        Brand $brand,
        Category $category,
        Attribute $attribute,
        array $finalGroups,
        float $markupPercent,
        array &$stats,
        array &$retainedProductIds
    ): void {
        $productName = $brandConfig['name']; // Clean, single product name (e.g. "Apple iTunes")
        $digitalExtensions = [];
        $itemsByVariant = [];
        $allVariations = [];
        $totalStock = 0;
        $hasMultipleGroups = count($finalGroups) > 1;

        foreach ($finalGroups as $group) {
            $rawGroupLabel = $group['category_name'] ?? 'Global';
            $regionLabel = $hasMultipleGroups ? $this->cleanGroupLabel($brandConfig['name'], $rawGroupLabel) : 'Package';
            if (empty($regionLabel)) {
                $regionLabel = $hasMultipleGroups ? 'Global' : 'Package';
            }

            $items = $group['items'] ?? [];
            $regionOptions = [];

            foreach ($items as $item) {
                $externalItemId = $item['id'];
                $itemStock = (int) ($item['quantity'] ?? 999);
                $rawPrice = isset($item['price']) && $item['price'] !== null ? (float) $item['price'] : null;
                $rawCurrency = strtolower($item['currency'] ?? 'usd');

                if ($rawPrice === null || $rawPrice <= 0) {
                    continue;
                }

                $costUsd = $this->convertToUsd($rawPrice, $rawCurrency);
                if ($costUsd <= 0) {
                    continue;
                }

                // Strict price & denomination quality filters
                $amount = (float) ($item['attributes']['amount'] ?? $item['attributes']['face_value_amount'] ?? 0);
                $isGiftCard = in_array(strtolower($brandConfig['category'] ?? ''), ['gift cards']);
                $isSubscription = in_array(strtolower($brandConfig['category'] ?? ''), ['subscriptions']);

                if ($isGiftCard) {
                    // Gift cards: ignore anything wholesale < $4.00 USD (eliminates 1 EUR, 1 GBP, 10 BRL, 11 SAR micro/test keys)
                    if ($costUsd < 4.00) {
                        continue;
                    }
                    if ($amount > 0 && $amount < 5) {
                        continue;
                    }
                    // Filter out heavily overpriced third-party reseller keys for USD
                    if ($rawCurrency === 'usd' && $amount > 0 && $costUsd > ($amount * 1.30)) {
                        continue;
                    }
                } elseif ($isSubscription) {
                    // Subscriptions: minimum cost $2.00 (allow 1-month, 3-month, etc.)
                    if ($costUsd < 2.00) {
                        continue;
                    }
                } else {
                    // Gaming cards: ignore items costing less than $0.50 USD
                    if ($costUsd < 0.50) {
                        continue;
                    }
                }

                // Calculate selling price with markup
                $retailPrice = round($costUsd * (1 + ($markupPercent / 100)), 2);
                if ($retailPrice <= 0) {
                    continue;
                }

                $label = $this->extractVariantLabel($item, $brandConfig['name'], $rawGroupLabel);
                if (isset($regionOptions[$label])) {
                    $label = $label . ' (' . strtoupper($rawCurrency) . ')';
                }
                if (isset($regionOptions[$label])) {
                    continue; // Skip duplicate label within same region
                }

                $regionOptions[$label] = [
                    'item' => $item,
                    'cost_usd' => $costUsd,
                    'retail_price' => $retailPrice,
                    'amount' => $amount,
                    'label' => $label,
                ];
            }

            if (empty($regionOptions)) {
                continue;
            }

            // Sort region options by retail price ascending
            uasort($regionOptions, fn($a, $b) => $a['retail_price'] <=> $b['retail_price']);

            $digitalExtensions[$regionLabel] = array_keys($regionOptions);

            foreach ($regionOptions as $optLabel => $optData) {
                $item = $optData['item'];
                $externalItemId = $item['id'];
                $variantKey = "{$regionLabel}-" . preg_replace('/\s+/', '-', $optLabel);
                $legacyVariantCode = str_replace([' ', ','], ['', ''], "{$regionLabel}-{$optLabel}");
                $sku = 'FR-' . strtoupper(substr(md5($externalItemId), 0, 8));

                $totalStock += (int) ($item['quantity'] ?? 999);

                $allVariations[] = [
                    'type' => $legacyVariantCode,
                    'price' => (double) $optData['retail_price'],
                    'sku' => $sku,
                    'qty' => (int) ($item['quantity'] ?? 999),
                ];

                $itemsByVariant[$variantKey] = [
                    'item' => $item,
                    'cost_usd' => $optData['cost_usd'],
                    'retail_price' => $optData['retail_price'],
                    'sku' => $sku,
                    'variant_key' => $variantKey,
                    'region_label' => $regionLabel,
                ];
            }
        }

        if (empty($itemsByVariant) || empty($digitalExtensions)) {
            Log::warning("No valid variations found for brand: {$productName}");
            return;
        }

        // Sort regions: USA/Global first, followed by Saudi Arabia, UAE, UK, Germany, etc.
        uksort($digitalExtensions, function($a, $b) {
            $priority = [
                'Global' => 1, 'Worldwide' => 1, 'USA' => 2, 'United States' => 2,
                'Saudi Arabia' => 3, 'UAE' => 4, 'United Kingdom' => 5, 'Germany' => 6,
                'Europe' => 7, 'Canada' => 8, 'PC / EA App' => 9, 'Xbox' => 10,
            ];
            $pa = $priority[$a] ?? 99;
            $pb = $priority[$b] ?? 99;
            return $pa === $pb ? strcasecmp($a, $b) : ($pa <=> $pb);
        });

        // Use lowest retail price of the primary/first region as the base product display price
        $firstRegionKey = array_key_first($digitalExtensions);
        $firstRegionOptions = array_filter($itemsByVariant, fn($v) => $v['region_label'] === $firstRegionKey);

        $lowestRetailPrice = !empty($firstRegionOptions)
            ? min(array_column($firstRegionOptions, 'retail_price'))
            : min(array_column($allVariations, 'price'));

        $lowestCostUsd = !empty($firstRegionOptions)
            ? min(array_column($firstRegionOptions, 'cost_usd'))
            : min(array_column($itemsByVariant, 'cost_usd'));

        $digitalFileTypes = array_keys($digitalExtensions);

        // Find or create ONE Master Product for this brand
        $masterProduct = Product::where('brand_id', $brand->id)->first();
        $sku = 'FR-' . strtoupper(substr(md5($brandConfig['slug']), 0, 8));

        $productData = [
            'name' => $productName,
            'unit_price' => $lowestRetailPrice,
            'purchase_price' => $lowestCostUsd,
            'current_stock' => $totalStock,
            'attributes' => json_encode([]),
            'choice_options' => json_encode([]),
            'variation' => json_encode($allVariations),
            'digital_product_file_types' => $digitalFileTypes,
            'digital_product_extensions' => $digitalExtensions,
            'status' => 1,
            'published' => 1,
        ];

        if ($masterProduct) {
            $masterProduct->update($productData);
            $stats['products_updated']++;
        } else {
            $slug = Str::slug($productName) . '-' . Str::random(5);

            $masterProduct = Product::create(array_merge($productData, [
                'added_by' => 'admin',
                'user_id' => 1,
                'slug' => $slug,
                'product_type' => 'digital',
                'digital_product_type' => 'ready_product',
                'category_id' => $category->id,
                'category_ids' => json_encode([['id' => (string) $category->id, 'position' => 1]]),
                'brand_id' => $brand->id,
                'unit' => 'pc',
                'min_qty' => 1,
                'minimum_order_qty' => 1,
                'refundable' => 0,
                'tax' => 0,
                'tax_type' => 'percent',
                'tax_model' => 'include',
                'discount' => 0,
                'discount_type' => 'flat',
                'thumbnail' => 'product.png',
                'thumbnail_storage_type' => 'public',
                'images' => json_encode(['product.png']),
                'colors' => json_encode([]),
                'request_status' => 1,
                'featured_status' => 1,
                'featured' => 1,
                'free_shipping' => 1,
                'multiply_qty' => 0,
                'shipping_cost' => 0,
                'code' => $sku,
                'details' => "<p><strong>{$productName}</strong> — Select your region and package below for instant code delivery directly upon checkout.</p>",
            ]));
            $stats['products_imported']++;
        }

        $retainedProductIds[] = $masterProduct->id;

        // Clean obsolete variations
        DigitalProductVariation::where('product_id', $masterProduct->id)
            ->whereNotIn('variant_key', array_keys($itemsByVariant))
            ->delete();

        // Clean obsolete provider mappings
        $validExternalIds = array_map(fn($v) => $v['item']['id'], $itemsByVariant);
        ProductProviderMapping::where('product_id', $masterProduct->id)
            ->where('provider_slug', 'foxreload')
            ->whereNotIn('external_item_id', $validExternalIds)
            ->delete();

        // Populate DigitalProductVariation & ProductProviderMapping
        foreach ($itemsByVariant as $variantKey => $info) {
            DigitalProductVariation::updateOrCreate(
                [
                    'product_id' => $masterProduct->id,
                    'variant_key' => $variantKey,
                ],
                [
                    'price' => $info['retail_price'],
                    'sku' => $info['sku'],
                    'file' => '',
                ]
            );

            $item = $info['item'];
            ProductProviderMapping::updateOrCreate(
                [
                    'product_id' => $masterProduct->id,
                    'provider_slug' => 'foxreload',
                    'external_item_id' => $item['id'],
                ],
                [
                    'variant_key' => $variantKey,
                    'external_category_id' => $item['_fox_category_id'] ?? null,
                    'cost_price' => $info['cost_usd'],
                    'currency' => 'usd',
                    'is_service' => (bool) ($item['isService'] ?? false),
                    'required_fields' => $item['requiredNoteFields'] ?? null,
                    'is_mock' => config('suppliers.suppliers.foxreload.is_mock', true),
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Group multiple items of the same platform/subcategory into one 6valley product with native variations
     */
    protected function syncGroupedProductWithVariations(
        array $brandConfig,
        Brand $brand,
        Category $category,
        Attribute $attribute,
        array $group,
        int $totalGroupsCount,
        float $markupPercent,
        array &$stats,
        array &$retainedProductIds
    ): void {
        $productName = $this->buildProductName($brandConfig['name'], $group['category_name'] ?? null, $totalGroupsCount);
        $items = $group['items'] ?? [];
        $optionsList = [];
        $variations = [];
        $itemsByVariant = [];
        $totalStock = 0;

        foreach ($items as $item) {
            $externalItemId = $item['id'];
            $itemStock = (int) ($item['quantity'] ?? 0);
            $rawPrice = isset($item['price']) && $item['price'] !== null ? (float) $item['price'] : null;
            $rawCurrency = strtolower($item['currency'] ?? 'usd');

            $suggestedStorefrontPrice = isset($item['storefrontPrice']) && $item['storefrontPrice'] !== null
                ? (float) $item['storefrontPrice']
                : null;

            // 1. Resolve wholesale cost in USD
            $costUsd = $rawPrice !== null ? $this->convertToUsd($rawPrice, $rawCurrency) : null;

            // 2. Resolve retail / selling price
            if ($suggestedStorefrontPrice !== null && $suggestedStorefrontPrice > 0) {
                $retailPrice = round($this->convertToUsd($suggestedStorefrontPrice, $rawCurrency), 2);
                if ($costUsd === null) {
                    $costUsd = round($retailPrice / (1 + ($markupPercent / 100)), 2);
                }
            } elseif ($costUsd !== null && $costUsd > 0) {
                $retailPrice = round($costUsd * (1 + ($markupPercent / 100)), 2);
            } else {
                $faceValue = $item['attributes']['face_value_amount'] ?? $item['attributes']['amount'] ?? null;
                if ($faceValue !== null && is_numeric($faceValue) && (float) $faceValue > 0) {
                    $faceCurrency = strtolower($item['attributes']['face_value_currency'] ?? $item['attributes']['currency'] ?? 'usd');
                    $retailPrice = round($this->convertToUsd((float) $faceValue, $faceCurrency), 2);
                    $costUsd = round($retailPrice / (1 + ($markupPercent / 100)), 2);
                } else {
                    continue;
                }
            }

            if ($retailPrice <= 0) {
                continue;
            }

            $label = $this->extractVariantLabel($item, $brandConfig['name'], $group['category_name'] ?? null);
            if (in_array($label, $optionsList)) {
                $label = $label . ' (' . strtoupper($rawCurrency) . ')';
            }
            if (in_array($label, $optionsList)) {
                $label = $label . ' #' . (count($optionsList) + 1);
            }

            $optionsList[] = $label;

            // 6valley native digital variant key: e.g. "Package-1,000-Coins"
            $variantKey = 'Package-' . preg_replace('/\s+/', '-', $label);
            $legacyVariantCode = str_replace([' ', ','], ['', ''], $label);

            $totalStock += $itemStock;

            $sku = 'FR-' . strtoupper(substr(md5($externalItemId), 0, 8));

            $variations[] = [
                'type' => $legacyVariantCode,
                'price' => (double) $retailPrice,
                'sku' => $sku,
                'qty' => $itemStock,
            ];

            $itemsByVariant[$variantKey] = [
                'item' => $item,
                'cost_usd' => $costUsd,
                'retail_price' => $retailPrice,
                'variant_key' => $variantKey,
                'legacy_variant_code' => $legacyVariantCode,
                'sku' => $sku,
                'label' => $label,
            ];
        }

        if (empty($itemsByVariant)) {
            Log::warning("No valid priced variations found for group: {$productName}");
            return;
        }

        // Sort variations by retail price ascending so storefront displays them in clean order ($5, $10, $20, $50...)
        uasort($itemsByVariant, fn($a, $b) => $a['retail_price'] <=> $b['retail_price']);

        $optionsList = array_map(fn($v) => $v['label'], $itemsByVariant);
        $variations = array_map(fn($v) => [
            'type' => $v['legacy_variant_code'],
            'price' => (double) $v['retail_price'],
            'sku' => $v['sku'],
            'qty' => (int) ($v['item']['quantity'] ?? 999),
        ], array_values($itemsByVariant));

        // Base price is lowest variation price
        $lowestRetailPrice = min(array_column($variations, 'price'));
        $lowestCostUsd = min(array_column($itemsByVariant, 'cost_usd'));

        // 6valley native digital variation structures
        $digitalFileTypes = ['Package'];
        $digitalExtensions = ['Package' => array_values($optionsList)];

        // Deterministic Product matching:
        $masterProduct = null;

        // 1. Match by external_category_id in ProductProviderMapping (not already retained in this brand run)
        if (!empty($group['category_id'])) {
            $existingMapping = ProductProviderMapping::where('provider_slug', 'foxreload')
                ->where('external_category_id', $group['category_id'])
                ->whereNotIn('product_id', $retainedProductIds)
                ->first();

            if ($existingMapping && $existingMapping->product && $existingMapping->product->brand_id == $brand->id) {
                $masterProduct = $existingMapping->product;
            }
        }

        // 2. Match by exact product name within this brand (not already retained)
        if (!$masterProduct) {
            $masterProduct = Product::where('brand_id', $brand->id)
                ->where('category_id', $category->id)
                ->where('name', $productName)
                ->whereNotIn('id', $retainedProductIds)
                ->first();
        }

        $sku = 'FR-' . strtoupper(substr(md5($brandConfig['slug'] . '-' . ($group['category_id'] ?? $productName)), 0, 8));

        $countryInfo = $this->resolveGroupCountry($group['category_name'] ?? null, $productName);

        $productData = [
            'name' => $productName,
            'country' => $countryInfo['country'],
            'country_code' => $countryInfo['country_code'],
            'unit_price' => $lowestRetailPrice,
            'purchase_price' => $lowestCostUsd,
            'current_stock' => $totalStock,
            'attributes' => json_encode([]),
            'choice_options' => json_encode([]),
            'variation' => json_encode($variations),
            'digital_product_file_types' => $digitalFileTypes,
            'digital_product_extensions' => $digitalExtensions,
            'status' => 1,
            'published' => 1,
        ];

        if ($masterProduct) {
            $masterProduct->update($productData);
            $stats['products_updated']++;
        } else {
            $slug = Str::slug($productName) . '-' . Str::random(5);

            $masterProduct = Product::create(array_merge($productData, [
                'added_by' => 'admin',
                'user_id' => 1,
                'slug' => $slug,
                'product_type' => 'digital',
                'digital_product_type' => 'ready_product',
                'category_id' => $category->id,
                'category_ids' => json_encode([['id' => (string) $category->id, 'position' => 1]]),
                'brand_id' => $brand->id,
                'unit' => 'pc',
                'min_qty' => 1,
                'minimum_order_qty' => 1,
                'refundable' => 0,
                'tax' => 0,
                'tax_type' => 'percent',
                'tax_model' => 'include',
                'discount' => 0,
                'discount_type' => 'flat',
                'thumbnail' => 'product.png',
                'thumbnail_storage_type' => 'public',
                'images' => json_encode(['product.png']),
                'colors' => json_encode([]),
                'request_status' => 1,
                'featured_status' => 1,
                'featured' => 1,
                'free_shipping' => 1,
                'multiply_qty' => 0,
                'shipping_cost' => 0,
                'code' => $sku,
                'details' => "<p><strong>{$productName}</strong> — Select your package below for instant code delivery directly upon checkout.</p>",
            ]));
            $stats['products_imported']++;
        }

        $retainedProductIds[] = $masterProduct->id;

        // Clean obsolete variations for this product
        DigitalProductVariation::where('product_id', $masterProduct->id)
            ->whereNotIn('variant_key', array_keys($itemsByVariant))
            ->delete();

        // Clean obsolete provider mappings for this product (e.g. items that moved to another group)
        $validExternalIds = array_map(fn($v) => $v['item']['id'], $itemsByVariant);
        ProductProviderMapping::where('product_id', $masterProduct->id)
            ->where('provider_slug', 'foxreload')
            ->whereNotIn('external_item_id', $validExternalIds)
            ->delete();

        // Populate DigitalProductVariation table for 6valley native digital checkout
        foreach ($itemsByVariant as $variantKey => $info) {
            DigitalProductVariation::updateOrCreate(
                [
                    'product_id' => $masterProduct->id,
                    'variant_key' => $variantKey,
                ],
                [
                    'price' => $info['retail_price'],
                    'sku' => $info['sku'],
                    'file' => '',
                ]
            );

            // Update ProductProviderMapping so fulfillment can find the supplier item
            $item = $info['item'];
            $externalItemId = $item['id'];

            ProductProviderMapping::updateOrCreate(
                [
                    'product_id' => $masterProduct->id,
                    'provider_slug' => 'foxreload',
                    'external_item_id' => $externalItemId,
                ],
                [
                    'variant_key' => $variantKey,
                    'external_category_id' => $group['category_id'] ?? ($item['_fox_category_id'] ?? null),
                    'cost_price' => $info['cost_usd'],
                    'currency' => 'usd',
                    'is_service' => (bool) ($item['isService'] ?? false),
                    'required_fields' => $item['requiredNoteFields'] ?? null,
                    'is_mock' => config('suppliers.suppliers.foxreload.is_mock', true),
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Sync items as standalone individual products
     */
    protected function syncIndividualProducts(
        array $brandConfig,
        Brand $brand,
        Category $category,
        array $items,
        float $markupPercent,
        array &$stats,
        array &$retainedProductIds
    ): void {
        foreach ($items as $item) {
            $externalItemId = $item['id'];
            $itemStock = (int) ($item['quantity'] ?? 0);
            $rawPrice = isset($item['price']) && $item['price'] !== null ? (float) $item['price'] : null;
            $rawCurrency = strtolower($item['currency'] ?? 'usd');

            $suggestedStorefrontPrice = isset($item['storefrontPrice']) && $item['storefrontPrice'] !== null
                ? (float) $item['storefrontPrice']
                : null;

            $costUsd = $rawPrice !== null ? $this->convertToUsd($rawPrice, $rawCurrency) : null;

            if ($suggestedStorefrontPrice !== null && $suggestedStorefrontPrice > 0) {
                $retailPrice = round($this->convertToUsd($suggestedStorefrontPrice, $rawCurrency), 2);
                if ($costUsd === null) {
                    $costUsd = round($retailPrice / (1 + ($markupPercent / 100)), 2);
                }
            } elseif ($costUsd !== null && $costUsd > 0) {
                $retailPrice = round($costUsd * (1 + ($markupPercent / 100)), 2);
            } else {
                $faceValue = $item['attributes']['face_value_amount'] ?? $item['attributes']['amount'] ?? null;
                if ($faceValue !== null && is_numeric($faceValue) && (float) $faceValue > 0) {
                    $faceCurrency = strtolower($item['attributes']['face_value_currency'] ?? $item['attributes']['currency'] ?? 'usd');
                    $retailPrice = round($this->convertToUsd((float) $faceValue, $faceCurrency), 2);
                    $costUsd = round($retailPrice / (1 + ($markupPercent / 100)), 2);
                } else {
                    continue;
                }
            }

            if ($retailPrice <= 0) {
                continue;
            }

            $mapping = ProductProviderMapping::where('provider_slug', 'foxreload')
                ->where('external_item_id', $externalItemId)
                ->first();

            if ($mapping && $mapping->product) {
                $product = $mapping->product;
                $product->update([
                    'unit_price' => $retailPrice,
                    'purchase_price' => $costUsd,
                    'current_stock' => $item['quantity'] ?? 999,
                    'status' => 1,
                ]);

                $mapping->update([
                    'cost_price' => $costUsd,
                    'is_mock' => config('suppliers.suppliers.foxreload.is_mock', true),
                    'is_active' => true,
                ]);

                $retainedProductIds[] = $product->id;
                $stats['products_updated']++;
            } else {
                $slug = Str::slug($item['name']) . '-' . Str::random(5);
                $sku = 'FR-' . strtoupper(substr(md5($externalItemId), 0, 8));
                $countryInfo = $this->resolveGroupCountry(null, $item['name']);

                $product = Product::create([
                    'added_by' => 'admin',
                    'user_id' => 1,
                    'name' => $item['name'],
                    'country' => $countryInfo['country'],
                    'country_code' => $countryInfo['country_code'],
                    'slug' => $slug,
                    'product_type' => 'digital',
                    'digital_product_type' => 'ready_product',
                    'category_id' => $category->id,
                    'category_ids' => json_encode([['id' => (string) $category->id, 'position' => 1]]),
                    'brand_id' => $brand->id,
                    'unit' => 'pc',
                    'min_qty' => 1,
                    'minimum_order_qty' => 1,
                    'current_stock' => $item['quantity'] ?? 999,
                    'refundable' => 0,
                    'unit_price' => $retailPrice,
                    'purchase_price' => $costUsd,
                    'tax' => 0,
                    'tax_type' => 'percent',
                    'tax_model' => 'include',
                    'discount' => 0,
                    'discount_type' => 'flat',
                    'thumbnail' => 'product.png',
                    'thumbnail_storage_type' => 'public',
                    'images' => json_encode(['product.png']),
                    'colors' => json_encode([]),
                    'attributes' => json_encode([]),
                    'choice_options' => json_encode([]),
                    'variation' => json_encode([]),
                    'published' => 1,
                    'status' => 1,
                    'request_status' => 1,
                    'featured_status' => 1,
                    'featured' => 1,
                    'free_shipping' => 1,
                    'multiply_qty' => 0,
                    'shipping_cost' => 0,
                    'code' => $sku,
                    'details' => "<p><strong>{$item['name']}</strong> — Instant digital code / direct top-up. Delivered directly to your screen and account upon checkout.</p>",
                ]);

                ProductProviderMapping::create([
                    'product_id' => $product->id,
                    'variant_key' => null,
                    'provider_slug' => 'foxreload',
                    'external_item_id' => $externalItemId,
                    'external_category_id' => $item['_fox_category_id'] ?? null,
                    'cost_price' => $costUsd,
                    'currency' => 'usd',
                    'is_service' => (bool) ($item['isService'] ?? false),
                    'required_fields' => $item['requiredNoteFields'] ?? null,
                    'is_mock' => config('suppliers.suppliers.foxreload.is_mock', true),
                    'is_active' => true,
                ]);

                $retainedProductIds[] = $product->id;
                $stats['products_imported']++;
            }
        }
    }

    /**
     * Build clean product name based on brand and platform/region label
     */
    protected function buildProductName(string $brandName, ?string $groupLabel, int $totalGroupsCount): string
    {
        if ($totalGroupsCount === 1 || empty($groupLabel)) {
            return $brandName;
        }

        $cleanLabel = $this->cleanGroupLabel($brandName, $groupLabel);

        if (empty($cleanLabel) || in_array(strtolower($cleanLabel), ['global', 'ww', 'default'])) {
            return $brandName;
        }

        // Special handling if brand includes 'Mobile' but subcategory is Steam PC
        if (stripos($cleanLabel, 'Steam') !== false && stripos($brandName, 'Mobile') !== false) {
            $baseName = trim(str_ireplace('Mobile', '', $brandName));
            return "{$baseName} ({$cleanLabel})";
        }

        return "{$brandName} ({$cleanLabel})";
    }

    /**
     * Clean subcategory/group label to isolate platform or country
     */
    protected function cleanGroupLabel(string $brandName, string $subName): string
    {
        $clean = preg_replace('/' . preg_quote($brandName, '/') . '/i', '', $subName);
        $clean = preg_replace('/\b(Apex|EA SPORTS FC|EA Sports FC|FIFA|Fortnite|Minecraft|PUBG|PUBG Mobile)\b/i', '', $clean);
        $clean = trim($clean, " -:_|()\t\n\r\0\x0B");

        if (stripos($clean, 'EA PC') !== false || stripos($clean, 'PC Points') !== false || preg_match('/\b(PC Key|PC)\b/i', $clean)) {
            return 'PC / EA App';
        }

        if (stripos($clean, 'Xbox') !== false) {
            return 'Xbox';
        }

        if (stripos($clean, 'Playstation') !== false || stripos($clean, 'PSN') !== false) {
            return 'PlayStation';
        }

        if (stripos($clean, 'Steam') !== false || stripos($clean, 'G-Coin') !== false) {
            return 'Steam PC';
        }

        if (stripos($clean, 'UC') !== false) {
            return 'Mobile UC';
        }

        if (stripos($clean, 'Shark') !== false) {
            return 'Shark Cards';
        }

        return !empty($clean) ? $clean : $subName;
    }

    /**
     * Resolve standardized country and ISO code for a group or product
     */
    protected function resolveGroupCountry(?string $groupLabel, string $productName): array
    {
        $target = trim(($groupLabel ?? '') . ' ' . $productName);
        return \App\Utils\CountryManager::detectCountryFromName($target);
    }

    /**
     * Check if a group contains multiple platforms and split into sub-groups if so
     */
    protected function splitGroupByPlatformIfNeeded(array $group, string $brandName): array
    {
        $items = $group['items'] ?? [];
        if (count($items) <= 1) {
            return [$group];
        }

        $itemsByPlatform = [];

        foreach ($items as $item) {
            $name = $item['name'] ?? '';

            if (preg_match('/\b(Xbox)\b/i', $name)) {
                $platform = 'Xbox';
            } elseif (preg_match('/\b(Steam)\b/i', $name)) {
                $platform = 'Steam (PC)';
            } elseif (preg_match('/\b(PC Key|PC-key|PC|EA Key|EA-key|EA Coins)\b/i', $name)) {
                $platform = 'PC / EA App';
            } elseif (preg_match('/\b(PSN|PlayStation|PS5|PS4)\b/i', $name)) {
                $platform = 'PlayStation';
            } elseif (preg_match('/\b(Nintendo|Switch)\b/i', $name)) {
                $platform = 'Nintendo Switch';
            } else {
                $platform = $group['category_name'] ?: 'Standard';
            }

            $itemsByPlatform[$platform][] = $item;
        }

        if (count($itemsByPlatform) > 1) {
            $subGroups = [];
            foreach ($itemsByPlatform as $platformName => $platformItems) {
                $subGroups[] = [
                    'category_id' => $group['category_id'],
                    'category_name' => $platformName,
                    'is_subcategory' => true,
                    'items' => $platformItems,
                ];
            }
            return $subGroups;
        }

        return [$group];
    }

    /**
     * Clean and extract readable variation denomination label from supplier item
     */
    protected function extractVariantLabel(array|string $itemOrName, string $brandName, ?string $groupLabel = null): string
    {
        if (is_array($itemOrName)) {
            $amount = $itemOrName['attributes']['face_value_amount'] ?? $itemOrName['attributes']['amount'] ?? null;
            $rawUnit = $itemOrName['attributes']['face_value_currency'] ?? $itemOrName['attributes']['currency'] ?? '';

            if (preg_match('/(Coins|Points|Diamonds|Gems|Minecoins|V-Bucks|G-Coin|UC|Tokens|Credits|USD|EUR|GBP|CAD|AUD|SAR|AED|BRL|TRY|INR)/i', $rawUnit, $um)) {
                $cleanUnit = $um[1];
            } elseif (preg_match('/(Coins|Points|Diamonds|Gems|Minecoins|V-Bucks|G-Coin|UC|Tokens|Credits|USD|EUR|GBP|CAD|AUD|SAR|AED|BRL|TRY|INR)/i', $itemOrName['name'] ?? '', $nm)) {
                $cleanUnit = $nm[1];
            } else {
                $cleanUnit = '';
            }

            if ($amount !== null && is_numeric($amount) && (float) $amount > 0 && !empty($cleanUnit)) {
                $formattedAmount = number_format((float) $amount);
                $cleanUnit = match (strtolower($cleanUnit)) {
                    'coins' => 'Coins',
                    'points' => 'Points',
                    'diamonds' => 'Diamonds',
                    'gems' => 'Gems',
                    'minecoins' => 'Minecoins',
                    'v-bucks' => 'V-Bucks',
                    'g-coin' => 'G-Coin',
                    'uc' => 'UC',
                    'tokens' => 'Tokens',
                    'credits' => 'Credits',
                    'usd' => '$',
                    'eur' => '€',
                    'gbp' => '£',
                    default => strtoupper($cleanUnit),
                };

                if ($cleanUnit === '$') {
                    return "\${$formattedAmount}";
                }
                if ($cleanUnit === '€') {
                    return "€{$formattedAmount}";
                }
                if ($cleanUnit === '£') {
                    return "£{$formattedAmount}";
                }

                return "{$formattedAmount} {$cleanUnit}";
            }

            $clean = $itemOrName['name'] ?? '';
        } else {
            $clean = $itemOrName;
        }

        // Strip brand name
        $clean = preg_replace('/' . preg_quote($brandName, '/') . '/i', '', $clean);
        $clean = preg_replace('/\b(PUBG|Mobile|Steam|Apex|Legends|Roblox|Minecraft|Fortnite|Jawaker|Free Fire)\b/i', '', $clean);

        // Strip platform and store tags
        $clean = preg_replace('/\s*\([^)]*(Xbox|PC|EA|Key|WW|Global|Official|Gift Card|US|USA|EU|DE|TR|Steam)[^)]*\)/i', '', $clean);
        $clean = preg_replace('/\b(EA KEY|EA Key|EA Coins|EA|PC Key|PC-key|PC|Xbox|Steam|WW|Global|Official|Gift Card|Redeem Code|Voucher|REGION FREE OFFICIAL|key)\b/i', '', $clean);

        // Strip leftover delimiters
        $clean = preg_replace('/[|\-–—:]+/', ' ', $clean);
        $clean = trim(preg_replace('/\s+/', ' ', $clean));

        // Format numbers with commas (e.g. 1000 -> 1,000)
        $clean = preg_replace_callback('/\b\d{4,}\b/', function ($m) {
            return number_format((float) str_replace(',', '', $m[0]));
        }, $clean);

        return !empty($clean) ? $clean : (is_array($itemOrName) ? ($itemOrName['name'] ?? 'Standard') : $itemOrName);
    }

    /**
     * Convert supplier price to USD
     */
    protected function convertToUsd(float $price, string $currency): float
    {
        return match (strtolower($currency)) {
            'rub' => round($price / 95.0, 2),   // ~95 RUB per USD
            'eur' => round($price * 1.08, 2),   // ~1.08 USD per EUR
            'gbp' => round($price * 1.28, 2),
            'cad' => round($price * 0.74, 2),
            'aud' => round($price * 0.65, 2),
            'sar', 'aed' => round($price / 3.75, 2),
            'kwd' => round($price * 3.26, 2),
            'bhd' => round($price * 2.65, 2),
            'try' => round($price / 34.0, 2),
            'brl' => round($price / 5.5, 2),
            'inr' => round($price / 84.0, 2),
            'php' => round($price / 58.0, 2),
            'thb' => round($price / 34.0, 2),
            'sgd' => round($price * 0.77, 2),
            'jpy' => round($price / 145.0, 2),
            'krw' => round($price / 1350.0, 2),
            default => round($price, 2),        // USD / default
        };
    }
}
