<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('minecraft_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('minecraft_account_id')->constrained()->cascadeOnDelete();
            $table->string('reward_name');
            $table->string('reward_description');
            $table->timestamps();

            $table->unique(['user_id', 'reward_name'], 'unique_user_reward');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('minecraft_rewards');
    }
};
