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
        Schema::create('message_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messages')->onDelete('cascade');
            $table->foreignId('thread_id')->constrained('threads')->onDelete('cascade');
            $table->foreignId('flagged_by_user_id')->constrained('users');
            $table->text('note');
            $table->string('status')->default('new')->index(); // new, acknowledged
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('staff_notes')->nullable();
            $table->foreignId('flag_review_ticket_id')->nullable()->constrained('threads');
            $table->timestamps();

            $table->index(['message_id', 'status']);
            $table->index(['thread_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_flags');
    }
};
