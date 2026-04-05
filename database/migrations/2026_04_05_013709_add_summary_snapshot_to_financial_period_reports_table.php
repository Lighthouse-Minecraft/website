<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_period_reports', function (Blueprint $table) {
            $table->json('summary_snapshot')->nullable()->after('published_by');
        });
    }

    public function down(): void
    {
        Schema::table('financial_period_reports', function (Blueprint $table) {
            $table->dropColumn('summary_snapshot');
        });
    }
};
