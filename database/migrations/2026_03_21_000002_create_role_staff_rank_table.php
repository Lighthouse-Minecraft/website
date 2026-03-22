<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_staff_rank', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->unsignedTinyInteger('staff_rank');
            $table->unique(['role_id', 'staff_rank']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_staff_rank');
    }
};
