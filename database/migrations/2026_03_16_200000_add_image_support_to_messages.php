<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('kind');
            $table->boolean('image_was_purged')->default(false)->after('image_path');
        });

        Schema::table('threads', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable()->after('status');
            $table->timestamp('locked_at')->nullable()->after('is_locked');
        });

        // Backfill existing data
        DB::table('threads')
            ->where('status', 'closed')
            ->update(['closed_at' => DB::raw('updated_at')]);

        DB::table('threads')
            ->where('is_locked', true)
            ->update(['locked_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['image_path', 'image_was_purged']);
        });

        Schema::table('threads', function (Blueprint $table) {
            $table->dropColumn(['closed_at', 'locked_at']);
        });
    }
};
