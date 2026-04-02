<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('roles')->where('name', 'Contact - Receive Submissions')->exists();

        if (! $exists) {
            DB::table('roles')->insert([
                'name' => 'Contact - Receive Submissions',
                'description' => 'Receive notifications for new public contact inquiries and manage contact threads',
                'color' => 'cyan',
                'icon' => 'envelope',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'Contact - Receive Submissions')->delete();
    }
};
