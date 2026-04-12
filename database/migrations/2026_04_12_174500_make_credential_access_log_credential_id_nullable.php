<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credential_access_logs', function (Blueprint $table) {
            $table->dropForeign(['credential_id']);
            $table->unsignedBigInteger('credential_id')->nullable()->change();
            $table->foreign('credential_id')->references('id')->on('credentials')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('credential_access_logs', function (Blueprint $table) {
            $table->dropForeign(['credential_id']);
            $table->unsignedBigInteger('credential_id')->nullable(false)->change();
            $table->foreign('credential_id')->references('id')->on('credentials')->cascadeOnDelete();
        });
    }
};
