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
                'name' => 'Page Editor',
                'description' => 'Can edit and manage content on the website.',
                'color' => 'purple',
                'icon' => 'newspaper'
            ],
            [
                'name' => 'Crew Member',
                'description' => 'Crew member on the site.',
                'color' => 'orange',
                'icon' => 'beaker'
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('roles')->whereIn('name', ['Page Editor', 'Crew Member'])->delete();
    }
};
