# Cancelled Minecraft Account Management -- Technical Documentation

> **Audience:** Project owner, developers, AI agents
> **Generated:** 2026-03-30
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

This feature extends Minecraft account management to handle accounts in `Cancelled` or `Cancelling`
status — states that arise when the in-game verification flow is abandoned or times out.

Three distinct capabilities are provided:

| Actor | Capability | Mechanism |
|---|---|---|
| Admin | Permanently delete (force-delete) a Cancelled/Cancelling account | `ForceDeleteMinecraftAccount` action via admin profile page |
| Parent | Hard-delete a child's Cancelled/Cancelling account without RCON | `RemoveChildMinecraftAccount` action via parent portal |
| Parent | Restart verification for a child's Cancelled/Cancelling account | `ParentRegenerateVerificationCode` action via parent portal |

**Why Cancelled/Cancelling accounts are a special case:**

A Cancelled or Cancelling account was never successfully verified. The player was added to
the whitelist during the verification attempt, but the `/verify` command was never run (or
the code expired). The server-side whitelist state for these accounts is already cleaned up
by the time they reach Cancelled status, so neither admin force-delete nor parent hard-delete
needs to issue a whitelist-remove RCON command. The restart-verification flow, however, must
re-add the player to the whitelist before creating a new verification record.

---

## 2. Database Schema

### `minecraft_accounts` table

Built up across several migrations from `2026_02_17` through `2026_03_18`.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | bigint (PK) | No | Auto-increment |
| `user_id` | bigint (FK) | No | References `users.id`, cascade-delete |
| `username` | varchar | No | Minecraft in-game name; Bedrock names prefixed with `.` |
| `uuid` | varchar | Yes (unique) | Java UUID or Floodgate UUID for Bedrock |
| `bedrock_xuid` | varchar | Yes | Bedrock Xbox UID (added `2026_02_28`) |
| `avatar_url` | varchar | Yes | mc-heads.net avatar URL (added `2026_02_19`) |
| `account_type` | varchar | No | `java` or `bedrock` (`MinecraftAccountType` enum) |
| `status` | varchar | No | See Enums Reference; defaults to `active` |
| `is_primary` | boolean | No | Whether this is the user's primary account (added `2026_02_27`) |
| `verified_at` | timestamp | Yes | Null for unverified/cancelled accounts |
| `last_username_check_at` | timestamp | Yes | When username was last synced from Mojang |
| `created_at` | timestamp | No | |
| `updated_at` | timestamp | No | |

**Indexes:** `last_username_check_at`, unique on `uuid`.

**Cascade behaviour:** Deleting the parent `users` row cascades-deletes all related
`minecraft_accounts` rows. Force-deleting an individual `MinecraftAccount` row is a hard
(permanent) delete — there is no Laravel soft-delete on this model.

---

## 3. Models & Relationships

### `App\Models\MinecraftAccount`

**File:** `app/Models/MinecraftAccount.php`

**Relationships:**

- `user(): BelongsTo` — the `User` who owns this account.
- `rewards(): HasMany` — `MinecraftReward` records associated with the account.

**Fillable fields:** `user_id`, `username`, `uuid`, `bedrock_xuid`, `avatar_url`,
`account_type`, `status`, `is_primary`, `verified_at`, `last_username_check_at`.

**Casts:** `account_type` → `MinecraftAccountType`, `status` → `MinecraftAccountStatus`,
`is_primary` → `boolean`, `verified_at` → `datetime`, `last_username_check_at` → `datetime`.

**Query Scopes:**

| Scope | Filters to |
|---|---|
| `scopeActive` | `status = active` |
| `scopeVerifying` | `status = verifying` |
| `scopeCancelling` | `status = cancelling` |
| `scopeCancelled` | `status = cancelled` |
| `scopeRemoved` | `status = removed` |
| `scopePrimary` | `is_primary = true` |
| `scopeCountingTowardLimit` | `status IN (active, verifying, banned)` |
| `scopeWhereNormalizedUuid` | UUID match ignoring hyphens |

**RCON Command Helpers:**

- `whitelistAddCommand()` — returns `whitelist add <username>` for Java, `fwhitelist add <uuid>` for Bedrock.
- `whitelistRemoveCommand()` — returns `whitelist remove <username>` for Java, `fwhitelist remove <uuid>` for Bedrock.
- `syncUserCommand(string $rank, string $staffPosition)` — returns `lh syncuser` command, appending `-bedrock <uuid>` for Bedrock accounts.

---

## 4. Enums Reference

### `App\Enums\MinecraftAccountStatus`

**File:** `app/Enums/MinecraftAccountStatus.php`

Backed enum (`string`).

| Case | Value | Label | Badge Color | Description |
|---|---|---|---|---|
| `Verifying` | `verifying` | Pending Verification | `yellow` | Whitelist added; awaiting `/verify` in-game |
| `Active` | `active` | Active | `green` | Verified and active on the server |
| `Cancelling` | `cancelling` | Cancelling Verification | `amber` | Verification in the process of being cancelled (transitional) |
| `Cancelled` | `cancelled` | Cancelled | `red` | Verification was never completed; whitelist cleaned up |
| `Banned` | `banned` | Banned | `orange` | Account banned from the server |
| `Removed` | `removed` | Removed | `zinc` | Admin-revoked or user-removed; whitelist removed |
| `ParentDisabled` | `parent_disabled` | Disabled by Parent | `purple` | Parent toggled Minecraft access off |

**Cancelled/Cancelling vs Removed distinction:**

- `Removed` accounts were once `Active` and have had their whitelist slot removed via the
  normal revoke or parent-remove flow (RCON `whitelist remove` was issued).
- `Cancelled`/`Cancelling` accounts never finished verification. Their whitelist entry is
  already gone (or never fully activated). No RCON call is needed to remove them.

**Account limit counting:** Only `Active`, `Verifying`, and `Banned` statuses count toward
the per-user `max_minecraft_accounts` limit. `Cancelled`, `Cancelling`, and `Removed`
accounts do not count against the limit.

---

## 5. Authorization & Permissions

### `App\Policies\MinecraftAccountPolicy`

**File:** `app/Policies/MinecraftAccountPolicy.php`

#### Methods relevant to this feature

**`forceDelete(User $user, MinecraftAccount $minecraftAccount): bool`**

Returns `true` when:
- The account's status is `Removed`, `Verifying`, `Cancelled`, **or** `Cancelling`
- AND the authenticated user `isAdmin()`.

```php
return ($minecraftAccount->status === MinecraftAccountStatus::Removed
        || $minecraftAccount->status === MinecraftAccountStatus::Verifying
        || $minecraftAccount->status === MinecraftAccountStatus::Cancelled
        || $minecraftAccount->status === MinecraftAccountStatus::Cancelling)
    && $user->isAdmin();
```

**`revoke(User $user, MinecraftAccount $minecraftAccount): bool`**

Returns `true` when status is `Active` AND user has `User - Manager` role. This is the
existing admin revoke path for active accounts and is NOT used by this feature.

**`delete(User $user, MinecraftAccount $minecraftAccount): bool`**

Returns `true` when the user owns the account or is an admin. Not directly used for
Cancelled/Cancelling management (that goes through `forceDelete` for admins or is checked
inside the Action for parents).

#### Parent portal authorization

Parent-portal operations (`RemoveChildMinecraftAccount`, `ParentRegenerateVerificationCode`)
are NOT gated through the policy. Instead, each Action performs a direct relationship check:

```php
if (! $parent->children()->where('child_user_id', $child->id)->exists()) {
    return ['success' => false, 'message' => 'You do not have permission to manage this account.'];
}
```

This check confirms a `parent_child_links` row exists for the pair before proceeding.

#### Permissions matrix

| Operation | Who | Gate / Check |
|---|---|---|
| Force-delete Cancelled/Cancelling account | Admin only | `MinecraftAccountPolicy::forceDelete` |
| Hard-delete child's Cancelled/Cancelling account | Parent only | Inline parent-child relationship check in `RemoveChildMinecraftAccount` |
| Restart verification for child's Cancelled/Cancelling account | Parent only | Inline parent-child relationship check in `ParentRegenerateVerificationCode` |
| View parent portal | Authenticated parent | `view-parent-portal` gate |
| Staff view parent portal | Officer+ staff | `isAtLeastRank(StaffRank::Officer)` check in component mount |

---

## 6. Routes

**File:** `routes/web.php`

```php
// Parent portal — logged-in parent
Volt::route('/parent-portal', 'parent-portal.index')
    ->name('parent-portal.index')
    ->middleware(['auth', 'verified', 'ensure-dob']);

// Parent portal — staff read-only view of a specific parent
Volt::route('/parent-portal/{user}', 'parent-portal.index')
    ->name('parent-portal.show')
    ->middleware(['auth', 'verified', 'ensure-dob']);

// User profile — admin profile page where force-delete is surfaced
Route::get('/profile/{user:slug}', [UserController::class, 'show'])
    ->name('profile.show')
    ->middleware(['auth', 'can:view,user']);
```

The profile page (`profile.show`) embeds the `display-basic-details` Volt component which
renders the Minecraft account list with the admin force-delete button.

---

## 7. User Interface Components

### Admin Profile Page — `display-basic-details`

**File:** `resources/views/livewire/users/display-basic-details.blade.php`

#### Livewire class state (relevant properties)

| Property | Type | Purpose |
|---|---|---|
| `$accountToForceDelete` | `?int` | Holds the account ID pending confirmation |

#### Methods

**`confirmForceDelete(int $accountId): void`**

Verifies `@can('forceDelete', $account)` before setting `$accountToForceDelete` and opening
the `confirm-force-delete-mc-account` modal.

**`forceDeleteMinecraftAccount(): void`**

Re-verifies the policy (`$this->authorize('forceDelete', $account)`), dispatches
`ForceDeleteMinecraftAccount::run($account, Auth::user())`, closes the modal, refreshes the
user model, and shows a success or danger toast.

#### Blade rendering logic

Accounts are shown in a list. The rendered button depends on status:

- **`Removed` status:** Shows "Reactivate" (if `@can('reactivate')`) and "Delete" (if
  `@can('forceDelete')`).
- **`Cancelled` or `Cancelling` status:** Shows only a "Remove" button (if
  `@can('forceDelete')`). No reactivation option — these accounts were never verified.
- **Any other non-Removed status:** Shows a "Revoke" button (if `@can('revoke')`).

The "Remove" label (used for Cancelled/Cancelling) and "Delete" label (used for Removed)
both call the same `confirmForceDelete()` method and the same underlying action.

#### Modal

`confirm-force-delete-mc-account` — a Flux confirmation modal, opened programmatically by
`Flux::modal('confirm-force-delete-mc-account')->show()`.

---

### Parent Portal — `parent-portal/index`

**File:** `resources/views/livewire/parent-portal/index.blade.php`

#### Livewire class state (relevant properties)

| Property | Type | Purpose |
|---|---|---|
| `$accountToRemoveId` | `?int` | Account ID staged for the confirm-remove modal |
| `$accountToRemoveName` | `string` | Username shown in confirmation dialog |
| `$accountToRemoveChildName` | `string` | Child's name shown in confirmation dialog |
| `$childMcVerificationCodes` | `array` | Keyed by child user ID; holds active code strings |
| `$childMcExpiresAt` | `array` | Keyed by child user ID; ISO-8601 expiry timestamps |
| `$isStaffViewing` | `bool` | `#[Locked]`; true when a staff member views another parent's portal |

#### Methods relevant to this feature

**`confirmRemoveChildMcAccount(int $accountId): void`**

Used for Active accounts only. Checks parent-child ownership, then populates
`$accountToRemoveId`, `$accountToRemoveName`, and `$accountToRemoveChildName` before opening
the `confirm-remove-mc-account` modal. Blocked in staff-view mode.

**`removeChildMcAccount(): void`**

Called from the confirm modal. Dispatches `RemoveChildMinecraftAccount::run($parent, $accountToRemoveId)`.
Clears modal state, refreshes `$this->children`, and shows a toast.

**`removeChildCancelledMcAccount(int $accountId): void`**

Directly dispatches `RemoveChildMinecraftAccount::run($parent, $accountId)` without a
separate confirm modal. The confirmation is handled by a Livewire `wire:confirm` attribute
on the button itself. Blocked in staff-view mode.

**`restartChildMinecraftVerification(int $accountId): void`**

Dispatches `ParentRegenerateVerificationCode::run($account, $parent)`. On success, stores the
new code and expiry in `$childMcVerificationCodes[$childId]` and
`$childMcExpiresAt[$childId]`, resets the `$this->children` computed property, and shows a
success toast with the `/verify <code>` instruction. On failure, shows a danger toast with
the error message. Blocked in staff-view mode.

#### Blade rendering logic (Cancelled/Cancelling accounts)

In the Linked Accounts section for each child, each Minecraft account row is rendered based
on status:

- **`Active` status:** Shows an X-mark remove button (calls `confirmRemoveChildMcAccount`).
- **`Cancelled` or `Cancelling` status:** Shows two buttons side-by-side:
  - "Restart" (arrow-path icon) — calls `restartChildMinecraftVerification({{ $mc->id }})`.
  - X-mark remove button — calls `removeChildCancelledMcAccount({{ $mc->id }})` with a
    `wire:confirm` native confirmation dialog.
- Staff-view mode hides all action buttons for all statuses.

#### Modal

`confirm-remove-mc-account` — used for removing Active accounts. Cancelled/Cancelling
account removal bypasses this modal and uses `wire:confirm` inline instead.

---

## 8. Actions (Business Logic)

All actions use `Lorisleiva\Actions\Concerns\AsAction` and are invoked via `ClassName::run(...)`.

---

### `App\Actions\ForceDeleteMinecraftAccount`

**File:** `app/Actions/ForceDeleteMinecraftAccount.php`

**Signature:** `handle(MinecraftAccount $account, User $admin): array`

**Return shape:** `['success' => bool, 'message' => string]`

**Business rules:**

1. The `$admin` must pass `isAdmin()` — returns failure if not.
2. The account status must be `Removed`, `Verifying`, `Cancelled`, or `Cancelling` — returns
   failure for any other status (e.g., Active, Banned).
3. Captures `$username`, `$accountType`, and `$affectedUser` before deletion (the model will
   be gone after delete).
4. Calls `$account->delete()`. This is a hard delete (no soft-delete trait).
5. Records activity on the affected user (`minecraft_account_permanently_deleted`).
6. Returns success with the message "Minecraft account permanently deleted. The UUID is now released."

**No RCON call is made.** Cancelled/Cancelling accounts no longer hold a whitelist slot;
Removed and Verifying accounts have already had RCON cleanup performed earlier in their
lifecycle.

**Effect:** The row is permanently removed from `minecraft_accounts`. The UUID is released
and can be registered by any user.

---

### `App\Actions\RemoveChildMinecraftAccount`

**File:** `app/Actions/RemoveChildMinecraftAccount.php`

**Signature:** `handle(User $parent, int $accountId): array`

**Return shape:** `['success' => bool, 'message' => string]`

**Business rules:**

1. Finds the account with `MinecraftAccount::findOrFail($accountId)`.
2. Verifies the parent-child relationship via `$parent->children()->where('child_user_id', $child->id)->exists()`.
3. **Cancelled/Cancelling fast path:** If the account status is `Cancelled` or `Cancelling`,
   hard-deletes directly without any RCON call. Records activity
   (`minecraft_account_removed_by_parent`) and returns success.
4. **Active path:** If status is not `Active`, returns failure ("current state"). Otherwise:
   - Calls `MinecraftRconService::executeCommand($account->whitelistRemoveCommand(), ...)`.
   - If RCON fails, returns failure without changing any state.
   - On RCON success, resets rank via `lh setmember <username> default`.
   - Sets status to `Removed` and saves.
   - If the account was `is_primary`, clears the flag and calls `AutoAssignPrimaryAccount::run($child)`.
   - Records activity (`minecraft_account_removed_by_parent`).

**Note:** The Cancelled/Cancelling fast path does not touch RCON at all, since those accounts
are already off the whitelist.

---

### `App\Actions\ParentRegenerateVerificationCode`

**File:** `app/Actions/ParentRegenerateVerificationCode.php`

**Signature:** `handle(MinecraftAccount $account, User $parent): array`

**Return shape:** `['success' => bool, 'code' => string|null, 'expires_at' => Carbon|null, 'error' => string|null]`

**Business rules (in order):**

1. Verifies parent-child relationship. Returns error if not found.
2. Checks status is `Cancelled` or `Cancelling`. Returns error for any other status.
3. Checks `$child->isInBrig()`. Returns error if true.
4. Checks `$child->parent_allows_minecraft`. Returns error if false.
5. **Rate limit:** Counts pending `MinecraftVerification` records for the child's `user_id`
   created in the last hour. If count >= `lighthouse.minecraft_verification_rate_limit_per_hour`,
   returns error. Rate limit is scoped to the child, not the parent.
6. **Code generation:** Generates a 6-character alphanumeric code from the charset
   `2346789ABCDEFGHJKMNPQRTUVWXYZ` (visually unambiguous — no 0, O, 1, I, L, 5, S).
   Retries up to 100 times to ensure uniqueness. Returns error if 100 attempts are exhausted.
7. Computes `$expiresAt = now()->addMinutes(lighthouse.minecraft_verification_grace_period_minutes)`.
8. **Re-whitelist via RCON:** Calls `$rconService->executeCommand($account->whitelistAddCommand(), ...)`.
   If RCON fails (server offline), returns error without changing any state.
9. Updates account status to `Verifying`.
10. Creates a `MinecraftVerification` record with the new code, scoped to the child's `user_id`.
11. **Rollback on DB failure:** If either the status update or `MinecraftVerification::create()` throws, logs the error,
    issues a RCON `whitelist remove`, reverts account to its original status (`Cancelled` or `Cancelling`), and returns error.
12. Records activity on the child (`minecraft_verification_regenerated`).
13. Returns success with `code` and `expires_at`.

---

## 9. Notifications

This feature does not send any notifications. No `TicketNotificationService` calls or
`Notification::send()` calls are made by any of the three actions or the UI components.

---

## 10. Background Jobs

This feature does not dispatch any background jobs. All operations are synchronous within
the Livewire request lifecycle.

---

## 11. Console Commands & Scheduled Tasks

This feature does not introduce any Artisan console commands or scheduled tasks.

---

## 12. Services

### `App\Services\MinecraftRconService`

**File:** `app/Services/MinecraftRconService.php`

Used by `RemoveChildMinecraftAccount` (active-account path only) and
`ParentRegenerateVerificationCode` (whitelist add, and rollback whitelist remove on failure).
`ForceDeleteMinecraftAccount` does not use RCON at all.

**Method:** `executeCommand(string $command, string $commandType, ?string $target, ?User $user, array $meta): array`

- Connects to the Minecraft server via RCON (`thedudeguy/rcon` library).
- Logs every command attempt to `MinecraftCommandLog` (initial status `failed`; updated after
  the connection attempt completes).
- For `lh` commands, success is determined by the response starting with `"Success:"`.
- For all other commands (whitelist, fwhitelist), any successful connection is treated as success.
- Returns `['success' => bool, 'response' => string|null, 'error' => string|null]`.

**Used commands in this feature:**

| Command | Used by | When |
|---|---|---|
| `whitelist add <username>` / `fwhitelist add <uuid>` | `ParentRegenerateVerificationCode` | Re-whitelisting on verification restart |
| `whitelist remove <username>` / `fwhitelist remove <uuid>` | `ParentRegenerateVerificationCode` | Rollback only, if `MinecraftVerification::create()` fails |
| `whitelist remove <username>` / `fwhitelist remove <uuid>` | `RemoveChildMinecraftAccount` (active path) | Removing an Active account |
| `lh setmember <username> default` | `RemoveChildMinecraftAccount` (active path) | Rank reset after whitelist removal |

---

## 13. Activity Log Entries

All entries are written via `RecordActivity::run($model, 'action_key', 'Description.')` and
stored in the `activity_logs` table.

| Action key | Subject | Description template | Triggered by |
|---|---|---|---|
| `minecraft_account_permanently_deleted` | Affected user (`$affectedUser`) | `Admin {admin->name} permanently deleted {accountType->label()} account: {username}` | `ForceDeleteMinecraftAccount` |
| `minecraft_account_removed_by_parent` | Child user | `{parent->name} removed cancelled {accountType->label()} account: {username}` (Cancelled path) or `{parent->name} removed {accountType->label()} account: {username}` (Active path) | `RemoveChildMinecraftAccount` |
| `minecraft_verification_regenerated` | Child user | `{parent->name} restarted verification for {accountType->label()} account: {username}` | `ParentRegenerateVerificationCode` |

---

## 14. Data Flow Diagrams

### Flow 1 — Admin Force-Delete (Cancelled/Cancelling account)

```text
Admin visits /profile/{slug}
  └─► display-basic-details renders Minecraft account list
        └─► Account with status Cancelled/Cancelling shows "Remove" button
              (@can('forceDelete', $account) → MinecraftAccountPolicy::forceDelete)
  └─► Admin clicks "Remove"
        └─► confirmForceDelete($accountId)
              └─► Policy check passes → $accountToForceDelete = $accountId
              └─► Flux::modal('confirm-force-delete-mc-account')->show()
  └─► Admin confirms modal
        └─► forceDeleteMinecraftAccount()
              └─► $this->authorize('forceDelete', $account)
              └─► ForceDeleteMinecraftAccount::run($account, Auth::user())
                    └─► isAdmin() check
                    └─► Status check (Removed/Verifying/Cancelled/Cancelling)
                    └─► $account->delete() [hard delete, no RCON]
                    └─► RecordActivity: minecraft_account_permanently_deleted
                    └─► return ['success' => true, ...]
              └─► Flux::modal close
              └─► $user->refresh()
              └─► Flux::toast success
```

### Flow 2 — Parent Remove Cancelled/Cancelling Account

```text
Parent visits /parent-portal
  └─► index.blade.php renders children list
        └─► Each Minecraft account row checks status
              └─► Cancelled/Cancelling: shows Restart + X-mark buttons
  └─► Parent clicks X-mark (remove)
        └─► wire:confirm dialog (browser native)
  └─► Parent confirms
        └─► removeChildCancelledMcAccount($accountId)
              └─► RemoveChildMinecraftAccount::run($parent, $accountId)
                    └─► findOrFail($accountId)
                    └─► Parent-child relationship check
                    └─► Status is Cancelled/Cancelling → fast path
                          └─► $account->delete() [hard delete, NO RCON]
                          └─► RecordActivity: minecraft_account_removed_by_parent
                          └─► return ['success' => true, ...]
              └─► unset($this->children) [re-query]
              └─► Flux::toast success
```

### Flow 3 — Parent Restart Verification (Cancelled/Cancelling account)

```text
Parent visits /parent-portal
  └─► index.blade.php renders children list
        └─► Cancelled/Cancelling account shows "Restart" button
  └─► Parent clicks "Restart"
        └─► restartChildMinecraftVerification($accountId)
              └─► MinecraftAccount::findOrFail($accountId)
              └─► ParentRegenerateVerificationCode::run($account, $parent)
                    └─► Parent-child relationship check
                    └─► Status check (Cancelled/Cancelling only)
                    └─► isInBrig() check
                    └─► parent_allows_minecraft check
                    └─► Rate limit check (child's pending verifications in last hour)
                    └─► Generate unique 6-char code (up to 100 attempts)
                    └─► Compute expiresAt = now() + grace_period_minutes
                    └─► RCON: whitelist add (or fwhitelist add for Bedrock)
                          └─► If RCON fails → return error (no state change)
                    └─► $account->update(['status' => Verifying])
                    └─► MinecraftVerification::create([...])
                          └─► If DB create fails:
                                └─► RCON: whitelist remove (rollback)
                                └─► $account->update(['status' => Cancelled])
                                └─► Log error
                                └─► return error
                    └─► RecordActivity: minecraft_verification_regenerated
                    └─► return ['success' => true, 'code' => ..., 'expires_at' => ...]
              └─► $childMcVerificationCodes[$childId] = $result['code']
              └─► $childMcExpiresAt[$childId] = ...
              └─► unset($this->children) [re-query]
              └─► Flux::toast: "Verification restarted! Have the child run /verify {code} in-game."
              └─► Code box displayed in portal for parent to relay
```

---

## 15. Configuration

**File:** `config/lighthouse.php`

| Key | Env variable | Default | Description |
|---|---|---|---|
| `lighthouse.minecraft_verification_grace_period_minutes` | `MINECRAFT_VERIFICATION_GRACE_PERIOD_MINUTES` | `30` | How long a verification code remains valid (minutes). Used when computing `expires_at` for new `MinecraftVerification` records. |
| `lighthouse.minecraft_verification_rate_limit_per_hour` | `MINECRAFT_VERIFICATION_RATE_LIMIT_PER_HOUR` | `10` | Maximum number of pending verification attempts a child user may have in any rolling 60-minute window. Applies to the `ParentRegenerateVerificationCode` action. |

---

## 16. Test Coverage

### `ForceDeleteMinecraftAccount`

**File:** `tests/Feature/Minecraft/ForceDeleteMinecraftAccountTest.php`

| Test | Verifies |
|---|---|
| `admin can permanently delete a removed account` | Success path for Removed status |
| `regular user cannot permanently delete` | Non-admin blocked |
| `cannot permanently delete an active account` | Active status blocked |
| `records activity log for permanent deletion` | `minecraft_account_permanently_deleted` written |
| `releases UUID so it can be re-registered` | Row deleted from `minecraft_accounts` |
| `admin can permanently delete a cancelled account` | Success path for Cancelled status |
| `admin can permanently delete a cancelling account` | Success path for Cancelling status |
| `non-admin cannot permanently delete a cancelled account` | Non-admin blocked for Cancelled status |

### `RemoveChildMinecraftAccount`

**File:** `tests/Feature/Actions/Actions/RemoveChildMinecraftAccountTest.php`

| Test | Verifies |
|---|---|
| `removes an active minecraft account` | Active path: RCON called, status set to Removed |
| `rejects removal by non-parent` | Non-parent blocked |
| `rejects removal of non-active account` | Banned/other status blocked (non-Cancelled/Cancelling) |
| `fails gracefully when whitelist removal fails` | RCON failure → no state change |
| `records activity after removal` | `minecraft_account_removed_by_parent` written |
| `hard-deletes a cancelled child minecraft account without rcon` | Cancelled fast path: row deleted, no RCON, activity written |
| `hard-deletes a cancelling child minecraft account without rcon` | Cancelling fast path: row deleted |
| `rejects cancelled account removal by non-parent` | Non-parent blocked even for Cancelled status |
| `active account path is unaffected by cancelled account changes` | Active path still works correctly |

### `ParentRegenerateVerificationCode`

**File:** `tests/Feature/Minecraft/ParentRegenerateVerificationCodeTest.php`

| Test | Verifies |
|---|---|
| `parent can restart verification for a cancelled child account` | Cancelled → Verifying, code returned, MinecraftVerification row created |
| `parent can restart verification for a cancelling child account` | Cancelling → Verifying |
| `records activity on child account after restart` | `minecraft_verification_regenerated` written |
| `fails when parent-child relationship does not exist` | Non-parent blocked |
| `fails when child is in brig` | Brig check blocks restart |
| `fails when minecraft access is parent-disabled` | `parent_allows_minecraft = false` blocks restart |
| `applies rate limiting against child user id not parent` | Rate limit is child-scoped, not parent-scoped |
| `does not change account state when server is offline` | RCON failure → status unchanged, no verification row |

All tests use Pest (`uses()->group(...)`) and mock `MinecraftRconService` where RCON
calls are expected.

---

## 17. File Map

```text
app/
  Actions/
    ForceDeleteMinecraftAccount.php        # Admin force-delete action
    RemoveChildMinecraftAccount.php        # Parent remove child account action
    ParentRegenerateVerificationCode.php   # Parent restart verification action
  Enums/
    MinecraftAccountStatus.php             # All status cases + label() + color()
  Models/
    MinecraftAccount.php                   # Model, scopes, RCON helpers
  Policies/
    MinecraftAccountPolicy.php             # forceDelete, revoke, delete, reactivate
  Services/
    MinecraftRconService.php               # RCON bridge; used by Restart and Active-path Remove

config/
  lighthouse.php                           # minecraft_verification_* settings

database/
  factories/
    MinecraftAccountFactory.php            # cancelled(), cancelling(), active(), etc.
  migrations/
    2026_02_17_064252_create_minecraft_accounts_table.php
    2026_02_19_000001_add_avatar_url_to_minecraft_accounts_table.php
    2026_02_20_060607_add_status_and_command_id_to_minecraft_accounts_table.php
    2026_02_20_063640_make_verified_at_nullable_on_minecraft_accounts_table.php
    2026_02_20_072300_make_verified_at_nullable_on_minecraft_accounts_table.php
    2026_02_21_100000_add_banned_status_to_minecraft_accounts_table.php
    2026_02_22_000000_drop_command_id_from_minecraft_accounts_table.php
    2026_02_26_000000_add_removed_status_to_minecraft_accounts_table.php
    2026_02_27_100000_add_is_primary_to_minecraft_accounts_table.php
    2026_02_28_043322_add_bedrock_xuid_to_minecraft_accounts_table.php
    2026_03_01_000002_add_parent_disabled_status_to_minecraft_accounts_table.php
    2026_03_18_040000_add_cancelling_status_to_minecraft_accounts_table.php

resources/views/livewire/
  users/
    display-basic-details.blade.php        # Admin profile; force-delete button + modal
  parent-portal/
    index.blade.php                        # Parent portal; restart + remove buttons

routes/
  web.php                                  # parent-portal.index, parent-portal.show, profile.show

tests/Feature/
  Minecraft/
    ForceDeleteMinecraftAccountTest.php
    ParentRegenerateVerificationCodeTest.php
  Actions/Actions/
    RemoveChildMinecraftAccountTest.php
```

---

## 18. Known Issues & Improvement Opportunities

1. **`ForceDeleteMinecraftAccount` performs no authorization via policy in the action itself.**
   The action relies on the Livewire component to check `$this->authorize('forceDelete', $account)`
   before calling the action. The action only re-checks `isAdmin()` inline. If the action
   were ever called from a context that does not pre-authorize (e.g., a CLI command or a
   different component), the admin check inside the action is the only guard. A future
   improvement could call `$admin->can('forceDelete', $account)` inside `handle()` instead
   of duplicating the status checks already in the policy.

2. **`removeChildCancelledMcAccount` uses `wire:confirm` (browser native dialog) instead of
   a Flux modal.** This differs from `confirmRemoveChildMcAccount` (active accounts), which
   opens a full `confirm-remove-mc-account` Flux modal. The browser native dialog is less
   visually consistent with the rest of the UI. A Flux confirmation modal would be more
   consistent.

3. **No notification is sent to the parent or child when verification is restarted.** If the
   parent is not actively watching the portal, they may not know to relay the new code to
   the child promptly.

4. **Rate limit is enforced only on `ParentRegenerateVerificationCode`, not on the initial
   `GenerateVerificationCode` action.** The rate limit protects against repeated restart
   attempts, but there is no shared enforcement point across both actions. The rate limit
   configuration key (`minecraft_verification_rate_limit_per_hour`) is the same, suggesting
   it was intended to be shared, but each action queries the verification table independently.

5. **Code uniqueness check is O(n) sequential.** The `do/while` loop in
   `ParentRegenerateVerificationCode` (and presumably in `GenerateVerificationCode`) performs
   up to 100 database queries to confirm uniqueness. At low verification volumes this is
   inconsequential, but it could be replaced with a unique index constraint + retry-on-
   constraint-violation approach.

6. **The `Cancelling` status is a transitional state** whose lifecycle is defined elsewhere
   (likely a scheduled job or the in-game plugin). The documentation for how an account moves
   from `Verifying` → `Cancelling` → `Cancelled` lives outside this feature's scope.
