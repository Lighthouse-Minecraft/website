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
        // Step 1: Get the role IDs to remove
        $roleIds = DB::table('roles')
            ->whereIn('name', ['Stowaway', 'Traveler', 'Resident', 'Citizen'])
            ->pluck('id');

        // Step 2: Remove the pivot relationships first
        DB::table('role_user')
            ->whereIn('role_id', $roleIds)
            ->delete();

        // Step 3: Delete the roles themselves
        DB::table('roles')
            ->whereIn('id', $roleIds)
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('roles')->insert([
            [
                'name' => 'Citizen',
                'description' => 'Users that have gone above and beyond in the community and have special recognition.',
                'color' => 'cyan',
                'icon' => 'home',
            ],
            [
                'name' => 'Resident',
                'description' => 'Permanent member with standard privileges.',
                'color' => 'sky',
                'icon' => 'identification',
            ],
            [
                'name' => 'Traveler',
                'description' => 'New users that are just starting out with limited access.',
                'color' => 'blue',
                'icon' => 'ticket',
            ],
            [
                'name' => 'Stowaway',
                'description' => 'Has agreed to the community rules but has not been verified by a staff member.',
                'color' => 'lime',
                'icon' => 'lifebuoy',
            ],
        ]);
    }
};
