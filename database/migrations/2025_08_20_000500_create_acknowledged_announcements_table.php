<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acknowledged_announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('announcement_id')->constrained('announcements')->onDelete('cascade');
            $table->timestamps();
            $table->unique(['author_id', 'announcement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acknowledged_announcements');
    }
};
