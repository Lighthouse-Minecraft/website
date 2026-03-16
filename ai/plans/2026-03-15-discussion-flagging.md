# Plan: Discussion Message Flagging

**Date**: 2026-03-15
**Planned by**: Claude Code
**Status**: PENDING APPROVAL

## Summary

Add the existing ticket message flag/report system to discussion threads. Users can flag
messages in discussions they participate in. Quartermaster staff (CrewMember+) can view
flagged discussions and acknowledge flags. Flagged discussions appear in a "Flagged" filter
on the discussions list for authorized staff. Staff with viewFlagged permission can view
the full discussion while any flag remains unresolved (has_open_flags = true).

## Files to Read (for implementing agent context)
- `CLAUDE.md`
- `ai/CONVENTIONS.md`
- `ai/ARCHITECTURE.md`

## Authorization Rules

**No new gates or policies needed.** The existing infrastructure already supports this:
- `MessagePolicy::flag()` — checks thread participation, prevents self-flag & system-flag
- `ThreadPolicy::viewFlagged()` — Quartermaster CrewMember+
- `FlagMessage` action — creates flag, review ticket, notifications
- `AcknowledgeFlag` action — acknowledges flag, recalculates `has_open_flags`

The only authorization change is in `Thread::isVisibleTo()` where Topics currently bypass
the flagged visibility check.

## Database Changes

None — the existing `message_flags` table and Thread `is_flagged`/`has_open_flags` columns
already work for any thread type.

## Implementation Steps (execute in this exact order)

---

### Step 1: Update Thread Visibility for Flagged Topics

**File**: `app/Models/Thread.php`
**Action**: Modify — update `isVisibleTo()` method

Change the Topic early-return (lines 133-136) from:

```php
// Topics: participant-only visibility (no department/flagged logic)
if ($this->type === ThreadType::Topic) {
    return $this->participants()->where('user_id', $user->id)->exists();
}
```

To:

```php
// Topics: participant-only or viewFlagged (while flags are open)
if ($this->type === ThreadType::Topic) {
    if ($user->can('viewFlagged', Thread::class) && $this->has_open_flags) {
        return true;
    }
    return $this->participants()->where('user_id', $user->id)->exists();
}
```

Key difference from tickets: Uses `has_open_flags` (not `is_flagged`) so staff access
is temporary — once all flags are acknowledged, non-participant staff lose access.

---

### Step 2: Add Flag UI to Discussion View Component (PHP)

**File**: `resources/views/livewire/topics/view-topic.blade.php`
**Action**: Modify — add imports, properties, and methods to the PHP class

Add imports after existing `use` statements:

```php
use App\Actions\AcknowledgeFlag;
use App\Actions\FlagMessage;
use App\Models\MessageFlag;
use Flux\Flux;
```

Add properties after `public array $searchResults = [];`:

```php
public ?int $flaggingMessageId = null;
public string $flagReason = '';
public ?int $acknowledgingFlagId = null;
public string $staffNotes = '';
```

Add computed property after `canAddParticipant`:

```php
#[Computed]
public function canViewFlagged(): bool
{
    return auth()->user()->can('viewFlagged', Thread::class);
}
```

Update `messages()` computed to eager-load flags:

```php
#[Computed]
public function messages()
{
    $messages = $this->thread->messages()
        ->with(['user.minecraftAccounts', 'user.discordAccounts', 'flags.flaggedBy', 'flags.reviewedBy'])
        ->orderBy('created_at')
        ->get();

    if (! auth()->user()->can('internalNotes', $this->thread)) {
        $messages = $messages->filter(fn ($msg) => $msg->kind !== MessageKind::InternalNote);
    }

    return $messages;
}
```

Add these four methods before the closing `};`:

```php
public function openFlagModal(int $messageId): void
{
    $message = Message::findOrFail($messageId);
    $this->authorize('flag', $message);

    $this->flaggingMessageId = $messageId;
    $this->flagReason = '';

    Flux::modal('flag-message')->show();
}

public function submitFlag(): void
{
    $validator = Validator::make(
        ['flagReason' => $this->flagReason],
        ['flagReason' => 'required|string|min:10']
    );

    if ($validator->fails()) {
        $this->addError('flagReason', $validator->errors()->first('flagReason'));
        return;
    }

    $message = Message::findOrFail($this->flaggingMessageId);
    $this->authorize('flag', $message);

    FlagMessage::run($message, auth()->user(), $this->flagReason);

    $this->flaggingMessageId = null;
    $this->flagReason = '';

    Flux::modal('flag-message')->close();
    Flux::toast('Message flagged for review. Staff will be notified.', variant: 'success');

    unset($this->messages);
}

public function openAcknowledgeModal(int $flagId): void
{
    $flag = MessageFlag::findOrFail($flagId);

    if (! $this->canViewFlagged) {
        abort(403);
    }

    $this->acknowledgingFlagId = $flagId;
    $this->staffNotes = '';

    Flux::modal('acknowledge-flag')->show();
}

public function acknowledgeFlag(): void
{
    if (! $this->canViewFlagged) {
        abort(403);
    }

    $flag = MessageFlag::findOrFail($this->acknowledgingFlagId);

    AcknowledgeFlag::run($flag, auth()->user(), $this->staffNotes ?: null);

    $this->acknowledgingFlagId = null;
    $this->staffNotes = '';

    Flux::modal('acknowledge-flag')->close();
    Flux::toast('Flag acknowledged successfully!', variant: 'success');

    unset($this->messages);
}
```

---

### Step 3: Add Flag UI to Discussion View Component (Blade)

**File**: `resources/views/livewire/topics/view-topic.blade.php`
**Action**: Modify — add flag button, flag display, and modals to Blade template

**3a. Add flag button** next to message timestamps for all non-system message types.
For other users' messages (the `@else` block starting at line 429), add after the
timestamp span (line 436):

```blade
@if(auth()->user()->can('flag', $message))
    <flux:button wire:click="openFlagModal({{ $message->id }})" variant="ghost" size="xs" class="!p-0.5" aria-label="Flag message">
        <flux:icon.flag class="size-3.5" />
    </flux:button>
@endif
```

This button should appear in the header row of each non-own, non-system message.

**3b. Add flag display** after each message's chat-bubble div (for staff who can view flags).
Add after the closing `</div>` of the chat-bubble in both the "other user" and "own message"
blocks:

```blade
@if($this->canViewFlagged && $message->flags->isNotEmpty())
    <div class="mt-2 space-y-2">
        @foreach($message->flags as $flag)
            <div wire:key="flag-{{ $flag->id }}" class="rounded border border-red-300 dark:border-red-700 bg-red-50 dark:bg-red-950 p-3">
                <div class="flex items-start justify-between">
                    <div class="text-sm">
                        <strong>Flagged by <a href="{{ route('profile.show', $flag->flaggedBy) }}" class="text-blue-600 dark:text-blue-400 hover:underline">{{ $flag->flaggedBy->name }}</a></strong> on {{ $flag->created_at->setTimezone($tz)->format('M j, Y g:i A') }}
                        <div class="mt-1 text-zinc-700 dark:text-zinc-300">{{ $flag->note }}</div>
                        @if($flag->status->value === 'acknowledged')
                            <div class="mt-2 text-xs text-zinc-600 dark:text-zinc-400">
                                @if($flag->reviewedBy && $flag->reviewed_at)
                                    <strong>Acknowledged by <a href="{{ route('profile.show', $flag->reviewedBy) }}" class="text-blue-600 dark:text-blue-400 hover:underline">{{ $flag->reviewedBy->name }}</a></strong> on {{ $flag->reviewed_at->setTimezone($tz)->format('M j, Y g:i A') }}
                                @else
                                    <strong>Acknowledged</strong>
                                @endif
                                @if($flag->staff_notes)
                                    <div class="mt-1">{{ $flag->staff_notes }}</div>
                                @endif
                            </div>
                        @endif
                    </div>
                    @if($flag->status->value === 'new')
                        <flux:button wire:click="openAcknowledgeModal({{ $flag->id }})" variant="primary" size="sm">
                            Acknowledge Flag
                        </flux:button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif
```

**3c. Add modals** before the closing `</div>` at the end of the component (before line 500):

```blade
{{-- Flag Message Modal --}}
<flux:modal name="flag-message" class="space-y-6">
    <div>
        <flux:heading size="lg">Flag Message</flux:heading>
        <flux:subheading>Why are you flagging this message?</flux:subheading>
    </div>

    <flux:field>
        <flux:label>Reason <span class="text-red-500">*</span></flux:label>
        <flux:textarea wire:model="flagReason" rows="4" placeholder="Please explain why this message should be reviewed by staff..." />
        <flux:error name="flagReason" />
    </flux:field>

    <div class="flex justify-end gap-2">
        <flux:button wire:click="$dispatch('close')" variant="ghost">Cancel</flux:button>
        <flux:button wire:click="submitFlag" variant="danger">Submit Flag</flux:button>
    </div>
</flux:modal>

{{-- Acknowledge Flag Modal --}}
<flux:modal name="acknowledge-flag" class="space-y-6">
    <div>
        <flux:heading size="lg">Acknowledge Flag</flux:heading>
        <flux:subheading>Add notes about your review of this flag (optional)</flux:subheading>
    </div>

    <flux:field>
        <flux:label>Staff Notes</flux:label>
        <flux:textarea wire:model="staffNotes" rows="4" placeholder="Add any notes about your review of this flag..." />
    </flux:field>

    <div class="flex justify-end gap-2">
        <flux:button wire:click="$dispatch('close')" variant="ghost">Cancel</flux:button>
        <flux:button wire:click="acknowledgeFlag" variant="primary">Acknowledge Flag</flux:button>
    </div>
</flux:modal>
```

---

### Step 4: Add Flagged Discussions Filter to Discussions List

**File**: `resources/views/livewire/topics/topics-list.blade.php`
**Action**: Modify — add a "Flagged" filter tab/button for authorized staff

Add a property to the PHP class:

```php
public string $filter = 'active';
```

Add a computed property:

```php
#[Computed]
public function canViewFlagged(): bool
{
    return auth()->user()->can('viewFlagged', Thread::class);
}

#[Computed]
public function flaggedTopics()
{
    if (! $this->canViewFlagged) {
        return collect();
    }

    return Thread::where('type', ThreadType::Topic)
        ->where('has_open_flags', true)
        ->with(['createdBy'])
        ->orderByDesc('last_message_at')
        ->get();
}
```

Add a filter button in the Blade template above the active topics list (after the heading):

```blade
@if($this->canViewFlagged)
    <div class="flex items-center gap-2">
        <flux:button wire:click="$set('filter', 'active')" :variant="$filter === 'active' ? 'primary' : 'ghost'" size="sm">
            My Discussions
        </flux:button>
        <flux:button wire:click="$set('filter', 'flagged')" :variant="$filter === 'flagged' ? 'danger' : 'ghost'" size="sm">
            Flagged
            @if($this->flaggedTopics->count() > 0)
                <flux:badge color="red" size="sm">{{ $this->flaggedTopics->count() }}</flux:badge>
            @endif
        </flux:button>
    </div>
@endif
```

Conditionally show flagged topics when that filter is selected:

```blade
@if($filter === 'flagged' && $this->canViewFlagged)
    @if($this->flaggedTopics->isNotEmpty())
        <div class="rounded-lg border border-red-200 dark:border-red-800 divide-y divide-red-200 dark:divide-red-800">
            @foreach($this->flaggedTopics as $topic)
                <a
                    href="{{ route('discussions.show', $topic) }}"
                    wire:navigate
                    wire:key="flagged-{{ $topic->id }}"
                    class="block p-4 hover:bg-red-50 dark:hover:bg-red-950/30 transition"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <flux:icon.flag class="size-4 text-red-500" />
                                <flux:heading size="sm">{{ $topic->subject }}</flux:heading>
                            </div>
                            <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                                <span>Started by {{ $topic->createdBy?->name ?? 'Unknown' }}</span>
                                <span class="mx-2">&bull;</span>
                                <span>{{ $topic->created_at->diffForHumans() }}</span>
                            </div>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @else
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-8 text-center text-zinc-500 dark:text-zinc-400">
            <flux:heading size="lg" class="text-zinc-500 dark:text-zinc-400">No Flagged Discussions</flux:heading>
            <flux:text class="mt-2">There are no discussions with open flags.</flux:text>
        </div>
    @endif
@else
    {{-- existing active/archived topics content --}}
@endif
```

---

### Step 5: Tests

**File**: `tests/Feature/Topics/DiscussionFlaggingTest.php`
**Action**: Create

Test cases:

```text
it('allows participants to flag messages from other users')
it('prevents users from flagging their own messages')
it('prevents non-participants from flagging messages')
it('prevents flagging system messages')
it('shows flag button only for flaggable messages')
it('creates moderation review ticket when message is flagged')
it('allows Quartermaster staff to view flagged discussion')
it('denies non-Quartermaster from viewing flagged discussion')
it('revokes staff access after all flags are acknowledged')
it('allows Quartermaster staff to acknowledge a flag')
it('shows flagged filter to Quartermaster staff on discussions list')
it('hides flagged filter from non-Quartermaster users')
```

Pattern — use existing test helpers:

```php
<?php
declare(strict_types=1);

use App\Actions\AcknowledgeFlag;
use App\Actions\FlagMessage;
use App\Enums\MessageKind;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Enums\ThreadType;
use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use Livewire\Volt\Volt;

uses()->group('topics', 'flagging');

it('allows participants to flag messages from other users', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $thread = Thread::factory()->create(['type' => ThreadType::Topic]);
    $thread->addParticipant($user);
    $thread->addParticipant($other);
    $message = Message::factory()->create([
        'thread_id' => $thread->id,
        'user_id' => $other->id,
    ]);

    $this->actingAs($user);
    FlagMessage::run($message, $user, 'Inappropriate content');

    expect($thread->fresh()->is_flagged)->toBeTrue();
    expect($thread->fresh()->has_open_flags)->toBeTrue();
});

it('allows Quartermaster staff to view flagged discussion', function () {
    $staff = User::factory()->create([
        'staff_department' => StaffDepartment::Quartermaster,
        'staff_rank' => StaffRank::CrewMember,
    ]);
    $thread = Thread::factory()->create([
        'type' => ThreadType::Topic,
        'is_flagged' => true,
        'has_open_flags' => true,
    ]);

    // Staff is NOT a participant
    expect($thread->isVisibleTo($staff))->toBeTrue();
});

it('revokes staff access after all flags are acknowledged', function () {
    $staff = User::factory()->create([
        'staff_department' => StaffDepartment::Quartermaster,
        'staff_rank' => StaffRank::CrewMember,
    ]);
    $thread = Thread::factory()->create([
        'type' => ThreadType::Topic,
        'is_flagged' => true,
        'has_open_flags' => false,
    ]);

    expect($thread->isVisibleTo($staff))->toBeFalse();
});
```

---

## Edge Cases

- **User flags then leaves**: Flag remains; staff can still see discussion
- **Multiple flags on same discussion**: Each creates its own review ticket; `has_open_flags`
  stays true until ALL are acknowledged
- **Staff is also a participant**: They can always view (participant access) AND see flags
- **Locked topic with flags**: Staff can view but cannot reply (existing lock behavior)
- **Flag on internal note**: MessagePolicy prevents flagging system messages, but internal
  notes are regular messages — participants can flag them. This is acceptable since internal
  notes are only visible to authorized users anyway.

## Known Risks

- The `FlagMessage` action creates a review ticket (ThreadType::Ticket) in Quartermaster
  department. This works identically for discussions as for tickets — no changes needed.
- The `MessageFlaggedNotification` notification links to the review ticket, not the
  original discussion. Staff can navigate to the original thread from the review ticket's
  system message. This is the same behavior as ticket flags.

## Definition of Done

- [ ] `php artisan migrate:fresh` passes (no new migrations)
- [ ] `./vendor/bin/pest` passes with zero failures
- [ ] All test cases from this plan are implemented
- [ ] No ad-hoc auth checks in Blade templates
- [ ] Flag button appears on discussion messages for participants (not own, not system)
- [ ] Quartermaster staff can view flagged discussions while flags are open
- [ ] Flagged filter appears on discussions list for authorized staff
- [ ] Flagged discussions no longer visible to non-participant staff after acknowledgement
