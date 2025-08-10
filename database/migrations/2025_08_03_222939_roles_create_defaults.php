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
                'name' => 'Admin',
                'description' => 'Full management of the website. Has full power to do anything and everything as any user.',
                'color' => 'red',
                'icon' => 'shield-check',
            ],
            [
                'name' => 'Officer',
                'description' => 'Manages operations and oversees users.',
                'color' => 'emerald',
                'icon' => 'star',
            ],
            [
                'name' => 'Command',
                'description' => 'Officer of the Command department.',
                'color' => 'orange',
                'icon' => 'finger-print',
            ],
            [
                'name' => 'Chaplain',
                'description' => 'Officer of the Chaplain department.',
                'color' => 'orange',
                'icon' => 'fire',
            ],
            [
                'name' => 'Engineer',
                'description' => 'Officer of the Engineer department.',
                'color' => 'orange',
                'icon' => 'code-bracket',
            ],
            [
                'name' => 'Quartermaster',
                'description' => 'Officer of the Quartermaster department.',
                'color' => 'orange',
                'icon' => 'check-badge',
            ],
            [
                'name' => 'Steward',
                'description' => 'Officer of the Steward department.',
                'color' => 'orange',
                'icon' => 'chat-bubble-bottom-center-text',
            ],
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
                'name' => 'Guest',
                'description' => 'Unregistered user with minimal access.',
                'color' => 'zinc',
                'icon' => 'information-circle',
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('roles')->whereIn('name', [
            'Admin', 'Officer', 'Command', 'Chaplain', 'Engineer', 'Quartermaster', 'Steward', 'Traveler', 'Resident', 'Citizen', 'Guest',
        ])->delete();
    }
};
