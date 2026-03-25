# lh syncuser Command Integration and Staff Rank Differentiation -- Technical Documentation

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

This feature updates the Lighthouse Website's Minecraft synchronization layer to use two new plugin commands introduced by the server-side Minecraft plugin:

- **`lh syncuser`** — a single command that whitelists a player, sets their member rank, and sets or removes their staff position in one RCON call (replacing the previous three-command sequence of `whitelist add` + `lh setmember` + `lh setstaff`/`lh removestaff`).
- **`lh syncstart`** — a pre-run command for the bulk repair artisan command that backs up and clears the whitelist before a full resync, ensuring no stale entries remain.

Additionally, the plugin now requires **rank-differentiated staff group assignments**: Officers receive their department name as-is (e.g., `engineer`), while Crew Members and Jr Crew receive a `_crew` suffix (e.g., `engineer_crew`). This reflects a LuckPerms staff track change on the Minecraft server.

**Who is affected:**
- **Site administrators** running `minecraft:repair-permissions` — the command now runs one-third the RCON traffic and completes faster.
- **Staff members** having their accounts synced — their correct Minecraft staff group (officer vs. crew) is applied based on actual rank.
- **All members** with active Minecraft accounts — any sync operation (promotion, demotion, verification, brig release, parent permission toggle) now uses the single unified `lh syncuser` call.

**How it fits in:** This feature builds directly on PRD #354 (Minecraft Permission Hardening and Bulk Repair), which established the `SyncMinecraftAccount` unified sync path and `RepairMinecraftPermissions` artisan command. PRD #365 updates those components to use the new plugin API without changing the external surface or authorization model.

**Key concepts:**
- **`lh syncuser` command**: `lh syncuser <username> <rank> <staffPosition>` for Java accounts; appends `-bedrock <uuid>` for Bedrock accounts. Staff position is either `none` or a LuckPerms group name.
- **`lh syncstart` command**: Run once before a bulk repair loop. Backs up whitelist to a timestamped JSON file and clears the live whitelist so every account gets a fresh add.
- **Officer vs. Crew staff distinction**: Officers use the plain department value (e.g., `engineer`). Crew Members and Jr Crew use the department value with `_crew` appended (e.g., `engineer_crew`).

---

## 2. Database Schema

No new migrations were introduced by this feature. All data lives in pre-existing tables.

### `users` table (relevant columns)

| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| `staff_rank` | int (cast to `StaffRank`) | Yes | `null`=None, 1=JrCrew, 2=CrewMember, 3=Officer |
| `staff_department` | string (cast to `StaffDepartment`) | Yes | `command`, `chaplain`, `engineer`, `quartermaster`, `steward` |
| `membership_level` | int (cast to `MembershipLevel`) | No | Determines `minecraftRank()` |
| `in_brig` | boolean | No | Ineligible if true |
| `parent_allows_minecraft` | boolean | No | Ineligible if false |

### `minecraft_accounts` table (relevant columns)

| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| `username` | string | No | Used in `lh syncuser` command |
| `uuid` | string | Yes | Bedrock UUID appended with `-bedrock` flag |
| `account_type` | string (cast to `MinecraftAccountType`) | No | `java` or `bedrock` |
| `status` | string (cast to `MinecraftAccountStatus`) | No | Only `active` accounts are synced |

### `minecraft_command_logs` table (relevant columns)

| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| `command` | string | No | Full `lh syncuser` or `lh syncstart` command string |
| `command_type` | string | No | `'sync'` for both syncuser and syncstart |
| `target` | string | Yes | Username for syncuser; `null` for syncstart |
| `user_id` | FK | Yes | Associated user; `null` for syncstart |
| `status` | string | No | `success` or `failed` |

**Migrations:** No new migrations. Existing schema from prior PRDs covers all required columns.

---

## 3. Models & Relationships

### `User` (`app/Models/User.php`)

**New method introduced by this feature:**

#### `minecraftStaffPosition(): string`

Returns the LuckPerms staff group name for this user's Minecraft account sync.

```
Logic:
- If staff_department is null → return 'none'
- If isAtLeastRank(StaffRank::Officer) → return $this->staff_department->value  (e.g., 'engineer')
- Otherwise (CrewMember, JrCrew, None with dept) → return $this->staff_department->value . '_crew'  (e.g., 'engineer_crew')
```

**Called by:** `SyncMinecraftAccount::handle()`, `RepairMinecraftPermissions::handle()`

**Helper methods used internally:**
- `isAtLeastRank(StaffRank $rank): bool` — checks `($this->staff_rank?->value ?? 0) >= $rank->value`

---

### `MinecraftAccount` (`app/Models/MinecraftAccount.php`)

**New method introduced by this feature:**

#### `syncUserCommand(string $rank, string $staffPosition): string`

Returns the correct `lh syncuser` RCON command string for this account.

```
Java:    "lh syncuser {username} {rank} {staffPosition}"
Bedrock: "lh syncuser {username} {rank} {staffPosition} -bedrock {uuid}"
```

**Placed alongside existing methods** `whitelistAddCommand()` and `whitelistRemoveCommand()` in the `// RCON Command Helpers` section of the model.

**Existing relationships (unchanged):**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `user()` | belongsTo | User | Account owner |
| `rewards()` | hasMany | MinecraftReward | Rewards linked to this account |

---

## 4. Enums Reference

### `StaffRank` (`app/Enums/StaffRank.php`)

| Case | Value | Label | Minecraft staff group suffix |
|------|-------|-------|------------------------------|
| `None` | 0 | None | N/A (no staff group) |
| `JrCrew` | 1 | Junior Crew Member | `_crew` suffix |
| `CrewMember` | 2 | Crew Member | `_crew` suffix |
| `Officer` | 3 | Officer | No suffix (plain department name) |

**Key helper:** `isAtLeastRank(StaffRank::Officer)` tests `$value >= 3`.

---

### `StaffDepartment` (`app/Enums/StaffDepartment.php`)

| Case | Value | Label | Officer group | Crew group |
|------|-------|-------|---------------|------------|
| `Command` | `'command'` | Command | `command` | `command_crew` |
| `Chaplain` | `'chaplain'` | Chaplain | `chaplain` | `chaplain_crew` |
| `Engineer` | `'engineer'` | Engineer | `engineer` | `engineer_crew` |
| `Quartermaster` | `'quartermaster'` | Quartermaster | `quartermaster` | `quartermaster_crew` |
| `Steward` | `'steward'` | Steward | `steward` | `steward_crew` |

---

### `MinecraftAccountType` (`app/Enums/MinecraftAccountType.php`)

| Case | Value | Sync command format |
|------|-------|---------------------|
| `Java` | `'java'` | `lh syncuser <username> <rank> <pos>` |
| `Bedrock` | `'bedrock'` | `lh syncuser <username> <rank> <pos> -bedrock <uuid>` |

---

### `MembershipLevel` (relevant to eligibility)

The `minecraftRank()` method returns:
- `null` for Drifter and Stowaway (ineligible for server access)
- `'traveler'`, `'resident'`, `'citizen'` for higher levels

---

## 5. Authorization & Permissions

No new gates or policies were added by this feature. The sync operations are internal server-to-server calls, not user-facing actions requiring authorization.

**Existing gates that trigger Minecraft syncs (unchanged):**
- Promotion/demotion actions require admin-level access (controlled by existing gates)
- `minecraft:repair-permissions` artisan command requires server shell access

### Permissions Matrix

| Action | Regular Member | Staff (any rank) | Admin |
|--------|---------------|-----------------|-------|
| `lh syncuser` sent on account sync | Automatic (no user action) | Automatic | Automatic |
| `lh syncstart` + bulk repair | No | No | Yes (artisan only) |

---

## 6. Routes

No new routes introduced by this feature. All sync operations are triggered internally by actions called from existing routes.

---

## 7. User Interface Components

Not applicable for this feature. No new Volt components or UI changes were introduced. The `lh syncuser` command replaces three internal RCON calls; users see no visible difference.

---

## 8. Actions (Business Logic)

### `SyncMinecraftAccount` (`app/Actions/SyncMinecraftAccount.php`)

**Signature:** `handle(MinecraftAccount $account): array`

**Step-by-step logic:**

1. Load `$account->user` and resolve `$rcon = app(MinecraftRconService::class)`
2. Determine `$rank = $user->membership_level->minecraftRank()` (null = ineligible)
3. Check eligibility: `$rank !== null && !$user->isInBrig() && $user->parent_allows_minecraft`

**Ineligible path:**
4. Send `$account->whitelistRemoveCommand()` via RCON (type `'whitelist'`)
5. Return `['eligible' => false, 'whitelist' => [...], 'rank' => null, 'staff' => null]`

**Eligible path (changed by this PRD):**
4. Call `$user->minecraftStaffPosition()` → `$staffPosition` (`'none'`, `'engineer'`, `'engineer_crew'`, etc.)
5. Call `$account->syncUserCommand($rank, $staffPosition)` → full `lh syncuser ...` string
6. Send the command via RCON (type `'sync'`, meta `['action' => 'sync_user']`)
7. Log `RecordActivity::run($user, 'minecraft_rank_synced', ...)`
8. If `$staffPosition !== 'none'`:
   - Log `RecordActivity::run($user, 'minecraft_staff_position_set', ...)`
   - `$staffReturn = ['success' => ..., 'action' => 'set', 'department' => $staffPosition]`
9. Else:
   - Log `RecordActivity::run($user, 'minecraft_staff_position_removed', ...)`
   - `$staffReturn = ['success' => ..., 'action' => 'remove', 'department' => null]`
10. Return `['eligible' => true, 'whitelist' => ['success' => ..., 'action' => 'add'], 'rank' => [...], 'staff' => $staffReturn]`

**Return shape (unchanged from pre-PRD):**
```php
[
    'eligible' => bool,
    'whitelist' => ['success' => bool, 'action' => 'add'|'remove'],
    'rank'     => ['success' => bool, 'rank' => string] | null,
    'staff'    => ['success' => bool, 'action' => 'set'|'remove', 'department' => string|null] | null,
]
```

**Called by:**
- `SyncMinecraftPermissions::run($user)` — for lifecycle syncs (promotion, demotion, etc.)
- `ReactivateMinecraftAccount::handle()` — checks `$result['whitelist']['success']` to decide whether to revert
- `UpdateChildPermission::handle()` — when parent enables Minecraft access
- `ReleaseUserFromBrig::handle()` — when a user is released from the brig
- `CompleteVerification::handle()` — after account verification succeeds

**Old fallback commands** (preserved as comments for reference):
```php
// $account->whitelistAddCommand()          → "whitelist add <username>" / "fwhitelist add <uuid>"
// "lh setmember {$username} {$rank}"
// "lh setstaff {$username} {$dept}" / "lh removestaff {$username}"
```

---

## 9. Notifications

Not applicable for this feature. No notifications are sent by the sync commands themselves.

---

## 10. Background Jobs

Not applicable for this feature. Minecraft sync calls are made synchronously via RCON.

---

## 11. Console Commands & Scheduled Tasks

### `minecraft:repair-permissions`
**File:** `app/Console/Commands/RepairMinecraftPermissions.php`
**Scheduled:** No (manual invocation only)

**Options:**
- `--dry-run` — Print planned actions without sending RCON commands
- `--pace=1` — Seconds to pause between outbound commands (default: 1, use 0 for testing)

**What it does (updated by this PRD):**

**Live mode:**
1. Fetches all active `MinecraftAccount` records with their `user` relationship eager-loaded
2. **NEW:** Sends `lh syncstart` once before the per-account loop (RCON type `'sync'`, target `null`, user `null`, meta `['action' => 'syncstart']`)
   - This backs up the server whitelist to a timestamped JSON file and clears the live whitelist
3. For each active account:
   - If eligible: sends `$account->syncUserCommand($rank, $user->minecraftStaffPosition())` (RCON type `'sync'`)
     - Success: increments `adds`, `rank_changes`, `staff_changes` counters
     - Failure: increments `failures` counter
   - If ineligible: sends `$account->whitelistRemoveCommand()` (RCON type `'whitelist'`)
4. Prints summary: Whitelist adds / Whitelist removes / Rank changes / Staff changes / Failures

**Dry-run mode:**
- Skips `lh syncstart` entirely (no output for it)
- For each eligible account: prints `[dry-run] lh syncuser <username> <rank> <staffPos>` and increments all counters
- For each ineligible account: prints `[dry-run] whitelist remove <username>` and the reason
- Prints summary with `[dry-run]` prefix (no Failures line)

**Old fallback commands** (preserved as comments):
```php
// $account->whitelistAddCommand()
// "lh setmember {$username} {$rank}"
// "lh setstaff {$username} {$dept}" / "lh removestaff {$username}"
```

---

## 12. Services

### `MinecraftRconService` (`app/Services/MinecraftRconService.php`)

No changes to this service. Relevant behavior:

- `executeCommand(string $command, string $commandType, ?string $target, ?User $user, array $meta): array`
  - Accepts `null` for `$target` and `$user` (used by `lh syncstart` call which has no associated account/user)
  - For `lh ` commands: success requires response starts with `"Success:"`
  - Logs every command to `minecraft_command_logs`
  - Returns `['success' => bool, 'response' => string|null, 'error' => string|null]`

The `lh syncstart` and `lh syncuser` commands both start with `lh ` so they are automatically subject to the strict `Success:` response validation introduced in PRD #354.

---

## 13. Activity Log Entries

| Action String | Logged By | Subject Model | Description Template |
|---------------|-----------|---------------|---------------------|
| `minecraft_rank_synced` | `SyncMinecraftAccount` | `User` | `"Synced Minecraft rank to {rank} for {username}"` |
| `minecraft_staff_position_set` | `SyncMinecraftAccount` | `User` | `"Set Minecraft staff position to {staffPosition} for {username}"` |
| `minecraft_staff_position_removed` | `SyncMinecraftAccount` | `User` | `"Removed Minecraft staff position for {username}"` |

Note: `RepairMinecraftPermissions` does not log activity directly; activity logging happens inside `SyncMinecraftAccount` which the repair command does not call (it sends RCON directly). Only lifecycle syncs via `SyncMinecraftAccount` generate activity log entries.

---

## 14. Data Flow Diagrams

### Lifecycle Sync (Promotion, Demotion, Brig Release, Parent Permission Change, Verification)

```
Event triggers (e.g., PromoteUser::run($user))
  -> SyncMinecraftPermissions::run($user)
    -> foreach active account:
      -> SyncMinecraftAccount::run($account)
        -> $rank = $user->membership_level->minecraftRank()
        -> eligibility check (rank, brig, parent_allows_minecraft)
        ->
        -> [INELIGIBLE PATH]
        ->   rcon->executeCommand($account->whitelistRemoveCommand(), 'whitelist', ...)
        ->   return ['eligible' => false, ...]
        ->
        -> [ELIGIBLE PATH]
        ->   $staffPosition = $user->minecraftStaffPosition()
        ->       staff_department null → 'none'
        ->       isAtLeastRank(Officer) → dept->value (e.g., 'engineer')
        ->       otherwise → dept->value . '_crew' (e.g., 'engineer_crew')
        ->   $cmd = $account->syncUserCommand($rank, $staffPosition)
        ->       Java:    "lh syncuser {username} {rank} {staffPos}"
        ->       Bedrock: "lh syncuser {username} {rank} {staffPos} -bedrock {uuid}"
        ->   rcon->executeCommand($cmd, 'sync', $account->username, $user, ...)
        ->   RecordActivity::run($user, 'minecraft_rank_synced', ...)
        ->   if staffPosition != 'none': RecordActivity::run($user, 'minecraft_staff_position_set', ...)
        ->   else: RecordActivity::run($user, 'minecraft_staff_position_removed', ...)
        ->   return ['eligible' => true, 'whitelist' => [...], 'rank' => [...], 'staff' => [...]]
```

---

### Bulk Repair (Live Mode)

```
Admin runs: php artisan minecraft:repair-permissions [--pace=N]
  -> RepairMinecraftPermissions::handle()
    -> MinecraftAccount::active()->with('user')->get()
    -> [if !dryRun]
    ->   rcon->executeCommand('lh syncstart', 'sync', null, null, ['action' => 'syncstart'])
    ->   (backs up whitelist, clears live whitelist on server)
    ->
    -> foreach $accounts as $account:
    ->   eligibility check
    ->
    ->   [ELIGIBLE]
    ->   $staffPosition = $user->minecraftStaffPosition()
    ->   $syncCmd = $account->syncUserCommand($rank, $staffPosition)
    ->   rcon->executeCommand($syncCmd, 'sync', $account->username, $user, ...)
    ->   success → adds++, rank_changes++, staff_changes++
    ->   failure → failures++
    ->
    ->   [INELIGIBLE]
    ->   rcon->executeCommand($account->whitelistRemoveCommand(), 'whitelist', ...)
    ->   success → removes++
    ->
    -> printSummary($counts, $dryRun)
```

---

### Bulk Repair (Dry-Run Mode)

```
Admin runs: php artisan minecraft:repair-permissions --dry-run
  -> RepairMinecraftPermissions::handle()
    -> MinecraftAccount::active()->with('user')->get()
    -> (lh syncstart NOT sent, NOT shown in output)
    ->
    -> foreach $accounts as $account:
    ->   [ELIGIBLE]
    ->   output: "[dry-run] lh syncuser {username} {rank} {staffPos}"
    ->   adds++, rank_changes++, staff_changes++
    ->
    ->   [INELIGIBLE]
    ->   output: "[dry-run] whitelist remove {username}"
    ->   output: "  ({reason})"
    ->   removes++
    ->
    -> printSummary — all lines prefixed "[dry-run]", no Failures line
```

---

## 15. Configuration

Not applicable for this feature. No new environment variables or config keys were introduced. The feature relies on existing `services.minecraft.rcon_*` config (from prior PRDs).

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/Minecraft/SyncUserCommandTest.php` | 7 | Model methods `minecraftStaffPosition()` and `syncUserCommand()` |
| `tests/Feature/Minecraft/SyncMinecraftAccountTest.php` | 10 | Full SyncMinecraftAccount action with new lh syncuser |
| `tests/Feature/Minecraft/MinecraftLifecycleSyncTest.php` | 11 | Promotion, demotion, reactivation, brig release, parent permission toggle all using lh syncuser |
| `tests/Feature/Minecraft/RepairMinecraftPermissionsTest.php` | 16 | Repair command dry-run and live mode with lh syncstart and lh syncuser |
| `tests/Feature/Minecraft/MixedAccountScenariosTest.php` | 9 | Multi-account users, Java+Bedrock, consistency between lifecycle and repair |
| `tests/Feature/Minecraft/CompleteVerificationTest.php` | (1 relevant) | Staff member verification uses lh syncuser with correct officer staff group |

### Test Case Inventory

**SyncUserCommandTest.php:**
- `minecraftStaffPosition returns none when user has no department`
- `minecraftStaffPosition returns department value for Officer`
- `minecraftStaffPosition returns department_crew for Crew Member`
- `minecraftStaffPosition returns department_crew for Jr Crew`
- `syncUserCommand returns correct string for Java account`
- `syncUserCommand returns correct string with none staff position for Java account`
- `syncUserCommand returns correct string for Bedrock account with -bedrock suffix`

**SyncMinecraftAccountTest.php:**
- `eligible user receives single lh syncuser command`
- `eligible staff Officer receives lh syncuser with department name (no _crew suffix)`
- `eligible staff Crew Member receives lh syncuser with _crew suffix`
- `eligible user records rank sync and staff activity`
- `eligible staff user records staff position set activity`
- `user below membership threshold receives whitelist remove`
- `ineligible user does not receive lh syncuser command`
- `brigged user receives whitelist remove`
- `parent-disabled user receives whitelist remove`
- `bedrock account uses lh syncuser with -bedrock suffix`

**MinecraftLifecycleSyncTest.php:**
- `SyncMinecraftPermissions sends lh syncuser for each active account`
- `SyncMinecraftPermissions skips removed and verifying accounts`
- `PromoteUser syncs Minecraft permissions through unified path`
- `DemoteUser syncs Minecraft permissions through unified path`
- `reactivation uses unified sync to restore whitelist and rank`
- `reactivation reverts account to Removed if sync fails`
- `brig release restores Minecraft access via unified sync when parent allows`
- `brig release sets account to ParentDisabled and skips sync when parent blocks MC`
- `parent disabling MC triggers whitelist remove via unified sync`
- `parent enabling MC triggers lh syncuser via unified sync`
- `parent enabling MC syncs staff position for Officer child via lh syncuser`

**RepairMinecraftPermissionsTest.php:**
- `exits successfully with message when no active accounts exist`
- `dry-run reports lh syncuser for eligible account`
- `dry-run does not send lh syncstart`
- `dry-run reports planned whitelist remove for brigged account`
- `dry-run reports planned whitelist remove for below-threshold account`
- `dry-run reports planned whitelist remove for parent-disabled account`
- `dry-run reports lh syncuser with _crew suffix for crew staff member`
- `dry-run reports lh syncuser with department for Officer staff member`
- `dry-run sends no RCON commands and prints summary`
- `live mode sends lh syncstart once before any per-account commands`
- `live mode sends single lh syncuser command for eligible account`
- `live mode sends whitelist remove for ineligible account`
- `live mode records failures in summary when RCON returns error`
- `mixed eligible and ineligible accounts processed correctly in dry-run`
- `mixed live run sends lh syncstart then correct commands per account`
- `bedrock account uses lh syncuser with -bedrock suffix in dry-run`

**MixedAccountScenariosTest.php:**
- `repair command repairs all active accounts when a user has multiple`
- `repair command removes all whitelist entries when a brigged user has multiple accounts`
- `repair command syncs staff on all accounts when a staff member has multiple accounts`
- `repair command handles a user with both java and bedrock accounts`
- `lifecycle sync and repair command both handle an eligible user via lh syncuser`
- `lifecycle sync and repair command both remove a brigged user from the whitelist`
- `lifecycle sync and repair command both remove a parent-disabled user from the whitelist`
- `lifecycle sync and repair command both handle a staff user via lh syncuser`
- `dry-run correctly reports all planned actions across multiple users and accounts`

### Coverage Gaps

- No test verifies that `lh syncstart` failure (non-Success response) is handled gracefully — the repair command proceeds with the loop regardless of syncstart result.
- No test covers the ordering guarantee between `lh syncstart` and ineligible `whitelist remove` commands (e.g., does syncstart fire even when all accounts are ineligible?). In the current implementation it does, since syncstart is sent before any per-account logic.
- No test covers `lh syncstart` with a non-null `pace` value to verify it counts as the "first command" and suppresses the initial sleep.

---

## 17. File Map

**Models:**
- `app/Models/User.php` — added `minecraftStaffPosition(): string`
- `app/Models/MinecraftAccount.php` — added `syncUserCommand(string $rank, string $staffPosition): string`

**Enums:**
- `app/Enums/StaffRank.php` — used to determine officer vs. crew suffix
- `app/Enums/StaffDepartment.php` — provides department string values for staff position
- `app/Enums/MinecraftAccountType.php` — used in `syncUserCommand()` to detect Bedrock

**Actions:**
- `app/Actions/SyncMinecraftAccount.php` — updated to use `lh syncuser`

**Callers of SyncMinecraftAccount (unchanged):**
- `app/Actions/SyncMinecraftPermissions.php`
- `app/Actions/ReactivateMinecraftAccount.php`
- `app/Actions/UpdateChildPermission.php`
- `app/Actions/ReleaseUserFromBrig.php`
- `app/Actions/CompleteVerification.php`

**Policies:** Not applicable for this feature.

**Gates:** Not applicable for this feature.

**Notifications:** Not applicable for this feature.

**Jobs:** Not applicable for this feature.

**Services:**
- `app/Services/MinecraftRconService.php` — unchanged; accepts `null` target/user for syncstart

**Controllers:** Not applicable for this feature.

**Volt Components:** Not applicable for this feature.

**Routes:** Not applicable for this feature.

**Migrations:** None introduced by this feature.

**Console Commands:**
- `app/Console/Commands/RepairMinecraftPermissions.php` — updated with `lh syncstart` + `lh syncuser`

**Tests:**
- `tests/Feature/Minecraft/SyncUserCommandTest.php`
- `tests/Feature/Minecraft/SyncMinecraftAccountTest.php`
- `tests/Feature/Minecraft/MinecraftLifecycleSyncTest.php`
- `tests/Feature/Minecraft/RepairMinecraftPermissionsTest.php`
- `tests/Feature/Minecraft/MixedAccountScenariosTest.php`
- `tests/Feature/Minecraft/CompleteVerificationTest.php` (one test updated)

**Config:** No new config keys. Uses existing `services.minecraft.*` RCON configuration.

---

## 18. Known Issues & Improvement Opportunities

1. **`lh syncstart` is always sent even when all accounts are ineligible.** If every active account is brigged or below threshold, the repair command will still send `lh syncstart` (clearing the whitelist) but then only send `whitelist remove` commands — redundant since the whitelist was already cleared. This is harmless but slightly wasteful.

2. **`lh syncstart` failure is silently ignored.** The repair command calls `rcon->executeCommand('lh syncstart', ...)` but does not check the return value. If syncstart fails (e.g., backup filesystem full), the command proceeds to process accounts as if the whitelist was cleared. A warning line should be emitted if syncstart returns `success: false`.

3. **Activity log `department` field stores the Minecraft group name, not the human label.** For an Engineer Officer, `minecraft_staff_position_set` logs `"Set Minecraft staff position to engineer for Steve"`. For an Engineer Crew Member, it logs `"engineer_crew"`. The ACP activity log will show the raw Minecraft group string rather than a friendly label. This is a cosmetic issue.

4. **`RepairMinecraftPermissions` does not call `SyncMinecraftAccount`.** It sends RCON commands directly, which means it bypasses `SyncMinecraftAccount`'s activity logging. The repair command does not log `minecraft_rank_synced`, `minecraft_staff_position_set`, or `minecraft_staff_position_removed` entries. Only lifecycle syncs log these activities.

5. **No retry or error recovery for `lh syncuser` failures during bulk repair.** If a `lh syncuser` call fails mid-repair, the account is counted as a failure in the summary but the repair command does not retry or skip to a fallback. A future improvement could retry failed accounts with individual old-style commands as fallback.

6. **`minecraftStaffPosition()` assumes `staff_rank` is always set when `staff_department` is set.** If a user has a `staff_department` but no `staff_rank` (e.g., `staff_rank = null`), `isAtLeastRank(StaffRank::Officer)` evaluates `(null ?? 0) >= 3` → `false`, so they get the `_crew` suffix. This is a sensible default but should be verified as intentional behavior for edge cases.
