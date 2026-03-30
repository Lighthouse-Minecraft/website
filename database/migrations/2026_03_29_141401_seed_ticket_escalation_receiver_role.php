<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('roles')->where('name', 'Ticket Escalation - Receiver')->exists();

        if (! $exists) {
            DB::table('roles')->insert([
                'name' => 'Ticket Escalation - Receiver',
                'description' => 'Receive escalation notifications when a ticket goes unassigned past the configured threshold',
                'color' => 'orange',
                'icon' => 'bell-alert',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'Ticket Escalation - Receiver')->delete();
    }
};
