<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->insertOrIgnore([
            'name' => 'System',
            'email' => 'system@lighthouse.local',
            'password' => Hash::make('system-user-no-login'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('users')->where('email', 'system@lighthouse.local')->delete();
    }
};
