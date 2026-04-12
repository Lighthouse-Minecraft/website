<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credential_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credential_id')->constrained('credentials')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('action');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credential_access_logs');
    }
};
