# Discipline Reports -- Technical Documentation

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

Discipline Reports is a system for staff members to document behavioral incidents (and commendations) involving community members. Reports capture what happened, where it occurred, witnesses, actions taken, and a severity level. Each report starts as a Draft and can be Published by an Officer, which notifies the subject user and their parents.

Staff members at Junior Crew Member rank and above can create and view reports. Officers can edit any draft report and publish reports (with a safeguard: when the subject is a staff member, the reporter cannot publish their own report — another officer must do it). Published reports are visible to the subject user themselves and their parents. Regular community members cannot see unpublished reports.

The feature includes a **risk score** system that aggregates severity points from published reports across 7-day, 30-day, and 90-day windows, producing a weighted total score. This risk score is displayed on user profiles and on a dashboard widget that highlights the highest-risk users. Reports can be categorized using configurable Report Categories (e.g., Language, Harassment, Griefing) managed by Officers in the Admin Control Panel.

Key concepts: **Subject** = the user the report is about. **Reporter** = the staff member who created the report. **Publisher** = the officer who approved/published the report. **Severity** = ranges from Commendation (0 points) through Severe (10 points). **Risk Score** = cumulative severity points across time windows (7d + 30d + 90d = total).

---

## 2. Database Schema

### `discipline_reports` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint (PK) | No | auto | Primary key |
| `subject_user_id` | foreignId | No | — | FK to `users.id`, cascadeOnDelete |
| `reporter_user_id` | foreignId | No | — | FK to `users.id`, cascadeOnDelete |
| `publisher_user_id` | foreignId | Yes | NULL | FK to `users.id`, nullOnDelete |
| `description` | text | No | — | What happened |
| `location` | string | No | — | Cast to `ReportLocation` enum |
| `witnesses` | text | Yes | NULL | Who else saw it |
| `actions_taken` | text | No | — | What was done in response |
| `severity` | string | No | — | Cast to `ReportSeverity` enum |
| `report_category_id` | foreignId | Yes | NULL | FK to `report_categories.id`, nullOnDelete |
| `status` | string | No | `draft` | Cast to `ReportStatus` enum |
| `published_at` | timestamp | Yes | NULL | When the report was published |
| `created_at` | timestamp | Yes | — | |
| `updated_at` | timestamp | Yes | — | |

**Indexes:** Composite index on `(subject_user_id, status, published_at)`
**Foreign Keys:**
- `subject_user_id` → `users.id` (cascade delete)
- `reporter_user_id` → `users.id` (cascade delete)
- `publisher_user_id` → `users.id` (null on delete)
- `report_category_id` → `report_categories.id` (null on delete)

**Migration(s):**
- `database/migrations/2026_03_01_223656_create_discipline_reports_table.php`
- `database/migrations/2026_03_01_230948_add_report_category_id_to_discipline_reports_table.php`

### `report_categories` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint (PK) | No | auto | Primary key |
| `name` | string | No | — | Category name |
| `color` | string | No | `zinc` | Flux UI badge color |
| `created_at` | timestamp | Yes | — | |
| `updated_at` | timestamp | Yes | — | |

**Indexes:** None beyond primary key
**Foreign Keys:** None
**Migration:** `database/migrations/2026_03_01_230942_create_report_categories_table.php`

**Seeded defaults:** Language (yellow), Harassment (red), Griefing (orange), Cheating (purple), Disrespect (blue), Spam (indigo), Inappropriate Content (red), Other (zinc)

---

## 3. Models & Relationships

### DisciplineReport (`app/Models/DisciplineReport.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `subject()` | belongsTo | User | Via `subject_user_id` — the user the report is about |
| `reporter()` | belongsTo | User | Via `reporter_user_id` — the staff member who created it |
| `publisher()` | belongsTo | User | Via `publisher_user_id` — the officer who published it |
| `category()` | belongsTo | ReportCategory | Via `report_category_id` |

**Scopes:**
- `scopePublished(Builder $query)` — `where('status', ReportStatus::Published)`
- `scopeDraft(Builder $query)` — `where('status', ReportStatus::Draft)`
- `scopeForSubject(Builder $query, User $user)` — `where('subject_user_id', $user->id)`

**Key Methods:**
- `isDraft(): bool` — Returns true if status is `Draft`
- `isPublished(): bool` — Returns true if status is `Published`

**Casts:**
- `location` => `ReportLocation::class`
- `severity` => `ReportSeverity::class`
- `status` => `ReportStatus::class`
- `published_at` => `datetime`

### ReportCategory (`app/Models/ReportCategory.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `disciplineReports()` | hasMany | DisciplineReport | Via `report_category_id` |

**Fillable:** `name`, `color`

### User (`app/Models/User.php`) — Discipline Report aspects

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `disciplineReports()` | hasMany | DisciplineReport | Via `subject_user_id` |

**Key Methods:**
- `disciplineRiskScore(): array{7d: int, 30d: int, 90d: int, total: int}` — Calculates severity-point-weighted risk score across time windows. Cached for 24 hours per user.
- `clearDisciplineRiskScoreCache(): void` — Clears the cached risk score (called when a report is published)
- `riskScoreColor(int $total): string` — Static method returning color based on thresholds: 1-10 = green, 11-25 = yellow, 26-50 = orange, 51+ = red, 0 = zinc

---

## 4. Enums Reference

### ReportSeverity (`app/Enums/ReportSeverity.php`)

| Case | Value | Label | Points | Color |
|------|-------|-------|--------|-------|
| `Commendation` | `commendation` | Commendation | 0 | green |
| `Trivial` | `trivial` | Trivial | 1 | zinc |
| `Minor` | `minor` | Minor | 2 | blue |
| `Moderate` | `moderate` | Moderate | 4 | yellow |
| `Major` | `major` | Major | 7 | orange |
| `Severe` | `severe` | Severe | 10 | red |

**Helper methods:** `label(): string`, `points(): int`, `color(): string`

### ReportLocation (`app/Enums/ReportLocation.php`)

| Case | Value | Label | Color |
|------|-------|-------|-------|
| `Minecraft` | `minecraft` | Minecraft | green |
| `DiscordText` | `discord_text` | Discord Text | indigo |
| `DiscordVoice` | `discord_voice` | Discord Voice | purple |
| `Other` | `other` | Other | zinc |

**Helper methods:** `label(): string`, `color(): string`

### ReportStatus (`app/Enums/ReportStatus.php`)

| Case | Value | Label | Color |
|------|-------|-------|-------|
| `Draft` | `draft` | Draft | amber |
| `Published` | `published` | Published | green |

**Helper methods:** `label(): string`, `color(): string`

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

| Gate Name | Who Can Pass | Logic Summary |
|-----------|-------------|---------------|
| `view-user-discipline-reports` | Admin, JrCrew+, self, parent of subject | `isAdmin() \|\| isAtLeastRank(JrCrew) \|\| id === targetUser->id \|\| children()->where(child_user_id)` |
| `manage-discipline-reports` | Admin, JrCrew+ | `isAdmin() \|\| isAtLeastRank(JrCrew)` |
| `publish-discipline-reports` | Admin, Officer+ | `isAdmin() \|\| isAtLeastRank(Officer)` |
| `view-discipline-report-log` | Admin, Officer+, Engineer dept | `isAdmin() \|\| isAtLeastRank(Officer) \|\| isInDepartment(Engineer)` |

### Policies

#### DisciplineReportPolicy (`app/Policies/DisciplineReportPolicy.php`)

**`before()` hook:** Admin → returns `true` for all abilities except `delete` and `publish`. Command Officer → same bypass. For `delete` and `publish`, always falls through to the specific method.

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `viewAny` | JrCrew+ | `isAtLeastRank(JrCrew)` |
| `view` | JrCrew+, subject (published only), parent of subject (published only) | Staff can see all; subject sees only published; parent sees only published for their child |
| `create` | JrCrew+ | `isAtLeastRank(JrCrew)` |
| `update` | Reporter (draft only), Officer+ (draft only) | Only draft reports can be edited; reporter can edit own; Officer+ can edit any draft |
| `publish` | Admin/Officer+ (draft only, with staff-subject safeguard) | Must be Admin or Officer+. If subject is a staff member, reporter cannot publish their own report. |
| `delete` | Nobody | Always returns `false` |

#### ReportCategoryPolicy (`app/Policies/ReportCategoryPolicy.php`)

**`before()` hook:** Admin → returns `true` for all abilities except `delete`. Command Officer → same bypass.

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `viewAny` | Officer+ | `isAtLeastRank(Officer)` |
| `create` | Officer+ | `isAtLeastRank(Officer)` |
| `update` | Officer+ | `isAtLeastRank(Officer)` |
| `delete` | Nobody | Always returns `false` |

### Permissions Matrix

| User Type | View Reports (own) | View Reports (others) | Create Report | Edit Draft | Publish Draft | Manage Categories | View Report Log |
|-----------|:------------------:|:--------------------:|:-------------:|:----------:|:-------------:|:-----------------:|:--------------:|
| Regular User | Published only | No | No | No | No | No | No |
| Parent (of subject) | Published only (child's) | No | No | No | No | No | No |
| Jr Crew Member | All | All | Yes | Own drafts | No | No | No |
| Crew Member | All | All | Yes | Own drafts | No | No | No |
| Officer | All | All | Yes | Any draft | Yes* | Yes | Yes |
| Command Officer | All | All | Yes | Any draft | Yes* | Yes | Yes |
| Admin | All | All | Yes | Any draft | Yes* | Yes | Yes |
| Engineer dept | All | All | Yes | varies | varies | varies | Yes |

*When subject is a staff member, the reporter cannot publish their own report.

---

## 6. Routes

No dedicated routes for Discipline Reports. The feature is accessed through:
- **User profile page** — Discipline reports card embedded in `resources/views/livewire/users/display-basic-details.blade.php`
- **Admin Control Panel** — Reports log tab in `resources/views/livewire/admin-control-panel-tabs.blade.php`
- **Dashboard** — Discipline reports widget in `resources/views/livewire/dashboard/discipline-reports-widget.blade.php`
- **Parent Portal** — Discipline reports modal in `resources/views/livewire/parent-portal/index.blade.php`

---

## 7. User Interface Components

### Discipline Reports Card (Profile Page)
**File:** `resources/views/livewire/users/discipline-reports-card.blade.php`
**Route:** Embedded in user profile page

**Purpose:** Displays discipline reports for a specific user. Staff can create, edit, and publish reports. Subject users and parents see only published reports.

**Authorization:** Mount checks: must be JrCrew+ staff, the subject user themselves, or a parent of the subject. Reports query: staff see all (including drafts), non-staff see only published.

**User Actions Available:**
- **Create Report** → `openCreateModal()` → validates → `CreateDisciplineReport::run()` → toast "Staff report created."
- **Edit Draft** → `openEditModal($id)` → validates → `UpdateDisciplineReport::run()` → toast "Staff report updated."
- **Publish Draft** → `publishReport($id)` → `PublishDisciplineReport::run()` → toast "Staff report published."
- **View Report** → `viewReport($id)` → opens `view-report-modal` with full details

**UI Elements:**
- Risk score badge with color and 7d/30d/90d tooltip breakdown
- "New Report" button (staff only)
- Reports table with date, category badge, description, severity badge
- Draft badge on unpublished reports
- Publish button (Officers only, with confirmation dialog)
- "Another officer must publish" text when reporter cannot publish their own report about a staff member
- Create/Edit/View modals with full form fields
- Markdown rendering for description and actions_taken in view modal

**Validation Rules (create/edit):**
- `formDescription`: required, string, min:10
- `formLocation`: required, valid `ReportLocation` enum
- `formActionsTaken`: required, string, min:5
- `formSeverity`: required, valid `ReportSeverity` enum
- `formCategory`: nullable, exists in `report_categories`

### Discipline Reports Log (ACP)
**File:** `resources/views/livewire/admin-manage-discipline-reports-page.blade.php`
**Route:** Embedded in ACP tabs (Logs category, discipline-report-log tab)

**Purpose:** Paginated, sortable, filterable log of all discipline reports for administrative review.

**Authorization:** `view-discipline-report-log` gate (Admin, Officer+, Engineer dept)

**User Actions Available:**
- **Filter** by status, severity, category (live-updating selects)
- **Sort** by created_at, published_at, severity, status (sortable table columns)
- **View Report** → opens `acp-view-report-modal`
- **Publish Draft** → `publishReport($id)` → `PublishDisciplineReport::run()` → toast "Report published."

**UI Elements:**
- Filter bar with status, severity, and category dropdowns
- Paginated table (15 per page) with: Subject, Category, Reporter, Location, Severity, Status, Created, Published
- Sortable columns
- View/Publish action buttons per row

### Discipline Reports Widget (Dashboard)
**File:** `resources/views/livewire/dashboard/discipline-reports-widget.blade.php`
**Route:** Embedded in dashboard for staff with `manage-discipline-reports` gate

**Purpose:** Dashboard widget showing recent reports (last 7 days), pending draft count, and top 5 highest-risk users.

**Authorization:** `manage-discipline-reports` gate (Admin, JrCrew+)

**User Actions Available:**
- **View Report** → opens `widget-view-report-modal`
- **Publish Draft** (from modal) → `PublishDisciplineReport::run()` → toast "Staff report published."
- **Link to full log** → "View All Reports" link to ACP discipline report log

**UI Elements:**
- Recent reports list with avatar, user link, category/severity badges, draft badge, date
- Pending count badge (amber)
- Top Risk Users section with risk score badges and 7d/30d/90d tooltips
- View report modal with full details and publish button

### Manage Report Categories (ACP)
**File:** `resources/views/livewire/admin-manage-report-categories-page.blade.php`
**Route:** Embedded in ACP tabs

**Purpose:** CRUD interface for report categories

**Authorization:** `ReportCategoryPolicy` (Admin, Command Officer, Officer+)

**User Actions Available:**
- **Create Category** → flyout modal → validates → `ReportCategory::create()` → toast
- **Edit Category** → flyout modal → validates → `$category->update()` → toast

**UI Elements:**
- Table with Name (as colored badge), Color, Reports count, Edit button
- Create Category button → flyout modal with name input and color select
- Available colors: red, orange, yellow, green, blue, indigo, purple, zinc

---

## 8. Actions (Business Logic)

### CreateDisciplineReport (`app/Actions/CreateDisciplineReport.php`)

**Signature:** `handle(User $subject, User $reporter, string $description, ReportLocation $location, string $actionsTaken, ReportSeverity $severity, ?string $witnesses, ?ReportCategory $category): DisciplineReport`

**Step-by-step logic:**
1. Creates `DisciplineReport` with status `Draft`, all provided fields
2. Records activity: `RecordActivity::run($subject, 'discipline_report_created', "Discipline report #{id} created by {reporter}. Severity: {severity}.", $reporter)`
3. If reporter is NOT an Officer → notifies Quartermaster department staff (excluding the reporter) via `DisciplineReportPendingReviewNotification`

**Called by:** `resources/views/livewire/users/discipline-reports-card.blade.php`

### UpdateDisciplineReport (`app/Actions/UpdateDisciplineReport.php`)

**Signature:** `handle(DisciplineReport $report, User $editor, string $description, ReportLocation $location, string $actionsTaken, ReportSeverity $severity, ?string $witnesses, ?ReportCategory $category): DisciplineReport`

**Step-by-step logic:**
1. Updates report fields: description, location, witnesses, actions_taken, severity, report_category_id
2. Records activity: `RecordActivity::run($report->subject, 'discipline_report_updated', "Discipline report #{id} updated by {editor}.", $editor)`
3. Returns fresh report

**Called by:** `resources/views/livewire/users/discipline-reports-card.blade.php`

### PublishDisciplineReport (`app/Actions/PublishDisciplineReport.php`)

**Signature:** `handle(DisciplineReport $report, User $publisher): DisciplineReport`

**Step-by-step logic:**
1. Returns early if report is not a Draft (idempotent guard)
2. Updates: status → `Published`, publisher_user_id, published_at → now()
3. Clears subject's discipline risk score cache: `$report->subject->clearDisciplineRiskScoreCache()`
4. Clears dashboard top risk users cache: `Cache::forget('dashboard.top_risk_users')`
5. Records activity: `RecordActivity::run($report->subject, 'discipline_report_published', "Discipline report #{id} published by {publisher}. Severity: {severity}.", $publisher)`
6. Sends `DisciplineReportPublishedNotification` to subject via `TicketNotificationService`
7. Sends `DisciplineReportPublishedParentNotification` to each parent of the subject via `TicketNotificationService`

**Called by:**
- `resources/views/livewire/users/discipline-reports-card.blade.php`
- `resources/views/livewire/admin-manage-discipline-reports-page.blade.php`
- `resources/views/livewire/dashboard/discipline-reports-widget.blade.php`

---

## 9. Notifications

### DisciplineReportPublishedNotification (`app/Notifications/DisciplineReportPublishedNotification.php`)

**Triggered by:** `PublishDisciplineReport` action
**Recipient:** Subject user (the person the report is about)
**Channels:** mail, Pushover (via `TicketNotificationService`)
**Mail subject:** "Staff Report Recorded"
**Content summary:** Informs the user that a staff report has been recorded with severity and location details, links to their profile
**Queued:** Yes

### DisciplineReportPublishedParentNotification (`app/Notifications/DisciplineReportPublishedParentNotification.php`)

**Triggered by:** `PublishDisciplineReport` action
**Recipient:** Each parent of the subject user
**Channels:** mail, Pushover (via `TicketNotificationService`)
**Mail subject:** "Staff Conversation Recorded for Your Child" (for Trivial/Minor) or "Staff Report Recorded for Your Child" (for Moderate+)
**Content summary:** Informs parent about the report, links to parent portal. Uses softer "conversation" language for lower severities.
**Queued:** Yes
**Special logic:** `isConversationSeverity()` returns true for `Trivial` and `Minor`, changing the subject line and Pushover title/message wording.

### DisciplineReportPendingReviewNotification (`app/Notifications/DisciplineReportPendingReviewNotification.php`)

**Triggered by:** `CreateDisciplineReport` action (when reporter is not an Officer)
**Recipient:** All Quartermaster department staff (excluding the reporter)
**Channels:** mail, Pushover (via `TicketNotificationService`)
**Mail subject:** "Discipline Report Pending Review"
**Content summary:** Alerts Quartermaster staff that a new report needs review, includes severity and subject/reporter names
**Queued:** Yes

---

## 10. Background Jobs

Not applicable for this feature.

---

## 11. Console Commands & Scheduled Tasks

Not applicable for this feature.

---

## 12. Services

### TicketNotificationService (`app/Services/TicketNotificationService.php`)
**Purpose:** Smart notification delivery determining which channels (mail, Pushover) to use based on user preferences
**Used by:** All three notification classes in this feature

---

## 13. Activity Log Entries

| Action String | Logged By | Subject Model | Description |
|---------------|-----------|---------------|-------------|
| `discipline_report_created` | CreateDisciplineReport | User (subject) | "Discipline report #{id} created by {reporter}. Severity: {severity}." |
| `discipline_report_updated` | UpdateDisciplineReport | User (subject) | "Discipline report #{id} updated by {editor}." |
| `discipline_report_published` | PublishDisciplineReport | User (subject) | "Discipline report #{id} published by {publisher}. Severity: {severity}." |

---

## 14. Data Flow Diagrams

### Creating a Discipline Report

```
Staff member clicks "New Report" on user's profile page
  -> discipline-reports-card::openCreateModal()
    -> $this->authorize('create', DisciplineReport::class)
    -> Opens create-report-modal
  -> Staff fills in category, description, location, witnesses, actions taken, severity
  -> Clicks "Create Report"
    -> discipline-reports-card::createReport()
      -> $this->authorize('create', DisciplineReport::class)
      -> Validates all fields
      -> CreateDisciplineReport::run($subject, Auth::user(), ...)
        -> DisciplineReport::create([status: Draft, ...])
        -> RecordActivity::run($subject, 'discipline_report_created', ...)
        -> If reporter is not Officer:
          -> Notify Quartermaster dept via DisciplineReportPendingReviewNotification
      -> Flux::modal('create-report-modal')->close()
      -> Flux::toast('Staff report created.', variant: 'success')
```

### Publishing a Discipline Report

```
Officer clicks "Publish" on a draft report (profile, dashboard widget, or ACP)
  -> wire:confirm="Publish this report? The user and their parents will be notified."
  -> component::publishReport($reportId)
    -> $this->authorize('publish', $report)
    -> PublishDisciplineReport::run($report, Auth::user())
      -> Guard: returns early if not Draft
      -> $report->update([status: Published, publisher_user_id, published_at: now()])
      -> $report->subject->clearDisciplineRiskScoreCache()
      -> Cache::forget('dashboard.top_risk_users')
      -> RecordActivity::run($subject, 'discipline_report_published', ...)
      -> DisciplineReportPublishedNotification sent to subject
      -> DisciplineReportPublishedParentNotification sent to each parent
    -> Flux::toast('Staff report published.', variant: 'success')
```

### Viewing a Published Report (as Subject User)

```
User views their own profile page
  -> discipline-reports-card::mount($user)
    -> Checks: isStaff || isSelf || isParent → abort(403) otherwise
    -> isStaffViewing = false (for self or parent)
  -> Reports query: DisciplineReport::published() (non-staff see only published)
  -> User clicks "View" on a report
    -> discipline-reports-card::viewReport($id)
      -> $this->authorize('view', $report) via DisciplineReportPolicy
      -> Opens view-report-modal
      -> Shows: category, location, severity, description (markdown), actions taken (markdown)
      -> Does NOT show: reporter name, publisher name (non-staff view)
```

### Risk Score Calculation

```
disciplineRiskScore() called (profile page, dashboard widget)
  -> Cache::remember("user.{id}.discipline_risk_score", 24h, ...)
    -> Query: disciplineReports()->published()->where('published_at >= 90d ago')
    -> For each report:
      -> points = severity->points()
      -> score90 += points
      -> If published_at >= 30d ago: score30 += points
      -> If published_at >= 7d ago: score7 += points
    -> total = score7 + score30 + score90 (recent reports triple-counted)
    -> Return {7d, 30d, 90d, total}
  -> Color determined by User::riskScoreColor($total)
```

---

## 15. Configuration

Not applicable for this feature. No environment variables or config values are specific to discipline reports.

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/Actions/DisciplineReports/CreateDisciplineReportTest.php` | 6 | Creating drafts, activity, QM notification, null witnesses |
| `tests/Feature/Actions/DisciplineReports/PublishDisciplineReportTest.php` | 6 | Publishing, publisher/timestamp, activity, notifications, cache |
| `tests/Feature/Actions/DisciplineReports/UpdateDisciplineReportTest.php` | 2 | Updating drafts, activity |
| `tests/Feature/Actions/DisciplineReports/ReportCategoryTest.php` | 3 | Create with/without category, update category |
| `tests/Feature/Livewire/DisciplineReportsCardTest.php` | 12 | Card visibility, draft/published filtering, CRUD via modal, publish, risk score |
| `tests/Feature/Models/UserDisciplineRiskScoreTest.php` | 10 | Score calculation, time windows, triple counting, caching, colors |
| `tests/Feature/Policies/DisciplineReportPolicyTest.php` | 15 | All policy methods, admin/command bypass, staff-subject safeguard |
| `tests/Feature/Policies/ReportCategoryPolicyTest.php` | 2 | Category management authorization, delete prevention |
| `tests/Unit/Notifications/DisciplineReportPublishedParentNotificationTest.php` | 9 | Mail template, conversation/report wording by severity, channels, queued |

### Test Case Inventory

**CreateDisciplineReportTest.php:**
- `it('creates a draft discipline report')`
- `it('records activity when a discipline report is created')`
- `it('notifies quartermaster department when non-officer creates report')`
- `it('does not notify the reporter even if they are in the quartermaster department')`
- `it('does not notify quartermaster when officer creates report')`
- `it('creates report with null witnesses when not provided')`

**PublishDisciplineReportTest.php:**
- `it('publishes a draft report')`
- `it('sets publisher and published_at on publish')`
- `it('records activity when report is published')`
- `it('notifies subject user when report is published')`
- `it('sends parent-specific notification to parent accounts')`
- `it('clears the subject risk score cache when report is published')`

**UpdateDisciplineReportTest.php:**
- `it('updates a draft discipline report')`
- `it('records activity when report is updated')`

**ReportCategoryTest.php:**
- `it('creates a report with a category')`
- `it('creates a report without a category')`
- `it('updates a report category')`

**DisciplineReportsCardTest.php:**
- `it('shows discipline reports card to staff on profile page')`
- `it('shows discipline reports card to the subject user')`
- `it('shows discipline reports card to parent of subject')`
- `it('hides discipline reports card from unrelated users')`
- `it('shows only published reports to non-staff users')`
- `it('shows all reports including drafts to staff')`
- `it('allows staff to create a report via modal')`
- `it('allows officer to publish a draft report')`
- `it('prevents non-officer from publishing')`
- `it('allows creator to edit their draft report')`
- `it('prevents editing of published reports')`
- `it('shows risk score badge with correct color')`

**UserDisciplineRiskScoreTest.php:**
- `it('returns zero scores when user has no reports')`
- `it('calculates 7-day score from published reports only')`
- `it('calculates 30-day score correctly')`
- `it('calculates 90-day score correctly')`
- `it('triple counts recent reports in total (7d + 30d + 90d)')`
- `it('excludes draft reports from risk score')`
- `it('excludes reports older than 90 days')`
- `it('returns correct color for each threshold')`
- `it('caches risk score for 24 hours')`
- `it('clears cached risk score when clearDisciplineRiskScoreCache is called')`

**DisciplineReportPolicyTest.php:**
- `it('allows admin to perform any action')`
- `it('allows command officer to perform any action')`
- `it('allows jr crew to view any reports')`
- `it('allows jr crew to create reports')`
- `it('allows report creator to update their draft report')`
- `it('allows officer to update any draft report')`
- `it('prevents updating a published report')`
- `it('allows officer to publish a draft report')`
- `it('prevents non-officer from publishing')`
- `it('allows subject user to view their published report')`
- `it('prevents subject user from viewing draft reports')`
- `it('allows parent to view published reports about their child')`
- `it('prevents parent from viewing draft reports about their child')`
- `it('prevents non-staff non-subject from viewing reports')`
- `it('prevents deletion of reports')`

**ReportCategoryPolicyTest.php:**
- `it('allows authorized roles to manage report categories')` (parameterized: admin, command officer, quartermaster officer, crew)
- `it('prevents deletion of report categories')`

**DisciplineReportPublishedParentNotificationTest.php:**
- `it('uses the parent template')`
- `it('passes child name to template')`
- `it('uses conversation wording in subject for trivial/minor severity')` (parameterized)
- `it('uses staff report wording in subject for moderate+ severity')` (parameterized)
- `it('pushover uses conversation wording for trivial/minor severity')` (parameterized)
- `it('pushover uses staff report wording for moderate+ severity')` (parameterized)
- `it('sends via mail channel when allowed')`
- `it('sends via pushover channel when allowed and key is set')`
- `it('is queued for background processing')`

### Coverage Gaps

- No tests for the `DisciplineReportPublishedNotification` (subject notification) — only the parent notification has unit tests
- No tests for the `DisciplineReportPendingReviewNotification`
- No tests for the ACP discipline reports log page (`admin-manage-discipline-reports-page.blade.php`) — filtering, sorting, pagination
- No tests for report category CRUD through the admin component
- No tests for the dashboard discipline reports widget
- No test for the staff-subject publish safeguard through the UI (policy test exists but not component-level)
- No test for the `Commendation` severity type behavior (0 points, does it still create a report?)
- No test for idempotent publish guard (publishing an already-published report)

---

## 17. File Map

**Models:**
- `app/Models/DisciplineReport.php`
- `app/Models/ReportCategory.php`
- `app/Models/User.php` (disciplineReports relationship, risk score methods)

**Enums:**
- `app/Enums/ReportSeverity.php`
- `app/Enums/ReportLocation.php`
- `app/Enums/ReportStatus.php`

**Actions:**
- `app/Actions/CreateDisciplineReport.php`
- `app/Actions/UpdateDisciplineReport.php`
- `app/Actions/PublishDisciplineReport.php`

**Policies:**
- `app/Policies/DisciplineReportPolicy.php`
- `app/Policies/ReportCategoryPolicy.php`

**Gates:** `AuthServiceProvider.php` — gates: `view-user-discipline-reports`, `manage-discipline-reports`, `publish-discipline-reports`, `view-discipline-report-log`

**Notifications:**
- `app/Notifications/DisciplineReportPublishedNotification.php`
- `app/Notifications/DisciplineReportPublishedParentNotification.php`
- `app/Notifications/DisciplineReportPendingReviewNotification.php`

**Jobs:** None

**Services:**
- `app/Services/TicketNotificationService.php` (notification delivery)

**Controllers:** None

**Volt Components:**
- `resources/views/livewire/users/discipline-reports-card.blade.php`
- `resources/views/livewire/admin-manage-discipline-reports-page.blade.php`
- `resources/views/livewire/admin-manage-report-categories-page.blade.php`
- `resources/views/livewire/dashboard/discipline-reports-widget.blade.php`

**Related Views:**
- `resources/views/livewire/users/display-basic-details.blade.php` (embeds discipline reports card)
- `resources/views/livewire/admin-control-panel-tabs.blade.php` (embeds reports log and categories)
- `resources/views/dashboard.blade.php` (embeds discipline reports widget)
- `resources/views/livewire/parent-portal/index.blade.php` (discipline reports modal for parents)

**Routes:** No dedicated routes (embedded in existing pages)

**Migrations:**
- `database/migrations/2026_03_01_223656_create_discipline_reports_table.php`
- `database/migrations/2026_03_01_230942_create_report_categories_table.php`
- `database/migrations/2026_03_01_230948_add_report_category_id_to_discipline_reports_table.php`

**Factories:**
- `database/factories/DisciplineReportFactory.php`
- `database/factories/ReportCategoryFactory.php`

**Mail Templates:**
- `resources/views/mail/discipline-report-published.blade.php`
- `resources/views/mail/discipline-report-published-parent.blade.php`
- `resources/views/mail/discipline-report-pending-review.blade.php`

**Console Commands:** None

**Tests:**
- `tests/Feature/Actions/DisciplineReports/CreateDisciplineReportTest.php`
- `tests/Feature/Actions/DisciplineReports/PublishDisciplineReportTest.php`
- `tests/Feature/Actions/DisciplineReports/UpdateDisciplineReportTest.php`
- `tests/Feature/Actions/DisciplineReports/ReportCategoryTest.php`
- `tests/Feature/Livewire/DisciplineReportsCardTest.php`
- `tests/Feature/Models/UserDisciplineRiskScoreTest.php`
- `tests/Feature/Policies/DisciplineReportPolicyTest.php`
- `tests/Feature/Policies/ReportCategoryPolicyTest.php`
- `tests/Unit/Notifications/DisciplineReportPublishedParentNotificationTest.php`

**Config:** None specific to this feature

---

## 18. Known Issues & Improvement Opportunities

1. **Risk score triple-counts recent reports**: A report published today contributes its points to the 7d window, the 30d window, AND the 90d window, then all three are summed for the total. This means a Severe report (10 points) from today counts as 30 total points. This is by design but could be confusing — the UI tooltips show the breakdown but the total is not a simple sum of unique points.

2. **No deletion mechanism**: Reports can never be deleted (`DisciplineReportPolicy::delete()` always returns `false`). While this preserves audit trails, there's no way to handle erroneously created reports. Consider adding an "archive" or "retract" status.

3. **No test for `DisciplineReportPublishedNotification`**: The notification sent to the subject user has no unit tests, unlike the parent notification which has thorough coverage.

4. **No test for `DisciplineReportPendingReviewNotification`**: The notification sent to Quartermaster staff for non-Officer-created reports has no dedicated tests.

5. **Published reports are immutable**: Once published, a report cannot be edited (policy returns `false` for update on published reports). This is intentional but means typos or errors in published reports cannot be corrected.

6. **Quartermaster-specific notification routing**: The `CreateDisciplineReport` action specifically notifies Quartermaster department staff when a non-Officer creates a report. This department name is hardcoded in the action rather than being configurable.

7. **Dashboard widget cache timing**: The `dashboard.top_risk_users` cache has a 5-minute TTL (300 seconds) but is explicitly busted when a report is published. This means the widget can be slightly stale between publish events.

8. **XSS protection via Str::markdown**: The view report modal renders description and actions_taken through `Str::markdown()` with `'html_input' => 'strip'` and `'allow_unsafe_links' => false`, which is good. However, the ACP view modal does NOT use markdown rendering — it displays raw text with `{{ }}` escaping. This inconsistency means reports look different depending on where they're viewed.
