# User Profile Page -- Technical Documentation

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

The User Profile Page is the central hub for viewing and managing a user's identity, linked accounts, family relationships, staff position, discipline reports, and activity log within the Lighthouse community. It is accessible at `/profile/{user}` and serves as both a public profile view and an administrative management interface, depending on the viewer's permissions.

Any authenticated user can view any other user's profile (the `view` ability on `UserPolicy` always returns `true`). However, administrative actions -- such as editing user details, promoting/demoting membership levels, placing users in or releasing them from the Brig, managing Minecraft/Discord accounts, and viewing discipline reports -- are gated behind specific policies and gates. Staff members (Officers, Command department) see additional management controls, contact information, and audit data.

The profile page is composed of a parent Blade view (`resources/views/users/show.blade.php`) that hosts two Livewire Volt components: `display-basic-details` (the main profile card with all interactive features) and `display-activity-log` (a paginated modal of user activity). A third component, `discipline-reports-card`, is embedded within `display-basic-details` and handles the full discipline report lifecycle (create, edit, publish, view). Settings pages for self-service profile editing (`settings/profile`) and staff bio management (`settings/staff-bio`) are related but separate routes.

Key concepts: **Membership Levels** (Drifter -> Stowaway -> Traveler -> Resident -> Citizen), **Staff Ranks** (None, JrCrew, CrewMember, Officer), **Staff Departments** (Command, Chaplain, Engineer, Quartermaster, Steward), **Brig** (discipline/parental hold system), **Linked Accounts** (Minecraft, Discord), **Family** (parent-child relationships).

---

## 2. Database Schema

### `users` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned (PK) | No | auto | |
| name | varchar(255) | No | | Display name / username |
| email | varchar(255) | No | | Unique |
| email_verified_at | timestamp | Yes | null | |
| password | varchar(255) | No | | Hashed |
| remember_token | varchar(100) | Yes | null | |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |
| rules_accepted_at | timestamp | Yes | null | When user accepted community rules |
| membership_level | int | No | 0 | Cast to `MembershipLevel` enum |
| staff_rank | int | Yes | null | Cast to `StaffRank` enum |
| staff_department | varchar | Yes | null | Cast to `StaffDepartment` enum |
| staff_title | varchar | Yes | null | |
| timezone | varchar | Yes | null | User's preferred timezone |
| avatar_preference | varchar | Yes | null | `auto`, `minecraft`, `discord`, `gravatar` |
| pushover_key | varchar | Yes | null | Pushover notification key |
| pushover_monthly_count | int | No | 0 | Rate limiting |
| pushover_count_reset_at | timestamp | Yes | null | |
| email_digest_frequency | varchar | Yes | null | Cast to `EmailDigestFrequency` |
| notification_preferences | json | Yes | null | |
| promoted_at | timestamp | Yes | null | Last promotion timestamp |
| last_prayed_at | timestamp | Yes | null | |
| last_notification_read_at | timestamp | Yes | null | |
| last_login_at | timestamp | Yes | null | |
| last_ticket_digest_sent_at | timestamp | Yes | null | |
| in_brig | boolean | No | false | Whether user is currently in the brig |
| brig_reason | text | Yes | null | |
| brig_expires_at | timestamp | Yes | null | Auto-release timer |
| next_appeal_available_at | timestamp | Yes | null | |
| brig_timer_notified | boolean | No | false | |
| brig_type | varchar | Yes | null | Cast to `BrigType` enum |
| date_of_birth | date | Yes | null | |
| parent_email | varchar | Yes | null | |
| parent_allows_site | boolean | No | true | |
| parent_allows_login | boolean | No | true | |
| parent_allows_minecraft | boolean | No | true | |
| parent_allows_discord | boolean | No | true | |
| staff_first_name | varchar | Yes | null | |
| staff_last_initial | varchar | Yes | null | |
| staff_bio | text | Yes | null | |
| staff_phone | varchar | Yes | null | Protected PII |
| staff_photo_path | varchar | Yes | null | |
| is_board_member | boolean | No | false | |

**Indexes:** `email` (unique)
**Foreign Keys:** None on users table directly
**Migration(s):** `database/migrations/0001_01_01_000000_create_users_table.php` plus 17 additional migration files adding columns.

### `activity_logs` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned (PK) | No | auto | |
| causer_id | bigint unsigned | Yes | null | FK to users.id |
| subject_type | varchar | No | | Polymorphic model class |
| subject_id | bigint unsigned | No | | Polymorphic model ID |
| action | varchar | No | | Snake_case action identifier |
| description | text | Yes | null | Human-readable description |
| meta | json | Yes | null | IP, user_agent, etc. |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

### `parent_child_links` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned (PK) | No | auto | |
| parent_user_id | bigint unsigned | No | | FK to users.id |
| child_user_id | bigint unsigned | No | | FK to users.id |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

---

## 3. Models & Relationships

### User (`app/Models/User.php`)

**Relationships:**

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `minecraftAccounts()` | hasMany | MinecraftAccount | All MC accounts |
| `discordAccounts()` | hasMany | DiscordAccount | All Discord accounts |
| `children()` | belongsToMany | User | Via `parent_child_links` (parent_user_id -> child_user_id) |
| `parents()` | belongsToMany | User | Via `parent_child_links` (child_user_id -> parent_user_id) |
| `roles()` | belongsToMany | Role | User roles (Admin, Page Editor, etc.) |
| `staffPosition()` | hasOne | StaffPosition | Current staff position |
| `meetingReports()` | hasMany | MeetingReport | |
| `boardMembership()` | hasOne | BoardMember | |
| `disciplineReports()` | hasMany | DisciplineReport | Via `subject_user_id` |
| `acknowledgedAnnouncements()` | belongsToMany | Announcement | |
| `prayerCountries()` | belongsToMany | PrayerCountry | |

**Key Methods:**
- `isInBrig(): bool` -- checks `in_brig` flag
- `brigTimerExpired(): bool` -- whether brig auto-release timer is past
- `canAppeal(): bool` -- whether user can submit brig appeal
- `avatarUrl(): ?string` -- resolves avatar based on preference (auto: MC -> Discord -> null)
- `minecraftAvatarUrl(): ?string` -- primary active MC account avatar
- `discordAvatarUrl(): ?string` -- active Discord account avatar
- `gravatarUrl(): string` -- Gravatar fallback
- `staffPhotoUrl(): ?string` -- staff bio photo URL
- `initials(): string` -- for Flux avatar fallback
- `isAdmin(): bool` -- has Admin role
- `hasRole(string): bool` -- checks role by name
- `isAtLeastLevel(MembershipLevel): bool` -- membership level comparison
- `isAtLeastRank(StaffRank): bool` -- staff rank comparison
- `isInDepartment(StaffDepartment): bool` -- department check
- `isJrCrew(): bool` -- shorthand for JrCrew rank check
- `isAdult(): bool` -- age >= 18 or no DOB set
- `isMinor(): bool` -- age < 18 and DOB set
- `age(): ?int` -- calculated age from DOB
- `disciplineRiskScore(): array` -- cached 7d/30d/90d risk scores
- `riskScoreColor(int): string` -- static color mapping for risk score badges
- `primaryMinecraftAccount(): ?MinecraftAccount` -- active primary MC account

**Casts:**
- `email_verified_at` => `datetime`
- `password` => `hashed`
- `membership_level` => `MembershipLevel::class`
- `staff_rank` => `StaffRank::class`
- `staff_department` => `StaffDepartment::class`
- `email_digest_frequency` => `EmailDigestFrequency::class`
- `rules_accepted_at` => `datetime`
- `promoted_at` => `datetime`
- `in_brig` => `boolean`
- `brig_expires_at` => `datetime`
- `next_appeal_available_at` => `datetime`
- `brig_timer_notified` => `boolean`
- `date_of_birth` => `date`
- `brig_type` => `BrigType::class`
- `parent_allows_site` / `parent_allows_login` / `parent_allows_minecraft` / `parent_allows_discord` => `boolean`
- `is_board_member` => `boolean`
- `notification_preferences` => `array`

### ActivityLog (`app/Models/ActivityLog.php`)

**Relationships:**

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `causer()` | belongsTo | User | Via `causer_id` |
| `subject()` | morphTo | any | Polymorphic |

**Scopes:**
- `scopeRelevantTo(Builder, User)` -- activities where user is causer OR subject

---

## 4. Enums Reference

### MembershipLevel (`app/Enums/MembershipLevel.php`)

| Case | Value | Label | Notes |
|------|-------|-------|-------|
| Drifter | 0 | Drifter | New/unverified users |
| Stowaway | 1 | Stowaway | Rules accepted, pending promotion |
| Traveler | 2 | Traveler | Basic community member |
| Resident | 3 | Resident | Established member |
| Citizen | 4 | Citizen | Full community member |

Helper methods: `label()`, `discordRoleId()`, `minecraftRank()`

### StaffRank (`app/Enums/StaffRank.php`)

| Case | Value | Label | Color | Notes |
|------|-------|-------|-------|-------|
| None | 0 | None | zinc | Non-staff |
| JrCrew | 1 | Junior Crew Member | amber | Under-17 staff |
| CrewMember | 2 | Crew Member | fuchsia | Standard staff |
| Officer | 3 | Officer | emerald | Senior staff |

Helper methods: `label()`, `color()`, `discordRoleId()`

### StaffDepartment (`app/Enums/StaffDepartment.php`)

| Case | Value | Label | Notes |
|------|-------|-------|-------|
| Command | command | Command | Leadership department |
| Chaplain | chaplain | Chaplain | |
| Engineer | engineer | Engineer | Technical department |
| Quartermaster | quartermaster | Quartermaster | User management |
| Steward | steward | Steward | |

Helper methods: `label()`, `discordRoleId()`

### BrigType (`app/Enums/BrigType.php`)

| Case | Value | Label | Notes |
|------|-------|-------|-------|
| Discipline | discipline | Disciplinary | Staff-initiated |
| ParentalPending | parental_pending | Pending Parental Approval | |
| ParentalDisabled | parental_disabled | Restricted by Parent | |
| AgeLock | age_lock | Age Verification Required | |

Helper methods: `label()`, `isDisciplinary()`, `isParental()`

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

| Gate Name | Who Can Pass | Logic Summary |
|-----------|-------------|---------------|
| `view-community-content` | Users not in brig | `! $user->in_brig` |
| `manage-stowaway-users` | Admin, Officers, QM CrewMembers | Core gate for promote/demote/brig actions |
| `manage-traveler-users` | Admin, Officers, QM CrewMembers | Same as manage-stowaway-users |
| `view-user-discipline-reports` | Admin, JrCrew+, self, parents | Staff or subject or parent of subject |
| `edit-staff-bio` | CrewMember+ or Board Members | For settings/staff-bio page |
| `link-discord` | Stowaway+ not in brig, parent allows | |
| `link-minecraft-account` | Stowaway+ not in brig, parent allows | |
| `view-acp` | Admin, CrewMember+, Page Editor, Engineer dept | |
| `view-activity-log` | Admin, Officers, Engineer dept | |
| `manage-discipline-reports` | Admin, JrCrew+ | |
| `publish-discipline-reports` | Admin, Officers | |

### Policies

#### UserPolicy (`app/Policies/UserPolicy.php`)

**`before()` hook:** Admin or Command Department Officer bypasses all checks (returns `true`).

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `viewAny` | Officers | `isAtLeastRank(StaffRank::Officer)` |
| `view` | Everyone | Always returns `true` |
| `viewActivityLog` | Self, QM dept, Officers | Own profile, Quartermaster department, or Officer rank |
| `viewPii` | Admin, Command dept, QM dept | View email/contact info |
| `viewStaffPhone` | Officers, Board Members | Only if target is staff/board member too |
| `update` | Self, QM Officers | Own profile or Quartermaster Officer |
| `create` | Nobody | Returns `false` |
| `delete` | Nobody | Returns `false` |
| `updateStaffPosition` | Nobody (admin bypass only) | Returns `false` |
| `removeStaffPosition` | Nobody (admin bypass only) | Returns `false` |

#### MinecraftAccountPolicy (`app/Policies/MinecraftAccountPolicy.php`)

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `viewAny` | Admin, Officers | |
| `setPrimary` | Account owner, Admin | Account must be Active |
| `delete` | Account owner, Admin | |
| `reactivate` | Account owner, Admin | Account must be Removed |
| `viewUuid` | Admin, Engineers, Officers | |
| `viewStaffAuditFields` | Admin, any staff member | |
| `revoke` | Admin, Engineer/Command Officers | Account must be Active |
| `forceDelete` | Admin only | Account must be Removed or Verifying |

#### StaffPositionPolicy (referenced via `@can('assign', $position)`)

Used for staff position assignment/removal controls on the profile page.

### Permissions Matrix

| User Type | View Profile | Edit User | Promote/Demote | Brig Actions | View PII | View Staff Phone | MC Revoke | MC Reactivate | MC Force Delete | Discord Revoke | View Discipline Reports | Create Discipline Report | Publish Discipline Report | View Activity Log |
|-----------|-------------|-----------|----------------|-------------|----------|-----------------|-----------|---------------|----------------|---------------|------------------------|------------------------|--------------------------|------------------|
| Regular User | Yes | Own only | No | No | No | No | No | Own only | No | No | Own only | No | No | Own only |
| Parent | Yes | No | No | No | No | No | No | No | No | No | Own + Children | No | No | No |
| JrCrew Staff | Yes | No | No | No | No | No | No | Own only | No | No | Yes (all) | Yes | No | Dept-based |
| CrewMember Staff | Yes | No | QM dept only | QM dept only | QM dept | No | No | Own only | No | No | Yes (all) | Yes | No | Dept-based |
| Officer Staff | Yes | QM dept | Yes | Yes | Dept-based | Yes (staff targets) | Eng/Cmd dept | Yes | No | No | Yes (all) | Yes | Yes | Yes |
| Command Officer | Yes | Yes (bypass) | Yes | Yes | Yes | Yes | Yes | Yes | No | No | Yes (all) | Yes | Yes | Yes |
| Admin | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes (all) | Yes | Yes | Yes |

---

## 6. Routes

| Method | URL | Middleware | Handler | Route Name |
|--------|-----|-----------|---------|------------|
| GET | `/profile/{user}` | `auth`, `can:view,user` | `UserController@show` | `profile.show` |
| GET | `/settings/profile` | `auth` | Volt: `settings.profile` | `settings.profile` |
| GET | `/settings/staff-bio` | `auth` | Volt: `settings.staff-bio` | `settings.staff-bio` |

---

## 7. User Interface Components

### Profile Show Page
**File:** `resources/views/users/show.blade.php`
**Route:** `/profile/{user}` (route name: `profile.show`)

**Purpose:** Parent view that composes the profile page from Livewire components.

**Layout:**
- Renders `<livewire:users.display-basic-details :user="$user" />` as the main component
- Conditionally shows an "View Activity Log" button + modal if viewer `can('viewActivityLog', $user)`
- Activity log modal contains `<livewire:users.display-activity-log :user="$user" lazy />`

---

### display-basic-details
**File:** `resources/views/livewire/users/display-basic-details.blade.php`
**Route:** Embedded in `/profile/{user}`

**Purpose:** The primary profile component displaying user identity, linked accounts, family relationships, staff management, and administrative actions.

**Authorization:** Various checks throughout using `@can`, `@canany`, `$this->authorize()`, and `Auth::user()->can()`.

**PHP Class Properties:**
- `public User $user` -- the profile subject
- `public ?MinecraftAccount $selectedAccount` -- for MC account detail modal
- `public string $brigActionReason` / `public ?int $brigActionDays` -- brig modal form
- `public ?int $accountToRevoke` / `public ?int $accountToForceDelete` -- MC action confirmations
- `public bool $editingUser` / `public array $editUserData` -- edit user modal form

**PHP Class Methods:**
- `mount(User $user)` -- loads user with relationships
- `assignToPosition(int $positionId)` -- assigns user to a staff position
- `removeFromPosition()` -- removes user from current staff position
- `getAvailablePositionsProperty()` -- computed: vacant staff positions
- `showAccount(int $accountId)` -- opens MC account detail modal
- `confirmRevoke(int $accountId)` / `revokeMinecraftAccount()` -- MC revoke flow
- `reactivateMinecraftAccount(int $accountId)` -- reactivate removed MC account
- `confirmForceDelete(int $accountId)` / `forceDeleteMinecraftAccount()` -- permanent MC delete
- `revokeDiscordAccount(int $accountId)` -- revoke Discord account (admin only)
- `promoteUser()` -- promote membership level
- `openPutInBrigModal()` / `confirmPutInBrig()` -- put user in brig
- `openReleaseFromBrigModal()` / `confirmReleaseFromBrig()` -- release from brig
- `getNextMembershipLevelProperty()` / `getPreviousMembershipLevelProperty()` -- computed
- `demoteUser()` -- demote membership level
- `openEditUserModal()` / `saveEditUser()` -- edit user details

**UI Layout (3 rows):**

**Row 1 - Core Identity:**
- **User Info Card**: Name, brig badge, child account badge, age badge (staff only), membership level, join date, Actions dropdown (Edit User, Brig actions, Promote/Demote)
- **Linked Accounts Card**: Minecraft section (account list with avatars, status badges, Manage link for own profile, Revoke/Reactivate/Delete buttons per policy), Discord section (account list with avatars, status badges, Manage link, Revoke for admins)
- **Family Card** (conditional): Parents list, Children list, "View Parent Portal" link for officers

**Row 2 - Staff Management (conditional):**
- **Contact Information Card** (viewPii or viewStaffPhone): Email, phone number
- **Staff Position Card**: Current position title, department, rank, Remove/Change Position buttons

**Row 3 - Details & Reports (conditional):**
- **Staff Details Card**: Staff photo, first name/last initial, rank badge, department badge, position title/description, bio, "Update Staff Bio" link (for own profile)
- **Discipline Reports Card**: Embedded `<livewire:users.discipline-reports-card>` (lazy loaded)

**Modals:**
- MC Account Detail (`mc-account-detail`) -- via Blade component
- Assign Staff Position (`assign-staff-position`) -- list of vacant positions
- Edit User (`profile-edit-user-modal`) -- flyout with name, email, DOB, parent email
- Put in Brig (`profile-put-in-brig-modal`) -- reason + optional days
- Promote Confirmation (`profile-promote-confirm-modal`)
- Demote Confirmation (`profile-demote-confirm-modal`)
- Release from Brig (`profile-release-from-brig-modal`) -- reason
- Force Delete MC Account (`confirm-force-delete-mc-account`)
- Revoke MC Account (`confirm-revoke-mc-account`)

---

### discipline-reports-card
**File:** `resources/views/livewire/users/discipline-reports-card.blade.php`
**Route:** Embedded in display-basic-details (lazy loaded)

**Purpose:** Displays and manages discipline reports for the profile subject.

**Authorization:** Mount checks: staff (JrCrew+), self, or parent. Individual actions use policy checks on `DisciplineReport`.

**PHP Class Properties:**
- `#[Locked] public int $userId` -- subject user ID
- `#[Locked] public bool $isStaffViewing` -- whether viewer is staff
- Form fields: `formDescription`, `formLocation`, `formWitnesses`, `formActionsTaken`, `formSeverity`, `formCategory`
- `#[Locked] public ?int $editingReportId` / `#[Locked] public ?int $viewingReportId`

**PHP Class Methods:**
- `mount(User $user)` -- authorization check, sets viewing context
- `getUserProperty()` / `getReportsProperty()` / `getRiskScoreProperty()` / `getCategoriesProperty()` -- computed
- `openCreateModal()` / `createReport()` -- create discipline report
- `openEditModal(int)` / `updateReport()` -- edit draft report
- `publishReport(int)` -- publish draft (officer+ only)
- `viewReport(int)` -- view report detail modal
- `getViewingReportProperty()` -- computed for view modal

**UI Elements:**
- Risk score badge with 7d/30d/90d breakdown tooltip
- Reports table: date, category badge, description (truncated), severity badge, Draft badge
- Action buttons: View, Edit (drafts only), Publish (officers, not own report)
- Create Report Modal: category, description, location, witnesses, actions taken, severity
- Edit Report Modal: same fields pre-filled
- View Report Modal: full details including reporter, publisher, timestamps (staff only)

---

### display-activity-log
**File:** `resources/views/livewire/users/display-activity-log.blade.php`
**Route:** Embedded in profile show page (lazy loaded, inside modal)

**Purpose:** Paginated activity log for the user (activities they caused or were subject of).

**Authorization:** Gated by `@can('viewActivityLog', $user)` in the parent view.

**PHP Class:**
- Uses `WithPagination`
- `with()` returns paginated `ActivityLog::relevantTo($this->user)` with `causer` and `subject` eager-loaded

**UI Elements:**
- Table with columns: Date/Time (localized to viewer's timezone), Subject (linked), Action (prettified), By User (linked), Details
- Pagination links

---

### Settings Profile
**File:** `resources/views/livewire/settings/profile.blade.php`
**Route:** `/settings/profile` (route name: `settings.profile`)

**Purpose:** Self-service profile editing for the authenticated user.

**PHP Class Properties:**
- `name`, `email`, `timezone`, `avatar_preference`, `timezones[]`

**Methods:**
- `mount()` -- populates from Auth::user()
- `updateProfileInformation()` -- validates and saves name, email, timezone, avatar_preference; resets email_verified_at if email changed
- `resendVerificationNotification()` -- re-sends email verification

**UI Elements:**
- Form: Name input, Email input (with unverified warning), Timezone searchable select, Avatar Source select (Auto/Minecraft/Discord/Gravatar)
- Save button, "Delete Account" section via `<livewire:settings.delete-user-form />`

---

### Settings Staff Bio
**File:** `resources/views/livewire/settings/staff-bio.blade.php`
**Route:** `/settings/staff-bio` (route name: `settings.staff-bio`)

**Purpose:** Staff members manage their public staff profile (bio, photo, name, phone).

**Authorization:** `edit-staff-bio` gate (CrewMember+ or Board Member).

**PHP Class Properties:**
- `firstName`, `lastInitial`, `bio`, `phone`, `$photo` (file upload), `existingPhotoUrl`

**Methods:**
- `mount()` -- authorizes, populates from Auth::user()
- `save()` -- validates, handles photo upload/storage, saves fields
- `removePhoto()` -- deletes staff photo from storage

**UI Elements:**
- Current photo preview with remove button
- Photo upload field (max 2MB)
- First name, Last initial, Bio textarea, Phone input (protected info notice)
- Save button

---

### MC Account Detail Modal (Blade Component)
**File:** `resources/views/components/minecraft/mc-account-detail-modal.blade.php`

**Purpose:** Displays detailed information about a specific Minecraft account.

**Props:** `$account` (nullable MinecraftAccount)

**UI Elements:**
- Avatar, username, status/type/primary badges
- UUID (if `viewUuid` policy), linked user link, created date
- Staff audit fields (verified_at, last_username_check_at) if `viewStaffAuditFields`
- Action buttons: Revoke, Reactivate, Delete Permanently (per policy)

---

## 8. Actions (Business Logic)

### PromoteUser (`app/Actions/PromoteUser.php`)

**Signature:** `handle(User $user, MembershipLevel $maxLevel = MembershipLevel::Citizen)`

**Step-by-step logic:**
1. Checks if user is already at or above `$maxLevel`; returns early if so
2. Finds the next level in the enum sequence
3. Updates `membership_level` and `promoted_at` on the user
4. Logs activity: `RecordActivity::run($user, 'user_promoted', "Promoted from X to Y.")`
5. Syncs Minecraft ranks: `SyncMinecraftRanks::run($user)`
6. Syncs Discord roles: `SyncDiscordRoles::run($user)` (with error handling)
7. Sends level-specific notifications:
   - Stowaway: `UserPromotedToStowawayNotification` to QM and Command staff
   - Traveler: `UserPromotedToTravelerNotification` to the user
   - Resident: `UserPromotedToResidentNotification` to the user

**Called by:** `display-basic-details::promoteUser()`

---

### DemoteUser (`app/Actions/DemoteUser.php`)

**Signature:** `handle(User $user, MembershipLevel $minLevel = MembershipLevel::Drifter)`

**Step-by-step logic:**
1. Checks if user is already at or below `$minLevel`; returns early if so
2. Finds the previous level in the enum sequence
3. Updates `membership_level` on the user
4. Logs activity: `RecordActivity::handle($user, 'user_demoted', "Demoted from X to Y.")`
5. Syncs Minecraft ranks and Discord roles

**Called by:** `display-basic-details::demoteUser()`

---

### PutUserInBrig (`app/Actions/PutUserInBrig.php`)

**Signature:** `handle(User $target, User $admin, string $reason, ?Carbon $expiresAt = null, ?Carbon $appealAvailableAt = null, BrigType $brigType = BrigType::Discipline, bool $notify = true): void`

**Step-by-step logic:**
1. Sets default `appealAvailableAt` to 24 hours if discipline type and not provided
2. Updates user: `in_brig=true`, `brig_reason`, `brig_expires_at`, `next_appeal_available_at`, `brig_timer_notified=false`, `brig_type`
3. Bans all Active/Verifying/ParentDisabled Minecraft accounts (sends whitelist remove commands, sets status to Banned)
4. Strips Discord roles and marks Discord accounts as Brigged
5. Logs activity: `RecordActivity::handle($target, 'user_put_in_brig', ...)`
6. Sends notification: `UserPutInBrigNotification` to target user (if `$notify`)

**Called by:** `display-basic-details::confirmPutInBrig()`, `ReleaseUserFromBrig` (parental re-engage)

---

### ReleaseUserFromBrig (`app/Actions/ReleaseUserFromBrig.php`)

**Signature:** `handle(User $target, User $admin, string $reason, bool $notify = true): void`

**Step-by-step logic:**
1. Clears brig fields: `in_brig=false`, nulls reason/expires/appeal/type
2. Restores banned Minecraft accounts (whitelists, sets status to Active or ParentDisabled)
3. Syncs Minecraft ranks and staff positions
4. Restores brigged Discord accounts (sets to Active or ParentDisabled)
5. Syncs Discord roles and staff
6. If parent has restricted site access for a minor, re-applies parental brig hold
7. Logs activity: `RecordActivity::handle($target, 'user_released_from_brig', ...)`
8. Sends notification: `UserReleasedFromBrigNotification` to target user (if `$notify`)

**Called by:** `display-basic-details::confirmReleaseFromBrig()`

---

### AssignStaffPosition (`app/Actions/AssignStaffPosition.php`)

**Signature:** `handle(StaffPosition $position, User $user): void`

**Step-by-step logic:**
1. Wrapped in DB transaction
2. If user already holds a different position, unassigns via `UnassignStaffPosition::run()`
3. If position has a different user, unassigns them
4. Updates `position.user_id` to the user
5. Computes effective rank (JrCrew if under 17 and position is CrewMember)
6. Calls `SetUsersStaffPosition::run()` to sync user's staff fields
7. Logs activity: `RecordActivity::run($position, 'staff_position_assigned', ...)`

**Called by:** `display-basic-details::assignToPosition()`

---

### UnassignStaffPosition (`app/Actions/UnassignStaffPosition.php`)

**Signature:** `handle(StaffPosition $position): void`

**Step-by-step logic:**
1. Gets the user assigned to the position; returns early if none
2. Wrapped in DB transaction
3. Clears `position.user_id`
4. Calls `RemoveUsersStaffPosition::run($user)` to clear staff fields
5. Logs activity: `RecordActivity::run($position, 'staff_position_unassigned', ...)`

**Called by:** `display-basic-details::removeFromPosition()`, `AssignStaffPosition` (re-assignment cleanup)

---

### RevokeMinecraftAccount (`app/Actions/RevokeMinecraftAccount.php`)

**Signature:** `handle(MinecraftAccount $account, User $admin): array`

**Step-by-step logic:**
1. Checks `revoke` policy
2. Resets MC rank to default via RCON
3. Removes MC staff position if user has one
4. Removes from whitelist via RCON
5. Sets account status to `Removed`
6. If was primary, clears flag and auto-assigns new primary
7. Logs activity: `RecordActivity::run($affectedUser, 'minecraft_account_revoked', ...)`

**Called by:** `display-basic-details::revokeMinecraftAccount()`

---

### ReactivateMinecraftAccount (`app/Actions/ReactivateMinecraftAccount.php`)

**Signature:** `handle(MinecraftAccount $account, User $user): array`

**Step-by-step logic:**
1. Validates account is in Removed status
2. Checks account limit for owner
3. Checks owner is not in brig
4. Adds to whitelist via RCON
5. Sets status to Active
6. Auto-assigns primary if needed
7. Syncs MC ranks and staff positions
8. Logs activity: `RecordActivity::run($owner, 'minecraft_account_reactivated', ...)`

**Called by:** `display-basic-details::reactivateMinecraftAccount()`

---

### ForceDeleteMinecraftAccount (`app/Actions/ForceDeleteMinecraftAccount.php`)

**Signature:** `handle(MinecraftAccount $account, User $admin): array`

**Step-by-step logic:**
1. Checks admin status
2. Validates account is Removed or Verifying
3. Hard deletes the account record
4. Logs activity: `RecordActivity::run($affectedUser, 'minecraft_account_permanently_deleted', ...)`

**Called by:** `display-basic-details::forceDeleteMinecraftAccount()`

---

### RevokeDiscordAccount (`app/Actions/RevokeDiscordAccount.php`)

**Signature:** `handle(DiscordAccount $account, User $admin): void`

**Step-by-step logic:**
1. Removes all managed Discord roles via API
2. Hard deletes the account record
3. Logs activity: `RecordActivity::run($owner, 'discord_account_revoked', ...)`

**Called by:** `display-basic-details::revokeDiscordAccount()`

---

### RecordActivity (`app/Actions/RecordActivity.php`)

**Signature:** `handle($subject, $action, $description = null, ?User $actor = null)` (static)

**Step-by-step logic:**
1. Determines causer ID from `$actor`, or falls back to Auth::id()
2. Captures meta (IP, user_agent)
3. Creates `ActivityLog` record

**Called by:** All profile actions, `display-basic-details::saveEditUser()`

---

### CreateDisciplineReport, UpdateDisciplineReport, PublishDisciplineReport

**Called by:** `discipline-reports-card` component methods. See discipline reports documentation for details.

---

## 9. Notifications

### UserPromotedToStowawayNotification (`app/Notifications/UserPromotedToStowawayNotification.php`)

**Triggered by:** `PromoteUser` (when promoting to Stowaway)
**Recipient:** QM and Command staff members
**Channels:** mail, Pushover (via TicketNotificationService)
**Content summary:** Notifies staff that a new Stowaway has been promoted

### UserPromotedToTravelerNotification (`app/Notifications/UserPromotedToTravelerNotification.php`)

**Triggered by:** `PromoteUser` (when promoting to Traveler)
**Recipient:** The promoted user
**Channels:** mail, Pushover (via TicketNotificationService)

### UserPromotedToResidentNotification (`app/Notifications/UserPromotedToResidentNotification.php`)

**Triggered by:** `PromoteUser` (when promoting to Resident)
**Recipient:** The promoted user
**Channels:** mail, Pushover (via TicketNotificationService)

### UserPutInBrigNotification (`app/Notifications/UserPutInBrigNotification.php`)

**Triggered by:** `PutUserInBrig` action
**Recipient:** The target user
**Channels:** mail, Pushover (via TicketNotificationService)
**Content summary:** Informs user they've been placed in the brig with reason and timer details

### UserReleasedFromBrigNotification (`app/Notifications/UserReleasedFromBrigNotification.php`)

**Triggered by:** `ReleaseUserFromBrig` action
**Recipient:** The target user
**Channels:** mail, Pushover (via TicketNotificationService)
**Content summary:** Informs user they've been released from the brig

---

## 10. Background Jobs

Not applicable for this feature. All profile actions are performed synchronously.

---

## 11. Console Commands & Scheduled Tasks

Not directly applicable to the profile page itself. Brig timer expiration is handled by a separate scheduled task not specific to the profile view.

---

## 12. Services

### MinecraftRconService (`app/Services/MinecraftRconService.php`)
**Purpose:** Executes Minecraft server commands via RCON.
**Used by:** `RevokeMinecraftAccount`, `ReactivateMinecraftAccount` for whitelist management and rank resets.

### DiscordApiService (`app/Services/DiscordApiService.php`)
**Purpose:** Interacts with Discord API for role management.
**Used by:** `PutUserInBrig` (strip roles), `RevokeDiscordAccount` (remove roles).

### TicketNotificationService (`app/Services/TicketNotificationService.php`)
**Purpose:** Smart notification delivery (mail + Pushover) with rate limiting and preference checks.
**Used by:** `PromoteUser`, `PutUserInBrig`, `ReleaseUserFromBrig` for sending user notifications.

---

## 13. Activity Log Entries

| Action String | Logged By | Subject Model | Description |
|---------------|-----------|---------------|-------------|
| `user_promoted` | PromoteUser | User | "Promoted from X to Y." |
| `user_demoted` | DemoteUser | User | "Demoted from X to Y." |
| `user_put_in_brig` | PutUserInBrig | User | "Put in the brig by {admin}. Reason: {reason}. Timer/appeal details." |
| `user_released_from_brig` | ReleaseUserFromBrig | User | "Released from brig by {admin}. Reason: {reason}" |
| `update_profile` | display-basic-details::saveEditUser | User | "User profile updated." |
| `staff_position_assigned` | AssignStaffPosition | StaffPosition | "Assigned {user} to position: {title} ({dept}, {rank})" |
| `staff_position_unassigned` | UnassignStaffPosition | StaffPosition | "Unassigned {user} from position: {title}" |
| `minecraft_account_revoked` | RevokeMinecraftAccount | User | "{admin} revoked {type} account: {username}" |
| `minecraft_staff_position_removed` | RevokeMinecraftAccount | User | "{admin} removed Minecraft staff position for {username}" |
| `minecraft_account_reactivated` | ReactivateMinecraftAccount | User | "Reactivated {type} account: {username}" |
| `minecraft_account_permanently_deleted` | ForceDeleteMinecraftAccount | User | "Admin {admin} permanently deleted {type} account: {username}" |
| `discord_account_revoked` | RevokeDiscordAccount | User | "Discord account revoked by {admin}: {username} ({id})" |

---

## 14. Data Flow Diagrams

### Viewing a User Profile

```text
User navigates to /profile/{user}
  -> GET /profile/{user} (middleware: auth, can:view,user)
    -> UserController::show($user)
      -> Gate::authorize('view', $user) (UserPolicy::view -> true)
      -> return view('users.show', ['user' => $user])
        -> Livewire: users.display-basic-details (mount, loads relationships)
        -> @can('viewActivityLog', $user) -> Activity Log button + modal
          -> Livewire: users.display-activity-log (lazy, in modal)
        -> @can('view-user-discipline-reports', $user) -> Discipline reports card
          -> Livewire: users.discipline-reports-card (lazy)
```

### Promoting a User

```text
Staff clicks "Promote to X" in Actions dropdown
  -> profile-promote-confirm-modal shown
  -> Staff clicks "Confirm Promotion"
    -> display-basic-details::promoteUser()
      -> Auth::user()->can('manage-stowaway-users') check
      -> Validates user is at least Stowaway
      -> PromoteUser::run($this->user)
        -> User.membership_level updated to next level
        -> User.promoted_at = now()
        -> RecordActivity::run($user, 'user_promoted', ...)
        -> SyncMinecraftRanks::run($user)
        -> SyncDiscordRoles::run($user)
        -> Level-specific notification sent
      -> Flux::modal('profile-promote-confirm-modal')->close()
      -> Flux::toast('Promoted to X successfully.', variant: 'success')
```

### Placing a User in the Brig

```text
Staff clicks "Put in Brig" in Actions dropdown
  -> profile-put-in-brig-modal shown
  -> Staff enters reason and optional days, clicks "Confirm -- Put in Brig"
    -> display-basic-details::confirmPutInBrig()
      -> Auth::user()->can('manage-stowaway-users') check
      -> Validates brigActionReason (required, min:5), brigActionDays (optional, 1-365)
      -> PutUserInBrig::run($user, Auth::user(), $reason, $expiresAt)
        -> User.in_brig = true, brig fields set
        -> MC accounts banned (whitelist removed)
        -> Discord roles stripped, accounts set to Brigged
        -> RecordActivity logged
        -> UserPutInBrigNotification sent
      -> Flux::modal close + toast
```

### Editing User Details (Admin)

```text
Staff clicks "Edit User" in Actions dropdown
  -> display-basic-details::openEditUserModal()
    -> $this->authorize('update', $this->user)
    -> profile-edit-user-modal flyout shown with current data
  -> Staff modifies fields, clicks "Save"
    -> display-basic-details::saveEditUser()
      -> $this->authorize('update', $this->user)
      -> Validates name, email, DOB, parent_email
      -> User::update([...])
      -> RecordActivity::run($user, 'update_profile', ...)
      -> Flux::modal close + toast
```

### Revoking a Minecraft Account

```text
Staff clicks "Revoke" on a MC account
  -> display-basic-details::confirmRevoke($accountId)
    -> Policy check: can('revoke', $account)
    -> confirm-revoke-mc-account modal shown
  -> Staff clicks "Revoke Account"
    -> display-basic-details::revokeMinecraftAccount()
      -> $this->authorize('revoke', $account)
      -> RevokeMinecraftAccount::run($account, Auth::user())
        -> RCON: reset rank to default
        -> RCON: remove from whitelist
        -> Account.status = Removed
        -> Auto-assign new primary if needed
        -> RecordActivity logged
      -> Modal close + toast
```

### Self-Service Profile Update

```text
User navigates to /settings/profile
  -> Volt: settings.profile
    -> mount() populates from Auth::user()
  -> User modifies name/email/timezone/avatar, clicks "Save"
    -> updateProfileInformation()
      -> Validates fields (name, email unique, timezone, avatar_preference)
      -> User.fill($validated) + save
      -> If email changed: email_verified_at = null
      -> Dispatches 'profile-updated' event
```

---

## 15. Configuration

| Key | Default | Purpose |
|-----|---------|---------|
| `lighthouse.max_minecraft_accounts` | (from config) | Max MC accounts per user, checked during reactivation |
| `lighthouse.discord.roles.*` | (env-based) | Discord role IDs for rank/department syncing |

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/Policies/UserPolicyTest.php` | 25 tests | UserPolicy: before bypass, viewAny, view, viewPii, update, create/delete/restore/forceDelete, viewStaffPhone, updateStaffPosition, removeStaffPosition |
| `tests/Feature/Livewire/ProfileFamilyDisplayTest.php` | 8 tests | Profile family display: child badge, family card, parent/children display, parent portal link visibility, action dropdown for staff |
| `tests/Feature/Livewire/ProfilePhase3Test.php` | 8 tests | Age badge display, badge colors by age, parent portal link visibility for officers vs non-officers, family card display |
| `tests/Feature/Livewire/DisciplineReportsCardTest.php` | 12 tests | Reports card visibility, draft vs published filtering, CRUD operations, publish authorization, risk score display |
| `tests/Feature/Settings/ProfileUpdateTest.php` | 5 tests | Settings profile page display, profile info update, email verification preservation, account deletion, password validation |
| `tests/Feature/Settings/StaffBioTest.php` | 5 tests | Staff bio page access for CrewMember+, Officers; denial for JrCrew, regular users, unauthenticated |
| `tests/Feature/AvatarTest.php` | 12 tests | User::avatarUrl() method: auto/minecraft/discord/gravatar preferences, fallback logic, primary account priority, inactive account filtering |

### Test Case Inventory

**UserPolicyTest.php:**
- `admin can bypass all policy checks`
- `command officer can bypass all policy checks`
- `non-admin non-command returns null from before`
- `admin can view any users`
- `quartermaster officer can view any users`
- `regular user cannot view any users`
- `user can view their own profile`
- `traveler can view other profiles`
- `drifter can view other profiles`
- `stowaway can view other profiles`
- `admin can view PII`
- `command staff can view PII`
- `quartermaster staff can view PII`
- `regular user cannot view PII`
- `user can update themselves`
- `quartermaster officer can update other users`
- `regular crew member cannot update other users`
- `no one can create/delete/restore/forceDelete users through policy`
- `officer/board member can/cannot view staff phone` (6 tests)
- `no one can update/remove staff positions through policy`

**ProfileFamilyDisplayTest.php:**
- `shows Child Account badge for user with parents`
- `does not show Child Account badge for user without parents`
- `shows Family card for user with parents`
- `shows Family card for user with children`
- `does not show Family card for user with no family links`
- `shows Parent Portal link in Family card for staff officers viewing parent profile`
- `hides Parent Portal link from non-staff`

**ProfilePhase3Test.php:**
- `shows age badge for staff viewing profile`
- `hides age badge for non-staff`
- `shows red badge for under-13`
- `shows blue badge for 13-16`
- `shows gray badge for adult`
- `shows parent portal link for officers on parent profile`
- `hides parent portal link for non-officers`
- `shows family cards on same row for user with family`

**DisciplineReportsCardTest.php:**
- `shows discipline reports card to staff on profile page`
- `shows discipline reports card to the subject user`
- `shows discipline reports card to parent of subject`
- `hides discipline reports card from unrelated users`
- `shows only published reports to non-staff users`
- `shows all reports including drafts to staff`
- `allows staff to create a report via modal`
- `allows officer to publish a draft report`
- `prevents non-officer from publishing`
- `allows creator to edit their draft report`
- `prevents editing of published reports`
- `shows risk score badge with correct color`

**ProfileUpdateTest.php:**
- `test_profile_page_is_displayed`
- `test_profile_information_can_be_updated`
- `test_email_verification_status_is_unchanged_when_email_address_is_unchanged`
- `test_user_can_delete_their_account`
- `test_correct_password_must_be_provided_to_delete_account`

**StaffBioTest.php:**
- `allows crew members to access the staff bio page`
- `allows officers to access the staff bio page`
- `denies jr crew from accessing the staff bio page`
- `denies regular users from accessing the staff bio page`
- `denies unauthenticated users from accessing the staff bio page`

**AvatarTest.php:**
- `returns null when auto preference and no linked accounts`
- `returns minecraft avatar in auto mode when MC account has avatar`
- `falls back to discord in auto mode when no MC avatar`
- `prefers minecraft over discord in auto mode`
- `returns minecraft avatar when preference is minecraft`
- `returns null when preference is minecraft but no MC account`
- `returns discord avatar when preference is discord`
- `returns gravatar URL when preference is gravatar`
- `skips inactive minecraft accounts in auto mode`
- `defaults to auto behavior for new users`
- `returns primary minecraft account avatar over non-primary`
- `falls back to any active account when primary has no avatar`

### Coverage Gaps

- **No tests for promote/demote flows** from the profile page component (only action-level tests may exist elsewhere)
- **No tests for brig put/release flows** from the profile page component
- **No tests for Minecraft account revoke/reactivate/force-delete** from the profile page
- **No tests for Discord account revoke** from the profile page
- **No tests for edit user modal** (saveEditUser method)
- **No tests for staff position assign/remove** from the profile page
- **No tests for the activity log modal** display and pagination
- **No tests for staff bio save/removePhoto functionality** (only access control is tested)
- **No test verifying the profile page route** returns 403 for unauthenticated users (though GuestRedirectTest may cover this generically)

---

## 17. File Map

**Models:**
- `app/Models/User.php`
- `app/Models/ActivityLog.php`
- `app/Models/MinecraftAccount.php`
- `app/Models/DiscordAccount.php`
- `app/Models/StaffPosition.php`
- `app/Models/DisciplineReport.php`
- `app/Models/ReportCategory.php`
- `app/Models/ParentChildLink.php`

**Enums:**
- `app/Enums/MembershipLevel.php`
- `app/Enums/StaffRank.php`
- `app/Enums/StaffDepartment.php`
- `app/Enums/BrigType.php`
- `app/Enums/MinecraftAccountStatus.php`
- `app/Enums/MinecraftAccountType.php`
- `app/Enums/DiscordAccountStatus.php`
- `app/Enums/ReportLocation.php`
- `app/Enums/ReportSeverity.php`
- `app/Enums/ReportStatus.php`

**Actions:**
- `app/Actions/PromoteUser.php`
- `app/Actions/DemoteUser.php`
- `app/Actions/PutUserInBrig.php`
- `app/Actions/ReleaseUserFromBrig.php`
- `app/Actions/AssignStaffPosition.php`
- `app/Actions/UnassignStaffPosition.php`
- `app/Actions/SetUsersStaffPosition.php`
- `app/Actions/RemoveUsersStaffPosition.php`
- `app/Actions/RevokeMinecraftAccount.php`
- `app/Actions/ReactivateMinecraftAccount.php`
- `app/Actions/ForceDeleteMinecraftAccount.php`
- `app/Actions/RevokeDiscordAccount.php`
- `app/Actions/RecordActivity.php`
- `app/Actions/SyncMinecraftRanks.php`
- `app/Actions/SyncDiscordRoles.php`
- `app/Actions/SyncMinecraftStaff.php`
- `app/Actions/SyncDiscordStaff.php`
- `app/Actions/AutoAssignPrimaryAccount.php`
- `app/Actions/CreateDisciplineReport.php`
- `app/Actions/UpdateDisciplineReport.php`
- `app/Actions/PublishDisciplineReport.php`

**Policies:**
- `app/Policies/UserPolicy.php`
- `app/Policies/MinecraftAccountPolicy.php`
- `app/Policies/StaffPositionPolicy.php`
- `app/Policies/DisciplineReportPolicy.php`

**Gates:** `app/Providers/AuthServiceProvider.php` -- gates: `manage-stowaway-users`, `view-user-discipline-reports`, `manage-discipline-reports`, `publish-discipline-reports`, `edit-staff-bio`, `view-community-content`, `view-activity-log`, `link-discord`, `link-minecraft-account`

**Notifications:**
- `app/Notifications/UserPromotedToStowawayNotification.php`
- `app/Notifications/UserPromotedToTravelerNotification.php`
- `app/Notifications/UserPromotedToResidentNotification.php`
- `app/Notifications/UserPutInBrigNotification.php`
- `app/Notifications/UserReleasedFromBrigNotification.php`

**Jobs:** None specific to this feature.

**Services:**
- `app/Services/MinecraftRconService.php`
- `app/Services/DiscordApiService.php`
- `app/Services/TicketNotificationService.php`

**Controllers:**
- `app/Http/Controllers/UserController.php`

**Volt Components:**
- `resources/views/livewire/users/display-basic-details.blade.php`
- `resources/views/livewire/users/display-activity-log.blade.php`
- `resources/views/livewire/users/discipline-reports-card.blade.php`
- `resources/views/livewire/settings/profile.blade.php`
- `resources/views/livewire/settings/staff-bio.blade.php`

**Blade Views & Components:**
- `resources/views/users/show.blade.php`
- `resources/views/components/minecraft/mc-account-detail-modal.blade.php`
- `resources/views/components/settings/layout.blade.php`
- `resources/views/partials/settings-heading.blade.php`

**Routes:**
- `profile.show` -- `GET /profile/{user}`
- `settings.profile` -- `GET /settings/profile`
- `settings.staff-bio` -- `GET /settings/staff-bio`
- `settings.minecraft-accounts` -- linked from profile MC section
- `settings.discord-account` -- linked from profile Discord section

**Migrations:**
- `database/migrations/0001_01_01_000000_create_users_table.php`
- Plus 17 additional user table migrations (brig fields, parental fields, staff bio, avatar preference, notification fields, etc.)

**Tests:**
- `tests/Feature/Policies/UserPolicyTest.php`
- `tests/Feature/Livewire/ProfileFamilyDisplayTest.php`
- `tests/Feature/Livewire/ProfilePhase3Test.php`
- `tests/Feature/Livewire/DisciplineReportsCardTest.php`
- `tests/Feature/Settings/ProfileUpdateTest.php`
- `tests/Feature/Settings/StaffBioTest.php`
- `tests/Feature/AvatarTest.php`

**Config:**
- `config/lighthouse.php` -- `max_minecraft_accounts`, Discord role IDs

---

## 18. Known Issues & Improvement Opportunities

1. **DemoteUser uses `RecordActivity::handle()` directly** instead of `RecordActivity::run()` -- inconsistent with the project's AsAction convention. The `DemoteUser` action also calls `RecordActivity::handle()` and `SyncDiscordRoles::run()` without error handling, unlike `PromoteUser` which wraps Discord sync in try/catch.

2. **Discord revoke on profile is admin-only with inline check** -- `revokeDiscordAccount()` in `display-basic-details` uses `Auth::user()->isAdmin()` directly instead of a policy method, which is inconsistent with the project's policy-based authorization pattern.

3. **Edit user modal lacks activity log detail** -- `saveEditUser()` records a generic "User profile updated." without specifying which fields changed. More detailed logging (e.g., "Name changed from X to Y") would improve audit trails.

4. **Missing eager loading for staff position in some paths** -- the `mount()` method loads `staffPosition`, but computed properties and some template checks may trigger additional queries.

5. **N+1 potential in discipline reports card** -- `getReportsProperty()` eager-loads `category` but not `reporter` or `publisher`, which are accessed in the view report modal.

6. **Settings profile page doesn't use RecordActivity** -- profile self-edits via `/settings/profile` don't log any activity, unlike admin edits via the profile page which log `update_profile`.

7. **Test coverage is thin for profile admin actions** -- promote, demote, brig, MC/Discord account management, edit user, and staff position flows from the profile component have no Livewire-level tests. Only the policy and display tests exist.

8. **`PutUserInBrig` uses `RecordActivity::handle()`** instead of `::run()` -- same inconsistency as DemoteUser.

9. **Stale `UserController` scaffold methods** -- the `index()`, `create()`, `store()`, `edit()`, `update()`, `destroy()` methods on UserController are empty stubs that could be removed to reduce dead code.

10. **Missing `@can` gate for brig actions in template** -- brig actions use `Auth::user()->can('manage-stowaway-users')` in PHP methods but the template uses `@can('manage-stowaway-users')` Blade directives, which is correct but the PHP methods don't use `$this->authorize()` pattern consistently.
