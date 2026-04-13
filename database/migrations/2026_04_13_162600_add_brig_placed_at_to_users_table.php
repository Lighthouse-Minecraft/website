<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add brig_placed_at nullable timestamp to users table.
     *
     * Records when the user was placed in the brig so the dashboard widget
     * can display and sort by "Date Placed". Set by PutUserInBrig action.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('brig_placed_at')->nullable()->after('brig_timer_notified');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('brig_placed_at');
        });
    }
};
