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
        Schema::create('minecraft_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 8)->unique()->index();
            $table->string('account_type'); // Java or Bedrock
            $table->string('minecraft_username')->nullable();
            $table->string('minecraft_uuid')->nullable();
            $table->enum('status', ['pending', 'completed', 'expired', 'failed'])->default('pending')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('whitelisted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('minecraft_verifications');
    }
};
