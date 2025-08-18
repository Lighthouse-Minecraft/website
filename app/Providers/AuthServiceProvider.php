<?php

namespace App\Providers;

use App\Enums\MembershipLevel;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // \App\Models\SomeModel::class => \App\Policies\SomeModelPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        Gate::define('view-community-updates', function ($user) {
            return $user->isAtLeastLevel(MembershipLevel::Traveler) || $user->hasRole('Admin');
        });
    }
}
