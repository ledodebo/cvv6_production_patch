#!/bin/bash

# ==============================================================================
# CVV6 Production Patch - Deployment & Activation Script
# ==============================================================================
# Run this script on your production server from your Laravel project root:
#   chmod +x deploy.sh
#   ./deploy.sh
# ==============================================================================

set -e

echo ""
echo "=========================================================="
echo " Starting CVV6 Digital Purchase & Catalog Deployment"
echo "=========================================================="
echo ""

# 1. Run Pending Database Migrations
echo "[Step 1/5] Running database migrations..."
php artisan migrate --force

# 2. Clear All Laravel Caches
echo ""
echo "[Step 2/5] Clearing application and route caches..."
php artisan optimize:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# 3. Optional: Re-cache Configuration for Production Speed
echo ""
echo "[Step 3/5] Rebuilding production configuration cache..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 4. Restart Background Queue Workers
echo ""
echo "[Step 4/5] Restarting background queue workers..."
php artisan queue:restart

# 5. Check Supplier Balance & Connectivity
echo ""
echo "[Step 5/5] Verifying FoxReload supplier balance and API connection..."
php artisan supplier:check-balance || echo "Warning: Supplier balance check failed. Please verify FOXRELOAD_TOKEN in your .env file."

echo ""
echo "=========================================================="
echo " Deployment Completed Successfully!"
echo "=========================================================="
echo ""
