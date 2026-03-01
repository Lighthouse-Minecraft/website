<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parent_child_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('child_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['parent_user_id', 'child_user_id']);
        });

        // Add CHECK constraint to prevent self-referential links (MySQL only, guarded at model level for SQLite)
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE parent_child_links ADD CHECK (parent_user_id <> child_user_id)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_child_links');
    }
};
