# Staff Page -- Technical Documentation

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

The Staff Page is a public-facing page (`/staff`) that displays the community's organizational structure. It shows all staff positions grouped by department (Command, Chaplain, Engineer, Quartermaster, Steward), with Officers displayed in larger cards above crew members. Each department section shows both filled and vacant positions. Below the staff departments, a "Board of Directors" section displays board members.

The page is read-only and publicly accessible (no authentication required). When a position or board member is clicked, a detail panel slides in on the right side showing the person's photo, name, title, rank badge, department badge, bio, responsibilities, and — for authorized viewers — contact phone number. JrCrew members have reduced information visibility: their staff photo is not shown (avatar only), and their real name and bio are hidden.

A companion feature is the **Staff Bio** settings page (`/settings/staff-bio`), where staff members at CrewMember rank or above (and board members) can edit their own public profile: first name, last initial, bio, phone number, and photo. This information feeds into both the Staff Page and the Board Members feature.

---

## 2. Database Schema

The Staff Page primarily reads from the `staff_positions`, `board_members`, and `users` tables. The relevant user fields for staff display are:

### `users` table (staff bio columns)

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `staff_first_name` | string | Yes | NULL | Public first name for staff display |
| `staff_last_initial` | string(1) | Yes | NULL | Last initial, stored uppercase |
| `staff_bio` | text | Yes | NULL | Staff bio text |
| `staff_phone` | string(30) | Yes | NULL | Contact phone (protected, Officer/Board-only visible) |
| `staff_photo_path` | string | Yes | NULL | Path to uploaded staff photo in public storage |

**Migration(s):**
- `database/migrations/2026_03_03_000002_add_staff_bio_fields_to_users_table.php` — adds `staff_first_name`, `staff_last_initial`, `staff_bio`, `staff_photo_path`
- `database/migrations/2026_03_04_170011_add_staff_phone_to_users_table.php` — adds `staff_phone`

For the `staff_positions` and `board_members` table schemas, see the **Staff Positions & Departments** and **Board Members** feature documentation respectively.

---

## 3. Models & Relationships

### User (`app/Models/User.php`) — staff display fields

**Key Methods:**
- `staffPhotoUrl(): ?string` — returns `asset('storage/'.$this->staff_photo_path)` if `staff_photo_path` is set, otherwise null
- `isJrCrew(): bool` — returns true if user is at JrCrew rank (used to restrict display of personal info)

**Fillable fields:** `staff_first_name`, `staff_last_initial`, `staff_bio`, `staff_phone`, `staff_photo_path`

### StaffPosition (`app/Models/StaffPosition.php`)

Used by the staff page to load positions grouped by department. Key methods used:
- `isFilled(): bool` — whether a user is assigned
- `isVacant(): bool` — opposite of isFilled
- Scopes: `filled()`, `inDepartment()`, `ordered()`
- Relationships: `user()` (belongsTo User)

See **Staff Positions & Departments** feature documentation for full model details.

### BoardMember (`app/Models/BoardMember.php`)

Used by the staff page for the Board of Directors section. Key methods used:
- `effectiveName(): string` — linked user's staff name or display_name
- `effectiveBio(): ?string` — linked user's bio or own bio
- `effectivePhotoUrl(): ?string` — linked user's photo or own photo
- `isLinked(): bool` — whether linked to a user
- Scope: `ordered()`

See **Board Members** feature documentation for full model details.

---

## 4. Enums Reference

### StaffDepartment (`app/Enums/StaffDepartment.php`)

| Case | Label |
|------|-------|
| Command | Command |
| Chaplain | Chaplain |
| Engineer | Engineer |
| Quartermaster | Quartermaster |
| Steward | Steward |

Used to group positions by department on the staff page. `StaffDepartment::cases()` iterates all departments.

### StaffRank (`app/Enums/StaffRank.php`)

| Case | Value | Label | Notes |
|------|-------|-------|-------|
| None | 0 | None | — |
| JrCrew | 1 | Jr. Crew | Reduced info display on staff page |
| CrewMember | 2 | Crew Member | Can edit staff bio |
| Officer | 3 | Officer | Displayed above crew in department sections |

Methods used: `label()`, `color()` (for rank badges in detail panel).

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

| Gate Name | Who Can Pass | Logic Summary |
|-----------|-------------|---------------|
| `edit-staff-bio` | CrewMember+ OR board members | `$user->isAtLeastRank(StaffRank::CrewMember) \|\| $user->is_board_member` |

### Policies

#### UserPolicy — `viewStaffPhone` (`app/Policies/UserPolicy.php`)

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `viewStaffPhone` | Officer+ or board member viewing a staff/board member | Actor must be Officer+ or board member AND target must be JrCrew+ or board member |

### Permissions Matrix

| User Type | View Staff Page | View Detail Panel | See Staff Phone | Edit Own Staff Bio |
|-----------|----------------|-------------------|-----------------|-------------------|
| Unauthenticated | Yes | Yes (click) | No | No |
| Regular User | Yes | Yes | No | No |
| JrCrew | Yes | Yes | No | No |
| CrewMember | Yes | Yes | No | Yes |
| Officer | Yes | Yes | Yes (staff/board targets only) | Yes |
| Board Member (non-staff) | Yes | Yes | Yes (staff/board targets only) | Yes |
| Admin | Yes | Yes | Yes | Yes |

---

## 6. Routes

| Method | URL | Middleware | Handler | Route Name |
|--------|-----|-----------|---------|------------|
| GET | `/staff` | (none — public) | `Volt::route('staff.page')` | `staff.index` |
| GET | `/settings/staff-bio` | auth | `Volt::route('settings.staff-bio')` | `settings.staff-bio` |

---

## 7. User Interface Components

### Staff Page
**File:** `resources/views/livewire/staff/page.blade.php`
**Route:** `/staff` (route name: `staff.index`)

**Purpose:** Public directory of all staff members organized by department, plus Board of Directors.

**Authorization:** None (publicly accessible)

**PHP Properties:**
- `$selectedPositionId` (int|null) — currently selected staff position
- `$selectedBoardMemberId` (int|null) — currently selected board member

**Key Methods/Computed Properties:**
- `mount()` — selects first filled Command Officer position as default, falls back to any first filled position
- `selectPosition($id)` — selects a staff position, clears board member selection
- `selectBoardMember($id)` — selects a board member, clears position selection
- `departments` (computed) — groups all positions by StaffDepartment with officers and crew separated; eager-loads `user.minecraftAccounts`, `user.discordAccounts`
- `selectedPosition` (computed) — loads selected position with user relations
- `boardMembers` (computed) — loads all board members ordered, with user relations
- `selectedBoardMember` (computed) — finds selected board member from loaded collection

**UI Layout:**
- Two-column layout: staff directory (2/3 width) + detail panel (1/4 width, sticky)
- **Staff Directory (left):**
  - For each department with positions:
    - Department heading with `department.label()` + "Department"
    - Officers in 2-4 column grid with larger cards (20x20 photos)
    - Crew (CrewMember + JrCrew) in 2-4 column grid with smaller cards (16x16 photos)
    - Each card shows: photo (staff photo for non-JrCrew, avatar otherwise), name, title
    - Vacant positions show "Open Position" with title
    - Cards are clickable with keyboard accessibility (enter, space)
  - "Board of Directors" section:
    - Grid of board member cards with `effectivePhotoUrl()`, `effectiveName()`, title
  - Empty state: "No staff positions have been configured yet."

- **Detail Panel (right, sticky):**
  - **For staff positions:**
    - Photo (staff photo for non-JrCrew, avatar otherwise)
    - Real name (first name + last initial, hidden for JrCrew)
    - Profile link (user's site username)
    - Title, rank badge (colored), department badge
    - Position description
    - Bio (hidden for JrCrew)
    - Responsibilities
    - Phone number (gated by `@can('viewStaffPhone', $user)`)
    - Requirements (shown only for vacant positions)
  - **For board members:**
    - Photo (staff photo, avatar, or own photo)
    - Effective name
    - Profile link (if linked to user)
    - Title badge
    - Bio
    - Phone number (gated by `@can('viewStaffPhone', $user)`, only if linked)
  - **Default state:** "Select a staff member to view their details."

### Staff Bio Settings
**File:** `resources/views/livewire/settings/staff-bio.blade.php`
**Route:** `/settings/staff-bio` (route name: `settings.staff-bio`)

**Purpose:** Allows staff members and board members to edit their public staff profile.

**Authorization:** `$this->authorize('edit-staff-bio')` in `mount()` and `save()`

**PHP Properties:**
- `$firstName` (string) — staff first name
- `$lastInitial` (string) — staff last initial (single letter)
- `$bio` (string) — staff bio text
- `$phone` (string) — contact phone number
- `$photo` — uploaded photo file (Livewire file upload)
- `$existingPhotoUrl` (string|null) — current staff photo URL

**Key Methods:**
- `mount()` — authorizes, loads current user's staff bio fields
- `save()` — authorizes, validates, handles photo upload/replacement, saves all fields to user
- `removePhoto()` — authorizes, deletes photo from storage, clears `staff_photo_path`

**Validation Rules:**
- `firstName` — nullable, string, max 50
- `lastInitial` — nullable, string, max 1, alpha only
- `bio` — nullable, string, max 2000
- `phone` — nullable, string, 10-30 chars, regex for phone format
- `photo` — nullable, image, max 2048 KB

**UI Elements:**
- Settings page layout with "Staff Bio" heading
- Current photo display with "Remove Photo" button
- File upload input for new photo
- First Name input
- Last Initial input (max 1 character)
- Bio textarea (5 rows)
- Phone number input with privacy note ("only visible to Officers and Board Members")
- "Save Staff Bio" submit button

---

## 8. Actions (Business Logic)

Not applicable for this feature. The Staff Page is read-only, and the Staff Bio settings page uses direct model updates rather than action classes.

---

## 9. Notifications

Not applicable for this feature.

---

## 10. Background Jobs

Not applicable for this feature.

---

## 11. Console Commands & Scheduled Tasks

Not applicable for this feature.

---

## 12. Services

Not applicable for this feature.

---

## 13. Activity Log Entries

Not applicable for this feature. The Staff Bio settings page does not call `RecordActivity::run()`.

---

## 14. Data Flow Diagrams

### Viewing the Staff Page

```
User navigates to /staff (no auth required)
  -> GET /staff
    -> Volt component staff.page mounts
      -> mount(): find default selected position (first filled Command Officer, or first filled)
      -> departments (computed): load all StaffPositions with user relations, group by department
      -> boardMembers (computed): load all BoardMembers ordered with user relations
    -> Render department sections with position cards
    -> Render Board of Directors section
    -> Render detail panel for default selected position
```

### Selecting a Staff Member

```
User clicks a staff position card
  -> selectPosition($id) fires
    -> $selectedPositionId = $id
    -> $selectedBoardMemberId = null
    -> selectedPosition (computed) reloads with user.minecraftAccounts, user.discordAccounts
    -> Detail panel re-renders with selected position's info
    -> JrCrew check: if user.isJrCrew(), hide staff photo, real name, bio
    -> Phone: @can('viewStaffPhone', $user) check determines phone visibility
```

### Editing Staff Bio

```
Staff member navigates to /settings/staff-bio
  -> GET /settings/staff-bio (middleware: auth)
    -> mount()
      -> $this->authorize('edit-staff-bio') — must be CrewMember+ or board member
      -> Load current values from user model

User fills form and clicks "Save Staff Bio"
  -> save() fires
    -> $this->authorize('edit-staff-bio')
    -> validate(firstName, lastInitial, bio, phone, photo)
    -> If photo uploaded:
      -> Delete old photo from public storage if exists
      -> Store new photo to 'staff-photos' directory on public disk
      -> Update user.staff_photo_path
    -> Update user: staff_first_name, staff_last_initial (uppercased), staff_bio, staff_phone
    -> Refresh existingPhotoUrl
    -> Flux::toast('Staff bio updated successfully.', 'Saved', variant: 'success')
```

### Removing Staff Photo

```
Staff member clicks "Remove Photo" on staff bio settings
  -> removePhoto() fires
    -> $this->authorize('edit-staff-bio')
    -> If user has staff_photo_path:
      -> Delete photo file from public storage
      -> Set user.staff_photo_path = null
      -> Set existingPhotoUrl = null
    -> Flux::toast('Photo removed.', 'Done', variant: 'success')
```

---

## 15. Configuration

Not applicable for this feature.

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/StaffPageTest.php` | 9 tests | Public staff page rendering, departments, positions, board members |
| `tests/Feature/Settings/StaffBioTest.php` | 5 tests | Staff bio settings access control |

### Test Case Inventory

#### `StaffPageTest.php`

1. `loads the public staff page without authentication`
2. `displays filled positions with user names`
3. `displays vacant positions as open`
4. `groups positions by department`
5. `does not display departments with no positions`
6. `displays board members section on the public page`
7. `does not display board members section when no board members exist`
8. `shows linked board member with user staff name`
9. `shows unlinked board member with display name`

#### `StaffBioTest.php`

1. `allows crew members to access the staff bio page`
2. `allows officers to access the staff bio page`
3. `denies jr crew from accessing the staff bio page`
4. `denies regular users from accessing the staff bio page`
5. `denies unauthenticated users from accessing the staff bio page`

### Coverage Gaps

- **No test for saving staff bio** — `StaffBioTest` only tests access control (who can reach the page), not the actual save functionality, validation, or photo upload.
- **No test for removing staff photo** — the `removePhoto()` method is untested.
- **No test for `viewStaffPhone` policy** — whether phone numbers are visible/hidden in the detail panel based on the viewer's permissions is untested.
- **No test for JrCrew information hiding** — the logic that hides staff photos, real names, and bios for JrCrew members is untested.
- **No test for detail panel interaction** — clicking a position/board member to view details is untested (only the list rendering is tested).
- **No test for board members** — `StaffBioTest` doesn't test whether board members (who are not staff) can access the bio page via the `edit-staff-bio` gate.
- **No test for default selection logic** — `mount()` selecting the first Command Officer as default is untested.

---

## 17. File Map

**Models:**
- `app/Models/User.php` (staff bio fields: `staff_first_name`, `staff_last_initial`, `staff_bio`, `staff_phone`, `staff_photo_path`)
- `app/Models/StaffPosition.php` (used for department display)
- `app/Models/BoardMember.php` (used for Board of Directors section)

**Enums:**
- `app/Enums/StaffDepartment.php`
- `app/Enums/StaffRank.php`

**Actions:** None

**Policies:**
- `app/Policies/UserPolicy.php` — method: `viewStaffPhone`

**Gates:** `app/Providers/AuthServiceProvider.php` — gates: `edit-staff-bio`

**Notifications:** None

**Jobs:** None

**Services:** None

**Controllers:** None

**Volt Components:**
- `resources/views/livewire/staff/page.blade.php` (public staff directory)
- `resources/views/livewire/settings/staff-bio.blade.php` (staff bio editing)

**Routes:**
- `staff.index` — `GET /staff`
- `settings.staff-bio` — `GET /settings/staff-bio`

**Migrations:**
- `database/migrations/2026_03_03_000002_add_staff_bio_fields_to_users_table.php`
- `database/migrations/2026_03_04_170011_add_staff_phone_to_users_table.php`

**Console Commands:** None

**Tests:**
- `tests/Feature/StaffPageTest.php`
- `tests/Feature/Settings/StaffBioTest.php`

**Config:** None

**Other:** None

---

## 18. Known Issues & Improvement Opportunities

1. **No activity logging for staff bio changes** — When a user updates their staff bio, no `RecordActivity::run()` is called. This means changes to public staff profiles are not auditable.

2. **Staff bio uses direct model updates** — The `save()` method in `staff-bio.blade.php` directly updates the User model rather than using an Action class. This is inconsistent with the project convention where business logic goes in Action classes.

3. **N+1 risk in departments computed property** — While `StaffPosition::with(['user.minecraftAccounts', 'user.discordAccounts'])` eager-loads relations, the `selectedPosition` computed property performs a separate query each time a position is selected. This is a minor performance concern but acceptable for the interactive use case.

4. **JrCrew visibility rules are in the Blade template** — The logic for hiding JrCrew staff photos, real names, and bios is scattered through `@if(! $isJrCrew && ...)` checks in the template. This could be encapsulated in model methods for consistency and testability.

5. **Phone regex could be more restrictive** — The phone validation regex `/^[\d\s\-\(\)\+\.]+$/` allows any combination of digits, spaces, hyphens, parentheses, plus signs, and periods. This accepts some technically invalid phone number formats.

6. **`edit-staff-bio` gate discrepancy with StaffBioTest** — The test states JrCrew is denied access, but the gate checks `isAtLeastRank(StaffRank::CrewMember)`. This is correct since JrCrew (value 1) is below CrewMember (value 2). However, there's no test confirming that a non-staff board member CAN access the page, which is the other branch of the gate.

7. **Staff page is fully public** — The staff page has no authentication requirement. While this is intentional (public community transparency), it means staff names, titles, ranks, and photos are visible to anyone, including non-members.

8. **Photo storage path not configurable** — Staff photos are stored in a hardcoded `staff-photos` directory on the public disk. This could be a config value.
