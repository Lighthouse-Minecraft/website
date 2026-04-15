<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credentials', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('website_url')->nullable();
            $table->text('username');
            $table->text('email')->nullable();
            $table->text('password');
            $table->text('totp_secret')->nullable();
            $table->text('notes')->nullable();
            $table->text('recovery_codes')->nullable();
            $table->boolean('needs_password_change')->default(false);
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credentials');
    }
};
