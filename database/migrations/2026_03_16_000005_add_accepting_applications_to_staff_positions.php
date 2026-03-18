<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_positions', function (Blueprint $table) {
            $table->boolean('accepting_applications')->default(false)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('staff_positions', function (Blueprint $table) {
            $table->dropColumn('accepting_applications');
        });
    }
};
