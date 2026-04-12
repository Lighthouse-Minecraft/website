<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_rule_agreements', function (Blueprint $table) {
            $table->foreignId('proxy_user_id')
                ->nullable()
                ->after('agreed_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_rule_agreements', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\User::class, 'proxy_user_id');
            $table->dropColumn('proxy_user_id');
        });
    }
};
