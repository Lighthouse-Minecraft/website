<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->after('email');
            $table->string('parent_email')->nullable()->after('date_of_birth');
            $table->string('brig_type', 30)->nullable()->after('brig_timer_notified');
            $table->boolean('parent_allows_site')->default(true)->after('brig_type');
            $table->boolean('parent_allows_minecraft')->default(true)->after('parent_allows_site');
            $table->boolean('parent_allows_discord')->default(true)->after('parent_allows_minecraft');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'date_of_birth',
                'parent_email',
                'brig_type',
                'parent_allows_site',
                'parent_allows_minecraft',
                'parent_allows_discord',
            ]);
        });
    }
};
