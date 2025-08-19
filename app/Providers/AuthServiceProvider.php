<?php

namespace App\Providers;

use App\Enums\MembershipLevel;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
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

        Gate::define('manage-stowaway-users', function ($user) {
            return $user->hasRole('Admin') || $user->isAtLeastRank(StaffRank::Officer) || ($user->isAtLeastRank(StaffRank::CrewMember) && $user->isInDepartment(StaffDepartment::Quartermaster));
        });

        Gate::define('view-ready-room', function ($user) {
            return $user->hasRole('Admin') || $user->isAtLeastRank(StaffRank::JrCrew);
        });
    }
}
