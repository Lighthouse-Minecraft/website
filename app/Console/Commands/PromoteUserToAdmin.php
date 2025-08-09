<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

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

        // Assuming the relationship is named 'roles' and Role has a 'name' attribute
        if ($user->roles()->where('name', 'Admin')->exists()) {
            $this->info("User {$user->email} is already an Admin.");

            return 0;
        }

        $adminRole = \App\Models\Role::where('name', 'Admin')->first();
        if (! $adminRole) {
            $this->error('Admin role not found.');

            return 1;
        }
        dd('this is a test');
        $user->roles()->attach($adminRole->id);

        $this->info("User {$user->email} has been promoted to Admin.");

        return 0;
    }
}
