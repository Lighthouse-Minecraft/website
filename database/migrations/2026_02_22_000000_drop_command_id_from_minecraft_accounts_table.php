<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('minecraft_accounts', function (Blueprint $table) {
            $table->dropColumn('command_id');
        });
    }

    public function down(): void
    {
        Schema::table('minecraft_accounts', function (Blueprint $table) {
            $table->string('command_id')->nullable()->after('status');
        });
    }
};
