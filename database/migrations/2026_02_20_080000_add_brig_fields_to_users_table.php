<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('in_brig')->default(false)->after('promoted_at');
            $table->text('brig_reason')->nullable()->after('in_brig');
            $table->timestamp('brig_expires_at')->nullable()->after('brig_reason');
            $table->boolean('brig_timer_notified')->default(false)->after('brig_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['in_brig', 'brig_reason', 'brig_expires_at', 'brig_timer_notified']);
        });
    }
};
