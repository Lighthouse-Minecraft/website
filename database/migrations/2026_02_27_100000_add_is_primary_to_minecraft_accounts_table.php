<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('minecraft_accounts', function (Blueprint $table) {
            $table->boolean('is_primary')->default(false)->after('status');
        });

        // Backfill: set the first active account per user as primary
        DB::statement('
            UPDATE minecraft_accounts
            SET is_primary = '.(DB::getDriverName() === 'pgsql' ? 'true' : '1')."
            WHERE id IN (
                SELECT MIN(id)
                FROM minecraft_accounts
                WHERE status = 'active'
                GROUP BY user_id
            )
        ");
    }

    public function down(): void
    {
        Schema::table('minecraft_accounts', function (Blueprint $table) {
            $table->dropColumn('is_primary');
        });
    }
};
