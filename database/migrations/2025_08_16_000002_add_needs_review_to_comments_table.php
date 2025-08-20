<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('comments', 'needs_review')) {
            Schema::table('comments', function (Blueprint $table): void {
                $table->boolean('needs_review')->default(false)->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('comments', 'needs_review')) {
            Schema::table('comments', function (Blueprint $table): void {
                $table->dropColumn('needs_review');
            });
        }
    }
};
