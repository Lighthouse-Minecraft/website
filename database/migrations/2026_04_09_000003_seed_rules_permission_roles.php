<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roles = [
            [
                'name' => 'Rules - Manage',
                'description' => 'Create and edit draft rule versions, add/edit/deactivate rules, reorder categories, and edit the rules header and footer.',
                'color' => 'indigo',
                'icon' => 'book-open',
            ],
            [
                'name' => 'Rules - Approve',
                'description' => 'Review submitted rule versions and approve or reject them. Cannot approve a version they created.',
                'color' => 'violet',
                'icon' => 'check-badge',
            ],
        ];

        foreach ($roles as $role) {
            $exists = DB::table('roles')->where('name', $role['name'])->exists();
            if (! $exists) {
                DB::table('roles')->insert(array_merge($role, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        DB::table('roles')->whereIn('name', ['Rules - Manage', 'Rules - Approve'])->delete();
    }
};
