<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discord_api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method');
            $table->string('endpoint');
            $table->string('action_type')->index();
            $table->string('target')->nullable();
            $table->enum('status', ['success', 'failed'])->index();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('response')->nullable();
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('executed_at')->useCurrent();
            $table->integer('execution_time_ms')->nullable();
            $table->timestamps();

            $table->index(['action_type', 'status']);
            $table->index('executed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discord_api_logs');
    }
};
