<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            $table->string('subtype')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Backfill null subtypes before making column NOT NULL
        DB::table('threads')->whereNull('subtype')->update(['subtype' => 'topic']);

        Schema::table('threads', function (Blueprint $table) {
            $table->string('subtype')->nullable(false)->change();
        });
    }
};
