# CVV6 Production Patch: Digital Catalog & Purchase Fulfillment System

This repository contains all modified and newly created files for:
1. **Digital Card Purchase & Automated Fulfillment Engine**:
   - Secure customer checkout via Wallet balance and Direct Payment (PayPal).
   - Automated procurement and delivery via FoxReload REST API (`items[].externalData`).
   - Atomic wallet race-condition & overdraft protection (`lockForUpdate` row-level locking).
   - Card code AES-256 encryption at rest (`digital_card_codes`).
   - Order financial ledger tracking customer selling price, cost price, gateway fees, and net profit (`order_financials`).
   - Automated customer wallet refund upon supplier out-of-stock or fulfillment errors.
   - Admin order details badge & manual retry button for failed fulfillments.
   - Zero-cost sandbox test gateway for risk-free local/staging end-to-end testing.
2. **Excel Digital Catalog Bulk Import**:
   - High-performance OpenSpout streaming import supporting multi-country master products, variations, wholesale costs, and supplier item ID mapping.
3. **Multi-Country Master Products**:
   - Consolidates regional master brands into single product pages with dynamic country selection dropdowns.
4. **Single-Region Plan Separation**:
   - Automatically splits distinct subscription plan tiers into separate product cards when there is no country variation.

---

## Complete File Tree

### Database Migrations
- `database/migrations/2026_09_15_000001_create_product_provider_mappings_table.php`
- `database/migrations/2026_09_15_000002_add_variant_key_to_product_provider_mappings_table.php`
- `database/migrations/2026_09_15_000003_add_country_fields_to_digital_product_tables.php`
- `database/migrations/2026_09_15_000004_change_variation_column_to_longtext_in_products_table.php`
- `database/migrations/2026_10_26_101606_create_digital_card_codes_table.php`
- `database/migrations/2026_10_26_101636_create_order_financials_table.php`
- `database/migrations/2026_10_26_101705_add_fulfillment_fields_to_orders_table.php`
- `database/migrations/2026_10_26_105300_add_sandbox_gateway_to_addon_settings.php`

### Models & Business Logic
- `app/Models/Product.php`
- `app/Models/DigitalProductVariation.php`
- `app/Models/ProductProviderMapping.php`
- `app/Models/Order.php`
- `app/Models/OrderDetail.php`
- `app/Models/DigitalCardCode.php`
- `app/Models/OrderFinancial.php`
- `app/Utils/CustomerManager.php`
- `app/Utils/OrderManager.php`
- `app/Utils/Helpers.php`
- `app/Utils/module-helper.php`
- `app/Utils/product.php`
- `app/Utils/ProductManager.php`

### Services, Supplier & Background Jobs
- `app/Services/DigitalOrderService.php`
- `app/Services/DigitalProductBulkImportService.php`
- `app/Services/ProductService.php`
- `app/Services/Supplier/FoxReload/foxreload_client.py`
- `app/Services/Supplier/FoxReload/FoxReloadClient.php`
- `app/Services/Supplier/SupplierCatalogSyncService.php`
- `app/Jobs/FulfillDigitalOrderJob.php`
- `app/Events/DigitalOrderDeliveredEvent.php`

### Payment Gateways & Controllers
- `config/digital_purchase.php`
- `config/suppliers.php`
- `app/Http/Controllers/Payment_Methods/SandboxPaymentController.php`
- `app/Traits/Payment.php`
- `app/Http/Controllers/Web/WebController.php`
- `app/Http/Controllers/Web/ProductDetailsController.php`
- `app/Http/Controllers/Web/DigitalProductDownloadController.php`
- `app/Http/Controllers/Admin/Product/ProductController.php`
- `app/Http/Controllers/Admin/Order/OrderController.php`

### Console Commands
- `app/Console/Commands/CheckSupplierBalance.php` (`php artisan supplier:check-balance`)
- `app/Console/Commands/ReconcileOrders.php` (`php artisan orders:reconcile`)
- `app/Console/Commands/SyncFoxReloadProductsCommand.php`

### Routes & Views
- `routes/web/routes.php`
- `routes/admin/routes.php`
- `resources/views/admin-views/order/order-details.blade.php`
- `resources/views/admin-views/product/bulk-import.blade.php`
- `resources/themes/default/web-views/products/details.blade.php`
- `resources/themes/default/web-views/products/_product-details-sticky.blade.php`
- `resources/themes/default/web-views/partials/_quick-view-data.blade.php`
- `public/assets/front-end/js/product-details.js`
- `public/assets/digital_product_bulk_format.xlsx`

---

## Live Deployment (Quick Start)

### Option 1: Automated Script
```bash
chmod +x deploy.sh
./deploy.sh
```

### Option 2: Manual Commands
1. Copy the files in this repository over your live Laravel root directory (paths match 1:1).
2. Run database migrations:
   ```bash
   php artisan migrate --force
   ```
3. Clear and rebuild Laravel caches:
   ```bash
   php artisan optimize:clear
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
4. Restart background queue workers:
   ```bash
   php artisan queue:restart
   ```
5. Check supplier balance connectivity:
   ```bash
   php artisan supplier:check-balance
   ```

For detailed deployment and environment configuration instructions, see [DEPLOYMENT_COMMANDS.md](DEPLOYMENT_COMMANDS.md).
