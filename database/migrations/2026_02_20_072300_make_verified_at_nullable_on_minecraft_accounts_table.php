<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('minecraft_accounts', function (Blueprint $table) {
            // verified_at is now null for accounts in 'verifying' state;
            // it gets populated when CompleteVerification promotes them to 'active'.
            $table->timestamp('verified_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('minecraft_accounts', function (Blueprint $table) {
            $table->timestamp('verified_at')->nullable(false)->change();
        });
    }
};
