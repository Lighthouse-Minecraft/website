# Brig System -- Technical Documentation

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

The Brig System is the discipline and access-restriction mechanism for the Lighthouse community. When a user is "put in the brig," their Minecraft accounts are banned (whitelist removed), their Discord roles are stripped, and they lose access to most community features. The system serves four distinct purposes represented by different brig types: disciplinary action by staff, pending parental approval for minors, parental account restriction, and age verification lockout.

The brig is enforced via authorization gates — the `view-community-content`, `link-discord`, and `link-minecraft-account` gates all check `!$user->in_brig`, which means brigged users are systematically blocked from community features without scattered conditional checks in views. When a brigged user logs in, the dashboard replaces normal content with a brig card showing their status, reason, and an appeal mechanism.

Staff members with the `manage-stowaway-users` gate (Admins, Officers, and Quartermaster CrewMembers+) can put users in and release users from the brig via the user profile page or the stowaway users dashboard widget. Users can submit appeals which create Quartermaster tickets, with a 7-day cooldown between appeals. A scheduled command (`brig:check-timers`) runs daily to notify users when their brig timer expires and they become eligible to appeal.

The brig system integrates deeply with the Parent Portal — under-13 registrations are automatically placed in `ParentalPending` brig, parents can disable children's accounts (putting them in `ParentalDisabled` brig), and releasing from disciplinary brig checks whether parental restrictions should re-engage. The `ProcessAgeTransitions` command handles automatic releases when children turn 13 (no parent linked) or 19 (adult transition).

---

## 2. Database Schema

### `users` table (brig-related columns)

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `in_brig` | boolean | No | `false` | Master flag for brig status |
| `brig_reason` | text | Yes | `null` | Human-readable reason for brig placement |
| `brig_expires_at` | timestamp | Yes | `null` | When the brig timer expires (not auto-release — just notification trigger) |
| `next_appeal_available_at` | timestamp | Yes | `null` | When the user can next submit an appeal |
| `brig_timer_notified` | boolean | No | `false` | Whether user has been notified of timer expiry |
| `brig_type` | string(30) | Yes | `null` | BrigType enum value: discipline, parental_pending, parental_disabled, age_lock |

**Indexes:** None specific to brig columns.
**Foreign Keys:** None.
**Migration(s):**
- `database/migrations/2026_02_20_080000_add_brig_fields_to_users_table.php` — adds `in_brig`, `brig_reason`, `brig_expires_at`, `brig_timer_notified`
- `database/migrations/2026_02_21_034025_add_next_appeal_available_at_to_users_table.php` — adds `next_appeal_available_at`
- `database/migrations/2026_02_28_100000_add_parental_fields_to_users_table.php` — adds `brig_type` (along with parental fields)

---

## 3. Models & Relationships

### User (`app/Models/User.php`)

The User model contains all brig-related fields and methods. There is no separate Brig model.

**Fillable brig fields:** `in_brig`, `brig_reason`, `brig_expires_at`, `next_appeal_available_at`, `brig_timer_notified`, `brig_type`

**Casts:**
- `in_brig` => `boolean`
- `brig_expires_at` => `datetime`
- `next_appeal_available_at` => `datetime`
- `brig_timer_notified` => `boolean`
- `brig_type` => `BrigType::class`

**Key Methods:**
- `isInBrig(): bool` — Returns `(bool) $this->in_brig`
- `brigTimerExpired(): bool` — Returns `true` if `brig_expires_at` is null or now >= `brig_expires_at`
- `canAppeal(): bool` — Returns `true` if user is in brig AND (`next_appeal_available_at` is null OR <= now())
- `isMinor(): bool` — Returns `true` if `date_of_birth` is set and age < 18

**Related Relationships (used by brig logic):**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `minecraftAccounts()` | hasMany | MinecraftAccount | Banned/restored during brig actions |
| `discordAccounts()` | hasMany | DiscordAccount | Brigged/restored during brig actions |
| `parents()` | belongsToMany | User | Used to check parental hold on release |
| `children()` | belongsToMany | User | Parent portal brig management |

---

## 4. Enums Reference

### BrigType (`app/Enums/BrigType.php`)

| Case | Value | Label | Notes |
|------|-------|-------|-------|
| `Discipline` | `'discipline'` | `Disciplinary` | Standard staff-imposed brig |
| `ParentalPending` | `'parental_pending'` | `Pending Parental Approval` | Under-13 awaiting parent registration |
| `ParentalDisabled` | `'parental_disabled'` | `Restricted by Parent` | Parent disabled child's access |
| `AgeLock` | `'age_lock'` | `Age Verification Required` | DOB not set, age verification needed |

**Helper Methods:**
- `isDisciplinary(): bool` — Returns `true` only for `Discipline`
- `isParental(): bool` — Returns `true` for `ParentalPending` and `ParentalDisabled`
- `label(): string` — Human-readable label for each case

### MinecraftAccountStatus (brig-relevant cases)

| Case | Value | Notes |
|------|-------|-------|
| `Active` | `'active'` | Normal status; banned on brig entry |
| `Verifying` | `'verifying'` | Also banned on brig entry |
| `Banned` | `'banned'` | Set when user is brigged; restored on release |
| `ParentDisabled` | `'parent_disabled'` | Also banned on brig entry; may restore to this on release |

### DiscordAccountStatus (brig-relevant cases)

| Case | Value | Notes |
|------|-------|-------|
| `Active` | `'active'` | Normal status; brigged on brig entry |
| `Brigged` | `'brigged'` | Set when user is brigged; restored on release |
| `ParentDisabled` | `'parent_disabled'` | Also brigged on brig entry; may restore to this on release |

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

| Gate Name | Who Can Pass | Logic Summary |
|-----------|-------------|---------------|
| `view-community-content` | Non-brigged users | `! $user->in_brig` |
| `manage-stowaway-users` | Admin, Officers, Quartermaster CrewMember+ | Controls who can put users in / release from brig |
| `link-discord` | Stowaway+ AND not in brig AND parent allows | `$user->isAtLeastLevel(Stowaway) && !$user->in_brig && $user->parent_allows_discord` |
| `link-minecraft-account` | Stowaway+ AND not in brig AND parent allows | `$user->isAtLeastLevel(Stowaway) && !$user->in_brig && $user->parent_allows_minecraft` |

### Policies

No brig-specific policy exists. Brig enforcement is done entirely through gates. The `UserPolicy` has a `before()` hook that grants Admins and Command Officers full access, but brig-specific authorization is handled by the `manage-stowaway-users` gate.

### Permissions Matrix

| User Type | Put in Brig | Release from Brig | Submit Appeal | View Brig Card | View Community Content |
|-----------|------------|-------------------|---------------|----------------|----------------------|
| Regular User | No | No | Yes (when eligible) | Yes (own) | No (when brigged) |
| Jr Crew | No | No | Yes (when eligible) | Yes (own) | No (when brigged) |
| CrewMember (Quartermaster) | Yes | Yes | N/A | Yes | Yes |
| Officer | Yes | Yes | N/A | Yes | Yes |
| Admin | Yes | Yes | N/A | Yes | Yes |
| System (scheduled tasks) | Yes (auto) | Yes (auto) | N/A | N/A | N/A |

---

## 6. Routes

| Method | URL | Middleware | Handler | Route Name |
|--------|-----|-----------|---------|------------|
| GET | `/` (dashboard) | auth, verified, ensure-dob | `DashboardController` | `dashboard` |
| GET | `/birthdate` | auth | `livewire/auth/collect-birthdate` | `birthdate.show` |
| GET | `/profile/{user}` | auth, verified | `livewire/users/display-basic-details` | `profile.show` |

The brig system does not have dedicated routes. Brig functionality is embedded in the dashboard (in-brig-card), the user profile page (put in/release from brig actions), and the stowaway users widget (put in brig action). The birthdate collection route is relevant because it handles `AgeLock` brig resolution.

---

## 7. User Interface Components

### In-Brig Card
**File:** `resources/views/livewire/dashboard/in-brig-card.blade.php`
**Route:** `/` (dashboard, conditionally shown)

**Purpose:** Shows a brig status card on the dashboard when user is in brig. Displays different UI for each brig type and provides an appeal/contact submission mechanism.

**Authorization:** No gate check — shown based on `$user->in_brig` (brig card replaces normal dashboard content).

**UI per BrigType:**
- **ParentalPending:** "Account Pending Approval" card with amber badge, "Contact Staff" button
- **ParentalDisabled:** "Account Restricted by Parent" card with orange badge, "Contact Staff" button
- **AgeLock:** "Account Locked" card with red badge, "Update Date of Birth" button (links to `birthdate.show`)
- **Discipline (default):** "You Are In the Brig" card with red badge, reason display, appeal availability timer, "Submit Appeal" button

**User Actions Available:**
- Submit appeal/contact → creates Quartermaster ticket via `submitAppeal()` method → 7-day cooldown → `Flux::toast` success message

**Appeal Flow (PHP class):**
- Validates `appealMessage` (required, min 20 chars)
- Creates `Thread` (type: Ticket, subtype: AdminAction, department: Quartermaster)
- Creates initial `Message` with appeal text
- Records activity: `ticket_opened`
- Sets `next_appeal_available_at` to 7 days from now
- Notifies Quartermaster staff via `NewTicketNotification`
- Uses DB transaction with row locking to prevent duplicate appeals

### User Profile - Brig Actions
**File:** `resources/views/livewire/users/display-basic-details.blade.php`
**Route:** `/profile/{user}` (route name: `profile.show`)

**Purpose:** User profile page that includes brig management actions for authorized staff.

**Authorization:** `manage-stowaway-users` gate for brig actions.

**User Actions Available:**
- "Put in Brig" button → opens `profile-put-in-brig-modal` → validates reason (min 5 chars) and optional days (1-365) → calls `PutUserInBrig::run()` → `Flux::toast` success
- "Release from Brig" button → opens `profile-release-from-brig-modal` → validates reason (min 5 chars) → calls `ReleaseUserFromBrig::run()` → `Flux::toast` success

**UI Elements:**
- "In the Brig" red badge on user's name when `isInBrig()`
- "Put in Brig" menu item (shown when user is NOT in brig, NOT staff, NOT self)
- "Release from Brig" menu item (shown when user IS in brig)
- Promotion/demotion actions hidden when user is in brig

### Stowaway Users Widget
**File:** `resources/views/livewire/dashboard/stowaway-users-widget.blade.php`
**Route:** `/` (dashboard widget)

**Purpose:** Dashboard widget for managing stowaway users, includes brig action.

**Authorization:** `manage-stowaway-users` gate.

**User Actions Available:**
- "Put in Brig" button per user → opens `brig-reason-modal` → validates reason (min 5 chars) and optional days (1-365) → calls `PutUserInBrig::run()` → closes modals → `Flux::toast` success

**Note:** Widget only shows stowaway users who are NOT in brig (`in_brig = false`).

### Traveler Users Widget
**File:** `resources/views/livewire/dashboard/traveler-users-widget.blade.php`
**Route:** `/` (dashboard widget)

**Purpose:** Dashboard widget for managing traveler users. Filters out brigged users.

**Note:** Only shows traveler users who are NOT in brig (`in_brig = false`).

### Admin Manage Users Page
**File:** `resources/views/livewire/admin-manage-users-page.blade.php`

**Purpose:** Admin user management page with brig filtering capability.

**UI Elements:**
- Brig filter dropdown: "All Users", "In the Brig", "Not in Brig"
- "In Brig" column with red badge
- Sortable by `in_brig` column

### Collect Birthdate Page
**File:** `resources/views/livewire/auth/collect-birthdate.blade.php`
**Route:** `/birthdate` (route name: `birthdate.show`)

**Purpose:** Collects date of birth from users. Handles AgeLock brig resolution.

**Brig-related logic:**
- If user is in `AgeLock` brig and enters age 17+: releases via `ReleaseUserFromBrig::run()`
- If user is in `AgeLock` brig and enters age <13: transitions to `ParentalPending` brig
- If user is in `AgeLock` brig and enters age 13-16: releases via `ReleaseUserFromBrig::run()`
- Under-13 new entry: calls `PutUserInBrig::run()` with `BrigType::ParentalPending`

### Parent Portal
**File:** `resources/views/livewire/parent-portal/index.blade.php`

**Purpose:** Parent portal showing child accounts with brig status badges and reasons.

**Brig-related display:**
- Shows brig type badge (red for disciplinary, amber for parental)
- Shows brig reason in callout
- Hides "Link Minecraft Account" when child is in brig

---

## 8. Actions (Business Logic)

### PutUserInBrig (`app/Actions/PutUserInBrig.php`)

**Signature:** `handle(User $target, User $admin, string $reason, ?Carbon $expiresAt = null, ?Carbon $appealAvailableAt = null, BrigType $brigType = BrigType::Discipline, bool $notify = true): void`

**Step-by-step logic:**
1. If `$appealAvailableAt` is null AND `$brigType` is Discipline, sets `$appealAvailableAt` to 24 hours from now
2. Updates user fields: `in_brig = true`, `brig_reason`, `brig_expires_at`, `next_appeal_available_at`, `brig_timer_notified = false`, `brig_type`
3. Bans all Active/Verifying/ParentDisabled Minecraft accounts: sends whitelist remove command via `SendMinecraftCommand::run()`, sets status to `Banned`
4. Strips Discord roles and marks accounts as Brigged: calls `DiscordApiService::removeAllManagedRoles()` for Active/ParentDisabled Discord accounts, sets status to `Brigged`
5. Records activity: `RecordActivity::handle($target, 'user_put_in_brig', "Put in the brig by {admin}. Reason: {reason}. Timer/Appeal info.")`
6. If `$notify` is true: sends `UserPutInBrigNotification` via `TicketNotificationService::send()` with category `'account'`

**Called by:**
- `resources/views/livewire/users/display-basic-details.blade.php` (profile page)
- `resources/views/livewire/dashboard/stowaway-users-widget.blade.php` (stowaway widget)
- `resources/views/livewire/auth/register.blade.php` (under-13 registration)
- `resources/views/livewire/auth/collect-birthdate.blade.php` (under-13 DOB entry)
- `app/Actions/UpdateChildPermission.php` (parent disabling child)
- `app/Actions/ReleaseUserFromBrig.php` (re-brigging with parental hold after discipline release)

### ReleaseUserFromBrig (`app/Actions/ReleaseUserFromBrig.php`)

**Signature:** `handle(User $target, User $admin, string $reason, bool $notify = true): void`

**Step-by-step logic:**
1. Clears all brig fields: `in_brig = false`, `brig_reason = null`, `brig_expires_at = null`, `next_appeal_available_at = null`, `brig_timer_notified = false`, `brig_type = null`
2. Determines MC restore status based on `parent_allows_minecraft` (Active or ParentDisabled)
3. Restores Banned Minecraft accounts: sends whitelist add command (if Active), sets status
4. If restoring to Active: syncs Minecraft ranks via `SyncMinecraftRanks::run()` and staff via `SyncMinecraftStaff::run()` (if staff)
5. Determines Discord restore status based on `parent_allows_discord` (Active or ParentDisabled)
6. Restores Brigged Discord accounts to determined status
7. If restoring to Active: syncs Discord roles via `SyncDiscordRoles::run()` and staff via `SyncDiscordStaff::run()` (if staff)
8. **Parental re-engagement check:** If `!parent_allows_site` AND user `isMinor()`, records release activity then immediately re-brigs with `BrigType::ParentalDisabled` (via `PutUserInBrig::run()` with `notify: false`) and returns early
9. Records activity: `RecordActivity::handle($target, 'user_released_from_brig', "Released from brig by {admin}. Reason: {reason}")`
10. If `$notify` is true: sends `UserReleasedFromBrigNotification` via `TicketNotificationService::send()` with category `'account'`

**Called by:**
- `resources/views/livewire/users/display-basic-details.blade.php` (profile page)
- `resources/views/livewire/auth/collect-birthdate.blade.php` (AgeLock release)
- `app/Actions/UpdateChildPermission.php` (parent enabling child)
- `app/Console/Commands/ProcessAgeTransitions.php` (auto-release at 13)

### UpdateChildPermission (`app/Actions/UpdateChildPermission.php`)

**Brig-related logic only:**
- When parent **disables** site access and child is NOT in brig: calls `PutUserInBrig::run()` with `BrigType::ParentalDisabled`
- When parent **enables** site access and child IS in parental brig: calls `ReleaseUserFromBrig::run()`

### ReleaseChildToAdult (`app/Actions/ReleaseChildToAdult.php`)

**Brig-related logic:** Dissolves parent-child links, resets parental toggles, releases from parental brig but does NOT release from disciplinary brig.

---

## 9. Notifications

### UserPutInBrigNotification (`app/Notifications/UserPutInBrigNotification.php`)

**Triggered by:** `PutUserInBrig` action (when `$notify = true`)
**Recipient:** The brigged user
**Channels:** mail, Pushover, Discord (via `TicketNotificationService`)
**Mail subject:** "You Have Been Placed in the Brig"
**Mail template:** `resources/views/mail/brig-placed.blade.php`
**Content summary:** Shows reason, appeal availability date (or immediate appeal link), notes MC/Discord access suspended
**Queued:** Yes

### UserReleasedFromBrigNotification (`app/Notifications/UserReleasedFromBrigNotification.php`)

**Triggered by:** `ReleaseUserFromBrig` action (when `$notify = true`)
**Recipient:** The released user
**Channels:** mail, Pushover, Discord (via `TicketNotificationService`)
**Mail subject:** "You Have Been Released from the Brig"
**Mail template:** `resources/views/mail/brig-released.blade.php`
**Content summary:** Announces release, MC/Discord access restored, welcome back message, dashboard link
**Queued:** Yes

### BrigTimerExpiredNotification (`app/Notifications/BrigTimerExpiredNotification.php`)

**Triggered by:** `CheckBrigTimers` console command
**Recipient:** Users whose brig timer has expired
**Channels:** mail, Pushover, Discord (via `TicketNotificationService`)
**Mail subject:** "Your Brig Period Has Ended — You May Now Appeal"
**Mail template:** `resources/views/mail/brig-timer-expired.blade.php`
**Content summary:** Informs user they can now submit an appeal via dashboard, notes no guarantee of reinstatement
**Queued:** Yes

---

## 10. Background Jobs

Not applicable for this feature. Brig operations are synchronous (no dedicated job classes). Notifications are queued via Laravel's `ShouldQueue` interface on the notification classes themselves.

---

## 11. Console Commands & Scheduled Tasks

### `brig:check-timers`
**File:** `app/Console/Commands/CheckBrigTimers.php`
**Scheduled:** Yes — daily at 09:00 (`routes/console.php`)
**What it does:** Finds users where `in_brig = true`, `brig_expires_at <= now()`, and `brig_timer_notified = false`. Sends `BrigTimerExpiredNotification` to each and sets `brig_timer_notified = true`. Does NOT auto-release — just notifies that they can appeal.

### `parent-portal:process-age-transitions`
**File:** `app/Console/Commands/ProcessAgeTransitions.php`
**Scheduled:** Yes — daily at 02:00 (`routes/console.php`)
**What it does:**
1. **13-year-olds:** Finds users in `ParentalPending` brig who have turned 13 and have NO linked parent. Releases them via `ReleaseUserFromBrig::run()` and sends `AccountUnlockedNotification`.
2. **19-year-olds:** Finds users who turned 19 and have parent links. Releases them to adult status via `ReleaseChildToAdult::run()` (dissolves parent links, resets toggles, releases from parental brig).

---

## 12. Services

### DiscordApiService (`app/Services/DiscordApiService.php`)
**Purpose:** Handles Discord API calls for role management.
**Brig-relevant method:**
- `removeAllManagedRoles(string $discordUserId): void` — Called by `PutUserInBrig` to strip all managed Discord roles when brigging a user.

### TicketNotificationService (`app/Services/TicketNotificationService.php`)
**Purpose:** Smart notification delivery (mail, Pushover, Discord) with category-based preferences.
**Brig-relevant usage:** All three brig notifications are sent through this service with the `'account'` category.

---

## 13. Activity Log Entries

| Action String | Logged By | Subject Model | Description |
|---------------|-----------|---------------|-------------|
| `user_put_in_brig` | `PutUserInBrig` | User | "Put in the brig by {admin}. Reason: {reason}. Timer set until {date}." or "No timer set." + appeal info |
| `user_released_from_brig` | `ReleaseUserFromBrig` | User | "Released from brig by {admin}. Reason: {reason}" or "Released from disciplinary brig by {admin}; parental restrictions re-applied. Reason: {reason}" |
| `ticket_opened` | In-brig card `submitAppeal()` | Thread | "Brig appeal submitted: {subject}" or "Staff contact submitted: {subject}" |
| `parent_permission_changed` | `UpdateChildPermission` | User | "Site access {enabled/disabled} by parent {name}." |

---

## 14. Data Flow Diagrams

### Putting a User in the Brig (Staff Action)

```
Staff clicks "Put in Brig" on profile page or stowaway widget
  -> Volt Component opens brig modal
  -> Staff enters reason + optional days
  -> Component validates (reason: min 5 chars, days: 1-365 optional)
  -> $this->authorize('manage-stowaway-users')
  -> PutUserInBrig::run($target, $admin, $reason, $expiresAt)
    -> User updated: in_brig=true, brig_reason, brig_expires_at, brig_type=Discipline
    -> Default 24h appeal cooldown set
    -> MC accounts: whitelist remove commands sent, status -> Banned
    -> Discord accounts: roles stripped via API, status -> Brigged
    -> RecordActivity::handle('user_put_in_brig')
    -> UserPutInBrigNotification sent via TicketNotificationService
  -> Flux::toast('User has been placed in the Brig')
```

### Releasing a User from the Brig (Staff Action)

```
Staff clicks "Release from Brig" on profile page
  -> Volt Component opens release modal
  -> Staff enters reason for release
  -> Component validates (reason: min 5 chars)
  -> $this->authorize('manage-stowaway-users')
  -> ReleaseUserFromBrig::run($target, $admin, $reason)
    -> User updated: in_brig=false, all brig fields cleared
    -> MC accounts: whitelist add commands sent, status -> Active (or ParentDisabled)
    -> MC ranks synced via SyncMinecraftRanks::run()
    -> Discord accounts: status -> Active (or ParentDisabled)
    -> Discord roles synced via SyncDiscordRoles::run()
    -> IF parent_allows_site=false AND isMinor():
       -> Re-brig with ParentalDisabled (early return)
    -> RecordActivity::handle('user_released_from_brig')
    -> UserReleasedFromBrigNotification sent
  -> Flux::toast('User has been released from the Brig')
```

### Submitting a Brig Appeal (User Action)

```
Brigged user clicks "Submit Appeal" on dashboard in-brig card
  -> Brig appeal modal opens
  -> User enters appeal message (min 20 chars)
  -> submitAppeal() method:
    -> Checks canAppeal() — must be in brig AND past appeal cooldown
    -> DB::transaction with row lock:
      -> Creates Thread (Ticket, AdminAction, Quartermaster dept)
      -> Creates Message with appeal text
      -> RecordActivity::handle('ticket_opened')
      -> Sets next_appeal_available_at = now() + 7 days
    -> Notifies Quartermaster staff via NewTicketNotification
    -> Flux::modal('brig-appeal-modal')->close()
    -> Flux::toast('Your appeal has been submitted')
```

### Automatic Brig on Under-13 Registration

```
User registers with age < 13
  -> Registration form processes DOB
  -> PutUserInBrig::run(target: $user, admin: $user, reason: 'Account pending parental approval', brigType: ParentalPending, notify: false)
    -> User placed in ParentalPending brig
    -> MC/Discord accounts banned
  -> Parent notification email sent
  -> User sees "Account Pending Approval" brig card on dashboard
```

### Brig Timer Expiry Notification (Scheduled)

```
Daily at 09:00 -> brig:check-timers command
  -> Queries: in_brig=true, brig_expires_at <= now(), brig_timer_notified=false
  -> For each user:
    -> BrigTimerExpiredNotification sent via TicketNotificationService
    -> brig_timer_notified = true
  -> (User is NOT auto-released — they must submit an appeal)
```

### Age Transition Processing (Scheduled)

```
Daily at 02:00 -> parent-portal:process-age-transitions
  -> processThirteenYearOlds():
    -> Finds: in_brig=true, brig_type=ParentalPending, age>=13, no parents
    -> ReleaseUserFromBrig::run() for each
    -> Sends AccountUnlockedNotification
  -> processNineteenYearOlds():
    -> Finds: age>=19, has parent links
    -> ReleaseChildToAdult::run() for each (dissolves links, releases parental brig)
```

---

## 15. Configuration

Not applicable for this feature. The brig system uses no dedicated configuration values or environment variables. The 24-hour initial appeal cooldown and 7-day between-appeal cooldown are hardcoded in the action and component respectively.

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/Actions/Actions/PutUserInBrigTest.php` | 9 tests | Core brig placement logic |
| `tests/Feature/Actions/Actions/ReleaseUserFromBrigTest.php` | 12 tests | Core brig release logic |
| `tests/Feature/Actions/Actions/PutUserInBrigWithParentTest.php` | 4 tests | Parental brig placement |
| `tests/Feature/Actions/Actions/ReleaseUserFromBrigWithParentTest.php` | 4 tests | Release with parental re-engagement |
| `tests/Feature/Actions/Actions/ReleaseChildToAdultTest.php` | 4 tests | Adult transition at 19 |
| `tests/Feature/Brig/BrigAppealTest.php` | 8 tests | Appeal submission and eligibility |
| `tests/Feature/Livewire/InBrigCardParentalTest.php` | 4 tests | Brig card UI per type |
| `tests/Feature/Console/Commands/ProcessAgeTransitionsTest.php` | 6 tests | Scheduled age transitions |
| `tests/Feature/Discord/DiscordBrigIntegrationTest.php` | 4 tests | Discord role stripping/restoration |

### Test Case Inventory

#### `tests/Feature/Actions/Actions/PutUserInBrigTest.php`
- it('marks the target user as in brig')
- it('sets brig_expires_at when expiresAt is provided')
- it('sets next_appeal_available_at when appealAvailableAt is provided')
- it('sets brig_timer_notified to false')
- it('bans active minecraft accounts')
- it('bans verifying minecraft accounts')
- it('records activity for user put in brig')
- it('sends notification to the target user')
- it('works without expires_at or appeal_available_at')

#### `tests/Feature/Actions/Actions/ReleaseUserFromBrigTest.php`
- it('marks the target user as no longer in brig')
- it('restores banned minecraft accounts to active')
- it('does not affect non-banned minecraft accounts')
- it('records activity for user released from brig')
- it('sends notification to the released user')
- it('clears next_appeal_available_at on release')
- it('sets brig_timer_notified to false on release')
- it('restores brigged discord accounts to active')
- it('syncs discord roles on release')
- it('syncs discord staff roles for staff users on release')
- it('skips discord staff sync for non-staff users')
- it('still records activity and sends notification even when no minecraft or discord accounts exist')

#### `tests/Feature/Actions/Actions/PutUserInBrigWithParentTest.php`
- it('sets brig_type on user')
- it('changes ParentDisabled MC accounts to Banned when brigged')
- it('defaults to Discipline brig_type')
- it('skips notification when notify is false')

#### `tests/Feature/Actions/Actions/ReleaseUserFromBrigWithParentTest.php`
- it('re-brigs with ParentalDisabled when parent_allows_site is false')
- it('restores MC to ParentDisabled when parent has MC disabled')
- it('restores MC to Active when parent has MC enabled')
- it('fully releases when parent_allows_site is true')

#### `tests/Feature/Actions/Actions/ReleaseChildToAdultTest.php`
- it('dissolves parent-child links')
- it('resets parental toggles to defaults')
- it('releases from parental brig')
- it('does not release from disciplinary brig')

#### `tests/Feature/Brig/BrigAppealTest.php`
- test('users can appeal when appeal timer has expired')
- test('users cannot appeal when appeal timer has not expired')
- test('users can appeal immediately when no appeal timer is set')
- test('users not in brig cannot appeal')
- test('submitting appeal sets next appeal timer')
- test('submitting appeal does not change brig_expires_at')
- test('appeal form is shown when user can appeal')
- test('appeal requires message content')

#### `tests/Feature/Livewire/InBrigCardParentalTest.php`
- it('shows parental pending message with contact staff button')
- it('shows parental disabled message with contact staff button')
- it('shows age lock message')
- it('shows appeal button only for disciplinary brig')

#### `tests/Feature/Console/Commands/ProcessAgeTransitionsTest.php`
- it('releases 13-year-olds with no parent from parental pending brig')
- it('does not release 13-year-olds who have a linked parent')
- it('does not release users under 13')
- it('does not release users in discipline brig')
- it('releases 19-year-olds from parent links')
- it('does not release users under 19 from parent links')

#### `tests/Feature/Discord/DiscordBrigIntegrationTest.php`
- it('sets discord accounts to brigged when user is put in brig')
- it('calls removeAllManagedRoles when brigging')
- it('restores discord accounts to active when released from brig')
- it('syncs discord permissions when released from brig')

### Coverage Gaps

- No test for the `CheckBrigTimers` console command
- No test for brig actions from the stowaway widget Livewire component
- No test for brig filter on admin manage users page
- No test for the profile page put-in-brig / release-from-brig Livewire methods directly
- No test verifying that `view-community-content` gate blocks brigged users
- No test for the `collect-birthdate` AgeLock->ParentalPending transition path
- No test for concurrent appeal submission (race condition handling)

---

## 17. File Map

**Models:**
- `app/Models/User.php` (brig fields and methods)

**Enums:**
- `app/Enums/BrigType.php`
- `app/Enums/MinecraftAccountStatus.php` (Banned, Active, ParentDisabled cases)
- `app/Enums/DiscordAccountStatus.php` (Brigged, Active, ParentDisabled cases)

**Actions:**
- `app/Actions/PutUserInBrig.php`
- `app/Actions/ReleaseUserFromBrig.php`
- `app/Actions/UpdateChildPermission.php` (brig integration)
- `app/Actions/ReleaseChildToAdult.php` (brig integration)
- `app/Actions/RecordActivity.php` (used for logging)
- `app/Actions/SendMinecraftCommand.php` (whitelist commands)
- `app/Actions/SyncMinecraftRanks.php` (rank restoration)
- `app/Actions/SyncMinecraftStaff.php` (staff rank restoration)
- `app/Actions/SyncDiscordRoles.php` (role restoration)
- `app/Actions/SyncDiscordStaff.php` (staff role restoration)

**Policies:**
- `app/Policies/UserPolicy.php` (no brig-specific methods, but `before()` grants admin access)

**Gates:** `app/Providers/AuthServiceProvider.php` -- gates: `view-community-content`, `manage-stowaway-users`, `link-discord`, `link-minecraft-account`

**Notifications:**
- `app/Notifications/UserPutInBrigNotification.php`
- `app/Notifications/UserReleasedFromBrigNotification.php`
- `app/Notifications/BrigTimerExpiredNotification.php`

**Jobs:** None dedicated.

**Services:**
- `app/Services/TicketNotificationService.php` (notification delivery)
- `app/Services/DiscordApiService.php` (role stripping)

**Controllers:**
- `app/Http/Controllers/DashboardController.php` (renders dashboard with brig card)

**Volt Components:**
- `resources/views/livewire/dashboard/in-brig-card.blade.php`
- `resources/views/livewire/dashboard/stowaway-users-widget.blade.php`
- `resources/views/livewire/dashboard/traveler-users-widget.blade.php`
- `resources/views/livewire/users/display-basic-details.blade.php`
- `resources/views/livewire/admin-manage-users-page.blade.php`
- `resources/views/livewire/auth/register.blade.php`
- `resources/views/livewire/auth/collect-birthdate.blade.php`
- `resources/views/livewire/parent-portal/index.blade.php`

**Mail Templates:**
- `resources/views/mail/brig-placed.blade.php`
- `resources/views/mail/brig-released.blade.php`
- `resources/views/mail/brig-timer-expired.blade.php`

**Routes:**
- Dashboard: `GET /` (`dashboard`)
- Birthdate: `GET /birthdate` (`birthdate.show`)
- Profile: `GET /profile/{user}` (`profile.show`)

**Migrations:**
- `database/migrations/2026_02_20_080000_add_brig_fields_to_users_table.php`
- `database/migrations/2026_02_21_034025_add_next_appeal_available_at_to_users_table.php`
- `database/migrations/2026_02_28_100000_add_parental_fields_to_users_table.php`

**Console Commands:**
- `app/Console/Commands/CheckBrigTimers.php`
- `app/Console/Commands/ProcessAgeTransitions.php`

**Scheduled Tasks:** `routes/console.php` — `brig:check-timers` daily at 09:00, `parent-portal:process-age-transitions` daily at 02:00

**Middleware:**
- `app/Http/Middleware/EnsureDateOfBirthIsSet.php` (redirects to birthdate page, relates to AgeLock flow)

**Tests:**
- `tests/Feature/Actions/Actions/PutUserInBrigTest.php`
- `tests/Feature/Actions/Actions/ReleaseUserFromBrigTest.php`
- `tests/Feature/Actions/Actions/PutUserInBrigWithParentTest.php`
- `tests/Feature/Actions/Actions/ReleaseUserFromBrigWithParentTest.php`
- `tests/Feature/Actions/Actions/ReleaseChildToAdultTest.php`
- `tests/Feature/Brig/BrigAppealTest.php`
- `tests/Feature/Livewire/InBrigCardParentalTest.php`
- `tests/Feature/Console/Commands/ProcessAgeTransitionsTest.php`
- `tests/Feature/Discord/DiscordBrigIntegrationTest.php`

**Config:** None specific.

---

## 18. Known Issues & Improvement Opportunities

1. **Hardcoded cooldown values:** The 24-hour initial appeal cooldown (in `PutUserInBrig`) and 7-day between-appeal cooldown (in `in-brig-card.blade.php`) are hardcoded. These could be moved to config values for easier tuning.

2. **No auto-release mechanism:** The `brig_expires_at` timer does NOT auto-release users — it only triggers a notification. The naming `brig_expires_at` is misleading since nothing actually "expires." Users must still manually submit an appeal. Consider renaming to `brig_appeal_available_at` or implementing actual auto-release.

3. **Missing CheckBrigTimers test:** The `brig:check-timers` console command has no test coverage.

4. **Admin DOB edit doesn't re-evaluate brig:** The comment in `admin-manage-users-page.blade.php` notes that admin DOB edits don't re-evaluate age-dependent brig states (AgeLock, ParentalPending). This is by design but could lead to inconsistencies.

5. **No staff-side brig dashboard:** There's no dedicated view for staff to see all brigged users with their reasons, timers, and appeal status. The admin manage users page has a filter but lacks detail. A dedicated brig management dashboard could improve staff workflow.

6. **Profile brig actions use gate checks inline:** The profile page (`display-basic-details.blade.php`) checks `Auth::user()->can('manage-stowaway-users')` inside PHP methods rather than using `$this->authorize()`. While functional, using `$this->authorize()` would be more consistent with the project's convention.

7. **Discord role restoration on release:** When releasing from brig, Discord accounts are set to Active status but role syncing happens in a separate step. If `SyncDiscordRoles::run()` fails (logged but caught), the user's Discord account would show as Active with no roles. There's no retry mechanism.

8. **Potential N+1 in PutUserInBrig:** The action iterates MC and Discord accounts with `->get()` inside loops that issue individual RCON/API calls. While the number of accounts per user is typically small, this could be optimized with bulk operations if needed.

9. **Appeal race condition:** The `submitAppeal()` method uses `lockForUpdate()` to prevent duplicate appeals, which is good. However, the Quartermaster notification is sent outside the transaction, so if it fails, the appeal is still recorded but staff may not be notified. The error is logged but there's no retry.
