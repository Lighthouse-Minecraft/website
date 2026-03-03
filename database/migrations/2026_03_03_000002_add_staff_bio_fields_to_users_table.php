<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('staff_first_name')->nullable()->after('staff_title');
            $table->string('staff_last_initial', 1)->nullable()->after('staff_first_name');
            $table->text('staff_bio')->nullable()->after('staff_last_initial');
            $table->string('staff_photo_path')->nullable()->after('staff_bio');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['staff_first_name', 'staff_last_initial', 'staff_bio', 'staff_photo_path']);
        });
    }
};
