<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('report_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color')->default('zinc');
            $table->timestamps();
        });

        // Seed default categories
        DB::table('report_categories')->insert([
            ['name' => 'Language', 'color' => 'yellow', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Harassment', 'color' => 'red', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Griefing', 'color' => 'orange', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Cheating', 'color' => 'purple', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Disrespect', 'color' => 'blue', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Spam', 'color' => 'indigo', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Inappropriate Content', 'color' => 'red', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Other', 'color' => 'zinc', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_categories');
    }
};
