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
        Schema::table('discipline_reports', function (Blueprint $table) {
            $table->foreignId('report_category_id')->nullable()->after('severity')->constrained('report_categories')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('discipline_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('report_category_id');
        });
    }
};
