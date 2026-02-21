<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Update the minecraft_accounts.status check constraint on PostgreSQL to include the 'banned' value; no-op for other drivers.
     *
     * On PostgreSQL this drops the existing `minecraft_accounts_status_check` constraint if present and re-adds it so `status` must be one of `'verifying'`, `'active'`, `'cancelled'`, or `'banned'`.
     */
    public function up(): void
    {
        // SQLite doesn't support check constraints on enum columns and doesn't need alteration.
        // On PostgreSQL, the check constraint must be dropped and re-added to include 'banned'.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE minecraft_accounts DROP CONSTRAINT IF EXISTS minecraft_accounts_status_check');
            DB::statement("ALTER TABLE minecraft_accounts ADD CONSTRAINT minecraft_accounts_status_check CHECK (status IN ('verifying', 'active', 'cancelled', 'banned'))");
        }
    }

    /**
     * Restore the PostgreSQL check constraint on minecraft_accounts.status to allow only 'verifying', 'active', and 'cancelled'; no action for other drivers.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE minecraft_accounts DROP CONSTRAINT IF EXISTS minecraft_accounts_status_check');
            // Reassign any banned accounts to cancelled before re-adding the constraint that excludes 'banned'
            DB::table('minecraft_accounts')->where('status', 'banned')->update(['status' => 'cancelled']);
            DB::statement("ALTER TABLE minecraft_accounts ADD CONSTRAINT minecraft_accounts_status_check CHECK (status IN ('verifying', 'active', 'cancelled'))");
        }
    }
};
