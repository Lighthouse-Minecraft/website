<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;

class UpdateUserLastLogin
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        if ($event->user instanceof Model) {
            $event->user->update([
                'last_login_at' => now(),
            ]);
        }
    }
}
