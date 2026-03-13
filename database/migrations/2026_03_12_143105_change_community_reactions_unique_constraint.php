<?php

use App\Models\CommunityReaction;
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
        // Remove duplicate (community_response_id, user_id) rows, keeping the latest
        $keepIds = CommunityReaction::query()
            ->selectRaw('MAX(id) as max_id')
            ->groupBy('community_response_id', 'user_id')
            ->pluck('max_id');

        CommunityReaction::whereNotIn('id', $keepIds)->delete();

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
