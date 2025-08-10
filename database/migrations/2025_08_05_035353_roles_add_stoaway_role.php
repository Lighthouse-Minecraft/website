<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('roles')->insert([
            [
                'name' => 'Stowaway',
                'description' => 'Has agreed to the community rules but has not been verified by a staff member.',
                'color' => 'lime',
                'icon' => 'lifebuoy',
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('roles')->where('name', 'Stoaway')->delete();
    }
};
