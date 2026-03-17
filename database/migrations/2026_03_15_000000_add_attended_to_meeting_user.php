<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_user', function (Blueprint $table) {
            $table->boolean('attended')->default(false)->after('added_at');
        });

        // Backfill: existing records were all "present" members
        DB::table('meeting_user')->update(['attended' => true]);
    }

    public function down(): void
    {
        Schema::table('meeting_user', function (Blueprint $table) {
            $table->dropColumn('attended');
        });
    }
};
