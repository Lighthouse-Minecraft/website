# Activity Logging -- Technical Documentation

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

The Activity Logging feature provides a comprehensive audit trail for the Lighthouse Website. Every significant user action — from registration, to membership promotion, to Minecraft account linking, to discipline reports — is recorded as a structured log entry in the `activity_logs` table. Each entry captures who performed the action (causer), what it was performed on (subject, via polymorphic relation), what the action was (a snake_case action string), a human-readable description, and metadata including IP address and user agent.

Activity logs are created throughout the application via the `RecordActivity` action class, which is called from over 40 locations across action classes and Livewire Volt components. The action class uses Laravel's `AsAction` trait and can be invoked statically as `RecordActivity::run($subject, 'action_string', 'description')` or `RecordActivity::handle(...)`.

The logs are viewable by administrators, Officers, and Engineering department staff through the Activity Log tab in the Admin Control Panel (ACP). The viewer provides a filterable, paginated table with timezone-aware timestamps, clickable subject links, and causer identification. The `ActivityLog` model also includes a `scopeRelevantTo` scope for querying logs related to a specific user (either as causer or subject), which could be used for per-user activity views.

Key terminology:
- **Causer** — the user who initiated the action (nullable for system/anonymous actions)
- **Subject** — the Eloquent model the action was performed on (polymorphic: User, Thread, Announcement, StaffPosition, BoardMember, Meeting, MeetingReport, DisciplineReport, etc.)
- **Action** — a snake_case string identifying the type of activity (e.g., `user_promoted`, `minecraft_account_linked`)
- **Meta** — a JSON object storing request IP and user agent at the time of the action

---

## 2. Database Schema

### `activity_logs` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint (auto) | No | — | Primary key |
| `causer_id` | foreignId (users) | Yes | NULL | User who caused the activity; null for system actions |
| `subject_type` | string | No | — | Polymorphic model class (e.g., `App\Models\User`) |
| `subject_id` | unsignedBigInteger | No | — | Polymorphic model ID |
| `action` | string | No | — | Snake_case action identifier |
| `description` | text | Yes | NULL | Human-readable description |
| `meta` | json | Yes | NULL | Structured metadata (IP, user agent) |
| `created_at` | timestamp | Yes | — | Laravel timestamp |
| `updated_at` | timestamp | Yes | — | Laravel timestamp |

**Indexes:**
- `activity_logs_subject_type_subject_id_index` — composite index on `(subject_type, subject_id)`
- `activity_logs_causer_id_index` — index on `causer_id`
- `activity_logs_action_index` — index on `action`

**Foreign Keys:**
- `causer_id` → `users.id` (ON DELETE SET NULL)

**Migration(s):** `database/migrations/2025_08_05_121307_create_activity_logs_table.php`

---

## 3. Models & Relationships

### ActivityLog (`app/Models/ActivityLog.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `causer()` | belongsTo | User | The user who performed the action; nullable |
| `subject()` | morphTo | (polymorphic) | The model the action was performed on |

**Scopes:**
- `scopeRelevantTo(Builder $query, User $user)` — returns logs where the user is either the causer OR the subject (when subject_type is User)

**Key Methods:** None beyond relationships and scopes.

**Casts:**
- `meta` => `array`

**Fillable:** `causer_id`, `subject_type`, `subject_id`, `action`, `description`, `meta`

---

## 4. Enums Reference

Not applicable for this feature. Activity logging does not use any enums of its own. The `action` field uses freeform snake_case strings rather than an enum.

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

| Gate Name | Who Can Pass | Logic Summary |
|-----------|-------------|---------------|
| `view-activity-log` | Admin, Officer+, Engineer dept (any rank) | Shares the `$canViewLogs` closure with other log gates |

### Policies

No dedicated ActivityLog policy exists. Access is controlled entirely through the `view-activity-log` gate.

### Permissions Matrix

| User Type | View Activity Log | Create Log Entries |
|-----------|------------------|--------------------|
| Regular User | No | Yes (implicitly, via actions they trigger) |
| Page Editor | No | Yes |
| Non-Engineering JrCrew | No | Yes |
| Non-Engineering CrewMember | No | Yes |
| Engineering (any rank) | Yes | Yes |
| Any Officer | Yes | Yes |
| Admin | Yes | Yes |

Note: "Create Log Entries" is implicit — any user whose actions trigger `RecordActivity::run()` creates log entries. There is no direct user-facing "create" action.

---

## 6. Routes

The Activity Log viewer is embedded as a tab within the ACP and does not have its own dedicated route. It is accessed via:

| Method | URL | Middleware | Handler | Route Name |
|--------|-----|-----------|---------|------------|
| GET | `/acp?category=logs&tab=activity-log` | auth | `AdminControlPanelController@index` → `admin-control-panel-tabs` → `admin-manage-activity-log-page` | `acp.index` |

---

## 7. User Interface Components

### Activity Log Viewer
**File:** `resources/views/livewire/admin-manage-activity-log-page.blade.php`
**Route:** `/acp?category=logs&tab=activity-log` (embedded in ACP)

**Purpose:** Displays a paginated, filterable table of all activity log entries across the application.

**Authorization:** Relies on the parent ACP tab component's `@can('view-activity-log')` directive. The component itself does not call `$this->authorize()`.

**PHP Properties:**
- `$perPage` (int, default 25) — pagination size
- `$filterAction` (string) — currently selected action filter
- `$distinctActions` (array) — all distinct action strings from the database, loaded on mount

**Key Methods:**
- `mount()` — loads all distinct action values from ActivityLog for the filter dropdown
- `updatedFilterAction()` — resets pagination when filter changes
- `activities()` (computed) — queries ActivityLog with `causer` and `subject` eager-loaded, filtered by action, ordered by newest first, paginated

**UI Elements:**
- **Header:** "User Activity Log" heading with action filter dropdown
- **Filter dropdown:** Select with "All Actions" default, populated from distinct action values in the database (formatted as Title Case with underscores replaced by spaces)
- **Table columns:**
  - Date/Time — timezone-aware display (uses auth user's timezone, defaults to UTC), shows date on first line and time on second
  - Subject — clickable link: User subjects link to profile, Thread subjects link to ticket view, other subjects show "ClassName #id", deleted subjects show "[deleted]"
  - Action — badge with Title Case formatting
  - By User — clickable link to causer's profile, or "System" italic text for null causers
  - Details — description text in muted color
- **Pagination:** 25 entries per page

---

## 8. Actions (Business Logic)

### RecordActivity (`app/Actions/RecordActivity.php`)

**Signature:** `handle($subject, $action, $description = null, ?User $actor = null): void`

**Step-by-step logic:**
1. Determines causer: uses `$actor->id` if provided, otherwise `Auth::id()` if authenticated, otherwise `null`
2. Captures metadata: `request()->ip()` and `request()->userAgent()`
3. Creates `ActivityLog` record with: `causer_id`, `subject_type` (from `get_class($subject)`), `subject_id` (from `$subject->getKey()`), `action`, `description`, `meta`

**Called by:** Over 40 locations across the application (see Section 13 for complete list).

**Notes:**
- The `$actor` parameter was added to support cases where the acting user differs from the authenticated user (e.g., system processes acting on behalf of a user)
- Uses `get_class()` rather than `$subject->getMorphClass()`, so the full class name is stored as `subject_type`
- Does not validate the `$action` string or `$subject` parameter — trusts the caller

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

This is the comprehensive catalog of every action string used with `RecordActivity::run()` across the application:

### User Lifecycle

| Action String | Logged By | Subject Model | Description Pattern |
|---------------|-----------|---------------|---------------------|
| `user_registered` | `resources/views/livewire/auth/register.blade.php` | User | "User registered for an account" |
| `rules_accepted` | `resources/views/livewire/dashboard/view-rules.blade.php` | User | "User accepted community rules and was promoted to Stowaway" |
| `user_promoted` | `PromoteUser` | User | "Promoted from {current} to {next}." |
| `user_demoted` | `DemoteUser` | User | "Demoted from {current} to {previous}." |
| `user_promoted_to_admin` | `PromoteUserToAdmin` | User | "Promoted to Admin role." |
| `update_profile` | `resources/views/livewire/users/display-basic-details.blade.php` | User | "User profile updated." |

### Brig System

| Action String | Logged By | Subject Model | Description Pattern |
|---------------|-----------|---------------|---------------------|
| `user_put_in_brig` | `PutUserInBrig` | User | "Put in brig by {admin}. Reason: {reason}. [Timer/Appeal info]" |
| `user_released_from_brig` | `ReleaseUserFromBrig` | User | "Released from [disciplinary] brig by {admin}. Reason: {reason}" |

### Minecraft Accounts

| Action String | Logged By | Subject Model | Description Pattern |
|---------------|-----------|---------------|---------------------|
| `minecraft_verification_generated` | `GenerateVerificationCode` | User | "Generated verification code for {type} account: {username}" |
| `minecraft_whitelisted` | `GenerateVerificationCode` | User | "Added {username} to server whitelist" |
| `minecraft_account_linked` | `CompleteVerification` | User | "Linked {type} account: {username}" |
| `minecraft_rank_synced` | `CompleteVerification` | User | "Synced Minecraft rank to {rank} for {username}" |
| `minecraft_staff_position_set` | `CompleteVerification`, `SyncMinecraftStaff` | User | "Set Minecraft staff position to {dept} for {username}" |
| `minecraft_staff_position_removed` | `UnlinkMinecraftAccount`, `RevokeMinecraftAccount`, `SyncMinecraftStaff` | User | "Removed Minecraft staff position for {username}" |
| `minecraft_rank_reset_requested` | `UnlinkMinecraftAccount` | User | "Reset rank to default for {username}" |
| `minecraft_whitelist_removal_requested` | `UnlinkMinecraftAccount` | User | "Removed {username} from server whitelist" |
| `minecraft_account_removed` | `UnlinkMinecraftAccount` | User | "Removed {type} account: {username}" |
| `minecraft_account_revoked` | `RevokeMinecraftAccount` | User | "{admin} revoked {type} account: {username}" |
| `minecraft_account_reactivated` | `ReactivateMinecraftAccount` | User | "Reactivated {type} account: {username}" |
| `minecraft_account_permanently_deleted` | `ForceDeleteMinecraftAccount` | User | "Admin {admin} permanently deleted {type} account: {username}" |
| `minecraft_account_removed_by_parent` | `RemoveChildMinecraftAccount` | User | "{parent} removed {type} account: {username}" |
| `minecraft_reward_granted` | `GrantMinecraftReward` | User | "Granted {reward}: {desc} to {username}" |

### Discord Accounts

| Action String | Logged By | Subject Model | Description Pattern |
|---------------|-----------|---------------|---------------------|
| `discord_account_linked` | `LinkDiscordAccount` | User | "Linked Discord account: {username} ({discordId})" |
| `discord_account_unlinked` | `UnlinkDiscordAccount` | User | "Unlinked Discord account: {username} ({discordId}) by {performer}" |
| `discord_account_revoked` | `RevokeDiscordAccount` | User | "Discord account revoked by {admin}: {username} ({discordId})" |
| `discord_roles_synced` | `SyncDiscordRoles` | User | "Synced Discord membership role to {level}" |
| `discord_staff_synced` | `SyncDiscordStaff` | User | "Synced Discord staff roles: {department}" |
| `discord_staff_removed` | `SyncDiscordStaff` | User | "Removed Discord staff roles" |

### Staff Positions

| Action String | Logged By | Subject Model | Description Pattern |
|---------------|-----------|---------------|---------------------|
| `staff_position_assigned` | `AssignStaffPosition` | StaffPosition | "Assigned {user} to position: {title} ({dept}, {rank})" |
| `staff_position_unassigned` | `UnassignStaffPosition` | StaffPosition | "Unassigned {user} from position: {title}" |
| `staff_position_updated` | `SetUsersStaffPosition` | User | Dynamic update text |
| `staff_position_removed` | `RemoveUsersStaffPosition` | User | Dynamic update text |

### Parent Portal

| Action String | Logged By | Subject Model | Description Pattern |
|---------------|-----------|---------------|---------------------|
| `parent_linked` | `AutoLinkParentOnRegistration` | User | "Parent account ({email}) automatically linked." |
| `parent_permission_changed` | `UpdateChildPermission` (4 calls) | User | "{permission} {enabled/disabled} by parent {name}." |
| `child_account_created` | `CreateChildAccount` | User | "Account created by parent {name}." |
| `child_released_to_adult` | `ReleaseChildToAdult` | User | Dynamic description |
| `update_child_account` | `resources/views/livewire/parent-portal/index.blade.php` | User | "Child account updated by parent {name}." |

### Discipline Reports

| Action String | Logged By | Subject Model | Description Pattern |
|---------------|-----------|---------------|---------------------|
| `discipline_report_created` | `CreateDisciplineReport` | User (subject) | Dynamic description |
| `discipline_report_updated` | `UpdateDisciplineReport` | User (subject) | Dynamic description |
| `discipline_report_published` | `PublishDisciplineReport` | User (subject) | Dynamic description |

### Support Tickets

| Action String | Logged By | Subject Model | Description Pattern |
|---------------|-----------|---------------|---------------------|
| `ticket_opened` | `resources/views/livewire/ready-room/tickets/create-ticket.blade.php` | Thread | "Opened ticket: {subject}" |
| `ticket_joined` | `resources/views/livewire/ready-room/tickets/view-ticket.blade.php` (2 calls) | Thread | "Joined ticket: {subject}" |
| `assignment_changed` | `resources/views/livewire/ready-room/tickets/view-ticket.blade.php` | Thread | Dynamic description |
| `message_flagged` | `FlagMessage` | Thread | "Message flagged by {user}" |
| `flag_acknowledged` | `AcknowledgeFlag` | Thread | "Flag acknowledged by {reviewer}[notes]" |

### Board Members

| Action String | Logged By | Subject Model | Description Pattern |
|---------------|-----------|---------------|---------------------|
| `board_member_created` | `CreateBoardMember` | BoardMember | "Board member created: {name}" |
| `board_member_updated` | `UpdateBoardMember` | BoardMember | "Board member updated: {name}" |
| `board_member_deleted` | `DeleteBoardMember` | BoardMember | "Board member deleted: {name}" |
| `board_member_linked` | `LinkBoardMemberToUser` | BoardMember | "Board member '{name}' linked to user {user}" |
| `board_member_unlinked` | `UnlinkBoardMemberFromUser` | BoardMember | "Board member '{name}' unlinked from user {user}" |

### Content Management

| Action String | Logged By | Subject Model | Description Pattern |
|---------------|-----------|---------------|---------------------|
| `announcement_created` | `admin-manage-announcements-page` | Announcement | "Announcement created." |
| `announcement_updated` | `admin-manage-announcements-page` | Announcement | "Announcement updated." |
| `announcement_deleted` | `admin-manage-announcements-page` | Announcement | "Announcement deleted." |
| `update_meeting` | `resources/views/livewire/meetings/manage-meeting.blade.php` | Meeting | "Updated meeting metadata." |
| `toggle_community_updates` | `resources/views/livewire/meetings/manage-meeting.blade.php` | Meeting | "Toggled community updates visibility." |
| `seed_default_questions` | `CreateDefaultMeetingQuestions` | Meeting | "Seeded default meeting questions." |
| `submit_meeting_report` | `SubmitMeetingReport` | MeetingReport | "Submitted or updated meeting report." |

---

## 14. Data Flow Diagrams

### Recording an Activity (Generic Flow)

```
User performs action (e.g., clicks "Promote" on user profile)
  -> Volt Component method fires
    -> Action::run(...) business logic executes
      -> RecordActivity::run($subject, 'action_string', 'Description', $actor?)
        -> Determine causer_id: $actor?.id ?? Auth::id() ?? null
        -> Capture meta: { ip: request()->ip(), user_agent: request()->userAgent() }
        -> ActivityLog::create([causer_id, subject_type, subject_id, action, description, meta])
        -> Record saved to activity_logs table
```

### Viewing the Activity Log

```
Staff navigates to /acp?category=logs&tab=activity-log
  -> GET /acp (middleware: auth)
    -> AdminControlPanelController@index
      -> Gate::authorize('view-acp')
      -> return view('admin.control-panel.index')
        -> <livewire:admin-control-panel-tabs />
          -> category='logs', tab='activity-log'
          -> @can('view-activity-log') passes
            -> <livewire:admin-manage-activity-log-page />
              -> mount(): load distinct action strings from DB
              -> activities(): query ActivityLog with causer+subject, paginate 25
              -> Render table with timezone-aware timestamps
```

### Filtering the Activity Log

```
Staff selects action from filter dropdown
  -> wire:model.live="filterAction" updates
    -> updatedFilterAction() fires
      -> resetPage() (back to page 1)
    -> activities() recomputes
      -> ->where('action', $filterAction) applied
      -> New paginated results rendered
```

### Anonymous Activity (System Action)

```
System process (no authenticated user) triggers action
  -> RecordActivity::run($subject, 'action_string', 'Description')
    -> Auth::check() returns false
    -> causer_id set to null
    -> ActivityLog created with null causer
    -> In viewer, causer column shows "System" (italic)
```

### Activity with Explicit Actor

```
Action needs to attribute to specific user (not necessarily Auth user)
  -> RecordActivity::run($subject, 'action_string', 'Description', $specificUser)
    -> causer_id = $specificUser->id (overrides Auth::id())
    -> Useful for webhook-triggered actions or background processes
```

---

## 15. Configuration

Not applicable for this feature. Activity logging has no config values or environment variables.

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Unit/Actions/RecordActivityTest.php` | 12 tests | Core RecordActivity action behavior |
| `tests/Feature/Tickets/TicketActivityLoggingTest.php` | 5 tests | Ticket-specific activity logging |

### Test Case Inventory

#### `RecordActivityTest.php`

1. `it records activity with authenticated user as causer` — verifies all fields including meta (IP, user agent)
2. `it records activity with null causer when not authenticated` — anonymous action logging
3. `it records activity with null description when not provided` — optional description
4. `it captures correct subject class for different model types` — polymorphic subject_type
5. `it stores meta information correctly` — IP and user agent capture
6. `it handles empty or null user agent gracefully` — null user agent edge case
7. `it creates multiple activity logs for multiple actions` — multiple sequential entries
8. `it maintains activity log relationships correctly` — causer and subject relationship loading
9. `it works with different authenticated users` — switching auth context between entries
10. `it stores correct timestamps when activities are created` — timestamp accuracy
11. `it handles special characters in action and description` — unicode support
12. `it works when called statically` — static invocation via `::handle()`

#### `TicketActivityLoggingTest.php`

1. `it logs activity when a ticket is opened` — `ticket_opened` action on Thread subject
2. `it logs activity when staff joins a ticket by replying` — `ticket_joined` action
3. `it logs activity when viewer becomes participant` — viewer-to-participant transition logs `ticket_joined`
4. `it does not log activity for regular replies` — confirms no `message_sent` or `internal_note_added` logging
5. `it does not log activity for internal notes` — confirms no `internal_note_added` logging

### Coverage Gaps

- **No test for `$actor` parameter** — `RecordActivity::handle()` accepts an optional `$actor` parameter to override the authenticated user, but no test exercises this path.
- **No test for the Activity Log viewer component** — `admin-manage-activity-log-page.blade.php` has no dedicated tests for rendering, filtering, or pagination.
- **No test for `scopeRelevantTo`** — the ActivityLog model's scope for querying user-related activities is untested.
- **No test for the `view-activity-log` gate** — while `AcpTabPermissionsTest.php` tests `view-activity-log` for Engineering JrCrew, Officers, and denied cases, there is no dedicated test for the Admin role.
- **Action string consistency is untested** — there are no tests validating that specific actions (beyond tickets) produce the expected action strings and descriptions.
- **No test for deleted subject display** — the viewer shows "[deleted]" for subjects that no longer exist, but this isn't tested.
- **Many action files test that ActivityLog is created** — individual action tests (e.g., `PutUserInBrigTest`, `PromoteUserTest`, `CreateDisciplineReportTest`, etc.) verify that activity is logged, but these are spread across 35+ test files and are not centralized.

---

## 17. File Map

**Models:**
- `app/Models/ActivityLog.php`

**Enums:** None

**Actions:**
- `app/Actions/RecordActivity.php`

**Policies:** None

**Gates:** `app/Providers/AuthServiceProvider.php` — gates: `view-activity-log`

**Notifications:** None

**Jobs:** None

**Services:** None

**Controllers:** None (accessed via ACP)

**Volt Components:**
- `resources/views/livewire/admin-manage-activity-log-page.blade.php` (viewer)

**Callers (Action files that invoke RecordActivity):**
- `app/Actions/PromoteUser.php`
- `app/Actions/DemoteUser.php`
- `app/Actions/PromoteUserToAdmin.php`
- `app/Actions/PutUserInBrig.php`
- `app/Actions/ReleaseUserFromBrig.php`
- `app/Actions/AssignStaffPosition.php`
- `app/Actions/UnassignStaffPosition.php`
- `app/Actions/SetUsersStaffPosition.php`
- `app/Actions/RemoveUsersStaffPosition.php`
- `app/Actions/GenerateVerificationCode.php`
- `app/Actions/CompleteVerification.php`
- `app/Actions/UnlinkMinecraftAccount.php`
- `app/Actions/RevokeMinecraftAccount.php`
- `app/Actions/ReactivateMinecraftAccount.php`
- `app/Actions/ForceDeleteMinecraftAccount.php`
- `app/Actions/RemoveChildMinecraftAccount.php`
- `app/Actions/GrantMinecraftReward.php`
- `app/Actions/SyncMinecraftStaff.php`
- `app/Actions/LinkDiscordAccount.php`
- `app/Actions/UnlinkDiscordAccount.php`
- `app/Actions/RevokeDiscordAccount.php`
- `app/Actions/SyncDiscordRoles.php`
- `app/Actions/SyncDiscordStaff.php`
- `app/Actions/CreateDisciplineReport.php`
- `app/Actions/UpdateDisciplineReport.php`
- `app/Actions/PublishDisciplineReport.php`
- `app/Actions/CreateChildAccount.php`
- `app/Actions/UpdateChildPermission.php`
- `app/Actions/ReleaseChildToAdult.php`
- `app/Actions/AutoLinkParentOnRegistration.php`
- `app/Actions/FlagMessage.php`
- `app/Actions/AcknowledgeFlag.php`
- `app/Actions/CreateBoardMember.php`
- `app/Actions/UpdateBoardMember.php`
- `app/Actions/DeleteBoardMember.php`
- `app/Actions/LinkBoardMemberToUser.php`
- `app/Actions/UnlinkBoardMemberFromUser.php`
- `app/Actions/SubmitMeetingReport.php`
- `app/Actions/CreateDefaultMeetingQuestions.php`

**Callers (Livewire components that invoke RecordActivity directly):**
- `resources/views/livewire/auth/register.blade.php`
- `resources/views/livewire/dashboard/view-rules.blade.php`
- `resources/views/livewire/users/display-basic-details.blade.php`
- `resources/views/livewire/parent-portal/index.blade.php`
- `resources/views/livewire/admin-manage-announcements-page.blade.php`
- `resources/views/livewire/meetings/manage-meeting.blade.php`
- `resources/views/livewire/ready-room/tickets/create-ticket.blade.php`
- `resources/views/livewire/ready-room/tickets/view-ticket.blade.php`
- `resources/views/livewire/ready-room/tickets/create-admin-ticket.blade.php`
- `resources/views/livewire/dashboard/in-brig-card.blade.php`

**Routes:**
- `acp.index` — `GET /acp` (activity log accessed via `?category=logs&tab=activity-log`)

**Migrations:**
- `database/migrations/2025_08_05_121307_create_activity_logs_table.php`

**Console Commands:** None

**Tests:**
- `tests/Unit/Actions/RecordActivityTest.php`
- `tests/Feature/Tickets/TicketActivityLoggingTest.php`

**Config:** None

**Other:** None

---

## 18. Known Issues & Improvement Opportunities

1. **No authorization check in the viewer component** — `admin-manage-activity-log-page.blade.php` does not call `$this->authorize('view-activity-log')` in its `mount()` method. It relies entirely on the parent ACP tab's `@can` directive. If mounted outside the ACP, it would be accessible to any authenticated user.

2. **No `ActivityLog` policy** — there is no `ActivityLogPolicy` class. All access control is through the `view-activity-log` gate. If future features need per-record authorization (e.g., restricting which logs a user can see), a policy would need to be created.

3. **Action strings are not constrained by an enum** — action strings are freeform strings with no validation. This means typos or inconsistencies (e.g., `minecraft_account_removed` vs `minecraft_account_permanently_deleted`) are possible. An enum or constants class would provide compile-time safety and a single source of truth.

4. **`get_class()` used instead of `getMorphClass()`** — `RecordActivity` uses `get_class($subject)` to store the `subject_type`, which stores the full class name (e.g., `App\Models\User`). Using `$subject->getMorphClass()` would be more resilient to namespace changes and follows Laravel's polymorphic conventions.

5. **Some callers use `::handle()` instead of `::run()`** — while both work (the `AsAction` trait maps `::run()` to `handle()`), the project convention is to use `::run()`. Several action files use `::handle()` directly, which is inconsistent.

6. **No log retention/cleanup mechanism** — activity logs grow indefinitely. There is no scheduled task to prune old entries or archive them, which could become a performance concern over time.

7. **Meta captures only IP and user agent** — the meta field could capture additional context (e.g., the route/URL where the action occurred, or changed field values for update operations), but currently only stores request-level metadata.

8. **No dashboard widget for activity** — unlike discipline reports which have a dashboard widget, there is no activity log widget on the dashboard showing recent activity to authorized staff.

9. **`scopeRelevantTo` is defined but not used** — the model defines a `relevantTo` scope for querying a user's activity, but no component or feature currently uses it. It may be intended for a future per-user activity view on profiles.

10. **Inconsistent caller patterns** — some actions call `RecordActivity::run()` through the import alias, while Livewire components use the fully qualified `\App\Actions\RecordActivity::run()`. This is cosmetic but could be standardized.
