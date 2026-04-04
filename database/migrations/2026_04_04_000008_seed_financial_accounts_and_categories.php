<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Accounts
        $accounts = [
            ['name' => 'Cash', 'type' => 'cash', 'opening_balance' => 0],
            ['name' => 'Stripe', 'type' => 'payment-processor', 'opening_balance' => 0],
            ['name' => 'RelayFi Checking', 'type' => 'checking', 'opening_balance' => 0],
            ['name' => 'RelayFi Savings', 'type' => 'savings', 'opening_balance' => 0],
        ];

        foreach ($accounts as $account) {
            $exists = DB::table('financial_accounts')->where('name', $account['name'])->exists();
            if (! $exists) {
                DB::table('financial_accounts')->insert(array_merge($account, [
                    'is_archived' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }

        // Expense top-level categories and subcategories
        $expenseCategories = [
            ['name' => 'Infrastructure', 'children' => ['Minecraft Hosting', 'Web Hosting', 'Domain & Email']],
            ['name' => 'Software & Tools', 'children' => ['Discord Bots', 'Web Dev Tools', 'Other Software']],
            ['name' => 'Administration', 'children' => ['Fees', 'Taxes', 'Board/Legal']],
            ['name' => 'Ministry/Community', 'children' => ['Events', 'Donations to Other Ministries', 'Other Ministry Costs']],
        ];

        $sortOrder = 0;
        foreach ($expenseCategories as $category) {
            $parentId = DB::table('financial_categories')
                ->where('name', $category['name'])
                ->where('type', 'expense')
                ->whereNull('parent_id')
                ->value('id');

            if (! $parentId) {
                $parentId = DB::table('financial_categories')->insertGetId([
                    'name' => $category['name'],
                    'parent_id' => null,
                    'type' => 'expense',
                    'sort_order' => $sortOrder++,
                    'is_archived' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $childSort = 0;
            foreach ($category['children'] as $child) {
                $exists = DB::table('financial_categories')
                    ->where('name', $child)
                    ->where('parent_id', $parentId)
                    ->exists();

                if (! $exists) {
                    DB::table('financial_categories')->insert([
                        'name' => $child,
                        'parent_id' => $parentId,
                        'type' => 'expense',
                        'sort_order' => $childSort++,
                        'is_archived' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // Income top-level categories (no subcategories)
        $incomeCategories = ['Donations', 'Staff Cash Contributions'];

        $incomeSortOrder = 0;
        foreach ($incomeCategories as $name) {
            $exists = DB::table('financial_categories')
                ->where('name', $name)
                ->where('type', 'income')
                ->whereNull('parent_id')
                ->exists();

            if (! $exists) {
                DB::table('financial_categories')->insert([
                    'name' => $name,
                    'parent_id' => null,
                    'type' => 'income',
                    'sort_order' => $incomeSortOrder++,
                    'is_archived' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('financial_categories')->whereIn('name', [
            'Infrastructure', 'Software & Tools', 'Administration', 'Ministry/Community',
            'Minecraft Hosting', 'Web Hosting', 'Domain & Email',
            'Discord Bots', 'Web Dev Tools', 'Other Software',
            'Fees', 'Taxes', 'Board/Legal',
            'Events', 'Donations to Other Ministries', 'Other Ministry Costs',
            'Donations', 'Staff Cash Contributions',
        ])->delete();

        DB::table('financial_accounts')->whereIn('name', [
            'Cash', 'Stripe', 'RelayFi Checking', 'RelayFi Savings',
        ])->delete();
    }
};
