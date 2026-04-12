<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DemoteAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:demote-admin-user {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove the Admin role from a user by email address';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = strtolower(trim($this->argument('email')));

        $user = \App\Models\User::where('email', $email)->first();

        if (! $user) {
            $this->error("User with email {$email} not found.");

            return 1;
        }

        if (! $user->isAdmin()) {
            $this->info("User {$user->email} is not an Admin.");

            return 0;
        }

        \App\Actions\RevokeUserAdmin::run($user);

        $this->info("Admin role removed from {$user->email}.");

        return 0;
    }
}
