<?php

namespace App\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register application service bindings and environment-specific overrides.
     *
     * When the application is running in the local environment, binds
     * App\Services\MinecraftRconService to App\Services\FakeMinecraftRconService
     * so the fake implementation is resolved where the real service is requested.
     */
    public function register(): void
    {
        if ($this->app->isLocal()) {
            $this->app->bind(
                \App\Services\MinecraftRconService::class,
                \App\Services\FakeMinecraftRconService::class,
            );
            $this->app->bind(
                \App\Services\DiscordApiService::class,
                \App\Services\FakeDiscordApiService::class,
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
        Notification::extend('minecraft', function ($app) {
            return new \App\Channels\MinecraftChannel;
        });

        // Register Socialite Discord provider
        Event::listen(
            \SocialiteProviders\Manager\SocialiteWasCalled::class,
            [\SocialiteProviders\Discord\DiscordExtendSocialite::class, 'handle']
        );
    }
}
