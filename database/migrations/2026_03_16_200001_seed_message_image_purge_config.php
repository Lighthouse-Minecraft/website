<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('site_configs')->insert([
            'key' => 'message_image_purge_days',
            'value' => '60',
            'description' => 'Days after ticket closure or topic lock before message images are automatically purged.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('site_configs')->where('key', 'message_image_purge_days')->delete();
    }
};
