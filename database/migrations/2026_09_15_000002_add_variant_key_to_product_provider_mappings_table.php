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
        Schema::table('product_provider_mappings', function (Blueprint $table) {
            if (!Schema::hasColumn('product_provider_mappings', 'variant_key')) {
                $table->string('variant_key', 100)->nullable()->after('product_id')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_provider_mappings', function (Blueprint $table) {
            if (Schema::hasColumn('product_provider_mappings', 'variant_key')) {
                $table->dropColumn('variant_key');
            }
        });
    }
};
