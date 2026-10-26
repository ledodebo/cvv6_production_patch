# Production Deployment Commands & Operations Guide

This guide details all shell commands, environment configurations, and operational procedures needed to deploy and run the **CVV6 Digital Card Purchase & Fulfillment System** on your live production server.

---

## 1. Automated 1-Step Deployment (Bash)

If you are deploying via SSH on a Linux production server:

```bash
chmod +x deploy.sh
./deploy.sh
```

---

## 2. Manual Step-by-Step Shell Commands

If you prefer to execute the commands manually in your terminal, run them in the following order:

### Step 1: Run Database Migrations
Creates the `digital_card_codes` table, `order_financials` table, `orders` fulfillment fields, and registers the gateway settings:
```bash
php artisan migrate --force
```

### Step 2: Clear Application and Cache
Flushes stale route definitions, compiled views, and cached configuration:
```bash
php artisan optimize:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

### Step 3: Rebuild Production Optimization Caches (Recommended for Production)
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Step 4: Restart Queue Workers
If you use Laravel Queues (Supervisor or background workers):
```bash
php artisan queue:restart
```

---

## 3. Post-Deployment Verification Commands

### Test Supplier Connectivity & Check Balance
Queries the FoxReload API (`GET /api/access/me/balances/`) and displays available USD and RUB balances:
```bash
php artisan supplier:check-balance
```

### Order Reconciliation Check
Compares local order records against supplier logs for the last 24 hours to ensure 0 lost cards:
```bash
php artisan orders:reconcile
```

---

## 4. Production Environment (`.env`) Configuration

Add or verify the following keys in your live `.env` file:

```ini
# ==========================================
# FoxReload Digital Goods Supplier
# ==========================================
FOXRELOAD_BASE_URL=https://public-api.foxreload.com/api
FOXRELOAD_TOKEN=your_live_foxreload_api_token_here
FOXRELOAD_IS_MOCK=false
FOXRELOAD_MIN_BALANCE_USD=50

# ==========================================
# Payment Gateways (Mock vs Live)
# ==========================================
# Set to 'false' in production to disable the $0 sandbox testing gateway
PAYMENT_GATEWAY_SANDBOX=false
```

---

## 5. Deactivating Sandbox & Activating Live Gateways

### Disable Sandbox Test Gateway in Database
Run this one-liner to ensure the developer sandbox gateway cannot be selected by customers:
```bash
php -r "require 'vendor/autoload.php'; \$app = require_once 'bootstrap/app.php'; \$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); Illuminate\Support\Facades\DB::table('addon_settings')->where('key_name', 'sandbox')->update(['is_active' => 0]);"
```

### Configure Live PayPal
1. Log in to your 6valley Admin Panel (`/admin/auth/login`).
2. Go to **System Setup** &rarr; **3rd Party** &rarr; **Payment Methods** &rarr; **PayPal**.
3. Input:
   - **Client ID**: Your Live PayPal Client ID from [developer.paypal.com](https://developer.paypal.com/)
   - **Secret**: Your Live PayPal Secret
   - **Environment**: `Live`
   - **Status**: `Active`
4. Click **Save Changes**.

---

## 6. Daily / Hourly Scheduled Tasks (Cron)

Ensure your production crontab runs the Laravel scheduler:
```cron
* * * * * cd /path/to/your/laravel/root && php artisan schedule:run >> /dev/null 2>&1
```

This automates:
- Low supplier balance alerts (`php artisan supplier:check-balance`)
- Automatic order reconciliation (`php artisan orders:reconcile`)
