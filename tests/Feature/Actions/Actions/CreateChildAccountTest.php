<?php

declare(strict_types=1);

use App\Actions\CreateChildAccount;
use App\Models\ParentChildLink;
use App\Models\User;
use App\Notifications\ChildWelcomeNotification;
use Illuminate\Support\Facades\Notification;

uses()->group('parent-portal', 'actions');

it('creates a child user account linked to parent', function () {
    Notification::fake();

    $parent = User::factory()->adult()->create();

    $child = CreateChildAccount::run($parent, 'TestChild', 'child@example.com', now()->subYears(14)->format('Y-m-d'));

    expect($child)->toBeInstanceOf(User::class)
        ->and($child->name)->toBe('TestChild')
        ->and($child->email)->toBe('child@example.com')
        ->and($child->parent_email)->toBe($parent->email);

    expect(ParentChildLink::where('parent_user_id', $parent->id)->where('child_user_id', $child->id)->exists())->toBeTrue();
});

it('sets restrictive defaults for under-13 child', function () {
    Notification::fake();

    $parent = User::factory()->adult()->create();
    $dob = now()->subYears(10)->format('Y-m-d');

    $child = CreateChildAccount::run($parent, 'YoungChild', 'young@example.com', $dob);

    expect($child->parent_allows_site)->toBeTrue()
        ->and($child->parent_allows_minecraft)->toBeFalse()
        ->and($child->parent_allows_discord)->toBeFalse();
});

it('sets permissive defaults for 13+ child', function () {
    Notification::fake();

    $parent = User::factory()->adult()->create();
    $dob = now()->subYears(15)->format('Y-m-d');

    $child = CreateChildAccount::run($parent, 'TeenChild', 'teen@example.com', $dob);

    expect($child->parent_allows_site)->toBeTrue()
        ->and($child->parent_allows_minecraft)->toBeTrue()
        ->and($child->parent_allows_discord)->toBeTrue();
});

it('sends a welcome notification to the child', function () {
    Notification::fake();

    $parent = User::factory()->adult()->create();

    $child = CreateChildAccount::run($parent, 'WelcomeChild', 'welcome@example.com', now()->subYears(14)->format('Y-m-d'));

    Notification::assertSentTo($child, ChildWelcomeNotification::class);
});

it('records activity for child account creation', function () {
    Notification::fake();

    $parent = User::factory()->adult()->create();
    $child = CreateChildAccount::run($parent, 'ActivityChild', 'activity@example.com', now()->subYears(14)->format('Y-m-d'));

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => User::class,
        'subject_id' => $child->id,
        'action' => 'child_account_created',
    ]);
});
