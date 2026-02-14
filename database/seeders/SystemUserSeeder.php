<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SystemUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'system@lighthouse.local'],
            [
                'name' => 'System',
                'password' => Hash::make('system-user-no-login'),
            ]
        );
    }
}
