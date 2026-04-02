<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            $table->string('guest_name')->nullable()->after('subject');
            $table->string('guest_email')->nullable()->after('guest_name');
            $table->string('conversation_token')->nullable()->unique()->after('guest_email');
        });
    }

    public function down(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            $table->dropUnique(['conversation_token']);
            $table->dropColumn(['guest_name', 'guest_email', 'conversation_token']);
        });
    }
};
