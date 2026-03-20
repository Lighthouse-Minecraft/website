<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn(['hero_image_path', 'og_image_path']);

            $table->foreignId('hero_image_id')->nullable()->constrained('blog_images')->nullOnDelete();
            $table->foreignId('og_image_id')->nullable()->constrained('blog_images')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hero_image_id');
            $table->dropConstrainedForeignId('og_image_id');

            $table->string('hero_image_path')->nullable();
            $table->string('og_image_path')->nullable();
        });
    }
};
