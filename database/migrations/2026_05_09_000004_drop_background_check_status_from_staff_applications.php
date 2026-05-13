<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing background_check_status values are historical and pre-date the
        // BackgroundCheck model. They are not migrated because the BackgroundCheck
        // model now owns this data going forward.
        Schema::table('staff_applications', function (Blueprint $table) {
            $table->dropColumn('background_check_status');
        });
    }

    public function down(): void
    {
        Schema::table('staff_applications', function (Blueprint $table) {
            $table->string('background_check_status')->nullable()->after('status');
        });
    }
};
