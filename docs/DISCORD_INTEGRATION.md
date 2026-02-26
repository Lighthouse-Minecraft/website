# Discord Server Integration — Feature Specification

This document is the authoritative specification for the Discord server integration feature.
Use it to validate that the implementation meets business requirements and to avoid regressions when making changes in this area.

---

## Overview

Community members can link their Discord account to their Lighthouse website profile via OAuth2.
Once linked, the system automatically manages their Discord server roles based on membership level,
staff department, and staff rank. Users can also receive notification DMs through Discord.

The integration handles:
- Account linking via Discord OAuth2 (one-click: authorize → callback → done)
- Membership role synchronization (membership level → Discord role)
- Staff role synchronization (staff department + staff rank → Discord roles)
- Brig enforcement (role stripping on brig placement; role restoration on release)
- Discord DM notifications as an additional notification channel
- Audit logging of every account state change

**Architecture note:** This integration uses Discord REST API v10 with Bot token authentication.
All API calls are on-demand HTTP requests triggered by user actions (promotion, brig, login, etc.).
No persistent bot process or always-on VPS is required.

---

## 1. Eligibility Requirements

A user may link a Discord account only if ALL of the following are true:

- **Membership level is at least Traveler** — Drifter and Stowaway users cannot link accounts
- **Not in the brig** — brigged users are blocked from initiating linking
- **Below the account limit** — default maximum is 1 linked account per user (configurable)

These rules are enforced by the `link-discord` gate and account limit check in `LinkDiscordAccount`.

---

## 2. Account Linking (OAuth2 Flow)

Triggered when the user clicks "Link Discord Account" on `/settings/discord-account`.

**Flow:**

1. User clicks link button → redirected to Discord OAuth2 authorization page
2. User authorizes the application on Discord (scopes: `identify`, `guilds.join`)
3. Discord redirects back to `/auth/discord/callback` with an authorization code
4. Server exchanges the code for tokens via Socialite
5. `LinkDiscordAccount::run()` is called with the user's Discord data

**Validation steps (in `LinkDiscordAccount`):**

1. Account limit check: user has fewer than `max_discord_accounts` linked accounts
2. Duplicate check: the Discord user ID is not already linked to any user

**On success:**

- A `DiscordAccount` record is created with `status = active` and `verified_at` set
- OAuth tokens (`access_token`, `refresh_token`) are stored with Laravel's `encrypted` cast
- `SyncDiscordPermissions::run($user)` is called to assign all appropriate roles
- Activity log recorded: `discord_account_linked`
- User is redirected back to settings page with a success message

**On failure:**

- If account limit reached: returns error message "Maximum Discord accounts reached."
- If Discord ID already linked: returns error message "This Discord account is already linked to another user."
- If OAuth fails (user cancels, network error): redirects with generic error message

---

## 3. Membership Level → Discord Role Mapping

| Membership Level | Config Key | Discord Role |
|---|---|---|
| Drifter (0) | — | — (cannot link accounts) |
| Stowaway (1) | — | — (cannot link accounts) |
| Traveler (2) | `lighthouse.discord.roles.traveler` | Traveler role |
| Resident (3) | `lighthouse.discord.roles.resident` | Resident role |
| Citizen (4) | `lighthouse.discord.roles.citizen` | Citizen role |

Additionally, all linked users receive the **Verified** role (`lighthouse.discord.roles.verified`).

**Role sync logic (`SyncDiscordRoles`):**

1. Remove ALL membership-level roles from the user (iterates all `MembershipLevel` cases)
2. Add the role matching the user's current membership level
3. Add the Verified role (if configured)

**Role sync triggers:**

- User promoted → `SyncDiscordPermissions::run($user)` syncs roles and staff across all active accounts
- User demoted → `SyncDiscordPermissions::run($user)` syncs roles and staff across all active accounts
- Account linked → roles assigned immediately after record creation

The `SyncDiscordPermissions` action handles both membership roles and staff roles together;
`SyncDiscordRoles` handles membership roles only.

---

## 4. Staff Department → Discord Role Mapping

| Staff Department | Config Key |
|---|---|
| Command | `lighthouse.discord.roles.staff_command` |
| Chaplain | `lighthouse.discord.roles.staff_chaplain` |
| Engineer | `lighthouse.discord.roles.staff_engineer` |
| Quartermaster | `lighthouse.discord.roles.staff_quartermaster` |
| Steward | `lighthouse.discord.roles.staff_steward` |
| None (position removed) | — (all department roles removed) |

---

## 5. Staff Rank → Discord Role Mapping

| Staff Rank | Config Key |
|---|---|
| Jr. Crew | `lighthouse.discord.roles.rank_jr_crew` |
| Crew Member | `lighthouse.discord.roles.rank_crew_member` |
| Officer | `lighthouse.discord.roles.rank_officer` |
| None | — (all rank roles removed) |

**Staff sync logic (`SyncDiscordStaff`):**

1. Remove ALL staff department roles (iterates all `StaffDepartment` cases)
2. Remove ALL staff rank roles (iterates all `StaffRank` cases)
3. If user has a department → add the matching department role
4. If user has a staff rank (not None) → add the matching rank role
5. Log activity: `discord_staff_synced` or `discord_staff_removed`

**Staff sync triggers:**

- Staff position assigned → `SyncDiscordStaff::run($user, $department)` across all active accounts
- Staff position removed → `SyncDiscordStaff::run($user)` sends role removals for all active accounts
- User promoted/demoted → handled by `SyncDiscordPermissions` (calls both roles and staff)

---

## 6. Account Unlinking (User-Initiated)

Triggered from `/settings/discord-account` when the user clicks "Unlink" and confirms.

**Sequence:**

1. `removeAllManagedRoles($discordUserId)` — strips all configured roles from the Discord user
2. Delete the `DiscordAccount` record from the database
3. Record activity log: `discord_account_unlinked`

---

## 7. Account Revocation (Admin-Initiated)

Triggered from a user's profile card when an admin clicks "Revoke" on a Discord account.

**Authorization:** Admin role only (via `DiscordAccountPolicy::delete()`).

**Sequence:**

1. `removeAllManagedRoles($discordUserId)` — strips all configured roles from the Discord user
2. Delete the `DiscordAccount` record from the database
3. Record activity log on the **affected user** (not the admin): `discord_account_revoked`
4. Activity description includes the admin's name for auditability

---

## 8. Brig Integration

### When a user is placed in the brig

For each Discord account with `active` status:

1. Call `removeAllManagedRoles($discordUserId)` — strip all managed Discord roles
2. Set `DiscordAccount.status` → `brigged`

All operations are wrapped in a try/catch per account. If the Discord API call fails, the
error is logged but brig placement still completes. The account status is still set to `brigged`
in the database so subsequent syncs will skip it.

### When a user is released from the brig

For each Discord account with `brigged` status:

1. Set `DiscordAccount.status` → `active`

After restoring all accounts, call `SyncDiscordPermissions::run($target)` to re-apply the
user's current membership role, staff department, and staff rank across all restored accounts.

---

## 9. Discord API Communication

All Discord API calls use the REST API v10 (`https://discord.com/api/v10`) with Bot token authentication.

### API Methods (`DiscordApiService`)

| Method | Discord Endpoint | Purpose |
|---|---|---|
| `getGuildMember($discordUserId)` | `GET /guilds/{id}/members/{id}` | Check if user is in the guild |
| `addRole($discordUserId, $roleId)` | `PUT /guilds/{id}/members/{id}/roles/{id}` | Add a role to a guild member |
| `removeRole($discordUserId, $roleId)` | `DELETE /guilds/{id}/members/{id}/roles/{id}` | Remove a role from a guild member |
| `sendDirectMessage($discordUserId, $content)` | `POST /users/@me/channels` + `POST /channels/{id}/messages` | Send a DM (creates DM channel first) |
| `removeAllManagedRoles($discordUserId)` | Multiple `DELETE` calls | Remove all configured roles (membership + department + rank + verified) |

**Error handling:** All API methods log warnings on failure but do not throw exceptions.
Empty or null role IDs are silently skipped (`addRole`/`removeRole` return `false` for empty IDs).
This ensures Discord API outages do not break core site operations.

---

## 10. Discord DM Notification Channel

Discord DMs are available as a notification delivery channel alongside email and Pushover.

### How it works

1. User enables "Discord DM" toggle in notification preferences (`/settings/notifications`)
2. When a notification fires, `TicketNotificationService::determineChannels()` checks:
   - User has `notify_tickets_discord` preference enabled
   - User has at least one active Discord account (`$user->hasDiscordLinked()`)
3. If both conditions are met, `DiscordChannel` is included in the `via()` array
4. `DiscordChannel::send()` calls `toDiscord()` on the notification to get the message text
5. The message is sent as a DM to each of the user's active Discord accounts

### Supported notifications

All user-facing notifications support Discord DMs via their `toDiscord()` method:

- `NewTicketNotification`
- `NewTicketReplyNotification`
- `TicketAssignedNotification`
- `MessageFlaggedNotification`
- `BrigTimerExpiredNotification`
- `UserPutInBrigNotification`
- `UserReleasedFromBrigNotification`
- `UserPromotedToTravelerNotification`
- `UserPromotedToResidentNotification`
- `UserPromotedToStowawayNotification`

**Not supported** (not user-facing DMs): `TicketDigestNotification`, `MinecraftCommandNotification`.

### Failure handling

- If the user has DMs disabled on Discord, `sendDirectMessage` returns `false`; no retry, no error to user
- If `toDiscord()` returns `null` or is not defined, the channel silently skips
- Individual account failures are caught per-account; one failed DM does not block others

---

## 11. Audit Logging

Every account state change is written to the activity log via `RecordActivity`:

| Action Key | When Recorded |
|---|---|
| `discord_account_linked` | Account successfully linked via OAuth |
| `discord_account_unlinked` | User unlinks their own account |
| `discord_account_revoked` | Admin revokes a user's account |
| `discord_roles_synced` | Membership roles synced to Discord |
| `discord_staff_synced` | Staff department + rank roles synced |
| `discord_staff_removed` | Staff roles removed from Discord |

---

## 12. Authorization Rules

| Action | Who Can Perform It |
|---|---|
| Link a Discord account | Authenticated user; membership level >= Traveler; not in brig (`link-discord` gate) |
| View own linked accounts | Account owner (`DiscordAccountPolicy::view()`) |
| Unlink own account | Account owner (`DiscordAccountPolicy::delete()`) |
| View all accounts (admin panel) | Admin only (`DiscordAccountPolicy::viewAny()`) |
| Revoke any account | Admin only (`DiscordAccountPolicy::delete()`) |

Authorization for linking uses the `link-discord` gate defined in `AuthServiceProvider`.
Account-level operations use `DiscordAccountPolicy`. Authorization checks must never be
duplicated in Blade views — use `@can` / policies only.

---

## 13. Configuration Reference

### Application settings (`config/lighthouse.php`)

| Key | Default | Description |
|---|---|---|
| `lighthouse.max_discord_accounts` | `1` | Maximum linked Discord accounts per user |
| `lighthouse.discord.roles.traveler` | — | Discord role ID for Traveler members |
| `lighthouse.discord.roles.resident` | — | Discord role ID for Resident members |
| `lighthouse.discord.roles.citizen` | — | Discord role ID for Citizen members |
| `lighthouse.discord.roles.staff_command` | — | Discord role ID for Command department |
| `lighthouse.discord.roles.staff_chaplain` | — | Discord role ID for Chaplain department |
| `lighthouse.discord.roles.staff_engineer` | — | Discord role ID for Engineer department |
| `lighthouse.discord.roles.staff_quartermaster` | — | Discord role ID for Quartermaster department |
| `lighthouse.discord.roles.staff_steward` | — | Discord role ID for Steward department |
| `lighthouse.discord.roles.rank_jr_crew` | — | Discord role ID for Jr. Crew rank |
| `lighthouse.discord.roles.rank_crew_member` | — | Discord role ID for Crew Member rank |
| `lighthouse.discord.roles.rank_officer` | — | Discord role ID for Officer rank |
| `lighthouse.discord.roles.verified` | — | Discord role ID for all verified/linked members |

### Service credentials (`config/services.php`)

| Key | Description |
|---|---|
| `services.discord.client_id` | Discord OAuth2 application client ID |
| `services.discord.client_secret` | Discord OAuth2 application client secret |
| `services.discord.redirect` | OAuth2 callback URL (default: `/auth/discord/callback`) |
| `services.discord.bot_token` | Discord bot token for REST API calls |
| `services.discord.guild_id` | Discord server (guild) ID to manage roles on |

### Environment variables (`.env`)

```env
DISCORD_CLIENT_ID=
DISCORD_CLIENT_SECRET=
DISCORD_BOT_TOKEN=
DISCORD_GUILD_ID=
DISCORD_REDIRECT_URI="${APP_URL}/auth/discord/callback"
MAX_DISCORD_ACCOUNTS=1

DISCORD_ROLE_TRAVELER=
DISCORD_ROLE_RESIDENT=
DISCORD_ROLE_CITIZEN=
DISCORD_ROLE_STAFF_COMMAND=
DISCORD_ROLE_STAFF_CHAPLAIN=
DISCORD_ROLE_STAFF_ENGINEER=
DISCORD_ROLE_STAFF_QUARTERMASTER=
DISCORD_ROLE_STAFF_STEWARD=
DISCORD_ROLE_RANK_JR_CREW=
DISCORD_ROLE_RANK_CREW_MEMBER=
DISCORD_ROLE_RANK_OFFICER=
DISCORD_ROLE_VERIFIED=
```

---

## 14. Local Development

In the local environment, `AppServiceProvider` binds `FakeDiscordApiService` in place of
`DiscordApiService`. The fake service:

- Logs all method calls to `storage/logs/laravel.log` with the `[FakeDiscord]` prefix
- Tracks all calls in an in-memory `$calls` array (useful for assertions in tests)
- Always returns success for `addRole`, `removeRole`, `sendDirectMessage`
- Never makes real HTTP requests to Discord

The only part of the flow that contacts Discord's servers is the OAuth2 redirect/callback,
which requires real `DISCORD_CLIENT_ID` and `DISCORD_CLIENT_SECRET` values. All role
management and DM sending is fully mocked in local and test environments.

---

## 15. User-Facing Pages

| URL | Component | Purpose |
|---|---|---|
| `/settings/discord-account` | `settings.discord-account` | User: link, view, unlink Discord accounts |
| `/settings/notifications` | `settings.notifications` | User: toggle Discord DM preference |
| `/dashboard` | `dashboard` | "Link Your Discord" card (shown when no accounts linked and eligible) |
| `/acp` → Discord Users | `admin-manage-discord-users-page` | Admin: browse all linked Discord accounts |
| User profile card | `users/display-basic-details` | View Discord accounts per user; admin can revoke |

---

## 16. Database Schema

### `discord_accounts` table

| Column | Type | Notes |
|---|---|---|
| `id` | bigIncrements | Primary key |
| `user_id` | foreignId | Indexed (NOT unique); constrained to users, cascadeOnDelete |
| `discord_user_id` | string | Unique (Discord snowflake ID) |
| `username` | string | Discord username |
| `global_name` | string, nullable | Discord display name |
| `avatar_hash` | string, nullable | For constructing CDN avatar URL |
| `access_token` | text | Stored with Laravel `encrypted` cast |
| `refresh_token` | text, nullable | Stored with Laravel `encrypted` cast |
| `token_expires_at` | timestamp, nullable | OAuth token expiry |
| `status` | string, default `active` | Enum: `active`, `brigged` |
| `verified_at` | timestamp | Set when OAuth2 linking completes |
| `created_at` | timestamp | Standard Laravel timestamp |
| `updated_at` | timestamp | Standard Laravel timestamp |

### Account status enum (`DiscordAccountStatus`)

| Value | Label | Color | Meaning |
|---|---|---|---|
| `active` | Active | green | Account is linked and functional |
| `brigged` | In the Brig | red | User is in brig; roles stripped, syncs skipped |

### Model relationships

- `User` → `discordAccounts()`: HasMany (not HasOne, to support configurable account limits)
- `DiscordAccount` → `user()`: BelongsTo
- `User::hasDiscordLinked()`: Returns `true` if user has at least one active Discord account

### Model helpers

- `DiscordAccount::avatarUrl()`: Constructs Discord CDN avatar URL, falls back to default avatar
- `DiscordAccount::displayName()`: Returns `global_name` if set, otherwise `username`
- `DiscordAccount::scopeActive()`: Query scope filtering to `status = active`

---

## 17. Edge Cases

1. **User not in Discord guild** — API role calls fail with 404; logged as warning, link still succeeds. Roles sync next time user is in guild and a sync triggers.
2. **Discord API down during brig** — Try/catch logs error, brig placement still completes, account marked Brigged. Roles may be temporarily out of sync.
3. **Discord user has DMs disabled** — `sendDirectMessage` returns `false`; no retry, no error shown to user.
4. **User unlinks then re-links** — Supported. Unlink deletes the record, new link creates a fresh record.
5. **Brigged Discord account** — All sync operations (`SyncDiscordRoles`, `SyncDiscordStaff`) check `status === active` before proceeding. Brigged accounts are skipped.
6. **Bot lacks guild permissions** — API calls fail gracefully with logging. No exception propagation.
7. **Account limit increase** — Changing `MAX_DISCORD_ACCOUNTS` immediately allows more accounts. Existing accounts are unaffected.
8. **Staff rank change without department change** — `SetUsersStaffPosition` calls `SyncDiscordStaff` which re-syncs both department and rank roles.
9. **Null/empty role IDs** — `addRole()` and `removeRole()` return `false` immediately for empty IDs. Unconfigured roles are safely skipped.
10. **Multiple active accounts** — All sync operations iterate over all active accounts. DMs are sent to every active account.

---

## 18. Key Implementation Files

| Purpose | File |
|---|---|
| OAuth2 controller | `app/Http/Controllers/DiscordAuthController.php` |
| Link account | `app/Actions/LinkDiscordAccount.php` |
| Unlink account | `app/Actions/UnlinkDiscordAccount.php` |
| Admin revoke | `app/Actions/RevokeDiscordAccount.php` |
| Membership role sync | `app/Actions/SyncDiscordRoles.php` |
| Staff role sync | `app/Actions/SyncDiscordStaff.php` |
| Combined sync (roles + staff) | `app/Actions/SyncDiscordPermissions.php` |
| Brig placement (Discord portion) | `app/Actions/PutUserInBrig.php` |
| Brig release (Discord portion) | `app/Actions/ReleaseUserFromBrig.php` |
| Discord REST API client | `app/Services/DiscordApiService.php` |
| Local/test mock API client | `app/Services/FakeDiscordApiService.php` |
| DM notification channel | `app/Notifications/Channels/DiscordChannel.php` |
| Account model | `app/Models/DiscordAccount.php` |
| Account status enum | `app/Enums/DiscordAccountStatus.php` |
| Membership role mapping | `app/Enums/MembershipLevel.php` (`discordRoleId()`) |
| Department role mapping | `app/Enums/StaffDepartment.php` (`discordRoleId()`) |
| Staff rank role mapping | `app/Enums/StaffRank.php` (`discordRoleId()`) |
| Authorization policy | `app/Policies/DiscordAccountPolicy.php` |
| Authorization gate | `app/Providers/AuthServiceProvider.php` (`link-discord`) |
| Service binding (local fake) | `app/Providers/AppServiceProvider.php` |
| Settings page | `resources/views/livewire/settings/discord-account.blade.php` |
| Admin page | `resources/views/livewire/admin-manage-discord-users-page.blade.php` |
| Routes | `routes/web.php` |
| Config (roles) | `config/lighthouse.php` |
| Config (credentials) | `config/services.php` |
