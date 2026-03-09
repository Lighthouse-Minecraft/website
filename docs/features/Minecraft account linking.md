# Minecraft Account Linking -- Technical Documentation

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

The Minecraft Account Linking feature allows Lighthouse community members to verify and link their Minecraft accounts (both Java and Bedrock editions) to their website profile. This is the primary mechanism through which users gain access to the whitelisted Minecraft server.

The verification flow works as follows: a user enters their Minecraft username, the system looks up their UUID via the Mojang API (Java) or GeyserMC/McProfile API (Bedrock), temporarily whitelists them, and generates a 6-character verification code. The user then joins the server and enters the code via an in-game plugin that sends a webhook back to the website to complete the verification. Once verified, the user's account is promoted to active status and their in-game rank is synced to match their website membership level.

Users at the Stowaway membership level or above (who are not in the brig and whose parent permissions allow it) can link up to a configurable number of accounts (default: 2). Accounts can be unlinked by the user, revoked by staff, reactivated from a removed state, or permanently deleted by admins. The system also handles automatic rank synchronization when a user's membership level changes, staff position syncing, new player rewards, periodic username refresh checks, and parent portal controls for child accounts.

Key concepts: **Verification** (temporary whitelist + code entry), **RCON** (Remote Console protocol for sending commands to the Minecraft server), **Floodgate** (bridge allowing Bedrock players to join Java servers, using deterministic UUIDs derived from Xbox XUIDs), **Primary Account** (the user's main MC account used for avatar display).

---

## 2. Database Schema

### `minecraft_accounts` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | No | auto | Primary key |
| user_id | bigint (FK) | No | - | References `users.id`, cascade delete |
| username | string | No | - | MC username or gamertag |
| uuid | string | No | - | MC UUID (Java) or Floodgate UUID (Bedrock), unique |
| bedrock_xuid | string | Yes | null | Xbox User ID for Bedrock accounts |
| avatar_url | string | Yes | null | URL to mc-heads.net avatar |
| status | enum | No | 'verifying' | verifying, active, cancelled, banned, removed, parent_disabled |
| is_primary | boolean | No | false | Whether this is the user's primary MC account |
| account_type | string | No | - | 'java' or 'bedrock' |
| verified_at | timestamp | Yes | null | When verification was completed |
| last_username_check_at | timestamp | Yes | null | Last time username was refreshed |
| created_at | timestamp | No | auto | |
| updated_at | timestamp | No | auto | |

**Indexes:** `status` (index), `uuid` (unique), `last_username_check_at` (index)
**Foreign Keys:** `user_id` -> `users.id` (cascade delete)
**Migrations:**
- `database/migrations/2026_02_17_064252_create_minecraft_accounts_table.php`
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

### `minecraft_verifications` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | No | auto | Primary key |
| user_id | bigint (FK) | Yes | null | References `users.id`, null on delete |
| code | string(8) | No | - | 6-char verification code, unique, indexed |
| account_type | string | No | - | 'java' or 'bedrock' |
| minecraft_username | string | Yes | null | Username at time of verification |
| minecraft_uuid | string | Yes | null | UUID at time of verification |
| status | enum | No | 'pending' | pending, completed, expired, failed |
| expires_at | timestamp | No | - | When the verification code expires |
| whitelisted_at | timestamp | Yes | null | When temp whitelist was added |
| created_at | timestamp | No | auto | |
| updated_at | timestamp | No | auto | |

**Indexes:** `code` (unique, index), `status` (index), `expires_at` (index)
**Foreign Keys:** `user_id` -> `users.id` (null on delete)
**Migration:** `database/migrations/2026_02_17_064256_create_minecraft_verifications_table.php`

### `minecraft_command_logs` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | No | auto | Primary key |
| user_id | bigint (FK) | Yes | null | References `users.id`, null on delete |
| command | string | No | - | The RCON command sent |
| command_type | string | No | - | Category: whitelist, rank, staff, reward, kick, verify |
| target | string | Yes | null | Player or entity targeted |
| status | enum | No | - | success, failed, timeout |
| response | text | Yes | null | Server response text |
| error_message | text | Yes | null | Error message if failed |
| ip_address | ipAddress | Yes | null | IP of the request initiator |
| meta | json | Yes | null | Additional metadata |
| executed_at | timestamp | No | now() | When command was executed |
| execution_time_ms | integer | Yes | null | Execution duration in ms |
| created_at | timestamp | No | auto | |
| updated_at | timestamp | No | auto | |

**Indexes:** `command_type` (index), `status` (index), `(command_type, status)` (composite), `executed_at` (index)
**Foreign Keys:** `user_id` -> `users.id` (null on delete)
**Migration:** `database/migrations/2026_02_17_064300_create_minecraft_command_logs_table.php`

### `minecraft_rewards` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | No | auto | Primary key |
| user_id | bigint (FK) | No | - | References `users.id`, cascade delete |
| minecraft_account_id | bigint (FK) | No | - | References `minecraft_accounts.id`, cascade delete |
| reward_name | string | No | - | Unique per user (e.g. "New Player Reward") |
| reward_description | string | No | - | Human-readable (e.g. "96 Lumens") |
| created_at | timestamp | No | auto | |
| updated_at | timestamp | No | auto | |

**Indexes:** `(user_id, reward_name)` (unique composite: `unique_user_reward`)
**Foreign Keys:** `user_id` -> `users.id` (cascade), `minecraft_account_id` -> `minecraft_accounts.id` (cascade)
**Migration:** `database/migrations/2026_02_24_200000_create_minecraft_rewards_table.php`

---

## 3. Models & Relationships

### MinecraftAccount (`app/Models/MinecraftAccount.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `user()` | belongsTo | User | The owning website user |
| `rewards()` | hasMany | MinecraftReward | Rewards granted to this account |

**Scopes:**
- `whereNormalizedUuid($uuid)` — Matches UUID ignoring dashes via `REPLACE(uuid, '-', '')`
- `active()` — Status = Active
- `verifying()` — Status = Verifying
- `cancelled()` — Status = Cancelled
- `removed()` — Status = Removed
- `primary()` — is_primary = true
- `countingTowardLimit()` — Status in [Active, Verifying, Banned] (counts against max account limit)

**Key Methods:**
- `whitelistAddCommand(): string` — Returns `"whitelist add {username}"` (Java) or `"fwhitelist add {uuid}"` (Bedrock)
- `whitelistRemoveCommand(): string` — Returns `"whitelist remove {username}"` (Java) or `"fwhitelist remove {uuid}"` (Bedrock)

**Casts:**
- `account_type` => `MinecraftAccountType`
- `status` => `MinecraftAccountStatus`
- `is_primary` => `boolean`
- `verified_at` => `datetime`
- `last_username_check_at` => `datetime`

### MinecraftVerification (`app/Models/MinecraftVerification.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `user()` | belongsTo | User | The user who initiated verification |

**Scopes:**
- `pending()` — Status = 'pending'
- `expired()` — expires_at < now()

**Casts:**
- `account_type` => `MinecraftAccountType`
- `expires_at` => `datetime`
- `whitelisted_at` => `datetime`

### MinecraftCommandLog (`app/Models/MinecraftCommandLog.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `user()` | belongsTo | User | The user who triggered the command |

**Scopes:**
- `successful()` — Status = 'success'
- `failed()` — Status = 'failed'

**Casts:**
- `meta` => `array`
- `executed_at` => `datetime`

### MinecraftReward (`app/Models/MinecraftReward.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `user()` | belongsTo | User | The user who received the reward |
| `minecraftAccount()` | belongsTo | MinecraftAccount | The account the reward was granted to |

### User (`app/Models/User.php`) — Minecraft-related

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `minecraftAccounts()` | hasMany | MinecraftAccount | All MC accounts |
| `primaryMinecraftAccount()` | (computed) | MinecraftAccount | First active + primary account |

**Key Fields:**
- `parent_allows_minecraft` — boolean, fillable, controls whether a child account can link MC accounts

---

## 4. Enums Reference

### MinecraftAccountType (`app/Enums/MinecraftAccountType.php`)

| Case | Value | Label |
|------|-------|-------|
| Java | `'java'` | Java Edition |
| Bedrock | `'bedrock'` | Bedrock Edition |

### MinecraftAccountStatus (`app/Enums/MinecraftAccountStatus.php`)

| Case | Value | Label | Color | Notes |
|------|-------|-------|-------|-------|
| Verifying | `'verifying'` | Pending Verification | yellow | Temp whitelisted, awaiting in-game code entry |
| Active | `'active'` | Active | green | Verified and on whitelist |
| Cancelled | `'cancelled'` | Cancelled | red | Failed cleanup state (server offline during expiry) |
| Banned | `'banned'` | Banned | orange | Counts toward limit but not active |
| Removed | `'removed'` | Removed | zinc | Soft-disabled, can be reactivated |
| ParentDisabled | `'parent_disabled'` | Disabled by Parent | purple | Parent turned off MC access |

Helper methods: `label()`, `color()`

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

| Gate Name | Who Can Pass | Logic Summary |
|-----------|-------------|---------------|
| `link-minecraft-account` | Traveler+ membership, not in brig, parent allows MC | `$user->isAtLeastLevel(MembershipLevel::Traveler) && !$user->in_brig && $user->parent_allows_minecraft` |
| `view-mc-command-log` | Admin, Officer, or Engineer department | Shared `$canViewLogs` closure |

### Policies

#### MinecraftAccountPolicy (`app/Policies/MinecraftAccountPolicy.php`)

**`before()` hook:** None

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `viewAny` | Admin or Officer+ rank | `$user->isAdmin() \|\| $user->isAtLeastRank(StaffRank::Officer)` |
| `view` | Nobody | Always returns `false` |
| `create` | Nobody | Always returns `false` |
| `update` | Nobody | Always returns `false` |
| `setPrimary` | Account owner or Admin | Account must be Active |
| `delete` | Account owner or Admin | No status restriction in policy |
| `reactivate` | Account owner or Admin | Account must be Removed |
| `viewUuid` | Admin, Engineer dept, or Officer+ rank | |
| `viewStaffAuditFields` | Admin or any staff member | `$user->staff_department !== null` |
| `revoke` | Admin, or Officer in Engineer/Command dept | Account must be Active |
| `restore` | Nobody | Always returns `false` |
| `forceDelete` | Admin only | Account must be Removed or Verifying |

### Permissions Matrix

| Action | Regular User | Stowaway+ User | Staff Crew | Officer (Eng/Cmd) | Officer (Other) | Admin |
|--------|-------------|----------------|------------|-------------------|-----------------|-------|
| Link MC account | No | Yes (if not in brig, parent allows) | Yes | Yes | Yes | Yes |
| View own accounts | Yes | Yes | Yes | Yes | Yes | Yes |
| Unlink own account | Yes | Yes | Yes | Yes | Yes | Yes |
| Set primary | Yes (own) | Yes (own) | Yes (own) | Yes (own) | Yes (own) | Yes (any) |
| Reactivate own | Yes | Yes | Yes | Yes | Yes | Yes |
| Revoke (admin remove) | No | No | No | Yes | No | Yes |
| Force delete | No | No | No | No | No | Yes |
| View all MC users (ACP) | No | No | No | Yes | Yes | Yes |
| View UUID | No | No | No (unless Eng) | Yes | Yes | Yes |
| View audit fields | No | No | Yes | Yes | Yes | Yes |
| View MC command log | No | No | No (unless Eng/Cmd) | Yes | No (unless Eng/Cmd) | Yes |
| Manage child MC accounts | Parent only | Parent only | Parent only | Parent only | Parent only | Parent only |

---

## 6. Routes

| Method | URL | Middleware | Handler | Route Name |
|--------|-----|-----------|---------|------------|
| GET | `/settings/minecraft-accounts` | `auth` | Volt: `settings.minecraft-accounts` | `settings.minecraft-accounts` |
| POST | `/api/minecraft/verify` | `throttle:30,1` | Inline closure (calls `CompleteVerification::run()`) | (none) |

The admin control panel at `/admin` includes the MC user manager and MC command log as tabs within the `admin-control-panel-tabs` Volt component. These are not separate routes but tab panels within the admin page.

---

## 7. User Interface Components

### Minecraft Accounts Settings Page
**File:** `resources/views/livewire/settings/minecraft-accounts.blade.php`
**Route:** `/settings/minecraft-accounts` (route name: `settings.minecraft-accounts`)

**Purpose:** Primary user-facing page for managing Minecraft account linking.

**Authorization:** Gate `link-minecraft-account` checked before generating verification codes.

**User Actions Available:**
- **Generate verification code** -> validates username/type, calls `GenerateVerificationCode::run()` -> displays code + instructions
- **Cancel verification** -> marks verification expired, removes whitelist via RCON, deletes account
- **Check verification** -> polls every 15s (`wire:poll.15s`) for completed verification
- **Simulate verification** -> local-only testing helper that calls `CompleteVerification::run()` directly
- **Unlink account** -> calls `UnlinkMinecraftAccount::run()` -> sets status to Removed
- **Reactivate account** -> calls `ReactivateMinecraftAccount::run()` -> re-whitelists
- **Set primary** -> calls `SetPrimaryMinecraftAccount::run()` -> updates primary flag
- **Remove verifying account** -> cancels in-progress verification

**UI Elements:**
- Linked accounts list with avatar, username, type badge, status badge, primary badge
- Verification form with username input and Java/Bedrock radio toggle
- Verification code display with countdown timer and polling
- Archived/removed accounts section with reactivate buttons
- 4 confirmation modals: unlink, cancel verification, remove verifying, set primary
- Account slot counter showing remaining available slots

### Admin MC Users Manager
**File:** `resources/views/livewire/admin-manage-mc-users-page.blade.php`
**Route:** Admin panel tab (`?category=users&tab=mc-user-manager`)

**Purpose:** Admin/Officer view of all Minecraft accounts with search, sort, and management actions.

**Authorization:** `viewAny` on `MinecraftAccount` policy (Admin or Officer+).

**User Actions Available:**
- **Search** accounts by username, user name, or UUID
- **Sort** by any column (username, user, type, status, UUID, verified_at)
- **View account details** -> opens `mc-account-detail` modal
- **Reactivate** -> calls `ReactivateMinecraftAccount::run()`
- **Force delete** -> calls `ForceDeleteMinecraftAccount::run()`

**UI Elements:**
- Sortable paginated table (15 per page) with avatar, username, user link, type/status badges, truncated UUID, verified date
- Account detail modal (shared component)

### Admin MC Command Log
**File:** `resources/views/livewire/admin-manage-mc-command-log-page.blade.php`
**Route:** Admin panel tab (`?category=logs&tab=mc-command-log`)

**Purpose:** View all RCON commands sent to the Minecraft server with filtering.

**Authorization:** Gate `view-mc-command-log`.

**User Actions Available:**
- **Search** by command text or target
- **Filter** by status (success/failed) and command type
- **Click target** to open account detail modal

**UI Elements:**
- Paginated table (25 per page) with date/time, type badge, command (monospace), target (clickable), triggered-by user, status badge, response/error, execution time

### MC Account Detail Modal (Shared Component)
**File:** `resources/views/components/minecraft/mc-account-detail-modal.blade.php`

**Purpose:** Reusable Blade component showing full account details in a modal.

**Authorization:** Uses `@can('viewUuid')`, `@can('viewStaffAuditFields')`, `@canany(['revoke', 'reactivate', 'forceDelete'])`.

**UI Elements:**
- Avatar, username, status/type/primary badges
- UUID (if authorized), linked user link, created_at, verified_at, last_username_check_at
- Action buttons: Revoke, Reactivate, Delete Permanently (based on permissions)

### User Profile Page — Minecraft Accounts Section
**File:** `resources/views/livewire/users/display-basic-details.blade.php`

**Purpose:** Staff can view and manage a user's Minecraft accounts from their profile page.

**User Actions Available:**
- **View account details** -> opens detail modal
- **Revoke account** -> calls `RevokeMinecraftAccount::run()` (with confirmation modal)
- **Reactivate account** -> calls `ReactivateMinecraftAccount::run()`
- **Force delete account** -> calls `ForceDeleteMinecraftAccount::run()` (with confirmation modal)

### Parent Portal — Minecraft Linking for Children
**File:** `resources/views/livewire/parent-portal/index.blade.php`

**Purpose:** Parents can manage their children's Minecraft account linking.

**User Actions Available:**
- **Toggle MC permission** -> enables/disables `parent_allows_minecraft` for a child
- **Link MC account for child** -> calls `GenerateVerificationCode::run()` for the child
- **Check verification for child** -> polls verification status
- **Remove child's MC account** -> calls `RemoveChildMinecraftAccount::run()`

### Staff Page
**File:** `resources/views/livewire/staff/page.blade.php`

**Purpose:** Displays staff positions with linked Minecraft accounts (via eager loading `user.minecraftAccounts`).

### Command Community Engagement Dashboard Widget
**File:** `resources/views/livewire/dashboard/command-community-engagement.blade.php`

**Purpose:** Dashboard widget for Command department that tracks community engagement metrics including Minecraft account stats.

**Minecraft queries:**
- New MC accounts count in current and previous iteration periods
- Pending MC verifications count (status = Verifying)
- Timeline chart data for `new_mc_accounts` over a 3-month period

---

## 8. Actions (Business Logic)

### GenerateVerificationCode (`app/Actions/GenerateVerificationCode.php`)

**Signature:** `handle(User $user, MinecraftAccountType $accountType, string $username): array`

**Step-by-step logic:**
1. Checks if user is in the brig -> reject
2. Checks if user has reached max accounts (Active/Verifying/Banned count) -> reject
3. Checks rate limit (verifications per hour) -> reject
4. Looks up UUID via Mojang API (Java) or McProfileService (Bedrock, with Floodgate UUID)
5. Validates API-returned username format and UUID format (defense-in-depth)
6. Checks if UUID already linked (any status) -> reject if so
7. Generates unique 6-character code from safe character set (excludes 0/O/1/I/L/5/S)
8. Creates `MinecraftAccount` record in 'verifying' status with avatar URL
9. Sends whitelist add command via RCON (synchronous) -> deletes account on failure
10. Creates `MinecraftVerification` record -> rolls back account + whitelist on failure
11. Logs activity: `minecraft_whitelisted` and `minecraft_verification_generated`

**Called by:** `settings.minecraft-accounts` Volt component, `parent-portal/index` Volt component

### CompleteVerification (`app/Actions/CompleteVerification.php`)

**Signature:** `handle(string $code, string $username, string $uuid, ?string $bedrockUsername, ?string $bedrockXuid): array`

**Step-by-step logic:**
1. Normalizes UUID (removes dashes)
2. Finds pending verification by code
3. Checks if expired -> marks expired if so
4. Matches username (case-insensitive) + UUID, with Floodgate prefix fallback and Bedrock username fallback
5. Within a DB transaction:
   - Finds the verifying `MinecraftAccount` (with lock for update)
   - Validates ownership and verifying status
   - Promotes to Active, sets `verified_at`, stores `bedrock_xuid` if provided
   - Marks verification as completed
   - Auto-assigns as primary if user has no primary account
   - Logs activity: `minecraft_account_linked`
6. Outside transaction: syncs rank via `SendMinecraftCommand::dispatch()` (`lh setmember`)
7. Syncs staff position if applicable (`lh setstaff`)
8. Grants new player reward via `GrantNewPlayerReward::run()`

**Called by:** Webhook route (`POST /api/minecraft/verify`), `simulateVerification()` in settings component

### ExpireVerification (`app/Actions/ExpireVerification.php`)

**Signature:** `handle(MinecraftVerification $verification): bool`

**Step-by-step logic:**
1. Marks verification as 'expired'
2. Finds associated MinecraftAccount in verifying state
3. Marks account as 'cancelled' (enters retry pool if RCON fails)
4. Attempts whitelist removal via RCON
5. On success: kicks player (best-effort), deletes account record
6. On failure: account stays cancelled for retry in next cleanup cycle

**Called by:** `CleanupExpiredVerifications` console command

### UnlinkMinecraftAccount (`app/Actions/UnlinkMinecraftAccount.php`)

**Signature:** `handle(MinecraftAccount $account, User $user): array`

**Step-by-step logic:**
1. Verifies ownership
2. Checks account is Active
3. Resets MC rank to default via RCON (`lh setmember <username> default`)
4. Removes staff position via RCON if applicable (`lh removestaff`)
5. Removes from whitelist via RCON -> bails on failure
6. Sets status to Removed
7. If was primary: clears flag, calls `AutoAssignPrimaryAccount::run()`
8. Logs activities: `minecraft_rank_reset_requested`, `minecraft_staff_position_removed`, `minecraft_whitelist_removal_requested`, `minecraft_account_removed`

**Called by:** `settings.minecraft-accounts` Volt component

### RevokeMinecraftAccount (`app/Actions/RevokeMinecraftAccount.php`)

**Signature:** `handle(MinecraftAccount $account, User $admin): array`

**Step-by-step logic:**
1. Checks admin has `revoke` permission via policy
2. Resets MC rank to default via RCON
3. Removes staff position via RCON if applicable -> bails on failure
4. Removes from whitelist via RCON -> bails on failure
5. Sets status to Removed
6. If was primary: clears flag, calls `AutoAssignPrimaryAccount::run()`
7. Logs activity: `minecraft_account_revoked`

**Called by:** `display-basic-details` Volt component (profile page), `admin-manage-mc-users-page` (indirectly via modal)

### ReactivateMinecraftAccount (`app/Actions/ReactivateMinecraftAccount.php`)

**Signature:** `handle(MinecraftAccount $account, User $user): array`

**Step-by-step logic:**
1. Checks account is Removed
2. Checks owner hasn't reached max account limit
3. Checks owner is not in brig
4. Adds to whitelist via RCON -> bails on failure
5. Sets status to Active
6. Calls `AutoAssignPrimaryAccount::run()`
7. Calls `SyncMinecraftRanks::run()` and `SyncMinecraftStaff::run()` if applicable
8. Logs activity: `minecraft_account_reactivated`

**Called by:** `settings.minecraft-accounts`, `display-basic-details`, `admin-manage-mc-users-page`

### SetPrimaryMinecraftAccount (`app/Actions/SetPrimaryMinecraftAccount.php`)

**Signature:** `handle(MinecraftAccount $account): bool`

**Step-by-step logic:**
1. Checks account is Active -> returns false if not
2. Within DB transaction: clears `is_primary` from all user's accounts, sets this one as primary

**Called by:** `settings.minecraft-accounts`, `AutoAssignPrimaryAccount`

### AutoAssignPrimaryAccount (`app/Actions/AutoAssignPrimaryAccount.php`)

**Signature:** `handle(User $user): void`

**Step-by-step logic:**
1. Checks if user already has a primary active account -> no-op if so
2. Finds first active account by ID
3. Calls `SetPrimaryMinecraftAccount::run()` on it

**Called by:** `UnlinkMinecraftAccount`, `RevokeMinecraftAccount`, `ReactivateMinecraftAccount`, `RemoveChildMinecraftAccount`

### ForceDeleteMinecraftAccount (`app/Actions/ForceDeleteMinecraftAccount.php`)

**Signature:** `handle(MinecraftAccount $account, User $admin): array`

**Step-by-step logic:**
1. Checks admin is actually admin
2. Checks account is Removed or Verifying
3. Hard-deletes the account record (releases UUID for re-registration)
4. Logs activity: `minecraft_account_permanently_deleted`

**Called by:** `display-basic-details`, `admin-manage-mc-users-page`

### SyncMinecraftRanks (`app/Actions/SyncMinecraftRanks.php`)

**Signature:** `handle(User $user): void`

**Step-by-step logic:**
1. Gets user's membership rank string via `$user->membership_level->minecraftRank()`
2. If null (below server access threshold) -> no-op
3. For each active account: dispatches `SendMinecraftCommand` with `lh setmember <username> <rank>`
4. Logs activity: `minecraft_rank_synced`

**Called by:** `ReactivateMinecraftAccount`, `SyncMinecraftPermissions`, `CompleteVerification` (inline), `PromoteUser` (indirectly via `SyncMinecraftPermissions`)

### SyncMinecraftStaff (`app/Actions/SyncMinecraftStaff.php`)

**Signature:** `handle(User $user, ?StaffDepartment $department = null): void`

**Step-by-step logic:**
1. Gets all active accounts
2. If department is not null: dispatches `lh setstaff <username> <department>` for each
3. If department is null: dispatches `lh removestaff <username>` for each
4. Logs activity: `minecraft_staff_position_set` or `minecraft_staff_position_removed`

**Called by:** `ReactivateMinecraftAccount`, `SyncMinecraftPermissions`

### SyncMinecraftPermissions (`app/Actions/SyncMinecraftPermissions.php`)

**Signature:** `handle(User $user): void`

**Step-by-step logic:**
1. Calls `SyncMinecraftRanks::run($user)`
2. Calls `SyncMinecraftStaff::run($user, $user->staff_department)`

**Called by:** `PromoteUser` and other membership/staff change actions

### SendMinecraftCommand (`app/Actions/SendMinecraftCommand.php`)

**Signature:** `handle(string $command, string $commandType, ?string $target, ?User $user, array $meta, bool $async): void`

**Step-by-step logic:**
1. If local environment: forces `$async = false` (bypasses queue for FakeMinecraftRconService)
2. If async: dispatches `MinecraftCommandNotification` as a queued notification
3. If sync: directly calls `MinecraftRconService::executeCommand()`

**Static helper:** `dispatch(...)` — calls `run(...)` with `$async = true`

**Called by:** `CompleteVerification`, `SyncMinecraftRanks`, `SyncMinecraftStaff`, `GrantMinecraftReward`

### GrantMinecraftReward (`app/Actions/GrantMinecraftReward.php`)

**Signature:** `handle(MinecraftAccount $account, User $user, string $rewardName, string $rewardDescription, string $rconCommand): bool`

**Step-by-step logic:**
1. Checks if user already has a reward with the same name (idempotent) -> returns false
2. Dispatches RCON command via `SendMinecraftCommand::dispatch()`
3. Creates `MinecraftReward` record
4. Logs activity: `minecraft_reward_granted`

**Called by:** `GrantNewPlayerReward`

### GrantNewPlayerReward (`app/Actions/GrantNewPlayerReward.php`)

**Signature:** `handle(MinecraftAccount $account, User $user): void`

**Constant:** `REWARD_NAME = 'New Player Reward'`

**Step-by-step logic:**
1. Checks if new player reward is enabled in config -> returns if disabled
2. Calculates Lumen amount: `diamonds * exchange_rate`
3. Calls `GrantMinecraftReward::run()` with `money give <username> <lumens>` command

**Called by:** `CompleteVerification`

### RemoveChildMinecraftAccount (`app/Actions/RemoveChildMinecraftAccount.php`)

**Signature:** `handle(User $parent, int $accountId): array`

**Step-by-step logic:**
1. Finds the account, verifies parent-child relationship
2. Checks account is Active
3. Removes from whitelist via RCON -> bails on failure
4. Resets rank to default via RCON
5. Sets status to Removed
6. If was primary: clears flag, calls `AutoAssignPrimaryAccount::run()`
7. Logs activity: `minecraft_account_removed_by_parent`

**Called by:** `parent-portal/index` Volt component

---

### Cross-Feature Actions That Interact With Minecraft Accounts

These actions belong to other features but directly modify Minecraft account status and whitelist state:

#### UpdateChildPermission (`app/Actions/UpdateChildPermission.php`)
When toggling the `minecraft` permission for a child:
- **Disabling:** Iterates Active/Verifying accounts, sends whitelist-remove via `SendMinecraftCommand::run()` (synchronous), sets status to `ParentDisabled`.
- **Enabling:** Iterates `ParentDisabled` accounts, sends whitelist-add, sets status to `Active`, calls `SyncMinecraftRanks::run()`.

#### PutUserInBrig (`app/Actions/PutUserInBrig.php`)
When a user is placed in the brig:
- Iterates all Active/Verifying/ParentDisabled MC accounts, sends whitelist-remove via `SendMinecraftCommand::run()`, sets status to `Banned`.

#### ReleaseUserFromBrig (`app/Actions/ReleaseUserFromBrig.php`)
When a user is released from the brig:
- Restores all `Banned` MC accounts. Determines restore status based on `parent_allows_minecraft` (either `Active` or `ParentDisabled`). If restoring to Active, sends whitelist-add, then calls `SyncMinecraftRanks::run()` and `SyncMinecraftStaff::run()`.

---

## 9. Notifications

### MinecraftCommandNotification (`app/Notifications/MinecraftCommandNotification.php`)

**Triggered by:** `SendMinecraftCommand` (async dispatch mode)
**Recipient:** Anonymous notifiable routed to 'minecraft' channel
**Channels:** `['minecraft']` (custom channel)
**Queued:** Yes (`implements ShouldQueue`)
**Retries:** 3, with backoff [60s, 300s, 900s]
**Content:** Executes RCON command via `MinecraftRconService::executeCommand()`

This notification wraps RCON command execution in Laravel's queue system, providing automatic retries with exponential backoff when the Minecraft server is unreachable.

---

## 10. Background Jobs

Not applicable for this feature. RCON commands are dispatched via `MinecraftCommandNotification` (queued notification) rather than standalone job classes. See [Section 9: Notifications](#9-notifications).

---

## 11. Console Commands & Scheduled Tasks

### `minecraft:cleanup-expired`
**File:** `app/Console/Commands/CleanupExpiredVerifications.php`
**Scheduled:** Every 5 minutes (`everyFiveMinutes()`, runs in background)
**What it does:**
1. **Pass 1 — Expire pending verifications:** Finds all pending verifications past their expiry time, delegates each to `ExpireVerification::run()` which marks them expired, removes from whitelist, kicks the player, and deletes the account record.
2. **Pass 2 — Retry cancelled accounts:** Finds accounts stuck in 'cancelled' status (server was offline during a previous Pass 1). Retries whitelist removal via RCON; deletes account on success, leaves for next cycle on failure.

### `minecraft:refresh-usernames`
**File:** `app/Console/Commands/RefreshMinecraftUsernames.php`
**Scheduled:** Daily at 3:00 AM (`dailyAt('03:00')`, runs in background)
**What it does:**
1. Calculates daily batch size to check all active accounts over a 30-day cycle
2. Selects accounts not checked in the last 30 days, prioritizing accounts of recently active users
3. For Java accounts: calls `MojangApiService::getJavaUsername(uuid)`
4. For Bedrock accounts: calls `McProfileService::getBedrockGamertag(uuid)`
5. Updates username if changed, updates `last_username_check_at` regardless

---

## 12. Services

### MinecraftRconService (`app/Services/MinecraftRconService.php`)
**Purpose:** Executes RCON commands on the Minecraft server and logs every command.

**Key methods:**
- `executeCommand(string $command, string $commandType, ?string $target, ?User $user, array $meta): array` — Connects to RCON server, sends command, logs to `minecraft_command_logs` table (creates log entry before execution, updates with result after). Returns `['success' => bool, 'response' => string|null, 'error' => string|null]`. Uses 3-second timeout.

**Configuration:** `services.minecraft.rcon_host`, `services.minecraft.rcon_port`, `services.minecraft.rcon_password`

### FakeMinecraftRconService (`app/Services/FakeMinecraftRconService.php`)
**Purpose:** Local development replacement for `MinecraftRconService`. Extends the real service but always returns success with simulated responses. Logs with `['simulated' => true]` in meta.

**Registered:** Bound in service provider when `APP_ENV=local`.

### MojangApiService (`app/Services/MojangApiService.php`)
**Purpose:** Interacts with the official Mojang API for Java Edition player lookups.

**Key methods:**
- `getJavaPlayerUuid(string $username): ?array` — Calls `api.mojang.com/users/profiles/minecraft/{username}`. Returns `['id' => uuid, 'name' => username]` or null.
- `getJavaUsername(string $uuid): ?string` — Calls `sessionserver.mojang.com/session/minecraft/profile/{uuid}`. Returns username or null.

### McProfileService (`app/Services/McProfileService.php`)
**Purpose:** Looks up Bedrock Edition player information via GeyserMC Global API (primary) and mcprofile.io (fallback).

**Key methods:**
- `getBedrockPlayerInfo(string $gamertag): ?array` — Returns `['xuid' => xuid, 'gamertag' => gamertag, 'floodgate_uuid' => uuid]` or null. Tries GeyserMC first, then mcprofile.io.
- `xuidToFloodgateUuid(string $xuid): string` — Computes deterministic Floodgate UUID from Xbox XUID using the formula `new UUID(0, xuid_as_long)`.
- `getBedrockGamertag(string $floodgateUuid): ?string` — Reverse lookup: gets gamertag from Floodgate UUID via mcprofile.io.

---

## 13. Activity Log Entries

| Action String | Logged By | Subject Model | Description |
|---------------|-----------|---------------|-------------|
| `minecraft_whitelisted` | GenerateVerificationCode | User | "Added {username} to server whitelist" |
| `minecraft_verification_generated` | GenerateVerificationCode | User | "Generated verification code for {type} account: {username}" |
| `minecraft_account_linked` | CompleteVerification | User | "Linked {type} account: {username}" |
| `minecraft_rank_synced` | CompleteVerification, SyncMinecraftRanks | User | "Synced Minecraft rank to {rank} for {username}" |
| `minecraft_staff_position_set` | CompleteVerification, SyncMinecraftStaff | User | "Set Minecraft staff position to {dept} for {username}" |
| `minecraft_staff_position_removed` | UnlinkMinecraftAccount, RevokeMinecraftAccount, SyncMinecraftStaff | User | "Removed Minecraft staff position for {username}" |
| `minecraft_rank_reset_requested` | UnlinkMinecraftAccount | User | "Reset rank to default for {username}" |
| `minecraft_whitelist_removal_requested` | UnlinkMinecraftAccount | User | "Removed {username} from server whitelist" |
| `minecraft_account_removed` | UnlinkMinecraftAccount | User | "Removed {type} account: {username}" |
| `minecraft_account_revoked` | RevokeMinecraftAccount | User | "{admin} revoked {type} account: {username}" |
| `minecraft_account_reactivated` | ReactivateMinecraftAccount | User | "Reactivated {type} account: {username}" |
| `minecraft_account_permanently_deleted` | ForceDeleteMinecraftAccount | User | "Admin {admin} permanently deleted {type} account: {username}" |
| `minecraft_reward_granted` | GrantMinecraftReward | User | "Granted {rewardName}: {description} to {username}" |
| `minecraft_account_removed_by_parent` | RemoveChildMinecraftAccount | User (child) | "{parent} removed {type} account: {username}" |

---

## 14. Data Flow Diagrams

### Verification Flow (Linking a New Account)

```
User visits /settings/minecraft-accounts
  -> Enters username + selects Java/Bedrock
  -> Clicks "Link Account"
    -> VoltComponent::generateCode()
      -> $this->authorize('link-minecraft-account') [Gate]
      -> $this->validate(['username' => 'required|...'])
      -> GenerateVerificationCode::run($user, $accountType, $username)
        -> Checks: brig, max accounts, rate limit
        -> MojangApiService::getJavaPlayerUuid() OR McProfileService::getBedrockPlayerInfo()
        -> MinecraftAccount::whereNormalizedUuid() check for duplicates
        -> Generates 6-char code
        -> MinecraftAccount::create(status: 'verifying')
        -> MinecraftRconService::executeCommand('whitelist add ...')
        -> MinecraftVerification::create(status: 'pending')
        -> RecordActivity x2 (whitelisted, verification_generated)
      -> Returns code + expires_at to UI
      -> Flux::toast('Verification code generated!')

User joins Minecraft server (temp whitelisted)
  -> Enters code via in-game plugin
  -> Plugin sends POST /api/minecraft/verify (with server_token)
    -> Validates server_token against config('services.minecraft.verification_token')
    -> $request->validate([code, minecraft_username, minecraft_uuid, ...])
    -> CompleteVerification::run($code, $username, $uuid, ...)
      -> Finds pending verification by code
      -> Matches username + UUID (with Floodgate/Bedrock fallbacks)
      -> DB::transaction:
        -> MinecraftAccount -> status = Active, verified_at = now()
        -> MinecraftVerification -> status = 'completed'
        -> Auto-assigns primary if needed
        -> RecordActivity: minecraft_account_linked
      -> SendMinecraftCommand::dispatch('lh setmember ...') [rank sync]
      -> SendMinecraftCommand::dispatch('lh setstaff ...') [if staff]
      -> GrantNewPlayerReward::run() [if enabled]
    -> Returns JSON {success: true, message: '...'}

Meanwhile on Settings Page:
  -> wire:poll.15s calls checkVerification()
    -> Finds completed verification
    -> Updates UI to show linked account
    -> Flux::toast('Account verified successfully!')
```

### Unlinking an Account

```
User clicks "Unlink" on settings page
  -> Confirms via modal
    -> VoltComponent::unlinkAccount($accountId)
      -> $this->authorize('delete', $account) [Policy]
      -> UnlinkMinecraftAccount::run($account, $user)
        -> Verifies ownership + Active status
        -> RCON: lh setmember <username> default (rank reset)
        -> RCON: lh removestaff <username> (if staff)
        -> RCON: whitelist remove <username> (or fwhitelist remove <uuid>)
        -> MinecraftAccount -> status = Removed
        -> AutoAssignPrimaryAccount::run() if was primary
        -> RecordActivity x4
      -> Flux::toast('Account removed successfully.')
```

### Staff Revoking an Account

```
Staff views user profile -> clicks Revoke on MC account
  -> Confirms via modal
    -> VoltComponent::revokeMinecraftAccount()
      -> $this->authorize('revoke', $account) [Policy: Admin or Eng/Cmd Officer]
      -> RevokeMinecraftAccount::run($account, $admin)
        -> Checks revoke permission
        -> RCON: rank reset + staff removal + whitelist remove
        -> MinecraftAccount -> status = Removed
        -> AutoAssignPrimaryAccount if was primary
        -> RecordActivity: minecraft_account_revoked
      -> Flux::toast('Account revoked successfully.')
```

### Expired Verification Cleanup (Scheduled)

```
Schedule: every 5 minutes -> minecraft:cleanup-expired
  -> Pass 1: Find all pending+expired verifications
    -> For each: ExpireVerification::run($verification)
      -> Verification -> status = 'expired'
      -> Account -> status = 'cancelled'
      -> RCON: whitelist remove (synchronous)
      -> If success: kick player, delete account
      -> If failure: account stays cancelled (retry pool)
  -> Pass 2: Find all cancelled accounts
    -> For each: retry RCON whitelist remove
      -> If success: delete account
      -> If failure: try again next cycle
```

### Username Refresh (Scheduled)

```
Schedule: daily at 3:00 AM -> minecraft:refresh-usernames
  -> Calculates daily batch = ceil(total_active / 30)
  -> Selects stale accounts (not checked in 30 days), prioritizing active users
  -> For each account:
    -> Java: MojangApiService::getJavaUsername(uuid)
    -> Bedrock: McProfileService::getBedrockGamertag(uuid)
    -> If name changed: update username + last_username_check_at
    -> If same/failed: update last_username_check_at only
```

### Parent Managing Child's MC Account

```
Parent visits Parent Portal
  -> Toggles "Join Minecraft Server" switch for child
    -> VoltComponent::togglePermission($childId, 'minecraft')
      -> UpdateChildPermission::run($parent, $child, 'minecraft', $newValue)

  -> Enters child's MC username and clicks "Link"
    -> VoltComponent::generateVerificationForChild($childId, $username, $accountTypeStr)
      -> Validates child.parent_allows_minecraft == true
      -> GenerateVerificationCode::run($child, $accountType, $username)
      -> Displays code for child to enter in-game

  -> Clicks "Remove" on child's MC account
    -> Confirms via modal
    -> VoltComponent::removeChildMcAccount()
      -> RemoveChildMinecraftAccount::run($parent, $accountId)
        -> Verifies parent-child relationship
        -> RCON: whitelist remove + rank reset
        -> MinecraftAccount -> status = Removed
        -> AutoAssignPrimaryAccount if was primary
```

---

## 15. Configuration

| Key | Default | Purpose |
|-----|---------|---------|
| `lighthouse.max_minecraft_accounts` | `2` | Max linked MC accounts per user |
| `lighthouse.minecraft_verification_grace_period_minutes` | `30` | Minutes before verification code expires |
| `lighthouse.minecraft_verification_rate_limit_per_hour` | `10` | Max verification attempts per user per hour |
| `lighthouse.minecraft.server_name` | `'Lighthouse MC'` | Display name of the MC server |
| `lighthouse.minecraft.server_host` | `'play.lighthousemc.net'` | Server address shown to users |
| `lighthouse.minecraft.server_port_java` | `25565` | Java Edition port |
| `lighthouse.minecraft.server_port_bedrock` | `19132` | Bedrock Edition port |
| `lighthouse.minecraft.rewards.new_player_enabled` | `false` | Whether new player rewards are active |
| `lighthouse.minecraft.rewards.new_player_diamonds` | `3` | Diamond value for new player reward |
| `lighthouse.minecraft.rewards.new_player_exchange_rate` | `32` | Lumens per diamond exchange rate |
| `services.minecraft.rcon_host` | `'localhost'` | RCON server host |
| `services.minecraft.rcon_port` | `25575` | RCON server port |
| `services.minecraft.rcon_password` | `null` | RCON server password |
| `services.minecraft.verification_token` | `null` | Token the MC plugin sends with webhook requests |

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/Minecraft/MinecraftAccountTest.php` | 4 | Model basics (relationship, casts, creation) |
| `tests/Feature/Minecraft/GenerateVerificationCodeTest.php` | 13 | Code generation, validation, rate limiting, RCON failure |
| `tests/Feature/Minecraft/CompleteVerificationTest.php` | 14 | Verification completion, UUID handling, Bedrock fallbacks, staff sync |
| `tests/Feature/Minecraft/MinecraftVerificationWebhookTest.php` | 12 | Webhook endpoint: auth, validation, completion, rate limiting |
| `tests/Feature/Minecraft/MinecraftSettingsPageTest.php` | 14 | Settings page: display, link, unlink, poll, authorization |
| `tests/Feature/Minecraft/UnlinkMinecraftAccountTest.php` | 8 | Unlinking: ownership, RCON commands, activity log, primary reassignment |
| `tests/Feature/Minecraft/RevokeMinecraftAccountTest.php` | 5 | Revocation: admin access, activity log, RCON, primary reassignment |
| `tests/Feature/Minecraft/ReactivateMinecraftAccountTest.php` | 8 | Reactivation: status check, limits, brig, RCON failure, primary |
| `tests/Feature/Minecraft/ForceDeleteMinecraftAccountTest.php` | 5 | Force delete: admin only, status check, UUID release |
| `tests/Feature/Minecraft/SetPrimaryMinecraftAccountTest.php` | 5 | Primary setting: active only, swap, isolation |
| `tests/Feature/Minecraft/AutoAssignPrimaryAccountTest.php` | 4 | Auto-assign: no-op if exists, first active by ID, skip removed |
| `tests/Feature/Minecraft/RemoveVerifyingAccountTest.php` | 5 | Remove verifying: RCON offline handling, ownership, state check |
| `tests/Feature/Minecraft/CleanupExpiredVerificationsTest.php` | 2 | Expiry cleanup: whitelist removal + kick |
| `tests/Feature/Minecraft/MinecraftCommandsTest.php` | 6 | RCON service: logging, execution time, failure handling |
| `tests/Feature/Minecraft/RefreshMinecraftUsernamesTest.php` | 7 | Username refresh: batching, priority, API calls, skip recent |
| `tests/Feature/Minecraft/GrantNewPlayerRewardTest.php` | 6 | New player reward: enable/disable, dedup, config, RCON, activity |
| `tests/Feature/Policies/MinecraftAccountPolicyTest.php` | 28 | Policy: all abilities, role combinations |
| `tests/Feature/Actions/Actions/RemoveChildMinecraftAccountTest.php` | 5 | Parent removing child's MC account |

**Total: 151 tests across 18 files**

### Test Case Inventory

**MinecraftAccountTest.php:**
- belongs to user
- casts account type to enum
- casts timestamps correctly
- can create java account
- can create bedrock account

**GenerateVerificationCodeTest.php:**
- generates verification code for java account
- generates verification code for bedrock account - stores clean gamertag
- bedrock verification strips dot prefix if user enters it
- excludes confusing characters from code
- rejects when max accounts reached
- rate limits verification attempts
- validates java username exists via mojang api
- validates bedrock username exists via mcprofile api
- handles rcon failure gracefully
- sets correct expiration time
- rejects when user already has this account verified
- rejects when user already has this account verified with uuid dashes
- rejects when user is in the brig

**CompleteVerificationTest.php:**
- completes verification and creates account
- fails if verification not found
- fails if verification expired
- fails if uuid already linked
- uses database transaction
- records activity log
- normalizes uuid with dashes
- completes verification for bedrock account with dot-prefix username
- completes verification for linked bedrock account using bedrock fallback
- stores bedrock xuid on verification
- does not overwrite existing bedrock xuid
- bedrock fallback does not match when bedrock_username is not provided
- completes verification for unlinked bedrock with clean gamertag stored and dot-prefixed incoming
- completes verification for linked bedrock with clean gamertag via bedrock_username
- syncs staff position when staff member verifies account

**MinecraftVerificationWebhookTest.php:**
- completes verification with valid token
- rejects invalid server token
- validates required fields
- rejects non-existent verification code
- rejects expired verification code
- rejects already completed verification
- rejects duplicate uuid
- accepts uuid with or without dashes
- completes verification for linked bedrock account via webhook
- rate limits requests
- case insensitive code matching
- records activity log on successful verification

**MinecraftSettingsPageTest.php:**
- displays minecraft settings page
- requires authentication
- displays linked accounts
- shows verification form when no active verification
- stowaway cannot see link form for minecraft accounts
- traveler can see link form for minecraft accounts
- generates verification code
- displays active verification code
- validates username required
- removes linked account by setting status to removed
- cannot remove another users account
- shows remaining account slots
- shows max accounts reached
- polls for verification completion
- checkVerification cleans up when timer expires while user is on the page

**UnlinkMinecraftAccountTest.php:**
- unlinks minecraft account by setting status to removed
- prevents unlinking other users account
- sends rank reset and whitelist remove commands via rcon
- sends removestaff command when user has a staff department
- records activity log
- removed account does not count toward account limit
- auto-assigns new primary when primary account is unlinked
- does not change primary when non-primary account is unlinked

**RevokeMinecraftAccountTest.php:**
- admin can revoke account by setting status to removed
- regular user cannot revoke
- records activity for affected user
- sends sync whitelist remove command
- auto-assigns new primary when primary account is revoked

**ReactivateMinecraftAccountTest.php:**
- reactivates a removed account back to active
- fails if account is not in removed status
- fails if user has reached max account limit
- fails if user is in the brig
- fails if whitelist add command fails
- records activity log on reactivation
- sets reactivated account as primary when user has no primary
- does not change existing primary when reactivating additional account

**ForceDeleteMinecraftAccountTest.php:**
- admin can permanently delete a removed account
- regular user cannot permanently delete
- cannot permanently delete an active account
- records activity log for permanent deletion
- releases UUID so it can be re-registered

**SetPrimaryMinecraftAccountTest.php:**
- sets an active account as primary
- clears primary from other accounts when setting new primary
- does not set a removed account as primary
- does not set a verifying account as primary
- does not affect other users accounts

**AutoAssignPrimaryAccountTest.php:**
- assigns first active account as primary when user has no primary
- does nothing when user already has a primary account
- does nothing when user has no active accounts
- skips removed accounts when auto-assigning

**RemoveVerifyingAccountTest.php:**
- removes a verifying account when user has no active verification code state
- expires the associated verification record when removing a verifying account
- marks account cancelled if RCON server is offline
- cannot remove another users verifying account
- cannot remove a non-verifying account via removeVerifyingAccount

**CleanupExpiredVerificationsTest.php:**
- removes whitelist and kicks player when verification expires
- does not crash if kick fails and still deletes account

**MinecraftCommandsTest.php:**
- cleanup command removes expired verifications
- cleanup command sends whitelist remove command via rcon
- cleanup command runs successfully
- rcon service logs commands
- rcon service records execution time
- rcon service handles connection failure

**RefreshMinecraftUsernamesTest.php:**
- refreshes usernames in staggered batches
- prioritizes accounts from active users
- updates java username from mojang api
- updates bedrock username from mcprofile api
- handles api failures gracefully
- skips recently checked accounts
- command runs successfully

**GrantNewPlayerRewardTest.php:**
- grants reward when enabled and first account
- does not grant reward when disabled
- does not grant duplicate reward
- calculates correct lumen amount from config
- records activity log entry
- sends rcon money command

**MinecraftAccountPolicyTest.php:**
- admin can view any minecraft accounts
- regular user cannot view any minecraft accounts
- always returns false through policy (view, create, update, restore)
- user can delete their own minecraft account
- admin can delete any minecraft account
- other user cannot delete someone elses minecraft account
- user can reactivate their own minecraft account
- admin can reactivate any minecraft account
- other user cannot reactivate someone elses minecraft account
- admin can force delete a minecraft account
- regular user cannot force delete a minecraft account
- admin can revoke a minecraft account
- engineer officer can revoke a minecraft account
- command officer can revoke a minecraft account
- steward officer cannot revoke a minecraft account
- engineer crew member cannot revoke a minecraft account
- regular user cannot revoke a minecraft account
- admin cannot revoke a removed minecraft account
- admin cannot revoke a cancelled minecraft account
- admin cannot revoke a verifying minecraft account
- admin can view uuid
- engineer crew member can view uuid
- officer in any department can view uuid
- regular user cannot view uuid
- non-engineer crew member cannot view uuid
- admin can view staff audit fields
- staff member can view staff audit fields
- regular user cannot view staff audit fields

**RemoveChildMinecraftAccountTest.php:**
- removes an active minecraft account
- rejects removal by non-parent
- rejects removal of non-active account
- fails gracefully when whitelist removal fails
- records activity after removal

### Coverage Gaps

- **Parent portal Minecraft linking** — no dedicated test file for `generateVerificationForChild()`, `checkChildVerification()`, or `removeChildMcAccount()` flows
- **Admin MC users page** — no tests for the admin MC user manager component (search, sort, showAccount, reactivate/forceDelete from admin context)
- **Admin MC command log page** — no tests for the command log viewer component
- **BannedStatus** — Set by `PutUserInBrig` action (cross-feature), restored by `ReleaseUserFromBrig`. No dedicated Minecraft-specific tests for the ban/restore flow.
- **ParentDisabled status** — Set by `UpdateChildPermission` action (cross-feature) when parent disables MC access. No dedicated Minecraft-specific tests for this flow.
- **SyncMinecraftPermissions** — no dedicated tests for this orchestrating action
- **SyncMinecraftRanks/SyncMinecraftStaff** — no dedicated test files
- **GrantMinecraftReward** — no dedicated test file (tested indirectly via GrantNewPlayerRewardTest)
- **RemoveChildMinecraftAccount** — has dedicated tests at `tests/Feature/Actions/Actions/RemoveChildMinecraftAccountTest.php` but located in a different directory from other MC tests
- **Profile page MC management** — no tests for revoke/reactivate/forceDelete from the profile view context

---

## 17. File Map

**Models:**
- `app/Models/MinecraftAccount.php`
- `app/Models/MinecraftVerification.php`
- `app/Models/MinecraftCommandLog.php`
- `app/Models/MinecraftReward.php`
- `app/Models/User.php` (minecraft-related relationships and fields)

**Enums:**
- `app/Enums/MinecraftAccountType.php`
- `app/Enums/MinecraftAccountStatus.php`

**Actions:**
- `app/Actions/GenerateVerificationCode.php`
- `app/Actions/CompleteVerification.php`
- `app/Actions/ExpireVerification.php`
- `app/Actions/UnlinkMinecraftAccount.php`
- `app/Actions/RevokeMinecraftAccount.php`
- `app/Actions/ReactivateMinecraftAccount.php`
- `app/Actions/SetPrimaryMinecraftAccount.php`
- `app/Actions/AutoAssignPrimaryAccount.php`
- `app/Actions/ForceDeleteMinecraftAccount.php`
- `app/Actions/SyncMinecraftRanks.php`
- `app/Actions/SyncMinecraftStaff.php`
- `app/Actions/SyncMinecraftPermissions.php`
- `app/Actions/SendMinecraftCommand.php`
- `app/Actions/GrantMinecraftReward.php`
- `app/Actions/GrantNewPlayerReward.php`
- `app/Actions/RemoveChildMinecraftAccount.php`

**Policies:**
- `app/Policies/MinecraftAccountPolicy.php`

**Gates:** `app/Providers/AuthServiceProvider.php` — gates: `link-minecraft-account`, `view-mc-command-log`

**Notifications:**
- `app/Notifications/MinecraftCommandNotification.php`

**Jobs:** None (RCON dispatch uses queued notifications)

**Services:**
- `app/Services/MinecraftRconService.php`
- `app/Services/FakeMinecraftRconService.php`
- `app/Services/MojangApiService.php`
- `app/Services/McProfileService.php`

**Controllers:** None (all routes handled by Volt components or inline closure)

**Volt Components:**
- `resources/views/livewire/settings/minecraft-accounts.blade.php`
- `resources/views/livewire/admin-manage-mc-users-page.blade.php`
- `resources/views/livewire/admin-manage-mc-command-log-page.blade.php`
- `resources/views/livewire/admin-control-panel-tabs.blade.php` (tabs for MC users and command log)
- `resources/views/livewire/users/display-basic-details.blade.php` (MC section on profile)
- `resources/views/livewire/parent-portal/index.blade.php` (child MC management)
- `resources/views/livewire/staff/page.blade.php` (MC account display)
- `resources/views/livewire/dashboard/command-community-engagement.blade.php` (MC engagement metrics)

**Blade Components:**
- `resources/views/components/minecraft/mc-account-detail-modal.blade.php`

**Routes:**
- `settings.minecraft-accounts` — `GET /settings/minecraft-accounts`
- (unnamed) — `POST /api/minecraft/verify`

**Migrations:**
- `database/migrations/2026_02_17_064252_create_minecraft_accounts_table.php`
- `database/migrations/2026_02_17_064256_create_minecraft_verifications_table.php`
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

**Console Commands:**
- `app/Console/Commands/CleanupExpiredVerifications.php`
- `app/Console/Commands/RefreshMinecraftUsernames.php`

**Tests:**
- `tests/Feature/Minecraft/MinecraftAccountTest.php`
- `tests/Feature/Minecraft/GenerateVerificationCodeTest.php`
- `tests/Feature/Minecraft/CompleteVerificationTest.php`
- `tests/Feature/Minecraft/MinecraftVerificationWebhookTest.php`
- `tests/Feature/Minecraft/MinecraftSettingsPageTest.php`
- `tests/Feature/Minecraft/UnlinkMinecraftAccountTest.php`
- `tests/Feature/Minecraft/RevokeMinecraftAccountTest.php`
- `tests/Feature/Minecraft/ReactivateMinecraftAccountTest.php`
- `tests/Feature/Minecraft/ForceDeleteMinecraftAccountTest.php`
- `tests/Feature/Minecraft/SetPrimaryMinecraftAccountTest.php`
- `tests/Feature/Minecraft/AutoAssignPrimaryAccountTest.php`
- `tests/Feature/Minecraft/RemoveVerifyingAccountTest.php`
- `tests/Feature/Minecraft/CleanupExpiredVerificationsTest.php`
- `tests/Feature/Minecraft/MinecraftCommandsTest.php`
- `tests/Feature/Minecraft/RefreshMinecraftUsernamesTest.php`
- `tests/Feature/Minecraft/GrantNewPlayerRewardTest.php`
- `tests/Feature/Policies/MinecraftAccountPolicyTest.php`
- `tests/Feature/Actions/Actions/RemoveChildMinecraftAccountTest.php`

**Config:**
- `config/lighthouse.php` — `max_minecraft_accounts`, `minecraft_verification_grace_period_minutes`, `minecraft_verification_rate_limit_per_hour`, `minecraft.*`
- `config/services.php` — `minecraft.rcon_host`, `minecraft.rcon_port`, `minecraft.rcon_password`, `minecraft.verification_token`

---

## 18. Known Issues & Improvement Opportunities

1. **Banned and ParentDisabled statuses are set by cross-feature actions only.** `MinecraftAccountStatus::Banned` is set by `PutUserInBrig` and restored by `ReleaseUserFromBrig`. `ParentDisabled` is set by `UpdateChildPermission`. These flows are tested within the Brig and Parent Portal feature test suites, but there are no Minecraft-specific tests validating the whitelist state transitions for these statuses.

2. **Duplicate `make_verified_at_nullable` migrations.** There are two migrations with the same purpose: `2026_02_20_060607_make_verified_at_nullable...` and `2026_02_20_072300_make_verified_at_nullable...`. The second one likely supersedes the first.

3. **Webhook route lacks named route.** The `POST /api/minecraft/verify` route has no `->name()`, making it harder to reference in tests or generate URLs.

4. **Webhook route uses inline closure.** The verification webhook logic is defined inline in `routes/web.php` rather than in a controller. For a feature this complex, a dedicated controller (e.g., `MinecraftVerificationController`) would improve maintainability and testability.

5. **SQL injection risk in admin search.** The admin MC users page uses `"%{$this->search}%"` directly in `like` clauses. While Livewire sanitizes input somewhat, using query parameter binding (`?`) would be safer. The same pattern appears in the command log page.

6. **RecordActivity inconsistency.** Some actions call `RecordActivity::handle()` while others call `RecordActivity::run()`. With the `AsAction` trait, both should work, but the inconsistency could confuse developers.

7. **Missing test coverage for parent portal MC flows.** The parent portal's MC account linking, verification checking, and removal flows have no dedicated tests.

8. **Missing test coverage for admin management pages.** Neither the MC users manager nor the command log page has any Livewire component tests.

9. **RCON service creates log entry before execution.** `MinecraftRconService::executeCommand()` creates a `minecraft_command_logs` entry with status 'failed' before executing the command, then updates it. If the process crashes between creation and update, stale 'failed' entries accumulate.

10. **Avatar URL construction is hardcoded.** `GenerateVerificationCode` hardcodes `https://mc-heads.net/avatar/` for avatar URLs. If this service changes or goes down, all new avatars break. Consider making this configurable.

11. **No whitelist sync on username change.** When `RefreshMinecraftUsernames` detects a username change for a Java account, it updates the database but does not update the server whitelist (the old username remains whitelisted, the new one is not). This could cause players to lose server access after a name change.

12. **`command_id` migration remnant.** The migration `2026_02_20_060607` adds a `command_id` column, and `2026_02_22_000000` drops it. This column was a short-lived experiment. The migrations work correctly but add unnecessary history.
