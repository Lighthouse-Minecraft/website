<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('onboarding_wizard_dismissed_at')->nullable()->after('rules_accepted_at');
            $table->timestamp('onboarding_wizard_completed_at')->nullable()->after('onboarding_wizard_dismissed_at');
        });

        // Backfill: users who already have linked accounts have already completed onboarding.
        // Set dismissed_at so the wizard never appears for them.
        $usersWithAccounts = DB::table('discord_accounts')
            ->select('user_id')
            ->union(DB::table('minecraft_accounts')->select('user_id'));

        DB::table('users')
            ->whereIn('id', $usersWithAccounts)
            ->whereNull('onboarding_wizard_dismissed_at')
            ->update(['onboarding_wizard_dismissed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['onboarding_wizard_dismissed_at', 'onboarding_wizard_completed_at']);
        });
    }
};
