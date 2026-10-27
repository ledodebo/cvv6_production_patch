#!/bin/bash
# ==============================================================================
# CVV6 Production Deployment Commands
# Digital Checkout & Instant Digital Card Delivery System
# ==============================================================================
# Instructions:
# 1. Pull or copy all files from this repository into your Laravel root:
#    cp -r cvv6_production_patch/* /var/www/html/ (or your project path)
# 2. Make this script executable and run it:
#    chmod +x DEPLOY_COMMANDS.sh
#    ./DEPLOY_COMMANDS.sh
# ==============================================================================

set -e

echo "=== [1/6] Running Database Migrations ==="
php artisan migrate --force

echo "=== [2/6] Clearing Application & Route Caches ==="
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan config:clear
php artisan cache:clear

echo "=== [3/6] Re-caching Routes, Views, and Config for Production ==="
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "=== [4/6] Restarting Queue Workers (for FulfillDigitalOrderJob) ==="
php artisan queue:restart

echo "=== [5/6] Checking FoxReload Supplier Connectivity ==="
php artisan supplier:check-balance || echo "Warning: Supplier balance check returned non-zero. Check your FOXRELOAD_TOKEN in .env."

echo "=== [6/6] Verifying Digital Checkout Routes ==="
php artisan route:list --name=account-order-digital-codes

echo ""
echo "=========================================================="
echo " All Digital Checkout & Delivery Updates Successfully Applied!"
echo "=========================================================="
