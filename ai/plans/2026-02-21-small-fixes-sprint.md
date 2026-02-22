# Plan: Small Fixes Sprint

**Date**: 2026-02-21
**Planned by**: Claude Code
**Status**: APPROVED

## Summary

A one-day sprint addressing accumulated UX issues and live production bugs. Covers mobile
layout on the ticket view page, markdown link support in ticket messages, crew member
access to join meetings, an improved meeting minutes viewer in the ready room, a
"schedule next meeting" modal after meeting completion, and three Bedrock verification
bugs (dot-prefix username mismatch, notification channel error, and missing kick on
verification expiry). Also adds server connection info to the verification UI.

**Issues closed:** #206, #208, #187, #216
**Branch:** `small-fixes-sprint` (from `staging`)

## Files to Read (for implementing agent context)

- `CLAUDE.md`
- `ai/CONVENTIONS.md`
- `ai/ARCHITECTURE.md`
- `resources/views/livewire/ready-room/tickets/view-ticket.blade.php`
- `resources/views/livewire/meetings/manage-meeting.blade.php`
- `resources/views/livewire/meeting/notes-display.blade.php`
- `app/Livewire/Meeting/NotesDisplay.php`
- `app/Policies/MeetingNotePolicy.php`
- `app/Policies/MeetingPolicy.php`
- `app/Actions/GenerateVerificationCode.php`
- `app/Actions/CompleteVerification.php`
- `app/Console/Commands/CleanupExpiredVerifications.php`
- `app/Providers/AppServiceProvider.php`
- `resources/views/livewire/settings/minecraft-accounts.blade.php`
- `config/lighthouse.php`
- `.env.example`
- `tests/Feature/Minecraft/GenerateVerificationCodeTest.php`
- `tests/Feature/Minecraft/CompleteVerificationTest.php`

## Authorization Rules

No new gates or policies being added. Changes to existing policy:

- `MeetingNotePolicy::create()` — restrict from CrewMember to Officer+ or Meeting Secretary
- `MeetingNotePolicy::update()` — same restriction
- `MeetingPolicy::attend()` already exists, allows CrewMember+ — no changes needed

## Database Changes

None — no migrations required.

---

## Implementation Steps

---

### Step 1: Issue #206 — Ticket View Mobile Layout
**File**: `resources/views/livewire/ready-room/tickets/view-ticket.blade.php`
**Action**: Modify — three CSS class changes only, no PHP

**Change 1** — Detail badge row (around line 523):
```blade
// Old:
<div class="mt-2 flex items-center gap-4 text-sm text-zinc-600 dark:text-zinc-400">

// New:
<div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-zinc-600 dark:text-zinc-400">
```

**Change 2** — Status/Assignment controls div (around line 540):
```blade
// Old:
<div class="flex items-center gap-4 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 p-4">

// New:
<div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 p-4">
```

**Change 3** — Reply form action row (around line 646):
```blade
// Old:
<div class="mt-4 flex items-center justify-between">

// New:
<div class="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
```

---

### Step 2: Issue #208 — Ticket Messages Markdown Link Support
**File**: `resources/views/livewire/ready-room/tickets/view-ticket.blade.php`
**Action**: Modify — replace message body renderer (around lines 596–602)

Replace the entire conditional `@if/@else/@endif` block inside the `<div class="mt-3 prose...">`:

```blade
{{-- Old: --}}
@if($message->kind === \App\Enums\MessageKind::System)
    {!! Str::markdown($message->body) !!}
@else
    {!! nl2br(e($message->body)) !!}
@endif

{{-- New (single line, handles all message kinds safely): --}}
{!! Str::markdown($message->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
```

Key notes:
- `html_input: 'strip'` prevents XSS by stripping HTML before markdown parsing.
- `allow_unsafe_links: false` blocks `javascript:` and other unsafe URL schemes.
- `[Label](https://example.com)` markdown links will render as `<a>` tags styled by existing prose classes.
- **Behavior change**: single newlines are no longer `<br>`; only double-newlines create paragraph breaks.
- Both system messages and regular messages now go through the same renderer.

---

### Step 3: Issue #187 — Crew Members Join Meeting (Policy)
**File**: `app/Policies/MeetingNotePolicy.php`
**Action**: Modify — restrict `create()` and `update()` to Officers and Meeting Secretary

Current state — both methods allow `StaffRank::CrewMember`:
```php
public function create(User $user): bool
{
    if ($user->isAtLeastRank(StaffRank::CrewMember)) {
        return true;
    }
    return false;
}

public function update(User $user, MeetingNote $meetingNote): bool
{
    if ($user->isAtLeastRank(StaffRank::CrewMember)) {
        return true;
    }
    return false;
}
```

Replace both with:
```php
public function create(User $user): bool
{
    return $user->isAtLeastRank(StaffRank::Officer) || $user->hasRole('Meeting Secretary');
}

public function update(User $user, MeetingNote $meetingNote): bool
{
    return $user->isAtLeastRank(StaffRank::Officer) || $user->hasRole('Meeting Secretary');
}
```

The `before()` method (grants blanket access to Admins) and `updateSave()` (lock-based) stay unchanged.
`StaffRank` is already imported in this file.

---

### Step 4: Issue #187 — Crew Members Join Meeting (Component)
**File**: `resources/views/livewire/meetings/manage-meeting.blade.php`
**Action**: Modify — add `joinMeeting()` method and button

**PHP section** — add imports at the top of the `<?php` block (after existing `use` statements):
```php
use Flux\Flux;
```

**PHP section** — add `joinMeeting()` method inside the `new class extends Component` body
(can go after the existing `StartMeeting()` method):
```php
public function joinMeeting(): void
{
    $this->authorize('attend', $this->meeting);

    if ($this->meeting->attendees->contains(auth()->id())) {
        Flux::toast('You are already listed as an attendee.', variant: 'warning');
        return;
    }

    $this->meeting->attendees()->attach(auth()->id(), ['added_at' => now()]);
    $this->meeting->load('attendees');
    Flux::toast('You have joined the meeting!', variant: 'success');
}
```

**Blade section** — inside the `<flux:card class="w-full lg:w-1/2 ...">` Meeting Details card,
after the `@if($meeting->status->value === 'in_progress')` manage-attendees block and before
the closing `</flux:card>`:

```blade
@if($meeting->status->value === 'in_progress')
    @can('attend', $meeting)
        @unless($meeting->attendees->contains(auth()->id()))
            <div class="mt-4">
                <flux:button wire:click="joinMeeting" variant="primary" size="sm">
                    Join Meeting
                </flux:button>
            </div>
        @endunless
    @endcan
@endif
```

Context: `MeetingPolicy::attend()` already exists and allows `StaffRank::CrewMember+`. Crew
members can already VIEW the manage page via `MeetingPolicy::view()`. The button only appears when:
- Meeting is InProgress
- User can `attend` (CrewMember+)
- User is not already an attendee

---

### Step 5: Issue #216 — Ready Room Past Meeting Minutes Modal (PHP)
**File**: `app/Livewire/Meeting/NotesDisplay.php`
**Action**: Modify — add two properties, update `selectMeeting()`, add `openFullMinutes()`

This is a traditional Livewire class (extends `Livewire\Component`), not a Volt component.

**Add imports** at the top of the file:
```php
use Flux\Flux;
```

**Add properties** (alongside existing `$selectedMeetingId` and `$selectedMeetingNote`):
```php
public ?string $selectedMeetingTitle = null;
public ?string $selectedMeetingMinutes = null;
```

**Replace `selectMeeting()`** (currently just loads the note):
```php
public function selectMeeting($meetingId): void
{
    $this->selectedMeetingId = $meetingId;

    $meeting = \App\Models\Meeting::find($meetingId);
    $this->selectedMeetingTitle = $meeting ? ($meeting->title . ' — ' . $meeting->day) : null;
    $this->selectedMeetingMinutes = $meeting?->minutes;

    $this->selectedMeetingNote = MeetingNote::with(['meeting', 'createdBy'])
        ->where('meeting_id', $meetingId)
        ->where('section_key', $this->sectionKey)
        ->first();
}
```

**Add `openFullMinutes()` method**:
```php
public function openFullMinutes(): void
{
    Flux::modal('full-meeting-minutes')->show();
}
```

---

### Step 6: Issue #216 — Ready Room Past Meeting Minutes Modal (Blade)
**File**: `resources/views/livewire/meeting/notes-display.blade.php`
**Action**: Modify — fix existing bug, add button, add modal

**Bug fix** — line 45 references `$meeting->title` and `$meeting->day` but `$meeting` is not a
component property. Replace with the new string property:
```blade
{{-- Old: --}}
<flux:heading size="lg" class="mb-4">{{ $meeting->title }} - {{ $meeting->day }}</flux:heading>

{{-- New: --}}
<flux:heading size="lg" class="mb-4">{{ $selectedMeetingTitle }}</flux:heading>
```

**Add "View Full Minutes" button** — inside the large card (`w-full lg:w-3/4`), after the
note-content block (after the `@elseif` / `@else` blocks, before `</flux:card>`):
```blade
@if($selectedMeetingId && $selectedMeetingMinutes)
    <div class="mt-4">
        <flux:button wire:click="openFullMinutes" variant="ghost" size="sm">
            View Full Meeting Minutes
        </flux:button>
    </div>
@endif
```

**Add modal** — outside and after the two-column `<div class="flex flex-col lg:flex-row gap-6">`,
before the closing `</div>` of the component root:
```blade
<flux:modal name="full-meeting-minutes" class="w-full max-w-3xl">
    <div class="space-y-6">
        <flux:heading size="lg">{{ $selectedMeetingTitle }} — Full Meeting Minutes</flux:heading>

        <div class="prose dark:prose-invert max-w-none">
            {!! nl2br(e($selectedMeetingMinutes)) !!}
        </div>

        <div class="flex justify-end">
            <flux:modal.close>
                <flux:button variant="ghost">Close</flux:button>
            </flux:modal.close>
        </div>
    </div>
</flux:modal>
```

---

### Step 7: Issue #5 — Schedule Next Meeting After Completion
**File**: `resources/views/livewire/meetings/manage-meeting.blade.php`
**Action**: Modify — add properties, helper, method, modify CompleteMeetingConfirmed, add modal

**PHP section** — add additional imports (alongside the `use Flux\Flux;` added in Step 4):
```php
use Carbon\CarbonImmutable;
use Carbon\CarbonTimeZone;
```

**PHP section** — add properties (alongside existing component properties):
```php
public string $scheduleNextTitle = '';
public string $scheduleNextDay = '';
public string $scheduleNextTime = '7:00 PM';
```

**PHP section** — add private helper method (follows the same pattern as `create-modal.blade.php`):
```php
private function parseScheduledTime(string $day, string $time): CarbonImmutable
{
    return CarbonImmutable::createFromFormat(
        'Y-m-d g:i A',
        "{$day} {$time}",
        new CarbonTimeZone('America/New_York')
    )->utc();
}
```

**PHP section** — add `scheduleNextMeeting()` method:
```php
public function scheduleNextMeeting(): void
{
    $this->authorize('create', Meeting::class);

    $this->validate([
        'scheduleNextTitle' => 'required|string|max:255',
        'scheduleNextDay'   => 'required|date',
        'scheduleNextTime'  => 'required|string',
    ]);

    $newMeeting = Meeting::create([
        'title'          => $this->scheduleNextTitle,
        'day'            => $this->scheduleNextDay,
        'scheduled_time' => $this->parseScheduledTime($this->scheduleNextDay, $this->scheduleNextTime),
    ]);

    Flux::toast('Next meeting scheduled!', variant: 'success');
    $this->modal('schedule-next-meeting')->close();
    $this->redirect(route('meeting.edit', $newMeeting), navigate: true);
}
```

**PHP section** — modify `CompleteMeetingConfirmed()`. After the existing
`$this->modal('complete-meeting-confirmation')->close();` line, add:
```php
// Pre-fill next meeting scheduler with current meeting details
$this->scheduleNextTitle = $this->meeting->title;
$this->scheduleNextTime  = $this->meeting->scheduled_time
    ->setTimezone('America/New_York')
    ->format('g:i A');
$this->modal('schedule-next-meeting')->show();
```

**Blade section** — after the closing `</div>` of the existing `<div class="w-full text-right">`
status-button block (which contains the End/Complete Meeting buttons and their modals), add:
```blade
@can('create', \App\Models\Meeting::class)
    <flux:modal name="schedule-next-meeting" class="min-w-[28rem] !text-left">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Schedule Next Meeting</flux:heading>
                <flux:text class="mt-2">Would you like to schedule the next occurrence?</flux:text>
            </div>

            <flux:input wire:model="scheduleNextTitle" label="Meeting Title" required />
            <flux:date-picker wire:model="scheduleNextDay" label="Meeting Date" required />
            <flux:input wire:model="scheduleNextTime" label="Meeting Time (Eastern Time)" required />

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Skip</flux:button>
                </flux:modal.close>
                <flux:button wire:click="scheduleNextMeeting" variant="primary">
                    Schedule Meeting
                </flux:button>
            </div>
        </div>
    </flux:modal>
@endcan
```

---

### Step 8: Bedrock Bug A — Dot-prefix Username Mismatch
**File**: `app/Actions/GenerateVerificationCode.php`
**Action**: Modify — Bedrock branch only

**Root cause**: GeyserMC API returns `gamertag: "FinalAsgard"` (no dot). The server shows the
player as `.FinalAsgard`. `CompleteVerification::handle()` does `strcasecmp("FinalAsgard", ".FinalAsgard")`
→ mismatch. Bedrock whitelist uses Floodgate UUID (not username), so that part is unaffected.

In the `else` (Bedrock) branch, replace:
```php
$playerData = $mcProfileService->getBedrockPlayerInfo($username);
...
$verifiedUsername = $playerData['gamertag'] ?? $username;
```

With:
```php
// Strip any user-entered leading dot before API lookup
$lookupUsername = ltrim($username, '.');
$playerData = $mcProfileService->getBedrockPlayerInfo($lookupUsername);
...
$verifiedUsername = $playerData['gamertag'] ?? $lookupUsername;

// Bedrock players appear as ".{gamertag}" on Floodgate servers — normalize here
if (! str_starts_with($verifiedUsername, '.')) {
    $verifiedUsername = '.' . $verifiedUsername;
}
```

After this change:
- `MinecraftVerification::minecraft_username` = `.FinalAsgard`
- `MinecraftAccount::username` = `.FinalAsgard`
- Server sends `.FinalAsgard` in the webhook → `CompleteVerification::strcasecmp` passes ✓
- Floodgate whitelist commands use `$commandId` (UUID), unaffected ✓

---

### Step 9: Bedrock Bug A — UI Hint
**File**: `resources/views/livewire/settings/minecraft-accounts.blade.php`
**Action**: Modify — add description below username input for Bedrock

In the "Link New Account" form, after the `<flux:input ... label="Minecraft Username" ...>` input,
add a conditional description:
```blade
@if($accountType === 'bedrock')
    <flux:description>
        Enter your Xbox gamertag without the dot — it will be added automatically.
    </flux:description>
@endif
```

The `flux:description` component renders as helper text below the input (standard Flux UI pattern).

---

### Step 10: Bedrock Bug A — Update Tests
**File**: `tests/Feature/Minecraft/GenerateVerificationCodeTest.php`
**Action**: Modify — update existing Bedrock test and add a second variant

The existing Bedrock test mocks `mcprofile.io` returning a gamertag WITH a dot. Update it so the
mock returns a gamertag WITHOUT a dot (more realistic for the GeyserMC path), and assert that the
stored username HAS the dot:

```php
test('generates verification code for bedrock account - normalizes dot prefix', function () {
    Http::fake([
        'api.geysermc.org/*' => Http::response([
            'xuid' => '2535428197086765',
        ]),
        // GeyserMC returns gamertag without dot
        // (McProfileService::getBedrockPlayerInfoFromGeyser uses the passed-in gamertag)
    ]);

    $result = $this->action->handle($this->user, MinecraftAccountType::Bedrock, 'BedrockPlayer');

    expect($result['success'])->toBeTrue();

    $this->assertDatabaseHas('minecraft_verifications', [
        'user_id' => $this->user->id,
        'minecraft_username' => '.BedrockPlayer', // dot was prepended
    ]);

    $this->assertDatabaseHas('minecraft_accounts', [
        'user_id' => $this->user->id,
        'username' => '.BedrockPlayer',
    ]);
});

test('bedrock verification does not double-add the dot if user enters it', function () {
    Http::fake([
        'api.geysermc.org/*' => Http::response(['xuid' => '2535428197086765']),
    ]);

    $result = $this->action->handle($this->user, MinecraftAccountType::Bedrock, '.BedrockPlayer');

    expect($result['success'])->toBeTrue();

    $this->assertDatabaseHas('minecraft_verifications', [
        'minecraft_username' => '.BedrockPlayer', // no double dot
    ]);
});
```

Note: You may need to mock the GeyserMC HTTP call properly — follow the existing test patterns
in the file for setting up Http::fake(). The GeyserMC API uses the `$lookupUsername` (with dot
stripped), and `McProfileService::getBedrockPlayerInfoFromGeyser()` returns `['gamertag' => $gamertag]`
where `$gamertag` is the passed-in value (the stripped username without dot). The dot normalization
then happens in `GenerateVerificationCode` before storing.

**File**: `tests/Feature/Minecraft/CompleteVerificationTest.php`
**Action**: Modify — add Bedrock username test

Add a test that a stored `.BedrockPlayer` username correctly matches against server-reported `.BedrockPlayer`:

```php
test('completes verification for bedrock account with dot-prefix username', function () {
    $verification = MinecraftVerification::factory()->for($this->user)->pending()->create([
        'code'               => 'BED123',
        'account_type'       => 'bedrock',
        'minecraft_username' => '.BedrockPlayer', // stored with dot
        'minecraft_uuid'     => '00000000-0000-0000-0009-01234567890a',
    ]);

    MinecraftAccount::factory()->for($this->user)->verifying()->create([
        'username'    => '.BedrockPlayer',
        'uuid'        => '00000000-0000-0000-0009-01234567890a',
        'account_type' => 'bedrock',
        'command_id'  => '00000000000000000009-01234567890a', // UUID-based
    ]);

    $result = $this->action->handle(
        'BED123',
        '.BedrockPlayer',  // server sends with dot
        '00000000-0000-0000-0009-01234567890a'
    );

    expect($result['success'])->toBeTrue();
});
```

---

### Step 11: Bedrock Bug B — Notification Channel Fix
**File**: `app/Providers/AppServiceProvider.php`
**Action**: Modify — fix custom channel registration

**Root cause**: `app()->bind('minecraft', ...)` registers in the service container, but
`ChannelManager::createDriver()` checks `$customCreators` (populated only by `Notification::extend()`)
and named `create*Driver()` methods — it does NOT fall back to `app()->make()` for unknown channel
names. This causes `InvalidArgumentException: Driver [minecraft] not supported.` in the queue worker.

**Add import** at the top of `AppServiceProvider.php`:
```php
use Illuminate\Support\Facades\Notification;
```

**In `boot()`**, replace:
```php
// Old:
app()->bind('minecraft', function () {
    return new \App\Channels\MinecraftChannel;
});

// New:
Notification::extend('minecraft', function ($app) {
    return new \App\Channels\MinecraftChannel;
});
```

No test changes needed — existing tests mock the RCON service directly and bypass the queue.

---

### Step 12: Bedrock Bug C — Kick Player on Verification Expiry
**File**: `app/Console/Commands/CleanupExpiredVerifications.php`
**Action**: Modify — add kick command after whitelist removal

In `handleExpiredVerifications()`, locate the `if ($result['success'])` block and add the kick
command BEFORE `$account->delete()`:

```php
if ($result['success']) {
    // Best-effort kick — no-op if player is already offline
    $rconService->executeCommand(
        "kick {$account->username} Your verification has expired. Please re-verify to rejoin.",
        'kick',
        $account->command_id,
        $verification->user,
        ['action' => 'kick_expired_verification', 'verification_id' => $verification->id]
    );

    $account->delete();
    $this->info("Removed whitelist and deleted account for {$account->username}.");
}
```

The kick is intentionally not checked for success — if the player is offline the server returns
an error which is fine. The `$account->username` for Bedrock will now be `.FinalAsgard` (with dot)
after the Bug A fix, which matches how the server identifies the player.

**Tests** — Add or extend a cleanup test file (check if
`tests/Feature/Minecraft/CleanupExpiredVerificationsTest.php` exists; if not, create it):

```php
<?php
declare(strict_types=1);

use App\Console\Commands\CleanupExpiredVerifications;
use App\Enums\MinecraftAccountStatus;
use App\Enums\MinecraftAccountType;
use App\Models\MinecraftAccount;
use App\Models\MinecraftVerification;
use App\Models\User;
use App\Services\MinecraftRconService;
use Illuminate\Foundation\Testing\RefreshDatabase;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->rconMock = $this->mock(MinecraftRconService::class, function ($mock) {
        $mock->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => 'OK']);
    });
});

it('removes whitelist and kicks player when verification expires', function () {
    $verification = MinecraftVerification::factory()->for($this->user)->create([
        'status'             => 'pending',
        'expires_at'         => now()->subMinutes(35),
        'minecraft_username' => 'TestPlayer',
        'minecraft_uuid'     => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
    ]);

    MinecraftAccount::factory()->for($this->user)->verifying()->create([
        'username'   => 'TestPlayer',
        'uuid'       => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
        'command_id' => 'TestPlayer',
        'account_type' => MinecraftAccountType::Java,
    ]);

    $this->rconMock->shouldReceive('executeCommand')
        ->withArgs(fn($cmd) => str_contains($cmd, 'whitelist remove'))
        ->once()
        ->andReturn(['success' => true, 'response' => 'OK']);

    $this->rconMock->shouldReceive('executeCommand')
        ->withArgs(fn($cmd) => str_contains($cmd, 'kick TestPlayer'))
        ->once()
        ->andReturn(['success' => true, 'response' => 'OK']);

    $this->artisan('minecraft:cleanup-expired')->assertSuccessful();

    $this->assertDatabaseMissing('minecraft_accounts', ['username' => 'TestPlayer']);
    $this->assertDatabaseHas('minecraft_verifications', ['status' => 'expired']);
});

it('does not crash if kick fails (player already offline)', function () {
    // ... similar setup but kick returns ['success' => false] ...
    // Assert account is still deleted
});
```

---

### Step 13: Server Info in Verification Card
**File**: `config/lighthouse.php`
**Action**: Modify — add `minecraft` config array at the bottom of the returned array

```php
'minecraft' => [
    'server_name'        => env('MINECRAFT_SERVER_NAME', 'Lighthouse MC'),
    'server_host'        => env('MINECRAFT_SERVER_HOST', ''),
    'server_port_java'   => (int) env('MINECRAFT_SERVER_PORT_JAVA', 25565),
    'server_port_bedrock' => (int) env('MINECRAFT_SERVER_PORT_BEDROCK', 19132),
],
```

---

### Step 14: Server Info — .env.example
**File**: `.env.example`
**Action**: Modify — append at the bottom

```blade
# Minecraft Server — player-facing connection info (shown in the verification UI)
MINECRAFT_SERVER_NAME="Lighthouse MC"
MINECRAFT_SERVER_HOST=play.lighthousemc.net
MINECRAFT_SERVER_PORT_JAVA=25565
MINECRAFT_SERVER_PORT_BEDROCK=19132
```

---

### Step 15: Server Info — Verification Card UI
**File**: `resources/views/livewire/settings/minecraft-accounts.blade.php`
**Action**: Modify — update the instructions list in the "Active Verification Code" card

The instructions list is in the `@if($verificationCode && $expiresAt)` block (around line 329–334).

Replace the existing `<ol>` with:
```blade
@php
    $serverName    = config('lighthouse.minecraft.server_name');
    $serverHost    = config('lighthouse.minecraft.server_host');
    $serverPort    = $accountType === 'bedrock'
        ? config('lighthouse.minecraft.server_port_bedrock')
        : config('lighthouse.minecraft.server_port_java');
    $defaultPort   = $accountType === 'bedrock' ? 19132 : 25565;
    $showPort      = $serverPort !== $defaultPort;
@endphp
<ol class="flex flex-col gap-1 list-decimal list-inside text-sm text-zinc-700 dark:text-zinc-300">
    <li>
        Join the Minecraft server: <strong>{{ $serverName }}</strong>
        @if($serverHost)
            <br>
            <code class="px-2 py-1 bg-zinc-200 dark:bg-zinc-700 rounded">
                {{ $serverHost }}{{ $showPort ? ':' . $serverPort : '' }}
            </code>
        @endif
    </li>
    <li>Type in chat: <code class="px-2 py-1 bg-zinc-200 dark:bg-zinc-700 rounded">/verify {{ $verificationCode }}</code></li>
    <li>Wait for confirmation (this page will update automatically)</li>
</ol>
```

Note: `$accountType` is a public property on the component — always available in the blade view.

---

## Edge Cases

- **Bedrock username with dot entered by user**: The `ltrim($username, '.')` strips it before API
  lookup; after getting the gamertag, the `str_starts_with` check prevents double-dotting.
- **Meeting already completed when schedule-next modal is shown**: The modal content is pre-filled
  from `$this->meeting` which still exists in component state. Works fine.
- **`openFullMinutes()` called with null minutes**: The button is only shown when
  `$selectedMeetingMinutes` is truthy, so this can't happen.
- **Kick RCON command fails (player offline)**: No-op; we don't check the return value.
- **`MINECRAFT_SERVER_HOST` not set**: The `@if($serverHost)` block is skipped; only server name shows.
- **`scheduleNextMeeting()` called without `create` permission**: Fails at `$this->authorize()` gate check — safe.

## Known Risks

- **Issue #208 newline behavior change**: Messages that relied on single newlines for line breaks
  will now show those lines merged into a paragraph. Users may notice this on existing ticket
  messages. This is an acceptable trade-off for the markdown link feature.
- **MeetingNotePolicy tightening**: Any CrewMember who was using note editing will lose that
  ability. Verify no crew members are currently relying on this in production before deploying.
- **MeetingNote `updateSave()` uses `locked_by` check**: If a CrewMember currently has a lock
  on a note, they could still save via `SaveNote()` (which checks `updateSave`, not `update`).
  The next time the lock expires or they unlock, they won't be able to re-lock. Acceptable.

## Definition of Done

- [ ] `./vendor/bin/pest` passes with zero failures
- [ ] All new test cases from this plan are implemented and pass
- [ ] No ad-hoc auth checks added in Blade templates (policies/gates only)
- [ ] Manual check: `/tickets/{id}` on 375px viewport — badges wrap, dropdowns stack, checkbox on own line
- [ ] Manual check: `/meetings/{id}/manage` as CrewMember during InProgress — "Join Meeting" button visible
- [ ] Manual check: Completing a meeting shows "Schedule Next Meeting" modal with title/time pre-filled
- [ ] Manual check: `/ready-room` department tab → select completed meeting → "View Full Minutes" button appears
- [ ] Manual check: Bedrock verification with plain gamertag → stored as `.gamertag`
- [ ] Commit messages reference and close #206, #208, #187, #216
