<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('background_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('run_by_user_id')->constrained('users');
            $table->string('service');
            $table->date('completed_date');
            $table->string('status');
            $table->text('notes')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('background_check_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('background_check_id')->constrained('background_checks');
            $table->string('path');
            $table->string('original_filename');
            $table->foreignId('uploaded_by_user_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('background_check_documents');
        Schema::dropIfExists('background_checks');
    }
};
