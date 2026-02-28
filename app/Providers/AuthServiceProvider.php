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
        \App\Models\Thread::class => \App\Policies\ThreadPolicy::class,
        \App\Models\Message::class => \App\Policies\MessagePolicy::class,
        \App\Models\DiscordAccount::class => \App\Policies\DiscordAccountPolicy::class,
    ];

    /**
     * Register authentication and authorization services and define application authorization gates.
     *
     * Registers the class's policy mappings and defines gates that control access to community content and updates,
     * manage stowaway/traveler user administration, and view various ready-room sections based on user roles, ranks,
     * membership levels, and departments.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        Gate::define('view-community-content', function ($user) {
            return ! $user->in_brig;
        });

        Gate::define('view-community-updates', function ($user) {
            return $user->isAtLeastLevel(MembershipLevel::Traveler) || $user->hasRole('Admin');
        });

        Gate::define('manage-stowaway-users', function ($user) {
            return $user->hasRole('Admin') || $user->isAtLeastRank(StaffRank::Officer) || ($user->isAtLeastRank(StaffRank::CrewMember) && $user->isInDepartment(StaffDepartment::Quartermaster));
        });

        Gate::define('manage-traveler-users', function ($user) {
            return $user->hasRole('Admin') || $user->isAtLeastRank(StaffRank::Officer) || ($user->isAtLeastRank(StaffRank::CrewMember) && $user->isInDepartment(StaffDepartment::Quartermaster));
        });

        Gate::define('view-ready-room', function ($user) {
            return $user->hasRole('Admin') || $user->isAtLeastRank(StaffRank::JrCrew);
        });

        Gate::define('view-ready-room-command', function ($user) {
            return $user->hasRole('Admin') || $user->isAtLeastRank(StaffRank::Officer) || ($user->isAtLeastRank(StaffRank::JrCrew) && $user->isInDepartment(StaffDepartment::Command));
        });

        Gate::define('view-ready-room-chaplain', function ($user) {
            return $user->hasRole('Admin') || $user->isAtLeastRank(StaffRank::Officer) || ($user->isAtLeastRank(StaffRank::JrCrew) && $user->isInDepartment(StaffDepartment::Chaplain));
        });

        Gate::define('view-ready-room-engineer', function ($user) {
            return $user->hasRole('Admin') || $user->isAtLeastRank(StaffRank::Officer) || ($user->isAtLeastRank(StaffRank::JrCrew) && $user->isInDepartment(StaffDepartment::Engineer));
        });

        Gate::define('view-ready-room-quartermaster', function ($user) {
            return $user->hasRole('Admin') || $user->isAtLeastRank(StaffRank::Officer) || ($user->isAtLeastRank(StaffRank::JrCrew) && $user->isInDepartment(StaffDepartment::Quartermaster));
        });

        Gate::define('view-ready-room-steward', function ($user) {
            return $user->hasRole('Admin') || $user->isAtLeastRank(StaffRank::Officer) || ($user->isAtLeastRank(StaffRank::JrCrew) && $user->isInDepartment(StaffDepartment::Steward));
        });

        Gate::define('link-discord', function ($user) {
            return $user->isAtLeastLevel(MembershipLevel::Traveler) && ! $user->in_brig;
        });

        Gate::define('view-acp', function ($user) {
            return $user->isAdmin()
                || $user->isAtLeastRank(StaffRank::Officer)
                || $user->hasRole('Page Editor')
                || $user->isInDepartment(StaffDepartment::Engineer);
        });

        $canViewLogs = function ($user) {
            return $user->isAdmin()
                || $user->isAtLeastRank(StaffRank::Officer)
                || $user->isInDepartment(StaffDepartment::Engineer);
        };

        Gate::define('view-mc-command-log', $canViewLogs);
        Gate::define('view-activity-log', $canViewLogs);
    }
}
