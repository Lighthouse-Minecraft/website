<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a nullable `next_appeal_available_at` timestamp column to the `users` table after `brig_expires_at`.
     *
     * The new column records when a user may next file an appeal and allows null values.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('next_appeal_available_at')->nullable()->after('brig_expires_at');
        });
    }

    /**
     * Remove the `next_appeal_available_at` timestamp column from the `users` table.
     *
     * This rollback drops the column added by the corresponding migration.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('next_appeal_available_at');
        });
    }
};
