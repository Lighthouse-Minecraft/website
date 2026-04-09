<?php

declare(strict_types=1);

use App\Actions\AddRuleToDraft;
use App\Actions\ApproveAndPublishVersion;
use App\Actions\CreateRuleVersion;
use App\Actions\DeactivateRuleInDraft;
use App\Actions\RejectDraftVersion;
use App\Actions\SubmitVersionForApproval;
use App\Models\Rule;
use App\Models\RuleCategory;
use App\Models\User;
use App\Notifications\RulesVersionPublishedNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;

uses()->group('rules', 'actions', 'approval');

// == SubmitVersionForApproval == //

it('transitions a draft version to submitted', function () {
    $user = User::factory()->create();
    $draft = CreateRuleVersion::run($user);

    SubmitVersionForApproval::run($draft, $user);

    expect($draft->fresh()->status)->toBe('submitted');
});

it('throws if trying to submit a non-draft version', function () {
    $user = User::factory()->create();
    $draft = CreateRuleVersion::run($user);
    $draft->status = 'submitted';
    $draft->save();

    expect(fn () => SubmitVersionForApproval::run($draft, $user))
        ->toThrow(AuthorizationException::class);
});

// == ApproveAndPublishVersion == //

it('transitions a submitted version to published', function () {
    $creator = User::factory()->create();
    $approver = User::factory()->create();
    $draft = CreateRuleVersion::run($creator);
    $draft->status = 'submitted';
    $draft->save();

    ApproveAndPublishVersion::run($draft, $approver);

    $version = $draft->fresh();
    expect($version->status)->toBe('published')
        ->and($version->approved_by_user_id)->toBe($approver->id)
        ->and($version->published_at)->not->toBeNull();
});

it('activates draft rules in the version when approved', function () {
    $creator = User::factory()->create();
    $approver = User::factory()->create();
    $draft = CreateRuleVersion::run($creator);

    $category = RuleCategory::first();
    $newRule = AddRuleToDraft::run($draft, $category, 'New Rule', 'Description.', $creator);

    $draft->status = 'submitted';
    $draft->save();

    ApproveAndPublishVersion::run($draft, $approver);

    expect($newRule->fresh()->status)->toBe('active');
});

it('deactivates rules marked for deactivation when approved', function () {
    $creator = User::factory()->create();
    $approver = User::factory()->create();
    $draft = CreateRuleVersion::run($creator);

    $activeRule = Rule::where('status', 'active')->first();
    DeactivateRuleInDraft::run($draft, $activeRule);

    $draft->status = 'submitted';
    $draft->save();

    ApproveAndPublishVersion::run($draft, $approver);

    expect($activeRule->fresh()->status)->toBe('inactive');
});

it('throws if the approver is the same as the creator', function () {
    $creator = User::factory()->create();
    $draft = CreateRuleVersion::run($creator);
    $draft->status = 'submitted';
    $draft->save();

    expect(fn () => ApproveAndPublishVersion::run($draft, $creator))
        ->toThrow(AuthorizationException::class);
});

it('throws if trying to approve a non-submitted version', function () {
    $creator = User::factory()->create();
    $approver = User::factory()->create();
    $draft = CreateRuleVersion::run($creator);
    // still in draft status

    expect(fn () => ApproveAndPublishVersion::run($draft, $approver))
        ->toThrow(AuthorizationException::class);
});

it('fires re-agreement notification for all active users when approved', function () {
    Notification::fake();

    $stowaway = User::factory()->create(['membership_level' => \App\Enums\MembershipLevel::Stowaway]);
    $traveler = User::factory()->create(['membership_level' => \App\Enums\MembershipLevel::Traveler]);
    $creator = User::factory()->create();
    $approver = User::factory()->create();

    $draft = CreateRuleVersion::run($creator);
    $draft->status = 'submitted';
    $draft->save();

    ApproveAndPublishVersion::run($draft, $approver);

    Notification::assertSentTo($stowaway, RulesVersionPublishedNotification::class);
    Notification::assertSentTo($traveler, RulesVersionPublishedNotification::class);
});

// == RejectDraftVersion == //

it('transitions a submitted version back to draft', function () {
    $creator = User::factory()->create();
    $approver = User::factory()->create();
    $draft = CreateRuleVersion::run($creator);
    $draft->status = 'submitted';
    $draft->save();

    RejectDraftVersion::run($draft, $approver, 'Please fix the wording on rule 3.');

    $version = $draft->fresh();
    expect($version->status)->toBe('draft')
        ->and($version->rejection_note)->toBe('Please fix the wording on rule 3.');
});

it('throws if trying to reject a non-submitted version', function () {
    $creator = User::factory()->create();
    $approver = User::factory()->create();
    $draft = CreateRuleVersion::run($creator);
    // still in draft status

    expect(fn () => RejectDraftVersion::run($draft, $approver, 'Some note.'))
        ->toThrow(AuthorizationException::class);
});
