<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_application_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_application_id')->constrained('staff_applications')->cascadeOnDelete();
            $table->foreignId('application_question_id')->constrained('application_questions')->cascadeOnDelete();
            $table->text('answer')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_application_answers');
    }
};
