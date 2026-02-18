<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

class UpdateUserLastLogin
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        $event->user->update([
            'last_login_at' => now(),
        ]);
    }
}
