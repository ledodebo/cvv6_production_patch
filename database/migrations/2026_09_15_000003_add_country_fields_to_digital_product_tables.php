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
        // 1. Add country fields to product_provider_mappings
        if (Schema::hasTable('product_provider_mappings')) {
            Schema::table('product_provider_mappings', function (Blueprint $table) {
                if (!Schema::hasColumn('product_provider_mappings', 'country_name')) {
                    $table->string('country_name', 100)->nullable()->after('variant_key');
                }
                if (!Schema::hasColumn('product_provider_mappings', 'country_code')) {
                    $table->string('country_code', 10)->nullable()->after('country_name')->index();
                }
            });
        }

        // 2. Add country fields to digital_product_variations
        if (Schema::hasTable('digital_product_variations')) {
            Schema::table('digital_product_variations', function (Blueprint $table) {
                if (!Schema::hasColumn('digital_product_variations', 'country_name')) {
                    $table->string('country_name', 100)->nullable()->after('variant_key');
                }
                if (!Schema::hasColumn('digital_product_variations', 'country_code')) {
                    $table->string('country_code', 10)->nullable()->after('country_name')->index();
                }
            });
        }

        // 3. Ensure country fields exist on products table
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (!Schema::hasColumn('products', 'country')) {
                    $table->string('country', 100)->nullable()->after('slug');
                }
                if (!Schema::hasColumn('products', 'country_code')) {
                    $table->string('country_code', 20)->nullable()->after('country')->index();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('product_provider_mappings')) {
            Schema::table('product_provider_mappings', function (Blueprint $table) {
                if (Schema::hasColumn('product_provider_mappings', 'country_code')) {
                    $table->dropColumn('country_code');
                }
                if (Schema::hasColumn('product_provider_mappings', 'country_name')) {
                    $table->dropColumn('country_name');
                }
            });
        }

        if (Schema::hasTable('digital_product_variations')) {
            Schema::table('digital_product_variations', function (Blueprint $table) {
                if (Schema::hasColumn('digital_product_variations', 'country_code')) {
                    $table->dropColumn('country_code');
                }
                if (Schema::hasColumn('digital_product_variations', 'country_name')) {
                    $table->dropColumn('country_name');
                }
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (Schema::hasColumn('products', 'country_code')) {
                    $table->dropColumn('country_code');
                }
                if (Schema::hasColumn('products', 'country')) {
                    $table->dropColumn('country');
                }
            });
        }
    }
};
