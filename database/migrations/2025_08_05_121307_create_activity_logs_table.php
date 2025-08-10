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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // The user who caused the activity (nullable for system actions)
            $table->foreignId('causer_id')->nullable()->constrained('users')->nullOnDelete();

            // Polymorphic subject (what the action was done to)
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');

            // Action name (e.g., user_registered, rules_accepted)
            $table->string('action');

            // Optional human-readable description
            $table->text('description')->nullable();

            // Optional structured metadata (IP, user agent, etc.)
            $table->json('meta')->nullable();

            $table->timestamps();

            // Indexes for performance
            $table->index(['subject_type', 'subject_id']);
            $table->index('causer_id');
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
