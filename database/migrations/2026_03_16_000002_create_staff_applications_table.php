<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('staff_position_id')->constrained('staff_positions')->cascadeOnDelete();
            $table->string('status');
            $table->text('reviewer_notes')->nullable();
            $table->string('background_check_status')->nullable();
            $table->text('conditions')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('staff_review_thread_id')->nullable()->constrained('threads')->nullOnDelete();
            $table->foreignId('interview_thread_id')->nullable()->constrained('threads')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['staff_position_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_applications');
    }
};
