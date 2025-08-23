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
        Schema::create('prayer_countries', function (Blueprint $table) {
            $table->id();
            $table->string('day');
            $table->string('name');
            $table->string('operation_world_url')->nullable();
            $table->string('prayer_cast_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prayer_countries');
    }
};
