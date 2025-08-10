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
            ->whereIn('name', ['Officer', 'Command', 'Chaplain', 'Engineer', 'Quartermaster', 'Steward', 'Crew Member'])
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
                'name' => 'Crew Member',
                'description' => 'Crew member on the site.',
                'color' => 'orange',
                'icon' => 'beaker',
            ],
        ]);
    }
};
