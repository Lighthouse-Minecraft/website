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
        Schema::create('prayer_country_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prayer_country_id')->constrained()->onDelete('cascade');
            $table->year('year');
            $table->integer('count')->default(0);
            $table->timestamps();

            $table->unique(['prayer_country_id', 'year'], 'country_year_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prayer_country_stats');
    }
};
