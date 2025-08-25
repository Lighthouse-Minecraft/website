<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('comments', 'reviewed_at')) {
            Schema::table('comments', function (Blueprint $table): void {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('comments', 'reviewed_at')) {
            Schema::table('comments', function (Blueprint $table): void {
                $table->dropColumn('reviewed_at');
            });
        }
    }
};
