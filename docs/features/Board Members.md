# Board Members -- Technical Documentation

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

The Board Members feature manages the organization's Board of Directors. Board members are displayed publicly on the Staff Page alongside staff departments, appearing in a dedicated "Board of Directors" section. Each board member has a display name, title, optional bio, and optional photo.

A key concept is **linking** — a board member record can optionally be linked to a site user account. When linked, the board member's display name, bio, and photo are dynamically pulled from the linked user's staff profile fields (`staff_first_name`, `staff_last_initial`, `staff_bio`, staff photo). When unlinked, the board member's own `display_name`, `bio`, and `photo_path` fields are used instead. Linking a user also sets the `is_board_member` flag on their User model, which grants them the `board-member` gate and access to the `edit-staff-bio` gate (allowing them to edit their staff bio even if they are not at CrewMember rank or above).

Board member management is restricted to Admins and Command department Officers. It is accessed through the "Board Members" tab under the Content category of the Admin Control Panel (ACP). The management interface supports full CRUD operations plus user linking/unlinking with search functionality.

---

## 2. Database Schema

### `board_members` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint (auto) | No | — | Primary key |
| `display_name` | string | No | — | Name displayed when not linked to a user |
| `title` | string | Yes | NULL | Board title (e.g., "Chairman", "Treasurer") |
| `user_id` | foreignId (users) | Yes | NULL | Linked user account; unique constraint |
| `bio` | text | Yes | NULL | Bio text used when not linked to a user |
| `photo_path` | string | Yes | NULL | Photo path used when not linked to a user |
| `sort_order` | unsignedInteger | No | 0 | Display order on the staff page |
| `created_at` | timestamp | Yes | — | Laravel timestamp |
| `updated_at` | timestamp | Yes | — | Laravel timestamp |

**Indexes:**
- `board_members_user_id_unique` — unique constraint on `user_id` (only one board member per user)

**Foreign Keys:**
- `user_id` → `users.id` (ON DELETE SET NULL)

**Migration(s):** `database/migrations/2026_03_03_000003_create_board_members_table.php`

### `users` table (added column)

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `is_board_member` | boolean | No | false | Set to true when linked to a BoardMember record |

**Migration(s):** `database/migrations/2026_03_03_000004_add_is_board_member_to_users_table.php`

---

## 3. Models & Relationships

### BoardMember (`app/Models/BoardMember.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `user()` | belongsTo | User | Optional linked user account |

**Scopes:**
- `scopeOrdered(Builder $query)` — orders by `sort_order` ASC then `display_name` ASC

**Key Methods:**
- `isLinked(): bool` — returns true if `user_id` is not null
- `isUnlinked(): bool` — returns true if `user_id` is null
- `effectiveName(): string` — returns linked user's `staff_first_name` + `staff_last_initial` if linked with those fields set, otherwise returns `display_name`
- `effectiveBio(): ?string` — returns linked user's `staff_bio` if linked, otherwise returns own `bio`
- `effectivePhotoUrl(): ?string` — returns linked user's staff photo URL (falling back to avatar) if linked, otherwise returns `asset('storage/'.$this->photo_path)` if photo exists, otherwise null

**Casts:** None explicit (uses default)

**Fillable:** `display_name`, `title`, `user_id`, `bio`, `photo_path`, `sort_order`

**Factory:** Has a factory (`HasFactory` trait)

### User (`app/Models/User.php`) — related fields

- `is_board_member` (boolean, fillable, cast to boolean) — flag set by Link/Unlink/Create/Delete actions
- Used by `board-member` gate and `edit-staff-bio` gate in `AuthServiceProvider`

---

## 4. Enums Reference

Not applicable for this feature.

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

| Gate Name | Who Can Pass | Logic Summary |
|-----------|-------------|---------------|
| `board-member` | Users with `is_board_member = true` | `return $user->is_board_member` |
| `edit-staff-bio` | CrewMember+ OR board members | `return $user->isAtLeastRank(StaffRank::CrewMember) \|\| $user->is_board_member` |

### Policies

#### BoardMemberPolicy (`app/Policies/BoardMemberPolicy.php`)

**`before()` hook:** Admin OR (Command department AND Officer rank) → returns `true` (full bypass)

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `viewAny` | Admin, Command Officer | All others denied (returns `false`) |
| `create` | Admin, Command Officer | All others denied |
| `update` | Admin, Command Officer | All others denied |
| `delete` | Admin, Command Officer | All others denied |

**Registered in:** `AuthServiceProvider` — `\App\Models\BoardMember::class => \App\Policies\BoardMemberPolicy::class`

### Permissions Matrix

| User Type | View (Staff Page) | viewAny (ACP) | Create | Update | Delete | Link/Unlink |
|-----------|-------------------|---------------|--------|--------|--------|-------------|
| Regular User | Yes (public) | No | No | No | No | No |
| Staff (any) | Yes | No | No | No | No | No |
| Non-Command Officer | Yes | No | No | No | No | No |
| Command Officer | Yes | Yes | Yes | Yes | Yes | Yes |
| Admin | Yes | Yes | Yes | Yes | Yes | Yes |

---

## 6. Routes

The Board Members management is embedded in the ACP and has no dedicated route. Public viewing is through the Staff Page.

| Method | URL | Middleware | Handler | Route Name |
|--------|-----|-----------|---------|------------|
| GET | `/acp?category=content&tab=board-member-manager` | auth | ACP → `admin-manage-board-members-page` | `acp.index` |
| GET | `/staff` | (public or auth) | Staff Page → `staff.page` | — |

---

## 7. User Interface Components

### Board Members Manager (ACP Tab)
**File:** `resources/views/livewire/admin-manage-board-members-page.blade.php`
**Route:** `/acp?category=content&tab=board-member-manager` (embedded in ACP)

**Purpose:** Full CRUD management for board members, including user linking/unlinking.

**Authorization:** `$this->authorize('viewAny', BoardMember::class)` in `boardMembers()` computed property; individual actions authorize against `create`, `update`, `delete` policies.

**PHP Properties:**
- Create form: `$newDisplayName`, `$newTitle`, `$newBio`, `$newPhoto`, `$newSortOrder`
- Edit form: `$editId`, `$editDisplayName`, `$editTitle`, `$editBio`, `$editPhoto`, `$editSortOrder`, `$editIsLinked`
- Link form: `$linkBoardMemberId`, `$linkUserId`, `$userSearch`

**Key Methods:**
- `boardMembers()` (computed) — authorize viewAny, load with user relation, ordered
- `createBoardMember()` — authorize create, validate, store photo, call `CreateBoardMember::run()`
- `openEditModal($id)` — authorize update, load board member data into edit form
- `updateBoardMember()` — authorize update, validate, handle photo replacement, call `UpdateBoardMember::run()`
- `deleteBoardMember($id)` — authorize delete, call `DeleteBoardMember::run()`
- `openLinkModal($id)` — authorize update, initialize link search form
- `searchedUsers` (computed) — search users by name (min 2 chars), exclude already-linked users, limit 10
- `selectUser($userId)` — set `$linkUserId`
- `linkUser()` — authorize update, validate, call `LinkBoardMemberToUser::run()`
- `unlinkUser($id)` — authorize update, call `UnlinkBoardMemberFromUser::run()`

**Modals:**
- `create-board-member-modal` (flyout) — create form with display name, title, photo upload, bio, sort order
- `edit-board-member-modal` (flyout) — edit form; photo field hidden when linked (bio/photo managed via Staff Bio)
- `link-user-modal` (standard) — user search with debounce, results list, link button

**UI Elements:**
- Table with columns: Sort Order, Name, Title, Linked User (with unlink button), Actions (Edit/Delete)
- Add Board Member button
- Edit/Delete buttons per row
- Link User button for unlinked members, Unlink button for linked members

### Staff Page (Public Display)
**File:** `resources/views/livewire/staff/page.blade.php`
**Route:** `/staff`

**Purpose:** Public-facing page showing all staff organized by department, plus a "Board of Directors" section.

**Board Member Display:**
- Grid layout (2/3/4 columns responsive)
- Each card shows: photo (via `effectivePhotoUrl()`), name (via `effectiveName()`), title (or "Board Member" default)
- Clicking a board member shows details in a sidebar panel: bio, contact info (if linked user and viewer has `viewStaffPhone` permission), profile link (if linked)

---

## 8. Actions (Business Logic)

### CreateBoardMember (`app/Actions/CreateBoardMember.php`)

**Signature:** `handle(string $displayName, ?string $title = null, ?int $userId = null, ?string $bio = null, ?string $photoPath = null, int $sortOrder = 0): BoardMember`

**Step-by-step logic:**
1. Wraps in DB transaction
2. Creates BoardMember record with all provided fields
3. If `$userId` is provided, finds the User and sets `is_board_member = true`
4. Logs activity: `RecordActivity::run($boardMember, 'board_member_created', "Board member created: {$displayName}")`
5. Returns the created BoardMember

**Called by:** `admin-manage-board-members-page` component

### UpdateBoardMember (`app/Actions/UpdateBoardMember.php`)

**Signature:** `handle(BoardMember $boardMember, string $displayName, ?string $title = null, ?string $bio = null, ?string $photoPath = null, ?int $sortOrder = null): void`

**Step-by-step logic:**
1. Builds update data array (display_name, title, bio, photo_path)
2. Adds sort_order only if non-null
3. Updates the BoardMember model
4. Logs activity: `RecordActivity::run($boardMember, 'board_member_updated', "Board member updated: {$displayName}")`

**Called by:** `admin-manage-board-members-page` component

### DeleteBoardMember (`app/Actions/DeleteBoardMember.php`)

**Signature:** `handle(BoardMember $boardMember): void`

**Step-by-step logic:**
1. Captures display_name and photo_path before deletion
2. Wraps in DB transaction:
   a. Logs activity: `RecordActivity::run($boardMember, 'board_member_deleted', "Board member deleted: {$displayName}")`
   b. If linked to a user, sets `is_board_member = false` on that user
   c. Deletes the BoardMember record
3. After transaction, deletes photo from public storage disk if it exists

**Called by:** `admin-manage-board-members-page` component

### LinkBoardMemberToUser (`app/Actions/LinkBoardMemberToUser.php`)

**Signature:** `handle(BoardMember $boardMember, User $user): void`

**Step-by-step logic:**
1. Wraps in DB transaction
2. If user is already linked to a different BoardMember, unlinks them (sets their `user_id` to null)
3. If board member was previously linked to a different user, clears that user's `is_board_member` flag
4. Updates board member's `user_id` to the new user
5. Sets `is_board_member = true` on the user
6. Logs activity: `RecordActivity::run($boardMember, 'board_member_linked', "Board member '{name}' linked to user {user}")`

**Called by:** `admin-manage-board-members-page` component

### UnlinkBoardMemberFromUser (`app/Actions/UnlinkBoardMemberFromUser.php`)

**Signature:** `handle(BoardMember $boardMember): void`

**Step-by-step logic:**
1. Gets the linked user; returns early if no linked user
2. Captures user name before transaction
3. Wraps in DB transaction:
   a. Sets board member's `user_id` to null
   b. Sets user's `is_board_member` to false
   c. Logs activity: `RecordActivity::run($boardMember, 'board_member_unlinked', "Board member '{name}' unlinked from user {user}")`

**Called by:** `admin-manage-board-members-page` component

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

| Action String | Logged By | Subject Model | Description |
|---------------|-----------|---------------|-------------|
| `board_member_created` | `CreateBoardMember` | BoardMember | "Board member created: {displayName}" |
| `board_member_updated` | `UpdateBoardMember` | BoardMember | "Board member updated: {displayName}" |
| `board_member_deleted` | `DeleteBoardMember` | BoardMember | "Board member deleted: {displayName}" |
| `board_member_linked` | `LinkBoardMemberToUser` | BoardMember | "Board member '{displayName}' linked to user {userName}" |
| `board_member_unlinked` | `UnlinkBoardMemberFromUser` | BoardMember | "Board member '{displayName}' unlinked from user {userName}" |

---

## 14. Data Flow Diagrams

### Creating a Board Member

```
Admin clicks "Add Board Member" in ACP Board Members tab
  -> Flux::modal('create-board-member-modal')->show()
Admin fills form (display name, title, photo, bio, sort order) and submits
  -> createBoardMember() fires
    -> $this->authorize('create', BoardMember::class)
    -> validate(displayName required, title, bio, sortOrder numeric, photo image max 2MB)
    -> Store photo to public disk if uploaded
    -> CreateBoardMember::run($displayName, $title, null, $bio, $photoPath, $sortOrder)
      -> DB::transaction:
        -> BoardMember::create([...])
        -> RecordActivity::run($boardMember, 'board_member_created', ...)
      -> Returns BoardMember
    -> Flux::modal('create-board-member-modal')->close()
    -> Flux::toast('Board member created!')
```

### Linking a Board Member to a User

```
Admin clicks "Link User" on an unlinked board member row
  -> openLinkModal($id) fires
    -> $this->authorize('update', $boardMember)
    -> Flux::modal('link-user-modal')->show()
Admin types user name in search field (min 2 chars, 300ms debounce)
  -> searchedUsers computed property queries users
    -> Filters by name LIKE, excludes already-linked users, limit 10
Admin clicks a user from results
  -> selectUser($userId) sets $linkUserId
Admin clicks "Link User" button
  -> linkUser() fires
    -> $this->authorize('update', $boardMember)
    -> validate(linkUserId required, exists in users)
    -> LinkBoardMemberToUser::run($boardMember, $user)
      -> DB::transaction:
        -> Clear any existing link for this user on other board members
        -> Clear is_board_member on previously linked user (if different)
        -> $boardMember->update(['user_id' => $user->id])
        -> $user->update(['is_board_member' => true])
        -> RecordActivity::run(...)
    -> Flux::modal('link-user-modal')->close()
    -> Flux::toast('User linked to board member!')
```

### Unlinking a Board Member from a User

```
Admin clicks "Unlink" button on a linked board member row
  -> unlinkUser($id) fires
    -> $this->authorize('update', $boardMember)
    -> UnlinkBoardMemberFromUser::run($boardMember)
      -> DB::transaction:
        -> $boardMember->update(['user_id' => null])
        -> $user->update(['is_board_member' => false])
        -> RecordActivity::run(...)
    -> Flux::toast('User unlinked from board member.')
```

### Deleting a Board Member

```
Admin clicks delete button on a board member row
  -> deleteBoardMember($id) fires
    -> $this->authorize('delete', $boardMember)
    -> DeleteBoardMember::run($boardMember)
      -> DB::transaction:
        -> RecordActivity::run(...) (logged before deletion)
        -> Clear is_board_member on linked user if any
        -> $boardMember->delete()
      -> Delete photo from storage if exists
    -> Flux::toast('Board member deleted.')
```

### Viewing Board Members (Public)

```
User navigates to /staff
  -> Staff Page loads
    -> Board members queried: BoardMember::ordered()->with('user')->get()
    -> Rendered in "Board of Directors" section after staff departments
    -> Each card shows: effectivePhotoUrl(), effectiveName(), title
    -> Click card -> sidebar panel with bio, contact info (gated), profile link
```

---

## 15. Configuration

Not applicable for this feature.

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/Policies/BoardMemberPolicyTest.php` | 8 tests | Policy authorization checks |
| `tests/Feature/Actions/BoardMember/CreateBoardMemberTest.php` | 5 tests | Create action logic |
| `tests/Feature/Actions/BoardMember/UpdateBoardMemberTest.php` | 2 tests | Update action logic |
| `tests/Feature/Actions/BoardMember/DeleteBoardMemberTest.php` | 4 tests | Delete action with cleanup |
| `tests/Feature/Actions/BoardMember/LinkBoardMemberTest.php` | 4 tests | Link action logic |
| `tests/Feature/Actions/BoardMember/UnlinkBoardMemberTest.php` | 4 tests | Unlink action logic |

### Test Case Inventory

#### `BoardMemberPolicyTest.php`

1. `it allows admin to view any board members`
2. `it allows command officer to view any board members`
3. `it denies regular user from viewing board members`
4. `it denies crew member from managing board members`
5. `it allows admin to create board members`
6. `it allows admin to update board members`
7. `it allows admin to delete board members`
8. `it denies non-command officer from managing board members`

#### `CreateBoardMemberTest.php`

1. `it creates an unlinked board member`
2. `it creates a board member linked to a user`
3. `it records activity when creating a board member`
4. `it stores title when provided`
5. `it creates board member without title`

#### `UpdateBoardMemberTest.php`

1. `it updates display name and title`
2. `it records activity when updating`

#### `DeleteBoardMemberTest.php`

1. `it deletes an unlinked board member`
2. `it clears is_board_member flag when deleting a linked board member`
3. `it records activity before deletion`
4. `it deletes photo from storage when deleting board member`

#### `LinkBoardMemberTest.php`

1. `it links a user to a board member`
2. `it sets is_board_member flag on user when linking`
3. `it clears existing board membership when user is already linked to another`
4. `it records activity when linking`

#### `UnlinkBoardMemberTest.php`

1. `it unlinks a user from a board member`
2. `it clears is_board_member flag on user when unlinking`
3. `it does nothing when board member has no linked user`
4. `it records activity when unlinking`

### Coverage Gaps

- **No Livewire component tests** — the `admin-manage-board-members-page` component has no dedicated test file. Validation rules, modal interactions, photo upload handling, and user search filtering in the UI are untested.
- **No test for `effectiveName()`, `effectiveBio()`, `effectivePhotoUrl()`** — the model's dynamic display methods are not unit tested.
- **No test for the `board-member` gate** — only the policy is tested; the gate that checks `$user->is_board_member` has no test.
- **No test for the `edit-staff-bio` gate for board members** — the gate allows board members to edit their bio, but this path isn't tested.
- **No test for Staff Page board member display** — the public rendering of board members on the staff page is untested.
- **No test for `scopeOrdered`** — the ordering scope is not directly tested.

---

## 17. File Map

**Models:**
- `app/Models/BoardMember.php`
- `app/Models/User.php` (related: `is_board_member` field)

**Enums:** None

**Actions:**
- `app/Actions/CreateBoardMember.php`
- `app/Actions/UpdateBoardMember.php`
- `app/Actions/DeleteBoardMember.php`
- `app/Actions/LinkBoardMemberToUser.php`
- `app/Actions/UnlinkBoardMemberFromUser.php`

**Policies:**
- `app/Policies/BoardMemberPolicy.php`

**Gates:** `app/Providers/AuthServiceProvider.php` — gates: `board-member`, `edit-staff-bio`

**Notifications:** None

**Jobs:** None

**Services:** None

**Controllers:** None

**Volt Components:**
- `resources/views/livewire/admin-manage-board-members-page.blade.php` (ACP management)
- `resources/views/livewire/staff/page.blade.php` (public display)

**Routes:**
- `acp.index` — `GET /acp` (board members tab: `?category=content&tab=board-member-manager`)
- Staff page — `GET /staff`

**Migrations:**
- `database/migrations/2026_03_03_000003_create_board_members_table.php`
- `database/migrations/2026_03_03_000004_add_is_board_member_to_users_table.php`

**Console Commands:** None

**Tests:**
- `tests/Feature/Policies/BoardMemberPolicyTest.php`
- `tests/Feature/Actions/BoardMember/CreateBoardMemberTest.php`
- `tests/Feature/Actions/BoardMember/UpdateBoardMemberTest.php`
- `tests/Feature/Actions/BoardMember/DeleteBoardMemberTest.php`
- `tests/Feature/Actions/BoardMember/LinkBoardMemberTest.php`
- `tests/Feature/Actions/BoardMember/UnlinkBoardMemberTest.php`

**Config:** None

**Other:** None

---

## 18. Known Issues & Improvement Opportunities

1. **Photo not cleaned up on update** — When `UpdateBoardMember::run()` is called with a new `$photoPath`, the old photo is not deleted from storage. Only `DeleteBoardMember` handles photo cleanup. The Livewire component may handle this in its `updateBoardMember()` method, but the action itself does not.

2. **`is_board_member` flag can become stale** — If a user is directly deleted from the database (bypassing the model), the `board_members` table's `user_id` is set to NULL (via `nullOnDelete`), but the `is_board_member` flag on the deleted user is moot. However, if the `board_members` record is manually modified (e.g., `user_id` set to null via tinker), the user's `is_board_member` flag would remain `true` incorrectly.

3. **No `view` policy method** — The policy only defines `viewAny`, `create`, `update`, and `delete`. There is no `view` method for individual board members. The staff page displays board members publicly without authorization, which is correct, but if a per-record view is ever needed, a method would need to be added.

4. **Activity logged before deletion** — In `DeleteBoardMember`, `RecordActivity::run()` is called inside the DB transaction before `$boardMember->delete()`. This is correct because the activity log needs the board member to still exist for the polymorphic subject. However, after deletion, the activity log entry will have a dangling `subject_id` — the viewer shows "[deleted]" for these entries.

5. **`CreateBoardMember` accepts `$userId` but component never passes it** — The action's `handle()` method accepts an optional `$userId` parameter for creating a linked board member directly, but the `admin-manage-board-members-page` component always creates unlinked members and uses the separate Link action. The `$userId` parameter is tested but unused in the UI.

6. **No cascade sync for linked user profile changes** — When a linked user changes their `staff_first_name`, `staff_bio`, or staff photo, the board member display updates automatically (via `effective*()` methods). However, there is no notification or cache invalidation — changes are reflected on next page load via live queries.

7. **Unique constraint on `user_id` prevents dual board membership** — The database has a unique constraint on `user_id`, preventing a user from being linked to multiple board member records. The `LinkBoardMemberToUser` action handles this by unlinking the user from any previous board member first.
