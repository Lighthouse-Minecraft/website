<?php

declare(strict_types=1);

use App\Models\User;
use App\Policies\AnnouncementPolicy;
use App\Policies\BoardMemberPolicy;
use App\Policies\CommunityQuestionPolicy;
use App\Policies\MeetingNotePolicy;
use App\Policies\MeetingPolicy;
use App\Policies\MessagePolicy;
use App\Policies\PagePolicy;
use App\Policies\PrayerCountryPolicy;
use App\Policies\ReportCategoryPolicy;
use App\Policies\StaffApplicationPolicy;
use App\Policies\StaffPositionPolicy;
use App\Policies\TaskPolicy;

uses()->group('policies', 'before-hooks');

dataset('policies with admin-only before hooks', [
    'AnnouncementPolicy' => fn () => new AnnouncementPolicy,
    'BoardMemberPolicy' => fn () => new BoardMemberPolicy,
    'MeetingPolicy' => fn () => new MeetingPolicy,
    'MeetingNotePolicy' => fn () => new MeetingNotePolicy,
    'MessagePolicy' => fn () => new MessagePolicy,
    'PagePolicy' => fn () => new PagePolicy,
    'PrayerCountryPolicy' => fn () => new PrayerCountryPolicy,
    'StaffApplicationPolicy' => fn () => new StaffApplicationPolicy,
    'StaffPositionPolicy' => fn () => new StaffPositionPolicy,
    'TaskPolicy' => fn () => new TaskPolicy,
]);

it('admin returns true from before hook', function ($policy) {
    $admin = User::factory()->admin()->create();

    expect($policy->before($admin, 'viewAny'))->toBeTrue();
})->with('policies with admin-only before hooks');

it('regular user returns null from before hook', function ($policy) {
    $user = User::factory()->create();

    expect($policy->before($user, 'viewAny'))->toBeNull();
})->with('policies with admin-only before hooks');

it('command officer returns null from before hook (no longer bypasses)', function ($policy) {
    $officer = officerCommand();

    expect($policy->before($officer, 'viewAny'))->toBeNull();
})->with('policies with admin-only before hooks');

// ReportCategoryPolicy and CommunityQuestionPolicy have special before() that skips delete
it('report category policy before hook skips delete ability', function () {
    $admin = User::factory()->admin()->create();
    $policy = new ReportCategoryPolicy;

    expect($policy->before($admin, 'viewAny'))->toBeTrue()
        ->and($policy->before($admin, 'delete'))->toBeNull();
});

it('community question policy before hook skips delete ability', function () {
    $admin = User::factory()->admin()->create();
    $policy = new CommunityQuestionPolicy;

    expect($policy->before($admin, 'viewAny'))->toBeTrue()
        ->and($policy->before($admin, 'delete'))->toBeNull();
});
