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
        Schema::create('minecraft_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('username');
            $table->string('uuid')->unique();
            $table->string('account_type'); // Java or Bedrock
            $table->timestamp('verified_at');
            $table->timestamp('last_username_check_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('last_username_check_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('minecraft_accounts');
    }
};
