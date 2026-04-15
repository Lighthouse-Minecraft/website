<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credential_staff_position', function (Blueprint $table) {
            $table->foreignId('credential_id')->constrained('credentials')->cascadeOnDelete();
            $table->foreignId('staff_position_id')->constrained('staff_positions')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['credential_id', 'staff_position_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credential_staff_position');
    }
};
