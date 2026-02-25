<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add brig-related columns to the users table.
     *
     * Adds four columns: `in_brig` (boolean, default false) after `promoted_at`,
     * `brig_reason` (text, nullable) after `in_brig`, `brig_expires_at` (timestamp, nullable)
     * after `brig_reason`, and `brig_timer_notified` (boolean, default false) after `brig_expires_at`.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('in_brig')->default(false)->after('promoted_at');
            $table->text('brig_reason')->nullable()->after('in_brig');
            $table->timestamp('brig_expires_at')->nullable()->after('brig_reason');
            $table->boolean('brig_timer_notified')->default(false)->after('brig_expires_at');
        });
    }

    /**
     * Reverts the migration by removing brig-related columns from the users table.
     *
     * Drops the `in_brig`, `brig_reason`, `brig_expires_at`, and `brig_timer_notified` columns from the `users` table.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['in_brig', 'brig_reason', 'brig_expires_at', 'brig_timer_notified']);
        });
    }
};
