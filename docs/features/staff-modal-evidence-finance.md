# Staff Modal, Report Evidence Images & Finance Dashboard — Technical Documentation

> **Audience:** Project owner, developers, AI agents
> **Generated:** 2026-04-26
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

This PRD (#637) delivers three distinct sub-features that collectively enhance how staff interact with discipline reports, the public staff page, and the finance module.

**Sub-feature 1: Staff Page Modal Replacement.** The public `/staff` page previously displayed position details in a sidebar panel. This was replaced with a Flux modal that shows full position details (description, responsibilities, requirements, assigned staff member's contact info) when a card is clicked. Board member cards also open a dedicated modal. The change improves the layout on all screen sizes and removes the sidebar's layout constraints.

**Sub-feature 2: Discipline Report Evidence Images.** Staff writing discipline reports can now attach image evidence (screenshots, photos) to a report while it is in draft status. Images are uploaded via Livewire's `WithFileUploads` trait, stored on the public disk under `report-evidence/{report_id}/`, and displayed in both the edit and view modals as responsive thumbnail grids. Removing an image on the edit modal is staged (pending) until the update is saved. Deleting a report cascades to delete both the database records and the files on disk.

**Sub-feature 3: Finance Staff Dashboard.** A new dashboard page accessible to any user with the `finance-view` gate provides at-a-glance financial health indicators: current cash position across all active bank accounts, current-month income/expense comparison with budget, a 6-month income/expense trend line chart, year-to-date summary figures, net assets (unrestricted vs. restricted), and a count of pending draft journal entries awaiting posting. All figures are computed from posted entries only; drafts are excluded until they are posted.

**Users:** The staff page is public (no authentication required). Evidence image upload and management is available to users with the `Discipline Report - Manager` role or report creators (draft only). The finance dashboard requires the `Finance - View`, `Finance - Record`, or `Finance - Manage` role.

---

## 2. Database Schema

### `discipline_report_images` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned | No | auto | Primary key |
| `discipline_report_id` | bigint unsigned | No | — | FK → `discipline_reports.id` |
| `path` | varchar | No | — | Relative path on public disk |
| `original_filename` | varchar | No | — | Client-provided filename for display |
| `created_at` | timestamp | Yes | null | |
| `updated_at` | timestamp | Yes | null | |

**Indexes:** Primary key on `id`
**Foreign Keys:** `discipline_report_id` → `discipline_reports(id)` CASCADE DELETE
**Migration:** `database/migrations/2026_04_26_024008_create_discipline_report_images_table.php`

---

### Financial tables (existing, added `finance.dashboard` route for summary display)

The finance dashboard reads from the existing financial tables created in `2026_04_05_000001_create_financial_tables.php`. Key tables used:

#### `financial_accounts` table (excerpt)

| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| `id` | bigint unsigned | No | Primary key |
| `code` | varchar | No | Unique account code |
| `name` | varchar | No | Human-readable name |
| `type` | enum | No | `asset`, `liability`, `net_assets`, `revenue`, `expense` |
| `normal_balance` | enum | No | `debit` or `credit` |
| `is_bank_account` | boolean | No | Flags accounts shown in cash position |
| `is_active` | boolean | No | Inactive accounts excluded |

#### `financial_journal_entries` table (excerpt)

| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| `id` | bigint unsigned | No | Primary key |
| `period_id` | bigint unsigned | No | FK → `financial_periods.id` |
| `date` | date | No | Transaction date |
| `status` | enum | No | `draft` or `posted` — only `posted` entries affect dashboard |
| `restricted_fund_id` | bigint unsigned | Yes | FK → `financial_restricted_funds.id` |

#### `financial_journal_entry_lines` table (excerpt)

| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| `id` | bigint unsigned | No | Primary key |
| `journal_entry_id` | bigint unsigned | No | FK → `financial_journal_entries.id` |
| `account_id` | bigint unsigned | No | FK → `financial_accounts.id` |
| `debit` | decimal(15,2) | No | Debit amount (0 if credit side) |
| `credit` | decimal(15,2) | No | Credit amount (0 if debit side) |

#### `financial_budgets` table (excerpt)

| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| `id` | bigint unsigned | No | Primary key |
| `account_id` | bigint unsigned | No | FK → `financial_accounts.id` |
| `period_id` | bigint unsigned | No | FK → `financial_periods.id` |
| `amount` | decimal(15,2) | No | Budget amount for that period |

**Unique constraint:** `[account_id, period_id]`

#### `financial_periods` table (excerpt)

| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| `id` | bigint unsigned | No | Primary key |
| `fiscal_year` | integer | No | e.g., 2026 |
| `month_number` | integer | No | 1–12 |
| `start_date` | date | No | |
| `end_date` | date | No | |
| `status` | enum | No | `open`, `reconciling`, `closed` |

**Unique constraint:** `[fiscal_year, month_number]`

---

## 3. Models & Relationships

### DisciplineReportImage (`app/Models/DisciplineReportImage.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `report()` | BelongsTo | DisciplineReport | FK: `discipline_report_id` |

**Key Methods:**
- `url(): string` — returns public URL via `StorageService::publicUrl($this->path)`

**Booted observer:**
- `deleting`: deletes the file from the public disk via `Storage::disk(...)->delete($this->path)`

**Fillable:** `discipline_report_id`, `path`, `original_filename`

---

### DisciplineReport (`app/Models/DisciplineReport.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `subject()` | BelongsTo | User | FK: `subject_user_id` |
| `reporter()` | BelongsTo | User | FK: `reporter_user_id` |
| `publisher()` | BelongsTo | User | FK: `publisher_user_id` |
| `category()` | BelongsTo | ReportCategory | FK: `report_category_id` |
| `images()` | HasMany | DisciplineReportImage | Eager-loaded as `$report->images` |
| `violatedRules()` | BelongsToMany | Rule | Pivot: `discipline_report_rules` |
| `topics()` | MorphMany | Thread | Polymorphic via `topicable` |

**Scopes:** `scopePublished()`, `scopeDraft()`, `scopeForSubject(User $user)`

**Key Methods:**
- `isDraft(): bool` — true if `status === ReportStatus::Draft`
- `isPublished(): bool` — true if `status === ReportStatus::Published`

**Booted observer:**
- `deleting`: iterates `$report->images` (collection) and calls `->delete()` on each, triggering `DisciplineReportImage::booted()` to clean up files

**Casts:**
- `location` → `ReportLocation`
- `severity` → `ReportSeverity`
- `status` → `ReportStatus`
- `published_at` → `datetime`

---

### StaffPosition (`app/Models/StaffPosition.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `user()` | BelongsTo | User | Nullable — null when position is vacant |
| `applications()` | HasMany | StaffApplication | |
| `credentials()` | BelongsToMany | Credential | |

**Scopes:** `vacant()`, `filled()`, `inDepartment()`, `ordered()`, `acceptingApplications()`

**Key Methods:**
- `isVacant(): bool`, `isFilled(): bool`, `isAcceptingApplications(): bool`

**Casts:** `department` → `StaffDepartment`, `rank` → `StaffRank`

---

### BoardMember (`app/Models/BoardMember.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `user()` | BelongsTo | User | Nullable — unlinked board members have no account |

**Scopes:** `ordered()`

**Key Methods:**
- `isLinked(): bool`, `isUnlinked(): bool`
- `effectiveName(): string` — user's staff name if linked, else `display_name`
- `effectiveBio(): string` — user's bio if linked, else `bio`
- `effectivePhotoUrl(): ?string` — user avatar if linked, else stored `photo_path`

---

### FinancialAccount (`app/Models/FinancialAccount.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `journalEntryLines()` | HasMany | FinancialJournalEntryLine | |
| `budgets()` | HasMany | FinancialBudget | |
| `reconciliations()` | HasMany | FinancialReconciliation | |

---

### FinancialJournalEntry (`app/Models/FinancialJournalEntry.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `period()` | BelongsTo | FinancialPeriod | |
| `postedBy()` | BelongsTo | User | |
| `createdBy()` | BelongsTo | User | |
| `reversesEntry()` | BelongsTo | FinancialJournalEntry | |
| `reversedBy()` | HasOne | FinancialJournalEntry | |
| `vendor()` | BelongsTo | FinancialVendor | |
| `restrictedFund()` | BelongsTo | FinancialRestrictedFund | |
| `lines()` | HasMany | FinancialJournalEntryLine | |
| `tags()` | BelongsToMany | FinancialTag | |

---

### FinancialPeriod (`app/Models/FinancialPeriod.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `journalEntries()` | HasMany | FinancialJournalEntry | |
| `budgets()` | HasMany | FinancialBudget | |
| `reconciliations()` | HasMany | FinancialReconciliation | |

---

### FinancialBudget (`app/Models/FinancialBudget.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `account()` | BelongsTo | FinancialAccount | |
| `period()` | BelongsTo | FinancialPeriod | |

---

## 4. Enums Reference

### ReportStatus (`app/Enums/ReportStatus.php`)

| Case | Value | Notes |
|------|-------|-------|
| `Draft` | `'draft'` | Editable; image upload allowed |
| `Published` | `'published'` | Immutable; image upload/delete blocked |

---

### ReportSeverity (`app/Enums/ReportSeverity.php`)

| Case | color() | label() |
|------|---------|---------|
| `Low` | `'green'` | `'Low'` |
| `Medium` | `'yellow'` | `'Medium'` |
| `High` | `'orange'` | `'High'` |
| `Critical` | `'red'` | `'Critical'` |

---

### ReportLocation (`app/Enums/ReportLocation.php`)

| Case | color() | label() |
|------|---------|---------|
| `InGame` | `'blue'` | `'In Game'` |
| `Discord` | `'indigo'` | `'Discord'` |
| `Forum` | `'purple'` | `'Forum'` |
| `Other` | `'zinc'` | `'Other'` |

---

### StaffDepartment (`app/Enums/StaffDepartment.php`)

| Case | Label |
|------|-------|
| `Command` | `'Command'` |
| `Engineering` | `'Engineering'` |
| `Chaplain` | `'Chaplain Corps'` |
| `Quartermaster` | `'Quartermaster'` |
| `Steward` | `'Steward'` |

---

### StaffRank (`app/Enums/StaffRank.php`)

| Case | Value | Notes |
|------|-------|-------|
| `Officer` | `'officer'` | Command-level |
| `CrewMember` | `'crew_member'` | Full staff |
| `JrCrew` | `'jr_crew'` | Junior staff |
| `None` | `'none'` | Not a staff member |

---

## 5. Authorization & Permissions

### Gates (from `app/Providers/AuthServiceProvider.php`)

| Gate Name | Who Can Pass | Logic Summary |
|-----------|-------------|---------------|
| `view-discipline-report-log` | Manager or log viewers | `hasRole('Discipline Report - Manager')` or specific log-viewer roles |
| `manage-discipline-reports` | Managers | `hasRole('Discipline Report - Manager')` |
| `publish-discipline-reports` | Publishers | `hasRole('Discipline Report - Publisher')` |
| `view-user-discipline-reports` | Staff, self, or parent | `hasRole('Staff Access')` OR `$user->id === $targetUser->id` OR parent relationship |
| `edit-staff-bio` | Staff or board members | `hasRole('Staff Access')` OR `is_board_member` |
| `board-member` | Board members | `is_board_member` |
| `finance-view` | Finance View/Record/Manage | Any finance role |
| `finance-record` | Finance Record/Manage | `Finance - Record` or `Finance - Manage` |
| `finance-manage` | Finance Manage only | `Finance - Manage` |
| `finance-community-view` | Residents+ | Not in brig AND `isAtLeastLevel(Resident)` OR admin |

---

### Policies

#### DisciplineReportPolicy (`app/Policies/DisciplineReportPolicy.php`)

**`before()` hook:** Admins bypass all checks *except* `delete` and `publish` (those continue to policy evaluation).

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `viewAny` | Managers or Staff | `hasRole('Discipline Report - Manager')` OR `hasRole('Staff Access')` |
| `view` | Manager, Staff, subject (published), parent of subject (published) | Published required for non-staff viewing |
| `create` | Managers only | `hasRole('Discipline Report - Manager')` |
| `update` | Manager or reporter | Report must be draft; manager always; reporter only if they created it |
| `publish` | Publisher (with exclusion) | Must have Publisher role; if subject is active staff, reporter cannot publish their own report |
| `delete` | Nobody | Always `false` |

---

### Permissions Matrix

| User Type | View Report (staff) | View Report (own, published) | Create Report | Edit Draft | Publish | Add Images | Finance Dashboard |
|-----------|--------------------|-----------------------------|---------------|------------|---------|-----------|------------------|
| Admin | ✓ (bypass) | ✓ | ✓ (bypass) | ✓ (bypass) | ✗ (policy) | ✓ (bypass) | ✓ (role) |
| Discipline Report - Manager | ✓ | ✓ | ✓ | ✓ | ✗ (needs Publisher) | ✓ | ✗ (needs Finance role) |
| Discipline Report - Publisher | ✓ (Staff Access) | ✓ | ✗ | ✗ (unless also manager/creator) | ✓ | ✗ | ✗ |
| Staff Access (general) | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ |
| Finance - View | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |
| Regular user | ✗ | ✓ (published) | ✗ | ✗ | ✗ | ✗ | ✗ |
| Parent of subject | ✗ | ✓ (published) | ✗ | ✗ | ✗ | ✗ | ✗ |

---

## 6. Routes

| Method | URL | Middleware | Handler | Route Name |
|--------|-----|-----------|---------|------------|
| GET | `/staff` | — (public) | `staff.page` | `staff.index` |
| GET | `/finance/dashboard` | `auth`, `can:finance-view` | `finance.dashboard` | `finance.dashboard.index` |
| GET | `/finance` | — (public) | `finance.public-dashboard` | `finance.public.index` |
| GET | `/finance/overview` | `auth`, `can:finance-community-view` | `finance.community-finance` | `finance.community.index` |

*(All other finance routes exist but are part of the pre-existing Double-Entry Accounting Ledger System feature, not this PRD.)*

---

## 7. User Interface Components

### Staff Page
**File:** `resources/views/livewire/staff/page.blade.php`
**Route:** `/staff` (route name: `staff.index`)

**Purpose:** Publicly viewable team page listing all staff positions grouped by department and all board members. Cards open modals for detail view.

**Authorization:** Public (no auth required). Apply button respects `auth` middleware internally.

**PHP Class Properties:**
- `$selectedPositionId: ?int` — ID of position whose modal is open
- `$selectedBoardMemberId: ?int` — ID of board member whose modal is open

**PHP Class Methods:**
- `selectPosition(int $id)` — sets `$selectedPositionId`, clears board member, opens `position-detail-modal`
- `selectBoardMember(int $id)` — sets `$selectedBoardMemberId`, clears position, opens `board-member-detail-modal`

**Computed Properties:**
- `departments` — `StaffPosition::with('user')->ordered()->get()` grouped by `department->label()`
- `boardMembers` — `BoardMember::with('user')->ordered()->get()`
- `selectedPosition` — `StaffPosition::with(['user.minecraftAccounts','user.discordAccounts'])->find($selectedPositionId)`
- `selectedBoardMember` — `$this->boardMembers->firstWhere('id', $selectedBoardMemberId)`

**Modals:**
- `position-detail-modal` — shows title, department, rank badge, status badge, description, responsibilities (rendered as Markdown), requirements, assigned user avatar/name/contact links (Discord/Minecraft), apply button if accepting applications
- `board-member-detail-modal` — shows effective name, title, effective photo, effective bio (rendered as Markdown), user contact links if linked

---

### Discipline Reports Card
**File:** `resources/views/livewire/users/discipline-reports-card.blade.php`
**Route:** Embedded on profile pages (not a standalone route)

**Purpose:** Displays discipline reports for a user. Staff see all (draft + published); subject sees published only; parents see published for their child. Provides create/edit/view modals with image management.

**Authorization:** `mount()` authorizes via `view-user-discipline-reports` gate.

**Key PHP Class Properties:**
- `$userId` (`#[Locked]`) — subject user ID
- `$isStaffViewing` (`#[Locked]`) — whether viewer is staff
- `$editingReportId` (`#[Locked]`) — ID of report being edited
- `$viewingReportId` (`#[Locked]`) — ID of report being viewed
- `$formImages` — array of `UploadedFile` objects (Livewire `WithFileUploads`)
- `$removedImageIds` — `int[]` of image IDs staged for deletion

**Key PHP Class Methods:**
- `createReport()` — validates form + images, calls `AttachDisciplineReportImages::run()` if images
- `updateReport()` — validates form + images, deletes staged removals (ownership-verified), calls `AttachDisciplineReportImages::run()` for new images
- `removeImage(int $imageId)` — authorizes `update`, appends to `$removedImageIds`
- `openEditModal(int $reportId)` — authorizes `update`, loads report, resets image state
- `openCreateModal()` — authorizes `create`

**Computed Properties:**
- `editingReportImages` — existing images for `$editingReportId` minus pending `$removedImageIds`
- `viewingReport` — `DisciplineReport::with(['subject','reporter','publisher','category','violatedRules','images'])->find($viewingReportId)`

**User Actions Available:**
- Create report via `create-report-modal` with multi-image upload and live previews
- Edit draft report via `edit-report-modal` (image add/remove only available for drafts)
- Publish draft report
- View any report via `view-report-modal` (Evidence section shows thumbnail grid if images exist)

---

### View Report (standalone page component)
**File:** `resources/views/livewire/reports/view-report.blade.php`
**Route:** Embedded in staff report view pages

**Purpose:** Full read-only view of a discipline report, including Evidence section with image grid.

**Authorization:** `mount()` calls `$this->authorize('view', $report)` via `DisciplineReportPolicy@view`.

**`mount()` eager-loads:** `['subject', 'reporter', 'publisher', 'category', 'violatedRules', 'images']`

**Evidence section:** Rendered only when `$report->images->isNotEmpty()`. Shows a responsive 2–4 column grid of `<img>` thumbnails, each wrapped in a `<a target="_blank">` link to the full-size image.

---

### Finance Dashboard
**File:** `resources/views/livewire/finance/dashboard.blade.php`
**Route:** `/finance/dashboard` (route name: `finance.dashboard.index`)

**Purpose:** At-a-glance financial health dashboard for finance staff. All figures from posted entries only.

**Authorization:** `mount()` calls `$this->authorize('finance-view')`.

**Computed Properties:**
| Property | Description |
|----------|-------------|
| `cashPosition` | Array of bank account name/balance plus total; queries posted entry lines joined to accounts where `is_bank_account = true` |
| `currentMonth` | Income, expenses, net, budgeted income/expenses for current calendar month; finds covering `FinancialPeriod` for budget data |
| `sixMonthTrend` | Array of 6 months (labels + income/expense arrays) for line chart |
| `ytdSummary` | Fiscal-year income, expenses, net; finds all periods sharing the same `fiscal_year` as today's period |
| `netAssets` | Total, unrestricted, restricted net assets using CASE WHEN SQL to separate restricted fund entries |
| `pendingDrafts` | `FinancialJournalEntry::where('status', 'draft')->count()` |

**All DB queries use raw `DB::table()` joins** — not Eloquent eager-loading — to produce aggregate SQL for balance calculations.

**UI Elements:**
- Cash Position grid (one card per bank account + total card)
- Current Month: stat cards for Income, Expenses, Net; budget variance indicators; `flux:chart` bar/line
- 6-Month Trend: `flux:chart` multi-series line (income vs. expenses)
- YTD Summary: Income, Expenses, Net stat cards
- Net Assets: Total, Unrestricted, Restricted stat cards
- Pending Drafts: Count badge with link to `/finance/journal`

---

## 8. Actions (Business Logic)

### AttachDisciplineReportImages (`app/Actions/AttachDisciplineReportImages.php`)

**Signature:** `handle(DisciplineReport $report, array $files): void`

**Step-by-step logic:**
1. Checks `$report->isPublished()` — throws `RuntimeException` if true (cannot attach images to published reports)
2. Reads `SiteConfig::getValue('max_image_size_kb', '2048')` for max file size
3. For each `UploadedFile $file`:
   a. Runs `Validator::make(['file' => $file], ['file' => "mimes:jpg,jpeg,png,gif,webp|max:{$maxKb}"])->validate()`
   b. Stores file to public disk: `$file->store("report-evidence/{$report->id}", config('filesystems.public_disk'))`
   c. Creates `DisciplineReportImage` with `discipline_report_id`, `path`, `original_filename`
4. No activity logging, no notifications, no jobs dispatched

**Called by:**
- `discipline-reports-card` Volt component — `createReport()` and `updateReport()` methods

---

## 9. Notifications

Not applicable for this feature. No notifications are sent when images are attached, edited, or when the finance dashboard is viewed.

---

## 10. Background Jobs

Not applicable for this feature.

---

## 11. Console Commands & Scheduled Tasks

Not applicable for this feature.

---

## 12. Services

### StorageService (`app/Services/StorageService.php`)

**Purpose:** Abstracts public file URL generation. Returns a permanent public URL for local storage or a signed S3 URL for cloud storage.

**Used by:** `DisciplineReportImage::url()` to generate thumbnail and full-size image URLs.

**Key method:**
- `publicUrl(string $path): string` — returns `Storage::disk(...)->url($path)` or signed URL depending on environment

---

## 13. Activity Log Entries

Not applicable for this feature. `AttachDisciplineReportImages` does not call `RecordActivity::run()`. Image management does not produce activity log entries.

---

## 14. Data Flow Diagrams

### Attaching Images on Report Creation

```
User selects files in create-report-modal
  -> wire:model="formImages" (WithFileUploads handles temp storage)
  -> User previews via $image->temporaryUrl()
  -> User clicks "Create Report" (wire:submit="createReport")
    -> discipline-reports-card::createReport()
      -> $this->authorize('create', DisciplineReport::class)  [DisciplineReportPolicy@create]
      -> $this->validate([..., 'formImages.*' => 'nullable|mimes:...|max:{$maxKb}'])
      -> DisciplineReport::create([...]) → $report
      -> if $this->formImages → AttachDisciplineReportImages::run($report, $this->formImages)
           -> foreach file: validate → store → DisciplineReportImage::create([...])
      -> $this->resetForm()
      -> Flux::modal('create-report-modal')->close()
      -> Flux::toast('Report created', variant: 'success')
```

### Editing Images on a Draft Report

```
User opens edit modal (clicks Edit button)
  -> discipline-reports-card::openEditModal($reportId)
    -> $this->authorize('update', $report)  [DisciplineReportPolicy@update]
    -> loads form fields, resets $formImages, $removedImageIds
    -> Flux::modal('edit-report-modal')->show()

User clicks × on existing image
  -> discipline-reports-card::removeImage($imageId)
    -> $this->authorize('update', $editingReport)
    -> $this->removedImageIds[] = $imageId  (staged, NOT deleted yet)
    -> editingReportImages computed property re-evaluates (filters out staged IDs)

User clicks "Update Report"
  -> discipline-reports-card::updateReport()
    -> $this->authorize('update', $editingReport)
    -> validate form + new formImages
    -> foreach $removedImageIds: DisciplineReportImage::where(id, discipline_report_id)->first()->delete()
         -> DisciplineReportImage::booted() deleting: Storage::disk->delete($path)
    -> if $this->formImages → AttachDisciplineReportImages::run($report, $this->formImages)
    -> Flux::toast('Report updated', variant: 'success')
```

### Viewing Evidence on a Report

```
User clicks View on a report
  -> discipline-reports-card::openViewModal($reportId)
    -> loads viewingReport with ['images'] eager-loaded
    -> Flux::modal('view-report-modal')->show()
  -> Blade: @if($viewingReport->images->isNotEmpty())
       renders Evidence section with thumbnail grid
    @endif

OR via standalone view-report component:
  -> mount(DisciplineReport $report)
    -> $this->authorize('view', $report)
    -> $this->report = $report->load([..., 'images'])
  -> @if($report->images->isNotEmpty()) renders Evidence flux:card @endif
```

### Cascade Delete

```
Admin deletes a DisciplineReport
  -> DisciplineReport::booted() deleting: $report->images->each->delete()
       -> for each DisciplineReportImage:
            -> DisciplineReportImage::booted() deleting: Storage::disk->delete($path)
            -> DB record deleted
  -> DisciplineReport record deleted
```

### Finance Dashboard Load

```
User navigates to /finance/dashboard
  -> Route: finance.dashboard.index (middleware: auth, can:finance-view)
    -> finance.dashboard Volt component
      -> mount(): $this->authorize('finance-view')
      -> Page renders, Livewire calls computed properties on demand:
           cashPosition: DB::table join → SUM(debit)/SUM(credit) per bank account
           currentMonth: DB::table join filtered by current calendar month dates
           sixMonthTrend: loop 6 months, DB::table per month
           ytdSummary: find fiscal year from today, sum all periods in that year
           netAssets: DB::table with CASE WHEN for restricted_fund_id IS NOT NULL
           pendingDrafts: FinancialJournalEntry::where('status','draft')->count()
      -> flux:chart components render using returned data arrays
```

### Staff Page Modal Interaction

```
User visits /staff (public, no auth required)
  -> staff.page Volt component loads
  -> getDepartmentsProperty(): StaffPosition::with('user')->ordered()->get() grouped by dept
  -> getBoardMembersProperty(): BoardMember::with('user')->ordered()->get()
  -> Page renders position cards and board member cards

User clicks a position card
  -> wire:click="selectPosition({{ $position->id }})"
    -> selectPosition(int $id):
         $this->selectedPositionId = $id
         $this->selectedBoardMemberId = null
         Flux::modal('position-detail-modal')->show()
    -> selectedPosition computed: StaffPosition::with([user.minecraftAccounts,user.discordAccounts])->find($id)
    -> position-detail-modal renders with position details + user contact info

User clicks a board member card
  -> wire:click="selectBoardMember({{ $bm->id }})"
    -> selectBoardMember(int $id):
         $this->selectedBoardMemberId = $id
         $this->selectedPositionId = null
         Flux::modal('board-member-detail-modal')->show()
    -> selectedBoardMember: $this->boardMembers->firstWhere('id', $id)
    -> board-member-detail-modal renders
```

---

## 15. Configuration

| Key | Default | Purpose |
|-----|---------|---------|
| `max_image_size_kb` | `'2048'` | Maximum upload size per evidence image (KB); read via `SiteConfig::getValue()` |
| `filesystems.public_disk` | `'public'` | Laravel filesystem disk name used for storing and reading evidence images |

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/StaffPageTest.php` | 12 | Staff page rendering, modal state, position/board-member display |
| `tests/Feature/Livewire/ReportEvidenceUITest.php` | 7 | Evidence section visibility, image upload/delete, cascade delete |
| `tests/Feature/Livewire/DisciplineReportsCardTest.php` | 12+ | Reports card access, CRUD, publish, risk score |
| `tests/Feature/Finance/FinanceDashboardTest.php` | 9 | Finance dashboard access, cash position, monthly totals, pending drafts |

---

### Test Case Inventory

**`tests/Feature/StaffPageTest.php`**
- loads the public staff page without authentication
- displays filled positions with user names
- displays vacant positions as open
- groups positions by department
- does not display departments with no positions
- displays board members section on the public page
- does not display board members section when no board members exist
- shows linked board member with user staff name
- shows unlinked board member with display name
- does not render the old sidebar panel markup
- selectPosition sets selectedPositionId and selectedPosition returns the correct model
- renders markdown responsibilities as HTML in the modal content

**`tests/Feature/Livewire/ReportEvidenceUITest.php`**
- view-report shows Evidence section with img elements when report has images
- view-report does not render Evidence section when report has no images
- opening edit modal for a published report is forbidden
- edit modal image UI is rendered for a draft report
- images attached via AttachDisciplineReportImages are retrievable from the report
- deleting a report also removes its image files from disk
- removeImage marks an image ID for pending removal without immediately deleting

**`tests/Feature/Livewire/DisciplineReportsCardTest.php`**
- shows discipline reports card to staff on profile page
- shows discipline reports card to the subject user
- shows discipline reports card to parent of subject
- hides discipline reports card from unrelated users
- shows only published reports to non-staff users
- shows all reports including drafts to staff
- allows staff to create a report via modal
- allows user with Discipline Report - Publisher role to publish a draft report
- prevents non-officer from publishing
- allows creator to edit their draft report
- prevents editing of published reports
- shows risk score badge with correct color

**`tests/Feature/Finance/FinanceDashboardTest.php`**
- finance-view user can access finance dashboard
- user without finance role cannot access finance dashboard
- unauthenticated user cannot access finance dashboard
- cash position shows correct balance for a posted journal entry
- draft entries are excluded from cash position
- current month income total reflects posted entries in current calendar month
- current month expenses reflect posted entries in current calendar month
- draft entries are excluded from current month totals
- pending drafts count matches draft journal entries

---

### Coverage Gaps

- No test for `removeImage` followed by `updateReport` verifying that the image is actually deleted from disk after the full update flow (only the pending state is tested)
- No test for `sixMonthTrend` computed property on the finance dashboard
- No test for `ytdSummary` or `netAssets` computed properties on the finance dashboard
- No test for the board member modal rendering (staff page tests cover position modal but not board member modal detail content)
- No test for `AttachDisciplineReportImages` throwing `RuntimeException` on a published report via the Livewire component (the action's guard is tested at the action level but not exercised through the UI path)
- No test for `SiteConfig::getValue('max_image_size_kb')` affecting validation (always uses the 2048 default in tests)

---

## 17. File Map

**Models:**
- `app/Models/DisciplineReport.php`
- `app/Models/DisciplineReportImage.php`
- `app/Models/StaffPosition.php`
- `app/Models/BoardMember.php`
- `app/Models/FinancialAccount.php`
- `app/Models/FinancialJournalEntry.php`
- `app/Models/FinancialJournalEntryLine.php`
- `app/Models/FinancialPeriod.php`
- `app/Models/FinancialBudget.php`
- `app/Models/FinancialBudgetLine.php`
- `app/Models/FinancialRestrictedFund.php`

**Actions:**
- `app/Actions/AttachDisciplineReportImages.php`

**Policies:**
- `app/Policies/DisciplineReportPolicy.php`

**Gates:** `app/Providers/AuthServiceProvider.php` — gates: `view-discipline-report-log`, `view-user-discipline-reports`, `manage-discipline-reports`, `publish-discipline-reports`, `edit-staff-bio`, `board-member`, `finance-view`, `finance-record`, `finance-manage`, `finance-community-view`

**Notifications:** None

**Jobs:** None

**Services:**
- `app/Services/StorageService.php` (used for image URL generation)

**Controllers:** None (all Volt components)

**Volt Components:**
- `resources/views/livewire/staff/page.blade.php`
- `resources/views/livewire/users/discipline-reports-card.blade.php`
- `resources/views/livewire/reports/view-report.blade.php`
- `resources/views/livewire/finance/dashboard.blade.php`

**Routes:**
- `staff.index` → `/staff`
- `finance.dashboard.index` → `/finance/dashboard`

**Migrations:**
- `database/migrations/2026_04_26_024008_create_discipline_report_images_table.php`
- `database/migrations/2026_04_05_000001_create_financial_tables.php` (pre-existing; finance dashboard reads these tables)

**Console Commands:** None

**Tests:**
- `tests/Feature/StaffPageTest.php`
- `tests/Feature/Livewire/ReportEvidenceUITest.php`
- `tests/Feature/Livewire/DisciplineReportsCardTest.php`
- `tests/Feature/Finance/FinanceDashboardTest.php`

**Config:**
- `SiteConfig` key: `max_image_size_kb`
- Config key: `filesystems.public_disk`

**Other:**
- `resources/views/dashboard/ready-room.blade.php` — Finance button updated to link to `finance.dashboard.index`
- `resources/views/livewire/finance/partials/nav.blade.php` — Dashboard nav link added as first item

---

## 18. Known Issues & Improvement Opportunities

1. **Missing full-flow image deletion test.** `removeImage` is tested to only stage the deletion without immediately deleting, but there is no test that completes the round-trip through `updateReport()` and verifies the file is gone from disk. A bug in `updateReport()`'s deletion loop would not be caught.

2. **Finance dashboard coverage gaps.** `sixMonthTrend`, `ytdSummary`, and `netAssets` have no test coverage. These involve complex raw SQL with fiscal year detection and CASE WHEN expressions — exactly the kind of logic that benefits most from regression tests.

3. **N+1 risk in `selectedPosition` computed property.** `StaffPosition::with(['user.minecraftAccounts','user.discordAccounts'])` is called once per click, which is fine, but if the board member section were to also eager-load user relations at mount time, a large staff list would cause multiple queries. Currently `boardMembers` loads `with('user')` in bulk, which is correct.

4. **`removeImage` authorization re-fetches report.** The `removeImage` method re-fetches the editing report from the database to call `authorize('update', $report)`. This is correct for security but adds a query per removal click. Since removal is staging-only (no immediate DB write), a lighter check using the `$editingReportId` already held in the locked property would be equivalent.

5. **`SiteConfig::getValue('max_image_size_kb')` not cached.** Called on every `createReport()` and `updateReport()` invocation. If SiteConfig reads from the database (rather than a cached value), this is an extra query per upload. Depends on SiteConfig implementation.

6. **Image uploads allowed only on drafts, but the check is in the Action, not the Policy.** `AttachDisciplineReportImages::run()` throws `RuntimeException` if the report is published. This guard is not in `DisciplineReportPolicy@update`, which means a crafted request could reach the action with a published report and receive an uncaught exception rather than a proper 403 response.

7. **No soft deletes on `DisciplineReportImage`.** Images are hard-deleted (DB row + file) when removed. There is no audit trail of what images were ever attached to a report. This may be intentional but is worth noting for compliance/audit use cases.

8. **Finance dashboard YTD uses `fiscal_year` from `FinancialPeriod`** — if the current date falls between fiscal years (e.g., no period covering today), the YTD query returns empty results silently. Consider adding a user-visible indicator when no active period is found.
