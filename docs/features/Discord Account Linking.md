# Discord Account Linking -- Technical Documentation

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

Discord Account Linking allows users to connect their Discord account to the Lighthouse Website via OAuth2 (using Laravel Socialite). Once linked, the system automatically syncs Discord server roles to match the user's membership level (Traveler, Resident, Citizen) and staff position (department + rank). Users also receive notifications via Discord DM through a custom `DiscordChannel` notification channel.

The feature is available to users at **Stowaway rank or above** who are **not in the Brig** and whose **parent allows Discord** (for child accounts). Parents can toggle Discord access per child via the Parent Portal, which changes the account status to `ParentDisabled` and strips all managed roles. The Brig system also integrates -- when a user is brigged, their Discord accounts are marked `Brigged` and all managed roles are removed; upon release, accounts are restored.

The feature includes an admin panel for viewing all linked Discord accounts and a Discord API log for auditing all bot API calls (role adds/removes, DMs, channel messages). Announcements can be automatically posted to a Discord channel. All Discord API interactions go through a centralized `DiscordApiService` with rate limit handling, retry logic, and comprehensive logging to `discord_api_logs`.

---

## 2. Database Schema

### `discord_accounts` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint (PK) | No | auto | |
| `user_id` | bigint (FK) | No | | References `users.id`, cascadeOnDelete |
| `discord_user_id` | string | No | | Unique; Discord's snowflake user ID |
| `username` | string | No | | Discord username |
| `global_name` | string | Yes | null | Discord display name |
| `avatar_hash` | string | Yes | null | Used to construct CDN avatar URL |
| `access_token` | text | No | | Encrypted at rest via Laravel's `encrypted` cast |
| `refresh_token` | text | Yes | null | Encrypted at rest via Laravel's `encrypted` cast |
| `token_expires_at` | timestamp | Yes | null | When the OAuth access token expires |
| `status` | string | No | `'active'` | Cast to `DiscordAccountStatus` enum |
| `verified_at` | timestamp | Yes | null | When the account was linked |
| `created_at` | timestamp | No | auto | |
| `updated_at` | timestamp | No | auto | |

**Indexes:** `user_id` (explicit index), `discord_user_id` (unique)
**Foreign Keys:** `user_id` -> `users.id` (cascade on delete)
**Migration:** `database/migrations/2026_02_24_220445_create_discord_accounts_table.php`

### `discord_api_logs` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint (PK) | No | auto | |
| `user_id` | bigint (FK) | Yes | null | References `users.id`, nullOnDelete |
| `method` | string | No | | HTTP method (GET, POST, PUT, DELETE) |
| `endpoint` | string | No | | Discord API path |
| `action_type` | string | No | | e.g. `add_role`, `remove_role`, `send_dm`, `get_member` |
| `target` | string | Yes | null | Usually a Discord user ID |
| `status` | enum('success','failed') | No | | |
| `http_status` | smallint unsigned | Yes | null | HTTP response status code |
| `response` | text | Yes | null | Response body (only stored on failure) |
| `error_message` | text | Yes | null | Error body (only stored on failure) |
| `meta` | json | Yes | null | Additional context (e.g. `role_id`) |
| `executed_at` | timestamp | No | current | When the API call was made |
| `execution_time_ms` | integer | Yes | null | Duration in milliseconds |
| `created_at` | timestamp | No | auto | |
| `updated_at` | timestamp | No | auto | |

**Indexes:** `action_type` (index), `status` (index), compound `(action_type, status)`, `executed_at`
**Foreign Keys:** `user_id` -> `users.id` (null on delete)
**Migration:** `database/migrations/2026_03_06_200000_create_discord_api_logs_table.php`

---

## 3. Models & Relationships

### DiscordAccount (`app/Models/DiscordAccount.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `user()` | belongsTo | User | Owner of the linked account |

**Scopes:**
- `scopeActive($query)` -- Filters to `status = 'active'`

**Key Methods:**
- `avatarUrl(): string` -- Returns CDN URL using avatar_hash, or default Discord avatar based on `discord_user_id % 5`
- `displayName(): string` -- Returns `global_name` if set, otherwise `username`

**Casts:**
- `status` => `DiscordAccountStatus::class`
- `access_token` => `'encrypted'`
- `refresh_token` => `'encrypted'`
- `token_expires_at` => `'datetime'`
- `verified_at` => `'datetime'`

### DiscordApiLog (`app/Models/DiscordApiLog.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `user()` | belongsTo | User | User who triggered the API call (nullable) |

**Scopes:**
- `scopeSuccessful($query)` -- Filters to `status = 'success'`
- `scopeFailed($query)` -- Filters to `status = 'failed'`

**Casts:**
- `meta` => `'array'`
- `executed_at` => `'datetime'`

### User (`app/Models/User.php`) -- Discord-related parts

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `discordAccounts()` | hasMany | DiscordAccount | All Discord accounts for the user |

**Key Methods:**
- `hasDiscordLinked(): bool` -- Returns true if user has any active Discord accounts
- `discordAvatarUrl(): ?string` -- Protected attribute accessor; returns avatar URL from first active Discord account

**Relevant Fillable Fields:**
- `parent_allows_discord` (boolean, default true) -- Parent toggle for child accounts

---

## 4. Enums Reference

### DiscordAccountStatus (`app/Enums/DiscordAccountStatus.php`)

| Case | Value | Label | Color | Notes |
|------|-------|-------|-------|-------|
| `Active` | `'active'` | Active | green | Normal linked state |
| `Brigged` | `'brigged'` | In the Brig | red | Set when user is put in the Brig |
| `ParentDisabled` | `'parent_disabled'` | Disabled by Parent | purple | Set when parent disables Discord access |

### MembershipLevel -- `discordRoleId()` method (`app/Enums/MembershipLevel.php`)

| Case | Discord Role Config Key | Notes |
|------|------------------------|-------|
| `Drifter` | `null` | No Discord role |
| `Stowaway` | `null` | No Discord role |
| `Traveler` | `lighthouse.discord.roles.traveler` | |
| `Resident` | `lighthouse.discord.roles.resident` | |
| `Citizen` | `lighthouse.discord.roles.citizen` | |

### StaffDepartment -- `discordRoleId()` method (`app/Enums/StaffDepartment.php`)

| Case | Discord Role Config Key |
|------|------------------------|
| `Command` | `lighthouse.discord.roles.staff_command` |
| `Chaplain` | `lighthouse.discord.roles.staff_chaplain` |
| `Engineer` | `lighthouse.discord.roles.staff_engineer` |
| `Quartermaster` | `lighthouse.discord.roles.staff_quartermaster` |
| `Steward` | `lighthouse.discord.roles.staff_steward` |

### StaffRank -- `discordRoleId()` method (`app/Enums/StaffRank.php`)

| Case | Discord Role Config Key |
|------|------------------------|
| `None` | `null` |
| `JrCrew` | `lighthouse.discord.roles.rank_jr_crew` |
| `CrewMember` | `lighthouse.discord.roles.rank_crew_member` |
| `Officer` | `lighthouse.discord.roles.rank_officer` |

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

| Gate Name | Who Can Pass | Logic Summary |
|-----------|-------------|---------------|
| `link-discord` | Stowaway+ AND not brigged AND parent allows Discord | `$user->isAtLeastLevel(MembershipLevel::Stowaway) && ! $user->in_brig && $user->parent_allows_discord` |
| `view-discord-api-log` | Admin, Officer+, or Engineer | Shared `$canViewLogs` closure (Admin or Officer+ rank) |

### Policies

#### DiscordAccountPolicy (`app/Policies/DiscordAccountPolicy.php`)

**`before()` hook:** None

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `viewAny` | Admin or Officer+ | `$user->isAdmin() \|\| $user->isAtLeastRank(StaffRank::Officer)` |
| `view` | Own account or Admin | `$user->id === $discordAccount->user_id \|\| $user->isAdmin()` |
| `create` | Nobody | Always returns `false` (accounts created via OAuth only) |
| `update` | Nobody | Always returns `false` |
| `delete` | Own account or Admin | `$user->id === $discordAccount->user_id \|\| $user->isAdmin()` |

### Permissions Matrix

| User Type | Link Account | Unlink Own | View Own | View All (Admin) | Revoke Others | Sync Roles (Manual) | View API Log |
|-----------|-------------|------------|----------|-------------------|---------------|---------------------|-------------|
| Drifter | No | N/A | N/A | No | No | No | No |
| Stowaway | Yes | Yes | Yes | No | No | No | No |
| Traveler+ | Yes | Yes | Yes | No | No | No | No |
| Jr Crew | Yes | Yes | Yes | No | No | No | No |
| Crew Member | Yes | Yes | Yes | No | No | Yes (`manage-stowaway-users`) | No |
| Officer | Yes | Yes | Yes | Yes | No | Yes | Yes |
| Admin | Yes | Yes | Yes | Yes | Yes | Yes | Yes |
| Brigged | No | No | N/A | No | No | No | No |
| Parent-Disabled | No | No | N/A | No | No | No | No |

---

## 6. Routes

| Method | URL | Middleware | Handler | Route Name |
|--------|-----|-----------|---------|------------|
| GET | `/settings/discord-account` | `auth` | Volt: `settings.discord-account` | `settings.discord-account` |
| GET | `/auth/discord/redirect` | `auth` | `DiscordAuthController@redirect` | `auth.discord.redirect` |
| GET | `/auth/discord/callback` | `auth` | `DiscordAuthController@callback` | `auth.discord.callback` |

---

## 7. User Interface Components

### Discord Settings Page
**File:** `resources/views/livewire/settings/discord-account.blade.php`
**Route:** `/settings/discord-account` (route name: `settings.discord-account`)

**Purpose:** Manage linked Discord accounts -- view, link new, unlink existing, join server with auto role sync.

**Authorization:** `link-discord` gate for linking; `delete` policy on DiscordAccount for unlinking; `manage-stowaway-users` gate for manual role sync button.

**User Actions Available:**
- **Link Account** -> Redirects to `auth.discord.redirect` (OAuth flow) -> `LinkDiscordAccount::run()` -> success/error flash
- **Unlink Account** -> `confirmUnlink($accountId)` opens modal -> `unlinkAccount()` calls `UnlinkDiscordAccount::run()` -> Flux toast
- **Join Server** -> Opens invite URL in new tab -> `joinServerClicked()` sets flag -> auto-checks guild membership at 10s and 30s via `checkGuildMembership()` -> syncs roles if found
- **Sync Roles** (staff only) -> `syncRoles()` calls `SyncDiscordRoles::run()` + `SyncDiscordStaff::run()` -> Flux toast

**UI Elements:**
- Session success/error callouts
- Linked accounts list with avatar, display name, username, status badge, unlink button
- Account limit warning callout
- Join Server card with invite link and delayed membership check animation
- Link Account card (only shown when gate passes and slots available)
- Parent-disabled / rank-too-low informational callouts
- Unlink confirmation modal (`confirm-unlink-discord`)

### Admin Discord Users Page
**File:** `resources/views/livewire/admin-manage-discord-users-page.blade.php`
**Route:** Embedded as tab panel in Admin Control Panel

**Purpose:** View all linked Discord accounts across all users with search and sorting.

**Authorization:** `viewAny` policy on `DiscordAccount` (Admin or Officer+)

**UI Elements:**
- Search input (username, display name, site user name)
- Sortable table: Discord Username (with avatar), Display Name, Site User (link to profile), Status (badge), Linked Date
- Pagination (15 per page)

### Admin Discord API Log Page
**File:** `resources/views/livewire/admin-manage-discord-api-log-page.blade.php`
**Route:** Embedded as tab panel in Admin Control Panel

**Purpose:** View and filter all Discord API calls for auditing and debugging.

**Authorization:** `view-discord-api-log` gate (Admin, Officer+, Engineer)

**UI Elements:**
- Search input (endpoint, target, action type)
- Status filter dropdown (All/Success/Failed)
- Action type filter dropdown (dynamic from DB)
- Table: Date/Time, Action (color-coded badge), Method, Endpoint, Target, Triggered By (user link), Status badge, HTTP status, Response/Error (truncated), Execution time (ms)
- Pagination (25 per page)

### Admin Control Panel Tabs
**File:** `resources/views/livewire/admin-control-panel-tabs.blade.php`

**Discord Integration:**
- "Users" tab group includes "Discord Users" tab (requires `viewAny` on `DiscordAccount`)
- "Logs" tab group includes "Discord API Log" tab (requires `view-discord-api-log` gate)

### User Profile Page -- Discord Section
**File:** `resources/views/livewire/users/display-basic-details.blade.php`

**Discord Integration:**
- Displays linked Discord accounts with avatar, display name, username, and status badge
- Owner sees "Manage" link to settings page
- Admins see "Revoke" button per account (calls `revokeDiscordAccount($accountId)` -> `RevokeDiscordAccount::run()`)

### Parent Portal
**File:** `resources/views/livewire/parent-portal/index.blade.php`

**Discord Integration:**
- "Join Discord Server" toggle per child (calls `togglePermission($childId, 'discord')`)
- Shows child's linked Discord accounts with status badges
- Staff viewers see read-only permission badges instead of toggle switches

### Dashboard
**File:** `resources/views/dashboard.blade.php`

**Discord Integration:**
- Account Linking card shows Discord section when `link-discord` gate passes
- Displays count of linked accounts or prompt to link
- "Manage" button links to settings page

### Notification Settings Page
**File:** `resources/views/livewire/settings/notifications.blade.php`

**Discord Integration:**
- Each notification category (Tickets, Account Updates, Announcements, Staff Alerts) has a "Discord DM" toggle
- Discord DM toggle is enabled only when `auth()->user()->hasDiscordLinked()` returns true; otherwise disabled with tooltip "Link a Discord account in Settings to enable"
- Preferences saved to `notification_preferences` JSON column with `discord` key per category

### Profile Settings Page
**File:** `resources/views/livewire/settings/profile.blade.php`

**Discord Integration:**
- Avatar preference select includes "Discord Account" option
- Validates `avatar_preference` with `in:auto,minecraft,discord,gravatar`
- "Auto" mode falls back: Minecraft -> Discord -> Initials

### Settings Layout
**File:** `resources/views/components/settings/layout.blade.php`

**Discord Integration:**
- Settings sidebar includes "Discord" nav link to `settings.discord-account` route

### Command Community Engagement Widget
**File:** `resources/views/livewire/dashboard/command-community-engagement.blade.php`

**Discord Integration:**
- Tracks "New Discord Accounts" metric (counts `DiscordAccount` records created in current/previous iteration period)
- Displays in dashboard 2x2 grid with delta comparison badge
- Clickable to show 3-month timeline chart in modal

### Discord Banner Modal
**File:** `resources/views/components/discord-banner-modal.blade.php`

**Purpose:** Clickable Minecraft server banner that opens a modal with Discord invite link, Bedrock/Java server connection buttons. Uses `openDiscordInvite()` JS function that tries Discord app URI first, falls back to web.

### Join Discord Image
**File:** `resources/views/components/join-discord-image.blade.php`

**Purpose:** Clickable Discord banner image that copies MC server IP to clipboard then redirects to Discord invite URL.

### Additional Discord-Aware Views (Avatar Resolution Only)
The following views eager-load `discordAccounts` solely for avatar URL resolution and have no other Discord functionality:
- `resources/views/livewire/staff/page.blade.php` -- Staff directory
- `resources/views/livewire/ready-room/tickets/tickets-list.blade.php` -- Ticket list
- `resources/views/livewire/ready-room/tickets/view-ticket.blade.php` -- Ticket detail
- `resources/views/livewire/admin-manage-announcements-page.blade.php` -- Announcements admin

### Registration & Birthdate Collection
- `resources/views/livewire/auth/register.blade.php` -- Under-13 registrations set `parent_allows_discord = false`
- `resources/views/livewire/auth/collect-birthdate.blade.php` -- Post-login birthdate collection for under-13 users sets `parent_allows_discord = false`

---

## 8. Actions (Business Logic)

### LinkDiscordAccount (`app/Actions/LinkDiscordAccount.php`)

**Signature:** `handle(User $user, array $discordData): array{success: bool, message: string, account?: DiscordAccount}`

**Step-by-step logic:**
1. Checks max account limit (`config('lighthouse.max_discord_accounts', 1)`) -- returns error if reached
2. Checks if `discord_user_id` already linked to any user -- returns error if duplicate
3. Creates `DiscordAccount` with status `Active`, `verified_at` = now, encrypted tokens
4. Calls `SyncDiscordRoles::run($user)` to sync membership roles
5. If user has a staff department, calls `SyncDiscordStaff::run($user, $user->staff_department)`
6. Logs activity: `RecordActivity::run($user, 'discord_account_linked', "Linked Discord account: {username} ({id})")`
7. Returns success with the account

**Called by:** `DiscordAuthController@callback`

### UnlinkDiscordAccount (`app/Actions/UnlinkDiscordAccount.php`)

**Signature:** `handle(DiscordAccount $account, User $performedBy): void`

**Step-by-step logic:**
1. Calls `DiscordApiService::removeAllManagedRoles($discordUserId)` to strip all roles
2. Stores username and ID for logging, gets owner
3. Deletes the account
4. Logs activity: `RecordActivity::run($owner, 'discord_account_unlinked', "Unlinked Discord account: {username} ({id}) by {performedBy}")`

**Called by:** `settings/discord-account` Volt component (`unlinkAccount()`)

### RevokeDiscordAccount (`app/Actions/RevokeDiscordAccount.php`)

**Signature:** `handle(DiscordAccount $account, User $admin): void`

**Step-by-step logic:**
1. Calls `DiscordApiService::removeAllManagedRoles($discordUserId)` to strip all roles
2. Stores username and ID for logging, gets owner
3. Deletes the account
4. Logs activity: `RecordActivity::run($owner, 'discord_account_revoked', "Discord account revoked by {admin}: {username} ({id})")`

**Called by:** `users/display-basic-details` Volt component (`revokeDiscordAccount()`)

### SyncDiscordRoles (`app/Actions/SyncDiscordRoles.php`)

**Signature:** `handle(User $user): void`

**Step-by-step logic:**
1. Gets user's active Discord accounts -- returns early if none
2. Builds managed role IDs: all `MembershipLevel` Discord role IDs + `verified` role
3. Builds desired role IDs: user's current `membership_level->discordRoleId()` + `verified` role
4. For each account, calls `DiscordApiService::syncManagedRoles()` which diffs current vs desired and only adds/removes changes
5. Logs activity: `RecordActivity::run($user, 'discord_roles_synced', "Synced Discord membership role to {level}")`

**Called by:** `LinkDiscordAccount`, `PromoteUser`, `DemoteUser`, `ReleaseUserFromBrig`, `UpdateChildPermission` (re-enable), `settings/discord-account` component (`checkGuildMembership`, `syncRoles`)

### SyncDiscordStaff (`app/Actions/SyncDiscordStaff.php`)

**Signature:** `handle(User $user, ?StaffDepartment $department = null): void`

**Step-by-step logic:**
1. Gets user's active Discord accounts -- returns early if none
2. Builds managed role IDs: all `StaffDepartment` Discord role IDs + all `StaffRank` Discord role IDs
3. Builds desired role IDs: current department's `discordRoleId()` + current rank's `discordRoleId()` (only if department is set and rank is not `None`)
4. For each account, calls `DiscordApiService::syncManagedRoles()`
5. Logs activity: `discord_staff_synced` (if department provided) or `discord_staff_removed` (if null)

**Called by:** `LinkDiscordAccount`, `SetUsersStaffPosition`, `RemoveUsersStaffPosition`, `ReleaseUserFromBrig`, `settings/discord-account` component (`checkGuildMembership`, `syncRoles`)

### SyncDiscordPermissions (`app/Actions/SyncDiscordPermissions.php`)

**Signature:** `handle(User $user): void`

**Step-by-step logic:**
1. Calls `SyncDiscordRoles::run($user)`
2. Calls `SyncDiscordStaff::run($user, $user->staff_department)`

**Called by:** Not directly referenced in the codebase -- appears to be a convenience wrapper.

### PostAnnouncementToDiscord (`app/Actions/PostAnnouncementToDiscord.php`)

**Signature:** `handle(Announcement $announcement): bool`

**Step-by-step logic:**
1. Gets `services.discord.announcements_channel_id` -- returns false if not configured
2. Builds message with title, content, and dashboard URL
3. Truncates to Discord's 2000 character limit if needed
4. Calls `DiscordApiService::sendChannelMessage($channelId, $content)`
5. Returns true/false; logs warning on failure

**Called by:** `SendAnnouncementNotifications` job

### Cross-Feature Actions that Trigger Discord Sync

#### PromoteUser (`app/Actions/PromoteUser.php`)
- After promoting, calls `SyncDiscordRoles::run($user)` (wrapped in try/catch)

#### DemoteUser (`app/Actions/DemoteUser.php`)
- After demoting, calls `SyncDiscordRoles::run($user)`

#### SetUsersStaffPosition (`app/Actions/SetUsersStaffPosition.php`)
- When department or rank changes, calls `SyncDiscordStaff::run($user, $department)`

#### RemoveUsersStaffPosition (`app/Actions/RemoveUsersStaffPosition.php`)
- Calls `SyncDiscordStaff::run($user)` with no department (removes all staff roles)

#### PutUserInBrig (`app/Actions/PutUserInBrig.php`)
- Strips all managed Discord roles via `DiscordApiService::removeAllManagedRoles()`
- Sets all Active and ParentDisabled Discord accounts to `Brigged` status

#### ReleaseUserFromBrig (`app/Actions/ReleaseUserFromBrig.php`)
- Determines restore status: `Active` if `parent_allows_discord`, else `ParentDisabled`
- Restores Brigged accounts to the determined status
- If restored to Active, syncs membership roles and staff roles

#### UpdateChildPermission (`app/Actions/UpdateChildPermission.php`)
- **Disable Discord:** Removes all managed roles via `DiscordApiService::removeAllManagedRoles()`, sets Active accounts to `ParentDisabled`
- **Enable Discord:** Restores ParentDisabled accounts to `Active`, calls `SyncDiscordRoles::run($child)`

---

## 9. Notifications

13 notifications support Discord DM delivery via the `DiscordChannel`. Each conditionally adds `DiscordChannel::class` to its `via()` channels when `'discord'` is in the notification's `$allowedChannels` array.

| Notification Class | Has `toDiscord()` | Summary |
|-------------------|-------------------|---------|
| `NewAnnouncementNotification` | Yes | Posts announcement title, author, and dashboard link |
| `UserPutInBrigNotification` | Yes | Notifies user they've been placed in the Brig |
| `UserReleasedFromBrigNotification` | Yes | Notifies user they've been released from the Brig |
| `UserPromotedToTravelerNotification` | Yes | Congratulates user on Traveler promotion |
| `UserPromotedToResidentNotification` | Yes | Congratulates user on Resident promotion |
| `UserPromotedToStowawayNotification` | Yes | Congratulates user on Stowaway promotion |
| `TicketAssignedNotification` | Yes | Notifies staff member of ticket assignment |
| `NewTicketNotification` | Yes | Notifies staff of new support ticket |
| `NewTicketReplyNotification` | Yes | Notifies of new ticket reply |
| `MessageFlaggedNotification` | Yes | Notifies staff of flagged message |
| `AccountUnlockedNotification` | Yes | Notifies user their account has been unlocked |
| `BrigTimerExpiredNotification` | Yes | Notifies staff that a brig timer has expired |
| `MeetingReportReminderNotification` | Yes | Reminds staff to submit meeting reports |

The `DiscordChannel` (see [Section 12: Services](#12-services)) iterates all active Discord accounts for the notifiable user and sends a DM to each.

---

## 10. Background Jobs

### SendAnnouncementNotifications (`app/Jobs/SendAnnouncementNotifications.php`)

**Triggered by:** Announcement creation/publishing
**What it does:** Sends `NewAnnouncementNotification` to eligible users AND calls `PostAnnouncementToDiscord::run($announcement)` to post to the Discord announcements channel
**Queue/Delay:** Queued (implements `ShouldQueue`)

---

## 11. Console Commands & Scheduled Tasks

Not applicable for this feature. No Discord-specific Artisan commands or scheduled tasks exist.

---

## 12. Services

### DiscordApiService (`app/Services/DiscordApiService.php`)

**Purpose:** Centralized wrapper around Discord REST API v10 with bot token authentication, rate limit handling, retry logic, and comprehensive audit logging.

**Constructor:** Reads `services.discord.bot_token` and `services.discord.guild_id` from config.

**Key Methods:**
- `getGuildMember(string $discordUserId): ?array` -- Fetches guild member data (roles, etc.)
- `addRole(string $discordUserId, string $roleId): bool` -- Adds a role to a guild member
- `removeRole(string $discordUserId, string $roleId): bool` -- Removes a role from a guild member
- `sendDirectMessage(string $discordUserId, string $content): bool` -- Creates DM channel then sends message
- `syncManagedRoles(string $discordUserId, array $managedRoleIds, array $desiredRoleIds): bool` -- Fetches current roles, computes diff within managed set, adds/removes only what changed
- `sendChannelMessage(string $channelId, string $content): bool` -- Posts a message to a channel
- `removeAllManagedRoles(string $discordUserId): void` -- Removes all roles from `config('lighthouse.discord.roles')`

**Rate Limiting:** `requestWithRetry()` handles HTTP 429 responses by sleeping for `retry_after` duration, up to `$maxRetries = 2` attempts.

**Audit Logging:** Every API call is logged to `DiscordApiLog` via `logApiCall()` with method, endpoint, action type, target, status, HTTP status, response (on failure), execution time, and optional meta.

### FakeDiscordApiService (`app/Services/FakeDiscordApiService.php`)

**Purpose:** Test double that extends `DiscordApiService`, recording all calls in a public `$calls` array instead of making real API requests. Used in tests by binding to the service container.

**Key Behaviors:**
- `getGuildMember()` returns a fake member with empty roles
- `addRole()` / `removeRole()` record calls and return true
- `syncManagedRoles()` records the call and simulates by calling `addRole()` for each desired role
- `removeAllManagedRoles()` records the call
- `sendDirectMessage()` records the call and returns true

### DiscordChannel (`app/Notifications/Channels/DiscordChannel.php`)

**Purpose:** Custom Laravel notification channel for sending Discord DMs.

**How it works:**
1. Checks notification has `toDiscord()` method
2. Gets message string from `$notification->toDiscord($notifiable)`
3. Iterates `$notifiable->discordAccounts()->active()->get()`
4. Sends DM to each account via `DiscordApiService::sendDirectMessage()`
5. Logs warning on failure (does not throw)

---

## 13. Activity Log Entries

| Action String | Logged By | Subject Model | Description |
|---------------|-----------|---------------|-------------|
| `discord_account_linked` | LinkDiscordAccount | User | "Linked Discord account: {username} ({discord_user_id})" |
| `discord_account_unlinked` | UnlinkDiscordAccount | User | "Unlinked Discord account: {username} ({id}) by {performedBy}" |
| `discord_account_revoked` | RevokeDiscordAccount | User | "Discord account revoked by {admin}: {username} ({id})" |
| `discord_roles_synced` | SyncDiscordRoles | User | "Synced Discord membership role to {level}" |
| `discord_staff_synced` | SyncDiscordStaff | User | "Synced Discord staff roles: {department}" |
| `discord_staff_removed` | SyncDiscordStaff | User | "Removed Discord staff roles" |

---

## 14. Data Flow Diagrams

### Linking a Discord Account (OAuth Flow)

```
User clicks "Link Discord Account" on /settings/discord-account
  -> GET /auth/discord/redirect (middleware: auth)
    -> DiscordAuthController::redirect()
      -> Gate::authorize('link-discord')
      -> Socialite::driver('discord')->scopes(['identify','guilds.join'])->redirect()
        -> User authenticates on Discord
        -> Discord redirects to /auth/discord/callback
  -> GET /auth/discord/callback (middleware: auth)
    -> DiscordAuthController::callback()
      -> Gate::authorize('link-discord')
      -> Socialite::driver('discord')->user()
      -> LinkDiscordAccount::run($user, $discordData)
        -> Checks max accounts limit
        -> Checks duplicate discord_user_id
        -> Creates DiscordAccount (status: Active, tokens encrypted)
        -> SyncDiscordRoles::run($user)
          -> DiscordApiService::syncManagedRoles() for each account
        -> SyncDiscordStaff::run($user) [if staff]
        -> RecordActivity::run($user, 'discord_account_linked', ...)
      -> Redirect to settings.discord-account with success flash
```

### Unlinking a Discord Account

```
User clicks "Unlink" on /settings/discord-account
  -> Flux::modal('confirm-unlink-discord')->show()
  -> User clicks "Unlink Account"
    -> VoltComponent::unlinkAccount()
      -> Finds account in user's discordAccounts
      -> $this->authorize('delete', $account)
      -> UnlinkDiscordAccount::run($account, auth()->user())
        -> DiscordApiService::removeAllManagedRoles($discordUserId)
          -> Removes each role from config('lighthouse.discord.roles')
        -> $account->delete()
        -> RecordActivity::run($owner, 'discord_account_unlinked', ...)
      -> Flux::modal('confirm-unlink-discord')->close()
      -> Flux::toast('Discord account unlinked successfully.', variant: 'success')
```

### Admin Revoking a Discord Account

```
Admin clicks "Revoke" on user profile page (/profile/{user})
  -> wire:confirm browser dialog
  -> VoltComponent::revokeDiscordAccount($accountId)
    -> Checks Auth::user()->isAdmin()
    -> Finds account in target user's discordAccounts
    -> RevokeDiscordAccount::run($account, Auth::user())
      -> DiscordApiService::removeAllManagedRoles($discordUserId)
      -> $account->delete()
      -> RecordActivity::run($owner, 'discord_account_revoked', ...)
    -> Flux::toast('Discord account revoked successfully.', variant: 'success')
```

### Role Sync on Promotion/Demotion

```
Staff promotes/demotes user via profile page
  -> PromoteUser::run($user) or DemoteUser::run($user)
    -> Updates membership_level
    -> SyncDiscordRoles::run($user)
      -> Gets active Discord accounts
      -> Builds managed IDs (all membership roles + verified)
      -> Builds desired IDs (new level's role + verified)
      -> DiscordApiService::syncManagedRoles() per account
        -> getGuildMember() to fetch current roles
        -> Computes diff: remove old level role, add new level role
        -> addRole() / removeRole() as needed
      -> RecordActivity::run($user, 'discord_roles_synced', ...)
```

### Brig Integration

```
PutUserInBrig::run($target, ...)
  -> For each Active/ParentDisabled Discord account:
    -> DiscordApiService::removeAllManagedRoles($discordUserId)
    -> Set account status to Brigged
  -> [User cannot link new accounts while in_brig due to gate]

ReleaseUserFromBrig::run($target, ...)
  -> Determine restore status: Active or ParentDisabled (based on parent_allows_discord)
  -> For each Brigged Discord account:
    -> Set status to restore status
  -> If restored to Active:
    -> SyncDiscordRoles::run($target)
    -> SyncDiscordStaff::run($target, $department) [if staff]
```

### Parent Toggling Discord Access

```
Parent toggles "Join Discord Server" switch in Parent Portal
  -> VoltComponent::togglePermission($childId, 'discord')
    -> UpdateChildPermission::run($child, $parent, 'discord', $enabled)

    IF disabling:
      -> Sets parent_allows_discord = false
      -> For each Active Discord account:
        -> DiscordApiService::removeAllManagedRoles()
        -> Set status to ParentDisabled

    IF enabling:
      -> Sets parent_allows_discord = true
      -> For each ParentDisabled Discord account:
        -> Set status to Active
      -> SyncDiscordRoles::run($child)
```

### Guild Join Detection & Role Sync

```
User clicks "Join Server" on /settings/discord-account
  -> Opens invite URL in new tab
  -> VoltComponent::joinServerClicked() sets awaitingGuildJoin = true
  -> JavaScript setTimeout calls checkGuildMembership() at 10s and 30s

VoltComponent::checkGuildMembership()
  -> Gets active Discord accounts
  -> For each: DiscordApiService::getGuildMember($discordUserId)
  -> If member found in guild:
    -> SyncDiscordRoles::run($user)
    -> SyncDiscordStaff::run($user) [if staff]
    -> Flux::toast('Discord roles synced successfully!')
    -> Sets awaitingGuildJoin = false
```

---

## 15. Configuration

### `config/services.php` -- Discord section

| Key | Env Variable | Default | Purpose |
|-----|-------------|---------|---------|
| `services.discord.client_id` | `DISCORD_CLIENT_ID` | | OAuth2 client ID |
| `services.discord.client_secret` | `DISCORD_CLIENT_SECRET` | | OAuth2 client secret |
| `services.discord.redirect` | `DISCORD_REDIRECT_URI` | `/auth/discord/callback` | OAuth2 callback URL |
| `services.discord.bot_token` | `DISCORD_BOT_TOKEN` | | Bot token for API calls |
| `services.discord.guild_id` | `DISCORD_GUILD_ID` | | Target Discord server ID |
| `services.discord.invite_url` | `DISCORD_INVITE_URL` | | Discord invite link shown to users |
| `services.discord.announcements_channel_id` | `DISCORD_ANNOUNCEMENTS_CHANNEL_ID` | | Channel ID for posting announcements |

### `config/lighthouse.php` -- Discord section

| Key | Env Variable | Default | Purpose |
|-----|-------------|---------|---------|
| `lighthouse.max_discord_accounts` | `MAX_DISCORD_ACCOUNTS` | `1` | Max Discord accounts per user |
| `lighthouse.discord.roles.traveler` | `DISCORD_ROLE_TRAVELER` | | Role ID for Traveler members |
| `lighthouse.discord.roles.resident` | `DISCORD_ROLE_RESIDENT` | | Role ID for Resident members |
| `lighthouse.discord.roles.citizen` | `DISCORD_ROLE_CITIZEN` | | Role ID for Citizen members |
| `lighthouse.discord.roles.staff_command` | `DISCORD_ROLE_STAFF_COMMAND` | | Role ID for Command department |
| `lighthouse.discord.roles.staff_chaplain` | `DISCORD_ROLE_STAFF_CHAPLAIN` | | Role ID for Chaplain department |
| `lighthouse.discord.roles.staff_engineer` | `DISCORD_ROLE_STAFF_ENGINEER` | | Role ID for Engineer department |
| `lighthouse.discord.roles.staff_quartermaster` | `DISCORD_ROLE_STAFF_QUARTERMASTER` | | Role ID for Quartermaster department |
| `lighthouse.discord.roles.staff_steward` | `DISCORD_ROLE_STAFF_STEWARD` | | Role ID for Steward department |
| `lighthouse.discord.roles.rank_jr_crew` | `DISCORD_ROLE_RANK_JR_CREW` | | Role ID for Jr Crew rank |
| `lighthouse.discord.roles.rank_crew_member` | `DISCORD_ROLE_RANK_CREW_MEMBER` | | Role ID for Crew Member rank |
| `lighthouse.discord.roles.rank_officer` | `DISCORD_ROLE_RANK_OFFICER` | | Role ID for Officer rank |
| `lighthouse.discord.roles.verified` | `DISCORD_ROLE_VERIFIED` | | Role ID for verified users |

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/Discord/LinkDiscordAccountTest.php` | 4 | Linking accounts, max limit, duplicate prevention, activity logging |
| `tests/Feature/Discord/UnlinkDiscordAccountTest.php` | 3 | Deletion, role removal via API, activity logging |
| `tests/Feature/Discord/RevokeDiscordAccountTest.php` | 3 | Admin deletion, role removal via API, activity logging |
| `tests/Feature/Discord/SyncDiscordRolesTest.php` | 4 | Correct role assignment, skips when no accounts, skips brigged, activity logging |
| `tests/Feature/Discord/SyncDiscordStaffTest.php` | 7 | Staff role sync, removal, skips when no accounts, skips brigged, activity logging, managed set completeness |
| `tests/Feature/Discord/DiscordAccountTest.php` | 12 | Model relationships, encryption, enum casting, scopes, avatar URL, display name, hasDiscordLinked |
| `tests/Feature/Discord/DiscordSettingsPageTest.php` | 6 | Page rendering, auth required, link button visibility, upgrade message, linked accounts display, unlinking |
| `tests/Feature/Discord/DiscordOAuthTest.php` | 5 | Auth required, rank gate, brig gate, stowaway access, eligible user access |
| `tests/Feature/Discord/DiscordBrigIntegrationTest.php` | 4 | Brig sets status, role removal on brig, restoration on release, permission sync on release |
| `tests/Feature/Blade/DiscordBannerModalTest.php` | 2 | Banner modal rendering, Discord invite button |
| `tests/Feature/Blade/JoinDiscordImageTest.php` | 2 | Component rendering, toast message |
| `tests/Feature/Auth/AcpTabPermissionsTest.php` | 4 (discord) | Discord API log gate (engineer, officer, denied), Discord account viewAny |
| `tests/Feature/Livewire/Dashboard/CommandCommunityEngagementTest.php` | 1 (discord) | New Discord Accounts metric counting |
| `tests/Feature/Actions/Actions/UpdateChildPermissionTest.php` | 2 (discord) | Parent disable sets ParentDisabled, enable restores Active |
| `tests/Feature/AvatarTest.php` | 3 (discord) | Discord fallback in auto mode, MC priority over Discord, explicit Discord preference |
| `tests/Feature/Services/TicketNotificationServiceCategoryTest.php` | 2 (discord) | Discord channel inclusion/exclusion based on linked account + preferences |
| `tests/Feature/Actions/Actions/ReleaseUserFromBrigTest.php` | 5 (discord) | Brig release restores Discord, syncs roles, syncs staff, skips non-staff, handles missing accounts |

### Test Case Inventory

**LinkDiscordAccountTest.php:**
- `it('links a discord account to a user')`
- `it('prevents linking when account limit is reached')`
- `it('prevents linking a discord id already linked to another user')`
- `it('records activity when linking')`

**UnlinkDiscordAccountTest.php:**
- `it('deletes the discord account')`
- `it('calls removeAllManagedRoles on discord api')`
- `it('records activity when unlinking')`

**RevokeDiscordAccountTest.php:**
- `it('deletes the discord account')`
- `it('calls removeAllManagedRoles on discord api')`
- `it('records activity when revoking')`

**SyncDiscordRolesTest.php:**
- `it('adds the correct membership role')`
- `it('skips sync when user has no discord accounts')`
- `it('skips brigged accounts')`
- `it('records activity when syncing')`

**SyncDiscordStaffTest.php:**
- `it('syncs staff department and rank roles')`
- `it('removes all staff roles when department is null')`
- `it('skips sync when user has no discord accounts')`
- `it('skips brigged accounts')`
- `it('records activity when syncing staff roles')`
- `it('records removal activity when department is null')`
- `it('includes all staff role ids in managed set')`

**DiscordAccountTest.php:**
- `it('belongs to a user')`
- `it('user has many discord accounts')`
- `it('encrypts access token')`
- `it('encrypts refresh token')`
- `it('casts status to enum')`
- `it('scopes active accounts')`
- `it('returns avatar url from cdn when hash exists')`
- `it('returns default avatar url when no hash')`
- `it('returns display name preferring global name')`
- `it('returns username when no global name')`
- `it('hasDiscordLinked returns true when user has active account')`
- `it('hasDiscordLinked returns false when user has no accounts')`
- `it('hasDiscordLinked returns false when user only has brigged accounts')`

**DiscordSettingsPageTest.php:**
- `it('can render the discord settings page')`
- `it('requires authentication')`
- `it('shows link button for eligible users')`
- `it('shows upgrade message for ineligible users')`
- `it('shows linked accounts')`
- `it('can unlink an account')`

**DiscordOAuthTest.php:**
- `it('requires authentication for discord redirect')`
- `it('requires stowaway rank to access discord redirect')`
- `it('blocks brigged users from discord redirect')`
- `it('allows stowaway rank to access discord redirect')`
- `it('allows eligible users to access discord redirect')`

**DiscordBrigIntegrationTest.php:**
- `it('sets discord accounts to brigged when user is put in brig')`
- `it('calls removeAllManagedRoles when brigging')`
- `it('restores discord accounts to active when released from brig')`
- `it('syncs discord permissions when released from brig')`

**DiscordBannerModalTest.php:**
- `it('renders the Discord banner modal component')`
- `it('shows Discord invite button')`

**JoinDiscordImageTest.php:**
- `it('renders the join discord image component')`
- `it('shows the toast message for copying')`

**AcpTabPermissionsTest.php (Discord-relevant):**
- `test('engineering jr crew can pass view-discord-api-log gate')`
- `test('any officer can pass view-discord-api-log gate')`
- `test('non-engineering non-officer is denied discord api log gate')`
- `test('any officer can viewAny discord accounts')`

**CommandCommunityEngagementTest.php (Discord-relevant):**
- `it('counts new discord accounts in the current iteration')`

**UpdateChildPermissionTest.php (Discord-relevant):**
- `it('disables discord and sets active accounts to ParentDisabled')`
- `it('enables discord and restores ParentDisabled accounts to Active')`

**AvatarTest.php (Discord-relevant):**
- `it('falls back to discord in auto mode when no MC avatar')`
- `it('prefers minecraft over discord in auto mode')`
- `it('returns discord avatar when preference is discord')`

**TicketNotificationServiceCategoryTest.php (Discord-relevant):**
- `it('includes discord channel when user has linked account and enabled preference')`
- `it('excludes discord channel when user has no linked account')`

**ReleaseUserFromBrigTest.php (Discord-relevant):**
- `it('restores brigged discord accounts to active')`
- `it('syncs discord roles on release')`
- `it('syncs discord staff roles for staff users on release')`
- `it('skips discord staff sync for non-staff users')`
- `it('still records activity and sends notification even when no minecraft or discord accounts exist')`

### Coverage Gaps

- **SyncDiscordPermissions action** has no dedicated test file
- **PostAnnouncementToDiscord action** has no dedicated test file -- the announcement posting to Discord channel is not specifically tested
- **DiscordApiService** has no unit tests for rate limit retry behavior (429 handling)
- **DiscordChannel notification channel** has no dedicated tests for DM delivery failures or multi-account DM delivery
- **Admin Discord Users page** -- no tests for search/sort/pagination functionality
- **Admin Discord API Log page** -- no tests for filtering/pagination
- **Parent Portal Discord toggle** -- the `UpdateChildPermission` action is tested in `tests/Feature/Actions/Actions/UpdateChildPermissionTest.php` but Discord-specific behavior (role stripping, status changes) may not be fully covered there
- **Guild join detection flow** (`checkGuildMembership`) -- no tests for the delayed membership check and role sync triggered via JavaScript timers
- **Profile page revoke** -- the admin revoke via profile page (`revokeDiscordAccount()` method on display-basic-details) has no dedicated test

---

## 17. File Map

**Models:**
- `app/Models/DiscordAccount.php`
- `app/Models/DiscordApiLog.php`
- `app/Models/User.php` (discord relationships, `hasDiscordLinked()`, `discordAvatarUrl()`, `parent_allows_discord`)

**Enums:**
- `app/Enums/DiscordAccountStatus.php`
- `app/Enums/MembershipLevel.php` (`discordRoleId()` method)
- `app/Enums/StaffDepartment.php` (`discordRoleId()` method)
- `app/Enums/StaffRank.php` (`discordRoleId()` method)

**Actions:**
- `app/Actions/LinkDiscordAccount.php`
- `app/Actions/UnlinkDiscordAccount.php`
- `app/Actions/RevokeDiscordAccount.php`
- `app/Actions/SyncDiscordRoles.php`
- `app/Actions/SyncDiscordStaff.php`
- `app/Actions/SyncDiscordPermissions.php`
- `app/Actions/PostAnnouncementToDiscord.php`
- `app/Actions/PromoteUser.php` (calls `SyncDiscordRoles`)
- `app/Actions/DemoteUser.php` (calls `SyncDiscordRoles`)
- `app/Actions/SetUsersStaffPosition.php` (calls `SyncDiscordStaff`)
- `app/Actions/RemoveUsersStaffPosition.php` (calls `SyncDiscordStaff`)
- `app/Actions/PutUserInBrig.php` (strips roles, sets Brigged)
- `app/Actions/ReleaseUserFromBrig.php` (restores accounts, syncs roles)
- `app/Actions/UpdateChildPermission.php` (toggles parent Discord access)

**Policies:**
- `app/Policies/DiscordAccountPolicy.php`

**Gates:** `app/Providers/AuthServiceProvider.php` -- gates: `link-discord`, `view-discord-api-log`

**Notifications:**
- `app/Notifications/Channels/DiscordChannel.php`
- 13 notification classes with `toDiscord()` method (see Section 9)

**Jobs:**
- `app/Jobs/SendAnnouncementNotifications.php`

**Services:**
- `app/Services/DiscordApiService.php`
- `app/Services/FakeDiscordApiService.php`

**Controllers:**
- `app/Http/Controllers/DiscordAuthController.php`

**Volt Components:**
- `resources/views/livewire/settings/discord-account.blade.php`
- `resources/views/livewire/admin-manage-discord-users-page.blade.php`
- `resources/views/livewire/admin-manage-discord-api-log-page.blade.php`
- `resources/views/livewire/admin-control-panel-tabs.blade.php`
- `resources/views/livewire/users/display-basic-details.blade.php` (Discord section)
- `resources/views/livewire/parent-portal/index.blade.php` (Discord toggle + accounts display)
- `resources/views/dashboard.blade.php` (Account Linking card)

**Blade Components:**
- `resources/views/components/discord-banner-modal.blade.php`
- `resources/views/components/join-discord-image.blade.php`
- `resources/views/components/settings/layout.blade.php` (Discord nav item)

**Additional Discord-Aware Views (avatar/eager-loading only):**
- `resources/views/livewire/settings/notifications.blade.php` (Discord DM toggles)
- `resources/views/livewire/settings/profile.blade.php` (Discord avatar preference)
- `resources/views/livewire/dashboard/command-community-engagement.blade.php` (New Discord Accounts metric)
- `resources/views/livewire/staff/page.blade.php` (avatar resolution)
- `resources/views/livewire/ready-room/tickets/tickets-list.blade.php` (avatar resolution)
- `resources/views/livewire/ready-room/tickets/view-ticket.blade.php` (avatar resolution)
- `resources/views/livewire/admin-manage-announcements-page.blade.php` (avatar resolution)
- `resources/views/livewire/auth/register.blade.php` (under-13 parent_allows_discord)
- `resources/views/livewire/auth/collect-birthdate.blade.php` (under-13 parent_allows_discord)

**Routes:**
- `settings.discord-account` -- `GET /settings/discord-account`
- `auth.discord.redirect` -- `GET /auth/discord/redirect`
- `auth.discord.callback` -- `GET /auth/discord/callback`

**Migrations:**
- `database/migrations/2026_02_24_220445_create_discord_accounts_table.php`
- `database/migrations/2026_03_06_200000_create_discord_api_logs_table.php`

**Console Commands:** None

**Tests:**
- `tests/Feature/Discord/LinkDiscordAccountTest.php`
- `tests/Feature/Discord/UnlinkDiscordAccountTest.php`
- `tests/Feature/Discord/RevokeDiscordAccountTest.php`
- `tests/Feature/Discord/SyncDiscordRolesTest.php`
- `tests/Feature/Discord/SyncDiscordStaffTest.php`
- `tests/Feature/Discord/DiscordAccountTest.php`
- `tests/Feature/Discord/DiscordSettingsPageTest.php`
- `tests/Feature/Discord/DiscordOAuthTest.php`
- `tests/Feature/Discord/DiscordBrigIntegrationTest.php`
- `tests/Feature/Blade/DiscordBannerModalTest.php`
- `tests/Feature/Blade/JoinDiscordImageTest.php`
- `tests/Feature/Auth/AcpTabPermissionsTest.php` (Discord-relevant tests)
- `tests/Feature/Livewire/Dashboard/CommandCommunityEngagementTest.php` (Discord metric test)
- `tests/Feature/Actions/Actions/UpdateChildPermissionTest.php` (Discord toggle tests)
- `tests/Feature/AvatarTest.php` (Discord avatar tests)
- `tests/Feature/Services/TicketNotificationServiceCategoryTest.php` (Discord channel tests)
- `tests/Feature/Actions/Actions/ReleaseUserFromBrigTest.php` (Discord brig release tests)

**Config:**
- `config/services.php` (discord section)
- `config/lighthouse.php` (`max_discord_accounts`, `discord.roles.*`)

---

## 18. Known Issues & Improvement Opportunities

1. **SyncDiscordPermissions appears unused.** The `SyncDiscordPermissions` action is a convenience wrapper calling `SyncDiscordRoles` + `SyncDiscordStaff`, but no code in the codebase actually calls it. All callers invoke the individual sync actions directly. Consider removing it or wiring it up as the single entry point.

2. **DiscordApiService logs duplicate data.** The `logApiCall()` method stores both `response` and `error_message` with the same value (`$response->body()`) on failure -- these columns are redundant.

3. **No token refresh mechanism.** OAuth access tokens have an expiry (`token_expires_at`) but there is no background job or middleware to refresh them before they expire. The tokens are stored but never used after initial linking -- the bot token is used for all API calls instead. The access/refresh tokens could be removed or a refresh flow added if user-level API access is needed.

4. **Hardcoded Discord invite code.** The `discord-banner-modal.blade.php` component has a hardcoded invite code `'4RNtFNApYt'` in JavaScript rather than using the configurable `services.discord.invite_url`. The `join-discord-image.blade.php` also hardcodes `'https://discord.gg/4RNtFNApYt'`.

5. **Parent portal references `discord_username` instead of `username`.** In `parent-portal/index.blade.php` line 584, the template accesses `$discord->discord_username` which does not exist on the model -- it should be `$discord->username` or `$discord->displayName()`.

6. **Missing test coverage for multiple areas.** See Coverage Gaps in Section 16. Key gaps: `PostAnnouncementToDiscord`, `SyncDiscordPermissions`, admin pages, guild join detection flow, API rate limiting, and profile page revoke action.

7. **No CSRF protection on Discord banner modal.** The `discord-banner-modal` and `join-discord-image` components use `onclick` handlers to open Discord links -- this is fine for UX but doesn't validate server-side. Not a security issue, just a pattern note.

8. **Role sync is not atomic.** If a role add succeeds but a subsequent role remove fails (e.g., due to rate limiting), the user will have incorrect roles until the next sync. Consider collecting all role changes and applying them in a single PATCH to the guild member endpoint.

9. **removeAllManagedRoles iterates config values directly.** The `removeAllManagedRoles()` method uses `config('lighthouse.discord.roles')` which returns a flat array including nested values. If the config structure changes (e.g., grouping), this could break silently. Additionally, it removes ALL configured roles rather than just the ones managed by the specific sync type.

10. **No notification preference for Discord DMs.** Users cannot opt out of Discord DM notifications independently -- the `allowedChannels` array on notifications controls this but there's no user-facing toggle to disable Discord DMs while keeping mail/Pushover.

11. **SQL injection risk in admin search.** The `admin-manage-discord-users-page.blade.php` uses `$this->search` directly in a LIKE query. While Livewire properties provide some protection, the pattern `"%{$this->search}%"` should use parameter binding for defense in depth. Same applies to the API log page.
