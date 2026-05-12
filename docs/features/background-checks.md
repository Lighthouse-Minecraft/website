# Background Check Tracking System — Technical Documentation

> **Audience:** Project owner, developers, AI agents
> **Generated:** 2026-05-09
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

The Background Check Tracking System allows staff with the appropriate role to record, manage, and track background check history for community members. Each check record captures the service provider, completion date, status, notes, and any supporting PDF documents. Records can progress through non-terminal statuses (Pending, Deliberating) and become locked once a terminal status (Passed, Failed, Waived) is reached.

The feature integrates with the User Profile page (via an embedded `background-checks-card` Volt component), the Staff Directory page (status badges on filled position cards), Department Report Cards during meetings (renewal badges), and the Staff Application Review workflow (the Approve Application button is gated on the applicant having a terminal background check record).

Key terminology:
- **Terminal status**: Passed, Failed, or Waived. Once a check reaches a terminal status, `locked_at` is set and status can no longer be changed, nor can documents be deleted.
- **Renewal badge**: Overdue/Due Soon/Waived badge shown to viewers with `background-checks-view` permission. A Passed check expires 2 years after `completed_date`; within 90 days of expiry shows Due Soon.
- **Self-visibility**: Regular users can see their own check history (read-only) on their own profile without any role requirement.

---

## 2. Database Schema

### `background_checks` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint | No | auto | Primary key |
| `user_id` | bigint | No | — | FK → `users.id` |
| `run_by_user_id` | bigint | No | — | FK → `users.id`; who ran the check |
| `service` | varchar | No | — | Provider name (e.g., "Checkr", "Sterling") |
| `completed_date` | date | No | — | Date the check was completed; cannot be future |
| `status` | varchar | No | — | `BackgroundCheckStatus` enum value |
| `notes` | text | Yes | null | Append-only log of timestamped notes |
| `locked_at` | timestamp | Yes | null | Set when status transitions to a terminal value |
| `created_at` | timestamp | No | — | |
| `updated_at` | timestamp | No | — | |
| `deleted_at` | timestamp | Yes | null | Soft delete |

**Foreign Keys:** `user_id` → `users.id`, `run_by_user_id` → `users.id`
**Migration:** `database/migrations/2026_05_09_000001_create_background_checks_tables.php`

---

### `background_check_documents` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint | No | auto | Primary key |
| `background_check_id` | bigint | No | — | FK → `background_checks.id` |
| `path` | varchar | No | — | Storage path within the public disk |
| `original_filename` | varchar | No | — | Client-provided filename shown in UI |
| `uploaded_by_user_id` | bigint | No | — | FK → `users.id` |
| `created_at` | timestamp | No | — | |
| `updated_at` | timestamp | No | — | |
| `deleted_at` | timestamp | Yes | null | Soft delete |

**Foreign Keys:** `background_check_id` → `background_checks.id`, `uploaded_by_user_id` → `users.id`
**Migration:** `database/migrations/2026_05_09_000001_create_background_checks_tables.php`

---

## 3. Models & Relationships

### BackgroundCheck (`app/Models/BackgroundCheck.php`)

**Traits:** `HasFactory`, `SoftDeletes`

**Fillable:** `user_id`, `run_by_user_id`, `service`, `completed_date`, `status`, `notes`, `locked_at`

**Relationships:**

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `user()` | BelongsTo | User | The subject of the check |
| `runByUser()` | BelongsTo | User | FK `run_by_user_id`; the staff member who ran it |
| `documents()` | HasMany | BackgroundCheckDocument | All attached PDF records |

**Casts:**
- `completed_date` → `date`
- `status` → `BackgroundCheckStatus`
- `locked_at` → `datetime`

**Key Methods:**
- `isLocked(): bool` — returns `true` when `locked_at` is not null (i.e., status is terminal)

---

### BackgroundCheckDocument (`app/Models/BackgroundCheckDocument.php`)

**Traits:** `HasFactory`, `SoftDeletes`

**Fillable:** `background_check_id`, `path`, `original_filename`, `uploaded_by_user_id`

**Relationships:**

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `backgroundCheck()` | BelongsTo | BackgroundCheck | Parent check record |
| `uploadedByUser()` | BelongsTo | User | FK `uploaded_by_user_id` |

**Key Methods:**
- `url(): string` — returns a public URL via `StorageService::publicUrl($this->path)`

---

### User (`app/Models/User.php`) — background-check relationships

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `backgroundChecks()` | HasMany | BackgroundCheck | All check records for this user |
| `latestTerminalBackgroundCheck()` | HasOne | BackgroundCheck | Most recent check with a terminal status (Passed/Failed/Waived), determined by max `id` |
| `latestPassedBackgroundCheck()` | HasOne | BackgroundCheck | Most recent check with Passed status, by max `id` |

Both `latestTerminalBackgroundCheck` and `latestPassedBackgroundCheck` use `ofMany` with a filter query and are suitable for eager-loading.

---

## 4. Enums Reference

### BackgroundCheckStatus (`app/Enums/BackgroundCheckStatus.php`)

| Case | Value | Label | Color | Terminal? |
|------|-------|-------|-------|-----------|
| `Pending` | `'pending'` | Pending | amber | No |
| `Deliberating` | `'deliberating'` | Deliberating | violet | No |
| `Passed` | `'passed'` | Passed | emerald | Yes |
| `Failed` | `'failed'` | Failed | red | Yes |
| `Waived` | `'waived'` | Waived | zinc | Yes |

**Helper methods:**
- `label(): string` — human-readable label
- `color(): string` — Flux badge color string
- `isTerminal(): bool` — `true` for Passed, Failed, Waived; `false` for Pending, Deliberating

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

| Gate Name | Who Can Pass | Logic Summary |
|-----------|-------------|---------------|
| `background-checks-view` | Background Checks - View OR Background Checks - Manage role | `$user->hasRole('Background Checks - View') \|\| $user->hasRole('Background Checks - Manage')` |
| `background-checks-manage` | Background Checks - Manage role only | `$user->hasRole('Background Checks - Manage')` |

Admins automatically pass all role checks because `User::hasRole()` returns `true` for any admin via the `isAdmin()` shortcut.

### Policies

No dedicated policy exists for `BackgroundCheck`. Authorization is enforced within the Livewire component via `$this->authorize('gate-name')`.

### Permissions Matrix

| User Type | View own history | View others' history | Add check | Update status | Add note | Upload PDF | Delete PDF |
|-----------|-----------------|---------------------|-----------|---------------|----------|------------|------------|
| Regular user (own profile) | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Background Checks - View | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Background Checks - Manage | ✅ | ✅ | ✅ | ✅ (non-terminal only) | ✅ | ✅ | ✅ (non-terminal only) |
| Admin | ✅ | ✅ | ✅ | ✅ (non-terminal only) | ✅ | ✅ | ✅ (non-terminal only) |

**Self-visibility note:** A regular user viewing their own profile sees the `background-checks-card` with read-only access (history + document downloads). They never see renewal badges (Overdue/Due Soon/Waived) — those require `background-checks-view`.

---

## 6. Routes

No dedicated routes exist for background checks. The feature is exposed entirely via embedded Livewire components on existing routes.

| Route | Component/Page | Notes |
|-------|---------------|-------|
| `GET /profile/{user}` (`profile.show`) | `users/show.blade.php` embeds `background-checks-card` | Conditionally rendered based on permission |
| `GET /staff` (`staff.index`) | `livewire/staff/page.blade.php` | Shows status badges on position cards |
| `GET /admin/applications/{application}` (`admin.applications.show`) | `livewire/staff-applications/review-detail.blade.php` | Embeds `background-checks-card` for applicant; gates Approve button |
| `GET /meetings/{meeting}/report` | `livewire/meeting/department-report-cards.blade.php` | Shows renewal badges per staff member |

---

## 7. User Interface Components

### background-checks-card
**File:** `resources/views/livewire/users/background-checks-card.blade.php`
**Embedded in:** `resources/views/users/show.blade.php`, `resources/views/livewire/staff-applications/review-detail.blade.php`

**Purpose:** Shows the full background check history for a given user. Provides read-only access to non-privileged users and full CRUD to managers.

**Authorization:** `mount()` aborts 403 if the authenticated user is neither the subject (`$isSelf`) nor a `background-checks-view` holder. All mutating methods call `$this->authorize('background-checks-manage')`.

**Properties (public, server-side):**
- `#[Locked] int $userId` — subject user ID
- `#[Locked] bool $canManage` — whether viewer has manage permission
- `#[Locked] bool $canViewRenewal` — whether viewer can see Overdue/Due Soon/Waived badges
- Form fields: `newService`, `newCompletedDate`, `newInitialNotes`, `noteCheckId`, `noteText`, `uploadCheckId`, `pendingDocuments[]`

**Computed properties:**
- `getUserProperty()` — eager-loads `backgroundChecks.documents.uploadedByUser`, `backgroundChecks.runByUser`, `latestTerminalBackgroundCheck`, `latestPassedBackgroundCheck`
- `getRenewalBadgeProperty(): ?array` — returns `['color' => ..., 'label' => ...]` or `null` (only if `canViewRenewal`)

**Renewal badge logic:**
1. If `latestTerminalBackgroundCheck` is Waived → violet "Waived"
2. Else if no `latestPassedBackgroundCheck` → red "Overdue"
3. Else if Passed check expires within 90 days → amber "Due Soon"
4. Else if Passed check already expired → red "Overdue"
5. Else → no badge

**User Actions:**
- **Add Check** (manage only) → modal → `submitNewCheck()` → `CreateBackgroundCheck::run()` → Flux toast
- **Set status** (manage, non-terminal checks) → `updateStatus(checkId, statusValue)` → `UpdateBackgroundCheckStatus::run()` → Flux toast
- **Add Note** (manage) → inline form → `submitNote()` → `AddBackgroundCheckNote::run()` → Flux toast
- **Upload PDF** (manage) → file input → `submitDocuments()` → `AttachBackgroundCheckDocuments::run()` → Flux toast
- **Delete PDF** (manage, non-terminal checks) → `deleteDocument(docId)` with `wire:confirm` → `DeleteBackgroundCheckDocument::run()` → Flux toast

**UI Elements:** Flux card with renewal badge in header; per-check rows showing status badge, service name, date, run-by, notes, document links with download and delete; inline note/upload forms; Add Check modal.

---

### Staff Page background check badges
**File:** `resources/views/livewire/staff/page.blade.php`

**Purpose:** Each filled staff position card shows a "BG Check" badge with color and tooltip indicating the holder's latest terminal check status.

**Methods:**
- `bgCheckTooltip(?BackgroundCheck $check): string` — returns tooltip text; reads `bg_check_no_record_message` from SiteConfig for null/non-terminal cases
- `bgCheckColor(?BackgroundCheck $check): string` — returns Flux badge color

**Eager load:** `user.latestTerminalBackgroundCheck` added to `getDepartmentsProperty()`.

**Visibility:** Badge is visible to all visitors (no auth required for the staff page itself). Color/tooltip reflect the terminal status or amber for "no record".

---

### Department Report Cards renewal badges
**File:** `resources/views/livewire/meeting/department-report-cards.blade.php`

**Purpose:** During a meeting, the department report cards component shows Overdue/Due Soon/Waived renewal badges next to each staff member's name, visible to Officers and above.

**Method:** `renewalBadge(User $member): ?array` — same logic as `getRenewalBadgeProperty()` in the profile card.

**Eager load:** `latestTerminalBackgroundCheck` and `latestPassedBackgroundCheck` on the staffMembers query.

---

### Staff Application Review — Approve gating
**File:** `resources/views/livewire/staff-applications/review-detail.blade.php`

**Purpose:** When an application is in the `BackgroundCheck` status stage, the Approve Application button is only shown when `$staffApplication->user->latestTerminalBackgroundCheck` is non-null. Otherwise a message is shown: "Approval requires a completed background check record on the applicant profile."

The `background-checks-card` is embedded inline (gated by `@can('background-checks-view')`) so reviewers with manage permission can add/update check records without leaving the review page.

---

## 8. Actions (Business Logic)

### CreateBackgroundCheck (`app/Actions/CreateBackgroundCheck.php`)

**Signature:** `handle(User $user, User $runBy, string $service, Carbon $completedDate, ?string $notes = null): BackgroundCheck`

**Step-by-step logic:**
1. Validates `completedDate` is not in the future; throws `InvalidArgumentException` if so
2. Creates `BackgroundCheck` with `status = BackgroundCheckStatus::Pending`
3. Logs activity: `background_check_created` on the new `BackgroundCheck`

**Called by:** `background-checks-card` → `submitNewCheck()`

---

### UpdateBackgroundCheckStatus (`app/Actions/UpdateBackgroundCheckStatus.php`)

**Signature:** `handle(BackgroundCheck $check, BackgroundCheckStatus $newStatus, User $updatedBy): void`

**Step-by-step logic:**
1. Throws `InvalidArgumentException` if `$check->status->isTerminal()` (locked record)
2. Updates `$check->status` to `$newStatus`
3. If `$newStatus->isTerminal()`, sets `$check->locked_at = now()`
4. Saves the model
5. Logs activity: `background_check_status_updated` on the `BackgroundCheck`

**Called by:** `background-checks-card` → `updateStatus()`

---

### AddBackgroundCheckNote (`app/Actions/AddBackgroundCheckNote.php`)

**Signature:** `handle(BackgroundCheck $check, string $noteText, User $author): void`

**Step-by-step logic:**
1. Formats a timestamped note entry: `[YYYY-MM-DD HH:mm] AuthorName: text`
2. Appends the entry to `$check->notes` (prepends newline if existing notes present)
3. Saves the model
4. Logs activity: `background_check_note_added` on the `BackgroundCheck`

**Note:** Notes can be added to locked (terminal) checks — only status changes and document deletion are blocked on locked records.

**Called by:** `background-checks-card` → `submitNote()`

---

### AttachBackgroundCheckDocuments (`app/Actions/AttachBackgroundCheckDocuments.php`)

**Signature:** `handle(BackgroundCheck $check, array $files, User $uploadedBy): void`

**Step-by-step logic (per file):**
1. Reads `max_background_check_document_size_kb` from `SiteConfig` (default: 10240 KB); treats zero or negative as the default
2. Validates each file: `mimes:pdf|max:{maxKb}` — throws `ValidationException` on failure
3. Stores file at `background-checks/{check->id}/` on the `public_disk` filesystem
4. Creates a `BackgroundCheckDocument` record
5. Logs activity: `background_check_document_attached` on the `BackgroundCheck`

**Called by:** `background-checks-card` → `submitDocuments()`; also called directly in tests

---

### DeleteBackgroundCheckDocument (`app/Actions/DeleteBackgroundCheckDocument.php`)

**Signature:** `handle(BackgroundCheckDocument $document, User $deletedBy): void`

**Step-by-step logic:**
1. Loads `$document->backgroundCheck`
2. Throws `InvalidArgumentException` if the parent check `isLocked()` (terminal status)
3. Deletes file from the `public_disk` storage
4. Soft-deletes the `BackgroundCheckDocument` record
5. Logs activity: `background_check_document_deleted` on the parent `BackgroundCheck`

**Called by:** `background-checks-card` → `deleteDocument()`

---

## 9. Notifications

Not applicable for this feature. Background checks do not trigger any email or Pushover notifications. The `ApplicationStatusChangedNotification` references `ApplicationStatus::BackgroundCheck` (the application stage), not a background check record.

---

## 10. Background Jobs

Not applicable for this feature.

---

## 11. Console Commands & Scheduled Tasks

Not applicable for this feature.

---

## 12. Services

### StorageService (`app/Services/StorageService.php`)

**Purpose:** Generates public URLs for stored files, abstracting the difference between local disk (permanent URLs) and S3 (temporary signed URLs, 60-minute expiry by default).

**Key methods:**
- `publicUrl(string $path, int $expirationMinutes = 60): string` — returns a download URL for a file on the `public_disk` filesystem

Used by:
- `BackgroundCheckDocument::url()` — convenience method on the document model
- `background-checks-card` → `documentUrl(string $path): string` — generates URLs for download links in the UI

---

## 13. Activity Log Entries

All activity is logged against the `BackgroundCheck` model as the subject.

| Action String | Logged By | Subject Model | Description |
|---------------|-----------|---------------|-------------|
| `background_check_created` | `CreateBackgroundCheck` | BackgroundCheck | "Background check created for {user} by {runBy} using service "{service}"." |
| `background_check_status_updated` | `UpdateBackgroundCheckStatus` | BackgroundCheck | "Background check status updated to {status} by {user}." |
| `background_check_note_added` | `AddBackgroundCheckNote` | BackgroundCheck | "Note added to background check by {user}." |
| `background_check_document_attached` | `AttachBackgroundCheckDocuments` | BackgroundCheck | "Document "{filename}" attached to background check by {user}." |
| `background_check_document_deleted` | `DeleteBackgroundCheckDocument` | BackgroundCheck | "Document "{filename}" deleted from background check by {user}." |

---

## 14. Data Flow Diagrams

### Adding a New Background Check Record

```text
Manager clicks "Add Check" on background-checks-card
  -> wire:click="openAddCheckModal()" [authorizes background-checks-manage]
    -> Flux::modal('add-bg-check-modal-{userId}').show()
  -> Manager fills service, date, optional notes; clicks "Add Check"
    -> wire:click="submitNewCheck()"
      -> $this->authorize('background-checks-manage')
      -> $this->validate(['newService' => 'required|...', 'newCompletedDate' => 'required|date|before_or_equal:today', ...])
      -> CreateBackgroundCheck::run($user, Auth::user(), $service, Carbon::parse($date), $notes)
        -> BackgroundCheck::create([..., 'status' => BackgroundCheckStatus::Pending])
        -> RecordActivity::run($check, 'background_check_created', ...)
      -> Flux::modal('add-bg-check-modal-{userId}').close()
      -> Flux::toast('Background check record added.', 'Added', variant: 'success')
```

### Updating Background Check Status

```text
Manager clicks status button (e.g., "Passed") on a non-terminal check row
  -> wire:click="updateStatus({checkId}, 'passed')" [wire:confirm confirmation]
    -> $this->authorize('background-checks-manage')
    -> UpdateBackgroundCheckStatus::run($check, BackgroundCheckStatus::Passed, Auth::user())
      -> Guard: throws if check is already terminal
      -> $check->status = Passed; $check->locked_at = now(); $check->save()
      -> RecordActivity::run($check, 'background_check_status_updated', ...)
    -> Flux::toast('Status updated.', 'Updated', variant: 'success')
    [Check row now shows lock icon; status/delete buttons disappear]
```

### Uploading a PDF Document

```text
Manager clicks "Upload PDF" on a check row
  -> wire:click="openUploadForm({checkId})" [authorizes background-checks-manage]
    -> uploadCheckId = checkId; pendingDocuments = []
  -> Manager selects file(s), clicks "Upload"
    -> wire:click="submitDocuments()"
      -> $this->authorize('background-checks-manage')
      -> AttachBackgroundCheckDocuments::run($check, $pendingDocuments, Auth::user())
        -> Per file: validate mimes:pdf|max:{SiteConfig limit}
        -> Store at "background-checks/{checkId}/{filename}" on public_disk
        -> BackgroundCheckDocument::create([...])
        -> RecordActivity::run($check, 'background_check_document_attached', ...)
      -> Flux::toast('Documents uploaded.', 'Uploaded', variant: 'success')
```

### Viewing Renewal Status (Profile Card)

```text
User visits /profile/{user}
  -> users/show.blade.php conditionally renders background-checks-card
     [condition: auth()->user()->can('background-checks-view') || auth()->id() === $user->id]
  -> background-checks-card::mount(User $user)
    -> Aborts 403 if neither own profile nor background-checks-view
    -> Sets canManage, canViewRenewal based on roles
  -> getRenewalBadgeProperty() [only if canViewRenewal]
    -> Evaluates latestTerminalBackgroundCheck and latestPassedBackgroundCheck
    -> Returns badge array or null
  -> Template renders badge in card header (Overdue/Due Soon/Waived/none)
```

### Staff Application Approval Gate

```text
Reviewer views /admin/applications/{application} (BackgroundCheck status)
  -> review-detail.blade.php renders
  -> @can('background-checks-view') embeds background-checks-card for applicant
  -> Actions section:
     @if($staffApplication->user->latestTerminalBackgroundCheck)
       → Shows "Approve Application" button
     @else
       → Shows "Approval requires a completed background check record" message
  -> If approved:
    -> wire:click opens approve-modal (only rendered when terminal check exists)
    -> approve() called: ApproveApplication::run($app, Auth::user(), $conditions, $notes)
      -> Application status → Approved
      -> AssignStaffPosition::run($position, $user)
      -> Notifications sent to applicant
```

---

## 15. Configuration

### SiteConfig keys (database-backed configuration)

| Key | Default Value | Purpose |
|-----|--------------|---------|
| `max_background_check_document_size_kb` | `10240` | Maximum allowed size in KB for PDF uploads; seeded by migration `2026_05_09_000002` |
| `bg_check_no_record_message` | `'Waiting for more donations to come in before we can do more background checks'` | Tooltip shown on staff directory badge when no terminal check exists; seeded by migration `2026_05_09_000003` |

These values are managed via the `SiteConfig` model and can be updated through the admin control panel without a deploy.

### Filesystem

Documents are stored on the disk configured as `filesystems.public_disk`. In production this is an S3 bucket (private, signed URLs). In development it is the local `public` disk (permanent URLs).

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/Models/BackgroundCheckTest.php` | 14 | Model casting, soft deletes, relationships, enum helpers |
| `tests/Feature/Actions/Actions/BackgroundCheckActionsTest.php` | 17 | CreateBackgroundCheck, UpdateBackgroundCheckStatus, AddBackgroundCheckNote |
| `tests/Feature/Actions/Actions/BackgroundCheckDocumentActionsTest.php` | 8 | AttachBackgroundCheckDocuments, DeleteBackgroundCheckDocument |
| `tests/Feature/Livewire/BackgroundChecksCardTest.php` | 18 | Profile card visibility, check display, renewal badges, manage actions, access control |
| `tests/Feature/Livewire/StaffDirectoryBadgeTest.php` | 8 | Staff page badge colors/tooltips, SiteConfig override, visibility to guests |
| `tests/Feature/Livewire/DepartmentReportCardsBadgeTest.php` | 6 | Meeting report card renewal badges |
| `tests/Feature/Livewire/ReviewDetailBgCheckGatingTest.php` | 5 | Approve button gating, moveToBackgroundCheck, approve action |

### Test Case Inventory

**BackgroundCheckTest:**
- casts status to BackgroundCheckStatus enum
- casts locked_at to datetime
- isLocked returns false when locked_at is null
- isLocked returns true when locked_at is set
- belongs to the subject user
- belongs to the run-by user
- has many documents
- soft deletes background check
- soft deletes background check document
- user has many background checks
- BackgroundCheckStatus Deliberating has correct label
- BackgroundCheckStatus Deliberating has a color
- terminal statuses are Passed, Failed, and Waived
- non-terminal statuses are Pending and Deliberating

**BackgroundCheckActionsTest:**
- creates a background check with Pending status
- creates a background check with optional notes
- creates a background check without notes when omitted
- rejects a future completed_date
- accepts today as a valid completed_date
- writes activity log on creation
- transitions a Pending check to Deliberating
- transitions a Pending check to Passed and sets locked_at
- transitions a Pending check to Failed and sets locked_at
- transitions a Pending check to Waived and sets locked_at
- transitions a Deliberating check to Passed
- throws when attempting to change a Passed (locked) check
- throws when attempting to change a Failed (locked) check
- throws when attempting to change a Waived (locked) check
- writes activity log on status update
- appends a note to a check with no existing notes
- appends a note in [YYYY-MM-DD HH:mm] format
- appends a note to a check with existing notes
- appends a note to a locked (terminal status) check
- writes activity log on note addition (→ includes overlap counting)

**BackgroundCheckDocumentActionsTest:**
- stores a PDF and creates a BackgroundCheckDocument record
- rejects a non-PDF file
- rejects a file that exceeds the SiteConfig size limit
- allows upload on an unlocked (Pending) check
- allows upload on a locked (Passed) check
- writes activity log on the parent BackgroundCheck when attaching a document
- soft-deletes the document and removes the file from storage
- blocks deleting a document from a locked check
- writes activity log on the parent BackgroundCheck when deleting a document

**BackgroundChecksCardTest:**
- shows the card to a user with background-checks-view role
- shows the card to a user viewing their own profile (self-visibility)
- hides the card from users without background-checks-view and not own profile
- displays check records with service, date, status, and run-by
- displays document download links for attached PDFs
- shows empty state when no checks exist
- shows overdue badge when user has no Passed check and viewer has background-checks-view
- shows due soon badge when most recent Passed check expires within 90 days
- does not show renewal badge to own-profile viewer without background-checks-view
- shows Add Check button to manage users
- hides Add Check button from view-only users
- creates a new background check via submitNewCheck
- validates required fields on submitNewCheck
- updates status on a non-terminal check via updateStatus
- adds a note to any check via submitNote
- uploads a PDF document to a check via submitDocuments
- deletes a document from a non-terminal check via deleteDocument
- blocks view-only users from calling manage methods

**StaffDirectoryBadgeTest:**
- shows green badge with passed tooltip on officer card when user has a passed background check
- shows zinc badge with waived tooltip on officer card when user has a waived background check
- shows amber badge with no-record message when user has no terminal background check
- uses SiteConfig value for no-record message
- uses most recent terminal check, not a pending or deliberating one
- shows no badge on unfilled position cards
- shows badge on crew member cards
- badge is visible to unauthenticated visitors

**DepartmentReportCardsBadgeTest:**
- shows Overdue badge when staff has no Passed background check
- shows Overdue badge when most recent Passed check expired over 2 years ago
- shows Due Soon badge when Passed check expires within 90 days
- shows Waived badge when most recent terminal check is Waived
- shows no badge when staff has a current valid Passed check
- shows no badge when Pending record exists but prior Passed check is still valid

**ReviewDetailBgCheckGatingTest:**
- hides Approve Application button when applicant has no terminal background check
- shows Approve Application button when applicant has a passed background check
- shows Approve Application button when applicant has a failed background check
- moves application to background check step
- approves application when terminal background check exists

### Coverage Gaps

- No test verifying that `AttachBackgroundCheckDocuments` respects the 60-minute signed URL expiry in S3 mode (StorageService branch not unit-tested)
- No test for the `bg_check_no_record_message` SiteConfig in the department report cards component
- No test for the applicant-facing `staff-applications/show.blade.php` view after dropping the `background_check_status` column
- No test verifying that the renewal badge correctly shows "Waived" (vs. "Overdue") when the most recent terminal check is Waived rather than Passed (in the profile card)
- Concurrent note appending is not tested (append semantics rely on read-then-write, not atomic update)

---

## 17. File Map

**Models:**
- `app/Models/BackgroundCheck.php`
- `app/Models/BackgroundCheckDocument.php`
- `app/Models/User.php` (adds `backgroundChecks()`, `latestTerminalBackgroundCheck()`, `latestPassedBackgroundCheck()` relationships)

**Enums:**
- `app/Enums/BackgroundCheckStatus.php`

**Actions:**
- `app/Actions/CreateBackgroundCheck.php`
- `app/Actions/UpdateBackgroundCheckStatus.php`
- `app/Actions/AddBackgroundCheckNote.php`
- `app/Actions/AttachBackgroundCheckDocuments.php`
- `app/Actions/DeleteBackgroundCheckDocument.php`

**Policies:** None — authorization is via gates only.

**Gates:** `app/Providers/AuthServiceProvider.php` — gates: `background-checks-view`, `background-checks-manage`

**Notifications:** None specific to this feature.

**Jobs:** None.

**Services:**
- `app/Services/StorageService.php` (shared service; used for document URL generation)

**Controllers:** None — feature is fully Livewire-based.

**Volt Components:**
- `resources/views/livewire/users/background-checks-card.blade.php` (primary card component)
- `resources/views/livewire/staff/page.blade.php` (adds BG Check badge to staff directory)
- `resources/views/livewire/meeting/department-report-cards.blade.php` (adds renewal badges)
- `resources/views/livewire/staff-applications/review-detail.blade.php` (embeds card, gates Approve button)

**Blade views:**
- `resources/views/users/show.blade.php` (embeds background-checks-card conditionally)
- `resources/views/livewire/staff-applications/show.blade.php` (applicant's own application view — no bg check data shown)

**Routes:** No dedicated routes; embedded in existing pages.

**Migrations:**
- `database/migrations/2026_05_09_000001_create_background_checks_tables.php` — creates `background_checks` and `background_check_documents` tables
- `database/migrations/2026_05_09_000002_seed_background_check_site_config.php` — seeds `max_background_check_document_size_kb`
- `database/migrations/2026_05_09_000003_seed_bg_check_no_record_message.php` — seeds `bg_check_no_record_message`
- `database/migrations/2026_05_09_000004_drop_background_check_status_from_staff_applications.php` — drops `background_check_status` from `staff_applications`

**Factories:**
- `database/factories/BackgroundCheckFactory.php` — states: `passed()`, `failed()`, `waived()`, `deliberating()`
- `database/factories/BackgroundCheckDocumentFactory.php`

**Tests:**
- `tests/Feature/Models/BackgroundCheckTest.php`
- `tests/Feature/Actions/Actions/BackgroundCheckActionsTest.php`
- `tests/Feature/Actions/Actions/BackgroundCheckDocumentActionsTest.php`
- `tests/Feature/Livewire/BackgroundChecksCardTest.php`
- `tests/Feature/Livewire/StaffDirectoryBadgeTest.php`
- `tests/Feature/Livewire/DepartmentReportCardsBadgeTest.php`
- `tests/Feature/Livewire/ReviewDetailBgCheckGatingTest.php`

**Config:** SiteConfig keys `max_background_check_document_size_kb` and `bg_check_no_record_message` (database-backed, managed via admin panel).

---

## 18. Known Issues & Improvement Opportunities

- **Note append is not atomic:** `AddBackgroundCheckNote` reads `$check->notes`, appends, then saves. Concurrent note additions from two browser sessions could silently drop one entry. A safe fix would be a raw `DB::table()->update()` with string concatenation or a dedicated `notes` JSONB column.

- **Document deletion blocked on locked checks but upload is not:** `DeleteBackgroundCheckDocument` throws on locked checks, but `AttachBackgroundCheckDocuments` has no such guard. Documents can be uploaded to Passed/Failed/Waived checks (intentional per tests), but this asymmetry is not documented in the UI.

- **`latestTerminalBackgroundCheck` uses max `id`, not max `completed_date`:** A check completed earlier but inserted later would be ranked highest. This is unlikely in practice but worth noting if bulk data imports occur.

- **Renewal badge logic is duplicated:** The badge calculation in `background-checks-card` (`getRenewalBadgeProperty`) is duplicated nearly identically in `department-report-cards.blade.php` (`renewalBadge(User $member)`). A shared trait or helper class would reduce maintenance risk.

- **Self-visibility bypass is broad:** A regular user visiting their own profile accesses the `background-checks-card`. If a `background-checks-manage` user is also viewing that profile, the embedded card on `review-detail.blade.php` may abort 403 for reviewers who lack `background-checks-view`. This is mitigated by the `@can('background-checks-view')` wrapper in `review-detail.blade.php`, but reviewers without that role cannot see check history while reviewing an application.

- **No expiry/archival mechanism:** There is no automated job to flag or archive stale checks. "Overdue" status is computed on-the-fly from `completed_date + 2 years`. If the renewal period policy changes, the hardcoded `addYears(2)` and `subDays(90)` thresholds in multiple components must all be updated manually.
