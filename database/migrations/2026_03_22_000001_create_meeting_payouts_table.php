<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('minecraft_account_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('amount');
            $table->enum('status', ['paid', 'skipped', 'failed', 'pending']);
            $table->string('skip_reason')->nullable();
            $table->timestamps();

            $table->unique(['meeting_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_payouts');
    }
};
