<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_report_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meeting_question_id')->constrained()->cascadeOnDelete();
            $table->text('answer')->nullable();
            $table->timestamps();

            $table->unique(['meeting_report_id', 'meeting_question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_report_answers');
    }
};
