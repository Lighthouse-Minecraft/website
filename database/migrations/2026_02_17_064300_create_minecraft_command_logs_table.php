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
        Schema::create('minecraft_command_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('command');
            $table->string('command_type')->index();
            $table->string('target')->nullable();
            $table->enum('status', ['success', 'failed', 'timeout'])->index();
            $table->text('response')->nullable();
            $table->text('error_message')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('executed_at')->useCurrent();
            $table->integer('execution_time_ms')->nullable();
            $table->timestamps();

            $table->index(['command_type', 'status']);
            $table->index('executed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('minecraft_command_logs');
    }
};
