<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_images', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('alt_text');
            $table->string('path');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->datetime('unreferenced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('blog_image_post', function (Blueprint $table) {
            $table->foreignId('blog_image_id')->constrained('blog_images')->cascadeOnDelete();
            $table->foreignId('blog_post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->primary(['blog_image_id', 'blog_post_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_image_post');
        Schema::dropIfExists('blog_images');
    }
};
