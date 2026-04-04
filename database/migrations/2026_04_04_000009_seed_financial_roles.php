<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roles = [
            [
                'name' => 'Financials - View',
                'description' => 'Read-only access to the full ledger and all financial reports',
                'color' => 'green',
                'icon' => 'eye',
            ],
            [
                'name' => 'Financials - Treasurer',
                'description' => 'Enter and edit transactions, set monthly budgets, publish period reports',
                'color' => 'blue',
                'icon' => 'banknotes',
            ],
            [
                'name' => 'Financials - Manage',
                'description' => 'Full financial access including managing accounts, categories, and tags',
                'color' => 'purple',
                'icon' => 'cog-6-tooth',
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
        DB::table('roles')->whereIn('name', [
            'Financials - View',
            'Financials - Treasurer',
            'Financials - Manage',
        ])->delete();
    }
};
