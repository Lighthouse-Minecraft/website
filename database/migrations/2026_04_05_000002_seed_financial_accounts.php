<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $accounts = [
            // Assets (normal balance: debit)
            ['code' => 1000, 'name' => 'Cash on Hand',          'type' => 'asset', 'subtype' => 'cash',           'normal_balance' => 'debit',  'is_bank_account' => false],
            ['code' => 1010, 'name' => 'Stripe Account',         'type' => 'asset', 'subtype' => 'cash',           'normal_balance' => 'debit',  'is_bank_account' => true],
            ['code' => 1020, 'name' => 'RelayFi Checking',       'type' => 'asset', 'subtype' => 'cash',           'normal_balance' => 'debit',  'is_bank_account' => true],
            ['code' => 1030, 'name' => 'RelayFi Savings',        'type' => 'asset', 'subtype' => 'cash',           'normal_balance' => 'debit',  'is_bank_account' => true],
            // Net Assets (normal balance: credit)
            ['code' => 3000, 'name' => 'Net Assets — Unrestricted', 'type' => 'net_assets', 'subtype' => 'unrestricted', 'normal_balance' => 'credit', 'is_bank_account' => false, 'fund_type' => 'unrestricted'],
            ['code' => 3100, 'name' => 'Net Assets — Restricted',   'type' => 'net_assets', 'subtype' => 'restricted',   'normal_balance' => 'credit', 'is_bank_account' => false, 'fund_type' => 'restricted'],
            // Revenue (normal balance: credit)
            ['code' => 4000, 'name' => 'Donations — General',        'type' => 'revenue', 'subtype' => 'donations',     'normal_balance' => 'credit', 'is_bank_account' => false],
            ['code' => 4100, 'name' => 'Contributions — Leadership', 'type' => 'revenue', 'subtype' => 'contributions', 'normal_balance' => 'credit', 'is_bank_account' => false],
            ['code' => 4200, 'name' => 'Other Income',               'type' => 'revenue', 'subtype' => 'other',         'normal_balance' => 'credit', 'is_bank_account' => false],
            // Expenses (normal balance: debit)
            ['code' => 5000, 'name' => 'Minecraft Hosting',         'type' => 'expense', 'subtype' => 'hosting',      'normal_balance' => 'debit', 'is_bank_account' => false],
            ['code' => 5010, 'name' => 'Web Hosting',               'type' => 'expense', 'subtype' => 'hosting',      'normal_balance' => 'debit', 'is_bank_account' => false],
            ['code' => 5020, 'name' => 'Domain & Email',            'type' => 'expense', 'subtype' => 'hosting',      'normal_balance' => 'debit', 'is_bank_account' => false],
            ['code' => 5030, 'name' => 'Software Subscriptions',    'type' => 'expense', 'subtype' => 'software',     'normal_balance' => 'debit', 'is_bank_account' => false],
            ['code' => 5040, 'name' => 'Payment Processing Fees',   'type' => 'expense', 'subtype' => 'fees',         'normal_balance' => 'debit', 'is_bank_account' => false],
            ['code' => 5050, 'name' => 'Professional Fees',         'type' => 'expense', 'subtype' => 'professional', 'normal_balance' => 'debit', 'is_bank_account' => false],
            ['code' => 5060, 'name' => 'Taxes & Compliance',        'type' => 'expense', 'subtype' => 'taxes',        'normal_balance' => 'debit', 'is_bank_account' => false],
        ];

        foreach ($accounts as $account) {
            $exists = DB::table('financial_accounts')->where('code', $account['code'])->exists();
            if (! $exists) {
                DB::table('financial_accounts')->insert(array_merge([
                    'fund_type' => 'unrestricted',
                    'is_active' => true,
                    'description' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $account));
            }
        }
    }

    public function down(): void
    {
        $codes = [1000, 1010, 1020, 1030, 3000, 3100, 4000, 4100, 4200, 5000, 5010, 5020, 5030, 5040, 5050, 5060];
        DB::table('financial_accounts')->whereIn('code', $codes)->delete();
    }
};
