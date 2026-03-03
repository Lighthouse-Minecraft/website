<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Update the minecraft_accounts.status check constraint on PostgreSQL to include 'parent_disabled'.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE minecraft_accounts DROP CONSTRAINT IF EXISTS minecraft_accounts_status_check');
            DB::statement("ALTER TABLE minecraft_accounts ADD CONSTRAINT minecraft_accounts_status_check CHECK (status IN ('verifying', 'active', 'cancelled', 'banned', 'removed', 'parent_disabled'))");
        }
    }

    /**
     * Restore the PostgreSQL check constraint to exclude 'parent_disabled'.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE minecraft_accounts DROP CONSTRAINT IF EXISTS minecraft_accounts_status_check');
            DB::table('minecraft_accounts')->where('status', 'parent_disabled')->update(['status' => 'cancelled']);
            DB::statement("ALTER TABLE minecraft_accounts ADD CONSTRAINT minecraft_accounts_status_check CHECK (status IN ('verifying', 'active', 'cancelled', 'banned', 'removed'))");
        }
    }
};
