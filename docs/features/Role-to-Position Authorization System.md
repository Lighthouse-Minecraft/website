# Role-to-Position Authorization System

## 1. Overview

The Role-to-Position Authorization System replaces the original role-based authorization model where roles were directly attached to users via a `role_user` pivot table. In the new system, permission roles are attached to **staff positions**, and users inherit roles through their assigned staff position.

### Key Design Decisions

- **Admin status** is stored as an `admin_granted_at` timestamp on the `users` table, replacing the old "Admin" role record. `User::isAdmin()` checks this timestamp.
- **Roles are attached to staff positions**, not users. The `role_user` pivot table was dropped and replaced with `role_staff_position`.
- **"Allow All" override**: A staff position can have `has_all_roles_at` set, granting the user in that position all roles without individually assigning them.
- **Role resolution chain**: `User::hasRole($name)` checks: (1) `isAdmin()` override, (2) staff position existence, (3) `has_all_roles_at` override, (4) position's assigned roles.
- **15 seeded permission roles** cover all discrete permissions in the system (moderating, brig management, discipline reports, docs editing, etc.).
- **All gates** in `AuthServiceProvider` were refactored to use `$user->hasRole('RoleName')` instead of checking rank/department combinations.
- **All policy `before()` hooks** check `$user->isAdmin()` only (no longer bypass for Command Officers).
- Old role records for membership levels (Stowaway, Traveler, Resident, Citizen, Guest) and the Admin role were deleted during migration.

### Relationship to Prior System

| Concept | Before | After |
|---------|--------|-------|
| Admin check | `role_user` pivot with "Admin" role | `users.admin_granted_at` timestamp |
| Role assignment | `role_user` (role <-> user) | `role_staff_position` (role <-> staff_position) |
| Role inheritance | Direct on user | Through staff position |
| Super-permission | Admin role in `role_user` | `admin_granted_at` OR `has_all_roles_at` on position |
| Policy bypass | Command Officers via `before()` | Admin only via `before()` |

---

## 2. Database Schema

### Original Tables (Unchanged by Feature)

#### `roles` table
Created by `database/migrations/2025_08_03_163609_create_roles_table.php`.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint (PK) | Auto-increment |
| `name` | string (unique) | Role name |
| `color` | string | Default `'zinc'` |
| `description` | text (nullable) | Human-readable description |
| `icon` | string (nullable) | Heroicons icon name |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

#### `staff_positions` table
Created by `database/migrations/2026_03_03_000001_create_staff_positions_table.php`.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint (PK) | Auto-increment |
| `title` | string | Position title |
| `department` | string | Cast to `StaffDepartment` enum |
| `rank` | unsignedTinyInteger | Cast to `StaffRank` enum |
| `description` | text (nullable) | |
| `responsibilities` | text (nullable) | |
| `requirements` | text (nullable) | |
| `user_id` | FK to `users` (nullable, unique) | One user per position |
| `sort_order` | unsignedInteger | Default 0 |
| `accepting_applications` | boolean | Added by later migration |
| `has_all_roles_at` | timestamp (nullable) | **Added by this feature** |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### New/Modified Schema (This Feature)

Migration: `database/migrations/2026_03_19_000001_admin_flag_and_role_infrastructure.php`

**Changes performed (in order):**

1. Adds `admin_granted_at` (nullable timestamp) to `users` table after `promoted_at`.
2. Migrates existing users with the "Admin" role in `role_user` to have `admin_granted_at = now()`.
3. Adds `has_all_roles_at` (nullable timestamp) to `staff_positions` table after `accepting_applications`.
4. Creates `role_staff_position` pivot table.
5. Deletes redundant role records: Stowaway, Traveler, Resident, Citizen, Guest, Admin.
6. Drops the `role_user` pivot table.

#### `role_staff_position` pivot table

| Column | Type | Notes |
|--------|------|-------|
| `role_id` | FK to `roles` | CASCADE on delete |
| `staff_position_id` | FK to `staff_positions` | CASCADE on delete |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

#### `users` table addition

| Column | Type | Notes |
|--------|------|-------|
| `admin_granted_at` | timestamp (nullable) | After `promoted_at`. Non-null = admin. |

### Seed Migration

Migration: `database/migrations/2026_03_19_000002_seed_permission_roles.php`

Uses `updateOrInsert` to seed 15 permission roles (see Section 4 for full list). Idempotent -- safe to re-run.

---

## 3. Models & Relationships

### `App\Models\User`

**File:** `app/Models/User.php`

**New/modified attributes:**
- `admin_granted_at` -- cast to `datetime`

**Key methods:**

```php
public function isAdmin(): bool
{
    return $this->admin_granted_at !== null;
}

public function hasRole(string $roleName): bool
{
    // 1. Admin override -- admins have all roles
    if ($this->isAdmin()) {
        return true;
    }
    // 2. No staff position means no roles
    $position = $this->staffPosition;
    if (! $position) {
        return false;
    }
    // 3. Position with "allow all" override
    if ($position->has_all_roles_at !== null) {
        return true;
    }
    // 4. Check position's assigned roles
    return $position->roles()->where('name', $roleName)->exists();
}
```

**Relationships (relevant):**
- `staffPosition(): HasOne` -- links to `StaffPosition`

### `App\Models\Role`

**File:** `app/Models/Role.php`

**Fillable:** `name`, `description`, `color`, `icon`

**Relationships:**
- `staffPositions(): BelongsToMany` -- links to `StaffPosition` via `role_staff_position` pivot (previously was `users(): BelongsToMany` via `role_user`)

### `App\Models\StaffPosition`

**File:** `app/Models/StaffPosition.php`

**New/modified attributes:**
- `has_all_roles_at` -- cast to `datetime`, added to `$fillable`

**Key methods:**

```php
public function hasRole(string $roleName): bool
{
    if ($this->has_all_roles_at !== null) {
        return true;
    }
    return $this->roles()->where('name', $roleName)->exists();
}
```

**Relationships (relevant):**
- `user(): BelongsTo` -- the user assigned to this position
- `roles(): BelongsToMany` -- links to `Role` via `role_staff_position` pivot

---

## 4. Enums Reference

### `App\Enums\StaffRank` (int-backed)

**File:** `app/Enums/StaffRank.php`

| Case | Value | Label |
|------|-------|-------|
| `None` | 0 | None |
| `JrCrew` | 1 | Junior Crew Member |
| `CrewMember` | 2 | Crew Member |
| `Officer` | 3 | Officer |

Methods: `label()`, `color()`, `discordRoleId()`

### `App\Enums\StaffDepartment` (string-backed)

**File:** `app/Enums/StaffDepartment.php`

| Case | Value |
|------|-------|
| `Command` | `'command'` |
| `Chaplain` | `'chaplain'` |
| `Engineer` | `'engineer'` |
| `Quartermaster` | `'quartermaster'` |
| `Steward` | `'steward'` |

Methods: `label()`, `discordRoleId()`

### `App\Enums\MembershipLevel` (int-backed)

**File:** `app/Enums/MembershipLevel.php`

| Case | Value |
|------|-------|
| `Drifter` | 0 |
| `Stowaway` | 1 |
| `Traveler` | 2 |
| `Resident` | 3 |
| `Citizen` | 4 |

Methods: `label()`, `discordRoleId()`, `minecraftRank()`

---

## 5. Authorization & Permissions

### 5.1 Seeded Permission Roles (15 total)

| Role Name | Description | Color | Icon |
|-----------|-------------|-------|------|
| Page Editor | Edit and manage website content pages | purple | newspaper |
| Moderator | View flagged messages, moderator powers in Discussions, lock topics | red | shield-exclamation |
| Brig Warden | Handle brig appeals, release users from the brig | orange | lock-closed |
| Manage Membership Level | Promote and demote users through membership levels | emerald | arrow-trending-up |
| Meeting Secretary | Manage non-staff-meeting meetings | amber | inbox-arrow-down |
| Attend Staff Meeting | Read-only access to staff meetings | sky | eye |
| Manage Staff Meeting | Write access to staff meetings | blue | pencil-square |
| Edit Docs | Edit documentation and handbook pages | violet | document-text |
| Manage Community Stories | Manage community questions and responses | pink | chat-bubble-left-right |
| Manage Discipline Reports | Create and manage discipline reports | yellow | clipboard-document-list |
| Publish Discipline Reports | Publish and finalize discipline reports | red | clipboard-document-check |
| Manage Site Config | Manage site configuration and application questions | slate | cog-6-tooth |
| View Logs | Access MC command log, Discord API log, and activity log | zinc | document-magnifying-glass |
| View All Ready Rooms | See all department ready rooms | teal | building-office |
| View Command Dashboard | Access the Command dashboard | indigo | chart-bar |

### 5.2 Gates

**File:** `app/Providers/AuthServiceProvider.php`

All gates are defined in the `boot()` method. Gates using `hasRole()` are marked with the role they check.

| Gate | Authorization Logic | Role(s) Used |
|------|---------------------|--------------|
| `view-community-content` | `!$user->in_brig` | -- |
| `view-all-community-updates` | `isAtLeastLevel(Traveler) \|\| hasRole('Admin')` | (admin override via hasRole) |
| `manage-stowaway-users` | `hasRole('Manage Membership Level')` | Manage Membership Level |
| `manage-traveler-users` | `hasRole('Manage Membership Level')` | Manage Membership Level |
| `release-from-brig` | `hasRole('Brig Warden')` | Brig Warden |
| `view-ready-room` | `hasRole('Admin') \|\| isAtLeastRank(JrCrew)` | (admin override) |
| `view-ready-room-command` | `isInDepartment(Command) \|\| hasRole('View All Ready Rooms')` | View All Ready Rooms |
| `view-ready-room-chaplain` | `isInDepartment(Chaplain) \|\| hasRole('View All Ready Rooms')` | View All Ready Rooms |
| `view-ready-room-engineer` | `isInDepartment(Engineer) \|\| hasRole('View All Ready Rooms')` | View All Ready Rooms |
| `view-ready-room-quartermaster` | `isInDepartment(Quartermaster) \|\| hasRole('View All Ready Rooms')` | View All Ready Rooms |
| `view-ready-room-steward` | `isInDepartment(Steward) \|\| hasRole('View All Ready Rooms')` | View All Ready Rooms |
| `link-discord` | `isAtLeastLevel(Stowaway) && !in_brig && parent_allows_discord` | -- |
| `view-parent-portal` | `isAdult() \|\| children()->exists()` | -- |
| `link-minecraft-account` | `isAtLeastLevel(Traveler) && !in_brig && parent_allows_minecraft` | -- |
| `view-acp` | `isAdmin() \|\| isAtLeastRank(JrCrew)` | -- |
| `view-mc-command-log` | `hasRole('View Logs')` | View Logs |
| `view-discord-api-log` | `hasRole('View Logs')` | View Logs |
| `view-activity-log` | `hasRole('View Logs')` | View Logs |
| `view-discipline-report-log` | `hasRole('View Logs')` | View Logs |
| `edit-staff-bio` | `isAtLeastRank(CrewMember) \|\| is_board_member` | -- |
| `board-member` | `$user->is_board_member` | -- |
| `view-user-discipline-reports` | `hasRole('Admin') \|\| isAtLeastRank(JrCrew) \|\| self \|\| parent` | (admin override) |
| `manage-discipline-reports` | `hasRole('Manage Discipline Reports')` | Manage Discipline Reports |
| `publish-discipline-reports` | `hasRole('Publish Discipline Reports')` | Publish Discipline Reports |
| `manage-site-config` | `hasRole('Manage Site Config')` | Manage Site Config |
| `view-command-dashboard` | `hasRole('View Command Dashboard')` | View Command Dashboard |
| `view-docs-users` | `!in_brig` | -- |
| `view-docs-resident` | `!in_brig && isAtLeastLevel(Resident)` | -- |
| `view-docs-citizen` | `!in_brig && isAtLeastLevel(Citizen)` | -- |
| `view-docs-staff` | `!in_brig && (isAtLeastRank(JrCrew) \|\| hasRole('Admin'))` | (admin override) |
| `view-docs-officer` | `!in_brig && (isAtLeastRank(Officer) \|\| hasRole('Admin'))` | (admin override) |
| `edit-docs` | `hasRole('Edit Docs')` | Edit Docs |
| `lock-topic` | `hasRole('Moderator')` | Moderator |
| `view-community-stories` | `!in_brig && isAtLeastLevel(Traveler)` | -- |
| `submit-community-response` | `!in_brig && isAtLeastLevel(Traveler)` | -- |
| `suggest-community-question` | `!in_brig && isAtLeastLevel(Citizen)` | -- |
| `manage-community-stories` | `hasRole('Manage Community Stories')` | Manage Community Stories |
| `review-staff-applications` | `isAdmin() \|\| isAtLeastRank(Officer) \|\| (JrCrew + dept match)` | -- |
| `manage-application-questions` | `hasRole('Manage Site Config')` | Manage Site Config |

### 5.3 Policies

**All policies registered in `AuthServiceProvider::$policies`:**

| Model | Policy |
|-------|--------|
| `Thread` | `ThreadPolicy` |
| `Message` | `MessagePolicy` |
| `DiscordAccount` | `DiscordAccountPolicy` |
| `ParentChildLink` | `ParentChildLinkPolicy` |
| `DisciplineReport` | `DisciplineReportPolicy` |
| `ReportCategory` | `ReportCategoryPolicy` |
| `StaffPosition` | `StaffPositionPolicy` |
| `BoardMember` | `BoardMemberPolicy` |
| `CommunityQuestion` | `CommunityQuestionPolicy` |
| `CommunityResponse` | `CommunityResponsePolicy` |
| `StaffApplication` | `StaffApplicationPolicy` |

**Policies not registered in `$policies` but present:**
`AnnouncementPolicy`, `MeetingPolicy`, `MeetingNotePolicy`, `PagePolicy`, `PrayerCountryPolicy`, `TaskPolicy`, `UserPolicy`, `RolePolicy`, `MinecraftAccountPolicy`

#### Policy `before()` Hook Summary

All policies with `before()` hooks now check `$user->isAdmin()` only. Command Officers no longer get automatic bypass.

| Policy | `before()` behavior |
|--------|---------------------|
| `AnnouncementPolicy` | Admin -> `true`; all others -> `null` |
| `BoardMemberPolicy` | Admin -> `true`; all others -> `null` |
| `CommunityQuestionPolicy` | Skips `delete` (falls through); Admin -> `true`; all others -> `null` |
| `CommunityResponsePolicy` | Admin -> `true`; all others -> `null` |
| `DisciplineReportPolicy` | Skips `delete` and `publish` (falls through); Admin -> `true`; all others -> `null` |
| `MeetingPolicy` | Admin -> `true`; all others -> `null` |
| `MeetingNotePolicy` | Admin -> `true`; all others -> `null` |
| `MessagePolicy` | Admin -> `true`; all others -> `null` |
| `PagePolicy` | Admin -> `true`; all others -> `null` |
| `PrayerCountryPolicy` | Admin -> `true`; all others -> `null` |
| `ReportCategoryPolicy` | Skips `delete` (falls through); Admin -> `true`; all others -> `null` |
| `RolePolicy` | Admin -> `true`; all others -> `null` |
| `StaffApplicationPolicy` | Admin -> `true`; all others -> `null` |
| `StaffPositionPolicy` | Admin -> `true`; all others -> `null` |
| `TaskPolicy` | Admin -> `true`; all others -> `null` |
| `ThreadPolicy` | Skips `reply` and `createTopic` (falls through); Admin -> `true`; all others -> `null` |
| `UserPolicy` | Admin -> `true`; all others -> `null` |
| `DiscordAccountPolicy` | No `before()` hook |
| `MinecraftAccountPolicy` | No `before()` hook |
| `ParentChildLinkPolicy` | No `before()` hook |

#### Policies Using `hasRole()` in Method Bodies

| Policy | Method(s) | Role(s) Checked |
|--------|-----------|-----------------|
| `AnnouncementPolicy` | `create`, `update`, `delete` | `Announcement Editor` |
| `CommunityResponsePolicy` | `view`, `update`, `delete` | `Manage Community Stories` |
| `DisciplineReportPolicy` | `viewAny`, `view`, `create`, `update` | `Manage Discipline Reports` |
| `DisciplineReportPolicy` | `publish` | `Publish Discipline Reports` |
| `MeetingPolicy` | `viewAny`, `view`, `attend`, `viewAnyPrivate`, `viewAnyPublic`, `create`, `update` | `Meeting Secretary` |
| `MeetingNotePolicy` | `create`, `update` | `Meeting Secretary` |
| `PagePolicy` | `viewAny`, `view`, `create`, `update`, `delete` | `Page Editor` |
| `ThreadPolicy` | `viewFlagged` | `Moderator` |

### 5.4 Permissions Matrix (Role -> Gates Unlocked)

| Role | Gates Directly Unlocked |
|------|------------------------|
| Admin (`admin_granted_at`) | All gates (via `hasRole()` override + policy `before()` hooks) |
| Manage Membership Level | `manage-stowaway-users`, `manage-traveler-users` |
| Brig Warden | `release-from-brig` |
| View All Ready Rooms | `view-ready-room-command`, `-chaplain`, `-engineer`, `-quartermaster`, `-steward` |
| View Logs | `view-mc-command-log`, `view-discord-api-log`, `view-activity-log`, `view-discipline-report-log` |
| Manage Discipline Reports | `manage-discipline-reports` |
| Publish Discipline Reports | `publish-discipline-reports` |
| Manage Site Config | `manage-site-config`, `manage-application-questions` |
| View Command Dashboard | `view-command-dashboard` |
| Edit Docs | `edit-docs` |
| Moderator | `lock-topic` |
| Manage Community Stories | `manage-community-stories` |
| Allow All (`has_all_roles_at`) | All role-gated permissions (same as Admin, excluding `isAdmin()` checks) |

---

## 6. Routes

The role/position management UI is embedded within the Admin Control Panel (ACP) as Livewire components rendered inside tabs. There are no dedicated routes for role or position management.

| Route | Controller/View | Gate |
|-------|-----------------|------|
| `GET /acp` | `AdminControlPanelController@index` | `view-acp` |

The ACP renders `admin-control-panel-tabs.blade.php`, which includes:
- **Staff Position Manager tab**: `<livewire:admin-manage-staff-positions-page />` (gated by `@can('viewAny', StaffPosition::class)`)
- **Role Manager tab**: `<livewire:admin-manage-roles-page />` (gated by `@can('viewAny', Role::class)`)

---

## 7. User Interface Components

### 7.1 Staff Position Manager

**File:** `resources/views/livewire/admin-manage-staff-positions-page.blade.php`

A Livewire Volt component that manages staff positions. Relevant to roles:

**PHP class properties:**
- `$rolePositionId` -- ID of position being managed for roles
- `$selectedRoleId` -- selected role to add

**PHP class methods (role-related):**
- `getPositionsProperty()` -- loads positions with `roles` relationship, authorizes via `StaffPosition::viewAny`
- `getAllRolesProperty()` -- loads all roles ordered by name
- `openRolesModal(int $positionId)` -- opens the role management modal for a position
- `addRoleToPosition()` -- attaches a role to the position via `syncWithoutDetaching`
- `removeRoleFromPosition(int $roleId)` -- detaches a role from the position
- `toggleAllowAll(int $positionId)` -- toggles `has_all_roles_at` timestamp on the position
- `getRolePositionProperty()` -- returns the position being managed with its roles

**Blade template features:**
- Positions table shows a "Roles" column with:
  - "Allow All" amber badge with star icon when `has_all_roles_at` is set
  - Individual role badges with their color and icon
  - "None" when no roles assigned
- "Roles" button on each position row opens the manage-roles modal
- **Manage Roles Modal** (`manage-roles-modal`):
  - Allow All toggle with Enable/Disable button
  - When Allow All is disabled: shows assigned roles with remove (X) button, and a dropdown to add new roles
  - When Allow All is enabled: shows message that individual assignments are not needed

### 7.2 Role Manager (CRUD)

**File:** `resources/views/livewire/admin-manage-roles-page.blade.php`

A Livewire Volt component for creating and editing roles. Admin-only access enforced via `RolePolicy`.

**PHP class methods:**
- `roles()` -- returns all roles
- `createRole()` -- validates and creates a new role (name, color, description, icon)
- `openEditModal($roleId)` -- loads role data into edit form
- `updateRole()` -- validates and updates an existing role

**Blade template features:**
- Table listing all roles with badge preview, color, description, icon, and Edit button
- Create Role flyout modal
- Edit Role flyout modal
- Icon validation against a curated list of Heroicons names

### 7.3 User Profile Role Badges

**File:** `resources/views/livewire/users/display-basic-details.blade.php`

The "Staff Details" card on user profiles shows role badges. Visibility is restricted to staff members (`isAtLeastRank(JrCrew)` or `isAdmin()`).

**Relevant Blade section (lines 790-809):**
- If the viewer is at least JrCrew or Admin:
  - If the user's position has `has_all_roles_at`: shows "Allow All" amber badge
  - Otherwise if the position has roles: shows each role as a badge with color and icon
- Non-staff viewers see the position title but not the role badges

---

## 8. Actions (Business Logic)

### `App\Actions\PromoteUserToAdmin`

**File:** `app/Actions/PromoteUserToAdmin.php`

Sets `admin_granted_at` and `promoted_at` to `now()` on the user. Idempotent (returns `true` if already admin). Logs activity as `user_promoted_to_admin`.

```php
public function handle(User $user): bool
```

### `App\Actions\RevokeUserAdmin`

**File:** `app/Actions/RevokeUserAdmin.php`

Sets `admin_granted_at` to `null`. Idempotent (returns `true` if already not admin). Logs activity as `user_admin_revoked`.

```php
public function handle(User $user): bool
```

---

## 9. Notifications

No notifications are sent as part of this feature. Admin promotion/revocation does not trigger notifications.

---

## 10. Background Jobs

No background jobs are introduced by this feature.

---

## 11. Console Commands & Scheduled Tasks

### `app:promote-user-to-admin`

**File:** `app/Console/Commands/PromoteUserToAdmin.php`

**Signature:** `app:promote-user-to-admin {email}`

**Description:** Promote a user to the Admin role by email address.

**Behavior:**
1. Looks up user by email
2. If not found: prints error, exits with code 1
3. If already admin: prints info message, exits with code 0
4. Otherwise: calls `PromoteUserToAdmin::run($user)`, prints success, exits with code 0

---

## 12. Services

No new services are introduced by this feature. Role resolution is handled directly in model methods (`User::hasRole()`, `StaffPosition::hasRole()`).

---

## 13. Activity Log Entries

| Action | Description | Subject |
|--------|-------------|---------|
| `user_promoted_to_admin` | "Promoted to Admin role." | User |
| `user_admin_revoked` | "Admin role revoked." | User |

---

## 14. Data Flow Diagrams

### Role Resolution Chain

```
User::hasRole('Moderator')
  |
  +-- isAdmin()? (admin_granted_at != null)
  |     YES -> return true
  |
  +-- Has staffPosition?
  |     NO -> return false
  |
  +-- staffPosition.has_all_roles_at != null?
  |     YES -> return true
  |
  +-- staffPosition.roles contains 'Moderator'?
        YES -> return true
        NO  -> return false
```

### Gate Resolution Flow

```
User attempts action requiring gate 'manage-discipline-reports'
  |
  +-- Gate::define checks $user->hasRole('Manage Discipline Reports')
        |
        +-- Role resolution chain (above)
              |
              +-- If user is admin -> allowed
              +-- If user's position has the role -> allowed
              +-- If user's position has Allow All -> allowed
              +-- Otherwise -> denied
```

### Policy Resolution Flow

```
User attempts policy action (e.g., DisciplineReport::update)
  |
  +-- before() hook: $user->isAdmin()?
  |     YES -> return true (bypass all checks)
  |     NO  -> return null (proceed to method)
  |
  +-- Policy method checks:
        +-- $user->hasRole('Manage Discipline Reports')? -> true
        +-- Other conditions (rank, ownership, etc.)
```

---

## 15. Configuration

No new configuration keys are introduced. Existing configuration used by referenced enums:

- `lighthouse.discord.roles.rank_jr_crew` -- Discord role ID for JrCrew rank
- `lighthouse.discord.roles.rank_crew_member` -- Discord role ID for CrewMember rank
- `lighthouse.discord.roles.rank_officer` -- Discord role ID for Officer rank
- `lighthouse.discord.roles.staff_command` (and other departments) -- Discord role IDs per department

---

## 16. Test Coverage

### `tests/Feature/Actions/Actions/RoleInfrastructureTest.php`

Group: `roles`, `actions`

| Test | Description |
|------|-------------|
| `it('isAdmin returns true when admin_granted_at is set')` | Verifies `isAdmin()` with timestamp set |
| `it('isAdmin returns false when admin_granted_at is null')` | Verifies `isAdmin()` without timestamp |
| `it('admin override grants all role checks')` | Admin passes any `hasRole()` call including nonexistent roles |
| `it('revokes admin by setting admin_granted_at to null')` | `RevokeUserAdmin` clears admin |
| `it('revoke is idempotent when user is not admin')` | No-op when already non-admin |
| `it('revoke records activity')` | Activity log entry created |
| `it('user with no staff position has no roles')` | `hasRole()` returns false without position |
| `it('user with staff position inherits that position roles')` | User gets role through position |
| `it('user whose position loses a role immediately loses that permission')` | Detaching role revokes access |
| `it('changing a user staff position changes their roles')` | New position = new roles |
| `it('user with allow-all position returns true for any role')` | `has_all_roles_at` override |
| `it('staff position hasRole returns true when position has the role')` | Direct position method |
| `it('staff position hasRole returns true for any role when has_all_roles_at is set')` | Allow All on position |
| `it('withRole factory creates a position and assigns the role')` | Factory helper validation |
| `it('role_staff_position pivot table works correctly')` | Pivot attach/count |

### `tests/Feature/Migrations/SeedPermissionRolesTest.php`

Group: `roles`, `migrations`

| Test | Description |
|------|-------------|
| `it('seeds exactly 15 permission roles')` | Count >= 15 |
| `it('seeds all 15 roles with correct attributes')` | Each role has correct name, description, color, icon |
| `it('has all 15 role names matching the PRD specification')` | Names match expected set |

### `tests/Feature/Gates/RoleBasedGatesTest.php`

Group: `gates`, `roles`

| Test | Description |
|------|-------------|
| `it('grants manage-stowaway-users to user with Manage Membership Level role')` | |
| `it('grants manage-traveler-users to user with Manage Membership Level role')` | |
| `it('denies manage-stowaway-users without Manage Membership Level role')` | Officer without role denied |
| `it('grants manage-stowaway-users to admin via hasRole override')` | Admin gets all |
| `it('grants manage-stowaway-users to user with allow-all position')` | Allow All override |
| `it('grants release-from-brig to user with Brig Warden role')` | |
| `it('denies release-from-brig without Brig Warden role')` | |
| `it('grants release-from-brig to admin')` | |
| `it('grants view-ready-room-command to user in Command department')` | Department match |
| `it('denies view-ready-room-command to user in different department without role')` | |
| `it('grants view-ready-room-command to user with View All Ready Rooms role')` | Role override |
| `it('grants all department ready rooms to admin')` | |
| `it('grants view-ready-room-chaplain to user in Chaplain department')` | |
| `it('grants view-ready-room-engineer to user in Engineer department')` | |
| `it('grants view-ready-room-quartermaster to user in Quartermaster department')` | |
| `it('grants view-ready-room-steward to user in Steward department')` | |
| `it('grants all department ready rooms to user with View All Ready Rooms role')` | |
| `it('grants view-acp to admin')` | |
| `it('grants view-acp to JrCrew')` | |
| `it('grants view-acp to CrewMember')` | |
| `it('grants view-acp to Officer')` | |
| `it('denies view-acp to regular user')` | |
| `it('grants all log gates to user with View Logs role')` | All 4 log gates |
| `it('denies all log gates without View Logs role')` | Officer without role denied |
| `it('grants all log gates to admin')` | |
| `it('grants manage-discipline-reports to user with Manage Discipline Reports role')` | |
| `it('denies manage-discipline-reports without role')` | |
| `it('grants publish-discipline-reports to user with Publish Discipline Reports role')` | |
| `it('denies publish-discipline-reports without role')` | |
| `it('grants manage-site-config to user with Manage Site Config role')` | |
| `it('denies manage-site-config without role')` | |
| `it('grants view-command-dashboard to user with View Command Dashboard role')` | |
| `it('denies view-command-dashboard without role')` | |
| `it('grants edit-docs to user with Edit Docs role')` | |
| `it('denies edit-docs without role')` | |
| `it('grants lock-topic to user with Moderator role')` | |
| `it('denies lock-topic without Moderator role')` | |
| `it('grants manage-community-stories to user with Manage Community Stories role')` | |
| `it('denies manage-community-stories without role')` | |
| `it('grants manage-application-questions to user with Manage Site Config role')` | |
| `it('denies manage-application-questions without role')` | |
| `it('grants review-staff-applications to admin')` | |
| `it('grants review-staff-applications to officers')` | |
| `it('grants review-staff-applications list access to JrCrew')` | |
| `it('grants review-staff-applications for same-department application to JrCrew')` | |
| `it('denies review-staff-applications for different-department application to JrCrew')` | |
| `it('grants review-staff-applications for any department to officers')` | |
| `it('denies review-staff-applications to regular users')` | |
| `it('grants review-staff-applications list access to CrewMember')` | |
| `it('grants review-staff-applications for same-department application to CrewMember')` | |
| `it('denies review-staff-applications for different-department application to CrewMember')` | |
| `it('grants view-ready-room to JrCrew (rank-based, unchanged)')` | |
| `it('denies view-ready-room to regular user (rank-based, unchanged)')` | |
| `it('grants edit-staff-bio to CrewMember (rank-based, unchanged)')` | |
| `it('grants board-member to board member (unchanged)')` | |
| `it('grants view-community-content to user not in brig (unchanged)')` | |
| `it('denies view-community-content to user in brig (unchanged)')` | |
| `it('grants all role-based gates to user with allow-all position')` | 15 gates checked |
| `it('grants all role-based gates to admin user')` | 20 gates checked |

### `tests/Feature/Policies/PolicyBeforeHooksTest.php`

Group: `policies`, `before-hooks`

| Test | Description |
|------|-------------|
| `it('admin returns true from before hook')` | Dataset: 10 policies |
| `it('regular user returns null from before hook')` | Dataset: 10 policies |
| `it('command officer returns null from before hook (no longer bypasses)')` | Dataset: 10 policies |
| `it('report category policy before hook skips delete ability')` | |
| `it('community question policy before hook skips delete ability')` | |

### `tests/Feature/Policies/RolePolicyTest.php`

Group: `policies`, `roles`

| Test | Description |
|------|-------------|
| `it('admin bypasses all role policy checks via before hook')` | |
| `it('non-admin returns null from role policy before hook')` | |
| `it('command officer returns null from role policy before hook')` | |
| `it('admin can view any roles')` | |
| `it('non-admin cannot view any roles')` | |
| `it('admin can create roles')` | |
| `it('non-admin cannot create roles')` | |
| `it('admin can update roles')` | |
| `it('non-admin cannot update roles')` | |

### `tests/Feature/Policies/DisciplineReportPolicyRolesTest.php`

Group: `discipline-reports`, `policies`, `roles`

| Test | Description |
|------|-------------|
| `it('admin bypasses discipline report policy via before hook except delete and publish')` | |
| `it('non-admin returns null from discipline report policy before hook')` | |
| `it('command officer returns null from discipline report policy before hook')` | |
| `it('user with Manage Discipline Reports role can view any reports')` | |
| `it('user with Manage Discipline Reports role can view any report')` | |
| `it('user with Manage Discipline Reports role can create reports')` | |
| `it('user with Manage Discipline Reports role can update draft reports')` | |
| `it('user with Manage Discipline Reports role cannot update published reports')` | |
| `it('user with Publish Discipline Reports role can publish draft reports')` | |
| `it('user without Publish Discipline Reports role cannot publish')` | |
| `it('user with Publish Discipline Reports role cannot publish already published report')` | |
| `it('reporter with Publish role cannot publish their own report about a staff member')` | |
| `it('admin can publish any draft report via before hook bypass on publish')` | |
| `it('regular user without roles cannot manage discipline reports')` | |

### `tests/Feature/Policies/CommunityResponsePolicyTest.php`

Group: `policies`, `community-stories`

| Test | Description |
|------|-------------|
| `it('admin bypasses community response policy via before hook')` | |
| `it('non-admin returns null from community response policy before hook')` | |
| `it('command officer returns null from community response policy before hook')` | |
| `it('user with Manage Community Stories role can view any response')` | |
| `it('user can view their own response')` | |
| `it('user can view approved responses')` | |
| `it('user with Manage Community Stories role can update any response')` | |
| `it('owner can update their own editable response')` | |
| `it('non-owner without role cannot update response')` | |
| `it('user with Manage Community Stories role can delete any response')` | |
| `it('owner can delete their own editable response')` | |
| `it('non-owner without role cannot delete response')` | |

### `tests/Feature/Policies/ThreadPolicyTest.php`

Group: `policies`, `tickets`

| Test | Description |
|------|-------------|
| `it('admin bypasses thread policy via before hook except for reply and createTopic')` | |
| `it('non-admin returns null from thread policy before hook')` | |
| `it('command officer returns null from thread policy before hook')` | |
| `it('user with Moderator role can view flagged tickets')` | |
| `it('quartermaster crew member can view flagged tickets')` | |
| `it('non-quartermaster crew without Moderator role cannot view flagged tickets')` | |
| `it('regular user cannot view flagged tickets')` | |
| `it('admin can view all threads')` | |
| `it('non-admin cannot view all threads via viewAll method')` | |
| `it('crew member can view their department threads')` | |
| `it('jr crew cannot view department threads')` | |
| `it('admin can reply to unlocked threads')` | |
| `it('admin cannot reply to locked threads')` | |

### `tests/Feature/Livewire/RoleAssignmentUITest.php`

Group: `roles`, `livewire`

| Test | Description |
|------|-------------|
| `it('allows admin to assign a role to a staff position')` | |
| `it('allows admin to remove a role from a staff position')` | |
| `it('prevents non-admin from managing roles on positions')` | assertForbidden |
| `it('displays role badges in positions table')` | |
| `it('allows admin to enable Allow All on a position')` | |
| `it('allows admin to disable Allow All on a position')` | |
| `it('displays Allow All badge for positions with has_all_roles_at set')` | |
| `it('prevents non-admin from toggling Allow All')` | assertForbidden |
| `it('shows role badges on profile to staff members')` | |
| `it('hides role badges on profile from non-staff users')` | |
| `it('shows Allow All badge on profile for staff-visible positions')` | |
| `it('shows role badges to admin users on profile')` | |

---

## 17. File Map

### Models
- `app/Models/User.php` -- `isAdmin()`, `hasRole()`, `staffPosition()` relationship
- `app/Models/Role.php` -- `staffPositions()` relationship
- `app/Models/StaffPosition.php` -- `roles()` relationship, `hasRole()`, `has_all_roles_at`

### Migrations
- `database/migrations/2025_08_03_163609_create_roles_table.php` -- original `roles` table
- `database/migrations/2026_03_03_000001_create_staff_positions_table.php` -- original `staff_positions` table
- `database/migrations/2026_03_19_000001_admin_flag_and_role_infrastructure.php` -- `admin_granted_at`, `has_all_roles_at`, `role_staff_position` pivot, drops `role_user`
- `database/migrations/2026_03_19_000002_seed_permission_roles.php` -- seeds 15 permission roles

### Actions
- `app/Actions/PromoteUserToAdmin.php` -- sets `admin_granted_at`
- `app/Actions/RevokeUserAdmin.php` -- clears `admin_granted_at`

### Gates & Policies
- `app/Providers/AuthServiceProvider.php` -- all gates and policy mappings
- `app/Policies/RolePolicy.php` -- admin-only CRUD on roles
- `app/Policies/StaffPositionPolicy.php` -- admin-only management of positions
- `app/Policies/ThreadPolicy.php` -- `viewFlagged` uses Moderator role
- `app/Policies/DisciplineReportPolicy.php` -- uses Manage/Publish Discipline Reports roles
- `app/Policies/CommunityResponsePolicy.php` -- uses Manage Community Stories role
- `app/Policies/CommunityQuestionPolicy.php` -- delegates to `manage-community-stories` gate
- `app/Policies/AnnouncementPolicy.php` -- uses Announcement Editor role
- `app/Policies/MeetingPolicy.php` -- uses Meeting Secretary role
- `app/Policies/MeetingNotePolicy.php` -- uses Meeting Secretary role
- `app/Policies/PagePolicy.php` -- uses Page Editor role
- `app/Policies/MessagePolicy.php` -- admin before hook only
- `app/Policies/BoardMemberPolicy.php` -- admin before hook only
- `app/Policies/PrayerCountryPolicy.php` -- admin before hook only
- `app/Policies/TaskPolicy.php` -- admin before hook only
- `app/Policies/UserPolicy.php` -- admin before hook only
- `app/Policies/StaffApplicationPolicy.php` -- admin before hook only
- `app/Policies/ReportCategoryPolicy.php` -- admin before hook, skips delete
- `app/Policies/DiscordAccountPolicy.php` -- uses `isAdmin()` directly
- `app/Policies/MinecraftAccountPolicy.php` -- uses `isAdmin()` directly
- `app/Policies/ParentChildLinkPolicy.php` -- no admin hook

### Console Commands
- `app/Console/Commands/PromoteUserToAdmin.php` -- `app:promote-user-to-admin {email}`

### Volt Components
- `resources/views/livewire/admin-manage-staff-positions-page.blade.php` -- role assignment to positions, Allow All toggle
- `resources/views/livewire/admin-manage-roles-page.blade.php` -- role CRUD
- `resources/views/livewire/users/display-basic-details.blade.php` -- role badges on profile
- `resources/views/livewire/admin-control-panel-tabs.blade.php` -- embeds position and role management tabs

### Enums
- `app/Enums/StaffRank.php`
- `app/Enums/StaffDepartment.php`
- `app/Enums/MembershipLevel.php`

### Factories
- `database/factories/UserFactory.php` -- `admin()`, `withRole()`, `withStaffPosition()`
- `database/factories/StaffPositionFactory.php` -- `officer()`, `crewMember()`, `inDepartment()`, `assignedTo()`

### Tests
- `tests/Feature/Actions/Actions/RoleInfrastructureTest.php`
- `tests/Feature/Migrations/SeedPermissionRolesTest.php`
- `tests/Feature/Gates/RoleBasedGatesTest.php`
- `tests/Feature/Policies/PolicyBeforeHooksTest.php`
- `tests/Feature/Policies/RolePolicyTest.php`
- `tests/Feature/Policies/CommunityResponsePolicyTest.php`
- `tests/Feature/Policies/ThreadPolicyTest.php`
- `tests/Feature/Policies/DisciplineReportPolicyRolesTest.php`
- `tests/Feature/Livewire/RoleAssignmentUITest.php`

### Test Support
- `tests/Pest.php` -- `loginAsAdmin()`, `loginAs()` helpers
- `tests/Support/Users.php` -- `officerCommand()`, `crewQuartermaster()`, `crewEngineer()`, `jrCrewEngineer()`, etc.

---

## 18. Known Issues & Improvement Opportunities

1. **`Announcement Editor` role not seeded.** The `AnnouncementPolicy` checks `hasRole('Announcement Editor')` but this role is not among the 15 seeded roles. It must be created manually or added to the seed migration.

2. **No UI for promoting/revoking admin.** The `PromoteUserToAdmin` and `RevokeUserAdmin` actions exist, and there is an artisan command for promotion, but there is no web UI for granting or revoking admin status. This is currently CLI-only.

3. **N+1 query potential in `hasRole()`.** Each call to `User::hasRole()` loads the `staffPosition` relation and then queries `roles`. For views that check multiple roles on multiple users, this could cause N+1 queries. Consider eager-loading `staffPosition.roles` or caching role names.

4. **`role_staff_position` has no unique constraint.** The pivot table has foreign keys but no composite unique index on `(role_id, staff_position_id)`. The code uses `syncWithoutDetaching` which prevents duplicates at the application level, but a database constraint would be safer.

5. **No role for ticket management.** Thread/ticket management (assign, reroute, close, internal notes) still relies on rank checks (`isAtLeastRank(CrewMember)`, `isAtLeastRank(Officer)`) rather than role-based permissions. These could be converted to roles for finer-grained control.

6. **No role for staff application review.** The `review-staff-applications` gate uses rank-based checks rather than a dedicated role.

7. **Some policies still use `isAdmin()` directly** instead of going through `hasRole()` (e.g., `DiscordAccountPolicy`, `MinecraftAccountPolicy`). These are correct behavior (admin-only actions), but the pattern is inconsistent with policies that use `before()` hooks.
