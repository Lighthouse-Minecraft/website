# Minecraft Permission Hardening and Bulk Repair -- Technical Documentation

> **Audience:** Project owner, developers, AI agents
> **Generated:** 2026-03-24
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

This feature encompasses two closely related hardening efforts: (1) making the `MinecraftRconService` treat custom `lh` plugin commands as failed unless the server responds with a `Success:` prefix, and (2) consolidating all desired-state Minecraft synchronisation into a single, authoritative code path shared by lifecycle events and an administrative bulk-repair command.

Prior to this work, `lh` plugin commands (e.g., `lh setmember`, `lh setstaff`, `lh removestaff`) were treated as successful as long as RCON delivered any response, including empty strings or error messages from the plugin. The hardening change means any `lh` command response that does not start with `Success:` is now logged as `failed`, surfacing silent permission-drift that was previously invisible in logs.

The unified sync path is implemented as `SyncMinecraftAccount` (single account) and `SyncMinecraftPermissions` (all active accounts for a user). Every lifecycle transition — promotion, demotion, brig release, parent permission toggle, and account reactivation — now funnels through these two actions instead of calling disparate rank and staff actions independently. This guarantees that whitelist eligibility, member rank, and staff position are always evaluated together and applied atomically from the server's perspective.

The `minecraft:repair-permissions` Artisan command provides operators with a bulk-repair tool that iterates over all active Minecraft accounts in the database, applies the same desired-state logic as `SyncMinecraftAccount`, and produces a detailed summary of actions taken. A `--dry-run` flag allows safe inspection before any RCON commands are sent, and a `--pace` flag throttles command throughput to avoid flooding the Minecraft server.

The primary audiences are server administrators (who run `minecraft:repair-permissions` after deployments, server restarts, or data migrations) and the application itself (which calls `SyncMinecraftPermissions` on every membership or staff state change).

---

## 2. Database Schema

### `minecraft_accounts` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | No | auto | Primary key |
| `user_id` | bigint unsigned | No | — | FK to `users.id`, cascades on delete |
| `username` | varchar | No | — | Minecraft Java username or Bedrock gamertag |
| `uuid` | varchar | No | — | Unique; Java UUID or Floodgate UUID for Bedrock |
| `bedrock_xuid` | varchar | Yes | null | Bedrock Xbox User ID |
| `avatar_url` | varchar | Yes | null | Cached avatar image URL |
| `account_type` | varchar | No | — | `java` or `bedrock` |
| `status` | enum | No | `verifying` | `verifying`, `active`, `cancelling`, `cancelled`, `banned`, `removed`, `parent_disabled` |
| `is_primary` | boolean | No | false | Whether this is the user's primary account |
| `verified_at` | timestamp | Yes | null | When verification completed |
| `last_username_check_at` | timestamp | Yes | null | Last username refresh check |
| `created_at` | timestamp | Yes | null | |
| `updated_at` | timestamp | Yes | null | |

**Indexes:** `status` (single), `last_username_check_at` (single), `uuid` (unique)
**Foreign Keys:** `user_id` → `users.id` ON DELETE CASCADE
**Migrations:**
- `database/migrations/2026_02_17_064252_create_minecraft_accounts_table.php`
- `database/migrations/2026_02_19_000001_add_avatar_url_to_minecraft_accounts_table.php`
- `database/migrations/2026_02_20_060607_add_status_and_command_id_to_minecraft_accounts_table.php`
- `database/migrations/2026_02_20_063640_make_verified_at_nullable_on_minecraft_accounts_table.php`
- `database/migrations/2026_02_20_072300_make_verified_at_nullable_on_minecraft_accounts_table.php`
- `database/migrations/2026_02_21_100000_add_banned_status_to_minecraft_accounts_table.php`
- `database/migrations/2026_02_22_000000_drop_command_id_from_minecraft_accounts_table.php`
- `database/migrations/2026_02_26_000000_add_removed_status_to_minecraft_accounts_table.php`
- `database/migrations/2026_02_27_100000_add_is_primary_to_minecraft_accounts_table.php`
- `database/migrations/2026_02_28_043322_add_bedrock_xuid_to_minecraft_accounts_table.php`
- `database/migrations/2026_03_01_000002_add_parent_disabled_status_to_minecraft_accounts_table.php`
- `database/migrations/2026_03_18_040000_add_cancelling_status_to_minecraft_accounts_table.php`

### `minecraft_command_logs` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | No | auto | Primary key |
| `user_id` | bigint unsigned | Yes | null | FK to `users.id`, nulls on delete |
| `command` | varchar | No | — | Full RCON command string |
| `command_type` | varchar | No | — | Category: `whitelist`, `rank`, `staff`, etc. |
| `target` | varchar | Yes | null | Player or entity name |
| `status` | enum | No | — | `success`, `failed`, `timeout` |
| `response` | text | Yes | null | Raw RCON response string |
| `error_message` | text | Yes | null | Failure reason |
| `ip_address` | varchar | Yes | null | Originating request IP |
| `meta` | json | Yes | null | Additional context (action, department, etc.) |
| `executed_at` | timestamp | No | current | When the command was sent |
| `execution_time_ms` | integer | Yes | null | Round-trip time in milliseconds |
| `created_at` | timestamp | Yes | null | |
| `updated_at` | timestamp | Yes | null | |

**Indexes:** `command_type` (single), `status` (single), `(command_type, status)` (composite), `executed_at` (single)
**Foreign Keys:** `user_id` → `users.id` ON DELETE SET NULL
**Migration:** `database/migrations/2026_02_17_064300_create_minecraft_command_logs_table.php`

---

## 3. Models & Relationships

### MinecraftAccount (`app/Models/MinecraftAccount.php`)

**Relationships:**

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `user()` | belongsTo | User | Account owner |
| `rewards()` | hasMany | MinecraftReward | In-game reward records |

**Scopes:**

| Scope | Filters |
|-------|---------|
| `scopeActive` | `status = active` |
| `scopeVerifying` | `status = verifying` |
| `scopeCancelling` | `status = cancelling` |
| `scopeCancelled` | `status = cancelled` |
| `scopeRemoved` | `status = removed` |
| `scopePrimary` | `is_primary = true` |
| `scopeCountingTowardLimit` | `status IN (active, verifying, banned)` |
| `scopeWhereNormalizedUuid` | UUID match ignoring hyphens |

**Key Methods:**
- `whitelistAddCommand(): string` -- returns `whitelist add <username>` for Java or `fwhitelist add <uuid>` for Bedrock
- `whitelistRemoveCommand(): string` -- returns `whitelist remove <username>` for Java or `fwhitelist remove <uuid>` for Bedrock

**Casts:**
- `account_type` => `MinecraftAccountType`
- `status` => `MinecraftAccountStatus`
- `is_primary` => `boolean`
- `verified_at` => `datetime`
- `last_username_check_at` => `datetime`

### MinecraftCommandLog (`app/Models/MinecraftCommandLog.php`)

**Relationships:**

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `user()` | belongsTo | User | User who triggered the command |

**Scopes:**

| Scope | Filters |
|-------|---------|
| `scopeSuccessful` | `status = success` |
| `scopeFailed` | `status = failed` |

**Casts:**
- `meta` => `array`
- `executed_at` => `datetime`

---

## 4. Enums Reference

### MinecraftAccountStatus (`app/Enums/MinecraftAccountStatus.php`)

| Case | Value | Label | Color | Notes |
|------|-------|-------|-------|-------|
| `Verifying` | `verifying` | Pending Verification | yellow | During token-based verification flow |
| `Active` | `active` | Active | green | On whitelist, eligible |
| `Cancelling` | `cancelling` | Cancelling Verification | amber | Verification being cancelled |
| `Cancelled` | `cancelled` | Cancelled | red | Verification cancelled |
| `Banned` | `banned` | Banned | orange | Temporarily blocked (e.g., in brig) |
| `Removed` | `removed` | Removed | zinc | Manually removed by user or admin |
| `ParentDisabled` | `parent_disabled` | Disabled by Parent | purple | Parent toggled Minecraft off |

**Helper methods:** `label(): string`, `color(): string`

### MinecraftAccountType (`app/Enums/MinecraftAccountType.php`)

| Case | Value | Label |
|------|-------|-------|
| `Java` | `java` | Java Edition |
| `Bedrock` | `bedrock` | Bedrock Edition |

**Helper methods:** `label(): string`

### MembershipLevel (`app/Enums/MembershipLevel.php`)

| Case | Value | Label | Minecraft Rank | Notes |
|------|-------|-------|----------------|-------|
| `Drifter` | `0` | Drifter | null | Below server threshold |
| `Stowaway` | `1` | Stowaway | null | Below server threshold |
| `Traveler` | `2` | Traveler | `traveler` | Minimum eligible level |
| `Resident` | `3` | Resident | `resident` | |
| `Citizen` | `4` | Citizen | `citizen` | |

**Helper methods:** `label(): string`, `discordRoleId(): ?string`, `minecraftRank(): ?string`

The `minecraftRank()` method is the authoritative source for eligibility: a `null` return value means the user should not be on the Minecraft server.

### StaffDepartment (`app/Enums/StaffDepartment.php`)

| Case | Value | Label |
|------|-------|-------|
| `Command` | `command` | Command |
| `Chaplain` | `chaplain` | Chaplain |
| `Engineer` | `engineer` | Engineer |
| `Quartermaster` | `quartermaster` | Quartermaster |
| `Steward` | `steward` | Steward |

**Helper methods:** `label(): string`, `discordRoleId(): ?string`

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

| Gate Name | Who Can Pass | Logic Summary |
|-----------|-------------|---------------|
| `link-minecraft-account` | Traveler+ members not in brig with parent permission | `isAtLeastLevel(Traveler) && !in_brig && parent_allows_minecraft` |
| `view-mc-command-log` | Users with "Logs - Viewer" role | `hasRole('Logs - Viewer')` |

### Policies

No Eloquent Policy classes are involved in this feature. Authorization for account management is handled via gates in `AuthServiceProvider` and role checks. The `minecraft:repair-permissions` Artisan command is protected by operating-system-level access (only users with shell access to the server can run it).

### Permissions Matrix

| User Type | Run repair-permissions | Sync permissions (automatic) | View command log |
|-----------|----------------------|------------------------------|-----------------|
| Drifter / Stowaway | No (shell access required) | Yes (lifecycle-triggered) | No |
| Traveler / Resident / Citizen | No (shell access required) | Yes (lifecycle-triggered) | No |
| Staff (no Logs role) | No (shell access required) | Yes (lifecycle-triggered) | No |
| Staff with "Logs - Viewer" | No (shell access required) | Yes (lifecycle-triggered) | Yes |
| Server operator (SSH) | Yes | N/A | N/A |

---

## 6. Routes

Not applicable for this feature. The feature is entirely driven through Action classes called from other lifecycle actions and from the Artisan console. There are no dedicated HTTP routes.

---

## 7. User Interface Components

Not applicable for this feature. The `minecraft:repair-permissions` command is a CLI-only tool. Permission synchronisation happens silently in the background as a side-effect of promotion, demotion, brig release, parent permission toggle, and account reactivation.

The `minecraft_command_logs` table is viewable through the Admin Control Panel via the `view-mc-command-log` gate, but that ACP component is documented in the Admin Control Panel feature document, not here.

---

## 8. Actions (Business Logic)

### SyncMinecraftAccount (`app/Actions/SyncMinecraftAccount.php`)

**Signature:** `handle(MinecraftAccount $account): array`

**Return type:**
```php
array{
    eligible: bool,
    whitelist: array{success: bool, action: string},
    rank: array{success: bool, rank: string}|null,
    staff: array{success: bool, action: string, department: string|null}|null,
}
```

**Eligibility determination:**
```php
$rank = $user->membership_level->minecraftRank();  // null = ineligible
$eligible = $rank !== null && !$user->isInBrig() && $user->parent_allows_minecraft;
```

**Step-by-step logic (ineligible path):**
1. Evaluates eligibility from the account owner's `membership_level`, `isInBrig()`, and `parent_allows_minecraft`
2. Sends `whitelistRemoveCommand()` via `MinecraftRconService::executeCommand()` with meta `['action' => 'sync_remove_ineligible']`
3. Returns `eligible: false`, `whitelist: {action: 'remove'}`, `rank: null`, `staff: null`

**Step-by-step logic (eligible path):**
1. Sends `whitelistAddCommand()` via RCON with meta `['action' => 'sync_add_eligible']`
2. Sends `lh setmember <username> <rank>` via RCON with meta `['action' => 'sync_rank', 'membership_level' => ...]`
3. Logs activity: `RecordActivity::run($user, 'minecraft_rank_synced', "Synced Minecraft rank to {$rank} for {$account->username}")`
4. If user has `staff_department` set: sends `lh setstaff <username> <department>` and logs `minecraft_staff_position_set`
5. If user has no `staff_department`: sends `lh removestaff <username>` and logs `minecraft_staff_position_removed`
6. Returns full result array with `eligible: true`, whitelist/rank/staff outcomes

**Called by:**
- `SyncMinecraftPermissions::run()` (iterates each active account)
- `ReactivateMinecraftAccount::run()` (single account on reactivation)
- `ReleaseUserFromBrig::run()` (each previously-banned account on brig release)
- `UpdateChildPermission::toggleMinecraft()` (each affected account on parent toggle)
- `CompleteVerification::handle()` (newly-verified account after verification succeeds)

---

### SyncMinecraftPermissions (`app/Actions/SyncMinecraftPermissions.php`)

**Signature:** `handle(User $user): void`

**Step-by-step logic:**
1. Fetches all active accounts: `$user->minecraftAccounts()->active()->get()`
2. Calls `SyncMinecraftAccount::run($account)` for each account
3. Skips `Removed`, `Verifying`, `Cancelled`, `Banned`, and `ParentDisabled` accounts (only `active` scope is queried)

**Called by:**
- `PromoteUser::run()` (after membership level is saved)
- `DemoteUser::run()` (after membership level is saved)

---

### PromoteUser (`app/Actions/PromoteUser.php`)

**Signature:** `handle(User $user, MembershipLevel $maxLevel = MembershipLevel::Citizen)`

**Minecraft-relevant steps:**
1. Advances `membership_level` by one step
2. Calls `SyncMinecraftPermissions::run($user)` to push the new rank to all active accounts
3. Calls `SyncDiscordRoles::run($user)` (Discord, not Minecraft)

---

### DemoteUser (`app/Actions/DemoteUser.php`)

**Signature:** `handle(User $user, MembershipLevel $minLevel = MembershipLevel::Drifter)`

**Minecraft-relevant steps:**
1. Decrements `membership_level` by one step
2. Calls `SyncMinecraftPermissions::run($user)` to push the new (lower) rank or remove the user from the whitelist if now below threshold
3. Calls `SyncDiscordRoles::run($user)` (Discord, not Minecraft)

---

### ReleaseUserFromBrig (`app/Actions/ReleaseUserFromBrig.php`)

**Signature:** `handle(User $target, User $admin, string $reason, bool $notify = true): void`

**Minecraft-relevant steps:**
1. Clears all brig fields on `$target`
2. Determines `$mcRestoreStatus`: `Active` if `parent_allows_minecraft`, else `ParentDisabled`
3. For each `Banned` Minecraft account:
   - Sets `status` to `$mcRestoreStatus`
   - If status is `Active`: calls `SyncMinecraftAccount::run($account)` to restore whitelist and rank
   - Wrapped in `try/catch`; failures are logged but do not abort the release
4. Continues to Discord restoration (separate concern)

---

### ReactivateMinecraftAccount (`app/Actions/ReactivateMinecraftAccount.php`)

**Signature:** `handle(MinecraftAccount $account, User $user): array`

**Minecraft-relevant steps:**
1. Guards: status must be `Removed`, not at account limit, not in brig
2. Sets `status = Active` and saves
3. Calls `AutoAssignPrimaryAccount::run($owner)` if needed
4. Calls `SyncMinecraftAccount::run($account)` to whitelist-add and set rank/staff
5. If `whitelist.success` is false, reverts `status` back to `Removed` and returns failure
6. Logs `minecraft_account_reactivated` activity on success

---

### UpdateChildPermission (`app/Actions/UpdateChildPermission.php`)

**Signature:** `handle(User $child, User $parent, string $permission, bool $enabled): void`

The `minecraft` permission branch (`toggleMinecraft`):

**Disabling:**
1. Sets `parent_allows_minecraft = false` and saves
2. For each `Active` or `Verifying` account: sets `status = ParentDisabled` and calls `SyncMinecraftAccount::run($account)`
3. `SyncMinecraftAccount` evaluates `parent_allows_minecraft = false` → sends whitelist remove
4. Logs `parent_permission_changed` activity

**Enabling:**
1. Sets `parent_allows_minecraft = true` and saves
2. For each `ParentDisabled` account: sets `status = Active` and calls `SyncMinecraftAccount::run($account)`
3. `SyncMinecraftAccount` evaluates eligibility → if eligible, sends whitelist add, rank, and staff commands
4. Logs `parent_permission_changed` activity

---

### SyncMinecraftRanks (`app/Actions/SyncMinecraftRanks.php`) *(legacy, pre-unification)*

**Signature:** `handle(User $user): void`

Sends `lh setmember <username> <rank>` for each active account. This action predates the unified `SyncMinecraftAccount` path. It is still present and exercised by legacy tests in `MinecraftCommandsTest.php`, but lifecycle events now use `SyncMinecraftPermissions`/`SyncMinecraftAccount` instead.

---

### SyncMinecraftStaff (`app/Actions/SyncMinecraftStaff.php`) *(legacy, pre-unification)*

**Signature:** `handle(User $user, ?StaffDepartment $department = null): void`

Sends `lh setstaff <username> <department>` or `lh removestaff <username>` for each active account. Also predates the unified path and is exercised only by legacy tests. Lifecycle events now use `SyncMinecraftPermissions`/`SyncMinecraftAccount`.

---

## 9. Notifications

Not applicable for this feature. No notifications are sent by `SyncMinecraftAccount`, `SyncMinecraftPermissions`, or `minecraft:repair-permissions`. Notifications that accompany lifecycle events (e.g., brig release, promotion) are sent by the calling action (`ReleaseUserFromBrig`, `PromoteUser`), which are separate concerns.

---

## 10. Background Jobs

Not applicable for this feature. All RCON commands are executed synchronously inline. There are no queued jobs involved in permission synchronisation or bulk repair.

---

## 11. Console Commands & Scheduled Tasks

### `minecraft:repair-permissions`
**File:** `app/Console/Commands/RepairMinecraftPermissions.php`
**Scheduled:** No — manual invocation only
**Options:**

| Option | Default | Description |
|--------|---------|-------------|
| `--dry-run` | false | Print planned commands without sending RCON; RCON is never called |
| `--pace` | 1 | Seconds to sleep between outbound commands (use 0 in testing) |

**What it does:**
1. Announces `[DRY RUN]` or `[LIVE]` mode
2. Loads all `MinecraftAccount::active()->with('user')->get()`
3. For each account, applies the same eligibility logic as `SyncMinecraftAccount`:
   - **Eligible:** sends whitelist add, `lh setmember`, and `lh setstaff`/`lh removestaff`
   - **Ineligible:** sends whitelist remove with reason annotation (in dry-run: shown inline)
4. Throttles via `pauseIfNeeded()` between commands (skips delay before first command)
5. Prints a summary table: `Whitelist adds`, `Whitelist removes`, `Rank changes`, `Staff changes`, `Failures`
6. Returns `Command::SUCCESS` in all non-exception paths

**Note:** The repair command contains its own inline copy of the eligibility calculation rather than delegating to `SyncMinecraftAccount`. This is intentional to support the `--dry-run` flag without side effects; the two paths use the same logic and are verified to produce identical RCON commands by the consistency test suite in `MixedAccountScenariosTest.php`.

---

## 12. Services

### MinecraftRconService (`app/Services/MinecraftRconService.php`)

**Purpose:** Encapsulates RCON connections to the Minecraft server, logs every command to `minecraft_command_logs`, and applies the `lh` command hardening rule.

**Key methods:**

- `executeCommand(string $command, string $commandType, ?string $target, ?User $user, array $meta): array`
  - Creates a `MinecraftCommandLog` record immediately with `status = failed`
  - Calls `connectAndSend($command)` to open the RCON socket and transmit the command
  - **lh command hardening:** if `str_starts_with($command, 'lh ')`, the result is only `success` if `str_starts_with(trim($response), 'Success:')`. Any other response (empty string, error text, plugin error message) is logged as `failed` with the raw response in `error_message`.
  - **Non-lh commands** (e.g., `whitelist add`, `fwhitelist add`): any non-false response is treated as success. These commands are handled natively by Minecraft/Floodgate and do not use the `lh` plugin protocol.
  - Updates the log entry with final `status`, `response`, `error_message`, and `execution_time_ms`
  - Returns `['success' => bool, 'response' => string|null, 'error' => string|null]`

- `connectAndSend(string $command): array` *(protected)*
  - Opens a `Thedudeguy\Rcon` connection using `services.minecraft.rcon_*` config values
  - Returns `['connected' => bool, 'result' => string|false|null]`
  - Declared `protected` so tests can subclass and override with simulated responses without a real server

**Error paths:**
- Connection failure: `['connected' => false, 'result' => null]` → `error_message = 'Failed to connect to RCON server'`
- Command send failure: `result === false` → `error_message = 'Failed to send command to RCON server'`
- `lh` non-success response: `error_message = 'lh command returned non-success response: <raw>'`
- Exception thrown: `error_message = $e->getMessage()`

---

## 13. Activity Log Entries

| Action String | Logged By | Subject Model | Description |
|---------------|-----------|---------------|-------------|
| `minecraft_rank_synced` | `SyncMinecraftAccount` | User | `"Synced Minecraft rank to {$rank} for {$account->username}"` |
| `minecraft_staff_position_set` | `SyncMinecraftAccount` | User | `"Set Minecraft staff position to {$department->label()} for {$account->username}"` |
| `minecraft_staff_position_removed` | `SyncMinecraftAccount` | User | `"Removed Minecraft staff position for {$account->username}"` |
| `minecraft_account_reactivated` | `ReactivateMinecraftAccount` | User | `"Reactivated {$account_type->label()} account: {$account->username}"` |
| `minecraft_rank_synced` | `SyncMinecraftRanks` (legacy) | User | `"Synced Minecraft rank to {$rank} for {$account->username}"` |
| `minecraft_staff_position_set` | `SyncMinecraftStaff` (legacy) | User | `"Set Minecraft staff position to {$department->label()} for {$account->username}"` |
| `minecraft_staff_position_removed` | `SyncMinecraftStaff` (legacy) | User | `"Removed Minecraft staff position for {$account->username}"` |

Note: The repair command (`minecraft:repair-permissions`) does **not** write activity log entries. It writes RCON command logs to `minecraft_command_logs` with action metadata in the `meta` JSON column.

---

## 14. Data Flow Diagrams

### Lifecycle-Triggered Sync (Promotion Example)

```
Staff clicks "Promote" on user management page
  -> VoltComponent::promote()
    -> $this->authorize('...')
    -> PromoteUser::run($user)
      -> $user->membership_level = $nextLevel
      -> $user->save()
      -> RecordActivity::run($user, 'user_promoted', ...)
      -> SyncMinecraftPermissions::run($user)
        -> foreach $user->minecraftAccounts()->active() as $account
          -> SyncMinecraftAccount::run($account)
            -> evaluate: rank = $user->membership_level->minecraftRank()
            -> evaluate: eligible = rank !== null && !in_brig && parent_allows_minecraft
            [eligible path]
            -> MinecraftRconService::executeCommand('whitelist add <username>', ...)
              -> MinecraftCommandLog::create([status: 'failed', ...])
              -> connectAndSend('whitelist add <username>')
              -> response not lh command -> status = 'success'
              -> log->update([status: 'success', ...])
              -> return ['success' => true, ...]
            -> MinecraftRconService::executeCommand('lh setmember <username> <rank>', ...)
              -> MinecraftCommandLog::create([status: 'failed', ...])
              -> connectAndSend('lh setmember ...')
              -> lh command: check str_starts_with(response, 'Success:')
              -> if yes: status = 'success'; if no: status = 'failed' + error_message
              -> log->update([status: ..., ...])
              -> return ['success' => bool, ...]
            -> RecordActivity::run($user, 'minecraft_rank_synced', ...)
            -> if staff: executeCommand('lh setstaff ...') + log activity
            -> else: executeCommand('lh removestaff ...') + log activity
      -> SyncDiscordRoles::run($user)
      -> send promotion notification
  -> Flux::toast('User promoted', variant: 'success')
```

### Brig Release Flow

```
Admin clicks "Release from Brig"
  -> ReleaseUserFromBrig::run($target, $admin, $reason)
    -> clear brig fields, $target->save()
    -> $mcRestoreStatus = parent_allows_minecraft ? Active : ParentDisabled
    -> foreach $target->minecraftAccounts()->where('status', Banned) as $account
      -> $account->status = $mcRestoreStatus
      -> $account->save()
      -> if Active: SyncMinecraftAccount::run($account)
           -> whitelist add + lh setmember + lh setstaff/removestaff
    -> RecordActivity::run($target, 'user_released_from_brig', ...)
    -> TicketNotificationService::send($target, UserReleasedFromBrigNotification)
```

### Parent Disabling Minecraft

```
Parent toggles Minecraft off on Parent Portal
  -> UpdateChildPermission::run($child, $parent, 'minecraft', false)
    -> $child->parent_allows_minecraft = false
    -> $child->save()
    -> foreach Active or Verifying accounts:
      -> $account->status = ParentDisabled
      -> $account->save()
      -> SyncMinecraftAccount::run($account)
           -> eligible = false (parent_allows_minecraft is false)
           -> MinecraftRconService::executeCommand('whitelist remove <username>', ...)
           -> return ['eligible' => false, 'whitelist' => {action: 'remove'}, ...]
    -> RecordActivity::run($child, 'parent_permission_changed', ...)
```

### Account Reactivation Flow

```
User clicks "Reactivate" on their Minecraft settings page
  -> ReactivateMinecraftAccount::run($account, $user)
    -> guard: status == Removed, not at limit, not in brig
    -> $account->status = Active; $account->save()
    -> AutoAssignPrimaryAccount::run($owner)
    -> syncResult = SyncMinecraftAccount::run($account)
      -> whitelist add + lh setmember + lh setstaff/removestaff
    -> if !syncResult['whitelist']['success']:
      -> $account->status = Removed; $account->save()
      -> return ['success' => false, ...]
    -> RecordActivity::run($owner, 'minecraft_account_reactivated', ...)
    -> return ['success' => true, ...]
```

### Bulk Repair Command Flow

```
Operator runs: php artisan minecraft:repair-permissions [--dry-run] [--pace=N]

  -> RepairMinecraftPermissions::handle()
    -> MinecraftAccount::active()->with('user')->get()
    -> foreach account:
      -> $rank = $user->membership_level->minecraftRank()
      -> $eligible = $rank !== null && !$user->isInBrig() && $user->parent_allows_minecraft
      -> if eligible:
           -> [dry-run]: print planned commands; increment counts
           -> [live]: MinecraftRconService::executeCommand(whitelist add ...)
                      pauseIfNeeded($firstCommand, $pace)
                      MinecraftRconService::executeCommand(lh setmember ...)
                      MinecraftRconService::executeCommand(lh setstaff/removestaff ...)
                      increment counts / failures
      -> if ineligible:
           -> compute reason string
           -> [dry-run]: print planned remove + reason; increment removes
           -> [live]: MinecraftRconService::executeCommand(whitelist remove ...)
    -> printSummary($counts, $dryRun)
    -> return Command::SUCCESS
```

---

## 15. Configuration

### `config/services.php`

| Key | Env Variable | Default | Purpose |
|-----|-------------|---------|---------|
| `services.minecraft.rcon_host` | `MINECRAFT_RCON_HOST` | `localhost` | RCON server hostname |
| `services.minecraft.rcon_port` | `MINECRAFT_RCON_PORT` | `25575` | RCON port |
| `services.minecraft.rcon_password` | `MINECRAFT_RCON_PASSWORD` | — | RCON authentication password |
| `services.minecraft.verification_token` | `MINECRAFT_VERIFICATION_TOKEN` | — | Token for verification webhook |

### `config/lighthouse.php`

| Key | Env Variable | Default | Purpose |
|-----|-------------|---------|---------|
| `lighthouse.max_minecraft_accounts` | `MAX_MINECRAFT_ACCOUNTS` | `2` | Max accounts per user (checked in ReactivateMinecraftAccount) |
| `lighthouse.minecraft.server_name` | `MINECRAFT_SERVER_NAME` | `Lighthouse MC` | Display name |
| `lighthouse.minecraft.server_host` | `MINECRAFT_SERVER_HOST` | `play.lighthousemc.net` | Public server address |

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/Minecraft/SyncMinecraftAccountTest.php` | 8 | Core `SyncMinecraftAccount` logic: eligibility paths, activity logging, Bedrock command variant |
| `tests/Feature/Minecraft/MinecraftCommandsTest.php` | 12 | `lh` command hardening, RCON logging, connection failures, legacy `SyncMinecraftRanks`/`SyncMinecraftStaff` |
| `tests/Feature/Minecraft/RepairMinecraftPermissionsTest.php` | 13 | `minecraft:repair-permissions`: dry-run and live modes, ineligibility reasons, summary output, Bedrock |
| `tests/Feature/Minecraft/MinecraftLifecycleSyncTest.php` | 10 | Lifecycle integration: `SyncMinecraftPermissions`, promotion, demotion, reactivation, brig release, parent toggle |
| `tests/Feature/Minecraft/MixedAccountScenariosTest.php` | 11 | Multi-account scenarios, Java/Bedrock mixed, consistency between lifecycle sync and repair command |

### Test Case Inventory

#### SyncMinecraftAccountTest.php
- `eligible user receives whitelist add, rank set, and staff removed`
- `eligible staff user receives setstaff command`
- `eligible user records rank sync and staff activity`
- `eligible staff user records staff position set activity`
- `user below membership threshold receives whitelist remove`
- `ineligible user does not receive rank or staff commands`
- `brigged user receives whitelist remove`
- `parent-disabled user receives whitelist remove`
- `bedrock account uses fwhitelist add command`

#### MinecraftCommandsTest.php
- `cleanup command removes expired verifications`
- `cleanup command sends whitelist remove command via rcon`
- `cleanup command runs successfully`
- `rcon service logs commands`
- `rcon service records execution time`
- `rcon service handles connection failure`
- `lh command is recorded as success when response starts with Success:`
- `lh command is recorded as failed when response is blank`
- `lh command is recorded as failed when response does not start with Success:`
- `non-lh command is recorded as success for any non-false response`
- `rcon connection failure is recorded as failed for lh command`
- `SyncMinecraftRanks sends rank command synchronously via rcon service`
- `SyncMinecraftStaff sends setstaff command synchronously via rcon service`
- `SyncMinecraftStaff sends removestaff command synchronously via rcon service`

#### RepairMinecraftPermissionsTest.php
- `exits successfully with message when no active accounts exist`
- `dry-run reports planned whitelist add and rank for eligible account`
- `dry-run reports planned whitelist remove for brigged account`
- `dry-run reports planned whitelist remove for below-threshold account`
- `dry-run reports planned whitelist remove for parent-disabled account`
- `dry-run reports setstaff command for staff members`
- `dry-run sends no RCON commands and prints summary`
- `live mode sends whitelist, rank, and staff RCON commands for eligible account`
- `live mode sends whitelist remove for ineligible account`
- `live mode records failures in summary when RCON returns error`
- `mixed eligible and ineligible accounts processed correctly in dry-run`
- `mixed live run sends correct RCON commands with pace=0`
- `bedrock account uses fwhitelist command in dry-run`

#### MinecraftLifecycleSyncTest.php
- `SyncMinecraftPermissions sends whitelist add and rank for each active account`
- `SyncMinecraftPermissions skips removed and verifying accounts`
- `PromoteUser syncs Minecraft permissions through unified path`
- `DemoteUser syncs Minecraft permissions through unified path`
- `reactivation uses unified sync to restore whitelist and rank`
- `reactivation reverts account to Removed if whitelist add fails`
- `brig release restores Minecraft access via unified sync when parent allows`
- `brig release sets account to ParentDisabled and skips sync when parent blocks MC`
- `parent disabling MC triggers whitelist remove via unified sync`
- `parent enabling MC triggers whitelist add and rank sync via unified sync`
- `parent enabling MC also syncs staff position when child is staff`

#### MixedAccountScenariosTest.php
- `repair command repairs all active accounts when a user has multiple`
- `repair command removes all whitelist entries when a brigged user has multiple accounts`
- `repair command sets staff on all accounts when a staff member has multiple accounts`
- `repair command handles a user with both java and bedrock accounts`
- `lifecycle sync and repair command send the same whitelist and rank commands for an eligible user`
- `lifecycle sync and repair command both remove a brigged user from the whitelist`
- `lifecycle sync and repair command both remove a parent-disabled user from the whitelist`
- `lifecycle sync and repair command both set staff position for a staff user`
- `dry-run correctly reports all planned actions across multiple users and accounts`

### Coverage Gaps

- **No test for `SyncMinecraftAccount` when the RCON call for `lh setmember` fails after whitelist add succeeds.** The caller (`ReactivateMinecraftAccount`) only checks `whitelist.success`, so a rank-set failure is silently ignored.
- **No test for `SyncMinecraftAccount` when `staff_department` is set but `lh setstaff` fails.** The action returns success regardless; there is no rollback path.
- **No test for `RepairMinecraftPermissions` with `--pace` greater than 0.** The `sleep()` call in `pauseIfNeeded()` is not exercised by any test (all tests pass `--pace=0`).
- **No integration test for `CompleteVerification` calling `SyncMinecraftAccount` for a newly-verified account.** This caller is referenced in the action grep but its sync behavior is not covered in the lifecycle test suite.
- **No test for the repair command handling an exception from RCON** (e.g., `connectAndSend` throwing). The `executeCommand` catch clause wraps this at the service level and returns `success: false`, but the command's outer loop does not have its own exception handling.

---

## 17. File Map

**Models:**
- `app/Models/MinecraftAccount.php`
- `app/Models/MinecraftCommandLog.php`

**Enums:**
- `app/Enums/MinecraftAccountStatus.php`
- `app/Enums/MinecraftAccountType.php`
- `app/Enums/MembershipLevel.php`
- `app/Enums/StaffDepartment.php`

**Actions:**
- `app/Actions/SyncMinecraftAccount.php` (core unified sync, new)
- `app/Actions/SyncMinecraftPermissions.php` (user-level wrapper, new)
- `app/Actions/PromoteUser.php` (calls `SyncMinecraftPermissions`)
- `app/Actions/DemoteUser.php` (calls `SyncMinecraftPermissions`)
- `app/Actions/ReleaseUserFromBrig.php` (calls `SyncMinecraftAccount`)
- `app/Actions/ReactivateMinecraftAccount.php` (calls `SyncMinecraftAccount`)
- `app/Actions/UpdateChildPermission.php` (calls `SyncMinecraftAccount`)
- `app/Actions/CompleteVerification.php` (calls `SyncMinecraftAccount`)
- `app/Actions/SyncMinecraftRanks.php` (legacy, pre-unification)
- `app/Actions/SyncMinecraftStaff.php` (legacy, pre-unification)

**Policies:** None specific to this feature.

**Gates:** `app/Providers/AuthServiceProvider.php` -- gates: `link-minecraft-account`, `view-mc-command-log`

**Notifications:** None specific to this feature.

**Jobs:** None specific to this feature.

**Services:**
- `app/Services/MinecraftRconService.php`

**Controllers:** None specific to this feature.

**Volt Components:** None specific to this feature.

**Routes:** None specific to this feature.

**Migrations:**
- `database/migrations/2026_02_17_064252_create_minecraft_accounts_table.php`
- `database/migrations/2026_02_17_064300_create_minecraft_command_logs_table.php`
- `database/migrations/2026_02_19_000001_add_avatar_url_to_minecraft_accounts_table.php`
- `database/migrations/2026_02_20_060607_add_status_and_command_id_to_minecraft_accounts_table.php`
- `database/migrations/2026_02_20_063640_make_verified_at_nullable_on_minecraft_accounts_table.php`
- `database/migrations/2026_02_20_072300_make_verified_at_nullable_on_minecraft_accounts_table.php`
- `database/migrations/2026_02_21_100000_add_banned_status_to_minecraft_accounts_table.php`
- `database/migrations/2026_02_22_000000_drop_command_id_from_minecraft_accounts_table.php`
- `database/migrations/2026_02_24_200000_create_minecraft_rewards_table.php`
- `database/migrations/2026_02_26_000000_add_removed_status_to_minecraft_accounts_table.php`
- `database/migrations/2026_02_27_100000_add_is_primary_to_minecraft_accounts_table.php`
- `database/migrations/2026_02_28_043322_add_bedrock_xuid_to_minecraft_accounts_table.php`
- `database/migrations/2026_03_01_000002_add_parent_disabled_status_to_minecraft_accounts_table.php`
- `database/migrations/2026_03_18_040000_add_cancelling_status_to_minecraft_accounts_table.php`

**Console Commands:**
- `app/Console/Commands/RepairMinecraftPermissions.php`

**Tests:**
- `tests/Feature/Minecraft/SyncMinecraftAccountTest.php`
- `tests/Feature/Minecraft/MinecraftCommandsTest.php`
- `tests/Feature/Minecraft/RepairMinecraftPermissionsTest.php`
- `tests/Feature/Minecraft/MinecraftLifecycleSyncTest.php`
- `tests/Feature/Minecraft/MixedAccountScenariosTest.php`

**Config:**
- `config/services.php` -- keys: `services.minecraft.*`
- `config/lighthouse.php` -- keys: `lighthouse.max_minecraft_accounts`, `lighthouse.minecraft.*`

---

## 18. Known Issues & Improvement Opportunities

1. **Duplicate eligibility logic in `RepairMinecraftPermissions`.** The repair command contains its own inline copy of the three-condition eligibility check (`rank !== null && !isInBrig() && parent_allows_minecraft`). If the eligibility rules change (e.g., a new condition is added), it must be updated in both `SyncMinecraftAccount` and `RepairMinecraftPermissions` separately. A shared eligibility helper on `MinecraftAccount` or `User` would eliminate this duplication.

2. **Partial sync failures are silently successful.** In `SyncMinecraftAccount`, if `lh setmember` or `lh setstaff` fails (non-`Success:` response), the action still returns `eligible: true` and `rank.success: false` — but the callers (`PromoteUser`, `DemoteUser`, `SyncMinecraftPermissions`) do not inspect the return value at all. Permission drift can occur without any alerting or retry mechanism.

3. **`ReactivateMinecraftAccount` only guards on `whitelist.success`.** After calling `SyncMinecraftAccount`, it checks only `$syncResult['whitelist']['success']`. A whitelist-add success + rank-set failure leaves the player on the whitelist with the wrong rank, and the reactivation is reported as successful.

4. **No rate-limiting on `SyncMinecraftAccount` calls from lifecycle actions.** A user with many active accounts who is simultaneously released from brig could trigger multiple `lh` plugin commands in rapid succession with no `sleep()` between them, unlike the repair command which has `--pace`. The Minecraft server or plugin could be overwhelmed.

5. **No test for `--pace > 0` in the repair command.** The `sleep()` branch in `pauseIfNeeded()` is untested. If the method's logic were broken (e.g., always sleeping even before the first command), no test would catch it.

6. **Legacy actions (`SyncMinecraftRanks`, `SyncMinecraftStaff`) still exist alongside the unified path.** These actions are no longer called by any lifecycle action but remain in the codebase and are exercised by `MinecraftCommandsTest.php`. They use `SendMinecraftCommand` (a different dispatch path) rather than `MinecraftRconService::executeCommand` directly, so their behavior and logging semantics differ from the unified path. This creates confusion about which path is canonical. These legacy actions should be deprecated and eventually removed.

7. **`SyncMinecraftAccount` activity logs are recorded even when RCON commands fail.** The `RecordActivity::run(...)` calls for `minecraft_rank_synced` and `minecraft_staff_position_set/removed` execute regardless of whether `$rankResult['success']` or `$staffResult['success']` is true. The activity log thus records a "sync" that may not have actually taken effect on the server.

8. **`BrigType` enum not checked during repair.** The repair command uses `$user->isInBrig()` to determine ineligibility, which correctly covers all brig types including `ParentalDisabled`. This is correct behavior but worth noting explicitly since `ParentalDisabled` brig type overlaps with `parent_allows_minecraft` — a user could be ineligible for two reasons simultaneously and the repair command reports only the first matching reason from `ineligibilityReason()`.
