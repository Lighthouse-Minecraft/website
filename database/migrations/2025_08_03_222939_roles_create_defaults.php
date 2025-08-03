<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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
                'name' => 'Admin',
                'description' => 'Full management of the website. Has full power to do anything and everything as any user.',
                'color' => 'red',
                'icon' => 'shield-check'
            ],
            [
                'name' => 'Officer',
                'description' => 'Manages operations and oversees users.',
                'color' => 'emerald',
                'icon' => 'star'
            ],
            [
                'name' => 'Command',
                'description' => 'Officer of the Command department.',
                'color' => 'orange',
                'icon' => 'star'
            ],
            [
                'name' => 'Chaplain',
                'description' => 'Officer of the Chaplain department.',
                'color' => 'orange',
                'icon' => 'star'
            ],
            [
                'name' => 'Engineer',
                'description' => 'Officer of the Engineer department.',
                'color' => 'orange',
                'icon' => 'star'
            ],
            [
                'name' => 'Quartermaster',
                'description' => 'Officer of the Quartermaster department.',
                'color' => 'orange',
                'icon' => 'star'
            ],
            [
                'name' => 'Steward',
                'description' => 'Officer of the Steward department.',
                'color' => 'orange',
                'icon' => 'star'
            ],
            [
                'name' => 'Citizen',
                'description' => 'Users that have gone above and beyond in the community and have special recognition.',
                'color' => 'cyan',
                'icon' => 'battery-100'
            ],
            [
                'name' => 'Resident',
                'description' => 'Permanent member with standard privileges.',
                'color' => 'sky',
                'icon' => 'battery-50'
            ],
            [
                'name' => 'Traveler',
                'description' => 'New users that are just starting out with limited access.',
                'color' => 'blue',
                'icon' => 'battery-0'
            ],
            [
                'name' => 'Guest',
                'description' => 'Unregistered user with minimal access.',
                'color' => 'zinc',
                'icon' => 'globe-alt'
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('roles')->whereIn('name', [
            'Admin', 'Officer', 'Command', 'Chaplain', 'Engineer', 'Quartermaster', 'Steward', 'Traveler', 'Resident', 'Citizen', 'Guest'
        ])->delete();
    }
};
