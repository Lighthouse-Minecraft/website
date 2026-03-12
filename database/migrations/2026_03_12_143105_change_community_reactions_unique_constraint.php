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
        Schema::table('community_reactions', function (Blueprint $table) {
            $table->dropUnique(['community_response_id', 'user_id', 'emoji']);
            $table->unique(['community_response_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('community_reactions', function (Blueprint $table) {
            $table->dropUnique(['community_response_id', 'user_id']);
            $table->unique(['community_response_id', 'user_id', 'emoji']);
        });
    }
};
