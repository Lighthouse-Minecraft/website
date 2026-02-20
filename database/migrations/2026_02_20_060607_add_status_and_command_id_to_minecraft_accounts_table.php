<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('minecraft_accounts', function (Blueprint $table) {
            $table->enum('status', ['verifying', 'active', 'cancelled'])
                ->default('verifying')
                ->after('avatar_url')
                ->index();

            $table->string('command_id')->nullable()->after('status');
        });

        // Backfill all pre-existing accounts as active (they were already verified)
        DB::statement("UPDATE minecraft_accounts SET status = 'active'");

        // Backfill command_id for pre-existing accounts:
        // Java accounts use username, Bedrock accounts use uuid
        DB::statement("UPDATE minecraft_accounts SET command_id = username WHERE account_type = 'java'");
        DB::statement("UPDATE minecraft_accounts SET command_id = uuid WHERE account_type = 'bedrock'");
    }

    public function down(): void
    {
        Schema::table('minecraft_accounts', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'command_id']);
        });
    }
};
