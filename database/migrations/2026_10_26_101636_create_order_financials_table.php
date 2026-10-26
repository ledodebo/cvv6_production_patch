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
        Schema::create('order_financials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->unique()->index();
            $table->decimal('selling_price', 14, 4)->default(0);
            $table->decimal('cost_price', 14, 4)->default(0);
            $table->decimal('gateway_fee', 14, 4)->default(0);
            $table->decimal('net_profit', 14, 4)->default(0);
            $table->string('currency', 10)->default('USD');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_financials');
    }
};
