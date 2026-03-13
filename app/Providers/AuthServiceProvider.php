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
        \App\Models\ParentChildLink::class => \App\Policies\ParentChildLinkPolicy::class,
        \App\Models\DisciplineReport::class => \App\Policies\DisciplineReportPolicy::class,
        \App\Models\ReportCategory::class => \App\Policies\ReportCategoryPolicy::class,
        \App\Models\StaffPosition::class => \App\Policies\StaffPositionPolicy::class,
        \App\Models\BoardMember::class => \App\Policies\BoardMemberPolicy::class,
        \App\Models\CommunityQuestion::class => \App\Policies\CommunityQuestionPolicy::class,
        \App\Models\CommunityResponse::class => \App\Policies\CommunityResponsePolicy::class,
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

        Gate::define('view-all-community-updates', function ($user) {
            return $user->isAtLeastLevel(MembershipLevel::Traveler) || $user->hasRole('Admin');
        });

        $canManageUsers = function ($user) {
            return $user->hasRole('Admin') || ($user->isAtLeastRank(StaffRank::JrCrew) && ($user->isInDepartment(StaffDepartment::Quartermaster) || $user->isInDepartment(StaffDepartment::Command)));
        };

        Gate::define('manage-stowaway-users', $canManageUsers);
        Gate::define('manage-traveler-users', $canManageUsers);

        Gate::define('release-from-brig', function ($user) {
            return $user->hasRole('Admin') || ($user->isAtLeastRank(StaffRank::Officer) && ($user->isInDepartment(StaffDepartment::Quartermaster) || $user->isInDepartment(StaffDepartment::Command)));
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
            return $user->isAtLeastLevel(MembershipLevel::Stowaway) && ! $user->in_brig && $user->parent_allows_discord;
        });

        Gate::define('view-parent-portal', function ($user) {
            return $user->isAdult() || $user->children()->exists();
        });

        Gate::define('link-minecraft-account', function ($user) {
            return $user->isAtLeastLevel(MembershipLevel::Traveler) && ! $user->in_brig && $user->parent_allows_minecraft;
        });

        Gate::define('view-acp', function ($user) {
            return $user->isAdmin()
                || $user->isAtLeastRank(StaffRank::CrewMember)
                || $user->hasRole('Page Editor')
                || $user->isInDepartment(StaffDepartment::Engineer);
        });

        $canViewLogs = function ($user) {
            return $user->isAdmin()
                || $user->isAtLeastRank(StaffRank::Officer)
                || $user->isInDepartment(StaffDepartment::Engineer);
        };

        Gate::define('view-mc-command-log', $canViewLogs);
        Gate::define('view-discord-api-log', $canViewLogs);
        Gate::define('view-activity-log', $canViewLogs);
        Gate::define('view-discipline-report-log', $canViewLogs);

        Gate::define('edit-staff-bio', function ($user) {
            return $user->isAtLeastRank(StaffRank::CrewMember) || $user->is_board_member;
        });

        Gate::define('board-member', function ($user) {
            return $user->is_board_member;
        });

        Gate::define('view-user-discipline-reports', function ($user, $targetUser) {
            return $user->hasRole('Admin')
                || $user->isAtLeastRank(StaffRank::JrCrew)
                || $user->id === $targetUser->id
                || $user->children()->where('child_user_id', $targetUser->id)->exists();
        });

        Gate::define('manage-discipline-reports', function ($user) {
            return $user->hasRole('Admin') || $user->isAtLeastRank(StaffRank::JrCrew);
        });

        Gate::define('publish-discipline-reports', function ($user) {
            return $user->hasRole('Admin') || $user->isAtLeastRank(StaffRank::Officer);
        });

        Gate::define('manage-site-config', function ($user) {
            return $user->hasRole('Admin') || $user->isAtLeastRank(StaffRank::Officer);
        });

        Gate::define('view-command-dashboard', function ($user) {
            return $user->isAdmin() || $user->isInDepartment(StaffDepartment::Command);
        });

        // Documentation visibility gates
        Gate::define('view-docs-users', function ($user) {
            return ! $user->in_brig;
        });

        Gate::define('view-docs-resident', function ($user) {
            return ! $user->in_brig && $user->isAtLeastLevel(MembershipLevel::Resident);
        });

        Gate::define('view-docs-citizen', function ($user) {
            return ! $user->in_brig && $user->isAtLeastLevel(MembershipLevel::Citizen);
        });

        Gate::define('view-docs-staff', function ($user) {
            return ! $user->in_brig && ($user->isAtLeastRank(StaffRank::JrCrew) || $user->hasRole('Admin'));
        });

        Gate::define('view-docs-officer', function ($user) {
            return ! $user->in_brig && ($user->isAtLeastRank(StaffRank::Officer) || $user->hasRole('Admin'));
        });

        Gate::define('edit-docs', function ($user) {
            return $user->hasRole('Admin') || $user->isAtLeastRank(StaffRank::Officer);
        });

        Gate::define('lock-topic', function ($user) {
            return $user->hasRole('Admin') || $user->isAtLeastRank(StaffRank::Officer);
        });

        // Community Questions & Stories
        Gate::define('view-community-stories', function ($user) {
            return ! $user->in_brig && $user->isAtLeastLevel(MembershipLevel::Traveler);
        });

        Gate::define('submit-community-response', function ($user) {
            return ! $user->in_brig && $user->isAtLeastLevel(MembershipLevel::Traveler);
        });

        Gate::define('suggest-community-question', function ($user) {
            return ! $user->in_brig && $user->isAtLeastLevel(MembershipLevel::Citizen);
        });

        Gate::define('manage-community-stories', function ($user) {
            return $user->hasRole('Admin')
                || ($user->isAtLeastRank(StaffRank::Officer) && $user->isInDepartment(StaffDepartment::Command))
                || ($user->isAtLeastRank(StaffRank::JrCrew) && $user->isInDepartment(StaffDepartment::Chaplain));
        });
    }
}
