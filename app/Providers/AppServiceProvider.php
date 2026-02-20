<?php

namespace App\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->isLocal()) {
            $this->app->bind(
                \App\Services\MinecraftRconService::class,
                \App\Services\FakeMinecraftRconService::class,
            );
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(
            Login::class,
            \App\Listeners\UpdateUserLastLogin::class,
        );

        // Register custom notification channel
        app()->bind('minecraft', function () {
            return new \App\Channels\MinecraftChannel;
        });
    }
}
