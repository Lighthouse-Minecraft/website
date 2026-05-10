<?php

declare(strict_types=1);

use App\Actions\AttachBackgroundCheckDocuments;
use App\Enums\BackgroundCheckStatus;
use App\Models\BackgroundCheck;
use App\Models\BackgroundCheckDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

uses()->group('background-checks', 'livewire', 'profile');

beforeEach(function () {
    Storage::fake(config('filesystems.public_disk'));
});

// === Visibility / Access ===

it('shows the card to a user with background-checks-view role', function () {
    $viewer = User::factory()->withRole('Background Checks - View')->create();
    $target = User::factory()->create();
    loginAs($viewer);

    $this->get(route('profile.show', $target))
        ->assertSeeLivewire('users.background-checks-card');
});

it('shows the card to a user viewing their own profile (self-visibility)', function () {
    $user = User::factory()->create();
    loginAs($user);

    $this->get(route('profile.show', $user))
        ->assertSeeLivewire('users.background-checks-card');
});

it('hides the card from users without background-checks-view and not own profile', function () {
    $viewer = User::factory()->create();
    $target = User::factory()->create();
    loginAs($viewer);

    $this->get(route('profile.show', $target))
        ->assertDontSeeLivewire('users.background-checks-card');
});

// === Check list display ===

it('displays check records with service, date, status, and run-by', function () {
    $viewer = User::factory()->withRole('Background Checks - View')->create();
    $target = User::factory()->create();
    $check = BackgroundCheck::factory()->create([
        'user_id' => $target->id,
        'run_by_user_id' => $viewer->id,
        'service' => 'Checkr Services',
        'completed_date' => '2024-03-15',
        'status' => BackgroundCheckStatus::Pending,
    ]);

    loginAs($viewer);

    $this->get(route('profile.show', $target))
        ->assertSee('Checkr Services')
        ->assertSee('Mar 15, 2024')
        ->assertSee('Pending')
        ->assertSee('Run by');
});

it('displays document download links for attached PDFs', function () {
    $viewer = User::factory()->withRole('Background Checks - View')->create();
    $target = User::factory()->create();
    $check = BackgroundCheck::factory()->create(['user_id' => $target->id]);
    $file = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');
    AttachBackgroundCheckDocuments::run($check, [$file], $viewer);

    loginAs($viewer);

    $this->get(route('profile.show', $target))
        ->assertSee('report.pdf');
});

it('shows empty state when no checks exist', function () {
    $viewer = User::factory()->withRole('Background Checks - View')->create();
    $target = User::factory()->create();
    loginAs($viewer);

    $this->get(route('profile.show', $target))
        ->assertSee('No background check records on file.');
});

// === Renewal badges (view permission required) ===

it('shows overdue badge when user has no Passed check and viewer has background-checks-view', function () {
    $viewer = User::factory()->withRole('Background Checks - View')->create();
    $target = User::factory()->create();
    loginAs($viewer);

    Volt::test('users.background-checks-card', ['user' => $target])
        ->assertSee('Overdue');
});

it('shows due soon badge when most recent Passed check expires within 90 days', function () {
    $viewer = User::factory()->withRole('Background Checks - View')->create();
    $target = User::factory()->create();
    BackgroundCheck::factory()->passed()->create([
        'user_id' => $target->id,
        'completed_date' => now()->subYears(2)->addDays(89)->toDateString(),
    ]);

    loginAs($viewer);

    Volt::test('users.background-checks-card', ['user' => $target])
        ->assertSee('Due Soon');
});

it('does not show renewal badge to own-profile viewer without background-checks-view', function () {
    $user = User::factory()->create();
    loginAs($user);

    Volt::test('users.background-checks-card', ['user' => $user])
        ->assertDontSee('Overdue')
        ->assertDontSee('Due Soon')
        ->assertDontSee('Waived');
});

// === Manage actions ===

it('shows Add Check button to manage users', function () {
    $manager = User::factory()->withRole('Background Checks - Manage')->create();
    $target = User::factory()->create();
    loginAs($manager);

    Volt::test('users.background-checks-card', ['user' => $target])
        ->assertSee('Add Check');
});

it('hides Add Check button from view-only users', function () {
    $viewer = User::factory()->withRole('Background Checks - View')->create();
    $target = User::factory()->create();
    loginAs($viewer);

    Volt::test('users.background-checks-card', ['user' => $target])
        ->assertDontSee('Add Check');
});

it('creates a new background check via submitNewCheck', function () {
    $manager = User::factory()->withRole('Background Checks - Manage')->create();
    $target = User::factory()->create();
    loginAs($manager);

    Volt::test('users.background-checks-card', ['user' => $target])
        ->set('newService', 'Sterling Volunteers')
        ->set('newCompletedDate', '2024-01-01')
        ->call('submitNewCheck')
        ->assertHasNoErrors();

    expect(BackgroundCheck::where('user_id', $target->id)->where('service', 'Sterling Volunteers')->exists())->toBeTrue();
});

it('validates required fields on submitNewCheck', function () {
    $manager = User::factory()->withRole('Background Checks - Manage')->create();
    $target = User::factory()->create();
    loginAs($manager);

    Volt::test('users.background-checks-card', ['user' => $target])
        ->call('submitNewCheck')
        ->assertHasErrors(['newService', 'newCompletedDate']);
});

it('updates status on a non-terminal check via submitUpdate', function () {
    $manager = User::factory()->withRole('Background Checks - Manage')->create();
    $target = User::factory()->create();
    $check = BackgroundCheck::factory()->create(['user_id' => $target->id]);
    loginAs($manager);

    Volt::test('users.background-checks-card', ['user' => $target])
        ->set('updateCheckId', $check->id)
        ->set('pendingStatusValue', 'passed')
        ->call('submitUpdate')
        ->assertHasNoErrors();

    expect($check->fresh()->status)->toBe(BackgroundCheckStatus::Passed);
});

it('adds a note to any check via submitUpdate', function () {
    $manager = User::factory()->withRole('Background Checks - Manage')->create();
    $target = User::factory()->create();
    $check = BackgroundCheck::factory()->passed()->create(['user_id' => $target->id]);
    loginAs($manager);

    Volt::test('users.background-checks-card', ['user' => $target])
        ->set('updateCheckId', $check->id)
        ->set('updateNote', 'Reviewed and confirmed valid.')
        ->call('submitUpdate')
        ->assertHasNoErrors();

    expect($check->fresh()->notes)->toContain('Reviewed and confirmed valid.');
});

it('uploads a PDF document to a check via submitUpdate', function () {
    $manager = User::factory()->withRole('Background Checks - Manage')->create();
    $target = User::factory()->create();
    $check = BackgroundCheck::factory()->create(['user_id' => $target->id]);
    loginAs($manager);

    $file = UploadedFile::fake()->create('check.pdf', 100, 'application/pdf');

    Volt::test('users.background-checks-card', ['user' => $target])
        ->set('updateCheckId', $check->id)
        ->set('pendingDocument', $file)
        ->call('submitUpdate')
        ->assertHasNoErrors();

    expect(BackgroundCheckDocument::where('background_check_id', $check->id)->count())->toBe(1);
});

it('deletes a document from a non-terminal check via deleteDocument', function () {
    $manager = User::factory()->withRole('Background Checks - Manage')->create();
    $target = User::factory()->create();
    $check = BackgroundCheck::factory()->create(['user_id' => $target->id]);
    $file = UploadedFile::fake()->create('check.pdf', 100, 'application/pdf');
    AttachBackgroundCheckDocuments::run($check, [$file], $manager);
    $doc = BackgroundCheckDocument::where('background_check_id', $check->id)->first();

    loginAs($manager);

    Volt::test('users.background-checks-card', ['user' => $target])
        ->call('deleteDocument', $doc->id)
        ->assertHasNoErrors();

    expect(BackgroundCheckDocument::find($doc->id))->toBeNull();
});

it('blocks view-only users from calling manage methods', function () {
    $viewer = User::factory()->withRole('Background Checks - View')->create();
    $target = User::factory()->create();
    $check = BackgroundCheck::factory()->create(['user_id' => $target->id]);
    loginAs($viewer);

    Volt::test('users.background-checks-card', ['user' => $target])
        ->call('submitNewCheck')
        ->assertForbidden();
});
