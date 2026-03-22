# Rank-Based Role Assignments -- Technical Documentation

> **Audience:** Project owner, developers, AI agents
> **Generated:** 2026-03-22
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

The Rank-Based Role Assignments feature makes staff rank permissions visible, configurable, and consistent across the Lighthouse Website. Previously, staff permissions were split between two invisible systems: roles assigned to staff positions, and hardcoded `isAtLeastRank()` checks scattered across gates and policies. This feature unifies them under a single role-based authorization model.

**What it does:**
- Introduces a `role_staff_rank` pivot table allowing roles to be assigned to staff ranks (Jr Crew, Crew Member, Officer), just as roles are already assigned to positions.
- Extends `User::hasRole()` to check rank-level role assignments in addition to position-level ones.
- Replaces all `isAtLeastRank()` checks in gates and policies with `hasRole()` checks using new feature-scoped roles (e.g., "Ticket - User", "Task - Department", "Meeting - Secretary").
- Renames existing roles from inconsistent formats to a standardized `Feature - Tier` convention (e.g., "Page Editor" → "Page - Editor").
- Seeds 12 new feature-scoped roles covering tickets, tasks, meetings, internal notes, discipline reports, applicant review, and officer docs.
- Adds a rank role management UI to the Staff Position management page with three rank cards and a grouped role picker.
- Displays rank-level roles on user profiles in a distinct section.

**Who uses it:** Admins manage rank-level role assignments. All staff members benefit from the transparent permission system. The grouped role picker and rank cards are visible on the Staff Position management page. Rank roles are visible on user profiles to anyone with the `Staff Access` role.

**Key concepts:**
- **Rank roles** — roles assigned to a staff rank (Jr Crew, Crew Member, Officer) via `role_staff_rank`. All users at that rank receive these roles.
- **Position roles** — roles assigned to a specific staff position via `role_staff_position`. Only the user in that position receives them.
- **Feature - Tier naming** — all roles follow a `Feature - Tier` convention (e.g., "Ticket - User", "Ticket - Manager"). Standalone roles like "Moderator" and "Brig Warden" are grouped under "Other".
- **No rank inheritance** — Officer rank roles do NOT auto-inherit Crew Member rank roles. Each rank gets explicit assignments only.

---

## 2. Database Schema

### `roles` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | No | auto | |
| name | varchar(255) | No | | Unique role name in Feature - Tier format |
| description | varchar(255) | Yes | null | Human-readable description |
| color | varchar(255) | Yes | null | Flux badge color (e.g., "blue", "amber") |
| icon | varchar(255) | Yes | null | Heroicon name (e.g., "ticket", "shield-check") |
| created_at | timestamp | Yes | | |
| updated_at | timestamp | Yes | | |

### `role_staff_rank` table (NEW)

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| role_id | bigint (FK) | No | | References `roles.id` with cascade delete |
| staff_rank | unsigned tinyint | No | | StaffRank enum value (1=JrCrew, 2=CrewMember, 3=Officer) |
| created_at | timestamp | Yes | | |
| updated_at | timestamp | Yes | | |

**Indexes:** Unique constraint on `(role_id, staff_rank)`
**Foreign Keys:** `role_id` → `roles.id` (cascade delete)
**Migration:** `database/migrations/2026_03_21_000002_create_role_staff_rank_table.php`

### `role_staff_position` table (existing, for reference)

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| role_id | bigint (FK) | No | | References `roles.id` |
| staff_position_id | bigint (FK) | No | | References `staff_positions.id` |

---

## 3. Models & Relationships

### Role (`app/Models/Role.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `staffPositions()` | belongsToMany | StaffPosition | Via `role_staff_position` pivot |

**Key Methods:**
- `getGroupAttribute(): string` — Computed accessor that extracts the feature group prefix from the role name. Returns the text before ` - ` (e.g., "Ticket" from "Ticket - User") or "Other" for roles without that separator.

**Casts:** None

### User (`app/Models/User.php`) — relevant methods

**Key Methods:**
- `hasRole(string $roleName): bool` — Checks if user has a role through any of four paths: (1) admin override, (2) position Allow All, (3) position's assigned roles, (4) user's staff rank has the role via `role_staff_rank`. Returns true if any path matches.
- `isAtLeastRank(StaffRank $rank): bool` — Compares user's rank value. Still exists for JrCrew privacy display checks and non-permission uses but no longer used in gates/policies.
- `staffPosition(): HasOne` — The user's staff position.

### StaffPosition (`app/Models/StaffPosition.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `roles()` | belongsToMany | Role | Via `role_staff_position` pivot |
| `user()` | belongsTo | User | The user assigned to this position |

---

## 4. Enums Reference

### StaffRank (`app/Enums/StaffRank.php`)

| Case | Value | Label | Color |
|------|-------|-------|-------|
| None | 0 | None | zinc |
| JrCrew | 1 | Junior Crew Member | amber |
| CrewMember | 2 | Crew Member | fuchsia |
| Officer | 3 | Officer | emerald |

**Helper methods:** `label()`, `color()`, `discordRoleId()`

---

## 5. Authorization & Permissions

### Complete Role Inventory

**Renamed roles (in-place rename preserving assignments):**

| Old Name | New Name |
|----------|----------|
| Page Editor | Page - Editor |
| Announcement Editor | Announcement - Editor |
| Manage Membership Level | Membership Level - Manager |
| Manage Community Stories | Community Stories - Manager |
| Manage Discipline Reports | Discipline Report - Manager |
| Publish Discipline Reports | Discipline Report - Publisher |
| Manage Site Config | Site Config - Manager |
| Manage Staff Meeting | Meeting - Manager |
| View Logs | Logs - Viewer |
| View All Ready Rooms | Ready Room - View All |
| User Manager | User - Manager |
| View PII | PII - Viewer |
| View Command Dashboard | Command Dashboard - Viewer |
| Blog Author | Blog - Author |

**New feature-scoped roles:**

| Name | Description | Color | Icon |
|------|-------------|-------|------|
| Staff Access | View ACP, ready room, staff docs, internal notes, discipline reports, meetings, edit own staff bio | sky | identification |
| Ticket - User | Create tickets, respond, close, reopen, view archived tickets | blue | ticket |
| Ticket - Manager | Archive and delete tickets + all Ticket - User capabilities | blue | inbox-stack |
| Task - Department | Create and edit tasks for own department | green | clipboard-document-list |
| Task - Manager | Create and edit tasks for any department | green | clipboard-document-check |
| Meeting - Department | Edit meeting notes for own department only | violet | document-text |
| Meeting - Secretary | Edit meeting notes for all departments | violet | pencil-square |
| Internal Note - Manager | Add internal notes to threads | amber | document-plus |
| Discipline Report - Publisher | Publish and finalize discipline reports | red | clipboard-document-check |
| Applicant Review - Department | Review staff applications for own department | teal | user-group |
| Applicant Review - All | Review staff applications for every department | teal | users |
| Officer Docs - Viewer | Access officer-level documentation | emerald | book-open |

**Unchanged roles:** Moderator, Brig Warden (standalone, no feature group)

### Gates (from `AuthServiceProvider`)

All gates now use `hasRole()` instead of `isAtLeastRank()`. Key gates affected by this feature:

| Gate Name | Who Can Pass | Logic Summary |
|-----------|-------------|---------------|
| `view-ready-room` | Staff Access role | `hasRole('Staff Access')` |
| `view-acp` | Staff Access role | `hasRole('Staff Access')` |
| `edit-staff-bio` | Staff Access or board member | `hasRole('Staff Access') \|\| is_board_member` |
| `view-user-discipline-reports` | Staff Access, self, or parent | `hasRole('Staff Access') \|\| self \|\| parent` |
| `manage-discipline-reports` | Discipline Report - Manager | `hasRole('Discipline Report - Manager')` |
| `publish-discipline-reports` | Discipline Report - Publisher | `hasRole('Discipline Report - Publisher')` |
| `view-docs-staff` | Staff Access (not in brig) | `!in_brig && hasRole('Staff Access')` |
| `view-docs-officer` | Officer Docs - Viewer (not in brig) | `!in_brig && hasRole('Officer Docs - Viewer')` |
| `review-staff-applications` | Applicant Review - All (any), Applicant Review - Department (own dept) | Department scoping via `staffPosition->department` |

### Policies

#### ThreadPolicy (`app/Policies/ThreadPolicy.php`)

**`before()` hook:** Admin bypasses all except `reply` and `createTopic`.

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `viewDepartment` | Ticket - User | Must also have `staff_department` |
| `viewFlagged` | Moderator or Ticket - Manager | |
| `createAsStaff` | Ticket - User | |
| `createTopic` | Admin, report subject, parent, or Ticket - User | Report must be published |
| `addParticipant` | Ticket - User | Must be able to view thread |
| `internalNotes` | Internal Note - Manager | Must be able to view thread |
| `changeStatus` | Ticket - User | Must be able to view thread |
| `assign` | Ticket - Manager | Must be able to view thread |
| `reroute` | Ticket - Manager | Must be able to view thread |
| `close` | Ticket - User (if can view) or ticket creator | |

#### MessagePolicy (`app/Policies/MessagePolicy.php`)

**`before()` hook:** Admin bypasses all.

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `view` | Anyone (regular), Internal Note - Manager (for internal notes) | Internal notes filtered by role |

#### TaskPolicy (`app/Policies/TaskPolicy.php`)

**`before()` hook:** Admin bypasses all.

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `create` | Task - Manager or Task - Department | |
| `update` | Task - Manager (any dept), Task - Department (own dept only) | Department scoping via `section_key` |

#### MeetingPolicy (`app/Policies/MeetingPolicy.php`)

**`before()` hook:** Admin bypasses all.

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `viewAny` | Staff Access | |
| `view` | Staff Access | |
| `attend` | Staff Access | |
| `viewAnyPrivate` | Staff Access | |
| `create` | Meeting - Manager | |
| `update` | Meeting - Manager | |

#### MeetingNotePolicy (`app/Policies/MeetingNotePolicy.php`)

**`before()` hook:** Admin bypasses all.

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `create` | Meeting - Manager, Meeting - Secretary, or Meeting - Department | |
| `update` | Meeting - Manager (any), Meeting - Secretary (any), Meeting - Department (own dept) | Department scoping via `section_key` |

#### DisciplineReportPolicy (`app/Policies/DisciplineReportPolicy.php`)

**`before()` hook:** Admin bypasses all except `delete` and `publish`.

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `viewAny` | Discipline Report - Manager or Staff Access | |
| `view` | Discipline Report - Manager, Staff Access, subject (if published), parent (if published) | |
| `create` | Discipline Report - Manager | |
| `update` | Discipline Report - Manager, or reporter (own draft) | Must be draft |
| `publish` | Discipline Report - Publisher | Must be draft; reporter cannot publish own if subject is staff |

#### StaffApplicationPolicy (`app/Policies/StaffApplicationPolicy.php`)

**`before()` hook:** Admin bypasses all.

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `viewAny` | Applicant Review - All or Applicant Review - Department | |

#### PagePolicy (`app/Policies/PagePolicy.php`)

**`before()` hook:** Admin bypasses all.

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `viewAny` | Page - Editor | |
| `view` | Anyone (if published) or Page - Editor | |
| `create` | Page - Editor | |
| `update` | Page - Editor | |
| `delete` | Page - Editor | |

#### StaffPositionPolicy (`app/Policies/StaffPositionPolicy.php`)

**`before()` hook:** Admin bypasses all.

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `viewAny` | Site Config - Manager | |
| `manageRoles` | Site Config - Manager | Cannot manage own position (anti-escalation) |

### Permissions Matrix

| User Type | View Rank Roles (Profile) | Manage Rank Roles | View Position Roles | Manage Position Roles | Ticket Operations | Task Create/Edit | Meeting Notes Edit |
|-----------|--------------------------|-------------------|--------------------|-----------------------|-------------------|-----------------|-------------------|
| Regular User | No | No | No | No | Own tickets only | No | No |
| JrCrew (Staff Access) | Yes | No | Yes | No | If has Ticket - User | If has Task role | If has Meeting role |
| CrewMember (Staff Access) | Yes | No | Yes | No | If has Ticket - User | If has Task role | If has Meeting role |
| Officer (Staff Access) | Yes | No | Yes | No | If has Ticket - Manager | If has Task - Manager | If has Meeting role |
| Site Config - Manager | Yes | No | Yes | Yes | Per role | Per role | Per role |
| Admin | Yes | Yes | Yes | Yes | All | All | All |

---

## 6. Routes

The rank role management is handled entirely within the existing Staff Position management page. No new routes were added.

| Method | URL | Middleware | Handler | Route Name |
|--------|-----|-----------|---------|------------|
| GET | /acp/staff-positions | auth, can:viewAny,StaffPosition | Volt: `admin-manage-staff-positions-page` | N/A |
| GET | /profile/{user} | auth | Volt: `users.display-basic-details` (nested) | profile.show |

---

## 7. User Interface Components

### Grouped Role Picker
**File:** `resources/views/livewire/partials/grouped-role-picker.blade.php`

**Purpose:** Reusable sub-component for selecting roles, grouped by feature prefix with collapsible sections. Used for both rank and position role management.

**Props:**
- `assignedRoleIds` (array) — IDs of currently assigned roles
- `readOnly` (bool) — Whether the picker allows toggling

**User Actions:**
- Click a role badge to toggle it on/off (dispatches `role-added` or `role-removed` events to parent)

**UI Elements:**
- Collapsible sections per feature group (Ticket, Task, Meeting, etc.)
- Role count indicator per group (e.g., "2/3")
- Color-coded badges: assigned roles show their color, unassigned show zinc with opacity
- Check-circle icon on assigned roles in edit mode

### Rank Cards (on Staff Position Management Page)
**File:** `resources/views/livewire/admin-manage-staff-positions-page.blade.php`

**Purpose:** Three cards at the top of the page showing roles assigned to each rank (Jr Crew, Crew Member, Officer).

**Authorization:** Cards visible to anyone with `viewAny` on StaffPosition. Edit button only for admins (`auth()->user()->isAdmin()`).

**User Actions:**
- Admin clicks "Edit" on a rank card → opens grouped role picker modal
- Toggling roles in the picker dispatches events → `onRoleAdded`/`onRoleRemoved` methods insert/delete from `role_staff_rank`
- Non-admin staff see cards in read-only mode (no Edit button)

### Profile Rank Roles Section
**File:** `resources/views/livewire/users/display-basic-details.blade.php`

**Purpose:** Displays rank-level roles in a distinct section within the staff details card, separate from position roles.

**Authorization:** Visible when viewer `hasRole('Staff Access')`.

**UI Elements:**
- Section labeled with rank name (e.g., "Crew Member Roles", "Officer Roles")
- Role badges with Feature - Tier names, colors, and icons
- Section hidden when user has no rank or rank has no assigned roles

---

## 8. Actions (Business Logic)

No dedicated Action classes were created for this feature. Rank role management is handled directly in the Volt component via DB queries on `role_staff_rank`. Position role management was already inline in the component.

The `CreateDisciplineReport` action (`app/Actions/CreateDisciplineReport.php`) was updated to use `hasRole('Discipline Report - Publisher')` instead of `isAtLeastRank(Officer)` for the QM notification check.

---

## 9. Notifications

Not applicable for this feature. No new notifications were added. The `CreateDisciplineReport` action's notification routing logic was updated (Publisher role check) but the notification itself is unchanged.

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

Not applicable for this feature. No activity logging was added for rank role assignment changes. (Position role changes were also not logged in the existing implementation.)

---

## 14. Data Flow Diagrams

### Assigning a Role to a Rank

```
Admin clicks "Edit" on rank card (e.g., Crew Member)
  -> openRankRolesModal(2)
    -> Sets activeRankValue = 2, clears rolePositionId
    -> Opens manage-rank-roles-modal
  -> Grouped picker renders with current rank role IDs
  -> Admin clicks unassigned role badge
    -> grouped-role-picker::toggleRole(roleId)
      -> Dispatches 'role-added' event
        -> admin-manage-staff-positions-page::onRoleAdded(roleId)
          -> Checks activeRankValue is set
          -> abort_unless(admin)
          -> DB::table('role_staff_rank')->insertOrIgnore(...)
          -> Clears computed caches (rankRoles, activeRankRoleIds)
          -> Flux::toast('Role assigned to rank.')
```

### Removing a Role from a Rank

```
Admin clicks assigned role badge in rank roles modal
  -> grouped-role-picker::toggleRole(roleId)
    -> Dispatches 'role-removed' event
      -> admin-manage-staff-positions-page::onRoleRemoved(roleId)
        -> Checks activeRankValue is set
        -> abort_unless(admin)
        -> DB::table('role_staff_rank')->where(...)->delete()
        -> Clears computed caches
        -> Flux::toast('Role removed from rank.')
```

### User::hasRole() Check

```
Code calls $user->hasRole('Ticket - User')
  -> (1) Admin? Return true
  -> (2) Position exists with has_all_roles_at? Return true
  -> (3) Position has role in role_staff_position? Return true
  -> (4) User has staff_rank != None?
    -> Query role_staff_rank JOIN roles WHERE name='Ticket - User' AND staff_rank=user->staff_rank->value
    -> Return exists()
  -> (5) Return false
```

### Viewing Rank Roles on Profile

```
User visits /profile/{targetUser}
  -> display-basic-details component renders
  -> Auth::user()->hasRole('Staff Access') check
    -> If true:
      -> Loads position roles via $user->staffPosition->load('roles')
      -> Queries role_staff_rank for user's rank to get rank roles
      -> Renders "Position Roles" section
      -> Renders "[Rank Name] Roles" section (if non-empty)
    -> If false: both sections hidden
```

---

## 15. Configuration

Not applicable for this feature. No new env variables or config values were added.

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/Policies/TicketRolePolicyTest.php` | 24 | Ticket - User and Ticket - Manager role checks across all ThreadPolicy abilities, plus MessagePolicy internal note viewing |
| `tests/Feature/Policies/TaskRolePolicyTest.php` | 9 | Task - Department and Task - Manager role checks with department scoping |
| `tests/Feature/Policies/MeetingRolePolicyTest.php` | 15 | Staff Access for meeting view, Meeting - Manager/Secretary/Department for notes |
| `tests/Feature/Policies/InternalNoteRolePolicyTest.php` | 5 | Internal Note - Manager for adding and viewing internal notes |
| `tests/Feature/Policies/DisciplineReportRolePolicyTest.php` | 11 | Discipline Report - Manager, Publisher, and Staff Access |
| `tests/Feature/Policies/ApplicantReviewRolePolicyTest.php` | 10 | Applicant Review - All and Department with department scoping |
| `tests/Feature/Policies/PageRolePolicyTest.php` | 12 | Page - Editor role for all PagePolicy abilities |
| `tests/Feature/Livewire/RankRoleManagementTest.php` | 6 | Admin assign/remove rank roles, non-admin blocked, rank cards display |
| `tests/Feature/Livewire/ProfileRankRolesTest.php` | 5 | Rank roles visible on profile for staff, hidden for non-staff, admin, rank label |

### Test Case Inventory

**TicketRolePolicyTest.php (24 tests):**
- grants viewDepartment to user with Ticket - User role and a department
- denies viewDepartment without Ticket - User role
- grants viewDepartment to admin
- grants createAsStaff to user with Ticket - User role
- denies createAsStaff without Ticket - User role
- grants internalNotes to Internal Note - Manager who can view the thread
- denies internalNotes without Internal Note - Manager role
- grants changeStatus to Ticket - User who can view the thread
- denies changeStatus without Ticket - User role
- grants assign to Ticket - Manager who can view the thread
- denies assign with only Ticket - User role
- grants assign to admin
- grants reroute to Ticket - Manager who can view the thread
- denies reroute with only Ticket - User role
- grants close to Ticket - User who can view the thread
- grants close to ticket creator without Ticket - User role
- denies close to non-creator without Ticket - User role
- grants viewFlagged to Ticket - Manager
- grants viewFlagged to Moderator
- denies viewFlagged without Ticket - Manager or Moderator role
- grants addParticipant to Ticket - User who can view the thread
- denies addParticipant without Ticket - User role
- allows Internal Note - Manager to view internal note messages
- denies non-Ticket-User from viewing internal note messages

**TaskRolePolicyTest.php (9 tests):**
- grants task create to user with Task - Manager role
- grants task create to user with Task - Department role
- denies task create without Task role
- grants task create to admin
- grants task update to Task - Manager for any department
- grants task update to Task - Department for own department
- denies task update to Task - Department for other department
- denies task update without Task role
- grants task update to admin

**MeetingRolePolicyTest.php (15 tests):**
- grants meeting viewAny to user with Staff Access role
- denies meeting viewAny without Staff Access role
- grants meeting viewAny to admin
- grants meeting create to Meeting - Manager
- denies meeting create without Meeting - Manager role
- grants meeting note create to Meeting - Manager
- grants meeting note create to Meeting - Secretary
- grants meeting note create to Meeting - Department
- denies meeting note create without meeting role
- grants meeting note update to Meeting - Manager for any department
- grants meeting note update to Meeting - Secretary for any department
- grants meeting note update to Meeting - Department for own department
- denies meeting note update to Meeting - Department for other department
- denies meeting note update without meeting role
- grants meeting note update to admin

**InternalNoteRolePolicyTest.php (5 tests):**
- grants internalNotes to Internal Note - Manager who can view the thread
- denies internalNotes without Internal Note - Manager role
- grants internalNotes to admin
- allows Internal Note - Manager to view internal note messages
- denies non-Internal-Note-Manager from viewing internal note messages

**DisciplineReportRolePolicyTest.php (11 tests):**
- grants discipline report viewAny to Discipline Report - Manager
- grants discipline report viewAny to Staff Access role
- denies discipline report viewAny without appropriate role
- grants discipline report viewAny to admin
- grants discipline report create to Discipline Report - Manager
- denies discipline report create without Discipline Report - Manager role
- grants discipline report update to Discipline Report - Manager for draft
- grants discipline report update to reporter for own draft
- denies discipline report update to non-reporter without Manager role
- grants discipline report publish to Discipline Report - Publisher for draft
- denies discipline report publish without Publisher role

**ApplicantReviewRolePolicyTest.php (10 tests):**
- grants review-staff-applications to Applicant Review - All
- grants review-staff-applications list to Applicant Review - Department
- grants review-staff-applications for same-department application
- denies review-staff-applications for different-department application
- grants review-staff-applications for any department to Applicant Review - All
- denies review-staff-applications without applicant review role
- grants review-staff-applications to admin
- grants staff application viewAny to Applicant Review - All
- grants staff application viewAny to Applicant Review - Department
- denies staff application viewAny without applicant review role

**PageRolePolicyTest.php (12 tests):**
- grants page viewAny to Page - Editor
- denies page viewAny without Page - Editor role
- grants page viewAny to admin
- grants page create to Page - Editor
- denies page create without Page - Editor role
- grants page update to Page - Editor
- denies page update without Page - Editor role
- grants page delete to Page - Editor
- denies page delete without Page - Editor role
- anyone can view published pages
- Page - Editor can view unpublished pages
- non-editor cannot view unpublished pages

**RankRoleManagementTest.php (6 tests):**
- allows admin to assign a role to a rank
- allows admin to remove a role from a rank
- prevents non-admin from managing rank roles
- displays rank cards on staff positions page
- displays assigned roles on rank cards
- non-admin staff can view rank cards in read-only mode

**ProfileRankRolesTest.php (5 tests):**
- shows rank roles section on profile for staff viewer
- hides rank roles section when rank has no assigned roles
- hides rank roles from non-staff viewers
- shows rank roles to admin viewer
- labels rank roles section with correct rank name

### Coverage Gaps

- No test for the grouped-role-picker component in isolation (it's tested indirectly through the rank management tests)
- No test for position role assignment using the new grouped picker (old tests were updated but test the `onRoleAdded`/`onRoleRemoved` methods directly)
- No test for the `Role::group` accessor
- Library visibility checks (`guides-index`, `books-index`) updated to use `hasRole('Staff Access')` / `hasRole('Officer Docs - Viewer')` but no tests verify these views
- No activity logging for rank role changes (neither assignment nor removal is logged)

---

## 17. File Map

**Models:**
- `app/Models/Role.php`
- `app/Models/User.php` (hasRole method)
- `app/Models/StaffPosition.php` (roles relationship)

**Enums:**
- `app/Enums/StaffRank.php`

**Actions:**
- `app/Actions/CreateDisciplineReport.php` (notification check updated)

**Policies:**
- `app/Policies/ThreadPolicy.php`
- `app/Policies/MessagePolicy.php`
- `app/Policies/TaskPolicy.php`
- `app/Policies/MeetingPolicy.php`
- `app/Policies/MeetingNotePolicy.php`
- `app/Policies/DisciplineReportPolicy.php`
- `app/Policies/StaffApplicationPolicy.php`
- `app/Policies/PagePolicy.php`
- `app/Policies/StaffPositionPolicy.php`

**Gates:** `app/Providers/AuthServiceProvider.php` — gates: `view-ready-room`, `view-acp`, `edit-staff-bio`, `view-user-discipline-reports`, `manage-discipline-reports`, `publish-discipline-reports`, `view-docs-staff`, `view-docs-officer`, `review-staff-applications`

**Notifications:** None new

**Jobs:** None

**Services:** None

**Controllers:** None

**Volt Components:**
- `resources/views/livewire/partials/grouped-role-picker.blade.php` (NEW)
- `resources/views/livewire/admin-manage-staff-positions-page.blade.php` (MODIFIED)
- `resources/views/livewire/users/display-basic-details.blade.php` (MODIFIED)
- `resources/views/livewire/ready-room/tickets/view-ticket.blade.php` (MODIFIED)
- `resources/views/livewire/reports/view-report.blade.php` (MODIFIED)
- `resources/views/livewire/users/discipline-reports-card.blade.php` (MODIFIED)
- `resources/views/livewire/library/guides-index.blade.php` (MODIFIED)
- `resources/views/livewire/library/books-index.blade.php` (MODIFIED)

**Routes:** No new routes

**Migrations:**
- `database/migrations/2026_03_21_000001_rename_roles_to_feature_tier_convention.php`
- `database/migrations/2026_03_21_000002_create_role_staff_rank_table.php`
- `database/migrations/2026_03_21_000003_seed_new_feature_scoped_roles.php`

**Console Commands:** None

**Tests:**
- `tests/Feature/Policies/TicketRolePolicyTest.php` (NEW)
- `tests/Feature/Policies/TaskRolePolicyTest.php` (NEW)
- `tests/Feature/Policies/MeetingRolePolicyTest.php` (NEW)
- `tests/Feature/Policies/InternalNoteRolePolicyTest.php` (NEW)
- `tests/Feature/Policies/DisciplineReportRolePolicyTest.php` (NEW)
- `tests/Feature/Policies/ApplicantReviewRolePolicyTest.php` (NEW)
- `tests/Feature/Policies/PageRolePolicyTest.php` (NEW)
- `tests/Feature/Livewire/RankRoleManagementTest.php` (NEW)
- `tests/Feature/Livewire/ProfileRankRolesTest.php` (NEW)
- `tests/Feature/Livewire/RoleAssignmentUITest.php` (MODIFIED)
- `tests/Feature/Policies/ThreadPolicyTest.php` (MODIFIED)
- `tests/Feature/Tickets/ThreadAuthorizationTest.php` (MODIFIED)
- `tests/Feature/Tickets/TicketsListTest.php` (MODIFIED)
- `tests/Feature/Tickets/CreateTicketTest.php` (MODIFIED)
- `tests/Feature/Tickets/ViewTicketTest.php` (MODIFIED)
- `tests/Feature/Tickets/TicketActivityLoggingTest.php` (MODIFIED)
- `tests/Feature/Tickets/MessageFlaggingTest.php` (MODIFIED)
- `tests/Feature/Topics/TopicPolicyTest.php` (MODIFIED)
- `tests/Feature/Topics/ViewTopicTest.php` (MODIFIED)
- `tests/Feature/Topics/DiscussionFlaggingTest.php` (MODIFIED)
- `tests/Feature/Meeting/MeetingCreateTest.php` (MODIFIED)
- `tests/Feature/Meeting/TaskTest.php` (MODIFIED)
- `tests/Feature/Gates/RoleBasedGatesTest.php` (MODIFIED)
- `tests/Feature/Livewire/DisciplineReportsCardTest.php` (MODIFIED)
- `tests/Feature/Livewire/StaffApplicationReviewTest.php` (MODIFIED)
- `tests/Feature/Policies/DisciplineReportPolicyTest.php` (MODIFIED)
- `tests/Feature/Policies/StaffApplicationPolicyTest.php` (MODIFIED)
- `tests/Feature/Actions/DisciplineReports/CreateDisciplineReportTest.php` (MODIFIED)
- `tests/Unit/Models/UserTest.php` (MODIFIED)

**Config:** None

**Other:**
- `database/factories/UserFactory.php` — `withRole()` factory method (pre-existing)
- `tests/Support/Users.php` — staff user helper functions (pre-existing, all include `Staff Access` role)

---

## 18. Known Issues & Improvement Opportunities

1. **No activity logging for rank role changes.** When an admin adds or removes a role from a rank, no activity log entry is created. This means rank role changes are invisible in the audit trail. Consider adding `RecordActivity::run()` calls to `onRoleAdded`/`onRoleRemoved` when `activeRankValue` is set.

2. **Inline DB queries in Volt component.** Rank role management uses raw `DB::table('role_staff_rank')` queries directly in the Volt component rather than an Action class. This is inconsistent with the project convention of putting business logic in Actions. Consider extracting `AssignRoleToRank` and `RemoveRoleFromRank` Action classes.

3. **Inline DB query in profile Blade template.** The rank roles section in `display-basic-details.blade.php` queries `role_staff_rank` directly in the Blade `@php` block. This should ideally be a computed property or method on the User model.

4. **No `staffRanks()` relationship on Role model.** The Role model has `staffPositions()` but no corresponding `staffRanks()` belongsToMany. This would be useful for querying which ranks have a given role.

5. **`isAtLeastRank()` still exists in non-gate code.** While all gates and policies have been migrated, `isAtLeastRank()` is still used in: `BlogPostPolicy`, `UserPolicy`, `parent-portal`, and JrCrew display checks. Some of these (BlogPostPolicy, UserPolicy) may warrant migration in a future issue.

6. **Grouped role picker queries all roles on every render.** The `groupedRoles` computed property loads all roles from DB. For ~30 roles this is fine, but if roles grow significantly, consider caching.

7. **No self-escalation prevention for rank roles.** Position role management has anti-escalation (cannot manage own position). Rank role management only checks `isAdmin()`, but doesn't prevent an admin from assigning roles to their own rank. Since only admins can manage rank roles (and admins already have all roles), this is low risk but worth noting.

8. **Event-based role management conflates two contexts.** The `onRoleAdded`/`onRoleRemoved` handlers use `activeRankValue` vs `rolePositionId` to determine context. If both are somehow set, rank takes precedence. This should not happen in practice since each modal clears the other's state, but the fallback behavior is implicit.
