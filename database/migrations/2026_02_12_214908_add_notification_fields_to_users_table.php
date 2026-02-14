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
        Schema::table('users', function (Blueprint $table) {
            $table->string('pushover_key')->nullable();
            $table->integer('pushover_monthly_count')->default(0);
            $table->timestamp('pushover_count_reset_at')->nullable();
            $table->timestamp('last_notification_read_at')->nullable();
            $table->string('email_digest_frequency')->default('immediate'); // immediate, daily, weekly
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'pushover_key',
                'pushover_monthly_count',
                'pushover_count_reset_at',
                'last_notification_read_at',
                'email_digest_frequency',
            ]);
        });
    }
};
