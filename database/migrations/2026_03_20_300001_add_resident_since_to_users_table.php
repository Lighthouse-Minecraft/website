<?php

use App\Enums\MembershipLevel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('resident_since')->nullable()->after('promoted_at');
        });

        // Backfill: for existing Residents and Citizens, set resident_since to promoted_at (or created_at as fallback)
        DB::table('users')
            ->whereIn('membership_level', [MembershipLevel::Resident->value, MembershipLevel::Citizen->value])
            ->update([
                'resident_since' => DB::raw('COALESCE(promoted_at, created_at)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('resident_since');
        });
    }
};
