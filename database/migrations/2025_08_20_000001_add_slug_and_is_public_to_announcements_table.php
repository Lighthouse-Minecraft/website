<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            if (! Schema::hasColumn('announcements', 'slug')) {
                $table->string('slug')->nullable()->unique()->after('title');
            }
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            if (Schema::hasColumn('announcements', 'slug')) {
                $table->dropUnique('announcements_slug_unique');
                $table->dropColumn('slug');
            }
            if (Schema::hasColumn('announcements', 'is_public')) {
                $table->dropColumn('is_public');
            }
        });
    }
};
