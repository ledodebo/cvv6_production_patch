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
        if (!Schema::hasTable('product_provider_mappings')) {
            Schema::create('product_provider_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->string('provider_slug', 50)->default('foxreload')->index();
            $table->string('external_item_id', 150)->index();
            $table->string('external_category_id', 150)->nullable()->index();
            $table->decimal('cost_price', 24, 4)->default(0);
            $table->string('currency', 10)->default('usd');
            $table->boolean('is_service')->default(false); // direct top-up vs code voucher
            $table->json('required_fields')->nullable(); // e.g. player_id
            $table->boolean('is_mock')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->unique(['product_id', 'provider_slug', 'external_item_id'], 'prod_prov_ext_unique');
        });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_provider_mappings');
    }
};
