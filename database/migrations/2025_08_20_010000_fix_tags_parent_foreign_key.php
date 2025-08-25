<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tags') && Schema::hasColumn('tags', 'parent_id')) {
            Schema::table('tags', function (Blueprint $table): void {
                // Drop any existing FK on parent_id (name is typically tags_parent_id_foreign)
                try {
                    $table->dropForeign(['parent_id']);
                } catch (\Throwable $e) {
                    // Ignore if the foreign key doesn't exist yet (fresh databases)
                }

                // Ensure parent_id references tags (self-referential hierarchy)
                $table->foreign('parent_id')
                    ->references('id')
                    ->on('tags')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tags') && Schema::hasColumn('tags', 'parent_id')) {
            Schema::table('tags', function (Blueprint $table): void {
                try {
                    $table->dropForeign(['parent_id']);
                } catch (\Throwable $e) {
                    // ignore
                }

                // Restore the previous (incorrect) reference only for rollback symmetry
                $table->foreign('parent_id')
                    ->references('id')
                    ->on('categories')
                    ->onDelete('cascade');
            });
        }
    }
};
