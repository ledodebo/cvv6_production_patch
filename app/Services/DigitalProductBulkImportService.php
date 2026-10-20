<?php

namespace App\Services;

use App\Enums\GlobalConstant;
use App\Models\Brand;
use App\Models\Category;
use App\Models\DigitalProductVariation;
use App\Models\Product;
use App\Models\ProductProviderMapping;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DigitalProductBulkImportService
{
    /**
     * Required and optional digital catalog columns.
     */
    protected array $digitalSignatureColumns = [
        'variation_name',
        'is_master_product',
        'external_item_id',
        'provider_slug',
        'wholesale_cost',
    ];

    /**
     * Check if the uploaded file has the digital catalog format.
     */
    public function isDigitalCatalog(UploadedFile|string $file): bool
    {
        @ini_set('memory_limit', '512M');
        try {
            $filePath = is_string($file) ? $file : $file->getRealPath();
            $headerRow = [];

            // 1. Ultra-fast streaming header read using OpenSpout (10ms, <1MB RAM)
            if (class_exists(\OpenSpout\Reader\XLSX\Reader::class)) {
                try {
                    $reader = new \OpenSpout\Reader\XLSX\Reader();
                    $reader->open($filePath);
                    foreach ($reader->getSheetIterator() as $sheet) {
                        foreach ($sheet->getRowIterator() as $row) {
                            $headerRow = $row->toArray();
                            break 2;
                        }
                    }
                    $reader->close();
                } catch (\Throwable $e) {
                    $headerRow = [];
                }
            }

            // 2. Try CSV OpenSpout reader if file was CSV
            if (empty($headerRow) && class_exists(\OpenSpout\Reader\CSV\Reader::class)) {
                try {
                    $reader = new \OpenSpout\Reader\CSV\Reader();
                    $reader->open($filePath);
                    foreach ($reader->getSheetIterator() as $sheet) {
                        foreach ($sheet->getRowIterator() as $row) {
                            $headerRow = $row->toArray();
                            break 2;
                        }
                    }
                    $reader->close();
                } catch (\Throwable $e) {
                    $headerRow = [];
                }
            }

            // 3. Fallback to PhpSpreadsheet if OpenSpout didn't load
            if (empty($headerRow)) {
                try {
                    $reader = IOFactory::createReaderForFile($filePath);
                    $reader->setReadDataOnly(true);
                    $reader->setReadFilter(new class implements IReadFilter {
                        public function readCell($columnAddress, $row, $worksheetName = ''): bool {
                            return $row == 1;
                        }
                    });
                    $spreadsheet = $reader->load($filePath);
                    $sheet = $spreadsheet->getActiveSheet();
                    $headerRow = $sheet->rangeToArray('A1:Z1')[0] ?? [];
                    $spreadsheet->disconnectWorksheets();
                    unset($spreadsheet, $sheet, $reader);
                } catch (\Throwable $e) {
                    $headerRow = [];
                }
            }

            if (empty($headerRow)) {
                return false;
            }

            $normalizedHeaders = array_map(function ($h) {
                return strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', (string)$h), '_'));
            }, array_filter($headerRow));

            // Distinct digital catalog markers
            $digitalMarkers = [
                'is_master_product',
                'external_item_id',
                'foxreload_item_id',
                'provider_slug',
                'wholesale_cost',
                'variation_name',
                'country_name',
                'retail_price',
            ];

            $matchedMarkers = 0;
            foreach ($digitalMarkers as $marker) {
                foreach ($normalizedHeaders as $hdr) {
                    if ($hdr === $marker || str_contains($hdr, $marker)) {
                        $matchedMarkers++;
                        break; // Each distinct marker can only contribute at most 1 match
                    }
                }
            }

            return $matchedMarkers >= 2;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('isDigitalCatalog detection error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Import digital products and variations from Excel.
     */
    public function importDigitalProducts(UploadedFile|string $file, string $addedBy = 'admin', ?int $userId = 1): array
    {
        @ini_set('memory_limit', '1024M');
        @ini_set('max_execution_time', '1800');
        DB::disableQueryLog();

        $filePath = is_string($file) ? $file : $file->getRealPath();
        $headerMap = [];
        $grouped = [];
        $rowCount = 0;

        $parsePrice = function ($val): float {
            if (is_numeric($val)) return (float)$val;
            $clean = preg_replace('/[^0-9.]/', '', (string)$val);
            return is_numeric($clean) ? (float)$clean : 0.0;
        };

        // 1. Streaming OpenSpout reader: parses 30,000+ rows in ~6s using <40MB RAM
        $streamSuccess = false;
        if (class_exists(\OpenSpout\Reader\XLSX\Reader::class)) {
            try {
                $reader = new \OpenSpout\Reader\XLSX\Reader();
                $reader->open($filePath);

                foreach ($reader->getSheetIterator() as $sheet) {
                    foreach ($sheet->getRowIterator() as $row) {
                        $cells = $row->toArray();
                        if ($rowCount === 0) {
                            foreach ($cells as $idx => $rawHeader) {
                                $norm = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', (string)$rawHeader), '_'));
                                $collapsed = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string)$rawHeader));
                                if ($norm) {
                                    $headerMap[$norm] = $idx;
                                    $headerMap[$collapsed] = $idx;
                                }
                            }
                            $rowCount++;
                            continue;
                        }
                        $rowCount++;

                        $col = function (array $aliases, $default = '') use ($cells, $headerMap) {
                            foreach ($aliases as $alias) {
                                $normAlias = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', (string)$alias), '_'));
                                $colAlias = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string)$alias));
                                if (isset($headerMap[$normAlias]) && isset($cells[$headerMap[$normAlias]])) {
                                    $v = trim((string)$cells[$headerMap[$normAlias]]);
                                    if ($v !== '') return $v;
                                }
                                if (isset($headerMap[$colAlias]) && isset($cells[$headerMap[$colAlias]])) {
                                    $v = trim((string)$cells[$headerMap[$colAlias]]);
                                    if ($v !== '') return $v;
                                }
                            }
                            return $default;
                        };

                        $productName = $col(['product_name', 'name', 'product']);
                        $variationName = $col(['variation_name', 'package', 'package_name', 'variation', 'denomination']);

                        if (!$productName || !$variationName) continue;

                        $brandName = $col(['brand', 'brand_name'], $productName);
                        $groupKey = Str::slug($brandName) . '___' . Str::slug($productName);

                        $rawCost = $col(['wholesale_cost_usd', 'wholesale_cost', 'cost_price', 'cost'], 0);
                        $rawPrice = $col(['retail_price_usd', 'retail_price', 'price', 'unit_price'], 0);

                        $grouped[$groupKey][] = [
                            'category' => $col(['category', 'category_name'], 'Gaming Cards'),
                            'brand' => $brandName,
                            'product_name' => $productName,
                            'is_master_product' => $col(['is_master_product', 'master_product', 'is_master'], 'No'),
                            'country_name' => $col(['country_name', 'country', 'region'], 'Global'),
                            'country_code' => strtoupper($col(['country_code', 'country_iso', 'iso_code'], 'GL')),
                            'variation_name' => $variationName,
                            'cost_price' => $parsePrice($rawCost),
                            'retail_price' => $parsePrice($rawPrice),
                            'stock' => (int) $col(['stock', 'quantity', 'current_stock'], 999),
                            'sku' => $col(['sku', 'code', 'product_sku', 'item_code']),
                            'provider_slug' => $col(['provider_slug', 'provider'], 'foxreload'),
                            'external_item_id' => $col(['external_item_id', 'item_id', 'foxreload_item_id']),
                            'external_category_id' => $col(['external_category_id', 'category_id', 'foxreload_category_id']),
                            'status' => (int) $col(['status', 'published', 'active'], 1),
                            'thumbnail' => $col(['thumbnail', 'image'], 'product.png'),
                            'is_mock' => $col(['is_mock', 'mock', 'sandbox']),
                        ];
                    }
                    break;
                }
                $reader->close();
                $streamSuccess = !empty($grouped);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('OpenSpout stream import failed, falling back: ' . $e->getMessage());
                $streamSuccess = false;
            }
        }

        // 2. Fallback to PhpSpreadsheet if OpenSpout wasn't used or produced no groups
        if (!$streamSuccess) {
            try {
                $reader = IOFactory::createReaderForFile($filePath);
                $reader->setReadDataOnly(true);
                $spreadsheet = $reader->load($filePath);
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray(null, true, false, true);
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet, $sheet, $reader);

                if (count($rows) >= 2) {
                    $headerRow = array_shift($rows);
                    $headerMap = [];
                    foreach ($headerRow as $colLetter => $rawHeader) {
                        $normalized = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', (string)$rawHeader), '_'));
                        $collapsed = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string)$rawHeader));
                        if ($normalized) {
                            $headerMap[$normalized] = $colLetter;
                            $headerMap[$collapsed] = $colLetter;
                        }
                    }

                    $col = function (array $row, array $aliases, $default = '') use ($headerMap) {
                        foreach ($aliases as $alias) {
                            $normAlias = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', (string)$alias), '_'));
                            $colAlias = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string)$alias));
                            if (isset($headerMap[$normAlias]) && isset($row[$headerMap[$normAlias]])) {
                                $val = trim((string)$row[$headerMap[$normAlias]]);
                                if ($val !== '') return $val;
                            }
                            if (isset($headerMap[$colAlias]) && isset($row[$headerMap[$colAlias]])) {
                                $val = trim((string)$row[$headerMap[$colAlias]]);
                                if ($val !== '') return $val;
                            }
                        }
                        return $default;
                    };

                    foreach ($rows as $row) {
                        $productName = $col($row, ['product_name', 'name', 'product']);
                        $variationName = $col($row, ['variation_name', 'package', 'package_name', 'variation', 'denomination']);
                        if (!$productName || !$variationName) continue;

                        $brandName = $col($row, ['brand', 'brand_name'], $productName);
                        $groupKey = Str::slug($brandName) . '___' . Str::slug($productName);
                        $rawCost = $col($row, ['wholesale_cost_usd', 'wholesale_cost', 'cost_price', 'cost'], 0);
                        $rawPrice = $col($row, ['retail_price_usd', 'retail_price', 'price', 'unit_price'], 0);

                        $grouped[$groupKey][] = [
                            'category' => $col($row, ['category', 'category_name'], 'Gaming Cards'),
                            'brand' => $brandName,
                            'product_name' => $productName,
                            'is_master_product' => $col($row, ['is_master_product', 'master_product', 'is_master'], 'No'),
                            'country_name' => $col($row, ['country_name', 'country', 'region'], 'Global'),
                            'country_code' => strtoupper($col($row, ['country_code', 'country_iso', 'iso_code'], 'GL')),
                            'variation_name' => $variationName,
                            'cost_price' => $parsePrice($rawCost),
                            'retail_price' => $parsePrice($rawPrice),
                            'stock' => (int) $col($row, ['stock', 'quantity', 'current_stock'], 999),
                            'sku' => $col($row, ['sku', 'code', 'product_sku', 'item_code']),
                            'provider_slug' => $col($row, ['provider_slug', 'provider'], 'foxreload'),
                            'external_item_id' => $col($row, ['external_item_id', 'item_id', 'foxreload_item_id']),
                            'external_category_id' => $col($row, ['external_category_id', 'category_id', 'foxreload_category_id']),
                            'status' => (int) $col($row, ['status', 'published', 'active'], 1),
                            'thumbnail' => $col($row, ['thumbnail', 'image'], 'product.png'),
                            'is_mock' => $col($row, ['is_mock', 'mock', 'sandbox']),
                        ];
                    }
                }
            } catch (\Throwable $e) {
                return [
                    'status' => false,
                    'message' => 'Unable to read Excel file: ' . $e->getMessage(),
                ];
            }
        }

        if (empty($grouped)) {
            return [
                'status' => false,
                'message' => 'No valid digital product variations found in the Excel file.',
            ];
        }

        $productsCreated = 0;
        $productsUpdated = 0;
        $variationsCount = 0;
        $mappingsCount = 0;

        $categoryCache = [];
        $brandCache = [];

        DB::beginTransaction();
        try {
            foreach ($grouped as $groupKey => $items) {
                $first = $items[0];
                $brandName = $first['brand'];
                $productName = $first['product_name'];
                $categoryName = $first['category'];

                // 1. Resolve or Create Category
                $categorySlug = Str::slug($categoryName);
                if (!isset($categoryCache[$categorySlug])) {
                    $categoryCache[$categorySlug] = Category::firstOrCreate(
                        ['slug' => $categorySlug],
                        [
                            'name' => $categoryName,
                            'icon' => 'default-icon.png',
                            'icon_storage_type' => 'public',
                            'parent_id' => 0,
                            'position' => 0,
                            'home_status' => 1,
                            'priority' => 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
                $category = $categoryCache[$categorySlug];

                // 2. Resolve or Create Brand
                $brandSlug = Str::slug($brandName);
                if (!isset($brandCache[$brandSlug])) {
                    $brandCache[$brandSlug] = Brand::firstOrCreate(
                        ['slug' => $brandSlug],
                        [
                            'name' => $brandName,
                            'image' => 'brand.png',
                            'image_storage_type' => 'public',
                            'image_alt_text' => $brandName,
                            'status' => 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
                $brand = $brandCache[$brandSlug];

                // 3. Determine Master Product vs Single Product
                $isMaster = false;
                foreach ($items as $it) {
                    $mFlag = strtolower($it['is_master_product']);
                    if (in_array($mFlag, ['yes', '1', 'true', 'y'])) {
                        $isMaster = true;
                        break;
                    }
                }
                if (!$isMaster) {
                    $uniqueCountries = array_unique(array_filter(array_column($items, 'country_name')));
                    if (count($uniqueCountries) > 1) {
                        $isMaster = true;
                    }
                }

                // 4. Build Variations & Extensions Structure
                $digitalExtensions = [];
                $legacyVariations = [];
                $itemsByVariantKey = [];
                $totalStock = 0;
                $lowestPrice = null;
                $lowestCost = null;

                // Sort items by retail price
                usort($items, fn($a, $b) => $a['retail_price'] <=> $b['retail_price']);

                foreach ($items as $it) {
                    $rPrice = $it['retail_price'] > 0 ? $it['retail_price'] : 0.01;
                    $cCost = $it['cost_price'];
                    $stock = $it['stock'] > 0 ? $it['stock'] : 999;
                    $totalStock += $stock;

                    if ($lowestPrice === null || $rPrice < $lowestPrice) {
                        $lowestPrice = $rPrice;
                    }
                    if ($lowestCost === null || $cCost < $lowestCost) {
                        $lowestCost = $cCost;
                    }

                    $vName = $it['variation_name'];
                    $sku = !empty($it['sku']) ? $it['sku'] : ('FR-' . strtoupper(substr(md5($it['external_item_id'] ?: $vName), 0, 8)));

                    $cName = !empty($it['country_name']) ? trim($it['country_name']) : 'Global';
                    $cCode = self::resolveCountryIsoCode($cName, $it['country_code'] ?? null);
                    $cleanCountry = preg_replace('/\s+/', '-', $cName);
                    $cleanOpt = preg_replace('/\s+/', '-', $vName);

                    if ($isMaster) {
                        $variantKey = "{$cleanCountry}-{$cleanOpt}";
                        $legacyCode = str_replace([' ', ','], '', "{$cName}-{$vName}");
                    } else {
                        $variantKey = "Package-{$cleanOpt}";
                        $legacyCode = str_replace([' ', ','], '', "Package-{$vName}");
                    }

                    // FLAW-3 FIX: If variant_key already exists, disambiguate it so no variations or mappings are overwritten
                    if (isset($itemsByVariantKey[$variantKey])) {
                        $baseVName = $vName;
                        $counter = 2;
                        while (isset($itemsByVariantKey[$variantKey])) {
                            $vName = "{$baseVName} #{$counter}";
                            $cleanOpt = preg_replace('/\s+/', '-', $vName);

                            if ($isMaster) {
                                $variantKey = "{$cleanCountry}-{$cleanOpt}";
                                $legacyCode = str_replace([' ', ','], '', "{$cName}-{$vName}");
                            } else {
                                $variantKey = "Package-{$cleanOpt}";
                                $legacyCode = str_replace([' ', ','], '', "Package-{$vName}");
                            }
                            $counter++;
                        }
                    }

                    if ($isMaster) {
                        $digitalExtensions[$cName][] = $vName;
                    } else {
                        $digitalExtensions['Package'][] = $vName;
                    }

                    $legacyVariations[] = [
                        'type' => $legacyCode,
                        'price' => $rPrice,
                        'sku' => $sku,
                        'qty' => $stock,
                        'country' => $cName,
                        'country_code' => $cCode,
                    ];

                    $itemsByVariantKey[$variantKey] = [
                        'sku' => $sku,
                        'price' => $rPrice,
                        'cost_price' => $cCost,
                        'country_name' => $cName,
                        'country_code' => $cCode,
                        'provider_slug' => $it['provider_slug'] ?: 'foxreload',
                        'external_item_id' => $it['external_item_id'],
                        'external_category_id' => $it['external_category_id'],
                        'is_mock' => $it['is_mock'],
                    ];
                }

                // De-duplicate variation names in extensions
                foreach ($digitalExtensions as $grp => $opts) {
                    $digitalExtensions[$grp] = array_values(array_unique($opts));
                }
                $digitalFileTypes = array_keys($digitalExtensions);

                // Country attributes for master vs single product
                $targetCountry = $isMaster ? null : ($first['country_name'] ?: 'Global');
                $targetCountryCode = $isMaster ? null : ($first['country_code'] ?: 'GL');

                // 5. Upsert Product
                $product = Product::where('brand_id', $brand->id)
                    ->where('name', $productName)
                    ->first();

                $categoryIds = json_encode([['id' => (string)$category->id, 'position' => 1]]);
                $detailsHtml = "<p><strong>{$productName}</strong> — Select your " . ($isMaster ? "country and package" : "package") . " below for instant digital code delivery directly upon checkout.</p>";
                $masterSku = 'FR-' . strtoupper(substr(md5($productName), 0, 8));

                if ($product) {
                    $product->update([
                        'category_id' => $category->id,
                        'category_ids' => $categoryIds,
                        'country' => $targetCountry,
                        'country_code' => $targetCountryCode,
                        'unit_price' => $lowestPrice ?? 1.00,
                        'purchase_price' => $lowestCost ?? 0.80,
                        'current_stock' => $totalStock,
                        'variation' => json_encode($legacyVariations),
                        'digital_product_file_types' => $digitalFileTypes,
                        'digital_product_extensions' => $digitalExtensions,
                        'attributes' => '[]',
                        'choice_options' => '[]',
                        'tax' => $product->tax ?? 0,
                        'tax_type' => $product->tax_type ?? 'percent',
                        'tax_model' => $product->tax_model ?? 'include',
                        'discount' => $product->discount ?? 0,
                        'discount_type' => $product->discount_type ?? 'flat',
                        'status' => $first['status'],
                        'published' => $first['status'],
                        'product_type' => 'digital',
                        'digital_product_type' => 'ready_product',
                        'updated_at' => now(),
                    ]);
                    $productsUpdated++;
                } else {
                    $randomSuffix = Str::random(5);
                    $productSlug = Str::slug($productName) . '-' . $randomSuffix;

                    $product = Product::create([
                        'added_by' => $addedBy,
                        'user_id' => $userId ?: 1,
                        'name' => $productName,
                        'slug' => $productSlug,
                        'country' => $targetCountry,
                        'country_code' => $targetCountryCode,
                        'product_type' => 'digital',
                        'digital_product_type' => 'ready_product',
                        'category_id' => $category->id,
                        'category_ids' => $categoryIds,
                        'brand_id' => $brand->id,
                        'unit' => 'pc',
                        'min_qty' => 1,
                        'minimum_order_qty' => 1,
                        'refundable' => 0,
                        'unit_price' => $lowestPrice ?? 1.00,
                        'purchase_price' => $lowestCost ?? 0.80,
                        'tax' => 0,
                        'tax_type' => 'percent',
                        'tax_model' => 'include',
                        'discount' => 0,
                        'discount_type' => 'flat',
                        'current_stock' => $totalStock,
                        'details' => $detailsHtml,
                        'status' => $first['status'],
                        'published' => $first['status'],
                        'request_status' => 1,
                        'featured_status' => 1,
                        'featured' => 1,
                        'free_shipping' => 1,
                        'multiply_qty' => 0,
                        'shipping_cost' => 0,
                        'code' => $masterSku,
                        'attributes' => '[]',
                        'choice_options' => '[]',
                        'colors' => '[]',
                        'color_image' => '[]',
                        'variation' => json_encode($legacyVariations),
                        'digital_product_file_types' => $digitalFileTypes,
                        'digital_product_extensions' => $digitalExtensions,
                        'thumbnail' => $first['thumbnail'] ?: 'product.png',
                        'thumbnail_storage_type' => 'public',
                        'images' => json_encode([$first['thumbnail'] ?: 'product.png']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $productsCreated++;
                }

                // 6. Sync digital_product_variations
                $now = now();
                $variationRows = [];
                foreach ($itemsByVariantKey as $vKey => $vInfo) {
                    $variationRows[] = [
                        'product_id' => $product->id,
                        'variant_key' => $vKey,
                        'country_name' => $vInfo['country_name'] ?? null,
                        'country_code' => $vInfo['country_code'] ?? null,
                        'sku' => $vInfo['sku'],
                        'price' => $vInfo['price'],
                        'file' => '',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                DigitalProductVariation::where('product_id', $product->id)->delete();
                if (!empty($variationRows)) {
                    foreach (array_chunk($variationRows, 500) as $chunk) {
                        DigitalProductVariation::insert($chunk);
                    }
                    $variationsCount += count($variationRows);
                }

                // 7. Sync product_provider_mappings
                $validExtIds = [];
                $uniqueMappings = [];
                foreach ($itemsByVariantKey as $vKey => $vInfo) {
                    if (!empty($vInfo['external_item_id'])) {
                        $extItemId = (string)$vInfo['external_item_id'];
                        $validExtIds[] = $extItemId;

                        $providerSlug = $vInfo['provider_slug'] ?: 'foxreload';
                        $mapKey = $providerSlug . '___' . $extItemId;

                        $isMockConfig = (bool) config("suppliers.suppliers.{$providerSlug}.is_mock", env('FOXRELOAD_IS_MOCK', true));
                        $isMock = !is_null($vInfo['is_mock'] ?? null) && $vInfo['is_mock'] !== ''
                            ? (in_array(strtolower((string) $vInfo['is_mock']), ['1', 'true', 'yes'], true) ? 1 : 0)
                            : ($isMockConfig ? 1 : 0);

                        $uniqueMappings[$mapKey] = [
                            'product_id' => $product->id,
                            'provider_slug' => $providerSlug,
                            'external_item_id' => $extItemId,
                            'variant_key' => $vKey,
                            'country_name' => $vInfo['country_name'] ?? null,
                            'country_code' => $vInfo['country_code'] ?? null,
                            'external_category_id' => $vInfo['external_category_id'] !== null ? (string)$vInfo['external_category_id'] : null,
                            'cost_price' => $vInfo['cost_price'],
                            'currency' => 'usd',
                            'is_service' => 0,
                            'is_mock' => $isMock,
                            'is_active' => 1,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                if (!empty($uniqueMappings)) {
                    foreach (array_chunk(array_values($uniqueMappings), 500) as $chunk) {
                        ProductProviderMapping::upsert(
                            $chunk,
                            ['product_id', 'provider_slug', 'external_item_id'],
                            ['variant_key', 'country_name', 'country_code', 'external_category_id', 'cost_price', 'currency', 'is_service', 'is_mock', 'is_active', 'updated_at']
                        );
                    }
                    $mappingsCount += count($uniqueMappings);
                }

                // Delete obsolete provider mappings for this product
                if (!empty($validExtIds)) {
                    ProductProviderMapping::where('product_id', $product->id)
                        ->where('provider_slug', 'foxreload')
                        ->whereNotIn('external_item_id', $validExtIds)
                        ->delete();
                }
            }

            DB::commit();

            return [
                'status' => true,
                'is_digital' => true,
                'message' => "Successfully imported {$productsCreated} new products, updated {$productsUpdated} existing products, synced {$variationsCount} variations and {$mappingsCount} provider fulfillment mappings!",
                'products_created' => $productsCreated,
                'products_updated' => $productsUpdated,
                'variations_count' => $variationsCount,
                'mappings_count' => $mappingsCount,
            ];

        } catch (\Throwable $e) {
            DB::rollBack();
            return [
                'status' => false,
                'message' => 'Failed to import digital products: ' . $e->getMessage() . ' on line ' . $e->getLine(),
            ];
        }
    }

    /**
     * Export all store digital products back into the master Excel format.
     */
    public function exportDigitalProducts(): BinaryFileResponse
    {
        $products = Product::where('product_type', 'digital')
            ->with(['brand', 'category'])
            ->orderBy('category_id')
            ->orderBy('name')
            ->get();

        $spreadsheet = new Spreadsheet();
        $ws = $spreadsheet->getActiveSheet();
        $ws->setTitle('Products Catalog');
        $ws->setShowGridLines(true);

        $headers = [
            'A' => ['Category', 18],
            'B' => ['Brand', 20],
            'C' => ['Product Name', 28],
            'D' => ['Is Master Product', 18],
            'E' => ['Country Name', 18],
            'F' => ['Country Code', 14],
            'G' => ['Variation Name', 25],
            'H' => ['Wholesale Cost (USD)', 20],
            'I' => ['Retail Price (USD)', 20],
            'J' => ['Stock', 12],
            'K' => ['SKU', 18],
            'L' => ['Provider Slug', 15],
            'M' => ['External Item ID', 38],
            'N' => ['External Category ID', 38],
            'O' => ['Status', 10],
            'P' => ['Thumbnail', 16],
        ];

        // Format headers
        foreach ($headers as $col => [$title, $width]) {
            $ws->setCellValue("{$col}1", $title);
            $ws->getColumnDimension($col)->setWidth($width);
        }
        $ws->getRowDimension(1)->setRowHeight(28);

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'name' => 'Segoe UI', 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E293B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ];
        $ws->getStyle('A1:P1')->applyFromArray($headerStyle);

        $rowIdx = 2;
        foreach ($products as $prod) {
            $catName = $prod->category ? $prod->category->name : 'Gaming Cards';
            $brandName = $prod->brand ? $prod->brand->name : $prod->name;

            $extensions = is_string($prod->digital_product_extensions)
                ? (json_decode($prod->digital_product_extensions, true) ?? [])
                : ($prod->digital_product_extensions ?? []);

            $isMaster = (count($extensions) > 1 || (count($extensions) === 1 && !in_array(strtolower(array_key_first($extensions) ?: ''), ['package', 'default', 'options', 'variation'])));

            $variations = DigitalProductVariation::where('product_id', $prod->id)->get();
            $mappings = ProductProviderMapping::where('product_id', $prod->id)->get()->keyBy('variant_key');

            // FLAW-5 FIX: Parse variation-level stock from product's variation JSON
            $prodVariationArray = is_string($prod->variation)
                ? (json_decode($prod->variation, true) ?? [])
                : (is_array($prod->variation) ? $prod->variation : []);

            $stockBySku = [];
            $stockByType = [];
            foreach ($prodVariationArray as $pv) {
                if (!empty($pv['sku'])) {
                    $stockBySku[$pv['sku']] = (int) ($pv['qty'] ?? 0);
                }
                if (!empty($pv['type'])) {
                    $stockByType[str_replace([' ', '-', ','], '', strtolower($pv['type']))] = (int) ($pv['qty'] ?? 0);
                }
            }

            if ($variations->isEmpty()) {
                // Single variation fallback
                $ws->setCellValue("A{$rowIdx}", $catName);
                $ws->setCellValue("B{$rowIdx}", $brandName);
                $ws->setCellValue("C{$rowIdx}", $prod->name);
                $ws->setCellValue("D{$rowIdx}", $isMaster ? 'Yes' : 'No');
                $ws->setCellValue("E{$rowIdx}", $prod->country ?: 'Global');
                $ws->setCellValue("F{$rowIdx}", self::resolveCountryIsoCode($prod->country, $prod->country_code));
                $ws->setCellValue("G{$rowIdx}", 'Standard');
                $ws->setCellValue("H{$rowIdx}", $prod->purchase_price);
                $ws->setCellValue("I{$rowIdx}", $prod->unit_price);
                $ws->setCellValue("J{$rowIdx}", $prod->current_stock);
                $ws->setCellValue("K{$rowIdx}", $prod->code);
                $ws->setCellValue("L{$rowIdx}", 'foxreload');
                $ws->setCellValue("M{$rowIdx}", '');
                $ws->setCellValue("N{$rowIdx}", '');
                $ws->setCellValue("O{$rowIdx}", $prod->status);
                $ws->setCellValue("P{$rowIdx}", $prod->thumbnail ?: 'product.png');
                $rowIdx++;
            } else {
                foreach ($variations as $var) {
                    $vKey = $var->variant_key;
                    $map = $mappings->get($vKey);

                    // Extract Country & Variation Name from variant_key
                    $cName = $prod->country ?: 'Global';
                    $cCode = self::resolveCountryIsoCode($prod->country, $prod->country_code);
                    $vName = $vKey;

                    if ($isMaster && str_contains($vKey, '-')) {
                        // e.g. "Saudi-Arabia-50-SAR"
                        foreach ($extensions as $countryKey => $packages) {
                            $cleanCountry = preg_replace('/\s+/', '-', $countryKey);
                            if (str_starts_with($vKey, $cleanCountry . '-')) {
                                $cName = $countryKey;
                                $vClean = substr($vKey, strlen($cleanCountry) + 1);
                                foreach ($packages as $pkg) {
                                    if (preg_replace('/\s+/', '-', $pkg) === $vClean) {
                                        $vName = $pkg;
                                        break;
                                    }
                                }
                                break;
                            }
                        }
                    } elseif (str_starts_with($vKey, 'Package-')) {
                        $vClean = substr($vKey, 8);
                        if (isset($extensions['Package'])) {
                            foreach ($extensions['Package'] as $pkg) {
                                if (preg_replace('/\s+/', '-', $pkg) === $vClean) {
                                    $vName = $pkg;
                                    break;
                                }
                            }
                        }
                    }

                    // FLAW-4 RESOLUTION: Retrieve persisted country code & name from mapping or variation model, falling back to ISO resolver
                    if (!empty($map?->country_name)) {
                        $cName = $map->country_name;
                    } elseif (!empty($var->country_name)) {
                        $cName = $var->country_name;
                    }

                    if (!empty($map?->country_code) && $map->country_code !== 'GL') {
                        $cCode = strtoupper(trim($map->country_code));
                    } elseif (!empty($var->country_code) && $var->country_code !== 'GL') {
                        $cCode = strtoupper(trim($var->country_code));
                    } else {
                        $cCode = self::resolveCountryIsoCode($cName, $prod->country_code);
                    }

                    // FLAW-5 RESOLUTION: Retrieve individual variation stock
                    $varStock = null;
                    if (!empty($var->sku) && isset($stockBySku[$var->sku])) {
                        $varStock = $stockBySku[$var->sku];
                    } else {
                        $cleanKey = str_replace([' ', '-', ','], '', strtolower($var->variant_key));
                        if (isset($stockByType[$cleanKey])) {
                            $varStock = $stockByType[$cleanKey];
                        }
                    }

                    if ($varStock === null) {
                        $varCount = max(1, $variations->count());
                        $varStock = (int) round($prod->current_stock / $varCount);
                    }

                    $ws->setCellValue("A{$rowIdx}", $catName);
                    $ws->setCellValue("B{$rowIdx}", $brandName);
                    $ws->setCellValue("C{$rowIdx}", $prod->name);
                    $ws->setCellValue("D{$rowIdx}", $isMaster ? 'Yes' : 'No');
                    $ws->setCellValue("E{$rowIdx}", $cName);
                    $ws->setCellValue("F{$rowIdx}", $cCode);
                    $ws->setCellValue("G{$rowIdx}", $vName);
                    $ws->setCellValue("H{$rowIdx}", $map ? $map->cost_price : $prod->purchase_price);
                    $ws->setCellValue("I{$rowIdx}", $var->price);
                    $ws->setCellValue("J{$rowIdx}", $varStock);
                    $ws->setCellValue("K{$rowIdx}", $var->sku ?: $prod->code);
                    $ws->setCellValue("L{$rowIdx}", $map ? $map->provider_slug : 'foxreload');
                    $ws->setCellValue("M{$rowIdx}", $map ? $map->external_item_id : '');
                    $ws->setCellValue("N{$rowIdx}", $map ? $map->external_category_id : '');
                    $ws->setCellValue("O{$rowIdx}", $prod->status);
                    $ws->setCellValue("P{$rowIdx}", $prod->thumbnail ?: 'product.png');

                    $ws->getStyle("H{$rowIdx}:I{$rowIdx}")->getNumberFormat()->setFormatCode('$#,##0.00');
                    $rowIdx++;
                }
            }
        }

        // Auto filter & freeze panes
        $ws->freezePane('C2');
        $ws->setAutoFilter("A1:P" . ($rowIdx - 1));

        $tempFileName = 'digital_products_catalog_' . date('Y_m_d_His') . '.xlsx';
        $tempPath = storage_path('app/' . $tempFileName);

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        return response()->download($tempPath, $tempFileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Resolve a 2-letter ISO country code from country name or raw string.
     */
    public static function resolveCountryIsoCode(?string $countryName, ?string $rawCode = null): string
    {
        if (!empty($rawCode)) {
            $code = strtoupper(trim($rawCode));
            if ($code !== 'GL' && strlen($code) === 2 && ctype_alpha($code)) {
                return $code;
            }
        }

        if (empty($countryName)) {
            return 'GL';
        }

        $c = strtolower(trim($countryName));
        if (in_array($c, ['global', 'worldwide', 'world', 'all'])) {
            return 'GL';
        }

        // Quick common aliases
        $aliases = [
            'usa' => 'US', 'united states' => 'US', 'united states of america' => 'US',
            'uk' => 'GB', 'united kingdom' => 'GB', 'great britain' => 'GB',
            'uae' => 'AE', 'united arab emirates' => 'AE',
            'ksa' => 'SA', 'saudi arabia' => 'SA', 'saudi' => 'SA',
            'turkey' => 'TR', 'turkiye' => 'TR',
            'germany' => 'DE', 'deutschland' => 'DE',
            'france' => 'FR', 'brazil' => 'BR', 'canada' => 'CA',
            'egypt' => 'EG', 'australia' => 'AU', 'india' => 'IN',
            'kuwait' => 'KW', 'qatar' => 'QA', 'bahrain' => 'BH', 'oman' => 'OM',
            'jordan' => 'JO', 'iraq' => 'IQ', 'lebanon' => 'LB',
        ];

        if (isset($aliases[$c])) {
            return $aliases[$c];
        }

        foreach (GlobalConstant::COUNTRIES as $item) {
            if (strcasecmp($item['name'], $countryName) === 0) {
                return strtoupper($item['code']);
            }
        }

        return 'GL';
    }
}
