# CVV6 Production Patch: Digital Catalog & Multi-Country Consolidation

This repository contains all modified and newly created files for:
1. **Excel Digital Catalog Bulk Import**: High-performance OpenSpout streaming import supporting multi-country master products, variations, wholesale costs, and supplier item ID mapping.
2. **Multi-Country Master Products**: Consolidates 25 regional master brands into single product pages with dynamic country selection dropdowns.
3. **Single-Region Plan Separation**: Automatically splits distinct subscription plan tiers (e.g., Suno AI Premier vs Renewal Premier) into separate product cards when there is no country variation.
4. **Database Migrations**:
   - `products.variation` column expanded to `LONGTEXT` (prevents 500 error crashes on large catalogs).
   - `product_provider_mappings` table for instant digital delivery mapping.
   - `country_name` and `country_code` added to `digital_product_variations`.

## File Tree
- `database/migrations/2026_09_15_000001_create_product_provider_mappings_table.php`
- `database/migrations/2026_09_15_000002_add_variant_key_to_product_provider_mappings_table.php`
- `database/migrations/2026_09_15_000003_add_country_fields_to_digital_product_tables.php`
- `database/migrations/2026_09_15_000004_change_variation_column_to_longtext_in_products_table.php`
- `app/Services/DigitalProductBulkImportService.php`
- `app/Services/ProductService.php`
- `app/Utils/product.php`
- `app/Utils/ProductManager.php`
- `app/Models/DigitalProductVariation.php`
- `app/Models/Product.php`
- `app/Models/ProductProviderMapping.php`
- `app/Http/Controllers/Web/ProductDetailsController.php`
- `app/Http/Controllers/Admin/Product/ProductController.php`
- `routes/admin/routes.php`
- `resources/views/admin-views/product/bulk-import.blade.php`
- `public/assets/digital_product_bulk_format.xlsx`
- `public/assets/front-end/js/product-details.js`
- `resources/themes/default/web-views/products/details.blade.php`
- `resources/themes/default/web-views/products/_product-details-sticky.blade.php`
- `resources/themes/default/web-views/partials/_quick-view-data.blade.php`
- `catalog_sorting_instructions.json`
- `dump_foxreload_direct_to_excel.py`

## Live Deployment Steps
1. Copy the files in this repository over your live Laravel root directory (paths match 1:1).
2. Run database migrations:
   ```bash
   php artisan migrate --force
   ```
3. Clear Laravel caches:
   ```bash
   php artisan optimize:clear
   ```
