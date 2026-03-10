# Parent Portal -- Technical Documentation

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

The Parent Portal is a comprehensive parental controls system that allows parents to create, manage, and restrict child accounts on the Lighthouse Website. Parents can create accounts for their children, control access to the website, Minecraft server, and Discord, link/remove Minecraft accounts, view discipline reports, and eventually release children to adult status.

The feature is accessible to any adult user (18+) or any user who already has linked child accounts. Parents can manage their own children's accounts through the portal. Staff members at Officer rank or above can view any parent's portal in a read-only mode for support purposes via `/parent-portal/{user}`.

The system integrates deeply with the Brig system (parental brig types: `ParentalPending`, `ParentalDisabled`), Minecraft account management (whitelist add/remove, rank sync), Discord account management (role stripping/restoration), and the registration flow (auto-linking parent accounts when a parent registers with a matching email). Age-based restrictions are applied at account creation — children under 13 have Minecraft and Discord disabled by default.

Key terminology: **Parent** = an adult user who has created or been linked to child accounts. **Child** = a user whose account was created by a parent or who provided a parent email during registration. **ParentChildLink** = the pivot model connecting parent and child user records. **Release to adult** = dissolving parent-child links when a child reaches 17+ (manual) or 19+ (automatic via scheduled command).

---

## 2. Database Schema

### `parent_child_links` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint (PK) | No | auto | Primary key |
| `parent_user_id` | foreignId | No | — | FK to `users.id`, cascadeOnDelete |
| `child_user_id` | foreignId | No | — | FK to `users.id`, cascadeOnDelete |
| `created_at` | timestamp | Yes | — | |
| `updated_at` | timestamp | Yes | — | |

**Indexes:** Unique composite on `(parent_user_id, child_user_id)`
**Foreign Keys:** `parent_user_id` → `users.id` (cascade delete), `child_user_id` → `users.id` (cascade delete)
**Constraints:** CHECK constraint `parent_user_id <> child_user_id` (MySQL only; enforced at model level for SQLite)
**Migration:** `database/migrations/2026_02_28_100001_create_parent_child_links_table.php`

### `users` table (parent-related columns)

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `date_of_birth` | date | Yes | NULL | Used for age calculations |
| `parent_email` | string | Yes | NULL | Email of parent, indexed; used for auto-linking |
| `parent_allows_site` | boolean | No | true | Parent toggle for site access |
| `parent_allows_login` | boolean | No | true | Parent toggle for login ability |
| `parent_allows_minecraft` | boolean | No | true | Parent toggle for MC access |
| `parent_allows_discord` | boolean | No | true | Parent toggle for Discord access |

**Indexes:** Index on `parent_email`
**Migration(s):**
- `database/migrations/2026_02_28_100000_add_parental_fields_to_users_table.php` (date_of_birth, parent_email, parent_allows_site, parent_allows_minecraft, parent_allows_discord)
- `database/migrations/2026_03_04_145109_add_parent_allows_login_to_users_table.php` (parent_allows_login)

---

## 3. Models & Relationships

### ParentChildLink (`app/Models/ParentChildLink.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `parent()` | belongsTo | User | Via `parent_user_id` |
| `child()` | belongsTo | User | Via `child_user_id` |

**Scopes:** None

**Key Methods:** None (simple pivot model)

**Boot Logic:** `saving` event prevents self-referential links (`parent_user_id === child_user_id` throws `InvalidArgumentException`)

**Casts:** None

**Fillable:** `parent_user_id`, `child_user_id`

### User (`app/Models/User.php`) — Parent/Child aspects

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `children()` | belongsToMany | User | Via `parent_child_links`, `parent_user_id` → `child_user_id` |
| `parents()` | belongsToMany | User | Via `parent_child_links`, `child_user_id` → `parent_user_id` |

**Key Methods:**
- `isAdult(): bool` — Returns true if `date_of_birth` is null or age >= 18
- `isMinor(): bool` — Returns true if `date_of_birth` is set and age < 18

**Casts:**
- `date_of_birth` => `date`
- `parent_allows_site` => `boolean`
- `parent_allows_login` => `boolean`
- `parent_allows_minecraft` => `boolean`
- `parent_allows_discord` => `boolean`

---

## 4. Enums Reference

### BrigType (`app/Enums/BrigType.php`) — Parent-related cases only

| Case | Value | Label | Notes |
|------|-------|-------|-------|
| `ParentalPending` | `parental_pending` | Parental Pending | Child registered but parent hasn't approved |
| `ParentalDisabled` | `parental_disabled` | Parental Disabled | Parent actively disabled site access |

**Helper methods:**
- `isParental(): bool` — Returns true for `ParentalPending` and `ParentalDisabled`
- `isDisciplinary(): bool` — Returns true only for `Discipline`

### MinecraftAccountStatus (`app/Enums/MinecraftAccountStatus.php`) — Parent-related case

| Case | Value | Notes |
|------|-------|-------|
| `ParentDisabled` | `parent_disabled` | MC account disabled by parent toggle |

### DiscordAccountStatus (`app/Enums/DiscordAccountStatus.php`) — Parent-related case

| Case | Value | Notes |
|------|-------|-------|
| `ParentDisabled` | `parent_disabled` | Discord account disabled by parent toggle |

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

| Gate Name | Who Can Pass | Logic Summary |
|-----------|-------------|---------------|
| `view-parent-portal` | Adults (18+) or users with children | `$user->isAdult() \|\| $user->children()->exists()` |
| `link-minecraft-account` | Traveler+, not in brig, parent allows MC | `isAtLeastLevel(Traveler) && !in_brig && parent_allows_minecraft` |
| `link-discord` | Stowaway+, not in brig, parent allows Discord | `isAtLeastLevel(Stowaway) && !in_brig && parent_allows_discord` |

### Policies

#### ParentChildLinkPolicy (`app/Policies/ParentChildLinkPolicy.php`)

**`before()` hook:** None

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `manage` | Parent of the child | `$parent->children()->where('child_user_id', $child->id)->exists()` |

### Permissions Matrix

| User Type | View Portal | Create Child | Manage Permissions | Link MC | Remove MC | Release to Adult | View Staff Mode |
|-----------|:-----------:|:------------:|:-----------------:|:-------:|:---------:|:---------------:|:--------------:|
| Minor (no children) | No | No | No | No | No | No | No |
| Adult (no children) | Yes | Yes | — | — | — | — | No |
| Parent (with children) | Yes | Yes | Own children | Own children | Own children | Own 17+ children | No |
| Officer+ Staff | Yes | — | — | — | — | — | Yes (read-only) |

---

## 6. Routes

| Method | URL | Middleware | Handler | Route Name |
|--------|-----|-----------|---------|------------|
| GET | `/parent-portal` | auth, verified, ensure-dob | `Volt::route` → `parent-portal.index` | `parent-portal.index` |
| GET | `/parent-portal/{user}` | auth, verified, ensure-dob | `Volt::route` → `parent-portal.index` | `parent-portal.show` |

---

## 7. User Interface Components

### Parent Portal Index
**File:** `resources/views/livewire/parent-portal/index.blade.php`
**Route:** `/parent-portal` (route name: `parent-portal.index`) and `/parent-portal/{user}` (route name: `parent-portal.show`)

**Purpose:** Full parent portal with child account management. Dual-mode: parent mode (full management) and staff view mode (read-only for Officer+).

**Authorization:** `view-parent-portal` gate checked on mount. Staff view mode requires Officer+ rank and checks `$this->authorize('viewAny', Thread::class)` for ticket access.

**User Actions Available:**

1. **Create Child Account** — Opens `create-child-modal`. Validates name, email, date_of_birth (must be < 17). Calls `CreateChildAccount::run()`. Shows success toast.
2. **Edit Child Details** — Opens `edit-child-modal`. Can update name, email, date_of_birth.
3. **Toggle Permissions** — Toggle switches for `use_site`, `login`, `minecraft`, `discord`. Calls `UpdateChildPermission::run()` for each toggle. Immediate effect with toast feedback.
4. **Generate MC Verification Code** — For children with `parent_allows_minecraft` enabled. Generates a code via `LinkMinecraftAccount` action and displays it.
5. **Check MC Verification Status** — Checks if a pending MC account has been verified.
6. **Remove Child MC Account** — Opens `confirm-remove-mc-modal`. Calls `RemoveChildMinecraftAccount::run()`. Removes from whitelist, resets rank, marks as Removed.
7. **View Discipline Reports** — Opens `discipline-reports-modal` showing published reports for the child.
8. **Release to Adult** — Button appears for children aged 17+. Calls `ReleaseChildToAdult::run()`. Dissolves all parent-child links, resets toggles, releases parental brig.

**UI Elements:**
- Child cards showing name, age, email, membership level, brig status
- Permission toggle switches (site, login, Minecraft, Discord)
- Minecraft accounts section with verification code generation, status checking, and removal
- Modals: `create-child-modal`, `edit-child-modal`, `confirm-remove-mc-modal`, `discipline-reports-modal`
- Staff read-only banner when viewing another user's portal
- Open support tickets for each child (links to ticket view)
- Family card and profile links

---

## 8. Actions (Business Logic)

### CreateChildAccount (`app/Actions/CreateChildAccount.php`)

**Signature:** `handle(User $parent, string $name, string $email, string $dateOfBirth): User`

**Step-by-step logic:**
1. Calculates if child is under 13 from `$dateOfBirth`
2. In a DB transaction:
   - Creates `User` with random password, `parent_email` = parent's email, `parent_allows_site` = true, MC/Discord = `!$isUnder13`
   - Creates `ParentChildLink` connecting parent and child
3. Records activity: `RecordActivity::run($child, 'child_account_created', "Account created by parent {$parent->name}.")`
4. Sends notification: `ChildWelcomeNotification` to child via `TicketNotificationService`

**Called by:** `resources/views/livewire/parent-portal/index.blade.php`

### UpdateChildPermission (`app/Actions/UpdateChildPermission.php`)

**Signature:** `handle(User $child, User $parent, string $permission, bool $enabled): void`

**Step-by-step logic:**
Uses `match` on `$permission` to dispatch to private methods:

- **`use_site`**: Sets `parent_allows_site`. If disabling and not already in brig → `PutUserInBrig::run()` with `BrigType::ParentalDisabled`, sends `ParentAccountDisabledNotification`. If enabling and in parental brig → `ReleaseUserFromBrig::run()`, sends `ParentAccountEnabledNotification`. Records activity.
- **`login`**: Sets `parent_allows_login`. If disabling, deletes all active sessions from `sessions` table. Records activity.
- **`minecraft`**: Sets `parent_allows_minecraft`. If disabling, whitelist-removes all Active/Verifying MC accounts and sets status to `ParentDisabled`. If enabling, whitelist-adds all `ParentDisabled` accounts back and restores to `Active`, then syncs ranks. Records activity.
- **`discord`**: Sets `parent_allows_discord`. If disabling, strips all managed Discord roles and sets accounts to `ParentDisabled`. If enabling, restores accounts to `Active` and syncs roles. Records activity.

**Called by:** `resources/views/livewire/parent-portal/index.blade.php`

### AutoLinkParentOnRegistration (`app/Actions/AutoLinkParentOnRegistration.php`)

**Signature:** `handle(User $newUser): void`

**Step-by-step logic:**
1. Case-insensitive search for users where `parent_email` matches new user's email
2. If no matches, returns early
3. Uses `syncWithoutDetaching` to link all matching children to the new parent
4. Records activity for each child: `RecordActivity::run($child, 'parent_linked', "Parent account ({$newUser->email}) automatically linked.")`

**Called by:** `resources/views/livewire/auth/register.blade.php` (registration flow)

### ReleaseChildToAdult (`app/Actions/ReleaseChildToAdult.php`)

**Signature:** `handle(User $child, ?User $releasedBy = null): void`

**Step-by-step logic:**
1. Deletes all `ParentChildLink` records for the child
2. Resets parental toggles: `parent_allows_site` = true, `parent_allows_minecraft` = true, `parent_allows_discord` = true, `parent_email` = null
3. If child is in parental brig → `ReleaseUserFromBrig::run()` with message "Released to adult account."
4. Records activity: `RecordActivity::run($child, 'child_released_to_adult', description)` with context-appropriate message (manual vs. automatic)

**Called by:**
- `resources/views/livewire/parent-portal/index.blade.php` (manual, parent-initiated for 17+)
- `app/Console/Commands/ProcessAgeTransitions.php` (automatic, for 19+)

### RemoveChildMinecraftAccount (`app/Actions/RemoveChildMinecraftAccount.php`)

**Signature:** `handle(User $parent, int $accountId): array{success: bool, message: string}`

**Step-by-step logic:**
1. Finds MinecraftAccount by ID
2. Validates parent owns the child: `$parent->children()->where('child_user_id', $child->id)->exists()`
3. Validates account is Active status
4. Removes from whitelist via RCON — if fails, returns error without further changes
5. Resets rank to `default` via RCON
6. Sets account status to `Removed`
7. If account was primary, unsets primary and runs `AutoAssignPrimaryAccount::run($child)`
8. Records activity: `RecordActivity::run($child, 'minecraft_account_removed_by_parent', description)`

**Called by:** `resources/views/livewire/parent-portal/index.blade.php`

---

## 9. Notifications

### ChildWelcomeNotification (`app/Notifications/ChildWelcomeNotification.php`)

**Triggered by:** `CreateChildAccount` action
**Recipient:** The newly created child account
**Channels:** mail, Pushover (via `TicketNotificationService`)
**Mail subject:** "Welcome to Lighthouse!"
**Content summary:** Informs child their parent created an account, provides password reset link
**Queued:** Yes

### ParentAccountNotification (`app/Notifications/ParentAccountNotification.php`)

**Triggered by:** Registration flow (when a minor registers and provides parent email)
**Recipient:** Parent email address (on-demand notification, no User required)
**Channels:** mail only
**Mail subject:** "Your Child Has Created a Lighthouse Account"
**Content summary:** Informs parent their child created an account, includes whether approval is required (under 13), provides registration link
**Queued:** Yes
**Note:** Approved exception to `TicketNotificationService` guideline — uses `Notification::route('mail', $email)` because parent User may not exist yet

### ParentAccountDisabledNotification (`app/Notifications/ParentAccountDisabledNotification.php`)

**Triggered by:** `UpdateChildPermission` action (when `use_site` is disabled)
**Recipient:** The child account
**Channels:** mail, Pushover (via `TicketNotificationService`)
**Mail subject:** "Your Account Has Been Restricted"
**Content summary:** Informs child their parent has restricted their access
**Queued:** Yes

### ParentAccountEnabledNotification (`app/Notifications/ParentAccountEnabledNotification.php`)

**Triggered by:** `UpdateChildPermission` action (when `use_site` is enabled)
**Recipient:** The child account
**Channels:** mail, Pushover (via `TicketNotificationService`)
**Mail subject:** "Your Account Has Been Enabled"
**Content summary:** Informs child their parent has re-enabled their account, provides dashboard link
**Queued:** Yes

---

## 10. Background Jobs

Not applicable for this feature. All operations are synchronous (RCON calls, database mutations) handled within Action classes.

---

## 11. Console Commands & Scheduled Tasks

### `parent-portal:process-age-transitions`
**File:** `app/Console/Commands/ProcessAgeTransitions.php`
**Scheduled:** Yes — daily at 02:00 (in `routes/console.php`)
**What it does:** Finds users turning 13 (releases from `AgeLock` brig) and users turning 19 (calls `ReleaseChildToAdult::run()` for automatic release to adult status). Also handles the transition at age 13 for enabling MC/Discord defaults.

---

## 12. Services

### TicketNotificationService (`app/Services/TicketNotificationService.php`)
**Purpose:** Smart notification delivery that determines which channels (mail, Pushover) to use based on user preferences.
**Used by:** `CreateChildAccount`, `UpdateChildPermission` (for child notifications)

### MinecraftRconService (`app/Services/MinecraftRconService.php`)
**Purpose:** Executes RCON commands against the Minecraft server.
**Used by:** `RemoveChildMinecraftAccount` (whitelist remove, rank reset)

### DiscordApiService (`app/Services/DiscordApiService.php`)
**Purpose:** Manages Discord API interactions including role management.
**Used by:** `UpdateChildPermission` (strip/restore Discord roles)

---

## 13. Activity Log Entries

| Action String | Logged By | Subject Model | Description |
|---------------|-----------|---------------|-------------|
| `child_account_created` | CreateChildAccount | User (child) | "Account created by parent {parent name}." |
| `parent_permission_changed` | UpdateChildPermission | User (child) | "Site access enabled/disabled by parent {name}.", "Website login enabled/disabled by parent {name}.", "Minecraft access enabled/disabled by parent {name}.", "Discord access enabled/disabled by parent {name}." |
| `parent_linked` | AutoLinkParentOnRegistration | User (child) | "Parent account ({email}) automatically linked." |
| `child_released_to_adult` | ReleaseChildToAdult | User (child) | "Released to adult account by {name}." or "Automatically released to adult account (age 19+)." |
| `minecraft_account_removed_by_parent` | RemoveChildMinecraftAccount | User (child) | "{parent name} removed {account_type} account: {username}" |

---

## 14. Data Flow Diagrams

### Creating a Child Account

```
Parent clicks "Add Child" on Parent Portal
  -> Opens create-child-modal
  -> Parent fills in name, email, date_of_birth
  -> Parent submits form
    -> Volt::createChild()
      -> Validates: name required, email unique, date_of_birth < 17 years old
      -> CreateChildAccount::run($parent, $name, $email, $dob)
        -> Calculates isUnder13
        -> DB transaction:
          -> User::create([name, email, random password, parent_email, parent_allows_*])
          -> ParentChildLink::create([parent_user_id, child_user_id])
        -> RecordActivity::run($child, 'child_account_created', ...)
        -> TicketNotificationService->send($child, ChildWelcomeNotification)
      -> Flux::toast('Child account created!', variant: 'success')
      -> Modal closes, child list refreshes
```

### Toggling a Permission (e.g., Minecraft)

```
Parent toggles "Minecraft" switch for a child
  -> Volt::togglePermission($childId, 'minecraft', $enabled)
    -> $this->authorize('manage', $child) via ParentChildLinkPolicy
    -> UpdateChildPermission::run($child, $parent, 'minecraft', $enabled)
      -> If disabling:
        -> Sets parent_allows_minecraft = false
        -> For each Active/Verifying MC account:
          -> SendMinecraftCommand::run(whitelist remove)
          -> Sets status = ParentDisabled
        -> RecordActivity::run(...)
      -> If enabling:
        -> Sets parent_allows_minecraft = true
        -> For each ParentDisabled MC account:
          -> SendMinecraftCommand::run(whitelist add)
          -> Sets status = Active
        -> SyncMinecraftRanks::run($child)
        -> RecordActivity::run(...)
    -> Flux::toast('Permission updated', variant: 'success')
```

### Releasing Child to Adult

```
Parent clicks "Release to Adult" for a 17+ child
  -> Volt::releaseToAdult($childId)
    -> Validates child is 17+
    -> ReleaseChildToAdult::run($child, $parent)
      -> ParentChildLink::where(child_user_id)->delete()
      -> Resets parent_allows_* to true, parent_email to null
      -> If in parental brig → ReleaseUserFromBrig::run()
      -> RecordActivity::run($child, 'child_released_to_adult', ...)
    -> Flux::toast('Child released to adult account', variant: 'success')
    -> Child removed from parent's list
```

### Auto-Linking Parent on Registration

```
New user registers with email matching a child's parent_email
  -> Registration form submits
    -> User created
    -> AutoLinkParentOnRegistration::run($newUser)
      -> Queries users WHERE LOWER(parent_email) = LOWER($newUser->email)
      -> If matches found:
        -> $newUser->children()->syncWithoutDetaching($childIds)
        -> For each child: RecordActivity::run($child, 'parent_linked', ...)
```

### Removing a Child's Minecraft Account

```
Parent clicks remove on child's MC account
  -> Opens confirm-remove-mc-modal
  -> Parent confirms
    -> Volt::removeMinecraftAccount($accountId)
      -> RemoveChildMinecraftAccount::run($parent, $accountId)
        -> Validates parent owns child
        -> Validates account is Active
        -> RCON: whitelist remove → if fails, returns error
        -> RCON: rank reset to default
        -> Sets status = Removed
        -> If primary, reassigns primary via AutoAssignPrimaryAccount
        -> RecordActivity::run(...)
      -> Flux::toast(result message)
```

---

## 15. Configuration

| Key | Default | Purpose |
|-----|---------|---------|
| `session.driver` | `database` | Used by `UpdateChildPermission` to invalidate child sessions when login is disabled (only works with `database` driver) |

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/Actions/Actions/CreateChildAccountTest.php` | 5 | Child creation, age-based defaults, notifications, activity |
| `tests/Feature/Actions/Actions/UpdateChildPermissionTest.php` | 9 | All 4 permission toggles, brig integration, MC/Discord status changes, activity |
| `tests/Feature/Actions/Actions/ReleaseChildToAdultTest.php` | 4 | Link dissolution, toggle reset, parental brig release, disciplinary brig preserved |
| `tests/Feature/Actions/Actions/RemoveChildMinecraftAccountTest.php` | 5 | Account removal, authorization, status validation, RCON failure, activity |
| `tests/Feature/Actions/Actions/AutoLinkParentOnRegistrationTest.php` | 4 | Email matching, duplicate prevention, multi-child linking, no-match handling |
| `tests/Feature/Policies/ParentChildLinkPolicyTest.php` | 3 | Parent can manage own child, non-parent denied, wrong child denied |
| `tests/Feature/Gates/ParentPortalGatesTest.php` | 7 | Portal access (adult, parent, minor), MC/Discord linking gates with parental toggles |
| `tests/Feature/Livewire/ParentPortalPhase3Test.php` | 6 | Profile links, ticket links, ticket policy, MC removal authorization |
| `tests/Feature/Livewire/ParentPortalEnhancementsTest.php` | 6 | Age validation on creation, under-13 defaults, MC code generation authorization |
| `tests/Feature/Notifications/ParentAccountNotificationTest.php` | 4 | Mail channel, approval data for under-13, non-approval for 13+, on-demand sending |
| `tests/Feature/Livewire/ProfileFamilyDisplayTest.php` | 8 | Child badge, family card display, parent portal link for staff, action dropdown |
| `tests/Feature/Livewire/InBrigCardParentalTest.php` | 4 | Parental pending/disabled messages, age lock, appeal button restriction |
| `tests/Feature/Actions/Actions/PutUserInBrigWithParentTest.php` | 4 | Brig type setting, ParentDisabled MC conversion, discipline default, skip notification |
| `tests/Feature/Actions/Actions/ReleaseUserFromBrigWithParentTest.php` | 4 | Re-brig on parent disabled, MC status restoration, full release |
| `tests/Feature/Auth/RegistrationWithAgeTest.php` | — | Age-based registration flow with parent email |
| `tests/Feature/Auth/CollectBirthdateTest.php` | — | Birthdate collection flow for existing users |
| `tests/Feature/Console/Commands/ProcessAgeTransitionsTest.php` | — | Scheduled age-based transitions (13, 19) |
| `tests/Unit/Notifications/ChildWelcomeNotificationTest.php` | — | ChildWelcomeNotification unit tests |
| `tests/Unit/Notifications/ParentAccountDisabledNotificationTest.php` | — | ParentAccountDisabledNotification unit tests |
| `tests/Unit/Notifications/ParentAccountEnabledNotificationTest.php` | — | ParentAccountEnabledNotification unit tests |

### Test Case Inventory

**CreateChildAccountTest.php:**
- `it('creates a child user account linked to parent')`
- `it('sets restrictive defaults for under-13 child')`
- `it('sets permissive defaults for 13+ child')`
- `it('sends a welcome notification to the child')`
- `it('records activity for child account creation')`

**UpdateChildPermissionTest.php:**
- `it('disables site access and puts child in parental brig')`
- `it('enables site access and releases child from parental brig')`
- `it('does not brig child for site disable if already in brig')`
- `it('disables minecraft and sets active accounts to ParentDisabled')`
- `it('enables minecraft and restores ParentDisabled accounts to Active')`
- `it('disables discord and sets active accounts to ParentDisabled')`
- `it('enables discord and restores ParentDisabled accounts to Active')`
- `it('throws exception for unknown permission type')`
- `it('records activity when permission is changed')`

**ReleaseChildToAdultTest.php:**
- `it('dissolves parent-child links')`
- `it('resets parental toggles to defaults')`
- `it('releases from parental brig')`
- `it('does not release from disciplinary brig')`

**RemoveChildMinecraftAccountTest.php:**
- `it('removes an active minecraft account')`
- `it('rejects removal by non-parent')`
- `it('rejects removal of non-active account')`
- `it('fails gracefully when whitelist removal fails')`
- `it('records activity after removal')`

**AutoLinkParentOnRegistrationTest.php:**
- `it('links parent to child when emails match')`
- `it('does not create duplicate links')`
- `it('links parent to multiple children with same parent_email')`
- `it('does nothing when no children have matching parent_email')`

**ParentChildLinkPolicyTest.php:**
- `it('allows parent to manage their child')`
- `it('denies non-parent from managing a child')`
- `it('denies parent from managing a different child')`

**ParentPortalGatesTest.php:**
- `it('allows adult to view parent portal')`
- `it('allows user with children to view parent portal')`
- `it('denies minor without children')`
- `it('blocks MC linking when parent_allows_minecraft is false')`
- `it('allows MC linking when parent_allows_minecraft is true and not in brig')`
- `it('blocks discord linking when parent_allows_discord is false')`
- `it('allows discord linking when parent_allows_discord is true and not in brig')`

**ParentPortalPhase3Test.php:**
- `it('links child name to profile page')`
- `it('links ticket subjects to ticket view page')`
- `it('allows parent to view child ticket via policy')`
- `it('blocks parent from viewing non-child ticket')`
- `it('blocks parent from viewing staff threads involving child')`
- `it('blocks MC removal for non-child account')`

**ParentPortalEnhancementsTest.php:**
- `it('rejects child creation when age is 17+')`
- `it('allows child creation when age is 16')`
- `it('sets MC/Discord to false for under-13 child')`
- `it('sets MC/Discord to true for 13+ child')`
- `it('rejects MC code generation when parent_allows_minecraft is false')`
- `it('rejects MC code generation for non-child user')`

**ProfileFamilyDisplayTest.php:**
- `it('shows Child Account badge for user with parents')`
- `it('does not show Child Account badge for user without parents')`
- `it('shows Family card for user with parents')`
- `it('shows Family card for user with children')`
- `it('does not show Family card for user with no family links')`
- `it('shows Parent Portal link in Family card for staff officers viewing parent profile')`
- `it('hides Parent Portal link from non-staff')`
- `it('shows action dropdown for staff on profile')`

**InBrigCardParentalTest.php:**
- `it('shows parental pending message with contact staff button')`
- `it('shows parental disabled message with contact staff button')`
- `it('shows age lock message')`
- `it('shows appeal button only for disciplinary brig')`

**PutUserInBrigWithParentTest.php:**
- `it('sets brig_type on user')`
- `it('changes ParentDisabled MC accounts to Banned when brigged')`
- `it('defaults to Discipline brig_type')`
- `it('skips notification when notify is false')`

**ReleaseUserFromBrigWithParentTest.php:**
- `it('re-brigs with ParentalDisabled when parent_allows_site is false')`
- `it('restores MC to ParentDisabled when parent has MC disabled')`
- `it('restores MC to Active when parent has MC enabled')`
- `it('fully releases when parent_allows_site is true')`

### Coverage Gaps

- No tests for the `login` permission toggle (session invalidation behavior)
- No explicit test for editing child details (name/email/dob) via the portal
- No test for the staff read-only view mode (`/parent-portal/{user}`)
- No test for MC verification code generation success path in the portal component
- No test for the discipline reports modal display
- `parent_allows_login` is not reset in `ReleaseChildToAdult` — possible oversight (only resets site, MC, Discord)

---

## 17. File Map

**Models:**
- `app/Models/ParentChildLink.php`
- `app/Models/User.php` (parent/child relationships and parental fields)

**Enums:**
- `app/Enums/BrigType.php` (ParentalPending, ParentalDisabled cases)
- `app/Enums/MinecraftAccountStatus.php` (ParentDisabled case)
- `app/Enums/DiscordAccountStatus.php` (ParentDisabled case)

**Actions:**
- `app/Actions/CreateChildAccount.php`
- `app/Actions/UpdateChildPermission.php`
- `app/Actions/AutoLinkParentOnRegistration.php`
- `app/Actions/ReleaseChildToAdult.php`
- `app/Actions/RemoveChildMinecraftAccount.php`

**Policies:**
- `app/Policies/ParentChildLinkPolicy.php`

**Gates:** `AuthServiceProvider.php` — gates: `view-parent-portal`, `link-minecraft-account` (partial), `link-discord` (partial)

**Notifications:**
- `app/Notifications/ChildWelcomeNotification.php`
- `app/Notifications/ParentAccountNotification.php`
- `app/Notifications/ParentAccountDisabledNotification.php`
- `app/Notifications/ParentAccountEnabledNotification.php`

**Jobs:** None

**Services:**
- `app/Services/TicketNotificationService.php` (notification delivery)
- `app/Services/MinecraftRconService.php` (RCON commands)
- `app/Services/DiscordApiService.php` (Discord role management)

**Controllers:** None (all Volt components)

**Volt Components:**
- `resources/views/livewire/parent-portal/index.blade.php`

**Related Views:**
- `resources/views/livewire/auth/register.blade.php` (AutoLinkParentOnRegistration, ParentAccountNotification)
- `resources/views/livewire/auth/collect-birthdate.blade.php` (age-based flows)
- `resources/views/livewire/users/display-basic-details.blade.php` (family display, parent_email)
- `resources/views/livewire/settings/minecraft-accounts.blade.php` (parent_allows_minecraft check)
- `resources/views/livewire/settings/discord-account.blade.php` (parent_allows_discord check)
- `resources/views/livewire/dashboard/stowaway-users-widget.blade.php` (parent_email display)
- `resources/views/livewire/admin-manage-users-page.blade.php` (parent_email display)
- `resources/views/components/layouts/app/sidebar.blade.php` (nav link gated by `view-parent-portal`)

**Routes:**
- `parent-portal.index` → `GET /parent-portal`
- `parent-portal.show` → `GET /parent-portal/{user}`

**Migrations:**
- `database/migrations/2026_02_28_100000_add_parental_fields_to_users_table.php`
- `database/migrations/2026_02_28_100001_create_parent_child_links_table.php`
- `database/migrations/2026_03_04_145109_add_parent_allows_login_to_users_table.php`
- `database/migrations/2026_03_01_000002_add_parent_disabled_status_to_minecraft_accounts_table.php`

**Console Commands:**
- `app/Console/Commands/ProcessAgeTransitions.php`

**Middleware:**
- `app/Http/Middleware/EnsureParentAllowsLogin.php`

**Factories:**
- `database/factories/ParentChildLinkFactory.php`

**Mail Templates:**
- `resources/views/mail/child-welcome.blade.php`
- `resources/views/mail/parent-account.blade.php`
- `resources/views/mail/parent-account-disabled.blade.php`
- `resources/views/mail/parent-account-enabled.blade.php`

**Tests:**
- `tests/Feature/Actions/Actions/CreateChildAccountTest.php`
- `tests/Feature/Actions/Actions/UpdateChildPermissionTest.php`
- `tests/Feature/Actions/Actions/ReleaseChildToAdultTest.php`
- `tests/Feature/Actions/Actions/RemoveChildMinecraftAccountTest.php`
- `tests/Feature/Actions/Actions/AutoLinkParentOnRegistrationTest.php`
- `tests/Feature/Actions/Actions/PutUserInBrigWithParentTest.php`
- `tests/Feature/Actions/Actions/ReleaseUserFromBrigWithParentTest.php`
- `tests/Feature/Policies/ParentChildLinkPolicyTest.php`
- `tests/Feature/Gates/ParentPortalGatesTest.php`
- `tests/Feature/Notifications/ParentAccountNotificationTest.php`
- `tests/Feature/Livewire/ParentPortalPhase3Test.php`
- `tests/Feature/Livewire/ParentPortalEnhancementsTest.php`
- `tests/Feature/Livewire/ProfileFamilyDisplayTest.php`
- `tests/Feature/Livewire/InBrigCardParentalTest.php`
- `tests/Feature/Auth/RegistrationWithAgeTest.php`
- `tests/Feature/Auth/CollectBirthdateTest.php`
- `tests/Feature/Console/Commands/ProcessAgeTransitionsTest.php`
- `tests/Unit/Notifications/ChildWelcomeNotificationTest.php`
- `tests/Unit/Notifications/ParentAccountDisabledNotificationTest.php`
- `tests/Unit/Notifications/ParentAccountEnabledNotificationTest.php`

**Config:**
- `config/session.php` (`driver` — used for session invalidation on login disable)

---

## 18. Known Issues & Improvement Opportunities

1. **`parent_allows_login` not reset in `ReleaseChildToAdult`**: The action resets `parent_allows_site`, `parent_allows_minecraft`, `parent_allows_discord`, and `parent_email` — but does NOT reset `parent_allows_login` to `true`. If a parent had disabled login before the child was released, the child would remain locked out of logging in even after release to adult status.

2. **No test coverage for login toggle**: The `UpdateChildPermission` action's `toggleLogin` method (which invalidates sessions) has no dedicated test coverage. The session table deletion logic is only active when `config('session.driver') === 'database'`.

3. **No test for staff read-only view**: The `/parent-portal/{user}` route allowing Officer+ staff to view another user's parent portal has no test coverage.

4. **No test for edit child details**: The edit child modal (update name/email/dob) in the Volt component lacks test coverage.

5. **RCON failure handling inconsistency**: In `RemoveChildMinecraftAccount`, a whitelist removal failure halts the operation entirely. In `UpdateChildPermission::toggleMinecraft`, failures are caught and logged but processing continues to the next account. Consider consistent error handling.

6. **Potential N+1 query in permission toggles**: `UpdateChildPermission` loads MC/Discord accounts with `->get()` in a loop that makes individual RCON calls. While the number of accounts per user is typically small, eager loading and batching could improve resilience.

7. **Missing MC code generation authorization test**: The portal allows parents to generate Minecraft verification codes for children, but the success path (valid parent, valid child, MC enabled) lacks a dedicated test.

8. **Discipline reports modal**: Viewing discipline reports in the parent portal is implemented but has no test coverage for the modal display or data loading.
