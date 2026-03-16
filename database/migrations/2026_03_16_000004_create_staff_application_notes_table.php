<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_application_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_application_id')->constrained('staff_applications')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index('staff_application_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_application_notes');
    }
};
