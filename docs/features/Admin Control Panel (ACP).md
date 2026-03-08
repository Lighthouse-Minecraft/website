# Admin Control Panel (ACP) -- Technical Documentation

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

The Admin Control Panel (ACP) is the central administrative hub of the Lighthouse Website. It provides a tabbed interface for managing users, content, logs, and configuration. The ACP itself is a **container feature** — it does not have its own models or database tables. Instead, it organizes and provides access to 14 embedded child Livewire components, each managing a different domain of the application.

The ACP is accessible to Admins, staff members at CrewMember rank or above, Page Editors, and Engineering department members. The specific tabs visible to a user depend on their individual permissions — the component dynamically shows only the categories and sub-tabs the user is authorized to access.

The ACP is organized into four top-level categories:
- **Users** — manage site users, Minecraft accounts, and Discord accounts
- **Content** — manage CMS pages, announcements, meetings, staff positions, and board members
- **Logs** — view Minecraft command logs, Discord API logs, activity logs, and discipline report logs
- **Config** — manage roles, report categories, and prayer nations

Each child component within the ACP is documented in its own feature documentation file where applicable (e.g., Discipline Reports, Staff Positions & Departments, Board Members, CMS Pages, Prayer Tracking). This document focuses on the ACP container itself and summarizes each embedded component.

---

## 2. Database Schema

The ACP does not have its own database tables. It serves as a container for child components that interact with their own respective tables (users, minecraft_accounts, discord_accounts, announcements, pages, meetings, staff_positions, board_members, roles, minecraft_command_logs, discord_api_logs, activity_logs, discipline_reports, report_categories, prayer_countries).

---

## 3. Models & Relationships

The ACP container does not define its own models. The child components reference the following models:

| Child Component | Primary Model(s) |
|----------------|-------------------|
| User Manager | `User`, `Role`, `DisciplineReport` |
| MC User Manager | `MinecraftAccount`, `User` |
| Discord User Manager | `DiscordAccount`, `User` |
| MC Command Log | `MinecraftCommandLog`, `MinecraftAccount` |
| Discord API Log | `DiscordApiLog` |
| Activity Log | `ActivityLog`, `User`, `Thread` |
| Page Manager | `Page` |
| Announcement Manager | `Announcement` |
| Meeting Manager | `Meeting` |
| Staff Position Manager | `StaffPosition` |
| Board Member Manager | `BoardMember`, `User` |
| Role Manager | `Role` |
| Report Category Manager | `ReportCategory` |
| Prayer Manager | `PrayerCountry` |

---

## 4. Enums Reference

### StaffRank (`app/Enums/StaffRank.php`)

Used by the `view-acp` gate and log-viewing gates:

| Case | Value | Label |
|------|-------|-------|
| None | 0 | None |
| JrCrew | 1 | Jr. Crew |
| CrewMember | 2 | Crew Member |
| Officer | 3 | Officer |

### StaffDepartment (`app/Enums/StaffDepartment.php`)

Used by the `view-acp` gate (Engineering department grants access):

| Case | Notes |
|------|-------|
| Command | Command department |
| Chaplain | Chaplain department |
| Engineer | Engineer department — grants ACP access even at JrCrew |
| Quartermaster | Quartermaster department |
| Steward | Steward department |

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

| Gate Name | Who Can Pass | Logic Summary |
|-----------|-------------|---------------|
| `view-acp` | Admin, CrewMember+, Page Editor role, Engineer dept (any rank) | Main ACP entry gate |
| `view-mc-command-log` | Admin, Officer+, Engineer dept (any rank) | Log viewing |
| `view-discord-api-log` | Admin, Officer+, Engineer dept (any rank) | Log viewing |
| `view-activity-log` | Admin, Officer+, Engineer dept (any rank) | Log viewing |
| `view-discipline-report-log` | Admin, Officer+, Engineer dept (any rank) | Log viewing |

All four log gates share the same closure (`$canViewLogs`).

### Category Visibility Logic

The tabs component dynamically determines which categories to show based on the user's permissions:

| Category | Visible When User Can... |
|----------|-------------------------|
| Users | `viewAny` User, MinecraftAccount, or DiscordAccount |
| Content | `viewAny` Page, Announcement, Meeting, StaffPosition, or BoardMember |
| Logs | Pass any of the four log gates |
| Config | `viewAny` Role, ReportCategory, or PrayerCountry |

### Policies Referenced by Child Components

| Policy | Used By Tab | Key Abilities |
|--------|-------------|---------------|
| `UserPolicy` | User Manager | `viewAny`, `update` |
| `MinecraftAccountPolicy` | MC User Manager | `viewAny`, `reactivate`, `forceDelete` |
| `DiscordAccountPolicy` | Discord User Manager | `viewAny` |
| `PagePolicy` | Page Manager | `viewAny` |
| `AnnouncementPolicy` | Announcement Manager | `viewAny`, `create`, `update`, `delete` |
| `MeetingPolicy` | Meeting Manager | `viewAnyPrivate`, `viewAnyPublic`, `view` |
| `StaffPositionPolicy` | Staff Position Manager | `viewAny` |
| `BoardMemberPolicy` | Board Member Manager | `viewAny`, `create`, `update`, `delete` |
| `RolePolicy` | Role Manager | `viewAny`, `create`, `update` |
| `ReportCategoryPolicy` | Report Category Manager | `viewAny` |
| `PrayerCountryPolicy` | Prayer Manager | `viewAny`, `create`, `update` |

### Permissions Matrix

| User Type | View ACP | Users Tab | Content Tab | Logs Tab | Config Tab |
|-----------|----------|-----------|-------------|----------|------------|
| Regular User | No | No | No | No | No |
| Page Editor (role) | Yes | No | Yes (Pages) | No | No |
| Engineering JrCrew | Yes | No | Yes | Yes | No |
| Engineering CrewMember+ | Yes | Yes | Yes | Yes | Yes* |
| Non-Engineering CrewMember | Yes | Yes | Yes | No | Yes* |
| Any Officer | Yes | Yes | Yes | Yes | Yes |
| Admin | Yes | Yes | Yes | Yes | Yes |

*Config visibility depends on specific policy `viewAny` checks (e.g., ReportCategoryPolicy, RolePolicy).

---

## 6. Routes

| Method | URL | Middleware | Handler | Route Name |
|--------|-----|-----------|---------|------------|
| GET | `/acp` | `auth` | `AdminControlPanelController@index` | `acp.index` |

The ACP uses URL-bound query parameters (`?category=logs&tab=activity-log`) for deep linking to specific tabs, handled by Livewire's `#[Url]` attribute on the `$category` and `$tab` properties.

Additional routes used by child components (navigated from within ACP):

| Method | URL | Handler | Route Name |
|--------|-----|---------|------------|
| GET | `/acp/pages/create` | Page create controller | `admin.pages.create` |
| GET | `/acp/pages/{page}/edit` | Page edit controller | `admin.pages.edit` |

---

## 7. User Interface Components

### Admin Control Panel Tabs (Container)
**File:** `resources/views/livewire/admin-control-panel-tabs.blade.php`
**Route:** `/acp` (route name: `acp.index`)

**Purpose:** Top-level tabbed container that organizes all ACP functionality into 4 categories with sub-tabs.

**Authorization:** `view-acp` gate (checked in `AdminControlPanelController@index`)

**PHP Properties:**
- `$category` (string, URL-bound, default: `'users'`) — active top-level category
- `$tab` (string, URL-bound, default: `'user-manager'`) — active sub-tab

**Key Methods:**
- `mount()` — if the user's current category has no visible tabs, auto-selects the first visible category
- `updatedCategory($value)` — resets sub-tab to the default for the new category
- `hasUsersTabs()`, `hasContentTabs()`, `hasLogsTabs()`, `hasConfigTabs()` — permission checks for category visibility
- `defaultTabFor($category)` — returns the first permitted sub-tab for a given category

**UI Elements:**
- Segmented top-level tabs (Users, Content, Logs, Config)
- Segmented sub-tabs within each category (smaller variant)
- Each tab panel embeds a child Livewire component, wrapped in `@can` directives

### Embedded Child Components Summary

#### Users Category

| Sub-Tab | Component | Lines | Key Features |
|---------|-----------|-------|--------------|
| Users | `admin-manage-users-page` | 304 | Search, brig filter, sortable table, edit modal with role assignment, risk score display |
| MC Users | `admin-manage-mc-users-page` | 180 | Search by username/UUID, account detail modal, reactivate/force-delete actions |
| Discord Users | `admin-manage-discord-users-page` | 102 | Search, sortable table with avatars, read-only view |

#### Content Category

| Sub-Tab | Component | Lines | Key Features |
|---------|-----------|-------|--------------|
| Pages | `admin-manage-pages-page` | 46 | Simple table, links to external create/edit routes |
| Announcements | `admin-manage-announcements-page` | 383 | CRUD with scheduling, expiry, markdown preview, timezone conversion |
| Meetings | `meetings.list` | 68 | Gate-filtered list, status badges, create modal |
| Staff Positions | `admin-manage-staff-positions-page` | — | Staff position CRUD (documented in Staff Positions & Departments feature) |
| Board Members | `admin-manage-board-members-page` | 329 | CRUD with photo upload, user linking/unlinking, search |

#### Logs Category

| Sub-Tab | Component | Lines | Key Features |
|---------|-----------|-------|--------------|
| MC Command Log | `admin-manage-mc-command-log-page` | 197 | Search, status/type filters, clickable targets, response/error display |
| Discord API Log | `admin-manage-discord-api-log-page` | 156 | Search, status/type filters, color-coded action badges, HTTP status display |
| Activity Log | `admin-manage-activity-log-page` | 126 | Action filter dropdown, polymorphic subject links, causer display |
| Reports Log | `admin-manage-discipline-reports-page` | — | Discipline report log (documented in Discipline Reports feature) |

#### Config Category

| Sub-Tab | Component | Lines | Key Features |
|---------|-----------|-------|--------------|
| Roles | `admin-manage-roles-page` | 203 | CRUD with 150+ icon choices, color customization |
| Report Categories | `admin-manage-report-categories-page` | — | Category CRUD (documented in Discipline Reports feature) |
| Prayer Nations | `prayer.manage-months` | 173 | Month-based calendar, day-level prayer data entry, caching |

---

## 8. Actions (Business Logic)

The ACP container does not define its own actions. Actions called by child components include:

### Actions Used by User Manager
- Direct model updates only (no action classes)

### Actions Used by MC User Manager
- `ReactivateMinecraftAccount::run($account, $user)` — reactivates a deactivated MC account
- `ForceDeleteMinecraftAccount::run($account, $user)` — permanently removes an MC account

### Actions Used by Announcement Manager
- `RecordActivity::run($announcement, 'announcement_created|updated|deleted', 'Description.')` — activity logging

### Actions Used by Board Member Manager
- `CreateBoardMember::run($displayName, $title, $bio, $photoPath, $sortOrder)`
- `UpdateBoardMember::run($boardMember, $displayName, $title, $bio, $photoPath, $sortOrder)`
- `DeleteBoardMember::run($boardMember)`
- `LinkBoardMemberToUser::run($boardMember, $user)`
- `UnlinkBoardMemberFromUser::run($boardMember)`

---

## 9. Notifications

Not applicable for the ACP container feature. Individual child components may trigger notifications documented in their respective feature docs.

---

## 10. Background Jobs

Not applicable for this feature.

---

## 11. Console Commands & Scheduled Tasks

Not applicable for this feature.

---

## 12. Services

Not applicable for the ACP container. Child components may use services documented in their respective feature docs.

---

## 13. Activity Log Entries

Activity entries logged by ACP child components:

| Action String | Logged By | Subject Model | Description |
|---------------|-----------|---------------|-------------|
| `announcement_created` | Announcement Manager | `Announcement` | "Announcement created." |
| `announcement_updated` | Announcement Manager | `Announcement` | "Announcement updated." |
| `announcement_deleted` | Announcement Manager | `Announcement` | "Announcement deleted." |

Other child components (Board Members, MC accounts, etc.) log activity through their action classes, documented in their respective feature docs.

---

## 14. Data Flow Diagrams

### Accessing the ACP

```
User navigates to /acp
  -> GET /acp (middleware: auth)
    -> AdminControlPanelController@index
      -> Gate::authorize('view-acp')  [fails → 403]
      -> return view('admin.control-panel.index')
        -> <livewire:admin-control-panel-tabs />
          -> mount(): check current category visibility
          -> auto-redirect to first visible category if needed
          -> render category tabs (filtered by has*Tabs() checks)
          -> render sub-tabs for active category (filtered by @can)
          -> embed child Livewire component for active tab
```

### Switching Categories

```
User clicks category tab (e.g., "Logs")
  -> wire:model.live="category" updates to "logs"
    -> updatedCategory('logs') fires
      -> defaultTabFor('logs') returns first permitted log tab
      -> $tab set to default (e.g., 'mc-command-log')
    -> URL updates to ?category=logs&tab=mc-command-log
    -> Logs sub-tabs render (filtered by @can gates)
    -> Active tab panel renders child component
```

### Editing a User (User Manager)

```
Admin clicks edit icon on user row
  -> openEditModal($userId) fires
    -> $this->authorize('update', $user)
    -> Load user data and roles into form state
    -> Flux::modal('edit-user-modal')->show()
User fills form and clicks Save
  -> saveUser() fires
    -> $this->authorize('update', $user)
    -> validate(name, email, date_of_birth, parent_email)
    -> $user->update($editUserData)
    -> Sync roles (prevent non-admin from assigning Admin role)
    -> Flux::modal('edit-user-modal')->close()
    -> Flux::toast('User updated successfully')
```

### Creating an Announcement

```
Staff clicks "Create Announcement" button
  -> @can('create', Announcement::class) check passes
  -> Flux::modal('create-announcement-modal')->show()
User fills form and clicks Create
  -> createAnnouncement() fires
    -> $this->authorize('create', Announcement::class)
    -> validate(title required, content required, dates)
    -> Convert user timezone dates to UTC
    -> Announcement::create([...])
    -> RecordActivity::run($announcement, 'announcement_created', 'Announcement created.')
    -> Flux::modal('create-announcement-modal')->close()
    -> Flux::toast('Announcement created!')
```

### Managing Board Members

```
Admin clicks "Add Board Member"
  -> Flux::modal('create-board-member-modal')->show()
Admin fills form with name, title, photo, bio, sort order
  -> createBoardMember() fires
    -> $this->authorize('create', BoardMember::class)
    -> validate(displayName, title, bio, sortOrder)
    -> Store uploaded photo to public storage
    -> CreateBoardMember::run($displayName, $title, $bio, $photoPath, $sortOrder)
    -> Flux::toast('Board member created!')

Admin clicks "Link User" on a board member row
  -> openLinkModal($id) fires
  -> Admin searches for user by name, selects from results
  -> linkUser() fires
    -> LinkBoardMemberToUser::run($boardMember, $user)
    -> User's is_board_member set to true
    -> Flux::toast('User linked!')
```

---

## 15. Configuration

Not applicable for the ACP container. The `view-acp` gate logic is defined in `AuthServiceProvider` and does not reference config values.

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/Livewire/AdminControlPanelTabsTest.php` | 13 tests | Tab switching, category visibility, permission filtering, defaults |
| `tests/Feature/Auth/AcpTabPermissionsTest.php` | 14 tests | Gate access for view-acp, log gates, viewAny policies |

### Test Case Inventory

#### `AdminControlPanelTabsTest.php`

1. `it renders with default category and tab when user has all permissions` — admin sees all 4 categories, defaults to users/user-manager
2. `it switches categories correctly` — setting category to 'logs' updates tab to mc-command-log
3. `it resets sub-tab when category changes` — switching categories resets to default sub-tab
4. `it switches sub-tabs within a category` — can set tab independently within a category
5. `it allows setting category and tab independently` — category and tab can be set in separate actions
6. `it shows only categories the user has access to` — Engineer Officer sees all 4 categories
7. `it defaults to content category when user lacks users permissions` — Engineering JrCrew defaults to content
8. `it shows only content for users with no staff position` — regular user only sees Content (via AnnouncementPolicy)
9. `it includes child livewire components in tab panels` — admin can render successfully
10. `it handles unauthenticated users gracefully` — no categories visible without auth
11. `it shows only content category for page editor role` — Page Editor role only sees Content
12. `it respects command department officer permissions for all categories` — Command Officer sees all 4
13. `it maintains wrapper div classes` — verifies CSS classes on wrapper div

#### `AcpTabPermissionsTest.php`

1. `engineering jr crew can pass view-mc-command-log gate`
2. `engineering jr crew can pass view-activity-log gate`
3. `any officer can pass view-mc-command-log gate`
4. `any officer can pass view-activity-log gate`
5. `engineering jr crew can pass view-discord-api-log gate`
6. `any officer can pass view-discord-api-log gate`
7. `non-engineering non-officer is denied discord api log gate`
8. `non-engineering non-officer is denied mc command log gate`
9. `non-engineering non-officer is denied activity log gate`
10. `any officer can viewAny minecraft accounts`
11. `any officer can viewAny discord accounts`
12. `crew member from non-engineering dept cannot viewAny minecraft accounts`
13. `engineering staff can pass view-acp gate`
14. `regular user without staff position cannot pass view-acp gate`

### Coverage Gaps

- No tests for the `view-discipline-report-log` gate (added alongside the other log gates but not tested)
- No tests for Page Editor role accessing the `view-acp` gate (tested in tabs component but not in the gate test file)
- No tests for Admin role accessing the `view-acp` gate
- No integration tests for individual child components within the ACP context (each child component may have its own tests)
- No test for deep-link URL parameter behavior (e.g., navigating directly to `?category=logs&tab=activity-log`)

---

## 17. File Map

**Models:** None specific to ACP (container only)

**Enums:**
- `app/Enums/StaffRank.php` — used in gate logic
- `app/Enums/StaffDepartment.php` — used in gate logic

**Actions:** None specific to ACP (child components use their own actions)

**Policies:** None specific to ACP (child components reference their own policies)

**Gates:** `app/Providers/AuthServiceProvider.php` — gates: `view-acp`, `view-mc-command-log`, `view-discord-api-log`, `view-activity-log`, `view-discipline-report-log`

**Notifications:** None

**Jobs:** None

**Services:** None

**Controllers:**
- `app/Http/Controllers/AdminControlPanelController.php`

**Volt Components:**
- `resources/views/livewire/admin-control-panel-tabs.blade.php` (container)
- `resources/views/livewire/admin-manage-users-page.blade.php`
- `resources/views/livewire/admin-manage-mc-users-page.blade.php`
- `resources/views/livewire/admin-manage-discord-users-page.blade.php`
- `resources/views/livewire/admin-manage-mc-command-log-page.blade.php`
- `resources/views/livewire/admin-manage-discord-api-log-page.blade.php`
- `resources/views/livewire/admin-manage-activity-log-page.blade.php`
- `resources/views/livewire/admin-manage-roles-page.blade.php`
- `resources/views/livewire/admin-manage-announcements-page.blade.php`
- `resources/views/livewire/admin-manage-board-members-page.blade.php`
- `resources/views/livewire/admin-manage-pages-page.blade.php`
- `resources/views/livewire/meetings/list.blade.php`
- `resources/views/livewire/prayer/manage-months.blade.php`
- `resources/views/livewire/admin-manage-staff-positions-page.blade.php`
- `resources/views/livewire/admin-manage-discipline-reports-page.blade.php`
- `resources/views/livewire/admin-manage-report-categories-page.blade.php`

**Views:**
- `resources/views/admin/control-panel/index.blade.php`

**Routes:**
- `acp.index` — `GET /acp`

**Migrations:** None specific to ACP

**Tests:**
- `tests/Feature/Livewire/AdminControlPanelTabsTest.php`
- `tests/Feature/Auth/AcpTabPermissionsTest.php`

**Config:** None

**Other:**
- Sidebar navigation entry gated by `@can('view-acp')` in the app layout

---

## 18. Known Issues & Improvement Opportunities

1. **Missing `view-discipline-report-log` gate test** — The gate is defined alongside the other three log gates using the same `$canViewLogs` closure, but `AcpTabPermissionsTest.php` does not include any tests for this gate specifically.

2. **Activity log component has no authorization check** — `admin-manage-activity-log-page.blade.php` does not call `$this->authorize()` in its `mount()` method. It relies entirely on the parent tab component's `@can('view-activity-log')` directive. If the component were ever mounted outside the ACP, it would be accessible without authorization.

3. **MC command log authorization inconsistency** — The `showAccount()` method in `admin-manage-mc-command-log-page.blade.php` authorizes against `MinecraftAccount::class` policy (`viewAny`) rather than the `view-mc-command-log` gate, mixing authorization strategies.

4. **User Manager uses direct model updates** — Unlike other components that use action classes (e.g., Board Member Manager uses `CreateBoardMember::run()`), the User Manager directly calls `$user->update()` and `$user->roles()->sync()`. This bypasses activity logging and any future business logic hooks.

5. **Page Manager has no inline authorization** — `admin-manage-pages-page.blade.php` loads all pages without any `$this->authorize()` call, relying entirely on the parent tab's `@can` directive.

6. **Hardcoded icon list** — `admin-manage-roles-page.blade.php` contains a hardcoded array of 150+ Heroicon names. This could become outdated if the icon library is updated.

7. **Inconsistent pagination** — User Manager uses 15 per page, log components use 25, announcements use 10. While not a bug, standardizing would improve consistency.

8. **No audit trail for user edits** — When an admin edits a user via the User Manager (changing name, email, roles, date of birth), no `RecordActivity::run()` call is made. Contrast this with announcements, which log creation, update, and deletion.

9. **Default category fallback** — If a user has no permissions at all, the `mount()` method iterates through all categories but doesn't handle the edge case where no category is visible. The component still renders but with an empty tab bar. The `view-acp` gate should prevent this scenario, but the component does not enforce the gate itself.
