<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $configs = [
            [
                'key' => 'max_image_size_kb',
                'value' => '2048',
                'description' => 'Default maximum image upload size in kilobytes. Applies to staff photos, board member photos, and other general image uploads.',
            ],
            [
                'key' => 'community_stories_max_image_size_kb',
                'value' => '5120',
                'description' => 'Maximum image upload size in kilobytes for community story responses. Set higher than the general limit to allow richer media.',
            ],
        ];

        foreach ($configs as $config) {
            DB::table('site_configs')->insertOrIgnore([
                'key' => $config['key'],
                'value' => $config['value'],
                'description' => $config['description'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $migrationDate = '2026-03-16 00:00:00';

        DB::table('site_configs')->whereIn('key', [
            'max_image_size_kb',
            'community_stories_max_image_size_kb',
        ])->where('created_at', '>=', $migrationDate)->delete();
    }
};
