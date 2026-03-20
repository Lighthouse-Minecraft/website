<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->foreignId('community_question_id')
                ->nullable()
                ->after('category_id')
                ->constrained('community_questions')
                ->nullOnDelete();
        });

        Schema::create('blog_post_community_response', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_response_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['blog_post_id', 'community_response_id'], 'blog_post_response_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_post_community_response');

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('community_question_id');
        });
    }
};
