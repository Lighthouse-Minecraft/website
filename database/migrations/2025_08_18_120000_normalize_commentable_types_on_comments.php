<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Normalize legacy FQCN and PascalCase types to lowercase aliases
        DB::table('comments')
            ->where('commentable_type', 'App\\Models\\Blog')
            ->update(['commentable_type' => 'blog']);

        DB::table('comments')
            ->where('commentable_type', 'Blog')
            ->update(['commentable_type' => 'blog']);

        DB::table('comments')
            ->where('commentable_type', 'App\\Models\\Announcement')
            ->update(['commentable_type' => 'announcement']);

        DB::table('comments')
            ->where('commentable_type', 'Announcement')
            ->update(['commentable_type' => 'announcement']);
    }

    public function down(): void
    {
        // No-op: cannot reliably restore original FQCN values
    }
};
