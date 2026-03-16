<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_questions', function (Blueprint $table) {
            $table->id();
            $table->string('question_text');
            $table->string('type');
            $table->string('category');
            $table->foreignId('staff_position_id')->nullable()->constrained('staff_positions')->cascadeOnDelete();
            $table->json('select_options')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_questions');
    }
};
