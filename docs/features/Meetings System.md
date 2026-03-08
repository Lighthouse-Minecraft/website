# Meetings System -- Technical Documentation

> **Audience:** Project owner, developers, AI agents
> **Generated:** 2026-03-07
> **Generator:** `/document-feature` skill

---

## Table of Contents

1. [Overview](#1-overview)
2. [Database Schema](#2-database-schema)
3. [Models & Relationships](#3-models--relationships)
4. [Enums Reference](#4-enums-reference)
5. [Authorization & Permissions](#5-authorization--permissions)
6. [Routes](#6-routes)
7. [User Interface Components](#7-user-interface-components)
8. [Actions (Business Logic)](#8-actions-business-logic)
9. [Notifications](#9-notifications)
10. [Background Jobs](#10-background-jobs)
11. [Console Commands & Scheduled Tasks](#11-console-commands--scheduled-tasks)
12. [Services](#12-services)
13. [Activity Log Entries](#13-activity-log-entries)
14. [Data Flow Diagrams](#14-data-flow-diagrams)
15. [Configuration](#15-configuration)
16. [Test Coverage](#16-test-coverage)
17. [File Map](#17-file-map)
18. [Known Issues & Improvement Opportunities](#18-known-issues--improvement-opportunities)

---

## 1. Overview

The Meetings System is a comprehensive staff meeting management feature that handles the complete lifecycle of meetings for the Lighthouse community. It supports scheduling, real-time collaborative note-taking during meetings, staff check-in reports, AI-powered community notes generation, task management, attendance tracking, and command-level engagement dashboards.

The system primarily serves staff members (Jr Crew, Crew Members, and Officers) and administrators. Officers and users with the "Meeting Secretary" role can create and manage meetings. All staff can submit pre-meeting check-in reports and collaborate on notes during meetings. The system supports multiple meeting types (Staff, Board, Community, Other), but staff meetings receive special treatment with department-based note sections, staff check-in questions, and report cards.

The meeting lifecycle progresses through defined states: **Pending** (scheduling and pre-meeting check-ins), **InProgress** (real-time note-taking with optimistic locking), **Finalizing** (minutes compilation with AI formatting for community-facing notes), and **Completed** (archived and viewable). Community members can view sanitized community updates derived from completed staff meetings.

The system also includes a Task subsystem for department-level to-do tracking, an iteration-based engagement dashboard for the Command department, and automated pre-meeting check-in reminders sent via email, Pushover, and Discord DM.

---

## 2. Database Schema

### `meetings` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | No | auto | |
| title | string | No | | Meeting title |
| type | string | No | `staff_meeting` | MeetingType enum value |
| day | string | No | | Date string (Y-m-d) |
| scheduled_time | timestamp | No | | Stored in UTC |
| start_time | timestamp | Yes | null | Set when meeting starts |
| end_time | timestamp | Yes | null | Set when meeting ends |
| is_public | boolean | No | false | Whether non-staff can view |
| summary | string | Yes | null | Brief summary |
| status | string | No | `pending` | MeetingStatus enum value |
| agenda | text | Yes | null | Copied from agenda note on start |
| minutes | text | Yes | null | Compiled from all department notes |
| community_minutes | text | Yes | null | AI-formatted public version |
| show_community_updates | boolean | No | true | Whether to display on community updates page |
| created_at | timestamp | No | | |
| updated_at | timestamp | No | | |

**Migration(s):**
- `database/migrations/2025_08_08_034207_create_meetings_table.php`
- `database/migrations/2025_08_12_234133_update_meetings_add_status_field.php`
- `database/migrations/2025_08_15_000816_update_meetings_add_minutes_fields.php`
- `database/migrations/2026_03_05_150956_add_type_to_meetings_table.php`
- `database/migrations/2026_03_05_160632_add_show_community_updates_to_meetings_table.php`

### `meeting_notes` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | No | auto | |
| created_by | foreignId | No | | FK to users.id |
| meeting_id | foreignId | No | | FK to meetings.id |
| section_key | string | No | | e.g., 'agenda', 'general', 'command', 'community' |
| content | text | Yes | null | Note content |
| locked_by | foreignId | Yes | null | FK to users.id (optimistic lock) |
| locked_at | timestamp | Yes | null | When lock was acquired |
| lock_updated_at | timestamp | Yes | null | Heartbeat timestamp for lock expiry |
| created_at | timestamp | No | | |
| updated_at | timestamp | No | | |

**Foreign Keys:** `created_by -> users.id`, `meeting_id -> meetings.id`, `locked_by -> users.id`
**Migration(s):** `database/migrations/2025_08_11_021754_create_meeting_notes_table.php`

### `meeting_user` table (pivot)

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | No | auto | |
| meeting_id | foreignId | No | | FK to meetings.id, cascade delete |
| user_id | foreignId | No | | FK to users.id, cascade delete |
| added_at | timestamp | No | | When attendee was added |
| created_at | timestamp | No | | |
| updated_at | timestamp | No | | |

**Indexes:** Unique constraint on `[meeting_id, user_id]`
**Migration(s):** `database/migrations/2025_08_15_132410_create_meeting_user_table.php`

### `meeting_questions` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | No | auto | |
| meeting_id | foreignId | No | | FK to meetings.id, cascade delete |
| question_text | text | No | | The question for staff check-in |
| sort_order | integer | No | 0 | Display order |
| created_at | timestamp | No | | |
| updated_at | timestamp | No | | |

**Migration(s):** `database/migrations/2026_03_05_150956_create_meeting_questions_table.php`

### `meeting_reports` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | No | auto | |
| meeting_id | foreignId | No | | FK to meetings.id, cascade delete |
| user_id | foreignId | No | | FK to users.id, cascade delete |
| submitted_at | timestamp | Yes | null | Null = not yet submitted |
| notified_at | timestamp | Yes | null | When reminder was sent |
| created_at | timestamp | No | | |
| updated_at | timestamp | No | | |

**Indexes:** Unique constraint on `[meeting_id, user_id]`
**Migration(s):** `database/migrations/2026_03_05_150957_create_meeting_reports_table.php`

### `meeting_report_answers` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | No | auto | |
| meeting_report_id | foreignId | No | | FK to meeting_reports.id, cascade delete |
| meeting_question_id | foreignId | No | | FK to meeting_questions.id, cascade delete |
| answer | text | Yes | null | Staff member's response |
| created_at | timestamp | No | | |
| updated_at | timestamp | No | | |

**Indexes:** Unique constraint on `[meeting_report_id, meeting_question_id]`
**Migration(s):** `database/migrations/2026_03_05_150958_create_meeting_report_answers_table.php`

### `tasks` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | No | auto | |
| name | string | No | | Task description |
| section_key | string | Yes | null | Department key (e.g., 'command', 'engineer') |
| status | string | No | | TaskStatus enum value |
| assigned_meeting_id | foreignId | Yes | null | FK to meetings.id (originally required, made nullable) |
| created_by | foreignId | No | | FK to users.id |
| assigned_to_user_id | foreignId | Yes | null | FK to users.id, null on delete |
| completed_by | foreignId | Yes | null | FK to users.id |
| completed_at | timestamp | Yes | null | |
| completed_meeting_id | foreignId | Yes | null | FK to meetings.id |
| archived_meeting_id | foreignId | Yes | null | FK to meetings.id |
| archived_at | timestamp | Yes | null | |
| created_at | timestamp | No | | |
| updated_at | timestamp | No | | |

**Migration(s):**
- `database/migrations/2025_08_16_163658_create_tasks_table.php`
- `database/migrations/2025_08_19_191557_update_tasks_nulify_meeting_field.php`
- `database/migrations/2026_02_26_215111_add_assigned_to_user_id_to_tasks_table.php`

### `roles` table (Meeting Secretary role)

A "Meeting Secretary" role is seeded via migration to grant meeting management permissions to non-Officer staff.

**Migration(s):** `database/migrations/2025_08_09_232055_populate_roles_add_meeting_secretary.php`

---

## 3. Models & Relationships

### Meeting (`app/Models/Meeting.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `notes()` | hasMany | MeetingNote | All notes for this meeting |
| `attendees()` | belongsToMany | User | Pivot: `meeting_user` with `added_at` timestamp |
| `questions()` | hasMany | MeetingQuestion | Check-in questions |
| `reports()` | hasMany | MeetingReport | Staff check-in reports |

**Casts:**
- `type` => `MeetingType`
- `status` => `MeetingStatus`

**Key Methods:**
- `startMeeting(): void` -- Sets status to InProgress, records start_time
- `endMeeting(): void` -- Sets status to Finalizing, records end_time
- `completeMeeting(): void` -- Sets status to Completed
- `isStaffMeeting(): bool` -- Returns true if type is StaffMeeting
- `isReportUnlocked(): bool` -- True if meeting is Pending and scheduled_time is within configured unlock window (default 7 days)
- `isReportLocked(): bool` -- True if meeting has started (status is not Pending)

### MeetingNote (`app/Models/MeetingNote.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `createdBy()` | belongsTo | User | Note creator |
| `meeting()` | belongsTo | Meeting | Parent meeting |
| `lockedBy()` | belongsTo | User | Current lock holder (nullable) |

**Eager Loads:** `lockedBy`, `createdBy` (via `$with`)

### MeetingQuestion (`app/Models/MeetingQuestion.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `meeting()` | belongsTo | Meeting | Parent meeting |

### MeetingReport (`app/Models/MeetingReport.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `meeting()` | belongsTo | Meeting | Parent meeting |
| `user()` | belongsTo | User | Staff member who submitted |
| `answers()` | hasMany | MeetingReportAnswer | Individual question answers |

**Casts:**
- `submitted_at` => `datetime`
- `notified_at` => `datetime`

**Key Methods:**
- `isSubmitted(): bool` -- Returns true if `submitted_at` is not null

### MeetingReportAnswer (`app/Models/MeetingReportAnswer.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `report()` | belongsTo | MeetingReport | Parent report |
| `question()` | belongsTo | MeetingQuestion | The question being answered |

### Task (`app/Models/Task.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `createdBy()` | belongsTo | User | Task creator |
| `completedBy()` | belongsTo | User | Who completed it |
| `completedMeeting()` | belongsTo | Meeting | Meeting where completed |
| `assignedMeeting()` | belongsTo | Meeting | Meeting where created |
| `assignedTo()` | belongsTo | User | Assigned staff member |

**Casts:**
- `status` => `TaskStatus`
- `completed_at` => `datetime`
- `archived_at` => `datetime`

---

## 4. Enums Reference

### MeetingType (`app/Enums/MeetingType.php`)

| Case | Value | Label |
|------|-------|-------|
| `StaffMeeting` | `staff_meeting` | Staff Meeting |
| `BoardMeeting` | `board_meeting` | Board Meeting |
| `CommunityMeeting` | `community_meeting` | Community Meeting |
| `Other` | `other` | Other |

Helper methods: `label(): string`

### MeetingStatus (`app/Enums/MeetingStatus.php`)

| Case | Value | Label |
|------|-------|-------|
| `Pending` | `pending` | Pending |
| `InProgress` | `in_progress` | In Progress |
| `Finalizing` | `finalizing` | Finalizing |
| `Completed` | `completed` | Completed |
| `Cancelled` | `cancelled` | Cancelled |
| `Archived` | `archived` | Archived |

Helper methods: `label(): string`

### TaskStatus (`app/Enums/TaskStatus.php`)

| Case | Value | Label |
|------|-------|-------|
| `Pending` | `pending` | Pending |
| `InProgress` | `in-progress` | In Progress |
| `Completed` | `completed` | Completed |
| `Archived` | `archived` | Archived |

Helper methods: `label(): string`

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

| Gate Name | Who Can Pass | Logic Summary |
|-----------|-------------|---------------|
| `view-ready-room` | Admin or JrCrew+ | Access to the Staff Ready Room page |
| `view-ready-room-command` | Admin, Officer+, or JrCrew+ in Command dept | View Command department tab |
| `view-ready-room-chaplain` | Admin, Officer+, or JrCrew+ in Chaplain dept | View Chaplain department tab |
| `view-ready-room-engineer` | Admin, Officer+, or JrCrew+ in Engineer dept | View Engineer department tab |
| `view-ready-room-quartermaster` | Admin, Officer+, or JrCrew+ in Quartermaster dept | View Quartermaster department tab |
| `view-ready-room-steward` | Admin, Officer+, or JrCrew+ in Steward dept | View Steward department tab |
| `view-all-community-updates` | Traveler+ or Admin | See full paginated community updates history |
| `view-command-dashboard` | Admin or Command department staff | Access command engagement dashboards |

### Policies

#### MeetingPolicy (`app/Policies/MeetingPolicy.php`)

**`before()` hook:** Admin or Command Officer bypasses all checks

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `viewAny` | CrewMember+ or Meeting Secretary | Can see meetings list |
| `view` | CrewMember+ or Meeting Secretary | Can view individual meeting |
| `attend` | CrewMember+ or Meeting Secretary | Can join/attend a meeting |
| `viewAnyPrivate` | Officer+ or Meeting Secretary | Can see private meetings |
| `viewAnyPublic` | Resident+ or Meeting Secretary | Can see public meetings |
| `create` | Officer+ or Meeting Secretary | Can create new meetings |
| `update` | Officer+ or Meeting Secretary | Can manage meeting lifecycle |
| `delete` | Nobody | Always returns false |

#### MeetingNotePolicy (`app/Policies/MeetingNotePolicy.php`)

**`before()` hook:** Admin bypasses all checks

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `create` | Officer+ or Meeting Secretary | Can create new notes |
| `update` | Officer+ or Meeting Secretary | Can edit/lock/unlock notes |
| `updateSave` | Current lock holder only | Can save content (must match `locked_by`) |

#### TaskPolicy (`app/Policies/TaskPolicy.php`)

**`before()` hook:** Admin bypasses all checks

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `viewAny` | Nobody | Returns false |
| `view` | Nobody | Returns false |
| `create` | CrewMember+ | Can create tasks |
| `update` | CrewMember+ | Can edit tasks |
| `delete` | Nobody | Returns false |

### Permissions Matrix

| User Type | View Meetings | Create Meeting | Manage Meeting | Take Notes | Create Tasks | Submit Check-In | View Community Updates | View Command Dashboard |
|-----------|--------------|----------------|----------------|------------|-------------|-----------------|----------------------|----------------------|
| Guest | No | No | No | No | No | No | Latest only | No |
| Stowaway | No | No | No | No | No | No | Latest only | No |
| Traveler+ | Public only | No | No | No | No | No | Full history | No |
| Jr Crew | Yes | No | No | No | No | Yes* | Full history | Dept only |
| Crew Member | Yes | No | No | No | Yes | Yes | Full history | Dept only |
| Officer | Yes | Yes | Yes | Yes | Yes | Yes | Full history | If Command |
| Meeting Secretary | Yes | Yes | Yes | Yes | No** | Yes | Full history | No |
| Admin | Full | Full | Full | Full | Full | Yes | Full | Full |

\* Jr Crew can submit check-ins but are excluded from attendance tracking.
\** Meeting Secretary role doesn't grant TaskPolicy create -- only CrewMember+ rank does.

---

## 6. Routes

| Method | URL | Middleware | Handler | Route Name |
|--------|-----|-----------|---------|------------|
| GET | `/ready-room` | auth | `DashboardController@readyRoom` | `ready-room.index` |
| GET | `/meetings` | auth | `MeetingController@index` | `meeting.index` |
| GET | `/meetings/{meeting}/manage` | auth | `MeetingController@edit` | `meeting.edit` |
| GET | `/meetings/{meeting}/report` | auth | Volt: `meeting.report-form` | `meeting.report` |

---

## 7. User Interface Components

### Meeting List
**File:** `resources/views/livewire/meetings/list.blade.php`
**Route:** `/meetings` (via `meeting.index` layout: `resources/views/meeting/index.blade.php`)

**Purpose:** Lists all meetings in the ACP for staff.

**Authorization:** `MeetingPolicy::viewAny` (enforced in controller)

**UI Elements:** Table of meetings with links to individual meeting management pages.

---

### Manage Meeting (Main Meeting Page)
**File:** `resources/views/livewire/meetings/manage-meeting.blade.php`
**Route:** `/meetings/{meeting}/manage` (via `meeting.edit` layout: `resources/views/meeting/edit.blade.php`)

**Purpose:** Central meeting management page handling the full lifecycle.

**Authorization:** `MeetingPolicy::view` (enforced in controller), `MeetingPolicy::update` for lifecycle actions.

**User Actions Available:**
- **Start Meeting** -> `StartMeetingConfirmed()` -> copies agenda note to meeting record, sets status to InProgress
- **Join Meeting** -> `joinMeeting()` -> adds current user to attendees pivot
- **End Meeting** -> `EndMeetingConfirmed()` -> compiles all department notes into minutes, creates community note, triggers AI formatting, sets status to Finalizing
- **Process AI Formatting** -> `processAiFormatting()` -> calls `FormatMeetingNotesWithAi::run()`, updates community note
- **Reformat with AI** -> `reformatWithAi()` -> allows re-running AI with custom prompt
- **Toggle Community Updates** -> `toggleCommunityUpdates()` -> flips `show_community_updates` flag
- **Complete Meeting** -> `CompleteMeetingConfirmed()` -> saves community note to `community_minutes`, sets status to Completed, prompts to schedule next meeting
- **Schedule Next Meeting** -> `scheduleNextMeeting()` -> creates new meeting with same type, seeds default questions

**UI Elements:**
- Meeting details card (type, time, status, attendees)
- Staff report status (check/x icons per department during Pending)
- Agenda editor (Pending) / read-only agenda (InProgress+)
- Department note sections with editors and task lists (InProgress)
- Meeting minutes display (Finalizing/Completed)
- Community notes editor with AI formatting (Finalizing)
- Community updates toggle (Finalizing)
- Confirmation modals for Start, End, Complete transitions
- Schedule next meeting modal (after Complete)
- AI prompt editor modal
- Polling: 30s for pending (day-of only), 60s for in-progress, 30s for finalizing, 0 for completed

---

### Create Meeting Modal
**File:** `resources/views/livewire/meeting/create-modal.blade.php`
**Route:** Modal on `/meetings` page

**Purpose:** Create a new meeting.

**Authorization:** `MeetingPolicy::create`

**User Actions Available:**
- Submit form -> creates Meeting with UTC-converted scheduled time, calls `CreateDefaultMeetingQuestions::run()` for staff meetings

**UI Elements:** Title input, type select (MeetingType enum), date picker, time input.

---

### Report Form (Staff Check-In)
**File:** `resources/views/livewire/meeting/report-form.blade.php`
**Route:** `/meetings/{meeting}/report` (route name: `meeting.report`)

**Purpose:** Staff submit pre-meeting check-in answers.

**Authorization:** `view-ready-room` gate. Only for staff meetings.

**User Actions Available:**
- Submit report -> `submitReport()` -> calls `SubmitMeetingReport::run()`

**UI Elements:**
- Meeting details card
- Textarea for each question (when report window is open)
- Read-only submitted answers (when meeting has started)
- Status callouts for locked/not-yet-open states

---

### Manage Questions
**File:** `resources/views/livewire/meeting/manage-questions.blade.php`
**Route:** Embedded in manage-meeting page (Pending staff meetings only)

**Purpose:** CRUD for meeting check-in questions.

**Authorization:** `MeetingPolicy::update`

**User Actions Available:**
- Add question -> `addQuestion()` -> creates MeetingQuestion with next sort_order
- Remove question -> `removeQuestion()` -> deletes MeetingQuestion
- Reorder -> `moveUp()`/`moveDown()` -> swaps sort_order values

**UI Elements:** Accordion with question list, reorder buttons (chevron up/down), trash button, add input.

---

### Department Section
**File:** `resources/views/livewire/meeting/department-section.blade.php`
**Route:** Embedded in manage-meeting page (InProgress/Finalizing)

**Purpose:** Renders note editor + task list for each department section.

**UI Elements:** Department heading, description, report cards (for staff meetings), note editor, task list. Special handling for 'general' (no report cards), 'community' (no task list, centered layout).

---

### Department Report Cards
**File:** `resources/views/livewire/meeting/department-report-cards.blade.php`
**Route:** Embedded in department-section

**Purpose:** Shows staff member cards per department with check-in submission status.

**User Actions Available:**
- Click staff card -> `viewReport()` -> opens modal with individual report answers

**UI Elements:** Grid of staff member cards with avatar, name, check/x icons. Modal shows Q&A for selected staff member.

---

### Notes Display (Ready Room)
**File:** `resources/views/livewire/meeting/notes-display.blade.php`
**Route:** Embedded in ready-room department pages

**Purpose:** Shows historical meeting notes for a specific department section.

**UI Elements:** Accordion of completed meetings with department notes, staff reports (for staff meetings), and full minutes. Paginated. Blade-only component (no PHP class).

---

### Note Editor
**File:** `resources/views/livewire/note/editor.blade.php`
**Route:** Embedded in department-section and manage-meeting

**Purpose:** Collaborative note editor with optimistic locking.

**Authorization:** `MeetingNotePolicy::create` (create), `MeetingNotePolicy::update` (edit/unlock), `MeetingNotePolicy::updateSave` (save)

**User Actions Available:**
- Create Note -> `CreateNote()` -> creates MeetingNote with lock
- Edit Note -> `EditNote()` -> acquires lock (checks for existing lock first)
- Save Note -> `SaveNote()` -> saves content, releases lock
- Update Note -> `UpdateNote()` -> periodic auto-save (retains lock, updates heartbeat)
- Heartbeat Check -> `HeartbeatCheck()` -> releases stale locks (configurable expiry)

**UI Elements:**
- Create button (when note doesn't exist)
- Read-only card with edit button (when note exists, not locked by user)
- Textarea with save button (when locked by current user)
- "Locked by [name]" indicator with disabled edit button (when locked by another)
- Polling: 10s during in-progress, 60s otherwise; 30s heartbeat for lock holder

---

### Manage Attendees
**File:** `resources/views/livewire/meeting/manage-attendees.blade.php`
**Route:** Embedded in manage-meeting page (InProgress only)

**Purpose:** Add attendees to an in-progress meeting.

**Authorization:** `MeetingPolicy::update`

**User Actions Available:**
- Add attendees -> `addAttendees()` -> attaches selected users to meeting pivot

**UI Elements:** "Add Attendee" button, modal with checkbox list of eligible staff not already attending.

---

### Dashboard: Alert In-Progress Meeting
**File:** `resources/views/livewire/dashboard/alert-in-progress-meeting.blade.php`
**Route:** Embedded on main dashboard

**Purpose:** Shows callout banner when a meeting is in progress.

**Authorization:** `MeetingPolicy::attend` gate

**UI Elements:** Sky-colored callout with "Join Meeting" link to `meeting.edit`.

---

### Dashboard: Ready Room Upcoming Meetings
**File:** `resources/views/livewire/dashboard/ready-room-upcoming-meetings.blade.php`
**Route:** Embedded in Ready Room tabs

**Purpose:** Shows next 3 pending meetings with check-in submission status.

**UI Elements:** List of upcoming meetings with title, date, and "Submit Check-In" / "Update Check-In" buttons (for staff meetings with questions in the unlock window).

---

### Dashboard: Ready Room (Main)
**File:** `resources/views/livewire/dashboard/ready-room.blade.php`
**Route:** Part of Ready Room page

**Purpose:** Tab-based department navigation for the Staff Ready Room.

**Authorization:** Department-specific gates (`view-ready-room-command`, etc.)

**UI Elements:** Segmented tab navigation (My Board, Command, Chaplain, Engineer, Quartermaster, Steward). My Board shows upcoming meetings + assigned tasks. Department tabs show department-specific content.

---

### Dashboard: Ready Room Department
**File:** `resources/views/livewire/dashboard/ready-room-department.blade.php`
**Route:** Embedded in ready-room tabs

**Purpose:** Department-specific view with upcoming meetings, task list, and meeting notes history.

**UI Elements:** Upcoming meetings widget, department task list, meeting notes display.

---

### Dashboard: Ready Room My Tasks
**File:** `resources/views/livewire/dashboard/ready-room-my-tasks.blade.php`
**Route:** Embedded in ready-room "My Board" tab

**Purpose:** Shows tasks assigned to the current user grouped by department.

**UI Elements:** Grid of department cards with task checkboxes. Empty state with clipboard icon.

---

### Task: Show Task
**File:** `resources/views/livewire/task/show-task.blade.php`
**Route:** Embedded in task lists

**Purpose:** Individual task display with toggle, edit, and archive actions.

**Authorization:** `TaskPolicy::update` for edit button

**User Actions Available:**
- Toggle completion -> `toggleCompletion()` -> marks as Completed or reverts to Pending
- Edit task -> `updateTask()` -> updates name and assignment
- Archive task -> `markAsArchived()` -> sets status to Archived (only during meetings)

**UI Elements:** Checkbox with label, assigned-to badge, edit button (pencil icon), archive button (during meetings for completed tasks), edit modal with name input and assign-to dropdown.

---

### Task: Department List
**File:** `resources/views/livewire/task/department-list.blade.php`
**Route:** Embedded in department sections

**Purpose:** CRUD for department tasks within meetings.

**Authorization:** `TaskPolicy::create` for adding tasks

**User Actions Available:**
- Add task -> `addTask()` -> creates new Task with section_key and meeting association

**UI Elements:** Sections for completed, in-progress, and archived tasks. Add task input with button.

---

### Community Updates List
**File:** `resources/views/livewire/community-updates/list.blade.php`
**Route:** Community Updates page

**Purpose:** Public-facing list of community updates from completed meetings.

**Authorization:** `view-all-community-updates` gate for paginated history (otherwise shows latest only)

**UI Elements:** Accordion of completed meetings with community_minutes content. Pagination for privileged users. "Join our community" CTA for non-members.

---

### Command Dashboard: Staff Engagement
**File:** `resources/views/livewire/dashboard/command-staff-engagement.blade.php`
**Route:** Embedded in command dashboard

**Purpose:** Staff-level engagement metrics using iteration boundaries.

**Authorization:** `view-command-dashboard` gate

**UI Elements:** Paginated table of staff with todos worked/open, tickets worked/open, reports submitted (3mo), meetings attended (3mo). Staff detail modal with 3-month iteration history. Previous iteration numbers in parentheses.

---

### Command Dashboard: Department Engagement
**File:** `resources/views/livewire/dashboard/command-department-engagement.blade.php`
**Route:** Embedded in command dashboard

**Purpose:** Department-level metrics with timeline charts.

**Authorization:** `view-command-dashboard` gate

**UI Elements:** Table of departments with ticket/todo metrics. Discipline report cards. Staff report completion and meeting attendance percentages. Detail modals with Flux charts showing 3-month timelines.

---

### Command Dashboard: Community Engagement
**File:** `resources/views/livewire/dashboard/command-community-engagement.blade.php`
**Route:** Embedded in command dashboard

**Purpose:** Community growth metrics using iteration boundaries.

**Authorization:** `view-command-dashboard` gate

**UI Elements:** Cards showing new users, new MC accounts, new Discord accounts, pending MC verification, active users. Delta badges vs previous iteration. Detail modal with timeline chart.

---

## 8. Actions (Business Logic)

### SubmitMeetingReport (`app/Actions/SubmitMeetingReport.php`)

**Signature:** `handle(Meeting $meeting, User $user, array $answers): MeetingReport`

**Step-by-step logic:**
1. Validates meeting is a staff meeting
2. Validates report window is open (`isReportUnlocked()` and not `isReportLocked()`)
3. Validates meeting has questions
4. Wraps in `DB::transaction`
5. `updateOrCreate` on MeetingReport (sets `submitted_at` to now)
6. For each question: `updateOrCreate` on MeetingReportAnswer
7. Logs activity: `RecordActivity::run($report, 'submit_meeting_report', "Submitted check-in for {title}")`
8. Returns the MeetingReport

**Called by:** `resources/views/livewire/meeting/report-form.blade.php` (`submitReport()` method)

---

### FormatMeetingNotesWithAi (`app/Actions/FormatMeetingNotesWithAi.php`)

**Signature:** `handle(string $notes, ?string $systemPrompt = null): array{success: bool, text: string, error: ?string}`

**Step-by-step logic:**
1. Returns raw notes immediately if input is empty/whitespace
2. Reads AI provider and model from config (`lighthouse.ai.meeting_notes_provider`, `lighthouse.ai.meeting_notes_model`)
3. Falls back to raw notes if no API key is configured
4. Uses PrismPHP to send notes to AI with system prompt (from config or custom)
5. Returns formatted text on success
6. Falls back to raw notes on any exception, empty response, or missing config

**Called by:** `resources/views/livewire/meetings/manage-meeting.blade.php` (`processAiFormatting()`, `reformatWithAi()`)

---

### CreateDefaultMeetingQuestions (`app/Actions/CreateDefaultMeetingQuestions.php`)

**Signature:** `handle(Meeting $meeting): void`

**Step-by-step logic:**
1. Returns early if meeting is not a staff meeting
2. Returns early if meeting already has questions
3. Seeds 4 default questions:
   - "What did you accomplish since the last meeting?"
   - "What are you currently working on?"
   - "What are your plans until the next meeting?"
   - "Is there anything you need help with or want to discuss?"
4. Logs activity: `RecordActivity::run($meeting, 'create_default_meeting_questions', 'Default check-in questions seeded.')`

**Called by:** `resources/views/livewire/meeting/create-modal.blade.php`, `resources/views/livewire/meetings/manage-meeting.blade.php` (`scheduleNextMeeting()`)

---

### GetIterationBoundaries (`app/Actions/GetIterationBoundaries.php`)

**Signature:** `handle(): array`

**Step-by-step logic:**
1. Checks cache (`iteration_boundaries`, 24h TTL)
2. Queries completed staff meetings ordered by `end_time` descending
3. If no completed meetings: returns fallback boundaries (current = last 14 days, no previous)
4. Computes `current_start` = last completed meeting's `end_time`, `current_end` = now
5. Computes `previous_start`/`previous_end` from second-to-last meeting (if exists)
6. Builds `iterations_3mo` array: array of iteration windows from completed staff meetings within last 3 months
7. Returns array with: `current_start`, `current_end`, `previous_start`, `previous_end`, `has_previous`, `previous_meeting`, `iterations_3mo`

**Called by:**
- `resources/views/livewire/dashboard/command-staff-engagement.blade.php`
- `resources/views/livewire/dashboard/command-department-engagement.blade.php`
- `resources/views/livewire/dashboard/command-community-engagement.blade.php`

---

## 9. Notifications

### MeetingReportReminderNotification (`app/Notifications/MeetingReportReminderNotification.php`)

**Triggered by:** `SendMeetingReportReminders` console command (via `TicketNotificationService::send()`)
**Recipient:** Staff members who haven't submitted check-ins
**Channels:** mail, Pushover, Discord DM (via `TicketNotificationService`)
**Mail subject:** "Reminder: Submit your check-in for {meeting title}"
**Content summary:** Reminder to submit staff check-in with link to `meeting.report` route
**Queued:** Yes

---

## 10. Background Jobs

Not applicable for this feature. The AI formatting and notification dispatch happen synchronously or via the console command scheduler.

---

## 11. Console Commands & Scheduled Tasks

### `meetings:send-report-reminders`
**File:** `app/Console/Commands/SendMeetingReportReminders.php`
**Scheduled:** Yes -- daily at 8:00 AM (configured in `routes/console.php`)

**What it does:**
1. Finds pending staff meetings with questions that are within the notification window (`meeting_report_notify_days`, default 3 days)
2. Gets all staff users (JrCrew+)
3. Batch-loads existing reports to avoid N+1
4. For each meeting/staff combination:
   - Skips if report already submitted (`submitted_at` set) or already notified (`notified_at` set)
   - Sends `MeetingReportReminderNotification` via `TicketNotificationService`
   - Creates or updates MeetingReport with `notified_at` timestamp
5. Reports total reminders sent

---

## 12. Services

Not applicable for this feature directly. The `TicketNotificationService` is used for sending notifications but is documented as part of the Tickets/Notifications feature.

---

## 13. Activity Log Entries

| Action String | Logged By | Subject Model | Description |
|---------------|-----------|---------------|-------------|
| `submit_meeting_report` | SubmitMeetingReport | MeetingReport | "Submitted check-in for {meeting title}" |
| `create_default_meeting_questions` | CreateDefaultMeetingQuestions | Meeting | "Default check-in questions seeded." |
| `toggle_community_updates` | manage-meeting component | Meeting | "Toggled community updates visibility." |

---

## 14. Data Flow Diagrams

### Creating a Meeting

```
Officer clicks "Create Meeting" button on /meetings page
  -> Modal opens (create-modal.blade.php)
    -> Fills: title, type, day, time
    -> Submit form
      -> createMeeting() method
        -> Parses scheduled_time to UTC via CarbonImmutable
        -> Meeting::create([...])
        -> CreateDefaultMeetingQuestions::run($meeting) (if staff meeting)
          -> Seeds 4 default questions
          -> RecordActivity::run($meeting, 'create_default_meeting_questions', ...)
        -> Flux::toast('Meeting created!', variant: 'success')
        -> Redirect to meeting.edit route
```

### Submitting a Staff Check-In

```
Staff member clicks "Submit Check-In" on upcoming meetings widget
  -> GET /meetings/{meeting}/report (middleware: auth)
    -> report-form.blade.php mount()
      -> $this->authorize('view-ready-room')
      -> Validates staff meeting, loads questions
      -> Loads existing report answers (if any)
    -> User fills textarea answers, clicks Submit
      -> submitReport()
        -> Checks isReportLocked() and isReportUnlocked()
        -> SubmitMeetingReport::run($meeting, auth()->user(), $answers)
          -> DB::transaction
          -> MeetingReport::updateOrCreate (sets submitted_at)
          -> MeetingReportAnswer::updateOrCreate for each question
          -> RecordActivity::run($report, 'submit_meeting_report', ...)
        -> $this->hasSubmitted = true
        -> Flux::toast('Check-in submitted successfully!', variant: 'success')
```

### Meeting Lifecycle: Start -> End -> Complete

```
Start Meeting:
  Officer clicks "Start Meeting" -> confirmation modal
    -> StartMeetingConfirmed()
      -> $this->authorize('update', $meeting)
      -> Copies agenda note content to $meeting->agenda
      -> $meeting->startMeeting() (status -> InProgress, start_time = now)
      -> Department note sections and task lists become visible

End Meeting:
  Officer clicks "End Meeting" -> confirmation modal
    -> EndMeetingConfirmed()
      -> Compiles general + all department notes into $meeting->minutes
      -> MeetingNote::updateOrCreate (community section with raw minutes)
      -> $meeting->endMeeting() (status -> Finalizing, end_time = now)
      -> Triggers processAiFormatting() via JS setTimeout
        -> FormatMeetingNotesWithAi::run($minutes)
        -> Updates community note with AI-formatted content

Complete Meeting:
  Officer clicks "Complete Meeting" -> confirmation modal
    -> CompleteMeetingConfirmed()
      -> Copies community note content to $meeting->community_minutes
      -> $meeting->completeMeeting() (status -> Completed)
      -> Opens "Schedule Next Meeting" modal
        -> scheduleNextMeeting() creates new meeting
        -> Redirects to new meeting's edit page
```

### Collaborative Note Editing (Optimistic Locking)

```
User sees note content with "Edit" button
  -> Clicks "Edit Notes"
    -> EditNote()
      -> $this->authorize('update', $note)
      -> Refreshes note to check for existing lock
      -> If locked by someone else: abort, show lock holder name
      -> Sets locked_by = auth()->id(), locked_at = now
      -> Shows textarea with content

User edits content in textarea
  -> wire:input.debounce.5s -> UpdateNote()
    -> Saves content, updates lock_updated_at (heartbeat)

User clicks "Save Notes"
  -> SaveNote()
    -> $this->authorize('updateSave', $note) (verifies lock holder)
    -> Prevents saving empty content over existing notes
    -> Updates content, calls UnlockNote()
    -> Releases lock (locked_by = null)

Lock expiry (background):
  -> wire:poll.30s -> HeartbeatCheck()
    -> If content unchanged and lock_updated_at > configured expiry
    -> UnlockNote() releases the stale lock
```

### Report Reminder Notifications

```
Daily at 8:00 AM (scheduled)
  -> meetings:send-report-reminders command
    -> Finds pending staff meetings within notification window
    -> For each staff member without submitted/notified report:
      -> TicketNotificationService::send($user, MeetingReportReminderNotification)
      -> Creates/updates MeetingReport with notified_at timestamp
```

---

## 15. Configuration

| Key | Default | Purpose |
|-----|---------|---------|
| `lighthouse.meeting_note_unlock_mins` | `4` | Minutes before an idle note lock auto-expires |
| `lighthouse.meeting_report_unlock_days` | `7` | Days before a meeting when check-in form opens |
| `lighthouse.meeting_report_notify_days` | `3` | Days before a meeting when reminders start |
| `lighthouse.ai.meeting_notes_system_prompt` | Long prompt (see config) | System prompt for AI community notes formatting |
| `lighthouse.ai.meeting_notes_provider` | `openai` | PrismPHP AI provider |
| `lighthouse.ai.meeting_notes_model` | `gpt-4o` | AI model for notes formatting |

**Environment variables:**
- `MEETING_NOTE_UNLOCK_MINS`
- `MEETING_REPORT_UNLOCK_DAYS`
- `MEETING_REPORT_NOTIFY_DAYS`
- `AI_MEETING_NOTES_PROMPT`
- `AI_MEETING_NOTES_PROVIDER`
- `AI_MEETING_NOTES_MODEL`

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/Meeting/AttendanceTest.php` | 15 | Attendee management, pivot table, display, permissions |
| `tests/Feature/Meeting/CommunityUpdatesTest.php` | 13 | Community updates page access, display, pagination, filtering |
| `tests/Feature/Meeting/EndMeetingAiFormattingTest.php` | 11 | AI formatting on end meeting, reformat button, authorization |
| `tests/Feature/Meeting/MeetingCreateTest.php` | 11 | Meeting creation form, validation, permissions, secretary role |
| `tests/Feature/Meeting/MeetingEditTest.php` | ~30 | Full meeting lifecycle, agenda, notes, workflow transitions, community updates toggle |
| `tests/Feature/Meeting/MeetingIndexTest.php` | 12 | Meetings list page, permissions, private meetings, navigation |
| `tests/Feature/Meeting/MeetingQuestionsTest.php` | 5 | Default questions, add/remove, deduplication |
| `tests/Feature/Meeting/MeetingReportTest.php` | 7 | Report submission, update, unlock/lock windows |
| `tests/Feature/Meeting/MeetingReportReminderTest.php` | 4 | Reminder command, skip submitted, notification window, deduplication |
| `tests/Feature/Meeting/MeetingTypeTest.php` | 7 | Meeting types, department sections, community updates defaults |
| `tests/Feature/Meeting/NoteEditorTest.php` | ~25 | Note creation, locking, saving, unlock expiry, permissions (create/edit/save/lock) |
| `tests/Feature/Meeting/NotesDisplayTest.php` | 6 | Notes display rendering, filtering, pagination |
| `tests/Feature/Meeting/TaskTest.php` | ~5 | Task creation, listing, permissions |
| `tests/Feature/CompleteMeetingTest.php` | 5 | Complete meeting button, modal, status transition, authorization |
| `tests/Feature/Dashboard/DashboardMeetingTest.php` | 1 | In-progress meeting alert on dashboard |
| `tests/Feature/Actions/FormatMeetingNotesWithAiTest.php` | 6 | AI action: no API key, success, empty response, custom prompt, config |
| `tests/Feature/Actions/GetIterationBoundariesTest.php` | 7 | Iteration boundaries: fallback, current/previous, 3mo history, caching |
| `tests/Feature/Livewire/Dashboard/CommandStaffEngagementTest.php` | ~10 | Staff engagement widget, metrics, detail modal, permissions |
| `tests/Feature/Livewire/Dashboard/CommandDepartmentEngagementTest.php` | ~12 | Department engagement widget, ticket/todo metrics, charts, permissions |
| `tests/Feature/Livewire/Dashboard/CommandCommunityEngagementTest.php` | ~10 | Community engagement widget, user/account metrics, permissions |
| `tests/Feature/DepartmentReadyRoom/ReadyRoomPageTest.php` | ~10 | Ready room page access, department tabs, permissions |
| `tests/Feature/DepartmentReadyRoom/UpcomingMeetingsTest.php` | 2 | Upcoming meetings widget display |

### Coverage Gaps

- **Meeting cancellation** -- MeetingStatus has `Cancelled` and `Archived` cases but no UI or tests for cancelling/archiving a meeting
- **Task edit/assignment** -- Task assignment to users is implemented in UI but has minimal test coverage
- **Task archiving** -- Archive functionality exists but lacks dedicated tests
- **Note content sanitization** -- `{!! nl2br($content) !!}` in several views could be an XSS risk if content contains user-injected HTML; tests don't verify escaping
- **Concurrent note editing** -- Lock conflict scenarios (two users try to lock simultaneously) lack race condition tests
- **AI formatting failure recovery** -- The UI shows a toast on failure but no test verifies the user can still manually edit the community note after AI failure
- **Schedule next meeting** -- The auto-schedule flow after completing a meeting lacks test coverage

---

## 17. File Map

**Models:**
- `app/Models/Meeting.php`
- `app/Models/MeetingNote.php`
- `app/Models/MeetingQuestion.php`
- `app/Models/MeetingReport.php`
- `app/Models/MeetingReportAnswer.php`
- `app/Models/Task.php`

**Enums:**
- `app/Enums/MeetingType.php`
- `app/Enums/MeetingStatus.php`
- `app/Enums/TaskStatus.php`

**Actions:**
- `app/Actions/SubmitMeetingReport.php`
- `app/Actions/FormatMeetingNotesWithAi.php`
- `app/Actions/CreateDefaultMeetingQuestions.php`
- `app/Actions/GetIterationBoundaries.php`

**Policies:**
- `app/Policies/MeetingPolicy.php`
- `app/Policies/MeetingNotePolicy.php`
- `app/Policies/TaskPolicy.php`

**Gates:** `app/Providers/AuthServiceProvider.php` -- gates: `view-ready-room`, `view-ready-room-command`, `view-ready-room-chaplain`, `view-ready-room-engineer`, `view-ready-room-quartermaster`, `view-ready-room-steward`, `view-all-community-updates`, `view-command-dashboard`

**Notifications:**
- `app/Notifications/MeetingReportReminderNotification.php`

**Jobs:** None

**Services:** None (uses `TicketNotificationService` from Tickets feature)

**Controllers:**
- `app/Http/Controllers/MeetingController.php`
- `app/Http/Controllers/DashboardController.php` (readyRoom method)

**Volt Components:**
- `resources/views/livewire/meetings/list.blade.php`
- `resources/views/livewire/meetings/manage-meeting.blade.php`
- `resources/views/livewire/meeting/create-modal.blade.php`
- `resources/views/livewire/meeting/report-form.blade.php`
- `resources/views/livewire/meeting/manage-questions.blade.php`
- `resources/views/livewire/meeting/department-section.blade.php`
- `resources/views/livewire/meeting/department-report-cards.blade.php`
- `resources/views/livewire/meeting/notes-display.blade.php`
- `resources/views/livewire/meeting/manage-attendees.blade.php`
- `resources/views/livewire/note/editor.blade.php`
- `resources/views/livewire/task/show-task.blade.php`
- `resources/views/livewire/task/department-list.blade.php`
- `resources/views/livewire/dashboard/alert-in-progress-meeting.blade.php`
- `resources/views/livewire/dashboard/ready-room.blade.php`
- `resources/views/livewire/dashboard/ready-room-department.blade.php`
- `resources/views/livewire/dashboard/ready-room-upcoming-meetings.blade.php`
- `resources/views/livewire/dashboard/ready-room-my-tasks.blade.php`
- `resources/views/livewire/dashboard/command-staff-engagement.blade.php`
- `resources/views/livewire/dashboard/command-department-engagement.blade.php`
- `resources/views/livewire/dashboard/command-community-engagement.blade.php`
- `resources/views/livewire/community-updates/list.blade.php`

**Blade Layouts/Views:**
- `resources/views/meeting/index.blade.php`
- `resources/views/meeting/edit.blade.php`
- `resources/views/dashboard/ready-room.blade.php`

**Routes:**
- `meeting.index` -- GET `/meetings`
- `meeting.edit` -- GET `/meetings/{meeting}/manage`
- `meeting.report` -- GET `/meetings/{meeting}/report`
- `ready-room.index` -- GET `/ready-room`

**Migrations:**
- `database/migrations/2025_08_08_034207_create_meetings_table.php`
- `database/migrations/2025_08_09_232055_populate_roles_add_meeting_secretary.php`
- `database/migrations/2025_08_11_021754_create_meeting_notes_table.php`
- `database/migrations/2025_08_12_234133_update_meetings_add_status_field.php`
- `database/migrations/2025_08_15_000816_update_meetings_add_minutes_fields.php`
- `database/migrations/2025_08_15_132410_create_meeting_user_table.php`
- `database/migrations/2025_08_16_163658_create_tasks_table.php`
- `database/migrations/2025_08_19_191557_update_tasks_nulify_meeting_field.php`
- `database/migrations/2026_02_26_215111_add_assigned_to_user_id_to_tasks_table.php`
- `database/migrations/2026_03_05_150956_add_type_to_meetings_table.php`
- `database/migrations/2026_03_05_150956_create_meeting_questions_table.php`
- `database/migrations/2026_03_05_150957_create_meeting_reports_table.php`
- `database/migrations/2026_03_05_150958_create_meeting_report_answers_table.php`
- `database/migrations/2026_03_05_160632_add_show_community_updates_to_meetings_table.php`

**Console Commands:**
- `app/Console/Commands/SendMeetingReportReminders.php`

**Tests:**
- `tests/Feature/Meeting/AttendanceTest.php`
- `tests/Feature/Meeting/CommunityUpdatesTest.php`
- `tests/Feature/Meeting/EndMeetingAiFormattingTest.php`
- `tests/Feature/Meeting/MeetingCreateTest.php`
- `tests/Feature/Meeting/MeetingEditTest.php`
- `tests/Feature/Meeting/MeetingIndexTest.php`
- `tests/Feature/Meeting/MeetingQuestionsTest.php`
- `tests/Feature/Meeting/MeetingReportTest.php`
- `tests/Feature/Meeting/MeetingReportReminderTest.php`
- `tests/Feature/Meeting/MeetingTypeTest.php`
- `tests/Feature/Meeting/NoteEditorTest.php`
- `tests/Feature/Meeting/NotesDisplayTest.php`
- `tests/Feature/Meeting/TaskTest.php`
- `tests/Feature/CompleteMeetingTest.php`
- `tests/Feature/Dashboard/DashboardMeetingTest.php`
- `tests/Feature/Actions/FormatMeetingNotesWithAiTest.php`
- `tests/Feature/Actions/GetIterationBoundariesTest.php`
- `tests/Feature/Livewire/Dashboard/CommandStaffEngagementTest.php`
- `tests/Feature/Livewire/Dashboard/CommandDepartmentEngagementTest.php`
- `tests/Feature/Livewire/Dashboard/CommandCommunityEngagementTest.php`
- `tests/Feature/DepartmentReadyRoom/ReadyRoomPageTest.php`
- `tests/Feature/DepartmentReadyRoom/UpcomingMeetingsTest.php`

**Config:**
- `config/lighthouse.php` -- keys: `meeting_note_unlock_mins`, `meeting_report_unlock_days`, `meeting_report_notify_days`, `ai.meeting_notes_system_prompt`, `ai.meeting_notes_provider`, `ai.meeting_notes_model`

---

## 18. Known Issues & Improvement Opportunities

1. **XSS Risk in Note Display**: Several views use `{!! nl2br($content) !!}` (e.g., manage-meeting, notes-display, community-updates/list) which renders raw HTML. While `nl2br()` converts newlines, the underlying content is not escaped. The notes-display component uses `{!! nl2br(e($departmentNote->content)) !!}` which is safe, but manage-meeting and community-updates/list do not use `e()`. This should be harmonized to use `{!! nl2br(e($content)) !!}` or `{!! Str::markdown($content) !!}` consistently.

2. **Missing Cancelled/Archived Lifecycle**: `MeetingStatus` includes `Cancelled` and `Archived` cases, but no UI or action supports transitioning a meeting to these states. They are dead enum cases.

3. **Duplicate Variable in HeartbeatCheck**: The `HeartbeatCheck()` method in `note/editor.blade.php` declares `$expirey_age` (line 136) and then immediately re-declares `$expiry_age` (line 140) and uses the second. The first declaration is dead code.

4. **Meeting Secretary + Task Permission Gap**: Users with the Meeting Secretary role can manage meetings but cannot create tasks (requires CrewMember rank). This may be intentional but could cause confusion if a non-crew Meeting Secretary tries to add tasks during a meeting.

5. **No Soft Deletes on Meetings**: Meetings cannot be deleted (policy always returns false) but there's no soft delete mechanism. If deletion is ever needed, there's no recovery path.

6. **Iteration Boundaries Cache Invalidation**: The `GetIterationBoundaries` action caches for 24 hours. If a meeting is completed, the dashboard won't reflect the updated iteration boundaries until the cache expires. No cache-bust mechanism exists.

7. **Missing Test Coverage**:
   - Schedule next meeting flow after completing a meeting
   - Task assignment and editing
   - Task archiving during meetings
   - Community updates toggle persistence
   - AI prompt editor custom prompt flow (end-to-end)
   - Concurrent lock acquisition race conditions

8. **Hardcoded Timezone**: The application uses `'America/New_York'` throughout the meeting views for display. This should ideally be configurable or user-specific.

9. **Inconsistent Component Naming**: Meeting-related components are split between `livewire/meetings/` (plural) and `livewire/meeting/` (singular) directories. This is a minor inconsistency in file organization.

10. **`notes-display.blade.php` Missing PHP Class**: This component relies on a `$this->meetings` computed property and `$sectionKey` variable but has no inline PHP class. It appears to depend on being rendered within a parent that provides these properties, which could be fragile.
