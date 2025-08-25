<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('acknowledged_blogs')) {
            return;
        }

        // Ensure the unique is on (author_id, blog_id). First, drop any user-based unique.
        try {
            // Postgres-style unique constraint
            DB::statement('ALTER TABLE acknowledged_blogs DROP CONSTRAINT IF EXISTS acknowledged_blogs_user_id_blog_id_unique');
        } catch (\Throwable $e) {
            // ignore
        }
        try {
            // Index-based unique (SQLite / others)
            DB::statement('DROP INDEX IF EXISTS acknowledged_blogs_user_id_blog_id_unique');
        } catch (\Throwable $e) {
            // ignore
        }

        // Drop existing author unique before recreating (avoids duplicates across engines)
        try {
            DB::statement('ALTER TABLE acknowledged_blogs DROP CONSTRAINT IF EXISTS acknowledged_blogs_author_id_blog_id_unique');
        } catch (\Throwable $e) {
            // ignore
        }
        try {
            DB::statement('DROP INDEX IF EXISTS acknowledged_blogs_author_id_blog_id_unique');
        } catch (\Throwable $e) {
            // ignore
        }

        // Create the correct unique on (author_id, blog_id)
        try {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS acknowledged_blogs_author_id_blog_id_unique ON acknowledged_blogs (author_id, blog_id)');
        } catch (\Throwable $e) {
            // Fallback: use Schema builder (may fail if it already exists, hence try/catch)
            try {
                Schema::table('acknowledged_blogs', function (Blueprint $table): void {
                    $table->unique(['author_id', 'blog_id'], 'acknowledged_blogs_author_id_blog_id_unique');
                });
            } catch (\Throwable $e2) {
                // ignore if already exists
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('acknowledged_blogs')) {
            return;
        }

        // Drop the author unique index/constraint only
        try {
            DB::statement('ALTER TABLE acknowledged_blogs DROP CONSTRAINT IF EXISTS acknowledged_blogs_author_id_blog_id_unique');
        } catch (\Throwable $e) {
            // ignore
        }
        try {
            DB::statement('DROP INDEX IF EXISTS acknowledged_blogs_author_id_blog_id_unique');
        } catch (\Throwable $e) {
            // ignore
        }
    }
};
