# Plan: Meeting Attendance Tracking Overhaul

**Date**: 2026-03-15
**Planned by**: Claude Code
**Status**: PENDING APPROVAL

## Summary

Overhaul the meeting attendance system from opt-in (only present members recorded) to full-roster tracking (every staff member gets a record when a meeting starts, defaulting to absent). The "Manage Attendees" modal is redesigned with toggles grouped by department (officers above crew). The dashboard's Staff Engagement widget is updated to use the new `attended` boolean field. The "Join Meeting" self-service button is removed — attendance is managed exclusively through the modal.

## Files to Read (for implementing agent context)
- `CLAUDE.md`
- `ai/CONVENTIONS.md`
- `ai/ARCHITECTURE.md`

## Authorization Rules
- No new gates or policies needed. Existing `update` policy on Meeting is sufficient for managing attendees.
- The `attend` gate (used by the removed "Join Meeting" button) can remain but is no longer referenced.

## Database Changes

| Migration file | Table | Change |
|---|---|---|
| `2026_03_15_000000_add_attended_to_meeting_user.php` | `meeting_user` | Add `attended` boolean column, default `false` |

Column details:
- `attended` — boolean, not nullable, default `false`. `true` = present, `false` = absent.

Backfill: All existing rows in `meeting_user` get `attended = true` (historically, being in the table meant "present").

---

## Implementation Steps (execute in this exact order)

---

### Step 1: Migration
**File**: `database/migrations/2026_03_15_000000_add_attended_to_meeting_user.php`
**Action**: Create

```php
Schema::table('meeting_user', function (Blueprint $table) {
    $table->boolean('attended')->default(false)->after('added_at');
});

// Backfill: existing records were all "present" members
DB::table('meeting_user')->update(['attended' => true]);
```

Rollback:
```php
Schema::table('meeting_user', function (Blueprint $table) {
    $table->dropColumn('attended');
});
```

---

### Step 2: Model Changes — Meeting
**File**: `app/Models/Meeting.php`
**Action**: Modify

**2a.** Update `attendees()` relationship to include `attended` in pivot:
```php
public function attendees(): BelongsToMany
{
    return $this->belongsToMany(User::class)
        ->withPivot('added_at', 'attended')
        ->withTimestamps()
        ->orderBy('meeting_user.added_at');
}
```

**2b.** Update `startMeeting()` to seed all staff members:
```php
public function startMeeting(): void
{
    if ($this->status !== MeetingStatus::Pending) {
        throw new \Exception('Meeting cannot be started unless it is pending.');
    }

    $this->status = MeetingStatus::InProgress;
    $this->start_time = now();
    $this->save();

    // Seed attendance records for all active staff
    $staffUsers = User::where('staff_rank', '>=', StaffRank::JrCrew->value)
        ->pluck('id');

    $now = now();
    $starterId = Auth::id();
    $records = [];
    foreach ($staffUsers as $userId) {
        $records[$userId] = [
            'added_at' => $now,
            'attended' => $userId === $starterId,
        ];
    }

    $this->attendees()->syncWithoutDetaching($records);
}
```

Add required imports at top of model:
```php
use App\Enums\StaffRank;
```

---

### Step 3: Livewire Component — manage-attendees (full rewrite)
**File**: `resources/views/livewire/meeting/manage-attendees.blade.php`
**Action**: Rewrite

PHP class changes:
- Remove `$selectedAttendees` array property
- Remove `addAttendees()` method
- Remove `getStaffMembersProperty()` (replaces with grouped computed)
- Add computed `attendeesByDepartment()` — returns attendees grouped by `staff_department`, within each group ordered by rank DESC then name ASC
- Add `toggleAttendance(int $userId)` method — toggles `attended` on the pivot record
- Add `markAllPresent()` and `markAllAbsent()` bulk methods
- Button text changes from "Add Attendee" to "Manage Attendees"
- Available during `InProgress` and `Finalizing` statuses (not just InProgress)

Blade template:
- Modal shows staff grouped by department headings
- Within each department: Officers first, then Crew Members, then Jr Crew
- Each staff member row: avatar, name, rank badge, and a `flux:switch` toggle
- Bulk actions at top: "Mark All Present" / "Mark All Absent" buttons

```php
new class extends Component {
    public Meeting $meeting;

    public function mount(Meeting $meeting)
    {
        $this->meeting = $meeting;
    }

    #[\Livewire\Attributes\Computed]
    public function attendeesByDepartment()
    {
        return $this->meeting->attendees()
            ->get()
            ->groupBy(fn ($user) => $user->staff_department?->value ?? 'unknown')
            ->sortKeys()
            ->map(fn ($group) => $group->sortByDesc(fn ($u) => $u->staff_rank->value)->sortBy('name')->sortByDesc('staff_rank'));
    }

    public function toggleAttendance(int $userId): void
    {
        $this->authorize('update', $this->meeting);

        $current = DB::table('meeting_user')
            ->where('meeting_id', $this->meeting->id)
            ->where('user_id', $userId)
            ->value('attended');

        DB::table('meeting_user')
            ->where('meeting_id', $this->meeting->id)
            ->where('user_id', $userId)
            ->update(['attended' => !$current]);

        unset($this->attendeesByDepartment);
    }

    public function markAllPresent(): void
    {
        $this->authorize('update', $this->meeting);

        DB::table('meeting_user')
            ->where('meeting_id', $this->meeting->id)
            ->update(['attended' => true]);

        unset($this->attendeesByDepartment);
    }

    public function markAllAbsent(): void
    {
        $this->authorize('update', $this->meeting);

        DB::table('meeting_user')
            ->where('meeting_id', $this->meeting->id)
            ->update(['attended' => false]);

        unset($this->attendeesByDepartment);
    }

    public function openModal(): void
    {
        $this->authorize('update', $this->meeting);
        $this->meeting->load('attendees');
        unset($this->attendeesByDepartment);
        $this->modal('manage-attendees')->show();
    }
};
```

Blade template (key structure):
```blade
@if(in_array($meeting->status->value, ['in_progress', 'finalizing']))
    @can('update', $meeting)
        <flux:button wire:click="openModal" variant="primary" color="indigo" size="sm" icon="user-group">
            Manage Attendees
        </flux:button>
    @endcan

    <flux:modal name="manage-attendees" class="min-w-[36rem]">
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">Manage Attendees</flux:heading>
                <div class="flex gap-2">
                    <flux:button wire:click="markAllPresent" size="xs" variant="ghost">All Present</flux:button>
                    <flux:button wire:click="markAllAbsent" size="xs" variant="ghost">All Absent</flux:button>
                </div>
            </div>

            <div class="space-y-4 max-h-[28rem] overflow-y-auto">
                @foreach($this->attendeesByDepartment as $department => $members)
                    <div>
                        <flux:text class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">
                            {{ \App\Enums\StaffDepartment::tryFrom($department)?->label() ?? $department }}
                        </flux:text>
                        <div class="space-y-1">
                            @foreach($members as $member)
                                <div wire:key="att-{{ $member->id }}" class="flex items-center justify-between p-2 rounded-lg border border-zinc-200 dark:border-zinc-700">
                                    <div class="flex items-center gap-2">
                                        <flux:avatar size="xs" :src="$member->avatarUrl()" />
                                        <div>
                                            <flux:text class="text-sm font-medium">{{ $member->name }}</flux:text>
                                            <flux:text variant="subtle" class="text-xs">{{ $member->staff_rank->label() }}</flux:text>
                                        </div>
                                    </div>
                                    <flux:switch
                                        wire:click="toggleAttendance({{ $member->id }})"
                                        :checked="(bool) $member->pivot->attended"
                                    />
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button variant="ghost">Close</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
@endif
```

---

### Step 4: Livewire Component — manage-meeting
**File**: `resources/views/livewire/meetings/manage-meeting.blade.php`
**Action**: Modify

**4a.** Remove the `joinMeeting()` method entirely (lines 148-172).

**4b.** Remove the "Join Meeting" button block from the blade template (lines 435-445):
```blade
{{-- REMOVE THIS BLOCK --}}
@if($meeting->status->value === 'in_progress')
    @can('attend', $meeting)
        @unless($meeting->attendees->contains(auth()->id()))
            ...Join Meeting button...
        @endunless
    @endcan
@endif
```

**4c.** Update the manage-attendees inclusion to also show during Finalizing (line 429-433):
Change:
```blade
@if($meeting->status->value === 'in_progress')
```
To:
```blade
@if(in_array($meeting->status->value, ['in_progress', 'finalizing']))
```

**4d.** Update the attendee list display to show present/absent status. In the attendees loop (lines 405-424), add a visual indicator:
- Present members: green check icon or normal display
- Absent members: red "Absent" badge or subtle/muted styling

**4e.** Update the attendee count display (line 397) to show "X of Y present":
```blade
@if($meeting->attendees->count() > 0)
    @php
        $presentCount = $meeting->attendees->where('pivot.attended', true)->count();
        $totalCount = $meeting->attendees->count();
    @endphp
    <strong>Attendance:</strong> {{ $presentCount }} / {{ $totalCount }} present<br>
@endif
```

---

### Step 5: Dashboard — Staff Engagement Widget
**File**: `resources/views/livewire/dashboard/command-staff-engagement.blade.php`
**Action**: Modify

**5a.** Update the `meetingsAttended` query (lines 116-121) to filter by `attended = true`:
```php
$meetingsAttended = DB::table('meeting_user')
    ->whereIn('meeting_id', $staffMeetings3mo)
    ->whereIn('user_id', $staffIds)
    ->where('attended', true)  // NEW: only count present members
    ->selectRaw('user_id, COUNT(*) as count')
    ->groupBy('user_id')
    ->pluck('count', 'user_id');
```

**5b.** Update the staff detail `$attended` check (lines 189-191) to check the `attended` pivot value:
```php
$attended = $meeting
    ? $meeting->attendees()
        ->where('users.id', $user->id)
        ->wherePivot('attended', true)
        ->exists()
    : null;
```

---

### Step 6: Update Tests — Attendance
**File**: `tests/Feature/Meeting/AttendanceTest.php`
**Action**: Rewrite

Test cases:
- `it('seeds all staff as absent when meeting starts')` — start a meeting, verify all JrCrew+ users have pivot records with `attended = false`
- `it('marks the meeting starter as present')` — starter should have `attended = true`
- `it('does not seed non-staff users')` — users with StaffRank::None should not get records
- `it('shows manage attendees button during in_progress')` — button text is "Manage Attendees"
- `it('shows manage attendees button during finalizing')`
- `it('does not show manage attendees button when completed')`
- `it('toggles attendance from absent to present')` — call `toggleAttendance`, verify pivot
- `it('toggles attendance from present to absent')` — reverse toggle
- `it('mark all present sets all attendees to present')` — call `markAllPresent`
- `it('mark all absent sets all attendees to absent')` — call `markAllAbsent`
- `it('only authorized users can toggle attendance')` — unauthorized user gets 403
- `it('groups attendees by department in modal')`
- `it('does not show join meeting button')` — verify the self-service button is gone

---

### Step 7: Update Tests — Dashboard
**File**: `tests/Feature/Livewire/Dashboard/CommandStaffEngagementTest.php`
**Action**: Modify

Update the `'shows meetings attended count over last 3 months'` test to attach with `attended => true`:
```php
$meeting->attendees()->attach($staff->id, ['added_at' => now()->subDays(14), 'attended' => true]);
```

Add test:
- `it('does not count absent members in meetings attended')` — attach with `attended => false`, verify count is 0

---

## Edge Cases
- **Meeting started before migration**: Existing pivot records are backfilled as `attended = true`. No absent records are retroactively created for staff who weren't there. Dashboard treats these the same as before.
- **Staff member added after meeting starts**: If a new staff member joins the team mid-meeting, they won't have a pivot record. The Manage Attendees modal shows only seeded records. This is acceptable — they weren't on the team when the meeting started.
- **Staff member removed from team**: Their existing pivot records remain. They simply won't appear in future meeting seeds.
- **Duplicate sync**: `syncWithoutDetaching` in `startMeeting()` is safe if called multiple times — won't overwrite existing records.

## Known Risks
- **Large staff roster**: `syncWithoutDetaching` with many users is a single DB operation — should be fine for any reasonable staff size.
- **Existing "Join Meeting" references**: The dashboard `ready-room` has a "Join Meeting" button that links to the meeting page. This can remain as navigation but should NOT auto-add attendance. Verify no other components call `joinMeeting()`.

## Definition of Done
- [ ] `php artisan migrate:fresh` passes
- [ ] `./vendor/bin/pest` passes with zero failures
- [ ] All test cases from this plan are implemented
- [ ] No ad-hoc auth checks in Blade templates
- [ ] "Join Meeting" self-service button removed from meeting manage page
- [ ] Manage Attendees modal works during InProgress and Finalizing
- [ ] Dashboard correctly counts only `attended = true` records
