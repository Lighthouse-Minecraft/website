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

        // Ensure author_id exists and is constrained to users
        if (! Schema::hasColumn('acknowledged_blogs', 'author_id')) {
            Schema::table('acknowledged_blogs', function (Blueprint $table): void {
                $table->foreignId('author_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('users')
                    ->onDelete('cascade');
            });
        }

        // If a user_id column exists, backfill author_id
        if (Schema::hasColumn('acknowledged_blogs', 'user_id') && Schema::hasColumn('acknowledged_blogs', 'author_id')) {
            DB::table('acknowledged_blogs')
                ->whereNull('author_id')
                ->update(['author_id' => DB::raw('user_id')]);
        }

        // Drop any unique constraints/indexes involving user_id, then enforce (author_id, blog_id)
        try {
            DB::statement('ALTER TABLE acknowledged_blogs DROP CONSTRAINT IF EXISTS acknowledged_blogs_user_id_blog_id_unique');
        } catch (\Throwable $e) {
            // ignore if not a constraint-based unique (e.g., SQLite)
        }
        try {
            DB::statement('DROP INDEX IF EXISTS acknowledged_blogs_user_id_blog_id_unique');
        } catch (\Throwable $e) {
            // ignore
        }

        // Replace existing author/blog unique for a clean slate
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

        // Create the correct unique index on (author_id, blog_id)
        try {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS acknowledged_blogs_author_id_blog_id_unique ON acknowledged_blogs (author_id, blog_id)');
        } catch (\Throwable $e) {
            // Fallback for engines without IF NOT EXISTS support
            try {
                Schema::table('acknowledged_blogs', function (Blueprint $table): void {
                    $table->unique(['author_id', 'blog_id']);
                });
            } catch (\Throwable $e2) {
                // ignore if already exists
            }
        }

        // Optionally drop user_id column if it exists to avoid confusion
        if (Schema::hasColumn('acknowledged_blogs', 'user_id')) {
            Schema::table('acknowledged_blogs', function (Blueprint $table): void {
                try {
                    $table->dropForeign(['user_id']);
                } catch (\Throwable $e) {
                    // ignore if no FK
                }
                try {
                    $table->dropColumn('user_id');
                } catch (\Throwable $e) {
                    // ignore for engines that require separate steps
                }
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('acknowledged_blogs')) {
            return;
        }

        // Recreate user_id and backfill from author_id if needed
        if (! Schema::hasColumn('acknowledged_blogs', 'user_id')) {
            Schema::table('acknowledged_blogs', function (Blueprint $table): void {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('users')
                    ->onDelete('cascade');
            });
        }

        if (Schema::hasColumn('acknowledged_blogs', 'user_id') && Schema::hasColumn('acknowledged_blogs', 'author_id')) {
            DB::table('acknowledged_blogs')
                ->whereNull('user_id')
                ->update(['user_id' => DB::raw('author_id')]);
        }

        // Swap unique back to (user_id, blog_id)
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

        try {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS acknowledged_blogs_user_id_blog_id_unique ON acknowledged_blogs (user_id, blog_id)');
        } catch (\Throwable $e) {
            try {
                Schema::table('acknowledged_blogs', function (Blueprint $table): void {
                    $table->unique(['user_id', 'blog_id']);
                });
            } catch (\Throwable $e2) {
                // ignore
            }
        }

        // We do not drop author_id in down to avoid data loss; unique has been reverted.
    }
};
