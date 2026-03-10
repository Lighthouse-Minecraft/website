# Settings Pages -- Technical Documentation

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

The Settings Pages feature provides authenticated users with a centralized area to manage their personal profile, security, appearance preferences, notification delivery, linked Minecraft accounts, linked Discord accounts, and (for staff) their public bio. The settings area is accessed via `/settings` (which redirects to `/settings/profile`) and uses a shared layout with a left-side navigation list linking to all sub-pages.

All settings pages require authentication. Some sub-pages have additional authorization: the **Staff Bio** page requires the `edit-staff-bio` gate (CrewMember+ or board member), the **Minecraft Accounts** page gates the link form behind `link-minecraft-account` (Traveler+, not brigged, parent allows Minecraft), and the **Discord Account** page gates the link button behind `link-discord` (Stowaway+, not brigged, parent allows Discord).

The settings area comprises 7 routable Volt components plus 1 embedded sub-component (delete-user-form). The pages cover profile information (name, email, timezone, avatar source), password changes, appearance (light/dark/system theme), notification preferences (documented separately in the Notification System feature doc), Minecraft account verification and management, Discord account linking via OAuth, and staff bio editing (documented separately in the Staff Page feature doc).

Key integrations include: Minecraft verification code generation and polling, RCON whitelist management, Discord OAuth flow, Discord role syncing, and Stripe customer portal (avatar source selection for Gravatar).

---

## 2. Database Schema

The Settings Pages feature primarily reads and writes to the `users` table. The relevant columns are documented across multiple migration files. Minecraft and Discord account tables are also involved but are documented in their respective feature docs.

### `users` table (settings-related columns)

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `name` | string | No | — | Display name |
| `email` | string | No | — | Unique email address |
| `password` | string | No | — | Hashed password |
| `email_verified_at` | timestamp | Yes | null | Reset to null when email changes |
| `timezone` | string | Yes | null | IANA timezone identifier |
| `avatar_preference` | string | Yes | null | 'auto', 'minecraft', 'discord', or 'gravatar' |
| `pushover_key` | string | Yes | null | See Notification System docs |
| `email_digest_frequency` | string | No | 'immediate' | See Notification System docs |
| `notification_preferences` | json | Yes | null | See Notification System docs |
| `staff_first_name` | string | Yes | null | See Staff Page docs |
| `staff_last_initial` | string | Yes | null | See Staff Page docs |
| `staff_bio` | text | Yes | null | See Staff Page docs |
| `staff_phone` | string | Yes | null | See Staff Page docs |
| `staff_photo_path` | string | Yes | null | See Staff Page docs |

---

## 3. Models & Relationships

### User (`app/Models/User.php`) — Settings-Related

The User model's full documentation is spread across multiple feature docs. Settings-related fillable fields include: `name`, `email`, `password`, `timezone`, `avatar_preference`, `pushover_key`, `email_digest_frequency`, `notification_preferences`, `staff_first_name`, `staff_last_initial`, `staff_bio`, `staff_phone`, `staff_photo_path`.

**Relationships used by settings pages:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `minecraftAccounts()` | hasMany | MinecraftAccount | Used by minecraft-accounts page |
| `discordAccounts()` | hasMany | DiscordAccount | Used by discord-account page |

---

## 4. Enums Reference

### MinecraftAccountStatus (`app/Enums/MinecraftAccountStatus.php`)

Used in the Minecraft settings page for status badges and conditional UI.

| Case | Value | Notes |
|------|-------|-------|
| `Active` | — | Account is verified and whitelisted |
| `Verifying` | — | Verification code generated, awaiting in-game command |
| `Banned` | — | Account is banned |
| `Removed` | — | Unlinked by user, shown in archived section |
| `Cancelled` | — | Verification cancelled before completion |

### MinecraftAccountType (`app/Enums/MinecraftAccountType.php`)

| Case | Value | Notes |
|------|-------|-------|
| `Java` | `'java'` | Java Edition account |
| `Bedrock` | `'bedrock'` | Bedrock Edition account |

### DiscordAccountStatus (`app/Enums/DiscordAccountStatus.php`)

Used in the Discord settings page for status badges.

| Case | Value | Notes |
|------|-------|-------|
| `Active` | — | Account is linked and active |

### EmailDigestFrequency (`app/Enums/EmailDigestFrequency.php`)

See Notification System documentation.

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

| Gate Name | Who Can Pass | Logic Summary |
|-----------|-------------|---------------|
| `edit-staff-bio` | CrewMember+ OR board member | `$user->isAtLeastRank(StaffRank::CrewMember) \|\| $user->is_board_member` |
| `link-discord` | Stowaway+ AND not brigged AND parent allows Discord | `$user->isAtLeastLevel(MembershipLevel::Stowaway) && !$user->in_brig && $user->parent_allows_discord` |
| `link-minecraft-account` | Traveler+ AND not brigged AND parent allows Minecraft | `$user->isAtLeastLevel(MembershipLevel::Traveler) && !$user->in_brig && $user->parent_allows_minecraft` |

### Policies

#### MinecraftAccountPolicy

Used by the Minecraft settings page for `delete`, `reactivate`, and `setPrimary` authorization on individual accounts.

#### DiscordAccountPolicy

Used by the Discord settings page for `delete` authorization when unlinking.

### Permissions Matrix

| User Type | Profile | Password | Appearance | Notifications | Minecraft (view) | Minecraft (link) | Discord (view) | Discord (link) | Staff Bio |
|-----------|---------|----------|------------|---------------|-------------------|-------------------|----------------|----------------|-----------|
| Unauthenticated | No | No | No | No | No | No | No | No | No |
| Regular User | Yes | Yes | Yes | Yes | Yes | No | Yes | No | No |
| Stowaway+ | Yes | Yes | Yes | Yes | Yes | Yes* | Yes | Yes* | No |
| CrewMember+ | Yes | Yes | Yes | Yes | Yes | Yes* | Yes | Yes* | Yes |
| Board Member | Yes | Yes | Yes | Yes | Yes | Yes* | Yes | Yes* | Yes |
| Admin | Yes | Yes | Yes | Yes | Yes | Yes* | Yes | Yes* | Yes |

*Requires not being in brig AND parent allowing the respective platform.

---

## 6. Routes

| Method | URL | Middleware | Handler | Route Name |
|--------|-----|-----------|---------|------------|
| GET | `/settings` | auth | Redirect to `/settings/profile` | — |
| GET | `/settings/profile` | auth | `Volt::route('settings.profile')` | `settings.profile` |
| GET | `/settings/password` | auth | `Volt::route('settings.password')` | `settings.password` |
| GET | `/settings/appearance` | auth | `Volt::route('settings.appearance')` | `settings.appearance` |
| GET | `/settings/notifications` | auth | `Volt::route('settings.notifications')` | `settings.notifications` |
| GET | `/settings/minecraft-accounts` | auth | `Volt::route('settings.minecraft-accounts')` | `settings.minecraft-accounts` |
| GET | `/settings/discord-account` | auth | `Volt::route('settings.discord-account')` | `settings.discord-account` |
| GET | `/settings/staff-bio` | auth | `Volt::route('settings.staff-bio')` | `settings.staff-bio` |

---

## 7. User Interface Components

### Settings Layout
**File:** `resources/views/components/settings/layout.blade.php`

**Purpose:** Shared layout wrapper for all settings pages. Provides a left-side navigation list and content area.

**Navigation Items:**
- Profile, Password, Appearance, Notifications, Minecraft, Discord
- Staff Bio (conditional: `@can('edit-staff-bio')`)

### Settings Heading
**File:** `resources/views/partials/settings-heading.blade.php`

**Purpose:** Shared heading partial showing "Settings" title and "Manage your profile and account settings" subtitle with separator.

---

### Profile
**File:** `resources/views/livewire/settings/profile.blade.php`
**Route:** `/settings/profile` (route name: `settings.profile`)

**Purpose:** Edit user's name, email, timezone, and avatar source.

**PHP Properties:**
- `$name`, `$email`, `$timezone`, `$avatar_preference` (strings)
- `$timezones` (array — populated from `DateTimeZone::listIdentifiers()`)

**Key Methods:**
- `mount()` — loads current user values
- `updateProfileInformation()` — validates and saves; resets `email_verified_at` to null if email changed; dispatches `profile-updated` event
- `resendVerificationNotification()` — sends email verification if not verified

**Validation Rules:**
- `name` — required, string, max 255
- `email` — required, string, lowercase, email, max 255, unique (excluding self)
- `timezone` — required, timezone:all
- `avatar_preference` — required, in:auto,minecraft,discord,gravatar

**UI Elements:**
- Name input, Email input (with verification status/resend button), Timezone searchable select, Avatar Source select (Auto/Minecraft/Discord/Gravatar), Save button
- Embedded `<livewire:settings.delete-user-form />` at bottom

---

### Delete User Form
**File:** `resources/views/livewire/settings/delete-user-form.blade.php`
**Route:** Embedded in Profile page (no direct route)

**Purpose:** Allows users to permanently delete their account.

**Key Methods:**
- `deleteUser(Logout $logout)` — validates password, deletes user, logs out, redirects to `/`

**Validation Rules:**
- `password` — required, string, current_password

**UI Elements:**
- "Delete Account" danger button → opens confirmation modal
- Modal: password input, Cancel button, "Delete Account" danger button

---

### Password
**File:** `resources/views/livewire/settings/password.blade.php`
**Route:** `/settings/password` (route name: `settings.password`)

**Purpose:** Update the user's password.

**Key Methods:**
- `updatePassword()` — validates current password and new password, updates with Hash::make, dispatches `password-updated` event

**Validation Rules:**
- `current_password` — required, string, current_password
- `password` — required, string, Password::defaults(), confirmed

**UI Elements:**
- Current password, New password, Confirm password inputs; Save button

---

### Appearance
**File:** `resources/views/livewire/settings/appearance.blade.php`
**Route:** `/settings/appearance` (route name: `settings.appearance`)

**Purpose:** Toggle light/dark/system theme.

**PHP Class:** Empty component — theme is handled entirely client-side via `$flux.appearance` Alpine.js model.

**UI Elements:**
- Segmented radio group: Light (sun icon), Dark (moon icon), System (computer-desktop icon)

---

### Notifications
**File:** `resources/views/livewire/settings/notifications.blade.php`
**Route:** `/settings/notifications` (route name: `settings.notifications`)

See **Notification System** feature documentation for full details.

---

### Minecraft Accounts
**File:** `resources/views/livewire/settings/minecraft-accounts.blade.php`
**Route:** `/settings/minecraft-accounts` (route name: `settings.minecraft-accounts`)

**Purpose:** Link, view, manage, and unlink Minecraft accounts. Handles the verification code workflow for new account linking.

**PHP Properties:**
- `$accountType` (string, default `'java'`), `$username` (string)
- `$verificationCode` (string|null), `$expiresAt` (Carbon|null), `$errorMessage` (string|null)
- `$selectedAccount` (MinecraftAccount|null)
- `$accountToUnlink`, `$accountToReactivate`, `$accountToRemoveVerifying` (int|null)

**Key Methods:**
- `mount()` — checks for active pending verification
- `generateCode()` — authorizes `link-minecraft-account`, validates username (3-16 chars) and accountType, calls `GenerateVerificationCode::run()`
- `checkVerification()` — polls for verification completion/expiration (called via `wire:poll.15s`)
- `cancelVerification()` — expires pending verification, removes whitelist entry via RCON
- `simulateVerification()` — local-only testing method, calls `CompleteVerification::run()`
- `showAccount(int $accountId)` — loads account detail modal
- `confirmRemove(int $accountId)` / `unlinkAccount()` — authorizes `delete` policy, calls `UnlinkMinecraftAccount::run()`
- `confirmReactivate(int $accountId)` / `reactivateAccount()` — authorizes `reactivate` policy, calls `ReactivateMinecraftAccount::run()`
- `setPrimary(int $accountId)` — authorizes `setPrimary` policy, calls `SetPrimaryMinecraftAccount::run()`
- `confirmRemoveVerifying(int $accountId)` / `removeVerifyingAccount()` — authorizes `delete` policy, expires verification, removes from whitelist
- `with()` — computed data: `$linkedAccounts`, `$archivedAccounts`, `$maxAccounts`, `$remainingSlots`, `$gracePeriodMinutes`

**UI Elements:**
- Linked accounts list with status badges, Set Primary / Remove buttons
- Active verification code display with instructions (server name, port, `/verify` command), Cancel button
- Simulate Verification button (local env only)
- Link New Account form: account type radio (Java/Bedrock), username input with avatar preview (Java), Generate Verification Code button
- Archived accounts with Reactivate button
- Account limit message / max reached warning
- Parent restriction callout (if `parent_allows_minecraft` is false)
- 4 confirmation modals: remove, reactivate, remove-verifying, cancel-verification
- Account detail modal component (`x-minecraft.mc-account-detail-modal`)
- Polls every 15 seconds for verification status

---

### Discord Account
**File:** `resources/views/livewire/settings/discord-account.blade.php`
**Route:** `/settings/discord-account` (route name: `settings.discord-account`)

**Purpose:** Link, view, sync, and unlink Discord accounts.

**PHP Properties:**
- `$accountToUnlink` (int|null)
- `$awaitingGuildJoin` (bool)

**Key Methods:**
- `confirmUnlink(int $accountId)` / `unlinkAccount()` — authorizes `delete` policy, calls `UnlinkDiscordAccount::run()`
- `joinServerClicked()` — sets `$awaitingGuildJoin = true` if user has active accounts
- `checkGuildMembership()` — checks if user has joined the Discord server via `DiscordApiService::getGuildMember()`, syncs roles via `SyncDiscordRoles::run()` and `SyncDiscordStaff::run()`
- `syncRoles()` — authorizes `manage-stowaway-users`, manually syncs Discord roles (staff only)
- `with()` — computed data: `$linkedAccounts`, `$maxAccounts`, `$remainingSlots`, `$inviteUrl`

**UI Elements:**
- Session success/error callouts
- Linked accounts list with avatar, display name, username, status badge, verified date
- Sync Roles button (staff only via `@can('manage-stowaway-users')`)
- Unlink button per account
- Account limit message
- Discord server invite card with "Join Server" button (triggers guild join check after 10s and 30s)
- Link Discord Account button (gated by `@can('link-discord')`)
- Ineligible user messages: parent restriction or "promoted to Stowaway" requirement
- Unlink confirmation modal

---

### Staff Bio
**File:** `resources/views/livewire/settings/staff-bio.blade.php`
**Route:** `/settings/staff-bio` (route name: `settings.staff-bio`)

See **Staff Page** feature documentation for full details.

---

## 8. Actions (Business Logic)

The Settings Pages invoke several actions from other features:

| Action | Used By | Purpose |
|--------|---------|---------|
| `GenerateVerificationCode` | Minecraft settings | Generate MC verification code |
| `CompleteVerification` | Minecraft settings (local simulate) | Complete MC verification |
| `ExpireVerification` | Minecraft settings (checkVerification) | Expire timed-out verification |
| `UnlinkMinecraftAccount` | Minecraft settings | Remove MC account link |
| `ReactivateMinecraftAccount` | Minecraft settings | Re-link archived MC account |
| `SetPrimaryMinecraftAccount` | Minecraft settings | Set primary MC account |
| `UnlinkDiscordAccount` | Discord settings | Remove Discord account link |
| `SyncDiscordRoles` | Discord settings | Sync Discord server roles |
| `SyncDiscordStaff` | Discord settings | Sync Discord staff roles |

These actions are documented in their respective feature documentation (Minecraft Account Verification, Discord Integration).

---

## 9. Notifications

Not applicable for this feature directly. The Notification settings page configures delivery preferences documented in the **Notification System** feature doc.

---

## 10. Background Jobs

Not applicable for this feature.

---

## 11. Console Commands & Scheduled Tasks

Not applicable for this feature directly. Minecraft verification cleanup (`minecraft:cleanup-expired`) and username refresh (`minecraft:refresh-usernames`) are scheduled but belong to the Minecraft feature domain.

---

## 12. Services

### MinecraftRconService (`app/Services/MinecraftRconService.php`)

Used by the Minecraft settings page to remove accounts from the whitelist when cancelling verification or removing verifying accounts.

### DiscordApiService (`app/Services/DiscordApiService.php`)

Used by the Discord settings page to check guild membership (`getGuildMember()`) and send DMs.

---

## 13. Activity Log Entries

Not applicable for this feature directly. Settings changes (profile, password, appearance) do not create activity log entries. Minecraft and Discord account actions that do log activity are documented in their respective feature docs.

---

## 14. Data Flow Diagrams

### Updating Profile Information

```
User navigates to /settings/profile
  -> GET /settings/profile (middleware: auth)
    -> Volt component mounts, loads user data
    -> Displays name, email, timezone, avatar preference form

User modifies fields and clicks "Save"
  -> updateProfileInformation() fires
    -> Validates: name, email (unique), timezone, avatar_preference
    -> $user->fill($validated)
    -> If email changed → $user->email_verified_at = null
    -> $user->save()
    -> Dispatches 'profile-updated' event → shows "Saved." message
```

### Changing Password

```
User navigates to /settings/password
  -> GET /settings/password (middleware: auth)

User fills form and clicks "Save"
  -> updatePassword() fires
    -> Validates current_password (must match), new password (confirmed, meets defaults)
    -> Auth::user()->update(['password' => Hash::make($validated['password'])])
    -> Resets form fields
    -> Dispatches 'password-updated' event → shows "Saved." message
```

### Deleting Account

```
User clicks "Delete Account" on profile page
  -> Modal opens asking for password confirmation

User enters password and clicks "Delete Account"
  -> deleteUser() fires
    -> Validates password is current_password
    -> Calls $logout on user, then ->delete()
    -> Redirects to /
```

### Linking a Minecraft Account

```
User navigates to /settings/minecraft-accounts
  -> GET /settings/minecraft-accounts (middleware: auth)
    -> mount() checks for active pending verification
    -> with() computes linked/archived accounts, remaining slots

User selects account type, enters username, clicks "Generate Verification Code"
  -> generateCode() fires
    -> $this->authorize('link-minecraft-account')
    -> Validates username (3-16 chars) and accountType (java/bedrock)
    -> GenerateVerificationCode::run(user, type, username)
      -> Creates MinecraftVerification record with code
      -> Creates MinecraftAccount in Verifying status
      -> Whitelists username via RCON
    -> Displays verification code with instructions

Page polls every 15 seconds via wire:poll.15s="checkVerification"
  -> checkVerification() re-queries verification status
    -> If completed → clears code, shows success toast
    -> If expired → calls ExpireVerification::run(), shows error toast
    -> If pending → no change (continues polling)
```

### Linking a Discord Account

```
User navigates to /settings/discord-account
  -> GET /settings/discord-account (middleware: auth)

User clicks "Link Discord Account" (requires @can('link-discord'))
  -> Browser navigates to route('auth.discord.redirect')
    -> Discord OAuth flow (handled by DiscordAuthController)
    -> Callback creates/updates DiscordAccount record
    -> Redirects back to settings page with session message

User clicks "Join Server" (invite link)
  -> Browser opens Discord invite URL in new tab
  -> joinServerClicked() sets awaitingGuildJoin = true
  -> JavaScript triggers checkGuildMembership() after 10s and 30s
    -> Checks guild membership via DiscordApiService
    -> If joined → SyncDiscordRoles::run() + SyncDiscordStaff::run()
    -> Shows success/failure toast
```

---

## 15. Configuration

| Key | Default | Purpose |
|-----|---------|---------|
| `lighthouse.max_minecraft_accounts` | — | Maximum Minecraft accounts a user can link |
| `lighthouse.minecraft_verification_grace_period_minutes` | `30` | Minutes before verification code expires |
| `lighthouse.minecraft.server_name` | — | Server display name shown in verification instructions |
| `lighthouse.minecraft.server_host` | — | Server hostname shown in verification instructions |
| `lighthouse.minecraft.server_port_java` | — | Java edition port shown in instructions |
| `lighthouse.minecraft.server_port_bedrock` | — | Bedrock edition port shown in instructions |
| `lighthouse.max_discord_accounts` | `1` | Maximum Discord accounts a user can link |
| `services.discord.invite_url` | — | Discord server invite URL |

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/Settings/ProfileUpdateTest.php` | 5 | Profile display, update, email verification, account deletion |
| `tests/Feature/Settings/PasswordUpdateTest.php` | 2 | Password update and validation |
| `tests/Feature/Settings/StaffBioTest.php` | 5 | Staff bio page access by role |
| `tests/Feature/Minecraft/MinecraftSettingsPageTest.php` | 14 | MC settings page, linking, verification, removal |
| `tests/Feature/Discord/DiscordSettingsPageTest.php` | 6 | Discord settings page, linking, unlinking |
| `tests/Feature/Minecraft/RemoveVerifyingAccountTest.php` | 5 | Removing accounts in Verifying status |

### Test Case Inventory

**ProfileUpdateTest:**
1. test_profile_page_is_displayed
2. test_profile_information_can_be_updated
3. test_email_verification_status_is_unchanged_when_email_address_is_unchanged
4. test_user_can_delete_their_account
5. test_correct_password_must_be_provided_to_delete_account

**PasswordUpdateTest:**
1. test_password_can_be_updated
2. test_correct_password_must_be_provided_to_update_password

**StaffBioTest:**
1. allows crew members to access the staff bio page
2. allows officers to access the staff bio page
3. denies jr crew from accessing the staff bio page
4. denies regular users from accessing the staff bio page
5. denies unauthenticated users from accessing the staff bio page

**MinecraftSettingsPageTest:**
1. displays minecraft settings page
2. requires authentication
3. displays linked accounts
4. shows verification form when no active verification
5. stowaway cannot see link form for minecraft accounts
6. traveler can see link form for minecraft accounts
6. generates verification code
7. displays active verification code
8. validates username required
9. removes linked account by setting status to removed
10. cannot remove another users account
11. shows remaining account slots
12. shows max accounts reached
13. polls for verification completion
14. checkVerification cleans up when timer expires while user is on the page

**DiscordSettingsPageTest:**
1. can render the discord settings page
2. requires authentication
3. shows link button for eligible users
4. shows upgrade message for ineligible users
5. shows linked accounts
6. can unlink an account

**RemoveVerifyingAccountTest:**
1. removes a verifying account when user has no active verification code state
2. expires the associated verification record when removing a verifying account
3. marks account cancelled if RCON server is offline
4. cannot remove another users verifying account
5. cannot remove a non-verifying account via removeVerifyingAccount

### Coverage Gaps

- **No test for appearance page** — the appearance settings page has no test coverage (though it's purely client-side)
- **No test for timezone update** — profile tests don't cover timezone or avatar_preference changes
- **No test for avatar_preference update** — the avatar source selection is untested
- **No test for notification settings** — the notifications settings page has no test (covered in Notification System feature doc's gaps)
- **No test for Discord role sync** — the `syncRoles()` method and `checkGuildMembership()` flow are untested
- **No test for Discord guild join flow** — the `joinServerClicked()` / `checkGuildMembership()` interaction is untested
- **No test for Minecraft simulateVerification** — the local-only simulation method is untested
- **No test for Minecraft setPrimary** — setting a primary Minecraft account from the settings page is untested
- **No test for Minecraft reactivateAccount** — reactivating an archived account is untested
- **No test for email resend verification** — the `resendVerificationNotification()` method is untested
- **No test for the settings redirect** — `/settings` → `/settings/profile` redirect is untested

---

## 17. File Map

**Models:**
- `app/Models/User.php` (settings-related fields)
- `app/Models/MinecraftAccount.php` (used by Minecraft settings)
- `app/Models/MinecraftVerification.php` (used by Minecraft settings)
- `app/Models/DiscordAccount.php` (used by Discord settings)

**Enums:**
- `app/Enums/MinecraftAccountStatus.php`
- `app/Enums/MinecraftAccountType.php`
- `app/Enums/DiscordAccountStatus.php`
- `app/Enums/EmailDigestFrequency.php`

**Actions:**
- `app/Actions/GenerateVerificationCode.php`
- `app/Actions/CompleteVerification.php`
- `app/Actions/ExpireVerification.php`
- `app/Actions/UnlinkMinecraftAccount.php`
- `app/Actions/ReactivateMinecraftAccount.php`
- `app/Actions/SetPrimaryMinecraftAccount.php`
- `app/Actions/UnlinkDiscordAccount.php`
- `app/Actions/SyncDiscordRoles.php`
- `app/Actions/SyncDiscordStaff.php`

**Policies:**
- `app/Policies/MinecraftAccountPolicy.php`
- `app/Policies/DiscordAccountPolicy.php`

**Gates:** `AuthServiceProvider.php` — gates: `edit-staff-bio`, `link-discord`, `link-minecraft-account`

**Notifications:** None specific

**Jobs:** None specific

**Services:**
- `app/Services/MinecraftRconService.php`
- `app/Services/DiscordApiService.php`

**Controllers:** None (all Volt routes)

**Volt Components:**
- `resources/views/livewire/settings/profile.blade.php`
- `resources/views/livewire/settings/password.blade.php`
- `resources/views/livewire/settings/appearance.blade.php`
- `resources/views/livewire/settings/notifications.blade.php`
- `resources/views/livewire/settings/minecraft-accounts.blade.php`
- `resources/views/livewire/settings/discord-account.blade.php`
- `resources/views/livewire/settings/staff-bio.blade.php`
- `resources/views/livewire/settings/delete-user-form.blade.php`

**Views/Partials:**
- `resources/views/components/settings/layout.blade.php`
- `resources/views/partials/settings-heading.blade.php`

**Routes:**
- `settings.profile` — `GET /settings/profile`
- `settings.password` — `GET /settings/password`
- `settings.appearance` — `GET /settings/appearance`
- `settings.notifications` — `GET /settings/notifications`
- `settings.minecraft-accounts` — `GET /settings/minecraft-accounts`
- `settings.discord-account` — `GET /settings/discord-account`
- `settings.staff-bio` — `GET /settings/staff-bio`
- `/settings` — redirect to `/settings/profile`

**Migrations:** Various (user table columns documented in respective feature docs)

**Console Commands:** None specific

**Tests:**
- `tests/Feature/Settings/ProfileUpdateTest.php`
- `tests/Feature/Settings/PasswordUpdateTest.php`
- `tests/Feature/Settings/StaffBioTest.php`
- `tests/Feature/Minecraft/MinecraftSettingsPageTest.php`
- `tests/Feature/Discord/DiscordSettingsPageTest.php`
- `tests/Feature/Minecraft/RemoveVerifyingAccountTest.php`

**Config:**
- `lighthouse.max_minecraft_accounts`
- `lighthouse.minecraft_verification_grace_period_minutes`
- `lighthouse.minecraft.server_name`, `server_host`, `server_port_java`, `server_port_bedrock`
- `lighthouse.max_discord_accounts`
- `services.discord.invite_url`

**Other:**
- `app/Livewire/Actions/Logout.php` (used by delete-user-form)

---

## 18. Known Issues & Improvement Opportunities

1. **No activity logging for profile changes** — Name, email, timezone, and avatar preference changes are not logged via `RecordActivity::run()`. There is no audit trail for profile modifications.

2. **No activity logging for password changes** — Password updates have no audit trail.

3. **No activity logging for account deletion** — When a user deletes their account, no activity log is created before deletion (though the record would be deleted with the user anyway, it could be useful for admin audit purposes).

4. **Appearance page has no server-side persistence** — The appearance setting is stored only client-side via Flux's `$flux.appearance` Alpine model. If the user switches browsers or clears local storage, their preference is lost.

5. **Empty PHP class for appearance** — The appearance Volt component has an empty PHP class (`new class extends Component { // }`), which means it could be a simple Blade partial instead of a Volt component.

6. **No validation for Pushover key format** — The notification settings page validates `pushover_key` as `nullable|string|max:255` but doesn't validate the format of the Pushover user key (which should be 30 alphanumeric characters).

7. **Avatar preference not tested** — The `avatar_preference` field and its 4 options (auto, minecraft, discord, gravatar) have no test coverage.

8. **Settings redirect not tested** — The `/settings` → `/settings/profile` redirect is not tested.

9. **Discord checkGuildMembership timing is client-side** — The guild join check uses `setTimeout` at 10s and 30s in JavaScript, which only works if the user stays on the page. If they navigate away and come back, the check doesn't resume.

10. **No rate limiting on generateCode** — The Minecraft verification code generation has no rate limiting beyond the gate check. A user could potentially spam code generation requests.

11. **Profile update doesn't use an Action class** — Profile updates use direct `$user->fill()` and `$user->save()` in the component, not an Action class, which is inconsistent with the project convention for other features.

12. **Password update doesn't use an Action class** — Same inconsistency as profile — direct model update in the component instead of through an Action.
