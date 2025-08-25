<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            if (! Schema::hasColumn('comments', 'commentable_title')) {
                $table->string('commentable_title')->nullable()->after('commentable_type');
            }
            if (! Schema::hasColumn('comments', 'commentable_content')) {
                $table->text('commentable_content')->nullable()->after('commentable_title');
            }
        });

        // Best-effort backfill using correlated subqueries (works on SQLite/MySQL)
        // Blogs
        DB::statement(
            "UPDATE comments SET
                commentable_title = (SELECT title FROM blogs WHERE blogs.id = comments.commentable_id),
                commentable_content = (SELECT content FROM blogs WHERE blogs.id = comments.commentable_id)
             WHERE commentable_type = 'blog' AND commentable_id IS NOT NULL"
        );
        // Announcements
        DB::statement(
            "UPDATE comments SET
                commentable_title = (SELECT title FROM announcements WHERE announcements.id = comments.commentable_id),
                commentable_content = (SELECT content FROM announcements WHERE announcements.id = comments.commentable_id)
             WHERE commentable_type = 'announcement' AND commentable_id IS NOT NULL"
        );
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            if (Schema::hasColumn('comments', 'commentable_content')) {
                $table->dropColumn('commentable_content');
            }
            if (Schema::hasColumn('comments', 'commentable_title')) {
                $table->dropColumn('commentable_title');
            }
        });
    }
};
