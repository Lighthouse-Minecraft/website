<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Update the minecraft_accounts.status check constraint on PostgreSQL to include the 'removed' value; no-op for other drivers.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE minecraft_accounts DROP CONSTRAINT IF EXISTS minecraft_accounts_status_check');
            DB::statement("ALTER TABLE minecraft_accounts ADD CONSTRAINT minecraft_accounts_status_check CHECK (status IN ('verifying', 'active', 'cancelled', 'banned', 'removed'))");
        }
    }

    /**
     * Restore the PostgreSQL check constraint on minecraft_accounts.status to exclude 'removed'; no action for other drivers.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE minecraft_accounts DROP CONSTRAINT IF EXISTS minecraft_accounts_status_check');
            DB::table('minecraft_accounts')->where('status', 'removed')->update(['status' => 'cancelled']);
            DB::statement("ALTER TABLE minecraft_accounts ADD CONSTRAINT minecraft_accounts_status_check CHECK (status IN ('verifying', 'active', 'cancelled', 'banned'))");
        }
    }
};
