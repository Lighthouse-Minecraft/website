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
        \App\Models\StaffApplication::class => \App\Policies\StaffApplicationPolicy::class,
        \App\Models\BlogPost::class => \App\Policies\BlogPostPolicy::class,
        \App\Models\Credential::class => \App\Policies\CredentialPolicy::class,
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
            return $user->hasRole('Membership Level - Manager');
        };

        Gate::define('manage-stowaway-users', $canManageUsers);
        Gate::define('manage-traveler-users', $canManageUsers);

        Gate::define('put-in-brig', function ($user) {
            return $user->hasRole('Brig Warden');
        });

        Gate::define('release-from-brig', function ($user) {
            return $user->hasRole('Brig Warden');
        });

        Gate::define('view-ready-room', function ($user) {
            return $user->hasRole('Staff Access');
        });

        Gate::define('view-ready-room-command', function ($user) {
            return $user->isInDepartment(StaffDepartment::Command) || $user->hasRole('Ready Room - View All');
        });

        Gate::define('view-ready-room-chaplain', function ($user) {
            return $user->isInDepartment(StaffDepartment::Chaplain) || $user->hasRole('Ready Room - View All');
        });

        Gate::define('view-ready-room-engineer', function ($user) {
            return $user->isInDepartment(StaffDepartment::Engineer) || $user->hasRole('Ready Room - View All');
        });

        Gate::define('view-ready-room-quartermaster', function ($user) {
            return $user->isInDepartment(StaffDepartment::Quartermaster) || $user->hasRole('Ready Room - View All');
        });

        Gate::define('view-ready-room-steward', function ($user) {
            return $user->isInDepartment(StaffDepartment::Steward) || $user->hasRole('Ready Room - View All');
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
            return $user->hasRole('Staff Access');
        });

        $canViewLogs = function ($user) {
            return $user->hasRole('Logs - Viewer');
        };

        Gate::define('view-mc-command-log', $canViewLogs);
        Gate::define('view-discord-api-log', $canViewLogs);
        Gate::define('view-activity-log', $canViewLogs);
        Gate::define('view-credential-access-log', $canViewLogs);
        Gate::define('view-discipline-report-log', function ($user) use ($canViewLogs) {
            return $canViewLogs($user) || $user->hasRole('Discipline Report - Manager');
        });

        Gate::define('edit-staff-bio', function ($user) {
            return $user->hasRole('Staff Access') || $user->is_board_member;
        });

        Gate::define('board-member', function ($user) {
            return $user->is_board_member;
        });

        Gate::define('view-user-discipline-reports', function ($user, $targetUser) {
            return $user->hasRole('Staff Access')
                || $user->id === $targetUser->id
                || $user->children()->where('child_user_id', $targetUser->id)->exists();
        });

        Gate::define('manage-discipline-reports', function ($user) {
            return $user->hasRole('Discipline Report - Manager');
        });

        Gate::define('publish-discipline-reports', function ($user) {
            return $user->hasRole('Discipline Report - Publisher');
        });

        Gate::define('manage-site-config', function ($user) {
            return $user->hasRole('Site Config - Manager');
        });

        Gate::define('view-command-dashboard', function ($user) {
            return $user->hasRole('Command Dashboard - Viewer');
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
            return ! $user->in_brig && $user->hasRole('Staff Access');
        });

        Gate::define('view-docs-officer', function ($user) {
            return ! $user->in_brig && $user->hasRole('Officer Docs - Viewer');
        });

        Gate::define('edit-docs', function ($user) {
            return app()->environment('local');
        });

        Gate::define('lock-topic', function ($user) {
            return $user->hasRole('Moderator');
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
            return $user->hasRole('Community Stories - Manager');
        });

        // Staff Applications
        Gate::define('review-staff-applications', function ($user, $application = null) {
            if ($user->isAdmin() || $user->hasRole('Applicant Review - All')) {
                return true;
            }

            if ($user->hasRole('Applicant Review - Department')) {
                // When no application is provided (route middleware), allow access to the list
                if ($application === null) {
                    return true;
                }

                // When an application is provided, check department match
                return $application->staffPosition
                    && $user->staff_department === $application->staffPosition->department;
            }

            return false;
        });

        Gate::define('manage-application-questions', function ($user) {
            return $user->hasRole('Site Config - Manager');
        });

        Gate::define('receive-ticket-escalations', function ($user) {
            return $user->hasRole('Ticket Escalation - Receiver');
        });

        Gate::define('manage-blog', function ($user) {
            return $user->hasRole('Blog - Author');
        });

        Gate::define('post-blog-comment', function ($user) {
            return ! $user->in_brig && $user->isAtLeastLevel(MembershipLevel::Traveler);
        });

        Gate::define('flag-blog-comment', function ($user) {
            return ! $user->in_brig && $user->isAtLeastLevel(MembershipLevel::Traveler);
        });

        Gate::define('moderate-blog-comments', function ($user) {
            return $user->hasRole('Moderator') || $user->hasRole('Blog - Author');
        });

        Gate::define('view-contact-inquiries', function ($user) {
            return $user->hasRole('Contact - Receive Submissions');
        });

        // Finance gates (tiered: Manage > Record > View)
        Gate::define('finance-view', function ($user) {
            return $user->hasRole('Finance - View')
                || $user->hasRole('Finance - Record')
                || $user->hasRole('Finance - Manage');
        });

        Gate::define('finance-record', function ($user) {
            return $user->hasRole('Finance - Record')
                || $user->hasRole('Finance - Manage');
        });

        Gate::define('finance-manage', function ($user) {
            return $user->hasRole('Finance - Manage');
        });

        // Community finance view — Resident+ members can see closed period summaries
        Gate::define('finance-community-view', function ($user) {
            return $user->isAdmin() || (! $user->in_brig && $user->isAtLeastLevel(MembershipLevel::Resident));
        });

        Gate::define('view-staff-activity', function ($user, $targetUser) {
            // The staff member themselves
            if ($user->id === $targetUser->id) {
                return true;
            }

            // Command officers and department leads (Officer rank in any department)
            return $user->isAtLeastRank(StaffRank::Officer);
        });

        // Vault gates
        Gate::define('manage-vault', function ($user) {
            return $user->hasRole('Vault Manager');
        });

        Gate::define('view-vault', function ($user) {
            return $user->isAtLeastRank(StaffRank::JrCrew);
        });

        // Rules gates
        Gate::define('rules.manage', function ($user) {
            return $user->hasRole('Rules - Manage');
        });

        Gate::define('rules.approve', function ($user) {
            return $user->hasRole('Rules - Approve');
        });
    }
}
