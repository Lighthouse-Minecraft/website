<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('threads', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index(); // ticket, dm, forum (future)
            $table->string('subtype')->index(); // support, admin_action, moderation_flag
            $table->string('department')->nullable()->index(); // StaffDepartment enum value
            $table->string('subject');
            $table->string('status')->index(); // open, pending, resolved, closed
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users');
            $table->boolean('is_flagged')->default(false)->index();
            $table->boolean('has_open_flags')->default(false)->index();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('threads');
    }
};
