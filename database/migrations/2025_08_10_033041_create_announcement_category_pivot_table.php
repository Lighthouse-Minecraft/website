<?php

use Illuminate\Database\Migrations\{Migration};
use Illuminate\Database\Schema\{Blueprint};
use Illuminate\Support\Facades\{Schema};

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('announcement_category')) {
            Schema::create('announcement_category', function (Blueprint $table) {
                $table->id();
                $table->foreignId('announcement_id')->constrained('announcements')->onDelete('cascade');
                $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcement_category');
    }
};
