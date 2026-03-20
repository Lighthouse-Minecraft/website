<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('roles')->where('name', 'Blog Author')->exists();

        if ($exists) {
            DB::table('roles')->where('name', 'Blog Author')->update([
                'description' => 'Create, edit, and manage blog posts',
                'color' => 'violet',
                'icon' => 'pencil-square',
                'updated_at' => now(),
            ]);
        } else {
            DB::table('roles')->insert([
                'name' => 'Blog Author',
                'description' => 'Create, edit, and manage blog posts',
                'color' => 'violet',
                'icon' => 'pencil-square',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'Blog Author')->delete();
    }
};
