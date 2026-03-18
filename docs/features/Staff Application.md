# Staff Application -- Technical Documentation

> **Audience:** Project owner, developers, AI agents
> **Generated:** 2026-03-17
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

The Staff Application system allows community members to apply for staff positions within the Lighthouse Minecraft server community. It provides a structured, multi-stage review pipeline where applicants fill out configurable questionnaires, and Command Officers and Admins guide applications through submission, review, interview, background check, and final approval or denial.

The feature serves two audiences: **regular community members** who want to apply for open staff positions, and **staff reviewers** (Command Officers and Admins) who evaluate applications. Crew Members and above can view the review list but cannot take review actions.

Key concepts:
- **Staff Positions** (`StaffPosition`) represent roles within departments. Each position can be toggled to accept applications.
- **Application Questions** (`ApplicationQuestion`) are configurable question templates organized by category (Core, Officer, CrewMember, PositionSpecific) and type (ShortText, LongText, YesNo, Select).
- **Applications** (`StaffApplication`) track a 7-status workflow: Submitted → UnderReview → Interview → BackgroundCheck → Approved/Denied/Withdrawn.
- **Applicant Snapshot**: At submission time, the system captures the applicant's age, membership level, report/commendation counts, providing reviewers with a frozen-in-time view.
- **Discussion Integration**: The system automatically creates staff-only review threads and (at interview stage) interview discussion threads linked to the application.

---

## 2. Database Schema

### `application_questions` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned | NO | auto_increment | PK |
| question_text | varchar(255) | NO | — | |
| type | varchar(255) | NO | — | ApplicationQuestionType enum value |
| category | varchar(255) | NO | — | ApplicationQuestionCategory enum value |
| staff_position_id | bigint unsigned | YES | NULL | FK → staff_positions.id (cascadeOnDelete) |
| select_options | json | YES | NULL | Array of dropdown options |
| sort_order | unsigned integer | NO | 0 | |
| is_active | boolean | NO | true | |
| created_at | timestamp | YES | NULL | |
| updated_at | timestamp | YES | NULL | |

**Foreign Keys:** `staff_position_id` → `staff_positions.id` (cascadeOnDelete)
**Migration:** `database/migrations/2026_03_16_000001_create_application_questions_table.php`

---

### `staff_applications` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned | NO | auto_increment | PK |
| user_id | bigint unsigned | NO | — | FK → users.id (cascadeOnDelete) |
| staff_position_id | bigint unsigned | YES | NULL | FK → staff_positions.id (nullOnDelete) |
| status | varchar(255) | NO | — | ApplicationStatus enum value |
| reviewer_notes | text | YES | NULL | Appended with timestamps |
| background_check_status | varchar(255) | YES | NULL | BackgroundCheckStatus enum value |
| conditions | text | YES | NULL | e.g. "30-day trial period" |
| reviewed_by | bigint unsigned | YES | NULL | FK → users.id (nullOnDelete) |
| staff_review_thread_id | bigint unsigned | YES | NULL | FK → threads.id (nullOnDelete) |
| interview_thread_id | bigint unsigned | YES | NULL | FK → threads.id (nullOnDelete) |
| applicant_age | unsigned smallint | YES | NULL | Snapshot at submission |
| applicant_member_since | date | YES | NULL | Snapshot at submission |
| applicant_membership_level | varchar(255) | YES | NULL | Snapshot at submission |
| applicant_membership_level_since | date | YES | NULL | Snapshot at submission |
| applicant_report_count | unsigned integer | NO | 0 | Snapshot at submission |
| applicant_commendation_count | unsigned integer | NO | 0 | Snapshot at submission |
| created_at | timestamp | YES | NULL | |
| updated_at | timestamp | YES | NULL | |

**Indexes:** `(user_id, status)`, `(staff_position_id, status)`
**Foreign Keys:** `user_id` → `users.id`, `staff_position_id` → `staff_positions.id`, `reviewed_by` → `users.id`, `staff_review_thread_id` → `threads.id`, `interview_thread_id` → `threads.id`
**Migrations:**
- `database/migrations/2026_03_16_000002_create_staff_applications_table.php`
- `database/migrations/2026_03_16_000006_add_applicant_snapshot_to_staff_applications.php`

---

### `staff_application_answers` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned | NO | auto_increment | PK |
| staff_application_id | bigint unsigned | NO | — | FK → staff_applications.id (cascadeOnDelete) |
| application_question_id | bigint unsigned | NO | — | FK → application_questions.id (cascadeOnDelete) |
| answer | text | YES | NULL | |
| created_at | timestamp | YES | NULL | |
| updated_at | timestamp | YES | NULL | |

**Unique Constraint:** `(staff_application_id, application_question_id)`
**Migration:** `database/migrations/2026_03_16_000003_create_staff_application_answers_table.php`

---

### `staff_application_notes` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned | NO | auto_increment | PK |
| staff_application_id | bigint unsigned | NO | — | FK → staff_applications.id (cascadeOnDelete) |
| user_id | bigint unsigned | NO | — | FK → users.id (cascadeOnDelete) |
| body | text | NO | — | |
| created_at | timestamp | YES | NULL | |
| updated_at | timestamp | YES | NULL | |

**Migration:** `database/migrations/2026_03_16_000004_create_staff_application_notes_table.php`

---

### `staff_positions` table (modification)

| Column Added | Type | Nullable | Default | Notes |
|-------------|------|----------|---------|-------|
| accepting_applications | boolean | NO | false | After `sort_order` |

**Migration:** `database/migrations/2026_03_16_000005_add_accepting_applications_to_staff_positions.php`

---

### Seed Migrations

- **`2026_03_16_000007_seed_application_info_site_config.php`** — Creates a `SiteConfig` entry with key `application_info_page` containing markdown text explaining the application process to applicants.
- **`2026_03_16_000008_seed_application_questions.php`** — Seeds 13 default application questions across Core (10), Officer (2), and CrewMember (1) categories.

---

## 3. Models & Relationships

### StaffApplication (`app/Models/StaffApplication.php`)

**Relationships:**

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `user()` | BelongsTo | User | The applicant |
| `staffPosition()` | BelongsTo | StaffPosition | Position applied for |
| `reviewer()` | BelongsTo | User | Via `reviewed_by` column |
| `answers()` | HasMany | StaffApplicationAnswer | |
| `notes()` | HasMany | StaffApplicationNote | Staff-only notes |
| `staffReviewThread()` | BelongsTo | Thread | Staff review discussion |
| `interviewThread()` | BelongsTo | Thread | Interview discussion |

**Scopes:**
- `pending()` — Filters to non-terminal statuses: Submitted, UnderReview, Interview, BackgroundCheck
- `forPosition($positionId)` — Filters by staff_position_id

**Key Methods:**
- `isTerminal(): bool` — Returns true if status is Approved, Denied, or Withdrawn
- `isPending(): bool` — Returns true if status is not terminal

**Casts:**
- `status` → `ApplicationStatus`
- `background_check_status` → `BackgroundCheckStatus`
- `applicant_member_since` → `date`
- `applicant_membership_level_since` → `date`

---

### ApplicationQuestion (`app/Models/ApplicationQuestion.php`)

**Relationships:**

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `staffPosition()` | BelongsTo | StaffPosition | Only for PositionSpecific questions |
| `answers()` | HasMany | StaffApplicationAnswer | |

**Scopes:**
- `active()` — Where `is_active = true`
- `forCategory($category)` — Filters by category enum
- `ordered()` — Orders by `sort_order`, then `id`

**Key Methods:**
- `isPositionSpecific(): bool` — Returns true if category is PositionSpecific

**Casts:**
- `type` → `ApplicationQuestionType`
- `category` → `ApplicationQuestionCategory`
- `select_options` → `array`
- `is_active` → `boolean`

---

### StaffApplicationAnswer (`app/Models/StaffApplicationAnswer.php`)

**Relationships:**

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `application()` | BelongsTo | StaffApplication | |
| `question()` | BelongsTo | ApplicationQuestion | |

---

### StaffApplicationNote (`app/Models/StaffApplicationNote.php`)

**Relationships:**

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `application()` | BelongsTo | StaffApplication | |
| `user()` | BelongsTo | User | Staff member who wrote the note |

---

### StaffPosition (`app/Models/StaffPosition.php`) — Related Model

**Relevant Relationships:**

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `applications()` | HasMany | StaffApplication | |
| `applicationQuestions()` | HasMany | ApplicationQuestion | Position-specific questions |

**Relevant Methods:**
- `isAcceptingApplications(): bool`

**Relevant Scopes:**
- `acceptingApplications()` — Where `accepting_applications = true`

---

## 4. Enums Reference

### ApplicationStatus (`app/Enums/ApplicationStatus.php`)

| Case | Value | Label | Color | Terminal? |
|------|-------|-------|-------|-----------|
| Submitted | `submitted` | Submitted | blue | No |
| UnderReview | `under_review` | Under Review | amber | No |
| Interview | `interview` | Interview | purple | No |
| BackgroundCheck | `background_check` | Background Check | cyan | No |
| Approved | `approved` | Approved | emerald | Yes |
| Denied | `denied` | Denied | red | Yes |
| Withdrawn | `withdrawn` | Withdrawn | zinc | Yes |

**Methods:** `label()`, `color()`, `isTerminal()`

---

### BackgroundCheckStatus (`app/Enums/BackgroundCheckStatus.php`)

| Case | Value | Label | Color |
|------|-------|-------|-------|
| Pending | `pending` | Pending | amber |
| Passed | `passed` | Passed | emerald |
| Failed | `failed` | Failed | red |
| Waived | `waived` | Waived | zinc |

**Methods:** `label()`, `color()`

---

### ApplicationQuestionType (`app/Enums/ApplicationQuestionType.php`)

| Case | Value | Label |
|------|-------|-------|
| ShortText | `short_text` | Short Text |
| LongText | `long_text` | Long Text |
| YesNo | `yes_no` | Yes / No |
| Select | `select` | Dropdown |

**Methods:** `label()`

---

### ApplicationQuestionCategory (`app/Enums/ApplicationQuestionCategory.php`)

| Case | Value | Label |
|------|-------|-------|
| Core | `core` | Core |
| Officer | `officer` | Officer |
| CrewMember | `crew_member` | Crew Member |
| PositionSpecific | `position_specific` | Position Specific |

**Methods:** `label()`

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

| Gate Name | Who Can Pass | Logic Summary |
|-----------|-------------|---------------|
| `review-staff-applications` | Admin OR Command Department Officer+ | Shared lambda `$canManageApplications` |
| `manage-application-questions` | Admin OR Command Department Officer+ | Same shared lambda |

### Policies

#### StaffApplicationPolicy (`app/Policies/StaffApplicationPolicy.php`)

**`before()` hook:** Admins always return `true`. Command Department Officers+ always return `true`. All others fall through to per-method checks.

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `viewAny` | CrewMember+ rank | `$user->isAtLeastRank(StaffRank::CrewMember)` |
| `view` | Application owner only | `$user->id === $application->user_id` |
| `create` | Any non-brig user | `! $user->in_brig` |
| `update` | Always false | Only passable via `before()` hook (Admin/Command Officer) |
| `delete` | Always false | Only passable via `before()` hook (Admin/Command Officer) |

### Permissions Matrix

| User Type | Apply | View Own | View Review List | Review/Update | Manage Questions |
|-----------|-------|----------|------------------|---------------|------------------|
| Regular User | Yes (if not in brig) | Yes | No | No | No |
| Jr Crew | Yes | Yes | No | No | No |
| Crew Member (any dept) | Yes | Yes | Yes (read-only) | No | No |
| Officer (non-Command) | Yes | Yes | Yes (read-only) | No | No |
| Command Officer | Yes | Yes | Yes | Yes | Yes |
| Admin | Yes | Yes | Yes | Yes | Yes |
| User in Brig | No | Yes (existing) | No | No | No |

---

## 6. Routes

| Method | URL | Middleware | Handler | Route Name |
|--------|-----|-----------|---------|------------|
| GET | `/applications` | auth | `staff-applications.my-applications` (Volt) | `applications.index` |
| GET | `/applications/apply/{staffPosition}` | auth | `staff-applications.apply` (Volt) | `applications.apply` |
| GET | `/applications/{staffApplication}` | auth | `staff-applications.show` (Volt) | `applications.show` |
| GET | `/admin/applications` | auth, can:review-staff-applications | `staff-applications.review-list` (Volt) | `admin.applications.index` |
| GET | `/admin/applications/{staffApplication}` | auth, can:review-staff-applications | `staff-applications.review-detail` (Volt) | `admin.applications.show` |

**Note:** The Application Questions management page is embedded as a Livewire component (`<livewire:admin-manage-application-questions-page />`) inside the Admin Control Panel Tabs (`resources/views/livewire/admin-control-panel-tabs.blade.php`), gated by `@can('manage-application-questions')`.

---

## 7. User Interface Components

### My Applications
**File:** `resources/views/livewire/staff-applications/my-applications.blade.php`
**Route:** `/applications` (route name: `applications.index`)

**Purpose:** Lists the authenticated user's own staff applications.

**Authorization:** `abort_unless(Auth::check(), 403)` — any logged-in user.

**UI Elements:**
- Card list showing each application with position title, department badge, status badge, and submission date
- Each card links to the application detail view
- Empty state message if no applications exist

---

### Apply for Position
**File:** `resources/views/livewire/staff-applications/apply.blade.php`
**Route:** `/applications/apply/{staffPosition}` (route name: `applications.apply`)

**Purpose:** Multi-step application form for applying to a specific staff position.

**Authorization:** `$this->authorize('create', StaffApplication::class)` — non-brig users only. Also checks `$staffPosition->accepting_applications`.

**User Actions Available:**
- **Acknowledge Info** → advances past the pre-application info screen
- **Submit Application** → calls `SubmitApplication::run()` → redirects to `applications.index` with success toast

**UI Elements:**
- Position info card (title, rank badge, department badge, description, responsibilities, requirements)
- Pre-application info screen (markdown from `SiteConfig::getValue('application_info_page')`) with "Continue" button
- Dynamic question form based on position rank (Core + Officer/CrewMember + PositionSpecific)
- Input types: text input, textarea, radio (Yes/No), select dropdown
- Duplicate application guard (shows "Application Already in Progress" message)
- Answers prepopulated from the user's most recent prior application

---

### Application Detail (Applicant View)
**File:** `resources/views/livewire/staff-applications/show.blade.php`
**Route:** `/applications/{staffApplication}` (route name: `applications.show`)

**Purpose:** Shows a submitted application's details to the applicant.

**Authorization:** `$this->authorize('view', $staffApplication)` — only the application owner.

**User Actions Available:**
- **Withdraw Application** → calls `WithdrawApplication::run()` → redirects with toast (only shown for non-terminal applications, with `wire:confirm`)

**UI Elements:**
- Position title, rank/department badges, status badge, submission date
- Q&A cards showing all answers
- Approval details card (shown only when Approved): background check status, conditions
- Interview discussion link (when available)
- Withdraw button with confirmation dialog

---

### Review List (Staff View)
**File:** `resources/views/livewire/staff-applications/review-list.blade.php`
**Route:** `/admin/applications` (route name: `admin.applications.index`)

**Purpose:** Paginated table of all staff applications for reviewers.

**Authorization:** `$this->authorize('viewAny', StaffApplication::class)` — CrewMember+ rank (via policy `before()` hook, Command Officers/Admins have full access).

**UI Elements:**
- Status filter dropdown (all ApplicationStatus cases)
- Table with columns: Applicant (linked to profile), Position, Department, Status (badge), Submitted date, Review button
- Pagination (20 per page)

---

### Review Detail (Staff View)
**File:** `resources/views/livewire/staff-applications/review-detail.blade.php`
**Route:** `/admin/applications/{staffApplication}` (route name: `admin.applications.show`)

**Purpose:** Comprehensive application review interface for staff/admins. This is the primary workflow control point.

**Authorization:** `$this->authorize('viewAny', StaffApplication::class)` on mount. All action methods check `$this->authorize('update', $this->staffApplication)`.

**Auto-Transition:** On first staff view, Submitted applications are automatically transitioned to UnderReview via `UpdateApplicationStatus::run()`.

**User Actions Available:**
- **Schedule Interview** → opens modal → calls `UpdateApplicationStatus::run()` with Interview status → creates interview discussion
- **Move to Background Check** → opens modal → calls `UpdateApplicationStatus::run()` with BackgroundCheck status + BG check status
- **Update Background Check** → opens modal → calls `UpdateBackgroundCheck::run()` → updates BG status without changing application status
- **Approve Application** → opens modal → calls `ApproveApplication::run()` with BG check status + conditions + notes
- **Approve without Background Check** → opens modal → calls `ApproveApplication::run()` with BackgroundCheckStatus::Waived
- **Deny Application** → opens modal → calls `DenyApplication::run()` with notes
- **Add Staff Note** → inline form → calls `AddApplicationNote::run()`

**Context-Aware Action Buttons (by status):**
- **UnderReview:** Schedule Interview, Deny
- **Interview:** Move to Background Check, Approve without BG Check, Interview Discussion link, Deny
- **BackgroundCheck:** Update Background Check, Approve Application, Deny
- **Terminal states:** No action buttons shown

**UI Elements:**
- Applicant info card (name linked to profile, member since)
- Position info card (title, rank badge, department badge)
- Applicant Background snapshot card (age, member since, membership level, tenure, reports, commendations)
- Status card (status badge, submission date, BG check status badge, conditions)
- Discussion links (staff review, interview)
- Q&A response cards
- Staff notes section (private, with add-note form)
- Status change notes history (pre-formatted text)
- 6 modals: schedule-interview, bg-check, update-bg-check, approve, approve-no-bg, deny

---

### Admin Manage Application Questions
**File:** `resources/views/livewire/admin-manage-application-questions-page.blade.php`
**Embedded in:** `resources/views/livewire/admin-control-panel-tabs.blade.php` (tab: "application-questions")
**Route:** Accessed via Admin Control Panel (no dedicated route)

**Purpose:** CRUD interface for managing application question templates.

**Authorization:** `$this->authorize('manage-application-questions')` — Admin or Command Officer.

**User Actions Available:**
- **Create Question** → opens modal → validates → creates `ApplicationQuestion` record
- **Edit Question** → opens modal → validates → updates `ApplicationQuestion` record
- **Delete Question** → `wire:confirm` → deletes `ApplicationQuestion` record

**UI Elements:**
- Table with columns: Order, Question (truncated), Type badge, Category badge, Position, Active badge, Actions (edit/delete)
- Pagination (20 per page)
- Create modal with fields: question text, type select, category select, position select (if PositionSpecific), select options input (if Select type), sort order, active checkbox
- Edit modal with identical fields

---

## 8. Actions (Business Logic)

### SubmitApplication (`app/Actions/SubmitApplication.php`)

**Signature:** `handle(User $applicant, StaffPosition $position, array $answers): StaffApplication`

**Step-by-step logic:**
1. Validates position is accepting applications (`RuntimeException` if not)
2. Validates no pending application exists for this user/position combo (`RuntimeException` if duplicate)
3. Creates `StaffApplication` with `Submitted` status and applicant snapshot data (age, membership level, member since, report/commendation counts)
4. Creates `StaffApplicationAnswer` records for each question/answer pair
5. Creates a staff-only review discussion thread via `CreateTopic::run()` titled "Staff Application Review: {name} for {position}"
6. Adds Command Officers, department staff, and Admins as thread participants
7. Updates application with `staff_review_thread_id`
8. Logs activity: `RecordActivity::run($application, 'application_submitted', ...)`
9. Sends `ApplicationStatusChangedNotification` to applicant via `TicketNotificationService::send()` (channel: `staff_alerts`)
10. Sends `NewStaffApplicationNotification` to all Command Officers and Admins via `TicketNotificationService::send()` (channel: `staff_alerts`)

**Called by:** `staff-applications/apply.blade.php` → `submit()` method

---

### ApproveApplication (`app/Actions/ApproveApplication.php`)

**Signature:** `handle(StaffApplication $application, User $reviewer, BackgroundCheckStatus $bgCheck, ?string $conditions = null, ?string $notes = null): void`

**Step-by-step logic:**
1. Wrapped in `DB::transaction()`
2. Updates application: status → Approved, background_check_status, reviewed_by, conditions
3. Appends timestamp-prefixed reviewer notes
4. Auto-assigns applicant to the staff position: `$application->staffPosition->update(['user_id' => $application->user_id])`
5. Closes staff review thread with system message ("Application approved") and locks it
6. Closes interview thread (if exists) with system message and locks it
7. Logs activity: `RecordActivity::run($application, 'application_approved', ...)`
8. Sends `ApplicationStatusChangedNotification` to applicant (channel: `staff_alerts`)

**Called by:** `review-detail.blade.php` → `approve()` and `approveWithoutBgCheck()` methods

---

### DenyApplication (`app/Actions/DenyApplication.php`)

**Signature:** `handle(StaffApplication $application, User $reviewer, ?string $notes = null): void`

**Step-by-step logic:**
1. Wrapped in `DB::transaction()`
2. Updates application: status → Denied, reviewed_by
3. Appends timestamp-prefixed reviewer notes
4. Closes staff review thread with system message ("Application denied") and locks it
5. Closes interview thread (if exists) with system message and locks it
6. Logs activity: `RecordActivity::run($application, 'application_denied', ...)`
7. Sends `ApplicationStatusChangedNotification` to applicant (channel: `staff_alerts`)

**Called by:** `review-detail.blade.php` → `deny()` method

---

### WithdrawApplication (`app/Actions/WithdrawApplication.php`)

**Signature:** `handle(StaffApplication $application, User $applicant): void`

**Step-by-step logic:**
1. Validates applicant is the application owner (`RuntimeException` if not)
2. Validates application is not in a terminal state (`RuntimeException` if terminal)
3. Updates application: status → Withdrawn
4. Logs activity: `RecordActivity::run($application, 'application_withdrawn', ...)`

**Called by:** `staff-applications/show.blade.php` → `withdraw()` method

---

### UpdateApplicationStatus (`app/Actions/UpdateApplicationStatus.php`)

**Signature:** `handle(StaffApplication $application, ApplicationStatus $newStatus, User $reviewer, ?string $notes = null, ?BackgroundCheckStatus $bgCheck = null): void`

**Step-by-step logic:**
1. Wrapped in `DB::transaction()`
2. Updates application: status, reviewed_by
3. Appends timestamp-prefixed reviewer notes (if provided)
4. If new status is `Interview` and no interview thread exists:
   - Creates interview discussion thread via `CreateTopic::run()` titled "Interview: {name} for {position}"
   - Adds applicant, Officers, and department crew as participants
   - Updates application with `interview_thread_id`
5. Posts system message in staff review thread about the status change
6. Sets background_check_status if provided (used when status → BackgroundCheck)
7. Logs activity: `RecordActivity::run($application, 'application_status_changed', ...)`
8. Sends `ApplicationStatusChangedNotification` to applicant (channel: `staff_alerts`)

**Called by:** `review-detail.blade.php` → `mount()` (auto-transition), `confirmStartReview()`, `confirmScheduleInterview()`, `moveToBackgroundCheck()` methods

---

### UpdateBackgroundCheck (`app/Actions/UpdateBackgroundCheck.php`)

**Signature:** `handle(StaffApplication $application, User $reviewer, BackgroundCheckStatus $bgCheck, ?string $notes = null): void`

**Step-by-step logic:**
1. Wrapped in `DB::transaction()`
2. Updates application: background_check_status, reviewed_by
3. Appends timestamp-prefixed reviewer notes
4. Posts system message in staff review thread about BG check update
5. Logs activity: `RecordActivity::run($application, 'background_check_updated', ...)`

**Called by:** `review-detail.blade.php` → `updateBackgroundCheck()` method

---

### AddApplicationNote (`app/Actions/AddApplicationNote.php`)

**Signature:** `handle(StaffApplication $application, User $staff, string $body): StaffApplicationNote`

**Step-by-step logic:**
1. Creates `StaffApplicationNote` record with application, user, and body
2. Logs activity: `RecordActivity::run($application, 'application_note_added', ...)`
3. Returns the created note

**Called by:** `review-detail.blade.php` → `addNote()` method

---

## 9. Notifications

### ApplicationStatusChangedNotification (`app/Notifications/ApplicationStatusChangedNotification.php`)

**Triggered by:** `SubmitApplication`, `ApproveApplication`, `DenyApplication`, `UpdateApplicationStatus`
**Recipient:** The applicant (application owner)
**Channels:** mail, Pushover (via `TicketNotificationService`, channel: `staff_alerts`)
**Queued:** Yes (`ShouldQueue`)

**Mail subjects by status:**
- Submitted: "Application Received — {position}"
- UnderReview: "Application Under Review — {position}"
- Interview: "Interview Scheduled — {position}"
- BackgroundCheck: "Background Check in Progress — {position}"
- Approved: "Application Approved — {position}"
- Denied: "Application Update — {position}"
- Withdrawn: "Application Withdrawn — {position}"

**Action link:** `route('applications.show', $application)`

---

### NewStaffApplicationNotification (`app/Notifications/NewStaffApplicationNotification.php`)

**Triggered by:** `SubmitApplication`
**Recipient:** All Command Officers and Admins
**Channels:** mail, Pushover (via `TicketNotificationService`, channel: `staff_alerts`)
**Queued:** Yes (`ShouldQueue`)
**Mail subject:** "New Staff Application: {applicant name} for {position}"
**Content summary:** Informs reviewers that a new application was submitted, includes applicant name and position title.
**Action link:** `route('admin.applications.show', $application)`

---

## 10. Background Jobs

Not applicable for this feature.

---

## 11. Console Commands & Scheduled Tasks

Not applicable for this feature.

---

## 12. Services

Not directly applicable. Notifications are routed through the shared `TicketNotificationService` (`app/Services/TicketNotificationService.php`) which handles channel selection (mail/Pushover) based on user preferences and notification channel configuration.

---

## 13. Activity Log Entries

| Action String | Logged By | Subject Model | Description |
|---------------|-----------|---------------|-------------|
| `application_submitted` | SubmitApplication | StaffApplication | "{name} submitted application for {position}" |
| `application_approved` | ApproveApplication | StaffApplication | "Application approved by {reviewer}" |
| `application_denied` | DenyApplication | StaffApplication | "Application denied by {reviewer}" |
| `application_withdrawn` | WithdrawApplication | StaffApplication | "Application withdrawn by {name}" |
| `application_status_changed` | UpdateApplicationStatus | StaffApplication | "Application status changed to {status} by {reviewer}" |
| `application_note_added` | AddApplicationNote | StaffApplication | "Staff note added by {name}" |
| `background_check_updated` | UpdateBackgroundCheck | StaffApplication | "Background check updated to {status} by {reviewer}" |

---

## 14. Data Flow Diagrams

### Submitting an Application

```
User clicks "Apply" on staff directory page
  -> GET /applications/apply/{staffPosition} (middleware: auth)
    -> apply.blade.php::mount()
      -> $this->authorize('create', StaffApplication::class)
      -> Checks position.accepting_applications
      -> Checks for existing pending application
      -> Loads questions by category (Core + Officer/CrewMember + PositionSpecific)
      -> Prepopulates answers from last application (if any)
    -> User sees pre-application info screen
    -> User clicks "Continue" -> acknowledgeInfo()
    -> User fills out form and clicks "Submit Application"
      -> apply.blade.php::submit()
        -> $this->authorize('create', StaffApplication::class)
        -> $this->validate() — all answers required
        -> SubmitApplication::run($user, $position, $answers)
          -> StaffApplication created (status: Submitted, snapshot data captured)
          -> StaffApplicationAnswer records created
          -> Staff review discussion thread created (CreateTopic)
          -> RecordActivity::run($app, 'application_submitted', ...)
          -> ApplicationStatusChangedNotification → applicant
          -> NewStaffApplicationNotification → Command Officers + Admins
        -> Flux::toast('Application submitted successfully!')
        -> Redirect to applications.index
```

### Reviewing an Application (Staff)

```
Staff member clicks "Review" in review list
  -> GET /admin/applications/{staffApplication} (middleware: auth, can:review-staff-applications)
    -> review-detail.blade.php::mount()
      -> $this->authorize('viewAny', StaffApplication::class)
      -> Loads application with all relationships
      -> AUTO-TRANSITION: If status is Submitted and user can update:
        -> UpdateApplicationStatus::run($app, UnderReview, $user)
          -> Status → UnderReview
          -> RecordActivity::run(...)
          -> ApplicationStatusChangedNotification → applicant
```

### Approving an Application

```
Reviewer clicks "Approve Application" button (BackgroundCheck status)
  -> Modal opens: approve-modal
  -> Reviewer selects final BG check status, enters conditions/notes
  -> Clicks "Approve Application"
    -> review-detail.blade.php::approve()
      -> $this->authorize('update', $staffApplication)
      -> ApproveApplication::run($app, $user, $bgStatus, $conditions, $notes)
        -> StaffApplication updated: status → Approved, BG check, conditions
        -> StaffPosition updated: user_id → applicant (auto-assignment)
        -> Staff review thread closed + locked with system message
        -> Interview thread closed + locked with system message (if exists)
        -> RecordActivity::run($app, 'application_approved', ...)
        -> ApplicationStatusChangedNotification → applicant
      -> Flux::toast('Application approved. Applicant assigned to position.')
```

### Withdrawing an Application

```
Applicant clicks "Withdraw Application" on show page
  -> wire:confirm "Are you sure?"
  -> show.blade.php::withdraw()
    -> WithdrawApplication::run($app, Auth::user())
      -> Validates user is owner
      -> Validates not terminal
      -> StaffApplication updated: status → Withdrawn
      -> RecordActivity::run($app, 'application_withdrawn', ...)
    -> Flux::toast('Application withdrawn.')
    -> Redirect to applications.index
```

### Scheduling an Interview

```
Reviewer clicks "Schedule Interview" (UnderReview status)
  -> Modal opens: schedule-interview-modal
  -> Reviewer enters optional notes
  -> Clicks "Schedule Interview"
    -> review-detail.blade.php::confirmScheduleInterview()
      -> $this->authorize('update', $staffApplication)
      -> UpdateApplicationStatus::run($app, Interview, $user, $notes)
        -> Status → Interview
        -> Interview discussion thread created (CreateTopic)
        -> Applicant + Officers + dept crew added as participants
        -> System message posted in staff review thread
        -> RecordActivity::run(...)
        -> ApplicationStatusChangedNotification → applicant
      -> Flux::toast('Application moved to Interview. Discussion created.')
```

---

## 15. Configuration

| Key | Default | Purpose |
|-----|---------|---------|
| `application_info_page` (SiteConfig) | Markdown text (seeded) | Content shown on pre-application info screen before applicants see the form |

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/Actions/SubmitApplicationTest.php` | 6 | Application submission, answer creation, activity logging, validation guards |
| `tests/Feature/Actions/ApproveApplicationTest.php` | 2 | Approval status update, activity logging |
| `tests/Feature/Actions/DenyApplicationTest.php` | 2 | Denial status update, activity logging |
| `tests/Feature/Actions/WithdrawApplicationTest.php` | 4 | Withdrawal, activity logging, ownership validation, terminal state guard |
| `tests/Feature/Actions/AddApplicationNoteTest.php` | 2 | Note creation, activity logging |
| `tests/Feature/Actions/UpdateApplicationStatusTest.php` | 6 | Status updates, notes appending, activity logging, BG check, interview discussion creation, participant addition |
| `tests/Feature/Livewire/StaffApplicationApplyTest.php` | 3 | Page load for accepting position, position-not-accepting guard, brig guard |
| `tests/Feature/Livewire/StaffApplicationReviewTest.php` | 4 | Admin access, command officer access, crew member cross-department access, jr crew denial |
| `tests/Feature/Policies/StaffApplicationPolicyTest.php` | 8 | All policy methods tested for various user types |

### Test Case Inventory

**SubmitApplicationTest:**
- it('creates application with submitted status')
- it('creates answer records for each question')
- it('records activity log')
- it('rejects when position is not accepting applications')
- it('rejects when user already has pending application for position')
- it('allows submission when previous application was denied')

**ApproveApplicationTest:**
- it('sets status to approved with background check and conditions')
- it('records activity log')

**DenyApplicationTest:**
- it('sets status to denied with reviewer notes')
- it('records activity log')

**WithdrawApplicationTest:**
- it('sets status to withdrawn')
- it('records activity log')
- it('only allows applicant to withdraw own application')
- it('rejects withdrawal of terminal application')

**AddApplicationNoteTest:**
- it('creates a note on the application')
- it('records activity log')

**UpdateApplicationStatusTest:**
- it('updates status and reviewer')
- it('appends reviewer notes with timestamp')
- it('records activity log')
- it('sets background check status when moving to background check')
- it('creates interview discussion when status moves to interview')
- it('adds applicant as participant in interview discussion')

**StaffApplicationApplyTest:**
- it('loads the apply page for accepting position')
- it('blocks application when position not accepting')
- it('blocks application for user in brig')

**StaffApplicationReviewTest:**
- it('admin can see review list')
- it('command officer can see review list')
- it('crew member from any department can access review page')
- it('jr crew cannot access review page')

**StaffApplicationPolicyTest:**
- it('allows admin to review applications')
- it('allows command officer to review applications')
- it('allows crew member from any department to view applications')
- it('denies jr crew from viewing applications')
- it('allows user to view own application')
- it('denies user from viewing others application')
- it('allows non-brig user to create applications')
- it('denies user in brig from creating applications')

### Coverage Gaps

- **UpdateBackgroundCheck action** has no dedicated test file
- **ApproveApplication** tests do not verify: position auto-assignment, thread closure/locking, notification sending
- **DenyApplication** tests do not verify: thread closure/locking, notification sending
- **SubmitApplication** tests do not verify: snapshot data capture, review thread creation, notification sending
- No Livewire tests for the **review-detail** component (most complex component — modals, status transitions, note adding)
- No Livewire tests for the **show** component (withdrawal flow)
- No Livewire tests for the **admin-manage-application-questions-page** component (CRUD operations)
- No tests for the **auto-transition** from Submitted → UnderReview on first staff view
- No tests verifying the **pre-population of answers** from previous applications

---

## 17. File Map

**Models:**
- `app/Models/StaffApplication.php`
- `app/Models/ApplicationQuestion.php`
- `app/Models/StaffApplicationAnswer.php`
- `app/Models/StaffApplicationNote.php`
- `app/Models/StaffPosition.php` (related)

**Enums:**
- `app/Enums/ApplicationStatus.php`
- `app/Enums/BackgroundCheckStatus.php`
- `app/Enums/ApplicationQuestionType.php`
- `app/Enums/ApplicationQuestionCategory.php`

**Actions:**
- `app/Actions/SubmitApplication.php`
- `app/Actions/ApproveApplication.php`
- `app/Actions/DenyApplication.php`
- `app/Actions/WithdrawApplication.php`
- `app/Actions/UpdateApplicationStatus.php`
- `app/Actions/UpdateBackgroundCheck.php`
- `app/Actions/AddApplicationNote.php`

**Policies:**
- `app/Policies/StaffApplicationPolicy.php`

**Gates:** `app/Providers/AuthServiceProvider.php` — gates: `review-staff-applications`, `manage-application-questions`

**Notifications:**
- `app/Notifications/ApplicationStatusChangedNotification.php`
- `app/Notifications/NewStaffApplicationNotification.php`

**Jobs:** None

**Services:** `app/Services/TicketNotificationService.php` (shared, used for notification delivery)

**Controllers:** None (all Volt components)

**Volt Components:**
- `resources/views/livewire/staff-applications/my-applications.blade.php`
- `resources/views/livewire/staff-applications/apply.blade.php`
- `resources/views/livewire/staff-applications/show.blade.php`
- `resources/views/livewire/staff-applications/review-list.blade.php`
- `resources/views/livewire/staff-applications/review-detail.blade.php`
- `resources/views/livewire/admin-manage-application-questions-page.blade.php`
- `resources/views/livewire/admin-control-panel-tabs.blade.php` (embeds question management)

**Routes:**
- `applications.index` → `/applications`
- `applications.apply` → `/applications/apply/{staffPosition}`
- `applications.show` → `/applications/{staffApplication}`
- `admin.applications.index` → `/admin/applications`
- `admin.applications.show` → `/admin/applications/{staffApplication}`

**Migrations:**
- `database/migrations/2026_03_16_000001_create_application_questions_table.php`
- `database/migrations/2026_03_16_000002_create_staff_applications_table.php`
- `database/migrations/2026_03_16_000003_create_staff_application_answers_table.php`
- `database/migrations/2026_03_16_000004_create_staff_application_notes_table.php`
- `database/migrations/2026_03_16_000005_add_accepting_applications_to_staff_positions.php`
- `database/migrations/2026_03_16_000006_add_applicant_snapshot_to_staff_applications.php`
- `database/migrations/2026_03_16_000007_seed_application_info_site_config.php`
- `database/migrations/2026_03_16_000008_seed_application_questions.php`

**Factories:**
- `database/factories/StaffApplicationFactory.php`
- `database/factories/ApplicationQuestionFactory.php`

**Console Commands:** None

**Tests:**
- `tests/Feature/Actions/SubmitApplicationTest.php`
- `tests/Feature/Actions/ApproveApplicationTest.php`
- `tests/Feature/Actions/DenyApplicationTest.php`
- `tests/Feature/Actions/WithdrawApplicationTest.php`
- `tests/Feature/Actions/AddApplicationNoteTest.php`
- `tests/Feature/Actions/UpdateApplicationStatusTest.php`
- `tests/Feature/Livewire/StaffApplicationApplyTest.php`
- `tests/Feature/Livewire/StaffApplicationReviewTest.php`
- `tests/Feature/Policies/StaffApplicationPolicyTest.php`

**Config:** `application_info_page` (SiteConfig database entry)

**Other:**
- `resources/views/livewire/staff/page.blade.php` (staff directory — contains "Apply" links to positions accepting applications)

---

## 18. Known Issues & Improvement Opportunities

1. **Missing test for `UpdateBackgroundCheck` action** — This action has no dedicated test file, leaving its DB transaction, note appending, and system message logic untested.

2. **Thin action tests** — `ApproveApplication` and `DenyApplication` tests only verify status change and activity logging, but don't test: position auto-assignment, thread closure/locking, notification dispatch, or conditions persistence.

3. **No Livewire tests for review-detail** — The most complex component (6 modals, auto-transition, note adding, multiple status transitions) has no component-level tests.

4. **`viewAny` used for review-detail authorization** — The `review-detail` component uses `$this->authorize('viewAny', StaffApplication::class)` in `mount()`, which means any CrewMember+ can access the review detail page. Action buttons are gated by `@can('update')`, but a CrewMember can still see all application details, staff notes, and reviewer notes. This may be intentional but could be a concern for sensitive applicant data.

5. **No notification on withdrawal** — When an applicant withdraws, no notification is sent to reviewers. If a reviewer has already started working the application, they won't know it was withdrawn until they check.

6. **`hasOpenApplication` check is global** — The apply page checks `StaffApplication::where('user_id', Auth::id())->pending()->exists()` without filtering by position. This means a user with a pending application for Position A cannot apply for Position B. This may be intentional (one active application at a time) but is worth noting.

7. **Reviewer notes format is fragile** — Notes are appended as plain text with `\n` separators and timestamp prefixes. A structured JSON array or separate table (like `StaffApplicationNote`) would be more maintainable. The feature already has `StaffApplicationNote` for staff notes — reviewer notes could potentially be unified.

8. **No pagination on my-applications** — `my-applications.blade.php` loads all applications via `->get()` without pagination. For users who apply frequently, this could grow unbounded.

9. **System user dependency** — Several actions look up `User::where('email', 'system@lighthouse.local')->first()` for posting system messages. If this user doesn't exist, system messages silently fail (guarded by `if ($systemUser)` checks). This dependency should ideally be validated or seeded as part of the feature setup.
