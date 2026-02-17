<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add an `is_viewer` boolean column to the `thread_participants` table.
     *
     * The new column defaults to `false` and is placed after the `user_id` column.
     */
    public function up(): void
    {
        Schema::table('thread_participants', function (Blueprint $table) {
            $table->boolean('is_viewer')->default(false)->after('user_id');
        });
    }

    /**
     * Reverts the schema change by removing the `is_viewer` column from the `thread_participants` table.
     */
    public function down(): void
    {
        Schema::table('thread_participants', function (Blueprint $table) {
            $table->dropColumn('is_viewer');
        });
    }
};
