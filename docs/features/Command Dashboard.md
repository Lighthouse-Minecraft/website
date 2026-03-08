# Command Dashboard -- Technical Documentation

> **Audience:** Project owner, developers, AI agents
> **Generated:** 2026-03-08
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

The Command Dashboard is a set of three widgets embedded in the main dashboard page, visible only to Command department staff and Admins. It provides leadership with a high-level view of organizational health across three dimensions: **community engagement** (new users, account linking, active users), **department engagement** (tickets and todos per department, discipline reports, meeting report completion, attendance rates), and **staff engagement** (per-person metrics for todos, tickets, reports, and attendance).

The dashboard uses a concept of **iterations** — time periods bounded by completed staff meeting end times. The current iteration runs from the last completed staff meeting to now; previous iterations span between earlier meetings. This provides a natural cadence aligned with the organization's meeting schedule. All three widgets compare current iteration metrics against previous iteration metrics, showing deltas. Clicking on metric tiles opens detail modals with 3-month timeline charts using Flux chart components.

The `GetIterationBoundaries` action is the core engine, computing iteration boundaries from completed staff meetings and caching the result for 24 hours. Individual metric queries use `Cache::flexible()` (stale-while-revalidate) or `Cache::remember()` for performance.

---

## 2. Database Schema

The Command Dashboard reads from multiple existing tables but does not have its own tables. The key tables queried are:

- **`users`** — staff rank, department, login dates, created dates
- **`tasks`** — assigned user, status, completion date, section_key (department)
- **`threads`** — ticket type, status, department, assigned user, dates
- **`meetings`** — type, status, end_time
- **`meeting_reports`** — meeting_id, user_id, submitted_at
- **`meeting_user`** — pivot table for meeting attendance
- **`minecraft_accounts`** — created_at, status
- **`discord_accounts`** — created_at
- **`discipline_reports`** — published_at, status

---

## 3. Models & Relationships

The Command Dashboard reads from but does not own any models. Models used:

- **User** — `staff_rank`, `staff_department`, `last_login_at`, `created_at`
- **Task** — `assigned_to_user_id`, `status`, `completed_at`, `section_key`
- **Thread** — `type`, `status`, `department`, `assigned_to_user_id`, `updated_at`, `created_at`
- **Meeting** — `type`, `status`, `end_time`; relationship: `attendees()` (belongsToMany User via `meeting_user`)
- **MeetingReport** — `meeting_id`, `user_id`, `submitted_at`
- **MinecraftAccount** — `status`, `created_at`
- **DiscordAccount** — `created_at`
- **DisciplineReport** — `published_at`; scopes: `published()`, `draft()`

---

## 4. Enums Reference

The following enums are used by the Command Dashboard widgets:

### StaffRank (`app/Enums/StaffRank.php`)

| Case | Value | Notes |
|------|-------|-------|
| `None` | 0 | Non-staff |
| `JrCrew` | 1 | Jr Crew — excluded from attendance tracking |
| `CrewMember` | 2 | Crew Member |
| `Officer` | 3 | Officer — meeting missed badges shown in red |

### StaffDepartment (`app/Enums/StaffDepartment.php`)

Used for department breakdown in the Department Engagement widget.

### TaskStatus (`app/Enums/TaskStatus.php`)

| Case | Notes |
|------|-------|
| `Completed` | Used for "worked" counts |
| `Archived` | Excluded from "open" counts |

### ThreadStatus (`app/Enums/ThreadStatus.php`)

| Case | Notes |
|------|-------|
| `Closed` | Used for "tickets worked" counts |

### ThreadType (`app/Enums/ThreadType.php`)

| Case | Notes |
|------|-------|
| `Ticket` | Filters to ticket-type threads only |

### MeetingStatus (`app/Enums/MeetingStatus.php`)

| Case | Notes |
|------|-------|
| `Completed` | Only completed meetings define iteration boundaries |

### MeetingType (`app/Enums/MeetingType.php`)

| Case | Notes |
|------|-------|
| `StaffMeeting` | Only staff meetings are used for iteration boundaries |

### MinecraftAccountStatus (`app/Enums/MinecraftAccountStatus.php`)

| Case | Notes |
|------|-------|
| `Verifying` | Used for "pending MC verification" count |

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

| Gate Name | Who Can Pass | Logic Summary |
|-----------|-------------|---------------|
| `view-command-dashboard` | Admin OR Command department | `$user->isAdmin() \|\| $user->isInDepartment(StaffDepartment::Command)` |

### Policies

Not applicable for this feature.

### Permissions Matrix

| User Type | View Command Dashboard |
|-----------|----------------------|
| Unauthenticated | No |
| Regular User | No |
| JrCrew (non-Command) | No |
| CrewMember (non-Command) | No |
| Officer (non-Command) | No |
| JrCrew (Command) | Yes |
| CrewMember (Command) | Yes |
| Officer (Command) | Yes |
| Admin | Yes |

---

## 6. Routes

The Command Dashboard does not have its own routes. It is embedded directly in the main dashboard page at `/dashboard` (route name: `dashboard`), conditionally rendered via `@can('view-command-dashboard')`.

---

## 7. User Interface Components

### Community Engagement Widget
**File:** `resources/views/livewire/dashboard/command-community-engagement.blade.php`
**Route:** Embedded in dashboard (`/dashboard`)

**Purpose:** Shows community growth metrics for the current iteration with comparison to previous iteration.

**Authorization:** `$this->authorize('view-command-dashboard')` in `showDetail()` method. Widget visibility controlled by `@can('view-command-dashboard')` in the dashboard template.

**Metrics Displayed:**
- New Users (current vs. previous iteration, clickable for 3-month timeline)
- New MC Accounts (current vs. previous, clickable)
- Pending MC Verification (point-in-time count, amber "needs attention" badge if > 0)
- New Discord Accounts (current vs. previous, clickable)
- Active Users / Logged In (current vs. previous, clickable, full-width)

**UI Elements:**
- Grid of metric tiles (2 columns) with bold current values, delta badges (green/red), previous values
- Detail modal with Flux area/line chart showing 3-month timeline per metric
- Iteration date range label at bottom
- Uses `Cache::flexible()` for current metrics (1h fresh, 12h stale-while-revalidate)
- Uses `Cache::remember()` for previous metrics (24h TTL)

---

### Department Engagement Widget
**File:** `resources/views/livewire/dashboard/command-department-engagement.blade.php`
**Route:** Embedded in dashboard (`/dashboard`)

**Purpose:** Shows per-department ticket and todo metrics, discipline report counts, and meeting report/attendance rates.

**Authorization:** Same as Community Engagement.

**Metrics Displayed:**
- **Per-department table:** Tickets Opened, Tickets Closed, Open Tickets, Todos Created, Todos Done, Remaining — with previous iteration in parentheses
- **Discipline Reports:** Published count (current vs. previous, clickable), Drafts count (with amber "pending review" badge)
- **Last Meeting:** Staff Report Completion % (clickable), Meeting Attendance % excluding Jr Crew (clickable)

**UI Elements:**
- Department metrics table with all `StaffDepartment` cases as rows
- Discipline reports section: two metric tiles
- Meeting stats section: two metric tiles (completion % and attendance %)
- Detail modal with timeline charts: discipline (area chart), reports (percentage line chart), attendance (percentage line chart)
- Uses `Cache::flexible()` and `Cache::remember()` for caching

---

### Staff Engagement Widget
**File:** `resources/views/livewire/dashboard/command-staff-engagement.blade.php`
**Route:** Embedded in dashboard (`/dashboard`)

**Purpose:** Shows per-staff-member engagement metrics with pagination and a drill-down detail modal.

**Authorization:** `$this->authorize('view-command-dashboard')` in `viewStaffDetail()`.

**Table Columns:**
- Name (linked to profile), Department, Rank
- Todos Worked (current, previous in parens), Todos Open (amber badge if > 0)
- Tickets Worked (current, previous in parens), Tickets Open (amber badge if > 0)
- Reports (3mo): submitted / total, with color-coded "missed" badge (1=blue, 2=amber, 3+=red)
- Attendance (3mo): attended / total, red "missed" badge for Officers only; "---" for JrCrew
- Detail button

**Staff Detail Modal:**
- Shows user's 3-month history per iteration
- Table: Iteration date range, Todos Worked, Tickets Worked, Report Submitted (green/red badge), Attended (green/red badge)

**UI Elements:**
- Paginated table (15 per page), sorted by rank descending then name
- Legend text explaining parentheses and timeframes
- Pagination links

---

## 8. Actions (Business Logic)

### GetIterationBoundaries (`app/Actions/GetIterationBoundaries.php`)

**Signature:** `handle(): array`

**Step-by-step logic:**
1. Caches result for 24 hours at key `command_dashboard.iteration_boundaries`
2. Queries last 7 completed StaffMeeting records ordered by `end_time` descending
3. If no meetings: returns fallback (30-day window, no previous, empty iterations)
4. **Current iteration:** `last_meeting.end_time` → `now()`
5. **Previous iteration:** `second_last_meeting.end_time` → `last_meeting.end_time` (if >= 2 meetings)
6. **iterations_3mo:** iterates pairs of consecutive meetings, building iteration windows until one starts before 3 months ago
7. Returns: `current_start`, `current_end`, `current_meeting`, `previous_start`, `previous_end`, `previous_meeting`, `has_previous`, `iterations_3mo`

**Called by:** All three Command Dashboard widgets (community, department, staff engagement)

---

## 9. Notifications

Not applicable for this feature.

---

## 10. Background Jobs

Not applicable for this feature.

---

## 11. Console Commands & Scheduled Tasks

Not applicable for this feature.

---

## 12. Services

Not applicable for this feature.

---

## 13. Activity Log Entries

Not applicable for this feature. The Command Dashboard is read-only.

---

## 14. Data Flow Diagrams

### Viewing the Command Dashboard

```
Authorized user navigates to /dashboard
  -> GET /dashboard (middleware: auth)
    -> Dashboard view renders
    -> @can('view-command-dashboard') passes for Admin or Command dept staff
      -> <livewire:dashboard.command-community-engagement />
        -> $this->metrics (computed property)
          -> GetIterationBoundaries::run() → cached iteration windows
          -> Cache::flexible() for current metrics (new users, MC, Discord, active users, pending verification)
          -> Cache::remember() for previous metrics
        -> Renders metric tiles with deltas

      -> <livewire:dashboard.command-department-engagement />
        -> $this->metrics (computed property)
          -> GetIterationBoundaries::run()
          -> computeDeptMetrics() for each StaffDepartment (tickets opened/closed/remaining, todos created/completed/remaining)
          -> Discipline report counts (published, drafts)
          -> Meeting stats (report completion %, attendance %)
        -> Renders department table + metric tiles

      -> <livewire:dashboard.command-staff-engagement />
        -> $this->staffTable (computed property)
          -> GetIterationBoundaries::run()
          -> Paginated staff users (rank desc, name asc)
          -> Batch queries: tasks completed (current/previous), tasks open, tickets worked (current/previous), tickets open
          -> Meeting reports submitted (3mo), meetings attended (3mo)
        -> Renders paginated staff table
```

### Viewing a Detail Modal (Community)

```
User clicks on a metric tile (e.g., "New Users")
  -> showDetail('new_users')
    -> $this->authorize('view-command-dashboard')
    -> Sets activeDetailMetric = 'new_users'
    -> Flux::modal('community-detail-modal')->show()
  -> Modal renders $this->timelineData (computed property)
    -> GetIterationBoundaries::run() → iterations_3mo
    -> For each iteration: counts metric in that date range
    -> Returns array of {label, count} for Flux chart
  -> Flux chart renders area/line chart with tooltip
```

### Viewing Staff Detail Modal

```
User clicks "Detail" button on a staff row
  -> viewStaffDetail($userId)
    -> $this->authorize('view-command-dashboard')
    -> Verifies user is staff
    -> Sets selectedStaffId
    -> Flux::modal('staff-detail-modal')->show()
  -> Modal renders $this->staffDetail (computed property)
    -> GetIterationBoundaries::run() → iterations_3mo
    -> For each iteration: counts tasks completed, tickets worked, report submitted, meeting attended
    -> Returns {user, iterations: [{label, completed, tickets_worked, report_submitted, attended}]}
  -> Renders iteration-by-iteration table with green/red badges
```

---

## 15. Configuration

Not applicable for this feature directly. Caching uses hardcoded TTLs:
- Iteration boundaries: 24 hours (`Cache::remember`)
- Current metrics: 1h fresh / 12h stale (`Cache::flexible([3600, 43200])`)
- Previous metrics: 24 hours (`Cache::remember`)

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/Livewire/Dashboard/CommandCommunityEngagementTest.php` | 13 | Community metrics, permissions |
| `tests/Feature/Livewire/Dashboard/CommandDepartmentEngagementTest.php` | 14 | Department metrics, discipline, meeting stats, permissions |
| `tests/Feature/Livewire/Dashboard/CommandStaffEngagementTest.php` | 12 | Staff table, metrics, detail modal, permissions |
| `tests/Feature/Actions/GetIterationBoundariesTest.php` | 7 | Iteration boundary computation, caching |

### Test Case Inventory

**CommandCommunityEngagementTest:**
1. can render for authorized users
2. counts new users created in the current iteration
3. counts new minecraft accounts in the current iteration
4. counts pending minecraft verification accounts
5. counts new discord accounts in the current iteration
6. shows active users metric
7. opens detail modal
8. is visible to command department staff on the dashboard
9. is visible to admins on the dashboard
10. is visible to command department jr crew
11. is not visible to non-command officers
12. is not visible to non-command crew members
13. is not visible to regular members

**CommandDepartmentEngagementTest:**
1. can render for authorized users
2. counts tickets opened in current iteration by department
3. counts tickets remaining open
4. counts todos created and completed by department
5. shows discipline reports published count
6. shows draft reports count with attention badge
7. shows staff report completion percentage for previous meeting
8. shows meeting attendance percentage excluding jr crew
9. shows dashes when no previous meeting exists
10. opens detail modal for discipline timeline
11. is visible to command department staff on the dashboard
12. is visible to admins on the dashboard
13. is not visible to non-command staff
14. is not visible to regular members

**CommandStaffEngagementTest:**
1. can render for authorized users
2. shows paginated table of staff members
3. does not show non-staff users in the table
4. shows current iteration todo assigned and completed counts per staff
5. shows reports submitted count over last 3 months
6. shows meetings attended count over last 3 months
7. shows meetings missed only for crew member and above, not jr crew
8. opens staff detail modal with 3-month history
9. is visible to command department staff on the dashboard
10. is visible to admins on the dashboard
11. is not visible to non-command officers
12. is not visible to regular members

**GetIterationBoundariesTest:**
1. returns fallback boundaries when no completed staff meetings exist
2. computes current iteration from last completed staff meeting to now
3. computes previous iteration between second-to-last and last meetings
4. builds iterations_3mo array for completed meetings within 3 months
5. only considers staff meetings, not board or community meetings
6. only considers completed meetings, not pending or cancelled
7. caches results

### Coverage Gaps

- **No test for ticket metrics in staff engagement** — the tickets worked/open columns are not directly tested
- **No test for previous iteration comparisons** — delta badges (green/red) showing +/- vs. previous iteration are not tested
- **No test for detail modal timeline data** — the `timelineData` computed property and chart rendering are not tested beyond "opens detail modal"
- **No test for cache invalidation** — cache TTLs and stale-while-revalidate behavior are not tested
- **No test for edge case: very few meetings** — behavior with exactly 1 meeting (no previous, no iterations_3mo) is partially tested

---

## 17. File Map

**Models:** None specific (reads from User, Task, Thread, Meeting, MeetingReport, MinecraftAccount, DiscordAccount, DisciplineReport)

**Enums:**
- `app/Enums/StaffRank.php`
- `app/Enums/StaffDepartment.php`
- `app/Enums/TaskStatus.php`
- `app/Enums/ThreadStatus.php`
- `app/Enums/ThreadType.php`
- `app/Enums/MeetingStatus.php`
- `app/Enums/MeetingType.php`
- `app/Enums/MinecraftAccountStatus.php`
- `app/Enums/ReportStatus.php`

**Actions:**
- `app/Actions/GetIterationBoundaries.php`

**Policies:** None

**Gates:** `AuthServiceProvider.php` — gates: `view-command-dashboard`

**Notifications:** None

**Jobs:** None

**Services:** None

**Controllers:** None

**Volt Components:**
- `resources/views/livewire/dashboard/command-community-engagement.blade.php`
- `resources/views/livewire/dashboard/command-department-engagement.blade.php`
- `resources/views/livewire/dashboard/command-staff-engagement.blade.php`

**Routes:** None specific (embedded in `/dashboard`)

**Migrations:** None specific

**Console Commands:** None

**Tests:**
- `tests/Feature/Livewire/Dashboard/CommandCommunityEngagementTest.php`
- `tests/Feature/Livewire/Dashboard/CommandDepartmentEngagementTest.php`
- `tests/Feature/Livewire/Dashboard/CommandStaffEngagementTest.php`
- `tests/Feature/Actions/GetIterationBoundariesTest.php`

**Config:** None specific

**Other:**
- `resources/views/dashboard.blade.php` (parent view with `@can('view-command-dashboard')` guard)

---

## 18. Known Issues & Improvement Opportunities

1. **Cache TTLs are hardcoded** — Iteration boundaries (24h), current metrics (1h/12h), and previous metrics (24h) use hardcoded TTL values. These should be configurable via `config/lighthouse.php`.

2. **No cache invalidation on meeting completion** — When a staff meeting is completed, the iteration boundaries cache is not invalidated. The dashboard will show stale boundaries until the 24-hour cache expires. Consider invalidating `command_dashboard.iteration_boundaries` when a meeting status changes to `Completed`.

3. **N+1 potential in staff detail modal** — The `staffDetail` computed property runs individual queries per iteration (tasks, tickets, reports, attendance). For 6+ iterations, this results in ~24 queries. Consider batch-loading across iterations.

4. **No authorization on `staffTable` computed property** — The `staffTable` property is a computed property that runs on every render. While the widget is only shown to authorized users via `@can`, the computed property itself doesn't check authorization. The `viewStaffDetail()` method does authorize, which is good.

5. **Department engagement widget uses `section_key` for task department** — Tasks are filtered by `section_key` matching `StaffDepartment` values. If section keys diverge from department values, this coupling could break.

6. **Attendance excludes JrCrew but reports include them** — Meeting attendance metrics exclude JrCrew (`staff_rank >= CrewMember`), but report completion counts all staff (`staff_rank != None`). This is intentional (JrCrew are expected to submit reports but not required to attend meetings) but could be confusing.

7. **Previous iteration delta colors are inverted for discipline reports** — The discipline reports delta badge uses `green` for decrease and `amber` for increase (`$delta <= 0 ? 'green' : 'amber'`), which is intentionally inverted (fewer reports = good). This is correct but could be confusing without explicit documentation.

8. **Hard-coded pagination size** — Staff table pagination is fixed at 15 per page. Consider making this configurable.

9. **`with()` method calls `auth()->user()->fresh()` on every render** — The staff engagement widget refreshes the user model from the database on every component render via `with()`. This is needed for account data freshness but adds a query per render cycle.
