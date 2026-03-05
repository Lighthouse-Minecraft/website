# Plan: Pre-Meeting Staff Reports & Meeting Types

**Date**: 2026-03-05
**Planned by**: Claude Code
**Status**: PENDING APPROVAL

## Context

Staff meetings happen every 2 weeks. Before each meeting, staff must fill out a report answering questions about their progress. Currently this is done externally. This feature moves the report system to the website, ties responses to meetings, adds meeting types to differentiate behavior, and integrates report data into the meeting workflow and department pages.

## Summary

1. **Meeting Types** — Add a `type` field to meetings (Staff Meeting, Board Meeting, Community Meeting, Other). Department note sections only appear for Staff Meetings; other types get one general note section.
2. **Pre-Meeting Reports** — Staff fill out a configurable questionnaire before each Staff Meeting. Reports lock when the meeting starts.
3. **Report Notifications** — Scheduled command sends reminders X days before the meeting to staff who haven't submitted.
4. **Ready Room UI** — Button next to each upcoming meeting for submitting/updating the report. Subtle when locked, prominent when available.
5. **In-Meeting Display** — Small user cards in each department section showing submission status; click to view full report in a modal.
6. **Post-Meeting Archive** — Staff responses shown in department pages between "Department Notes" and "Full Meeting Minutes."

## Files to Read (for implementing agent context)
- `CLAUDE.md`
- `ai/CONVENTIONS.md`
- `ai/ARCHITECTURE.md`
- `app/Models/Meeting.php`
- `app/Models/MeetingNote.php`
- `app/Enums/MeetingStatus.php`
- `app/Enums/StaffDepartment.php`
- `app/Enums/StaffRank.php`
- `config/lighthouse.php`
- `resources/views/livewire/meetings/manage-meeting.blade.php`
- `resources/views/livewire/meeting/department-section.blade.php`
- `resources/views/livewire/dashboard/ready-room-upcoming-meetings.blade.php`
- `resources/views/livewire/dashboard/ready-room-department.blade.php`
- `resources/views/livewire/meeting/notes-display.blade.php`
- `app/Livewire/Meeting/NotesDisplay.php`
- `resources/views/livewire/meeting/create-modal.blade.php`
- `app/Services/TicketNotificationService.php`
- `routes/console.php`

## Configuration (`.env` / `config/lighthouse.php`)

Add to `config/lighthouse.php`:
```php
'meeting_report_unlock_days' => (int) env('MEETING_REPORT_UNLOCK_DAYS', 7),
'meeting_report_notify_days' => (int) env('MEETING_REPORT_NOTIFY_DAYS', 3),
```

## Database Changes

| Migration | Table | Change |
|---|---|---|
| `add_type_to_meetings_table` | `meetings` | Add `type` string column, default `'staff_meeting'`, update existing rows |
| `create_meeting_questions_table` | `meeting_questions` | New table for configurable questions |
| `create_meeting_reports_table` | `meeting_reports` | New table for user report submissions |
| `create_meeting_report_answers_table` | `meeting_report_answers` | New table for individual answers |

### Column Details

**meetings.type** — `string`, default `'staff_meeting'`. Existing rows set to `'staff_meeting'`.

**meeting_questions**:
- `id` — bigIncrements
- `meeting_id` — foreignId, constrained, cascadeOnDelete
- `question_text` — text
- `sort_order` — integer, default 0
- `timestamps`

**meeting_reports**:
- `id` — bigIncrements
- `meeting_id` — foreignId, constrained, cascadeOnDelete
- `user_id` — foreignId, constrained, cascadeOnDelete
- `submitted_at` — timestamp, nullable
- `timestamps`
- Unique constraint on `[meeting_id, user_id]`

**meeting_report_answers**:
- `id` — bigIncrements
- `meeting_report_id` — foreignId, constrained, cascadeOnDelete
- `meeting_question_id` — foreignId, constrained, cascadeOnDelete
- `answer` — text, nullable
- `timestamps`

## New Enum

**`app/Enums/MeetingType.php`**:
```php
enum MeetingType: string {
    case StaffMeeting = 'staff_meeting';
    case BoardMeeting = 'board_meeting';
    case CommunityMeeting = 'community_meeting';
    case Other = 'other';

    public function label(): string { /* human-readable labels */ }
}
```

## New Models

**`app/Models/MeetingQuestion.php`** — fillable: `meeting_id`, `question_text`, `sort_order`. Relations: `meeting()`.

**`app/Models/MeetingReport.php`** — fillable: `meeting_id`, `user_id`, `submitted_at`. Relations: `meeting()`, `user()`, `answers()`. Cast: `submitted_at` as datetime.

**`app/Models/MeetingReportAnswer.php`** — fillable: `meeting_report_id`, `meeting_question_id`, `answer`. Relations: `report()`, `question()`.

## Model Changes

**`app/Models/Meeting.php`** — Add:
- `type` to `$fillable`
- `'type' => MeetingType::class` to `$casts`
- `questions()` HasMany MeetingQuestion
- `reports()` HasMany MeetingReport
- `isStaffMeeting(): bool` helper
- `isReportUnlocked(): bool` — checks if current date is within unlock window
- `isReportLocked(): bool` — true if meeting status is InProgress or beyond

**`app/Models/User.php`** — Add:
- `meetingReports()` HasMany MeetingReport

## Authorization

No new gates needed. Report submission is available to all staff (JrCrew+), same as `view-ready-room` gate. Meeting question management uses existing `Meeting::update` policy.

## Implementation Steps

---

### Step 1: Config
**File**: `config/lighthouse.php` — **Modify**

Add `meeting_report_unlock_days` and `meeting_report_notify_days` entries.

---

### Step 2: MeetingType Enum
**File**: `app/Enums/MeetingType.php` — **Create**

Four cases: StaffMeeting, BoardMeeting, CommunityMeeting, Other. Each with `label()` method.

---

### Step 3: Migrations (4 files)
**Action**: Create all four migrations.

1. `add_type_to_meetings_table` — Add `type` column, default `'staff_meeting'`, backfill existing rows.
2. `create_meeting_questions_table` — Schema as described above.
3. `create_meeting_reports_table` — Schema as described above with unique constraint.
4. `create_meeting_report_answers_table` — Schema as described above.

Run `php artisan migrate`.

---

### Step 4: New Models (3 files)
**Action**: Create `MeetingQuestion`, `MeetingReport`, `MeetingReportAnswer` models with fillable, casts, and relationships as described above.

---

### Step 5: Update Meeting Model
**File**: `app/Models/Meeting.php` — **Modify**

- Add `type` to `$fillable` and `$casts`
- Add `questions()`, `reports()` relationships
- Add `isStaffMeeting()`, `isReportUnlocked()`, `isReportLocked()` helpers:

```php
public function isStaffMeeting(): bool
{
    return $this->type === MeetingType::StaffMeeting;
}

public function isReportUnlocked(): bool
{
    if (!$this->isStaffMeeting()) return false;
    if ($this->isReportLocked()) return false;

    $unlockDays = config('lighthouse.meeting_report_unlock_days', 7);
    return now()->gte($this->scheduled_time->subDays($unlockDays));
}

public function isReportLocked(): bool
{
    return in_array($this->status, [
        MeetingStatus::InProgress,
        MeetingStatus::Finalizing,
        MeetingStatus::Completed,
        MeetingStatus::Archived,
    ]);
}
```

---

### Step 6: Update User Model
**File**: `app/Models/User.php` — **Modify**

Add `meetingReports()` HasMany relationship.

---

### Step 7: Action — CreateDefaultMeetingQuestions
**File**: `app/Actions/CreateDefaultMeetingQuestions.php` — **Create**

Called when a Staff Meeting is created. Creates 4 default questions:
1. "What did I accomplish this iteration (since the last meeting)?"
2. "What am I currently working on?"
3. "What do I plan on working on in the next iteration?"
4. "What help do I need from my department or the staff team?"

```php
class CreateDefaultMeetingQuestions
{
    use AsAction;

    public function handle(Meeting $meeting): void
    {
        if (!$meeting->isStaffMeeting()) return;
        if ($meeting->questions()->exists()) return;

        $defaults = [
            'What did I accomplish this iteration (since the last meeting)?',
            'What am I currently working on?',
            'What do I plan on working on in the next iteration?',
            'What help do I need from my department or the staff team?',
        ];

        foreach ($defaults as $i => $question) {
            $meeting->questions()->create([
                'question_text' => $question,
                'sort_order' => $i,
            ]);
        }
    }
}
```

---

### Step 8: Action — SubmitMeetingReport
**File**: `app/Actions/SubmitMeetingReport.php` — **Create**

Handles creating/updating a user's report for a meeting. Validates meeting is staff type, report window is open, and meeting hasn't started.

```php
class SubmitMeetingReport
{
    use AsAction;

    public function handle(Meeting $meeting, User $user, array $answers): MeetingReport
    {
        // answers = [question_id => answer_text, ...]
        $report = MeetingReport::updateOrCreate(
            ['meeting_id' => $meeting->id, 'user_id' => $user->id],
            ['submitted_at' => now()]
        );

        foreach ($answers as $questionId => $answerText) {
            MeetingReportAnswer::updateOrCreate(
                ['meeting_report_id' => $report->id, 'meeting_question_id' => $questionId],
                ['answer' => $answerText]
            );
        }

        return $report;
    }
}
```

---

### Step 9: Notification — MeetingReportReminderNotification
**File**: `app/Notifications/MeetingReportReminderNotification.php` — **Create**

Follows existing notification pattern (setChannels, via, toMail, toPushover, toDiscord). Subject: "Reminder: Submit your report for [Meeting Title]". Includes link to the report form. Category: `'staff_alerts'`.

---

### Step 10: Scheduled Command — SendMeetingReportReminders
**File**: `app/Console/Commands/SendMeetingReportReminders.php` — **Create**

Runs daily. For each pending Staff Meeting where `scheduled_time - notify_days <= now() < scheduled_time`:
- Find staff members (JrCrew+) who don't have a `meeting_report` for this meeting
- Send `MeetingReportReminderNotification` via `TicketNotificationService::send()` with category `'staff_alerts'`
- Track that notification was sent (to avoid re-sending daily). Add `report_reminder_sent_at` nullable timestamp to meetings table, or track per-user. Simplest: add a `notified_at` nullable column to `meeting_reports` — but users without reports don't have rows. Better approach: create a `meeting_report` row with `submitted_at = null` when notification is sent, so we can track who was notified. Then the report form checks for existing row.

**Schedule**: Add to `routes/console.php`:
```php
Schedule::command('meetings:send-report-reminders')->dailyAt('08:00');
```

---

### Step 11: Update Meeting Creation — Add Type Selection
**File**: `resources/views/livewire/meeting/create-modal.blade.php` — **Modify**

Add a `flux:select` for meeting type. After creation, if type is `staff_meeting`, call `CreateDefaultMeetingQuestions::run($meeting)`.

---

### Step 12: Update Schedule Next Meeting
**File**: `resources/views/livewire/meetings/manage-meeting.blade.php` — **Modify**

In the `scheduleNextMeeting()` method, carry over the meeting type from the completed meeting. After creating the new meeting, if staff type, call `CreateDefaultMeetingQuestions::run($newMeeting)`.

---

### Step 13: Update manage-meeting — Conditional Department Sections
**File**: `resources/views/livewire/meetings/manage-meeting.blade.php` — **Modify**

When meeting is InProgress:
- **Staff Meeting**: Show general + department sections (current behavior) + report cards in each department section
- **Non-Staff Meeting**: Show only a single general note section (no department loop)

Wrap the department loop in `@if($meeting->isStaffMeeting())`:
```blade
@if ($meeting->status == MeetingStatus::InProgress)
    <livewire:meeting.department-section :meeting="$meeting" departmentValue="general" ... />
    <flux:separator />

    @if($meeting->isStaffMeeting())
        @foreach(StaffDepartment::cases() as $department)
            <livewire:meeting.department-section ... />
            <flux:separator />
        @endforeach
    @endif
@endif
```

---

### Step 14: Create Report Form Component
**File**: `resources/views/livewire/meeting/report-form.blade.php` — **Create**

Livewire Volt component. Props: `Meeting $meeting`.

- Loads questions for the meeting, loads existing report if any
- Shows textarea for each question
- Submit button calls `SubmitMeetingReport::run()`
- If meeting has started (locked): show read-only view of submitted answers
- If no report submitted and locked: show "You did not submit a report for this meeting"
- Uses `Flux::toast()` for feedback

This is a full-page or modal component. Since it's accessed from the Ready Room via a button, it should be a **modal** on the Ready Room page, or a dedicated route. Given the form could be substantial, a **dedicated page** is cleaner.

**Route**: `GET /meetings/{meeting}/report` → Volt component
Add to `routes/web.php` in the auth middleware group.

---

### Step 15: Update Ready Room Upcoming Meetings Widget
**File**: `resources/views/livewire/dashboard/ready-room-upcoming-meetings.blade.php` — **Modify**

For each meeting in the list:
- If staff meeting, show a report button next to the meeting link
- Button states:
  - **Not yet unlocked** (before unlock window): Subtle/ghost button, "Report" text, disabled-looking (zinc/subtle variant)
  - **Unlocked, not submitted**: Primary/accent button, "Submit Report" text, links to report page
  - **Unlocked, already submitted**: Ghost/subtle button, "Update Report" text, links to report page
- Non-staff meetings: no button

Logic in mount or computed: load reports for current user for these meetings. Check `isReportUnlocked()` and whether user has a report with non-null `submitted_at`.

---

### Step 16: Create Report Cards Component for Department Sections
**File**: `resources/views/livewire/meeting/department-report-cards.blade.php` — **Create**

Livewire Volt component. Props: `Meeting $meeting`, `string $department`.

- Queries users where `staff_department = $department` and `staff_rank >= JrCrew`
- For each user, check if they have a `meeting_report` for this meeting
- Display a small card grid with:
  - User avatar (small) + name
  - Check icon (green) if report submitted, X icon (red) if not
  - Entire card is clickable → opens modal with full report

Modal content:
- User name + avatar at top
- Each question with user's answer below
- If no report: "No report submitted"

---

### Step 17: Integrate Report Cards into Department Sections
**File**: `resources/views/livewire/meeting/department-section.blade.php` — **Modify**

For Staff Meeting department sections (not 'general', not 'community'), add report cards above the note editor:

```blade
@if ($departmentValue !== 'general' && $departmentValue !== 'community' && $meeting->isStaffMeeting())
    <livewire:meeting.department-report-cards :meeting="$meeting" :department="$departmentValue" />
@endif
```

---

### Step 18: Add Question Management to Meeting Page
**File**: `resources/views/livewire/meeting/manage-questions.blade.php` — **Create**

Livewire Volt component shown on the manage-meeting page when meeting is Pending and Staff type. Allows adding, editing, reordering, and removing questions. Simple inline editing with `flux:input` fields and up/down/delete buttons.

**Integrate into manage-meeting.blade.php**: Show below the agenda section when meeting is Pending and Staff type:
```blade
@if ($meeting->status == MeetingStatus::Pending && $meeting->isStaffMeeting())
    <livewire:meeting.manage-questions :meeting="$meeting" />
@endif
```

---

### Step 19: Update Notes Display — Add Staff Reports Section
**File**: `app/Livewire/Meeting/NotesDisplay.php` — **Modify**
**File**: `resources/views/livewire/meeting/notes-display.blade.php` — **Modify**

In the accordion for each completed meeting, between "Department Notes" and "Full Meeting Minutes", add a "Staff Reports" section that shows submitted reports for users in that department.

Update the query to eager-load reports with answers:
```php
Meeting::with([
    'notes' => fn ($q) => $q->where('section_key', $this->sectionKey)->with('createdBy'),
    'reports' => fn ($q) => $q->with(['user', 'answers.question'])
        ->whereHas('user', fn ($uq) => $uq->where('staff_department', $this->sectionKey)),
])
```

In the template, between Department Notes card and Full Meeting Minutes card:
```blade
@if($meeting->isStaffMeeting() && $meeting->reports->isNotEmpty())
    <flux:card>
        <flux:heading size="sm">Staff Reports</flux:heading>
        @foreach($meeting->reports as $report)
            <div class="mt-3 border-t pt-3 first:border-t-0 first:pt-0">
                <strong>{{ $report->user->name }}</strong>
                @foreach($report->answers as $answer)
                    <div class="mt-1 ml-4">
                        <em class="text-sm text-zinc-500">{{ $answer->question->question_text }}</em>
                        <p class="text-sm">{{ $answer->answer ?? 'No response' }}</p>
                    </div>
                @endforeach
            </div>
        @endforeach
    </flux:card>
@endif
```

---

### Step 20: Tests

**Test files to create:**

1. **`tests/Feature/Meeting/MeetingTypeTest.php`**
   - `it('creates a staff meeting with default type')`
   - `it('creates a meeting with a specific type')`
   - `it('shows department sections only for staff meetings during in-progress')`
   - `it('shows single general note for non-staff meetings')`

2. **`tests/Feature/Meeting/MeetingQuestionsTest.php`**
   - `it('creates default questions when staff meeting is created')`
   - `it('does not create questions for non-staff meetings')`
   - `it('allows editing questions while meeting is pending')`
   - `it('does not allow editing questions after meeting starts')`

3. **`tests/Feature/Meeting/MeetingReportTest.php`**
   - `it('allows staff to submit a report when unlocked')`
   - `it('does not allow report submission before unlock window')`
   - `it('does not allow report submission after meeting starts')`
   - `it('allows updating an existing report')`
   - `it('shows report data in department notes display')`

4. **`tests/Feature/Meeting/MeetingReportReminderTest.php`**
   - `it('sends reminders to staff who have not submitted reports')`
   - `it('does not send reminders to staff who already submitted')`
   - `it('does not send reminders outside the notification window')`
   - `it('does not send duplicate reminders')`

---

## Edge Cases

- **User changes department between report submission and meeting** — Report stays tied to user, displayed in whatever department they had when submitted. Use the user's current `staff_department` for display.
- **Meeting type changed after questions created** — If changed away from staff_meeting, questions and reports remain in DB but are hidden in UI. If changed back, they reappear.
- **No questions on a staff meeting** — Report button should not appear if meeting has 0 questions.
- **Staff member demoted after submitting report** — Report still exists, shown in meeting archive. Real-time display filters by current rank.
- **Scheduling next meeting** — Type carries over. Default questions auto-created for new staff meeting.

## Known Risks

- **Large teams**: Many report cards could clutter department sections. The modal approach (small cards + click-to-expand) mitigates this.
- **Notification flooding**: Using the notification tracker (creating report rows with null `submitted_at`) prevents duplicate reminders. Only one reminder per user per meeting.

## Verification

1. `php artisan migrate:fresh --seed` passes
2. `./vendor/bin/pest` passes with zero failures
3. Create a Staff Meeting → verify 4 default questions appear
4. Create a Board Meeting → verify no questions, no department sections when started
5. Fill out report form → verify data saved, button text changes to "Update Report"
6. Start meeting → verify reports are locked, cards show in department sections
7. Click a report card → modal shows full answers
8. Complete meeting → verify reports appear in department Ready Room notes
9. Test notification command: `php artisan meetings:send-report-reminders` — verify staff without reports get notified
