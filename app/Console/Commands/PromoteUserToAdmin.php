<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PromoteUserToAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:promote-user-to-admin {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Promote a user to the Admin role by email address';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');

        $user = \App\Models\User::where('email', $email)->first();

        if (! $user) {
            $this->error("User with email {$email} not found.");

            return 1;
        }

        if ($user->roles()->where('name', 'Admin')->exists()) {
            $this->info("User {$user->email} is already an Admin.");

            return 0;
        }

        $success = \App\Actions\PromoteUserToAdmin::run($user);

        if (! $success) {
            $this->error('Admin role not found.');

            return 1;
        }

        $this->info("User {$user->email} has been promoted to Admin.");

        return 0;
    }
}
