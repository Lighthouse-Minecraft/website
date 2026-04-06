<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roles = [
            [
                'name' => 'Finance - View',
                'description' => 'Read-only access to the full ledger, all financial reports, and financial configuration.',
                'color' => 'green',
                'icon' => 'eye',
            ],
            [
                'name' => 'Finance - Record',
                'description' => 'All Finance - View capabilities plus the ability to enter, draft, post, and reverse journal entries, manage bank reconciliations, and close periods.',
                'color' => 'blue',
                'icon' => 'pencil-square',
            ],
            [
                'name' => 'Finance - Manage',
                'description' => 'All Finance - Record capabilities plus the ability to manage chart of accounts, vendors, tags, restricted funds, budgets, and fiscal year configuration.',
                'color' => 'purple',
                'icon' => 'banknotes',
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
        DB::table('roles')->whereIn('name', ['Finance - View', 'Finance - Record', 'Finance - Manage'])->delete();
    }
};
