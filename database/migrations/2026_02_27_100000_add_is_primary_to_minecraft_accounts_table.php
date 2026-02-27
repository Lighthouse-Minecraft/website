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
        $primaryAccountIds = DB::table('minecraft_accounts')
            ->where('status', 'active')
            ->groupBy('user_id')
            ->selectRaw('MIN(id) as id')
            ->pluck('id');

        if ($primaryAccountIds->isNotEmpty()) {
            DB::table('minecraft_accounts')
                ->whereIn('id', $primaryAccountIds)
                ->update(['is_primary' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('minecraft_accounts', function (Blueprint $table) {
            $table->dropColumn('is_primary');
        });
    }
};
