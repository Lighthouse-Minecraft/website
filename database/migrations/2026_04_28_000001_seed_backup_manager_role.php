<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('roles')->where('name', 'Backup Manager')->exists();
        if (! $exists) {
            DB::table('roles')->insert([
                'name' => 'Backup Manager',
                'description' => 'Access to the backup management dashboard: create, download, restore, and delete database backups.',
                'color' => 'amber',
                'icon' => 'circle-stack',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // up() is conditional (only inserts if missing), so rollback cannot safely
        // determine whether this role pre-existed — leave it in place.
    }
};
