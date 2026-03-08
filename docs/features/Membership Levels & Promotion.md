# Membership Levels & Promotion -- Technical Documentation

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

The Membership Levels & Promotion system defines the progression of users through the Lighthouse community. Every user has a `membership_level` that determines what features they can access, what Discord roles and Minecraft ranks they receive, and how they appear in the community. The system uses a linear progression: Drifter -> Stowaway -> Traveler -> Resident -> Citizen.

New users register as **Drifters** (level 0) with no access to community features. When they read and accept the community rules, they are automatically promoted to **Stowaway** (level 1), which gives them basic access but is essentially a "waiting room" — staff must review stowaways and manually promote them to **Traveler** (level 2) to grant Minecraft/Discord access. Travelers are new members building trust; after a period of active participation, staff promotes them to **Resident** (level 3) for full membership. **Citizen** (level 4) is the highest membership level.

Promotions and demotions are performed by staff with the `manage-stowaway-users` or `manage-traveler-users` gates (Admins, Officers, and Quartermaster CrewMembers+). Each promotion triggers Minecraft rank synchronization, Discord role synchronization, activity logging, and level-specific notifications. There is also a separate `PromoteUserToAdmin` action and Artisan command for granting the Admin role.

The membership level gates many features: `view-community-content` requires not being in brig, `view-all-community-updates` requires at least Traveler, and `link-discord`/`link-minecraft-account` require at least Stowaway (plus not being in brig and parental permission).

---

## 2. Database Schema

### `users` table (membership-related columns)

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `membership_level` | unsignedTinyInteger | No | `0` (Drifter) | MembershipLevel enum value (0-4) |
| `promoted_at` | timestamp | Yes | `null` | When user was last promoted |
| `rules_accepted_at` | timestamp | Yes | `null` | When user accepted community rules (triggers Drifter -> Stowaway) |

**Indexes:** None specific to membership columns.
**Foreign Keys:** None.
**Migration(s):**
- `database/migrations/2025_08_05_130249_update_users_add_membership_and_officer_roles.php` — adds `membership_level` (default Drifter)
- `database/migrations/2026_02_12_170856_add_promoted_at_to_users_table.php` — adds `promoted_at`

---

## 3. Models & Relationships

### User (`app/Models/User.php`)

**Fillable membership fields:** `membership_level`, `promoted_at`, `rules_accepted_at`

**Casts:**
- `membership_level` => `MembershipLevel::class`
- `promoted_at` => `datetime`
- `rules_accepted_at` => `datetime`

**Key Methods:**
- `isAtLeastLevel(MembershipLevel $level): bool` — Compares membership level values (`>=`)
- `isLevel(MembershipLevel $level): bool` — Exact level match (`==`)

**Related Relationships (used by promotion logic):**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `minecraftAccounts()` | hasMany | MinecraftAccount | Ranks synced on promotion |
| `discordAccounts()` | hasMany | DiscordAccount | Roles synced on promotion |
| `roles()` | belongsToMany | Role | Admin role attached via PromoteUserToAdmin |

---

## 4. Enums Reference

### MembershipLevel (`app/Enums/MembershipLevel.php`)

| Case | Value | Label | Discord Role | Minecraft Rank |
|------|-------|-------|-------------|----------------|
| `Drifter` | `0` | `Drifter` | None | None |
| `Stowaway` | `1` | `Stowaway` | None | None |
| `Traveler` | `2` | `Traveler` | `config('lighthouse.discord.roles.traveler')` | `traveler` |
| `Resident` | `3` | `Resident` | `config('lighthouse.discord.roles.resident')` | `resident` |
| `Citizen` | `4` | `Citizen` | `config('lighthouse.discord.roles.citizen')` | `citizen` |

**Helper Methods:**
- `label(): string` — Human-readable name for the level
- `discordRoleId(): ?string` — Discord role ID from config; `null` for Drifter/Stowaway
- `minecraftRank(): ?string` — Minecraft rank string; `null` for Drifter/Stowaway

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

| Gate Name | Who Can Pass | Logic Summary |
|-----------|-------------|---------------|
| `view-community-content` | Non-brigged users | `! $user->in_brig` |
| `view-all-community-updates` | Traveler+ or Admin | `$user->isAtLeastLevel(MembershipLevel::Traveler) \|\| $user->hasRole('Admin')` |
| `manage-stowaway-users` | Admin, Officers, Quartermaster CrewMember+ | Controls Stowaway->Traveler promotion and brig actions |
| `manage-traveler-users` | Admin, Officers, Quartermaster CrewMember+ | Controls Traveler->Resident promotion (same logic as manage-stowaway-users) |
| `link-discord` | Stowaway+ AND not in brig AND parent allows | `$user->isAtLeastLevel(Stowaway) && !$user->in_brig && $user->parent_allows_discord` |
| `link-minecraft-account` | Stowaway+ AND not in brig AND parent allows | `$user->isAtLeastLevel(Stowaway) && !$user->in_brig && $user->parent_allows_minecraft` |

### Policies

No promotion-specific policy methods exist. Promotion authorization is handled entirely through gates.

### Permissions Matrix

| User Type | Accept Rules (Drifter->Stowaway) | Promote Stowaway->Traveler | Promote Traveler->Resident | Promote/Demote on Profile | Promote to Admin | View Membership Level |
|-----------|--------------------------------|---------------------------|--------------------------|--------------------------|-----------------|---------------------|
| Drifter | Yes (self) | No | No | No | No | Yes (own) |
| Stowaway | N/A (already past) | No | No | No | No | Yes |
| Traveler | N/A | No | No | No | No | Yes |
| Resident | N/A | No | No | No | No | Yes |
| Citizen | N/A | No | No | No | No | Yes |
| CrewMember (QM) | N/A | Yes | Yes | Yes | No | Yes |
| Officer | N/A | Yes | Yes | Yes | No | Yes |
| Admin | N/A | Yes | Yes | Yes | Yes (console) | Yes |

---

## 6. Routes

| Method | URL | Middleware | Handler | Route Name |
|--------|-----|-----------|---------|------------|
| GET | `/` | auth, verified, ensure-dob | `DashboardController` | `dashboard` |
| GET | `/profile/{user}` | auth, can:view,user | `UserController@show` | `profile.show` |

The membership level system does not have dedicated routes. Promotion actions are embedded in the dashboard widgets (stowaway/traveler) and the user profile page. The rules acceptance modal is a dashboard component.

---

## 7. User Interface Components

### View Rules
**File:** `resources/views/livewire/dashboard/view-rules.blade.php`
**Route:** `/` (dashboard component)

**Purpose:** Displays community rules and handles Drifter -> Stowaway promotion when user accepts rules.

**Authorization:** None (available to all authenticated users; only Drifters get the "accept" button).

**User Actions Available:**
- "I Have Read the Rules and Agree to Follow Them" button -> calls `acceptRules()` -> sets `rules_accepted_at`, records activity, calls `PromoteUser::run($user, MembershipLevel::Stowaway)`, redirects to dashboard with success toast

**UI Elements:**
- "Read & Accept Rules" primary button (for users who haven't accepted)
- "View Rules" button (for users who have accepted)
- Flyout modal with full community rules text
- Accept button shown only if `!rules_accepted_at || isLevel(Drifter)`

### Stowaway Users Widget
**File:** `resources/views/livewire/dashboard/stowaway-users-widget.blade.php`
**Route:** `/` (dashboard widget, gated by `manage-stowaway-users`)

**Purpose:** Lists Stowaway users not in brig, allowing staff to review and promote to Traveler.

**Authorization:** `manage-stowaway-users` gate.

**User Actions Available:**
- View user details modal (shows MC accounts, Discord, join date)
- "Promote to Traveler" button -> calls `promoteToTraveler()` -> `PromoteUser::run($selectedUser, MembershipLevel::Traveler)` -> success banner
- "Put in Brig" button (see Brig System documentation)

**UI Elements:**
- Paginated table of stowaway users (10 per page)
- User detail modal with MC/Discord account info
- Success banner after promotion with link to promoted user's profile
- Empty state message when no stowaways exist

### Traveler Users Widget
**File:** `resources/views/livewire/dashboard/traveler-users-widget.blade.php`
**Route:** `/` (dashboard widget, gated by `manage-traveler-users`)

**Purpose:** Lists Traveler users not in brig, ordered by `promoted_at` ascending (oldest first), allowing staff to promote to Resident.

**Authorization:** `manage-traveler-users` gate.

**User Actions Available:**
- View user details modal (shows join date, promoted_at date)
- "Promote to Resident" button -> calls `promoteToResident()` -> `PromoteUser::run($selectedUser, MembershipLevel::Resident)` -> success banner

**UI Elements:**
- Paginated table with name and "Promoted" time (via `diffForHumans()`)
- User detail modal with key dates
- Success banner after promotion
- Empty state message

### User Profile - Promotion/Demotion
**File:** `resources/views/livewire/users/display-basic-details.blade.php`
**Route:** `/profile/{user}` (route name: `profile.show`)

**Purpose:** User profile page that includes promote/demote actions for authorized staff.

**Authorization:** `manage-stowaway-users` gate for promotion/demotion actions.

**User Actions Available:**
- "Promote to {next level}" menu item -> opens `profile-promote-confirm-modal` -> `promoteUser()` -> `PromoteUser::run($user)` -> success toast
- "Demote to {previous level}" menu item -> opens `profile-demote-confirm-modal` -> `demoteUser()` -> `DemoteUser::run($user)` -> success toast

**Computed Properties:**
- `nextMembershipLevel` — Returns next level if user is at least Stowaway, else null
- `previousMembershipLevel` — Returns previous level if user is above Traveler, else null (can't demote below Traveler)

**UI Elements:**
- Membership level label displayed: "Member Rank: {level}"
- Promote/demote menu items (hidden when user is in brig)
- Confirmation modals with current and target level names
- Promote button uses `arrow-up-circle` icon; demote uses `arrow-down-circle`

### Admin Manage Users Page
**File:** `resources/views/livewire/admin-manage-users-page.blade.php`

**Purpose:** Admin user list with membership level column (sortable).

**UI Elements:**
- "Level" column showing `membership_level->label()`
- Sortable by `membership_level`

---

## 8. Actions (Business Logic)

### PromoteUser (`app/Actions/PromoteUser.php`)

**Signature:** `handle(User $user, MembershipLevel $maxLevel = MembershipLevel::Citizen): void`

**Step-by-step logic:**
1. Gets current membership level
2. If current >= maxLevel, returns early (no-op)
3. Finds next level in the enum cases array
4. Updates `membership_level` to next level and `promoted_at` to now
5. Records activity: `RecordActivity::run($user, 'user_promoted', "Promoted from {old} to {new}.")`
6. Syncs Minecraft ranks: `SyncMinecraftRanks::run($user)`
7. Syncs Discord roles: `SyncDiscordRoles::run($user)` (caught exception logged as warning)
8. Sends level-specific notification:
   - **Stowaway:** `UserPromotedToStowawayNotification` sent to Quartermaster AND Command staff (category: `staff_alerts`)
   - **Traveler:** `UserPromotedToTravelerNotification` sent to the promoted user (category: `account`)
   - **Resident:** `UserPromotedToResidentNotification` sent to the promoted user (category: `account`)
   - **Citizen:** No notification sent

**Called by:**
- `resources/views/livewire/dashboard/view-rules.blade.php` (with maxLevel: Stowaway)
- `resources/views/livewire/dashboard/stowaway-users-widget.blade.php` (with maxLevel: Traveler)
- `resources/views/livewire/dashboard/traveler-users-widget.blade.php` (with maxLevel: Resident)
- `resources/views/livewire/users/display-basic-details.blade.php` (no maxLevel cap — default Citizen)

### DemoteUser (`app/Actions/DemoteUser.php`)

**Signature:** `handle(User $user, MembershipLevel $minLevel = MembershipLevel::Drifter): void`

**Step-by-step logic:**
1. Gets current membership level
2. If current <= minLevel, returns early (no-op)
3. Finds previous level in the enum cases array
4. Updates `membership_level` to previous level (does NOT update `promoted_at`)
5. Records activity: `RecordActivity::handle($user, 'user_demoted', "Demoted from {old} to {new}.")`
6. Syncs Minecraft ranks: `SyncMinecraftRanks::run($user)`
7. Syncs Discord roles: `SyncDiscordRoles::run($user)`
8. No notification sent on demotion

**Called by:**
- `resources/views/livewire/users/display-basic-details.blade.php` (profile page demote action)

### PromoteUserToAdmin (`app/Actions/PromoteUserToAdmin.php`)

**Signature:** `handle(User $user): bool`

**Step-by-step logic:**
1. If user already has Admin role, returns `true` (no-op)
2. Finds the Admin role by name
3. If Admin role doesn't exist, returns `false`
4. Attaches Admin role to user
5. Sets `promoted_at` to now
6. Records activity: `RecordActivity::run($user, 'user_promoted_to_admin', 'Promoted to Admin role.')`
7. Returns `true`

**Called by:**
- `app/Console/Commands/PromoteUserToAdmin.php` (Artisan command)

### SyncMinecraftRanks (`app/Actions/SyncMinecraftRanks.php`)

**Signature:** `handle(User $user): void`

**Step-by-step logic:**
1. Gets the Minecraft rank string from user's membership level
2. If rank is null (Drifter/Stowaway), returns early
3. Gets all active Minecraft accounts for the user
4. For each account: dispatches `SendMinecraftCommand` with `lh setmember {username} {rank}`
5. Records activity per account: `RecordActivity::handle($user, 'minecraft_rank_synced', "Synced Minecraft rank to {rank} for {username}")`

**Called by:**
- `app/Actions/PromoteUser.php`
- `app/Actions/DemoteUser.php`
- `app/Actions/ReleaseUserFromBrig.php` (on release)
- `app/Actions/CompleteVerification.php` (after MC account verification)

### SyncDiscordRoles (`app/Actions/SyncDiscordRoles.php`)

**Signature:** `handle(User $user): void`

**Step-by-step logic:**
1. Gets all active Discord accounts for the user
2. If none, returns early
3. Builds list of all managed role IDs (all membership level Discord roles + verified role)
4. Builds list of desired role IDs for this user (their level's Discord role + verified role)
5. For each Discord account: calls `DiscordApiService::syncManagedRoles()` to add/remove roles
6. Records activity: `RecordActivity::run($user, 'discord_roles_synced', "Synced Discord membership role to {level}")`

**Called by:**
- `app/Actions/PromoteUser.php`
- `app/Actions/DemoteUser.php`
- `app/Actions/ReleaseUserFromBrig.php` (on release)

---

## 9. Notifications

### UserPromotedToStowawayNotification (`app/Notifications/UserPromotedToStowawayNotification.php`)

**Triggered by:** `PromoteUser` action (when user promoted to Stowaway)
**Recipient:** Quartermaster AND Command staff members (NOT the promoted user)
**Channels:** mail, Pushover, Discord (via `TicketNotificationService`, category: `staff_alerts`)
**Mail subject:** "New Stowaway User: {name}"
**Mail template:** `resources/views/mail/user-promoted-stowaway.blade.php`
**Content summary:** Informs staff that a user agreed to rules and is awaiting review for Traveler promotion. Includes link to user's profile.
**Queued:** Yes

### UserPromotedToTravelerNotification (`app/Notifications/UserPromotedToTravelerNotification.php`)

**Triggered by:** `PromoteUser` action (when user promoted to Traveler)
**Recipient:** The promoted user
**Channels:** mail, Pushover, Discord (via `TicketNotificationService`, category: `account`)
**Mail subject:** "Welcome to Traveler Status!"
**Mail template:** `resources/views/mail/user-promoted-traveler.blade.php`
**Content summary:** Congratulates user, explains Traveler status means MC/Discord access, encourages participation, links to Minecraft settings page to link accounts.
**Queued:** Yes

### UserPromotedToResidentNotification (`app/Notifications/UserPromotedToResidentNotification.php`)

**Triggered by:** `PromoteUser` action (when user promoted to Resident)
**Recipient:** The promoted user
**Channels:** mail, Pushover, Discord (via `TicketNotificationService`, category: `account`)
**Mail subject:** "Welcome, Resident {name}!"
**Mail template:** `resources/views/mail/user-promoted-resident.blade.php`
**Content summary:** Thanks user for engagement, announces full server membership.
**Queued:** Yes

---

## 10. Background Jobs

Not applicable for this feature. Promotion operations are synchronous. Minecraft rank sync dispatches `SendMinecraftCommand` as a job, but that's part of the Minecraft integration system, not this feature specifically.

---

## 11. Console Commands & Scheduled Tasks

### `app:promote-user-to-admin`
**File:** `app/Console/Commands/PromoteUserToAdmin.php`
**Signature:** `app:promote-user-to-admin {email}`
**Scheduled:** No
**What it does:** Looks up a user by email address and promotes them to the Admin role via `PromoteUserToAdmin::run()`. Handles cases: user not found (error), already admin (info), admin role missing (error).

---

## 12. Services

### DiscordApiService (`app/Services/DiscordApiService.php`)
**Purpose:** Handles Discord API calls for role management.
**Key method (promotion-relevant):**
- `syncManagedRoles(string $discordUserId, array $managedRoleIds, array $desiredRoleIds): void` — Adds/removes Discord roles to match the user's current membership level.

### TicketNotificationService (`app/Services/TicketNotificationService.php`)
**Purpose:** Smart notification delivery (mail, Pushover, Discord) with category-based preferences.
**Promotion usage:** Sends all three promotion notifications through this service with appropriate categories (`staff_alerts` for stowaway staff notification, `account` for user notifications).

---

## 13. Activity Log Entries

| Action String | Logged By | Subject Model | Description |
|---------------|-----------|---------------|-------------|
| `rules_accepted` | View Rules component | User | "User accepted community rules and was promoted to Stowaway" |
| `user_promoted` | `PromoteUser` | User | "Promoted from {old level} to {new level}." |
| `user_demoted` | `DemoteUser` | User | "Demoted from {old level} to {new level}." |
| `user_promoted_to_admin` | `PromoteUserToAdmin` | User | "Promoted to Admin role." |
| `minecraft_rank_synced` | `SyncMinecraftRanks` | User | "Synced Minecraft rank to {rank} for {username}" |
| `discord_roles_synced` | `SyncDiscordRoles` | User | "Synced Discord membership role to {level}" |

---

## 14. Data Flow Diagrams

### Accepting Rules (Drifter -> Stowaway)

```
Drifter clicks "Read & Accept Rules" on dashboard
  -> View Rules flyout modal opens
  -> User reads rules
  -> User clicks "I Have Read the Rules and Agree to Follow Them"
  -> view-rules component: acceptRules()
    -> User.rules_accepted_at = now()
    -> RecordActivity::run('rules_accepted')
    -> PromoteUser::run($user, maxLevel: Stowaway)
      -> User.membership_level = Stowaway, promoted_at = now()
      -> RecordActivity::run('user_promoted')
      -> SyncMinecraftRanks::run() (no-op for Stowaway — no MC rank)
      -> SyncDiscordRoles::run() (no-op for Stowaway — no Discord role)
      -> UserPromotedToStowawayNotification sent to QM + Command staff
    -> Flux::modal close, Flux::toast success
    -> Redirect to dashboard
```

### Promoting Stowaway to Traveler (Staff Action)

```
Staff clicks stowaway user in Stowaway Users Widget
  -> User detail modal opens
  -> Staff clicks "Promote to Traveler"
  -> stowaway-users-widget: promoteToTraveler()
    -> $this->authorize('manage-stowaway-users')
    -> PromoteUser::run($selectedUser, maxLevel: Traveler)
      -> User.membership_level = Traveler, promoted_at = now()
      -> RecordActivity::run('user_promoted')
      -> SyncMinecraftRanks::run() -> sends `lh setmember {username} traveler`
      -> SyncDiscordRoles::run() -> adds Traveler + Verified Discord roles
      -> UserPromotedToTravelerNotification sent to promoted user
    -> Modal closes, success banner shown
```

### Promoting Traveler to Resident (Staff Action)

```
Staff clicks traveler user in Traveler Users Widget
  -> User detail modal opens
  -> Staff clicks "Promote to Resident"
  -> traveler-users-widget: promoteToResident()
    -> $this->authorize('manage-traveler-users')
    -> PromoteUser::run($selectedUser, maxLevel: Resident)
      -> User.membership_level = Resident, promoted_at = now()
      -> RecordActivity::run('user_promoted')
      -> SyncMinecraftRanks::run() -> sends `lh setmember {username} resident`
      -> SyncDiscordRoles::run() -> swaps Traveler role for Resident role
      -> UserPromotedToResidentNotification sent to promoted user
    -> Modal closes, success banner shown
```

### Promoting/Demoting from Profile Page (Staff Action)

```
Staff clicks "Promote to {next}" on user's profile dropdown
  -> profile-promote-confirm-modal opens
  -> Staff confirms promotion
  -> display-basic-details: promoteUser()
    -> Auth check: manage-stowaway-users gate
    -> Guard: user must be at least Stowaway
    -> PromoteUser::run($user) (no maxLevel cap — can promote up to Citizen)
    -> Modal closes, success toast

Staff clicks "Demote to {prev}" on user's profile dropdown
  -> profile-demote-confirm-modal opens
  -> Staff confirms demotion
  -> display-basic-details: demoteUser()
    -> Auth check: manage-stowaway-users gate
    -> DemoteUser::run($user)
    -> Modal closes, success toast
```

### Promoting to Admin (Console)

```
Admin runs: php artisan app:promote-user-to-admin user@email.com
  -> Looks up user by email
  -> PromoteUserToAdmin::run($user)
    -> Checks if user already has Admin role
    -> Attaches Admin role, sets promoted_at
    -> RecordActivity::run('user_promoted_to_admin')
  -> Console output: "User has been promoted to Admin."
```

---

## 15. Configuration

| Key | Default | Purpose |
|-----|---------|---------|
| `DISCORD_ROLE_TRAVELER` | (env) | Discord role ID for Traveler level |
| `DISCORD_ROLE_RESIDENT` | (env) | Discord role ID for Resident level |
| `DISCORD_ROLE_CITIZEN` | (env) | Discord role ID for Citizen level |
| `DISCORD_ROLE_VERIFIED` | (env) | Discord role ID for verified users (added alongside level role) |

Config path: `config/lighthouse.php` -> `discord.roles.traveler`, `.resident`, `.citizen`, `.verified`

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/Actions/Actions/DemoteUserTest.php` | 6 tests | DemoteUser action logic |
| `tests/Feature/Actions/Actions/PromoteUserToAdminTest.php` | 5 tests | Admin promotion action |
| `tests/Feature/Console/Commands/PromoteUserToAdminCommandTest.php` | 4 tests | Admin promotion Artisan command |
| `tests/Feature/Livewire/Dashboard/StowawayUsersWidgetTest.php` | 12 tests | Stowaway widget rendering and promotion |
| `tests/Feature/Livewire/Dashboard/TravelerUsersWidgetTest.php` | 12 tests | Traveler widget rendering and promotion |
| `tests/Feature/Discord/SyncDiscordRolesTest.php` | 4 tests | Discord role syncing |

### Test Case Inventory

#### `tests/Feature/Actions/Actions/DemoteUserTest.php`
- it('demotes a user one level down')
- it('does not demote below the minimum level')
- it('does not demote stowaway below default minimum level')
- it('respects a custom minimum level')
- it('records activity when demoting')
- it('does not record activity when at minimum level')

#### `tests/Feature/Actions/Actions/PromoteUserToAdminTest.php`
- it('returns true when user is already an admin')
- it('promotes user to admin when user is not admin and role exists')
- it('returns false when admin role does not exist')
- it('does not attach duplicate admin role when user is already admin')
- it('successfully promotes user when user has other roles but not admin')

#### `tests/Feature/Console/Commands/PromoteUserToAdminCommandTest.php`
- it('promotes a user to admin by email')
- it('shows error when user not found')
- it('shows info when user is already admin')
- it('shows error when admin role does not exist')

#### `tests/Feature/Livewire/Dashboard/StowawayUsersWidgetTest.php`
- it('can render')
- it('displays stowaway users in the table')
- it('shows empty state when no stowaway users exist')
- it('can open user details modal')
- it('can promote stowaway to traveler')
- it('prevents non-admin users from promoting')
- it('can close the modal')
- it('can be seen by officers')
- it('can be seen by crew members in the quartermaster department')
- it('cannot be viewed by non-officers')
- it('cannot be viewed by JrCrew')
- it('allows officers to promote stowaway users')

#### `tests/Feature/Livewire/Dashboard/TravelerUsersWidgetTest.php`
- it('can render')
- it('displays traveler users in the table')
- it('shows empty state when no traveler users exist')
- it('displays users sorted by promoted_at oldest first')
- it('can open user details modal')
- it('displays joined and promoted_at dates')
- it('handles null promoted_at gracefully')
- it('can promote traveler to resident')
- it('can close the modal')
- it('is visible to admins')
- it('is visible to officers')
- it('is not visible to regular users')

#### `tests/Feature/Discord/SyncDiscordRolesTest.php`
- it('adds the correct membership role')
- it('skips sync when user has no discord accounts')
- it('skips brigged accounts')
- it('records activity when syncing')

### Coverage Gaps

- No dedicated test for the `PromoteUser` action itself (only tested indirectly via widget/component tests)
- No test for the `view-rules` component `acceptRules()` method (Drifter -> Stowaway promotion path)
- No test for the profile page `promoteUser()` / `demoteUser()` Livewire methods
- No test for the `nextMembershipLevel` / `previousMembershipLevel` computed properties
- No test verifying promotion notifications are sent correctly (UserPromotedToTravelerNotification, etc.)
- No test for SyncMinecraftRanks action
- No test for promotion to Citizen level
- No test for DemoteUser's Discord role sync behavior

---

## 17. File Map

**Models:**
- `app/Models/User.php` (membership_level, promoted_at fields and methods)
- `app/Models/Role.php` (Admin role for PromoteUserToAdmin)

**Enums:**
- `app/Enums/MembershipLevel.php`

**Actions:**
- `app/Actions/PromoteUser.php`
- `app/Actions/DemoteUser.php`
- `app/Actions/PromoteUserToAdmin.php`
- `app/Actions/SyncMinecraftRanks.php`
- `app/Actions/SyncDiscordRoles.php`
- `app/Actions/RecordActivity.php` (used for logging)
- `app/Actions/SendMinecraftCommand.php` (dispatched by SyncMinecraftRanks)

**Policies:**
- `app/Policies/UserPolicy.php` (no promotion-specific methods)

**Gates:** `app/Providers/AuthServiceProvider.php` -- gates: `view-community-content`, `view-all-community-updates`, `manage-stowaway-users`, `manage-traveler-users`, `link-discord`, `link-minecraft-account`

**Notifications:**
- `app/Notifications/UserPromotedToStowawayNotification.php`
- `app/Notifications/UserPromotedToTravelerNotification.php`
- `app/Notifications/UserPromotedToResidentNotification.php`

**Jobs:** None dedicated.

**Services:**
- `app/Services/TicketNotificationService.php` (notification delivery)
- `app/Services/DiscordApiService.php` (role syncing)

**Controllers:**
- `app/Http/Controllers/UserController.php` (renders profile page)
- `app/Http/Controllers/DashboardController.php` (renders dashboard)

**Volt Components:**
- `resources/views/livewire/dashboard/view-rules.blade.php`
- `resources/views/livewire/dashboard/stowaway-users-widget.blade.php`
- `resources/views/livewire/dashboard/traveler-users-widget.blade.php`
- `resources/views/livewire/users/display-basic-details.blade.php`
- `resources/views/livewire/admin-manage-users-page.blade.php`

**Mail Templates:**
- `resources/views/mail/user-promoted-stowaway.blade.php`
- `resources/views/mail/user-promoted-traveler.blade.php`
- `resources/views/mail/user-promoted-resident.blade.php`

**Routes:**
- Dashboard: `GET /` (`dashboard`)
- Profile: `GET /profile/{user}` (`profile.show`)

**Migrations:**
- `database/migrations/2025_08_05_130249_update_users_add_membership_and_officer_roles.php`
- `database/migrations/2026_02_12_170856_add_promoted_at_to_users_table.php`

**Console Commands:**
- `app/Console/Commands/PromoteUserToAdmin.php`

**Tests:**
- `tests/Feature/Actions/Actions/DemoteUserTest.php`
- `tests/Feature/Actions/Actions/PromoteUserToAdminTest.php`
- `tests/Feature/Console/Commands/PromoteUserToAdminCommandTest.php`
- `tests/Feature/Livewire/Dashboard/StowawayUsersWidgetTest.php`
- `tests/Feature/Livewire/Dashboard/TravelerUsersWidgetTest.php`
- `tests/Feature/Discord/SyncDiscordRolesTest.php`

**Config:**
- `config/lighthouse.php` — `discord.roles.traveler`, `discord.roles.resident`, `discord.roles.citizen`, `discord.roles.verified`

**Other:**
- None

---

## 18. Known Issues & Improvement Opportunities

1. **No notification for Citizen promotion:** When a user is promoted to Citizen (the highest level), no notification is sent. This may be intentional if Citizen promotion is rare, but it creates an inconsistency with other levels.

2. **No notification on demotion:** The `DemoteUser` action does not send any notification to the user. Users may not realize they've been demoted unless they check their profile.

3. **Inconsistent activity logging methods:** `PromoteUser` uses `RecordActivity::run()` while `DemoteUser` uses `RecordActivity::handle()`. Both work (AsAction trait), but inconsistent calling convention.

4. **Profile page demotion floor is Traveler:** The `previousMembershipLevel` computed property returns `null` if user is at or below Traveler, preventing demotion below Traveler from the profile page. However, the `DemoteUser` action itself allows demotion all the way to Drifter by default. This is likely intentional (Drifters haven't accepted rules) but could be confusing.

5. **`manage-stowaway-users` and `manage-traveler-users` have identical logic:** Both gates have exactly the same authorization logic. They could potentially be consolidated into a single gate like `manage-user-promotions`, unless they're intentionally separate for future differentiation.

6. **No PromoteUser action test:** The core `PromoteUser` action has no dedicated test file. It's tested indirectly through widget tests but edge cases (e.g., promoting at maxLevel, notification dispatch, Discord sync failure handling) may not be covered.

7. **DemoteUser doesn't update `promoted_at`:** When demoting, the `promoted_at` timestamp retains the value from the last promotion. This could be misleading — e.g., a Resident demoted to Traveler would still show their Resident promotion date.

8. **Discord sync failure is silently logged in PromoteUser:** If `SyncDiscordRoles::run()` fails during promotion, it's caught and logged as a warning, but the promotion still succeeds. The user might be promoted without proper Discord roles until a manual sync is triggered.

9. **Cache usage in view-rules:** The `acceptRules()` method calls `Cache::forget('user:' . auth()->user()->id . ':is_stowaway')` but there's no corresponding `Cache::put()` visible for this key in the same file. This cache key may be stale or unused.

10. **No Citizen-to-Resident promotion notification for Traveler widget:** The Traveler widget limits promotion to `MembershipLevel::Resident` via the maxLevel parameter, which is correct. But the profile page has no maxLevel cap, meaning any authorized staff can promote a Traveler directly past Resident to Citizen. The PromoteUser action only promotes one level at a time regardless, so this isn't a bug, but the profile page would need multiple clicks.
