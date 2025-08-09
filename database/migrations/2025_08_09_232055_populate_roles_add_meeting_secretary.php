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
                'name' => 'Meeting Secretary',
                'description' => 'Has permission to manage meetings to take notes. Includes the ability to create, and edit meetings.',
                'color' => 'amber',
                'icon' => 'inbox-arrow-down'
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('roles')->where('name', 'Meeting Secretary')->delete();
    }
};
