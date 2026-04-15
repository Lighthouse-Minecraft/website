<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('roles')->where('name', 'Vault Manager')->exists();
        if (! $exists) {
            DB::table('roles')->insert([
                'name' => 'Vault Manager',
                'description' => 'Full control over the staff credential vault: create, edit, delete credentials and manage position access.',
                'color' => 'violet',
                'icon' => 'key',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'Vault Manager')->delete();
    }
};
