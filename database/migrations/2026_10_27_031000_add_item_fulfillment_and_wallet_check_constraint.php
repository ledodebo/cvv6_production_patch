<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add item-level fulfillment fields to order_details
        Schema::table('order_details', function (Blueprint $table) {
            if (!Schema::hasColumn('order_details', 'foxreload_order_id')) {
                $table->string('foxreload_order_id', 128)->nullable()->index()->after('digital_file_after_sell');
            }
            if (!Schema::hasColumn('order_details', 'client_reference_id')) {
                $table->string('client_reference_id', 128)->nullable()->index()->after('foxreload_order_id');
            }
            if (!Schema::hasColumn('order_details', 'fulfillment_status')) {
                $table->string('fulfillment_status', 32)->nullable()->default('pending')->index()->after('client_reference_id');
            }
            if (!Schema::hasColumn('order_details', 'fulfillment_error')) {
                $table->text('fulfillment_error')->nullable()->after('fulfillment_status');
            }
        });

        // 2. Sanitize negative wallet balances before enforcing check constraint
        try {
            DB::statement('UPDATE users SET wallet_balance = 0 WHERE wallet_balance < 0');
        } catch (\Throwable $e) {
            // Ignore if table or column doesn't match
        }

        // 3. Add CHECK (wallet_balance >= 0) constraint to users table if supported
        try {
            DB::statement('ALTER TABLE users ADD CONSTRAINT chk_users_wallet_balance_non_negative CHECK (wallet_balance >= 0)');
        } catch (\Throwable $e) {
            // Constraints might already exist or DB engine might silently ignore/reject
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            if (Schema::hasColumn('order_details', 'fulfillment_error')) {
                $table->dropColumn('fulfillment_error');
            }
            if (Schema::hasColumn('order_details', 'fulfillment_status')) {
                $table->dropColumn('fulfillment_status');
            }
            if (Schema::hasColumn('order_details', 'client_reference_id')) {
                $table->dropColumn('client_reference_id');
            }
            if (Schema::hasColumn('order_details', 'foxreload_order_id')) {
                $table->dropColumn('foxreload_order_id');
            }
        });

        try {
            DB::statement('ALTER TABLE users DROP CONSTRAINT chk_users_wallet_balance_non_negative');
        } catch (\Throwable $e) {
            //
        }
    }
};
