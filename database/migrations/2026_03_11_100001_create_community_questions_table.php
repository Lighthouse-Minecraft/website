<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_questions', function (Blueprint $table) {
            $table->id();
            $table->text('question_text');
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('suggested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('suggestion_id')->nullable()->constrained('question_suggestions')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('start_date');
            $table->index('end_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_questions');
    }
};
