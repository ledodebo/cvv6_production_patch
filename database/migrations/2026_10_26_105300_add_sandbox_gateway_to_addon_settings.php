<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $existing = DB::table('addon_settings')
            ->where('key_name', 'sandbox')
            ->where('settings_type', 'payment_config')
            ->first();

        $values = json_encode([
            'gateway' => 'sandbox',
            'mode' => 'test',
            'status' => '1',
        ]);

        $additionalData = json_encode([
            'gateway_title' => 'Sandbox Gateway (0$ Offline Test)',
            'gateway_image' => null,
        ]);

        if (!$existing) {
            DB::table('addon_settings')->insert([
                'id' => (string) Str::uuid(),
                'key_name' => 'sandbox',
                'live_values' => $values,
                'test_values' => $values,
                'settings_type' => 'payment_config',
                'mode' => 'test',
                'is_active' => 1,
                'additional_data' => $additionalData,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('addon_settings')
                ->where('key_name', 'sandbox')
                ->where('settings_type', 'payment_config')
                ->update([
                    'is_active' => 1,
                    'mode' => 'test',
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('addon_settings')
            ->where('key_name', 'sandbox')
            ->where('settings_type', 'payment_config')
            ->delete();
    }
};
