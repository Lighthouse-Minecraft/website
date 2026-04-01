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
            $table->unsignedBigInteger('rules_accepted_by_user_id')->nullable()->after('rules_accepted_at');
            $table->foreign('rules_accepted_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        // Backfill: all users who are Stowaway or above (membership_level >= 1) agreed themselves.
        DB::table('users')
            ->where('membership_level', '>=', 1)
            ->whereNull('rules_accepted_by_user_id')
            ->update(['rules_accepted_by_user_id' => DB::raw('id')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['rules_accepted_by_user_id']);
            $table->dropColumn('rules_accepted_by_user_id');
        });
    }
};
