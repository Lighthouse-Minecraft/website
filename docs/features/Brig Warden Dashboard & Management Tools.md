# Brig Warden Dashboard & Management Tools -- Technical Documentation

> **Audience:** Project owner, developers, AI agents
> **Generated:** 2026-04-13
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

The Brig Warden Dashboard & Management Tools is a staff-facing suite of features that gives Brig Wardens a centralized interface for overseeing and managing all users currently in the brig. Prior to this feature, wardens had to navigate to individual user profiles to take action, had no visibility into upcoming releases or pending appeals, and lacked tools to adjust brig status without releasing and re-brigging a user.

This feature introduces four major capabilities: (1) a **Brig Warden Dashboard Widget** on the staff dashboard showing approaching-release users, open appeal counts, and total brigged counts; (2) a **Brig Status Manager** shared modal component embeddable from the widget, user profile pages, and appeal threads; (3) a **dedicated BrigAppeal thread type** that separates appeal discussions from general staff topics; and (4) a **Brig Activity Log tab** in the ACP for a full audit trail of brig-related actions.

It also introduces **permanent confinement** (`permanent_brig_at` column): a flag that marks an account as permanently confined, blocks appeal submissions, excludes the user from release timer notifications, and surfaces a distinct UI message to the confined user. When permanent confinement is removed, the user is always notified regardless of the notify toggle.

This feature is exclusively used by users with the `Brig Warden` role (staff). The brigged user interacts with the existing in-brig-card component on their dashboard, which was updated to handle permanent confinement and the new BrigAppeal thread type.

---

## 2. Database Schema

### `users` table (brig-relevant columns)

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `in_brig` | boolean | No | false | Whether user is currently in the brig |
| `brig_reason` | text | Yes | null | Admin-provided reason for confinement |
| `brig_expires_at` | timestamp | Yes | null | When the brig expires; null = indefinite |
| `brig_timer_notified` | boolean | No | false | Whether BrigTimerExpiredNotification was sent |
| `brig_type` | varchar | Yes | null | Enum value from `BrigType` |
| `next_appeal_available_at` | timestamp | Yes | null | Earliest timestamp user may submit an appeal |
| `permanent_brig_at` | timestamp | Yes | null | Set when permanent confinement is applied; null = not permanent |
| `brig_placed_at` | timestamp | Yes | null | When user was placed in brig (set by PutUserInBrig) |

**Migration(s):**
- `database/migrations/2026_02_20_080000_add_brig_fields_to_users_table.php` — adds `in_brig`, `brig_reason`, `brig_expires_at`, `brig_timer_notified`
- `database/migrations/2026_04_13_000001_add_permanent_brig_at_to_users_table.php` — adds `permanent_brig_at`
- `database/migrations/2026_04_13_162600_add_brig_placed_at_to_users_table.php` — adds `brig_placed_at`

---

## 3. Models & Relationships

### User (`app/Models/User.php`)

This is the primary model for this feature. All brig state is stored directly on the user record.

**Brig-Related Methods:**

- `isInBrig(): bool` — Returns `(bool) $this->in_brig`. Used by UI and authorization checks.
- `brigTimerExpired(): bool` — Returns `true` when `brig_expires_at` is null or `now() >= brig_expires_at`. "Expired" means the timer has run out, which triggers the appeal window.
- `canAppeal(): bool` — Returns `false` when not in brig, when `permanent_brig_at` is set, or when `next_appeal_available_at` is in the future. Returns `true` otherwise.
- `disciplineRiskScore(): array` — Cached (1 day) computation of published discipline report severity points over 7d/30d/90d windows. Returns `['7d' => int, '30d' => int, '90d' => int, 'total' => int]`.
- `clearDisciplineRiskScoreCache(): void` — Manually clears the risk score cache for a user.
- `riskScoreColor(int $total): string` — Static method. Maps total score to a Flux badge color: `zinc` (0), `green` (1–10), `yellow` (11–25), `orange` (26–50), `red` (51+).

**Brig-Related Fillable Columns:**
`in_brig`, `brig_reason`, `brig_expires_at`, `next_appeal_available_at`, `brig_timer_notified`, `brig_type`, `brig_placed_at`, `permanent_brig_at`

**Brig-Related Casts:**
- `in_brig` => `boolean`
- `brig_expires_at` => `datetime`
- `next_appeal_available_at` => `datetime`
- `brig_timer_notified` => `boolean`
- `brig_type` => `BrigType::class` (enum)
- `brig_placed_at` => `datetime`
- `permanent_brig_at` => `datetime`

---

## 4. Enums Reference

### BrigType (`app/Enums/BrigType.php`)

| Case | Value | Label | Notes |
|------|-------|-------|-------|
| `Discipline` | `discipline` | Disciplinary | Standard disciplinary brig; gates appeal submission |
| `ParentalPending` | `parental_pending` | Pending Parental Approval | Placed while awaiting parental approval |
| `ParentalDisabled` | `parental_disabled` | Restricted by Parent | Parent has disabled account |
| `AgeLock` | `age_lock` | Age Verification Required | Account locked pending DOB verification |
| `RulesNonCompliance` | `rules_non_compliance` | Rules Non-Compliance | Rules violation |

**Helper methods:**
- `isDisciplinary(): bool` — True only for `Discipline`
- `isParental(): bool` — True for `ParentalPending` and `ParentalDisabled`

### ThreadType (`app/Enums/ThreadType.php`)

| Case | Value | Label | Notes |
|------|-------|-------|-------|
| `BrigAppeal` | `brig_appeal` | Brig Appeal | **Added by this feature.** Used for disciplinary appeal threads. |

### ThreadStatus (`app/Enums/ThreadStatus.php`)

| Case | Value | Label |
|------|-------|-------|
| `Open` | `open` | Open |
| `Pending` | `pending` | Pending |
| `Resolved` | `resolved` | Resolved |
| `Closed` | `closed` | Closed |

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

| Gate Name | Who Can Pass | Logic |
|-----------|-------------|-------|
| `put-in-brig` | Brig Warden role | `$user->hasRole('Brig Warden')` |
| `release-from-brig` | Brig Warden role | `$user->hasRole('Brig Warden')` |

The `put-in-brig` gate is used exclusively throughout this feature for all warden-facing actions and UI visibility.

### Policies

Not applicable for this feature — authorization is gate-based.

### Permissions Matrix

| User Type | See widget | Manage brig status | Quick release | View brig log | Submit appeal |
|-----------|-----------|-------------------|---------------|---------------|---------------|
| Regular user (in brig) | No | No | No | No | Yes (if eligible) |
| Regular user (not in brig) | No | No | No | No | No |
| Staff (no Brig Warden) | No | No | No | No | No |
| Brig Warden | Yes | Yes | Yes | Yes | No |

---

## 6. Routes

This feature does not introduce new routes. The Brig Warden Widget is embedded in the existing `/dashboard` page, and the Brig Status Manager is a shared Livewire component. The brig appeal thread view uses the existing `/discussions/{thread}` route. The ACP brig log uses the existing `/acp` route with `?category=logs&tab=brig-log`.

| Method | URL | Middleware | Handler | Route Name |
|--------|-----|-----------|---------|------------|
| GET | `/dashboard` | auth | `resources/views/dashboard.blade.php` | `dashboard` |
| GET | `/discussions/{thread}` | auth | `topics.view-topic` (Volt) | `discussions.show` |
| GET | `/acp` | auth | `AdminControlPanelController@index` | `acp.index` |

---

## 7. User Interface Components

### BrigWardenWidget
**File:** `resources/views/livewire/dashboard/brig-warden-widget.blade.php`
**Embedded in:** `resources/views/dashboard.blade.php` (Staff Dashboard section)

**Purpose:** Dashboard card giving wardens at-a-glance situational awareness and quick access to management actions.

**Authorization:** `mount()` calls `$this->authorize('put-in-brig')`. The dashboard gates the component with `@can('put-in-brig')`.

**Computed Properties:**
- `approachingRelease` — Users where `in_brig=true`, `brig_expires_at` is within 7 days and not null, and `permanent_brig_at` is null. Ordered by `brig_expires_at` ascending.
- `openAppealsCount` — Count of `Thread` where `type = BrigAppeal` and `status = Open`.
- `totalBriggedCount` — Count of all users where `in_brig = true`.
- `allBriggedUsers` — All brigged users, filterable by `search` (name LIKE), sortable by `name`, `brig_type`, `brig_placed_at`, or `brig_expires_at`.
- `managingUser` — The `User` for the currently open management modal (set by `openManageModal()`).

**User Actions:**
- Open appeals badge → links to `discussions.index` route
- Total brigged badge / "View All" button → opens `brig-all-users-modal` (Alpine `$flux.modal`)
- Approaching release "Manage" button → calls `openManageModal($userId)`, then opens `brig-manage-user-modal`
- All-users modal "Manage" button → calls `openManageModal($userId)`, closes all-users modal, opens manage modal
- Sort column headers in all-users modal → calls `sortBy($column)`, toggles direction

**UI Elements:**
- Approaching release list with risk score badge (colored) and time remaining
- Open appeals badge (red if > 0, zinc if 0)
- Total brigged badge (amber)
- "View All" button
- All-users modal: searchable text field, sortable table (Name, Type, Reason truncated, Date Placed, Expires At), Permanent badge for permanent users, parental/age-lock types shown in blue badges
- Manage modal: embeds `<livewire:brig.brig-status-manager>`

---

### BrigStatusManager
**File:** `resources/views/livewire/brig/brig-status-manager.blade.php`

**Purpose:** Shared component for editing a brigged user's status in-place. Embedded in the warden widget, user profile modal, and BrigAppeal thread view.

**Authorization:** `mount()` and `saveStatus()` and `quickRelease()` all call `$this->authorize('put-in-brig')`.

**Properties:**
- `brigReason` (string) — Current brig reason, pre-populated from `$user->brig_reason`
- `brigExpiresAt` (string) — Datetime-local formatted value of `$user->brig_expires_at`
- `brigPermanent` (bool) — Whether `permanent_brig_at` is set
- `brigNotify` (bool, default `true`) — Whether to notify user of changes
- `brigReleaseReason` (string) — Required reason for quick release

**Lifecycle Hooks:**
- `updatedBrigPermanent()` — Forces `brigNotify = true` whenever the permanent toggle changes

**Methods:**
- `saveStatus()` — Validates (`brigReason` required/min:5, `brigExpiresAt` nullable/date). Computes `$permanent` change (null/true/false), `$newExpiresAt` (false/null/Carbon), and `$newReason` (null if unchanged). Calls `UpdateBrigStatus::run(...)`. Refreshes user, shows success toast, dispatches `brig-status-updated` event.
- `quickRelease()` — Validates (`brigReleaseReason` required/min:5). Calls `UpdateBrigStatus::run(releaseReason: ...)`. Shows success toast, dispatches `brig-status-updated`.

**UI Elements:**
- Brig type badge (read-only) + Permanent badge (conditional)
- Reason textarea with validation error
- Expires At datetime-local input (hidden when `brigPermanent` is true via Alpine `x-show`)
- Permanent Confinement checkbox (`wire:model.live`)
- "Notify user of updates?" checkbox (disabled when permanent flag unchanged)
- Save Changes button
- Quick Release section: release reason textarea, Release from Brig button (danger variant)

---

### AdminManageBrigLogPage
**File:** `resources/views/livewire/admin-manage-brig-log-page.blade.php`
**Embedded in:** ACP Logs tab panel (`admin-control-panel-tabs.blade.php`)

**Purpose:** Filterable, paginated audit log of all brig-related activity entries.

**Authorization:** `mount()` calls `$this->authorize('put-in-brig')`.

**Computed Property:**
- `entries` — `ActivityLog` records filtered to brig action slugs, `with(['causer', 'subject'])`, `latest()`, paginated 25/page.

**Filtered Actions:** `user_put_in_brig`, `user_released_from_brig`, `brig_status_updated`, `brig_appeal_submitted`, `permanent_brig_set`, `permanent_brig_removed`

**UI Elements:**
- Heading "Brig Activity Log"
- Paginated table: Date/Time (user's timezone), Target User (linked), Action (badge), By (linked admin or "System"), Description

---

### AdminControlPanelTabs (modified)
**File:** `resources/views/livewire/admin-control-panel-tabs.blade.php`

**Changes made by this feature:**
- `hasLogsTabs()` now returns `true` when `$user->can('put-in-brig')` (previously only log-viewer role)
- `defaultTabFor('logs')` resolves to `'brig-log'` for wardens who lack other log roles
- New "Brig Log" tab added to the Logs category
- New `brig-log` tab panel embeds `<livewire:admin-manage-brig-log-page>`

---

### ViewTopic (modified)
**File:** `resources/views/livewire/topics/view-topic.blade.php`

**Changes made by this feature:**
- `mount()` already accepted `ThreadType::BrigAppeal` in addition to `ThreadType::Topic` (added by issue #628)
- **New:** "Manage Brig Status" button appears in the thread header for `BrigAppeal` threads when `@can('put-in-brig')`. Opens `brig-appeal-manage-modal`.
- **New:** `brig-appeal-manage-modal` Flux modal at the bottom of the component. Embeds `<livewire:brig.brig-status-manager :user="$thread->createdBy">` targeting the thread creator (the brigged user).

---

### InBrigCard (modified)
**File:** `resources/views/livewire/dashboard/in-brig-card.blade.php`

**Changes made by this feature:**
- Shows "Permanently Confined" heading and message when `$user->permanent_brig_at` is set
- No appeal button or appeal modal rendered for permanent users
- Disciplinary appeals now create `ThreadType::BrigAppeal` threads (previously `ThreadType::Topic`)
- Appeal participants auto-add: Brig Wardens, all-roles staff (`has_all_roles_at` set), and Admins (`admin_granted_at` set) — replacing previous Command Officers + Quartermasters logic

---

## 8. Actions (Business Logic)

### UpdateBrigStatus (`app/Actions/UpdateBrigStatus.php`)

**Signature:**
```php
public function handle(
    User $target,
    User $admin,
    ?string $newReason = null,
    Carbon|false|null $newExpiresAt = false,
    ?bool $permanent = null,
    bool $notify = true,
    ?string $releaseReason = null,
): void
```

**Parameter semantics:**
- `$newExpiresAt = false` — no change to expiry
- `$newExpiresAt = null` — clear expiry (indefinite)
- `$newExpiresAt = Carbon` — set to this timestamp
- `$permanent = null` — no change to permanent flag
- `$permanent = true` — set permanent confinement
- `$permanent = false` — remove permanent confinement

**Step-by-step logic:**

**Path A — Quick Release (`$releaseReason !== null`):**
1. Calls `ReleaseUserFromBrig::run($target, $admin, $releaseReason)`
2. Calls `RecordActivity::run($target, 'brig_status_updated', "Quick release by {$admin->name}. Reason: {$releaseReason}.")`
3. Returns.

**Path B — Set Permanent (`$permanent === true`):**
1. Sets `$target->permanent_brig_at = now()`, `brig_expires_at = null`, `next_appeal_available_at = null`
2. Saves target
3. Calls `RecordActivity::run($target, 'permanent_brig_set', "...")`
4. If `$notify`: sends `BrigStatusUpdatedNotification`
5. Returns.

**Path C — Remove Permanent (`$permanent === false`):**
1. Sets `$target->permanent_brig_at = null`
2. Recalculates `next_appeal_available_at`: uses `brig_expires_at` if set, else `now()->addHours(24)`
3. Saves target
4. Calls `RecordActivity::run($target, 'permanent_brig_removed', "...")`
5. **Always** sends `BrigStatusUpdatedNotification` (ignores `$notify` toggle)
6. Returns.

**Path D — Reason / Timer Updates:**
1. If `$newReason !== null`: sets `$target->brig_reason = $newReason`
2. If `$newExpiresAt !== false`: sets `$target->brig_expires_at = $newExpiresAt`
3. If no changes: returns early without saving or logging
4. Saves target
5. Calls `RecordActivity::run($target, 'brig_status_updated', "Brig status updated by {$admin->name}: {$changesSummary}.")`
6. If `$notify`: sends `BrigStatusUpdatedNotification`

**Called by:** `BrigStatusManager::saveStatus()`, `BrigStatusManager::quickRelease()`

**Sends notification:** `BrigStatusUpdatedNotification` via `TicketNotificationService::send($target, ..., 'account')`

---

### PutUserInBrig (`app/Actions/PutUserInBrig.php`)

**Signature:**
```php
public function handle(
    User $target,
    User $admin,
    string $reason,
    ?Carbon $expiresAt = null,
    ?Carbon $appealAvailableAt = null,
    BrigType $brigType = BrigType::Discipline,
    bool $notify = true,
    bool $permanent = false,
): void
```

**Step-by-step logic:**
1. Validates: target not already in brig; for Discipline type, admin cannot brig self and cannot brig staff members
2. Computes `$appealAvailableAt`: null if permanent; `$expiresAt ?? now()->addHours(24)` for Discipline when not provided
3. Sets user fields: `in_brig=true`, `brig_reason`, `brig_expires_at`, `next_appeal_available_at`, `brig_timer_notified=false`, `brig_type`, `brig_placed_at=now()`, `permanent_brig_at`
4. Bans all active/verifying/ParentDisabled Minecraft accounts (whitelist remove + status=Banned)
5. Strips Discord managed roles, assigns "In Brig" Discord role
6. Calls `RecordActivity::handle($target, 'user_put_in_brig', description)`
7. If `$notify`: sends `UserPutInBrigNotification` via `TicketNotificationService`

**Called by:** `InBrigCard` (in-brig-card component), stowaway widget, traveler widget, user profile page

---

## 9. Notifications

### BrigStatusUpdatedNotification (`app/Notifications/BrigStatusUpdatedNotification.php`)

**Triggered by:** `UpdateBrigStatus` action
**Recipient:** The brigged user (`$target`)
**Channels:** mail, optionally Pushover (via `TicketNotificationService` channel selection)
**Queued:** Yes (`implements ShouldQueue`)
**Mail subject:** "Your Brig Status Has Been Updated"
**Mail content:** Greeting, "A staff member has updated your brig status.", change summary line, link to dashboard, note about brig appeals
**Pushover title/message:** "Brig Status Updated" / "A staff member has updated your brig status: {summary}"

---

## 10. Background Jobs

Not applicable for this feature. Brig management is synchronous.

---

## 11. Console Commands & Scheduled Tasks

### `brig:check-timers`
**File:** `app/Console/Commands/CheckBrigTimers.php`
**Scheduled:** See `routes/console.php`
**What it does:** Finds users where `in_brig=true`, `permanent_brig_at IS NULL`, `brig_expires_at <= now()`, and `brig_timer_notified=false`. For each: sends `BrigTimerExpiredNotification` via `TicketNotificationService`, then sets `brig_timer_notified=true`.

**This feature's change:** Added `->whereNull('permanent_brig_at')` to skip permanently confined users who should never receive timer notifications.

---

## 12. Services

### TicketNotificationService
**File:** `app/Services/TicketNotificationService.php`

This feature uses `TicketNotificationService::send($user, $notification, 'account')` to dispatch `BrigStatusUpdatedNotification`. The service handles channel selection based on the user's notification preferences and account type. No changes were made to the service itself.

---

## 13. Activity Log Entries

| Action String | Logged By | Subject Model | When Logged |
|---------------|-----------|---------------|-------------|
| `user_put_in_brig` | `PutUserInBrig` | `User` (target) | When user is placed in brig |
| `user_released_from_brig` | `ReleaseUserFromBrig` | `User` (target) | When user is released |
| `brig_status_updated` | `UpdateBrigStatus` | `User` (target) | Reason/timer change or quick release |
| `permanent_brig_set` | `UpdateBrigStatus` | `User` (target) | When permanent flag is set |
| `permanent_brig_removed` | `UpdateBrigStatus` | `User` (target) | When permanent flag is removed |
| `brig_appeal_submitted` | `InBrigCard` | `User` (target) | When brigged user submits an appeal |

---

## 14. Data Flow Diagrams

### Warden Updates Brig Status

```
Warden clicks "Save Changes" in BrigStatusManager (on widget, profile, or thread)
  -> BrigStatusManager::saveStatus()
    -> $this->authorize('put-in-brig')  [403 if not warden]
    -> $this->validate(['brigReason' => 'required|min:5', 'brigExpiresAt' => 'nullable|date'])
    -> Computes diff: $newReason, $newExpiresAt, $permanent
    -> UpdateBrigStatus::run(target, admin, newReason, newExpiresAt, permanent, notify)
      [Path D: reason/timer change]
        -> User::save() [brig_reason, brig_expires_at updated]
        -> RecordActivity::run($user, 'brig_status_updated', summary)
        -> BrigStatusUpdatedNotification sent if notify=true
      [Path B: permanent set]
        -> User::save() [permanent_brig_at=now, brig_expires_at=null, next_appeal_available_at=null]
        -> RecordActivity::run($user, 'permanent_brig_set', ...)
        -> BrigStatusUpdatedNotification sent if notify=true
      [Path C: permanent removed]
        -> User::save() [permanent_brig_at=null, next_appeal_available_at recalculated]
        -> RecordActivity::run($user, 'permanent_brig_removed', ...)
        -> BrigStatusUpdatedNotification ALWAYS sent
    -> $this->user->refresh()
    -> Flux::toast('Brig status updated.', 'Saved', variant: 'success')
    -> $this->dispatch('brig-status-updated')
```

### Warden Quick Releases a User

```
Warden enters release reason and clicks "Release from Brig"
  -> BrigStatusManager::quickRelease()
    -> $this->authorize('put-in-brig')
    -> $this->validate(['brigReleaseReason' => 'required|min:5'])
    -> UpdateBrigStatus::run(target, admin, releaseReason: $reason)
      -> ReleaseUserFromBrig::run($target, $admin, $reason)
        -> Clears all brig fields on user
        -> Restores Minecraft accounts (whitelist add)
        -> Restores Discord accounts (removes In Brig role, re-syncs)
        -> Records activity log
        -> Sends UserReleasedFromBrigNotification
      -> RecordActivity::run($target, 'brig_status_updated', "Quick release by...")
    -> Flux::toast('{name} has been released from the Brig.', 'Released', variant: 'success')
    -> $this->dispatch('brig-status-updated')
```

### Brigged User Submits a Disciplinary Appeal

```
Brigged user clicks "Submit Appeal" on in-brig-card
  -> InBrigCard appeal modal opens
  -> User enters appeal message (min 20 chars)
  -> InBrigCard::submitAppeal()
    -> Validates message
    -> Creates Thread with type=BrigAppeal, created_by=$user->id
    -> Creates initial Message in the thread
    -> Adds thread participants:
       - Brig Wardens (role 'Brig Warden')
       - All-roles staff (has_all_roles_at is set)
       - Admins (admin_granted_at is set)
    -> RecordActivity::run($user, 'brig_appeal_submitted', ...)
    -> Sets next_appeal_available_at = now() + 7 days
    -> Flux::toast('Appeal submitted.', variant: 'success')
```

### Warden Views BrigAppeal Thread and Manages Brig Status

```
Warden opens /discussions/{thread} for a BrigAppeal thread
  -> ViewTopic::mount()
    -> Checks thread type is BrigAppeal or Topic (else 404)
    -> $this->authorize('view', $thread)
  -> Thread renders with "Manage Brig Status" button (visible only to put-in-brig)
  -> Warden clicks "Manage Brig Status"
    -> $flux.modal('brig-appeal-manage-modal').show()
    -> Modal renders BrigStatusManager for $thread->createdBy (the brigged user)
  -> Warden makes changes in modal
    -> BrigStatusManager::saveStatus() or quickRelease()
```

---

## 15. Configuration

Not applicable for this feature — no new environment variables or config keys.

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/Brig/PermanentConfinementTest.php` | 8 | permanent_brig_at model behavior, CheckBrigTimers exclusion, in-brig-card UI |
| `tests/Feature/Brig/BrigAppealThreadTypeTest.php` | 11 | BrigAppeal enum, appeal thread creation, participant auto-add, topics list exclusion, view-topic routing |
| `tests/Feature/Actions/Actions/UpdateBrigStatusTest.php` | 14 | All UpdateBrigStatus paths: reason/timer, permanent set/remove, notifications, quick release |
| `tests/Feature/Livewire/BrigStatusManagerTest.php` | 11 | Authorization, field population, validation, permanent toggle, quick release, profile page embedding |
| `tests/Feature/Livewire/BrigWardenWidgetTest.php` | 11 | Authorization, approaching release filters, open appeals badge, total count, search |
| `tests/Feature/Livewire/BrigAppealManageButtonTest.php` | 3 | "Manage Brig Status" button visibility in thread view |
| `tests/Feature/Livewire/BrigLogPageTest.php` | 7 | Authorization, brig action filtering, non-brig exclusion, newest-first ordering |

**Total new tests: 65**

### Test Case Inventory

**PermanentConfinementTest.php:**
- `canAppeal()` returns false when `permanent_brig_at` is set
- `canAppeal()` returns false for permanent user even when `next_appeal_available_at` is past
- `PutUserInBrig` sets `next_appeal_available_at` to `brig_expires_at` when expires_at provided
- `PutUserInBrig` defaults `next_appeal_available_at` to 24h when no expires_at
- `PutUserInBrig` sets `permanent_brig_at` and clears `next_appeal_available_at` when permanent=true
- `CheckBrigTimers` skips users with `permanent_brig_at` set
- `CheckBrigTimers` processes non-permanent users with expired timers
- in-brig-card shows "Permanently Confined" for users with `permanent_brig_at`

**BrigAppealThreadTypeTest.php:**
- `ThreadType::BrigAppeal` has the correct value `brig_appeal`
- `ThreadType::BrigAppeal` has a label `Brig Appeal`
- Submitting a disciplinary appeal creates a BrigAppeal thread
- Submitting a parental contact creates a Topic thread (not BrigAppeal)
- Brig Warden is added as participant when appeal is submitted
- Admin is added as participant when appeal is submitted
- All-roles staff is added as participant when appeal is submitted
- BrigAppeal threads do not appear in the topics list
- Topic threads continue to appear in the topics list
- view-topic renders BrigAppeal threads for participants
- view-topic returns 404 for BrigAppeal threads without matching participant

**UpdateBrigStatusTest.php:**
- Updates reason and logs activity
- Updates expiry and logs activity
- Clears expiry when null passed
- Sets permanent, clears expiry and appeal timer
- Logs `permanent_brig_set` action
- Removes permanent, recalculates appeal timer from `brig_expires_at`
- Removes permanent, sets 24h timer if no `brig_expires_at`
- Logs `permanent_brig_removed` action
- Sends `BrigStatusUpdatedNotification` when notify=true
- Skips notification when notify=false
- Always notifies when removing permanent (ignores notify flag)
- Quick release delegates to `ReleaseUserFromBrig`
- Quick release logs activity
- Quick release does not send `BrigStatusUpdatedNotification` (ReleaseUserFromBrig sends its own)

**BrigStatusManagerTest.php:**
- Non-warden cannot mount the component (403)
- Warden can mount the component (fields populated)
- Warden can update the brig reason
- Reason is required and at least 5 chars
- Non-warden cannot call saveStatus (403 on mount)
- Permanent toggle mounts as true when user has `permanent_brig_at`
- Changing `brigPermanent` forces `brigNotify` to true
- Quick release requires a reason
- Quick release releases the user from brig
- Profile page shows "Manage Brig Status" item for wardens when user is brigged
- Profile page does not show "Manage Brig Status" for non-wardens

**BrigWardenWidgetTest.php:**
- Non-warden cannot mount the widget (403)
- Warden can mount the widget
- Approaching release list includes users expiring within 7 days
- Approaching release list excludes users expiring beyond 7 days
- Approaching release list excludes users with no expiry
- Approaching release list excludes permanently confined users
- Open appeals count reflects open BrigAppeal threads
- Closed BrigAppeal threads are not counted in open appeals
- Total brigged count is accurate
- All brigged users list is searchable by name
- Widget is visible on dashboard for wardens

**BrigAppealManageButtonTest.php:**
- Warden sees "Manage Brig Status" button on BrigAppeal thread
- Non-warden does not see "Manage Brig Status" button on BrigAppeal thread
- "Manage Brig Status" button does not appear on regular Topic threads

**BrigLogPageTest.php:**
- Non-warden cannot mount the brig log page (403)
- Warden can mount the brig log page
- Brig log shows `user_put_in_brig` entries
- Brig log shows `brig_status_updated` entries
- Brig log shows `permanent_brig_set` entries
- Brig log excludes non-brig activity entries
- Brig log is ordered newest-first

### Coverage Gaps

- No test verifies the "notify" toggle interaction (e.g., checkbox disabled state when permanent unchanged)
- No test for the all-users modal sort functionality (sorting by `brig_type`, `brig_placed_at`, `brig_expires_at`)
- No test for the "View All" / total brigged badge click behavior (Alpine.js interaction not easily testable)
- No test for the dashboard.blade.php `@can('put-in-brig')` gate guard (widget embedded in non-Volt view)
- BrigStatusManager does not test what happens when `saveStatus()` is called with no changes (early return path in `UpdateBrigStatus`)
- No integration test for the ACP tab navigation (the new "Brig Log" tab appearing only for wardens)

---

## 17. File Map

**Models:**
- `app/Models/User.php` — primary model; brig state stored directly on user record

**Enums:**
- `app/Enums/BrigType.php` — brig type classification
- `app/Enums/ThreadType.php` — includes `BrigAppeal` case (added by this PRD)
- `app/Enums/ThreadStatus.php` — thread status values

**Actions:**
- `app/Actions/UpdateBrigStatus.php` — **new**: single entry point for all in-brig status modifications
- `app/Actions/PutUserInBrig.php` — **modified**: added `brig_placed_at = now()` and `permanent` param
- `app/Actions/ReleaseUserFromBrig.php` — called by `UpdateBrigStatus` for quick release (unmodified)
- `app/Actions/RecordActivity.php` — activity logging (unmodified)

**Notifications:**
- `app/Notifications/BrigStatusUpdatedNotification.php` — **new**: notifies user of brig status changes
- `app/Notifications/UserPutInBrigNotification.php` — existing, unmodified
- `app/Notifications/BrigTimerExpiredNotification.php` — existing, unmodified

**Console Commands:**
- `app/Console/Commands/CheckBrigTimers.php` — **modified**: added `whereNull('permanent_brig_at')` exclusion

**Volt Components:**
- `resources/views/livewire/brig/brig-status-manager.blade.php` — **new**: shared management modal component
- `resources/views/livewire/dashboard/brig-warden-widget.blade.php` — **new**: staff dashboard widget
- `resources/views/livewire/admin-manage-brig-log-page.blade.php` — **new**: ACP brig log tab
- `resources/views/livewire/dashboard/in-brig-card.blade.php` — **modified**: permanent confinement UI, BrigAppeal thread type, new participant logic
- `resources/views/livewire/topics/view-topic.blade.php` — **modified**: "Manage Brig Status" button and modal for BrigAppeal threads
- `resources/views/livewire/admin-control-panel-tabs.blade.php` — **modified**: added Brig Log tab to Logs section

**Views:**
- `resources/views/dashboard.blade.php` — **modified**: added BrigWardenWidget embed

**Gates:**
- `app/Providers/AuthServiceProvider.php` — gates: `put-in-brig`, `release-from-brig`

**Migrations:**
- `database/migrations/2026_02_20_080000_add_brig_fields_to_users_table.php`
- `database/migrations/2026_04_13_000001_add_permanent_brig_at_to_users_table.php`
- `database/migrations/2026_04_13_162600_add_brig_placed_at_to_users_table.php`

**Tests:**
- `tests/Feature/Brig/PermanentConfinementTest.php`
- `tests/Feature/Brig/BrigAppealThreadTypeTest.php`
- `tests/Feature/Actions/Actions/UpdateBrigStatusTest.php`
- `tests/Feature/Livewire/BrigStatusManagerTest.php`
- `tests/Feature/Livewire/BrigWardenWidgetTest.php`
- `tests/Feature/Livewire/BrigAppealManageButtonTest.php`
- `tests/Feature/Livewire/BrigLogPageTest.php`

**Config:** No new config keys.

---

## 18. Known Issues & Improvement Opportunities

1. **`brig_placed_at` not backfilled for existing records:** The migration adds the column with `nullable`, but users placed in the brig before this migration will have `brig_placed_at = null`. The "Date Placed" column in the all-users modal will display "—" for legacy records. A backfill migration using the activity log (`user_put_in_brig` entry) could populate these.

2. **Open appeals badge links to general discussions, not filtered:** The badge in the widget links to `discussions.index`, which shows all topics. There's no dedicated route or filter for BrigAppeal threads. A warden navigating there will see all discussions, not just appeals. A `?type=brig_appeal` filter on the topics list would improve this.

3. **`allBriggedUsers` in the widget is not paginated:** The all-users modal loads all brigged users in a single query. With a large community this could become a performance issue. Adding pagination or a virtual scroller to the modal would be a future improvement.

4. **`managingUser` computed property is not protected with `#[Locked]`:** The `managingUserId` public property in `BrigWardenWidget` accepts user-supplied integers. While `openManageModal()` authorizes `put-in-brig`, a crafted Livewire request could set `managingUserId` to any user ID. Adding `#[Locked]` to `managingUserId` (or moving it to a `#[Locked]` property) would prevent direct tampering.

5. **`saveStatus()` has no guard against concurrent edits:** If two wardens update the same user's brig status simultaneously, the second save will silently overwrite the first without warning. Optimistic locking (checking `updated_at` before saving) would prevent lost updates.

6. **Brig log requires `put-in-brig` but ACP requires `view-acp`:** A Brig Warden with `Staff Access` role can reach the ACP and see the Brig Log. However, if a warden lacks `Staff Access`, they cannot reach the ACP at all. The `AdminControlPanelController` gate check (`view-acp = Staff Access`) is the bottleneck — the brig log tab is unreachable for non-staff wardens (if such a configuration exists).

7. **No test for `updatedBrigPermanent()` disabling the notify checkbox:** The acceptance criteria mentions the notify checkbox is disabled when the permanent flag is unchanged. This is implemented via a `:disabled` Blade binding but has no Pest test coverage verifying the disabled state.
