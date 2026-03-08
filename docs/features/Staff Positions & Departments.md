# Staff Positions & Departments -- Technical Documentation

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

Staff Positions & Departments is the organizational backbone of the Lighthouse community's staff structure. It defines a hierarchical system of departments (Command, Chaplain, Engineer, Quartermaster, Steward), staff ranks (None, Junior Crew Member, Crew Member, Officer), and named positions (e.g., "Head Engineer") that users can be assigned to. Each position belongs to a department and has a rank level, and each user can hold at most one position at a time.

The feature is managed by Admins and Command Department Officers, who can create, edit, delete, and assign staff positions through the Admin Control Panel. When a user is assigned to a position, their `staff_rank`, `staff_department`, and `staff_title` fields on the User model are updated, and their Minecraft staff group and Discord staff roles are synchronized automatically.

Staff members (Crew Member rank and above, plus Board Members) can edit their own staff bio — a public profile including first name, last initial, bio text, phone number, and photo — visible on the public Staff Page. The Staff Page (`/staff`) displays all positions grouped by department, with a detail panel showing the selected staff member's bio, rank, and contact information (phone visible only to Officers and Board Members).

The staff rank and department system underpins authorization across the entire application. Many gates (e.g., `view-ready-room`, `manage-stowaway-users`, `view-acp`, `view-command-dashboard`) reference `StaffRank` and `StaffDepartment` to control access to features. This document focuses on the staff position management system itself, not every gate that references ranks.

---

## 2. Database Schema

### `staff_positions` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint (PK) | No | auto | Primary key |
| `title` | string | No | — | Position title (e.g., "Head Engineer") |
| `department` | string | No | — | Cast to `StaffDepartment` enum |
| `rank` | unsignedTinyInteger | No | — | Cast to `StaffRank` enum |
| `description` | text | Yes | NULL | Short description of the position |
| `responsibilities` | text | Yes | NULL | What this position is responsible for |
| `requirements` | text | Yes | NULL | Special requirements (e.g., minimum age) |
| `user_id` | foreignId | Yes | NULL | FK to `users.id`, nullOnDelete |
| `sort_order` | unsignedInteger | No | 0 | Display ordering within department |
| `created_at` | timestamp | Yes | — | |
| `updated_at` | timestamp | Yes | — | |

**Indexes:** Unique on `user_id` (a user can hold only one position)
**Foreign Keys:** `user_id` → `users.id` (null on delete)
**Migration:** `database/migrations/2026_03_03_000001_create_staff_positions_table.php`

### `users` table (staff-related columns)

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `staff_rank` | unsignedTinyInteger | No | 0 (None) | Cast to `StaffRank` enum |
| `staff_department` | string | Yes | NULL | Cast to `StaffDepartment` enum |
| `staff_title` | string | Yes | NULL | Position title copied from StaffPosition |
| `staff_first_name` | string | Yes | NULL | Real first name for public display |
| `staff_last_initial` | string(1) | Yes | NULL | Last initial for public display |
| `staff_bio` | text | Yes | NULL | Staff member's bio text |
| `staff_photo_path` | string | Yes | NULL | Path to uploaded staff photo |
| `staff_phone` | string(30) | Yes | NULL | Phone number (visible to Officers/Board only) |

**Migration(s):**
- `database/migrations/2025_08_05_130249_update_users_add_membership_and_officer_roles.php` (staff_rank, staff_department, staff_title)
- `database/migrations/2026_03_03_000002_add_staff_bio_fields_to_users_table.php` (staff_first_name, staff_last_initial, staff_bio, staff_photo_path)
- `database/migrations/2026_03_04_170011_add_staff_phone_to_users_table.php` (staff_phone)

---

## 3. Models & Relationships

### StaffPosition (`app/Models/StaffPosition.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `user()` | belongsTo | User | The staff member assigned to this position |

**Scopes:**
- `scopeVacant($query)` — Positions with no assigned user (`whereNull('user_id')`)
- `scopeFilled($query)` — Positions with an assigned user (`whereNotNull('user_id')`)
- `scopeInDepartment($query, StaffDepartment $department)` — Filter by department
- `scopeOrdered($query)` — Orders by `sort_order` ASC, `rank` DESC, `title` ASC

**Key Methods:**
- `isVacant(): bool` — Returns true if `user_id` is null
- `isFilled(): bool` — Returns true if `user_id` is not null

**Casts:**
- `department` => `StaffDepartment::class`
- `rank` => `StaffRank::class`

**Fillable:** `title`, `department`, `rank`, `description`, `responsibilities`, `requirements`, `user_id`, `sort_order`

### User (`app/Models/User.php`) — Staff aspects

**Key Methods:**
- `isAtLeastRank(StaffRank $rank): bool` — True if user's `staff_rank` value >= given rank value
- `isRank(StaffRank $rank): bool` — True if user's `staff_rank` == given rank
- `isInDepartment(StaffDepartment $department): bool` — True if user's `staff_department` === given department
- `isJrCrew(): bool` — True if `staff_rank === StaffRank::JrCrew`
- `staffPhotoUrl(): ?string` — Returns asset URL for `staff_photo_path` or null

**Casts:**
- `staff_rank` => `StaffRank::class`
- `staff_department` => `StaffDepartment::class`

---

## 4. Enums Reference

### StaffDepartment (`app/Enums/StaffDepartment.php`)

| Case | Value | Label | Discord Role Config Key |
|------|-------|-------|------------------------|
| `Command` | `command` | Command | `lighthouse.discord.roles.staff_command` |
| `Chaplain` | `chaplain` | Chaplain | `lighthouse.discord.roles.staff_chaplain` |
| `Engineer` | `engineer` | Engineer | `lighthouse.discord.roles.staff_engineer` |
| `Quartermaster` | `quartermaster` | Quartermaster | `lighthouse.discord.roles.staff_quartermaster` |
| `Steward` | `steward` | Steward | `lighthouse.discord.roles.staff_steward` |

**Helper methods:**
- `label(): string` — Human-readable department name
- `discordRoleId(): ?string` — Returns the Discord role ID from config

### StaffRank (`app/Enums/StaffRank.php`)

| Case | Value | Label | Color | Discord Role Config Key |
|------|-------|-------|-------|------------------------|
| `None` | 0 | None | zinc | null |
| `JrCrew` | 1 | Junior Crew Member | amber | `lighthouse.discord.roles.rank_jr_crew` |
| `CrewMember` | 2 | Crew Member | fuchsia | `lighthouse.discord.roles.rank_crew_member` |
| `Officer` | 3 | Officer | emerald | `lighthouse.discord.roles.rank_officer` |

**Helper methods:**
- `label(): string` — Human-readable rank name
- `color(): string` — Flux UI badge color
- `discordRoleId(): ?string` — Returns the Discord role ID from config

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

Gates that directly involve staff rank/department for access control:

| Gate Name | Who Can Pass | Logic Summary |
|-----------|-------------|---------------|
| `manage-stowaway-users` | Admin, Officer+, QM Crew+ | `isAdmin() \|\| isAtLeastRank(Officer) \|\| (CrewMember+ && Quartermaster)` |
| `manage-traveler-users` | Admin, Officer+, QM Crew+ | Same as above |
| `view-ready-room` | Admin, JrCrew+ | `isAdmin() \|\| isAtLeastRank(JrCrew)` |
| `view-ready-room-command` | Admin, Officer+, Command dept JrCrew+ | `isAdmin() \|\| isAtLeastRank(Officer) \|\| (JrCrew+ && Command)` |
| `view-ready-room-chaplain` | Admin, Officer+, Chaplain dept JrCrew+ | Same pattern for Chaplain |
| `view-ready-room-engineer` | Admin, Officer+, Engineer dept JrCrew+ | Same pattern for Engineer |
| `view-ready-room-quartermaster` | Admin, Officer+, QM dept JrCrew+ | Same pattern for Quartermaster |
| `view-ready-room-steward` | Admin, Officer+, Steward dept JrCrew+ | Same pattern for Steward |
| `view-acp` | Admin, CrewMember+, Page Editor, Engineer dept | `isAdmin() \|\| isAtLeastRank(CrewMember) \|\| hasRole('Page Editor') \|\| isInDepartment(Engineer)` |
| `view-mc-command-log` | Admin, Officer+, Engineer dept | `isAdmin() \|\| isAtLeastRank(Officer) \|\| isInDepartment(Engineer)` |
| `view-discord-api-log` | Same as above | Same |
| `view-activity-log` | Same as above | Same |
| `view-discipline-report-log` | Same as above | Same |
| `edit-staff-bio` | CrewMember+, Board members | `isAtLeastRank(CrewMember) \|\| is_board_member` |
| `manage-discipline-reports` | Admin, JrCrew+ | `isAdmin() \|\| isAtLeastRank(JrCrew)` |
| `publish-discipline-reports` | Admin, Officer+ | `isAdmin() \|\| isAtLeastRank(Officer)` |
| `view-command-dashboard` | Admin, Command dept | `isAdmin() \|\| isInDepartment(Command)` |

### Policies

#### StaffPositionPolicy (`app/Policies/StaffPositionPolicy.php`)

**`before()` hook:** Admin OR (Command department + Officer rank) → bypasses all checks (returns `true`)

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `viewAny` | Admin, Command Officer only | Denied for all others (returns `false`) |
| `create` | Admin, Command Officer only | Denied for all others |
| `update` | Admin, Command Officer only | Denied for all others |
| `delete` | Admin, Command Officer only | Denied for all others |
| `assign` | Admin, Command Officer only | Denied for all others |

#### UserPolicy (`app/Policies/UserPolicy.php`) — Staff-related method

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `viewStaffPhone` | Officer+ or Board member viewing a staff/board member | Actor must be `isAtLeastRank(Officer) \|\| is_board_member`; target must be `isAtLeastRank(JrCrew) \|\| is_board_member` |

### Permissions Matrix

| User Type | View Staff Page | Edit Own Bio | Manage Positions (CRUD) | Assign/Unassign | View Staff Phone |
|-----------|:--------------:|:------------:|:----------------------:|:--------------:|:---------------:|
| Regular User | Yes (public) | No | No | No | No |
| Jr Crew Member | Yes | No | No | No | No |
| Crew Member | Yes | Yes | No | No | No |
| Officer | Yes | Yes | No | No | Yes |
| Command Officer | Yes | Yes | Yes | Yes | Yes |
| Admin | Yes | Yes* | Yes | Yes | Yes* |
| Board Member | Yes | Yes | No | No | Yes |

*Admin has `edit-staff-bio` gate access if they are also at least CrewMember rank or a board member.

---

## 6. Routes

| Method | URL | Middleware | Handler | Route Name |
|--------|-----|-----------|---------|------------|
| GET | `/staff` | (none — public) | `Volt::route` → `staff.page` | `staff.index` |
| GET | `/settings/staff-bio` | auth, verified | `Volt::route` → `settings.staff-bio` | `settings.staff-bio` |

The staff position management UI is embedded within the Admin Control Panel tabs component (`resources/views/livewire/admin-control-panel-tabs.blade.php`), accessible via the ACP route (gated by `view-acp`).

---

## 7. User Interface Components

### Staff Page (Public)
**File:** `resources/views/livewire/staff/page.blade.php`
**Route:** `/staff` (route name: `staff.index`)

**Purpose:** Public-facing page displaying all staff members organized by department, with a detail panel for viewing individual staff member information. Also displays Board of Directors section.

**Authorization:** None — publicly accessible (no auth required)

**User Actions Available:**
- Click a staff member card → `selectPosition($id)` → displays their details in the side panel
- Click a board member card → `selectBoardMember($id)` → displays board member details

**UI Elements:**
- Departments grouped with Officers and Crew sections
- Staff member cards showing photo, name, and title
- Vacant positions displayed as "Open Position"
- Detail panel (sticky sidebar) showing: photo, real name, username (linked to profile), rank badge, department badge, description, bio, responsibilities, phone (gated by `viewStaffPhone` policy), and requirements (for vacant positions)
- Board of Directors section at the bottom

**Computed Properties:**
- `departments` — Groups all positions by `StaffDepartment`, splits into officers and crew per department
- `selectedPosition` — The currently selected `StaffPosition` with eager-loaded user
- `boardMembers` — All board members (ordered)
- `selectedBoardMember` — Currently selected board member

### Manage Staff Positions (ACP Tab)
**File:** `resources/views/livewire/admin-manage-staff-positions-page.blade.php`
**Route:** Embedded in ACP tabs (no dedicated route)

**Purpose:** Admin interface for CRUD operations on staff positions

**Authorization:** `StaffPositionPolicy::viewAny` on mount (only Admin or Command Officers)

**User Actions Available:**
- **Create Position** → Opens `create-position-modal` → validates → `StaffPosition::create()` → toast
- **Edit Position** → Opens `edit-position-modal` via `openEditModal($id)` → validates → `$position->update()` → toast
- **Delete Position** → `deletePosition($id)` with confirmation → only if vacant → `$position->delete()` → toast

**UI Elements:**
- Table with columns: Sort, Title, Department, Rank, Assigned To, Actions
- Assigned users linked to their profile page
- Vacant positions shown with "Vacant" badge
- Edit button (always), Delete button (only for vacant positions)
- Create Position button → flyout modal with form fields: Title, Department (select), Rank (CrewMember or Officer only), Description, Responsibilities, Requirements, Sort Order
- Edit modal with same fields

**Validation Rules (create/edit):**
- `title`: required, string, max:255
- `department`: required, must be valid `StaffDepartment` value
- `rank`: required, must be `CrewMember` (2) or `Officer` (3)
- `description`: nullable, string, max:2000
- `responsibilities`: nullable, string, max:2000
- `requirements`: nullable, string, max:2000
- `sortOrder`: required, integer, min:0

### Staff Bio Settings
**File:** `resources/views/livewire/settings/staff-bio.blade.php`
**Route:** `/settings/staff-bio` (route name: `settings.staff-bio`)

**Purpose:** Allows staff members to edit their own public bio information

**Authorization:** `edit-staff-bio` gate (CrewMember+ or Board member)

**User Actions Available:**
- **Save Bio** → validates → updates user's `staff_first_name`, `staff_last_initial`, `staff_bio`, `staff_phone`, `staff_photo_path` → toast
- **Upload Photo** → stores to `staff-photos` directory on `public` disk, deletes old photo
- **Remove Photo** → deletes from storage, clears `staff_photo_path`

**Validation Rules:**
- `firstName`: nullable, string, max:50
- `lastInitial`: nullable, string, max:1, alpha
- `bio`: nullable, string, max:2000
- `phone`: nullable, string, min:10, max:30, regex for phone characters only
- `photo`: nullable, image, max:2048 (2MB)

### Profile Page (Staff Section)
**File:** `resources/views/livewire/users/display-basic-details.blade.php`

Staff-related content on user profiles includes:
- Staff rank badge with color
- Staff photo (hidden for JrCrew)
- Real name display (first name + last initial, hidden for JrCrew)
- Staff bio (hidden for JrCrew)
- Staff phone (gated by `viewStaffPhone` policy)
- Edit bio link for the user viewing their own profile (if CrewMember+)

---

## 8. Actions (Business Logic)

### AssignStaffPosition (`app/Actions/AssignStaffPosition.php`)

**Signature:** `handle(StaffPosition $position, User $user): void`

**Step-by-step logic:**
1. In a DB transaction:
   - If user already holds a different position → calls `UnassignStaffPosition::run()` on old position
   - If this position already has a different user → calls `UnassignStaffPosition::run()` on this position
   - Updates `$position->user_id` to the user's ID
   - Computes effective rank: if position rank is `CrewMember` and user age < 17 → downgrades to `JrCrew`
   - Calls `SetUsersStaffPosition::run($user, $title, $department, $effectiveRank)`
2. Records activity: `RecordActivity::run($position, 'staff_position_assigned', "Assigned {name} to position: {title} ({dept}, {rank})")`

**Called by:** ACP staff management interface (not directly found in Volt components — likely called from admin tools or manual invocation)

### UnassignStaffPosition (`app/Actions/UnassignStaffPosition.php`)

**Signature:** `handle(StaffPosition $position): void`

**Step-by-step logic:**
1. Returns early if position has no assigned user
2. In a DB transaction:
   - Sets `$position->user_id` to null
   - Calls `RemoveUsersStaffPosition::run($user)` to clear user's staff fields
3. Records activity: `RecordActivity::run($position, 'staff_position_unassigned', "Unassigned {name} from position: {title}")`

**Called by:** `AssignStaffPosition` (when reassigning), admin tools

### SetUsersStaffPosition (`app/Actions/SetUsersStaffPosition.php`)

**Signature:** `handle(User $user, $title, StaffDepartment $department, StaffRank $rank)`

**Step-by-step logic:**
1. Returns false if title is null or empty
2. Compares current vs. new values for department, rank, and title
3. Only updates fields that changed; returns true early if no changes
4. Saves user model
5. Records activity: `RecordActivity::run($user, 'staff_position_updated', description)` with details of what changed
6. If department changed → calls `SyncMinecraftStaff::run($user, $department)`
7. If department or rank changed → calls `SyncDiscordStaff::run($user, $department)`

**Called by:** `AssignStaffPosition`

### RemoveUsersStaffPosition (`app/Actions/RemoveUsersStaffPosition.php`)

**Signature:** `handle(User $user)`

**Step-by-step logic:**
1. Builds activity description with removed department, rank, and title
2. Sets `staff_department` to null, `staff_rank` to `StaffRank::None`, `staff_title` to null
3. Saves user model
4. Records activity: `RecordActivity::run($user, 'staff_position_removed', description)`
5. Calls `SyncMinecraftStaff::run($user)` (null department → removes MC staff position)
6. Calls `SyncDiscordStaff::run($user)` (null department → removes Discord staff roles)

**Called by:** `UnassignStaffPosition`

### SyncMinecraftStaff (`app/Actions/SyncMinecraftStaff.php`)

**Signature:** `handle(User $user, ?StaffDepartment $department = null): void`

**Step-by-step logic:**
1. Gets all active Minecraft accounts for the user
2. Returns early if no active accounts
3. For each active account:
   - If department is set → dispatches `SendMinecraftCommand` with `lh setstaff {username} {department}`
   - If department is null → dispatches `SendMinecraftCommand` with `lh removestaff {username}`
4. Records activity for each account

**Called by:** `SetUsersStaffPosition`, `RemoveUsersStaffPosition`

### SyncDiscordStaff (`app/Actions/SyncDiscordStaff.php`)

**Signature:** `handle(User $user, ?StaffDepartment $department = null): void`

**Step-by-step logic:**
1. Gets all active Discord accounts for the user
2. Returns early if no active accounts
3. Builds managed role IDs list from all `StaffDepartment` and `StaffRank` Discord role IDs
4. Builds desired role IDs based on user's current department and rank
5. For each Discord account → calls `DiscordApiService::syncManagedRoles()` with managed and desired sets
6. Records activity: `discord_staff_synced` (if department set) or `discord_staff_removed` (if null)

**Called by:** `SetUsersStaffPosition`, `RemoveUsersStaffPosition`

---

## 9. Notifications

Not applicable for this feature. Staff position changes do not trigger notifications.

---

## 10. Background Jobs

Not applicable for this feature directly. However, `SyncMinecraftStaff` dispatches `SendMinecraftCommand` as a job (`::dispatch()`) for RCON command execution.

---

## 11. Console Commands & Scheduled Tasks

Not applicable for this feature.

---

## 12. Services

### DiscordApiService (`app/Services/DiscordApiService.php`)
**Purpose:** Manages Discord API interactions including role management
**Key methods used by this feature:**
- `syncManagedRoles(string $discordUserId, array $managedRoleIds, array $desiredRoleIds)` — Adds desired roles and removes managed roles that aren't desired

### MinecraftRconService (via `SendMinecraftCommand`)
**Purpose:** Executes RCON commands against the Minecraft server
**Commands used:** `lh setstaff {username} {department}`, `lh removestaff {username}`

---

## 13. Activity Log Entries

| Action String | Logged By | Subject Model | Description |
|---------------|-----------|---------------|-------------|
| `staff_position_assigned` | AssignStaffPosition | StaffPosition | "Assigned {name} to position: {title} ({dept}, {rank})" |
| `staff_position_unassigned` | UnassignStaffPosition | StaffPosition | "Unassigned {name} from position: {title}" |
| `staff_position_updated` | SetUsersStaffPosition | User | "Updating staff position: Department: X => Y, Rank: X => Y, Title: X => Y" |
| `staff_position_removed` | RemoveUsersStaffPosition | User | "Removed staff position: Department: X, Rank: X, Title: X" |
| `minecraft_staff_position_set` | SyncMinecraftStaff | User | "Set Minecraft staff position to {dept} for {username}" |
| `minecraft_staff_position_removed` | SyncMinecraftStaff | User | "Removed Minecraft staff position for {username}" |
| `discord_staff_synced` | SyncDiscordStaff | User | "Synced Discord staff roles: {dept}" |
| `discord_staff_removed` | SyncDiscordStaff | User | "Removed Discord staff roles" |

---

## 14. Data Flow Diagrams

### Assigning a User to a Staff Position

```
Admin/Command Officer clicks assign in ACP
  -> AssignStaffPosition::run($position, $user)
    -> DB transaction:
      -> If user has existing position → UnassignStaffPosition::run(old)
        -> old position.user_id = null
        -> RemoveUsersStaffPosition::run(user)
          -> user.staff_department = null, staff_rank = None, staff_title = null
          -> SyncMinecraftStaff::run(user, null) → lh removestaff
          -> SyncDiscordStaff::run(user, null) → remove all staff roles
      -> position.user_id = user.id
      -> Compute effective rank (JrCrew if < 17)
      -> SetUsersStaffPosition::run(user, title, dept, rank)
        -> user.staff_department = dept
        -> user.staff_rank = rank
        -> user.staff_title = title
        -> SyncMinecraftStaff::run(user, dept) → lh setstaff {username} {dept}
        -> SyncDiscordStaff::run(user, dept) → sync department + rank roles
    -> RecordActivity::run(position, 'staff_position_assigned', ...)
```

### Creating a Staff Position

```
Admin/Command Officer clicks "Create Position" in ACP
  -> Opens create-position-modal
  -> Fills in title, department, rank, description, responsibilities, requirements, sort order
  -> Submits form
    -> Volt::createPosition()
      -> $this->authorize('create', StaffPosition::class)
      -> Validates fields
      -> StaffPosition::create([...])
      -> Flux::modal('create-position-modal')->close()
      -> Flux::toast('Staff position created.', variant: 'success')
```

### Editing Staff Bio

```
Staff member navigates to /settings/staff-bio
  -> Volt::mount()
    -> $this->authorize('edit-staff-bio')
    -> Loads existing bio fields from Auth::user()
  -> Fills in first name, last initial, bio, phone, photo
  -> Submits form
    -> Volt::save()
      -> $this->authorize('edit-staff-bio')
      -> Validates all fields
      -> If photo uploaded → stores to staff-photos/ on public disk, deletes old
      -> Updates user fields: staff_first_name, staff_last_initial, staff_bio, staff_phone, staff_photo_path
      -> Flux::toast('Staff bio updated successfully.', variant: 'success')
```

### Viewing the Staff Page

```
User (or public visitor) navigates to /staff
  -> Volt::mount()
    -> Selects default position (first filled Command Officer, or first filled position)
  -> Departments computed property:
    -> StaffPosition::with('user.minecraftAccounts', 'user.discordAccounts')->ordered()->get()
    -> Groups by StaffDepartment, splits into officers and crew
  -> User clicks a staff card → selectPosition($id) → detail panel updates
  -> Detail panel shows: photo, real name, title, rank badge, dept badge, bio, responsibilities
  -> Phone visible only to Officers/Board via @can('viewStaffPhone', $user)
```

---

## 15. Configuration

| Key | Env Variable | Purpose |
|-----|-------------|---------|
| `lighthouse.discord.roles.staff_command` | `DISCORD_ROLE_STAFF_COMMAND` | Discord role ID for Command department |
| `lighthouse.discord.roles.staff_chaplain` | `DISCORD_ROLE_STAFF_CHAPLAIN` | Discord role ID for Chaplain department |
| `lighthouse.discord.roles.staff_engineer` | `DISCORD_ROLE_STAFF_ENGINEER` | Discord role ID for Engineer department |
| `lighthouse.discord.roles.staff_quartermaster` | `DISCORD_ROLE_STAFF_QUARTERMASTER` | Discord role ID for Quartermaster department |
| `lighthouse.discord.roles.staff_steward` | `DISCORD_ROLE_STAFF_STEWARD` | Discord role ID for Steward department |
| `lighthouse.discord.roles.rank_jr_crew` | `DISCORD_ROLE_RANK_JR_CREW` | Discord role ID for Junior Crew rank |
| `lighthouse.discord.roles.rank_crew_member` | `DISCORD_ROLE_RANK_CREW_MEMBER` | Discord role ID for Crew Member rank |
| `lighthouse.discord.roles.rank_officer` | `DISCORD_ROLE_RANK_OFFICER` | Discord role ID for Officer rank |

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/Policies/StaffPositionPolicyTest.php` | 8 | Policy before() hook, viewAny, create, update, assign abilities |
| `tests/Feature/Livewire/ManageStaffPositionsTest.php` | 3 | Component mount, admin assign, admin remove |
| `tests/Feature/Actions/AssignStaffPositionTest.php` | 8 | Assignment, rank computation (age-based JrCrew), reassignment, activity |
| `tests/Feature/Actions/UnassignStaffPositionTest.php` | 5 | Removal, field clearing, bio preservation, vacant noop, activity |
| `tests/Unit/Actions/SetUsersStaffPositionTest.php` | 19 | All field update scenarios, partial changes, activity, all depts/ranks |
| `tests/Unit/Actions/RemoveUsersStaffPositionTest.php` | 14 | Removal, activity, different depts/ranks, persistence, idempotency |
| `tests/Feature/Settings/StaffBioTest.php` | 5 | Access control for bio page (crew, officer, jr crew, regular, unauth) |
| `tests/Feature/Discord/SyncDiscordStaffTest.php` | 7 | Role sync, removal, no accounts, brigged accounts, activity |
| `tests/Feature/StaffPageTest.php` | 9 | Public page load, filled/vacant display, department grouping, board members |

### Test Case Inventory

**StaffPositionPolicyTest.php:**
- `it('allows admin to view any staff positions')`
- `it('allows command officer to view any staff positions')`
- `it('denies regular user from viewing staff positions')`
- `it('denies crew member from managing staff positions')`
- `it('allows admin to create staff positions')`
- `it('allows admin to update staff positions')`
- `it('allows admin to assign staff positions')`
- `it('denies non-command officer from managing staff positions')`

**ManageStaffPositionsTest.php:**
- `it('mounts the component and loads staff position relationship')`
- `it('allows admin to assign a staff position')`
- `it('allows admin to remove a staff position')`

**AssignStaffPositionTest.php:**
- `it('assigns a user to a vacant position')`
- `it('syncs staff rank department and title on the user')`
- `it('sets JrCrew rank when user is under 17 with crew member position')`
- `it('sets CrewMember rank when user is 17 or older with crew member position')`
- `it('sets CrewMember rank when user age is unknown with crew member position')`
- `it('clears old position when reassigning user to a new position')`
- `it('unassigns existing holder when position is already filled')`
- `it('records activity when assigning')`

**UnassignStaffPositionTest.php:**
- `it('removes the user from the position')`
- `it('clears user staff fields')`
- `it('preserves bio data when unassigning')`
- `it('does nothing when position is already vacant')`
- `it('records activity when unassigning')`

**SetUsersStaffPositionTest.php:**
- `it('sets staff position for user with no existing position')`
- `it('updates existing staff position completely')`
- `it('returns false when title is null')`
- `it('returns true when no changes are needed')`
- `it('records activity when staff position is updated')`
- `it('only updates changed fields - department only')`
- `it('only updates changed fields - rank only')`
- `it('only updates changed fields - title only')`
- `it('handles user with null existing department correctly')`
- `it('handles user with None rank correctly')`
- `it('works with all staff departments')`
- `it('works with all staff ranks')`
- `it('handles special characters in title')`
- `it('handles empty string title as null')`
- `it('preserves other user data when updating staff position')`
- `it('persists changes to database correctly')`
- `it('handles multiple updates to same user')`
- `it('generates correct activity description with multiple changes')`
- `it('handles long titles correctly')`

**RemoveUsersStaffPositionTest.php:**
- `it('removes all staff position details from user')`
- `it('records activity when staff position is removed')`
- `it('returns true when operation is successful')`
- `it('handles user with different staff departments correctly')`
- `it('handles user with different staff ranks correctly')`
- `it('handles user with null staff title')`
- `it('handles user with empty string staff title')`
- `it('preserves other user data when removing staff position')`
- `it('generates correct activity description format')`
- `it('works with user that has special characters in staff title')`
- `it('persists changes to database correctly')`
- `it('can be called multiple times on same user without error')`
- `it('handles long staff titles correctly')`
- `it('calls RecordActivity with correct parameters')`

**StaffBioTest.php:**
- `it('allows crew members to access the staff bio page')`
- `it('allows officers to access the staff bio page')`
- `it('denies jr crew from accessing the staff bio page')`
- `it('denies regular users from accessing the staff bio page')`
- `it('denies unauthenticated users from accessing the staff bio page')`

**SyncDiscordStaffTest.php:**
- `it('syncs staff department and rank roles')`
- `it('removes all staff roles when department is null')`
- `it('skips sync when user has no discord accounts')`
- `it('skips brigged accounts')`
- `it('records activity when syncing staff roles')`
- `it('records removal activity when department is null')`
- `it('includes all staff role ids in managed set')`

**StaffPageTest.php:**
- `it('loads the public staff page without authentication')`
- `it('displays filled positions with user names')`
- `it('displays vacant positions as open')`
- `it('groups positions by department')`
- `it('does not display departments with no positions')`
- `it('displays board members section on the public page')`
- `it('does not display board members section when no board members exist')`
- `it('shows linked board member with user staff name')`
- `it('shows unlinked board member with display name')`

### Coverage Gaps

- No tests for staff bio save/update functionality (only access control is tested)
- No tests for photo upload/removal in the staff bio settings
- No tests for the create/edit/delete position flows in the ACP component (only assign/unassign via the component)
- No tests for `SyncMinecraftStaff` action
- No tests for the staff detail panel display (selecting a position and viewing details)
- No test for the `viewStaffPhone` policy method in the context of the staff page
- No tests for the JrCrew visibility restrictions (bio, photo, real name hidden for JrCrew on staff page)

---

## 17. File Map

**Models:**
- `app/Models/StaffPosition.php`
- `app/Models/User.php` (staff_rank, staff_department, staff_title, staff_bio fields)

**Enums:**
- `app/Enums/StaffDepartment.php`
- `app/Enums/StaffRank.php`

**Actions:**
- `app/Actions/AssignStaffPosition.php`
- `app/Actions/UnassignStaffPosition.php`
- `app/Actions/SetUsersStaffPosition.php`
- `app/Actions/RemoveUsersStaffPosition.php`
- `app/Actions/SyncMinecraftStaff.php`
- `app/Actions/SyncDiscordStaff.php`

**Policies:**
- `app/Policies/StaffPositionPolicy.php`
- `app/Policies/UserPolicy.php` (viewStaffPhone method)

**Gates:** `AuthServiceProvider.php` — gates: `edit-staff-bio`, `view-ready-room`, `view-ready-room-command`, `view-ready-room-chaplain`, `view-ready-room-engineer`, `view-ready-room-quartermaster`, `view-ready-room-steward`, `manage-stowaway-users`, `manage-traveler-users`, `view-acp`, `view-mc-command-log`, `view-discord-api-log`, `view-activity-log`, `view-discipline-report-log`, `manage-discipline-reports`, `publish-discipline-reports`, `view-command-dashboard`

**Notifications:** None

**Jobs:** None directly (uses `SendMinecraftCommand::dispatch()` indirectly)

**Services:**
- `app/Services/DiscordApiService.php` (syncManagedRoles)

**Controllers:** None

**Volt Components:**
- `resources/views/livewire/staff/page.blade.php`
- `resources/views/livewire/admin-manage-staff-positions-page.blade.php`
- `resources/views/livewire/settings/staff-bio.blade.php`
- `resources/views/livewire/users/display-basic-details.blade.php` (staff section)

**Routes:**
- `staff.index` → `GET /staff`
- `settings.staff-bio` → `GET /settings/staff-bio`

**Migrations:**
- `database/migrations/2025_08_05_130249_update_users_add_membership_and_officer_roles.php`
- `database/migrations/2026_03_03_000001_create_staff_positions_table.php`
- `database/migrations/2026_03_03_000002_add_staff_bio_fields_to_users_table.php`
- `database/migrations/2026_03_04_170011_add_staff_phone_to_users_table.php`

**Console Commands:** None

**Factories:**
- `database/factories/StaffPositionFactory.php`

**Tests:**
- `tests/Feature/Policies/StaffPositionPolicyTest.php`
- `tests/Feature/Livewire/ManageStaffPositionsTest.php`
- `tests/Feature/Actions/AssignStaffPositionTest.php`
- `tests/Feature/Actions/UnassignStaffPositionTest.php`
- `tests/Unit/Actions/SetUsersStaffPositionTest.php`
- `tests/Unit/Actions/RemoveUsersStaffPositionTest.php`
- `tests/Feature/Settings/StaffBioTest.php`
- `tests/Feature/Discord/SyncDiscordStaffTest.php`
- `tests/Feature/StaffPageTest.php`

**Config:**
- `config/lighthouse.php` — `discord.roles.staff_*` and `discord.roles.rank_*`

**Other:**
- `resources/views/livewire/admin-control-panel-tabs.blade.php` (embeds staff positions management)

---

## 18. Known Issues & Improvement Opportunities

1. **`SyncMinecraftStaff` uses `RecordActivity::handle()` instead of `::run()`**: The `SyncMinecraftStaff` action calls `RecordActivity::handle()` directly rather than `RecordActivity::run()` as per project conventions. This works since `handle()` is the underlying method, but is inconsistent with the `AsAction` pattern used everywhere else.

2. **No tests for `SyncMinecraftStaff`**: This action dispatches RCON commands to set/remove staff positions on the Minecraft server but has no dedicated test file. Only Discord sync has tests.

3. **No tests for staff bio save functionality**: The `StaffBioTest` only tests access control (who can view the page) but doesn't test the actual save, photo upload, or photo removal functionality.

4. **Position CRUD not tested through component**: `ManageStaffPositionsTest` only tests mount, assign, and remove — not create, edit, or delete position flows through the Livewire component.

5. **JrCrew bio/photo restrictions are UI-only**: The staff page hides bio, real name, and custom photo for JrCrew members through Blade conditionals rather than a policy method. This means the data is still present on the model and could leak through other views.

6. **Assign/unassign not exposed in ACP component**: The `admin-manage-staff-positions-page.blade.php` component manages position CRUD but doesn't have assign/unassign functionality in its UI. The `AssignStaffPosition` and `UnassignStaffPosition` actions exist but their UI entry point is not clear from the code examined.

7. **Unique constraint on `user_id` prevents multiple positions**: The `staff_positions` table has a unique constraint on `user_id`, enforcing one position per user at the database level. This is correct for the current design but means a user cannot hold multiple positions simultaneously.

8. **Staff bio data preserved on unassign**: `UnassignStaffPosition` clears `staff_rank`, `staff_department`, and `staff_title` but preserves `staff_first_name`, `staff_last_initial`, `staff_bio`, `staff_phone`, and `staff_photo_path`. This is tested and intentional, but means former staff retain bio data that may become stale.
