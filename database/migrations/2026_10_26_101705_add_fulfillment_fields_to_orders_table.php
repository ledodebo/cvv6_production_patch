<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'foxreload_order_id')) {
                $table->string('foxreload_order_id', 128)->nullable()->index()->after('transaction_ref');
            }
            if (!Schema::hasColumn('orders', 'fulfillment_status')) {
                $table->string('fulfillment_status', 32)->nullable()->default('pending')->index()->after('order_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'foxreload_order_id')) {
                $table->dropColumn('foxreload_order_id');
            }
            if (Schema::hasColumn('orders', 'fulfillment_status')) {
                $table->dropColumn('fulfillment_status');
            }
        });
    }
};
