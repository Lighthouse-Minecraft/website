# Staff Meeting Lumen Payouts — Technical Documentation

> **Audience:** Project owner, developers, AI agents
> **Generated:** 2026-03-23
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

The Staff Meeting Lumen Payouts feature automatically distributes in-game Lumen currency to eligible staff members when a staff meeting is completed. Before this feature, meeting managers had to manually track attendance, form submissions, and payout eligibility — a tedious, error-prone process.

When a meeting manager clicks "Complete Meeting," the system evaluates every staff member in the meeting's attendee list against rank-specific eligibility rules, then sends `money give` RCON commands to the Minecraft server for each eligible user. A permanent audit record is written to the `meeting_payouts` table regardless of outcome (paid, skipped, or failed).

The feature has three user-facing surfaces:
- **Payout Preview** (during Finalizing step): Meeting managers see a real-time eligibility table and can exclude individual users before finalizing.
- **Payout Summary** (on completed meeting page): All staff with `Staff Access` can see the permanent payout audit trail.
- **Site Config** (ACP): Admins adjust per-rank Lumen amounts or set them to 0 to disable payouts for that rank.

Key terminology:
- **Lumen (✦)**: In-game currency on the Minecraft server, granted via RCON economy plugin.
- **Payout eligibility**: Rank-specific requirements (form submission ± attendance) before a user can receive their Lumen grant.
- **Excluded**: A manager-initiated skip for a specific user; recorded with reason "Excluded by manager."
- **MeetingPayout**: The DB record representing one user's payout attempt for one meeting.

---

## 2. Database Schema

### `meeting_payouts` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint unsigned (PK) | No | auto-increment | |
| `meeting_id` | bigint unsigned (FK) | No | — | → `meetings.id`, cascades on delete |
| `user_id` | bigint unsigned (FK) | No | — | → `users.id`, cascades on delete |
| `minecraft_account_id` | bigint unsigned (FK) | Yes | NULL | → `minecraft_accounts.id`, nulls on delete |
| `amount` | unsigned int | No | — | Lumen amount; 0 for skipped/disabled |
| `status` | enum | No | — | `'paid'`, `'skipped'`, `'failed'`, `'pending'` |
| `skip_reason` | varchar | Yes | NULL | Human-readable reason when status ≠ paid |
| `created_at` | timestamp | Yes | NULL | |
| `updated_at` | timestamp | Yes | NULL | |

**Indexes:**
- `UNIQUE (meeting_id, user_id)` — prevents duplicate payout records per user per meeting

**Foreign Keys:**
- `meeting_id` → `meetings.id` (CASCADE DELETE)
- `user_id` → `users.id` (CASCADE DELETE)
- `minecraft_account_id` → `minecraft_accounts.id` (SET NULL on delete)

**Migration:** `database/migrations/2026_03_22_000001_create_meeting_payouts_table.php`

---

### `site_config` table (payout-related rows only)

| Key | Default Value | Description |
|-----|---------------|-------------|
| `meeting_payout_jr_crew` | `'50'` | Lumen payout for Jr Crew (0 = disabled) |
| `meeting_payout_crew_member` | `'75'` | Lumen payout for Crew Members (0 = disabled) |
| `meeting_payout_officer` | `'100'` | Lumen payout for Officers (0 = disabled) |

Values are stored as strings; cast to `int` in `ProcessMeetingPayouts`. Non-numeric or missing values are treated as 0 (disabled).

**Migration (seeder):** `database/migrations/2026_03_22_000002_seed_meeting_payout_site_config.php`

---

## 3. Models & Relationships

### MeetingPayout (`app/Models/MeetingPayout.php`)

**Fillable:** `meeting_id`, `user_id`, `minecraft_account_id`, `amount`, `status`, `skip_reason`

**Relationships:**

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `meeting()` | belongsTo | Meeting | |
| `user()` | belongsTo | User | |
| `minecraftAccount()` | belongsTo | MinecraftAccount | Nullable; the account that was paid |

**Scopes:** None

**Key Methods:** None beyond relationships

**Casts:** None (status is an enum column but not cast to a PHP enum)

---

### Meeting (`app/Models/Meeting.php`) — payout-relevant portions

**Relationships (payout-related):**

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `payouts()` | hasMany | MeetingPayout | All payout records for this meeting |
| `attendees()` | belongsToMany | User | Via `meeting_user` pivot; includes `attended` flag used in eligibility |
| `reports()` | hasMany | MeetingReport | Used to check form submission in eligibility |

**Key Methods (payout-related):**
- `isStaffMeeting(): bool` — returns `true` when `type === MeetingType::StaffMeeting`; gates payout UI visibility

---

## 4. Enums Reference

### StaffRank (`app/Enums/StaffRank.php`)

| Case | Value | Label | Payout Config Key |
|------|-------|-------|-------------------|
| `None` | 0 | None | — (no payout) |
| `JrCrew` | 1 | Junior Crew Member | `meeting_payout_jr_crew` |
| `CrewMember` | 2 | Crew Member | `meeting_payout_crew_member` |
| `Officer` | 3 | Officer | `meeting_payout_officer` |

**Helper methods:** `label()`, `color()`, `discordRoleId()`

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

No custom gates are defined for payout functionality. All authorization flows through the `MeetingPolicy`.

### Policies

#### MeetingPolicy (`app/Policies/MeetingPolicy.php`)

**`before()` hook:** Admins bypass all checks (`$user->isAdmin()` → `true`)

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `view` | Staff | `hasRole('Staff Access')` |
| `viewAny` | Staff | `hasRole('Staff Access')` |
| `update` | Meeting Manager | `hasRole('Meeting - Manager')` |
| `create` | Meeting Manager | `hasRole('Meeting - Manager')` |
| `attend` | Staff | `hasRole('Staff Access')` |
| `delete` | Nobody | Always `false` |

**Payout-specific authorization:**
- `payout-preview` component `toggleExclude()`: calls `$this->authorize('update', $this->meeting)` — requires `Meeting - Manager`
- `payout-summary` component: read-only, no explicit authorization check (the parent meeting page is already gated by `view` policy)
- `manage-meeting` `CompleteMeetingConfirmed()`: calls `$this->authorize('update', $this->meeting)` — requires `Meeting - Manager`

### Permissions Matrix

| User Type | View Payout Summary | See Payout Preview | Toggle Exclude | Complete Meeting |
|-----------|--------------------|--------------------|----------------|-----------------|
| Regular member | No | No | No | No |
| Staff (Staff Access) | Yes | No | No | No |
| Meeting Manager | Yes | Yes | Yes | Yes |
| Admin | Yes | Yes | Yes | Yes |

---

## 6. Routes

| Method | URL | Middleware | Handler | Route Name |
|--------|-----|-----------|---------|------------|
| GET | `/meetings/{meeting}/manage` | `auth`, meeting access | `ManageMeetingController@edit` (renders `meetings/manage-meeting`) | `meeting.edit` |

The payout UI lives inside `meeting.edit`. There are no dedicated payout routes.

---

## 7. User Interface Components

### Payout Preview Component
**File:** `resources/views/livewire/meeting/payout-preview.blade.php`
**Embedded in:** Manage Meeting (`meeting.edit`) during the **Finalizing** step, for staff meetings only

**Purpose:** Shows a real-time eligibility table before meeting completion, allowing managers to exclude individual users.

**Authorization:** `toggleExclude()` calls `$this->authorize('update', $this->meeting)` (requires `Meeting - Manager`)

**User Actions Available:**
- Toggle exclude switch for any eligible user → calls `toggleExclude(int $userId)` → updates `$excludedUserIds` array → dispatches `payoutExcludedUsersChanged` event to parent

**Computed Properties:**
- `payoutAmounts`: array of per-rank Lumen amounts from SiteConfig
- `allPayoutsDisabled`: true when all three rank amounts are 0 (hides entire component)
- `submittedUserIds`: user IDs that have a submitted `MeetingReport` for this meeting
- `attendeesWithEligibility`: collection of arrays with user, rank, attended, formSubmitted, mcAccount, amount, eligible, skipReason

**UI Elements:**
- Hidden entirely when `allPayoutsDisabled` is true
- Eligibility table: Name (link to profile), Rank, Form (✓/✗ icon), Attended (✓/✗ icon), MC Account (✓/✗ icon), Amount (Lumens ✦ or —), Include toggle (visible to managers only)
- Ineligible rows rendered at `opacity-50` with skip reason shown below name

---

### Payout Summary Component
**File:** `resources/views/livewire/meeting/payout-summary.blade.php`
**Embedded in:** Manage Meeting (`meeting.edit`) during the **Completed** step, for staff meetings only

**Purpose:** Displays the permanent audit trail of payout outcomes from the `meeting_payouts` table.

**Authorization:** No explicit check — accessible to anyone who can view the completed meeting (requires `Staff Access` via the parent page's policy)

**Computed Properties:**
- `payouts`: all `MeetingPayout` records for the meeting, eager-loaded with `user`
- `paidCount`, `totalLumens`, `skippedCount`, `failedCount`: aggregate statistics

**UI Elements:**
- Hidden entirely when no payout records exist (pre-feature meetings)
- Summary line: "X paid (Y ✦ total) · Z skipped · W failed"
- Detail table: Name (link to profile), Amount (Lumens or —), Status badge (green/zinc/red), Reason

---

### Manage Meeting Component (payout-related changes)
**File:** `resources/views/livewire/meetings/manage-meeting.blade.php`

**Payout-related additions:**

| Property/Method | Purpose |
|-----------------|---------|
| `public array $excludedPayoutUserIds = []` | Tracks excluded user IDs synced from child payout-preview |
| `syncExcludedPayoutUsers(array $excludedUserIds)` | Listens for `payoutExcludedUsersChanged` event from payout-preview child |
| `CompleteMeetingConfirmed()` | Now calls `ProcessMeetingPayouts::run($this->meeting, $this->excludedPayoutUserIds)` |

The `payout-preview` component is embedded in the Finalizing section (after the community notes editor). The `payout-summary` component is embedded in the Completed section (after community minutes). Both are only rendered when `$meeting->isStaffMeeting()` is true.

---

## 8. Actions (Business Logic)

### ProcessMeetingPayouts (`app/Actions/ProcessMeetingPayouts.php`)

**Signature:** `handle(Meeting $meeting, array $excludedUserIds = []): void`

**Step-by-step logic:**
1. Loads per-rank payout amounts from `SiteConfig` (keys: `meeting_payout_jr_crew`, `meeting_payout_crew_member`, `meeting_payout_officer`), cast to `int`.
2. Fetches user IDs with a submitted `MeetingReport` for this meeting (`submitted_at` not null).
3. Iterates over all attendees in the `meeting_user` pivot.
4. For each attendee, skips if a `MeetingPayout` record already exists (duplicate prevention).
5. Evaluates eligibility in order:
   - **No staff rank / rank None** → skipped, reason: "No staff rank"
   - **Rank payout amount = 0** → skipped, reason: "Rank payout disabled"
   - **In `$excludedUserIds`** → skipped, reason: "Excluded by manager"
   - **No submitted report** → skipped, reason: "Form not submitted"
   - **Officer + not attended** → skipped, reason: "Did not attend"
   - **No primary Minecraft account** → skipped, reason: "No Minecraft account"
   - **All checks pass** → eligible, proceeds to payout
6. Creates a `MeetingPayout` record for each user (status: `paid`, `skipped`, or `failed`).
7. For eligible users, calls `SendMinecraftCommand::run("money give {username} {amount}", 'meeting_payout', ...)` **synchronously** (`$async = false`) so RCON failures are caught inline.
8. On RCON exception: updates payout record to `status: 'failed'`, increments `$failedCount`. Does not re-throw — meeting completion is not blocked.
9. Logs activity: `RecordActivity::run($meeting, 'meeting_payouts_processed', "Meeting payouts: X paid, Y skipped, Z failed.")`

**Called by:**
- `manage-meeting.blade.php` → `CompleteMeetingConfirmed()` (line ~316)

---

### SendMinecraftCommand (`app/Actions/SendMinecraftCommand.php`)

**Signature:** `handle(string $command, string $commandType, ?string $target, ?User $user, array $meta, bool $async): void`

Called by `ProcessMeetingPayouts` with `$async = false` (synchronous). Issues `money give <PlayerName> <amount>` to the Minecraft RCON server.

In local environments, `$async` is forced to `false` regardless of the passed value.

**Called by:** `ProcessMeetingPayouts` with `commandType = 'meeting_payout'`

---

## 9. Notifications

Not applicable for this feature. No email or Pushover notifications are sent for Lumen payouts. Staff members receive their Lumens silently in-game through the economy plugin.

---

## 10. Background Jobs

No dedicated background jobs are created for this feature. RCON commands are dispatched synchronously (via `SendMinecraftCommand` with `$async = false`) so failures are caught and recorded on the `MeetingPayout` record during meeting completion.

In non-local environments, if `$async` were `true`, the command would be dispatched as a `MinecraftCommandNotification` queued notification — but for meeting payouts, synchronous execution is intentional.

---

## 11. Console Commands & Scheduled Tasks

Not applicable for this feature. No Artisan commands or scheduled tasks are associated with meeting payouts.

---

## 12. Services

### MinecraftRconService (indirect usage)

**File:** `app/Services/MinecraftRconService.php`

**Relevant method:** `executeCommand(string $command, string $commandType, ?string $target, ?User $user, array $meta): array`

Called by `SendMinecraftCommand` when `$async = false`. Returns `['success' => bool, 'response' => mixed, 'error' => mixed]`. Any exception thrown propagates to `ProcessMeetingPayouts`, which catches it and marks the payout as `'failed'`.

---

## 13. Activity Log Entries

| Action String | Logged By | Subject Model | Description |
|---------------|-----------|---------------|-------------|
| `meeting_payouts_processed` | ProcessMeetingPayouts | Meeting | `"Meeting payouts: X paid, Y skipped, Z failed."` |

---

## 14. Data Flow Diagrams

### Meeting Completion with Payouts

```text
Manager clicks "Complete Meeting" on Finalizing page
  -> Livewire: manage-meeting::CompleteMeeting()
    -> Flux::modal('complete-meeting-confirmation')->show()

Manager clicks "Complete Meeting" button in modal
  -> Livewire: manage-meeting::CompleteMeetingConfirmed()
    -> $this->authorize('update', $this->meeting)  [Meeting - Manager required]
    -> $this->meeting->completeMeeting()
    -> $this->meeting->save()
    -> ProcessMeetingPayouts::run($this->meeting, $this->excludedPayoutUserIds)
      -> For each attendee in meeting_user pivot:
         -> Create MeetingPayout (skipped) OR
         -> MinecraftRconService::executeCommand("money give {username} {amount}", ...)
            -> SUCCESS ($result['success'] == true):  MeetingPayout status = 'paid'
            -> FAILURE ($result['success'] == false): MeetingPayout status = 'failed' (meeting still completes)
      -> RecordActivity::run($meeting, 'meeting_payouts_processed', '...')
    -> Flux::modal('complete-meeting-confirmation')->close()
    -> Flux::modal('schedule-next-meeting')->show()
```

---

### Payout Preview: Manager Excludes a User

```text
Manager toggles exclude switch for a user in payout-preview table
  -> Livewire: payout-preview::toggleExclude(int $userId)
    -> $this->authorize('update', $this->meeting)  [Meeting - Manager required]
    -> Adds/removes $userId from $this->excludedUserIds
    -> $this->dispatch('payoutExcludedUsersChanged', excludedUserIds: [...])
      -> Livewire: manage-meeting::syncExcludedPayoutUsers(array $excludedUserIds)
        -> $this->excludedPayoutUserIds = $excludedUserIds
        (stored in parent for use during CompleteMeetingConfirmed)
```

---

### Completed Meeting: Viewing Payout Summary

```text
Staff member visits completed meeting page (GET /meetings/{meeting}/manage)
  -> Route: meeting.edit [auth middleware]
    -> manage-meeting component renders
      -> $meeting->isStaffMeeting() == true
        -> <livewire:meeting.payout-summary :meeting="$meeting" />
          -> payout-summary::payouts computed property
            -> $this->meeting->payouts()->with('user')->orderBy('id')->get()
          -> If payouts exist: renders summary card with counts + detail table
          -> If no payouts: renders empty <div> (no card shown)
```

---

## 15. Configuration

All payout amounts are stored in the `site_config` table, managed via the ACP Site Config page.

| Key | Default | Purpose |
|-----|---------|---------|
| `meeting_payout_jr_crew` | `'50'` | Lumen amount for Jr Crew; set to `'0'` to disable |
| `meeting_payout_crew_member` | `'75'` | Lumen amount for Crew Members; set to `'0'` to disable |
| `meeting_payout_officer` | `'100'` | Lumen amount for Officers; set to `'0'` to disable |

Values are cached by `SiteConfig::getValue()` for 5 minutes (`Cache::remember("site_config.{$key}", 300, ...)`). Changes via ACP take effect within 5 minutes.

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/Actions/Actions/ProcessMeetingPayoutsTest.php` | 13 | Core action eligibility logic, RCON failure handling, duplicate prevention, activity log |
| `tests/Feature/Livewire/PayoutPreviewTest.php` | 10 | Payout preview component rendering, eligibility display, exclude toggle behavior |
| `tests/Feature/Livewire/PayoutSummaryTest.php` | 9 | Payout summary component rendering, counts, status display, persistence |

### Test Case Inventory

#### ProcessMeetingPayoutsTest.php
- it pays a Jr Crew member who submitted the form
- it skips a Jr Crew member who did not submit the form
- it pays a Crew Member who submitted the form
- it skips a Crew Member who did not submit the form
- it pays an Officer who submitted the form and attended
- it skips an Officer who submitted the form but did not attend
- it skips an Officer who attended but did not submit the form
- it skips a user with no primary Minecraft account
- it skips a user when rank payout is set to 0
- it skips a user in the excluded list
- it marks payout as failed when RCON throws, but does not block completion
- it does not create duplicate payout records when called twice
- it records activity with correct counts

#### PayoutPreviewTest.php
- it renders the payout preview during finalizing for a staff meeting
- it hides the preview when all rank payout amounts are zero
- it shows an eligible user with payout amount
- it shows ineligible user grayed out with skip reason for missing form
- it shows ineligible Officer grayed out when did not attend
- it shows ineligible user when no Minecraft account
- it toggling an eligible user off adds them to the excluded list
- it toggling an excluded user back on removes them from the excluded list
- it dispatches payoutExcludedUsersChanged event when toggle changes
- it denies toggle to non-manager user

#### PayoutSummaryTest.php
- it renders payout summary when payout records exist
- it does not render when there are no payout records
- it shows correct paid count and total lumens
- it shows correct skipped count
- it shows correct failed count
- it shows paid status badge and amount for paid user
- it shows skipped status and reason for skipped user
- it shows failed status for failed user
- it summary data persists from database across reloads

### Coverage Gaps

- **No test for user with `StaffRank::None`** explicitly (the "No staff rank" skip path). The action has this path but it is not covered in tests.
- **No integration test** verifying that `CompleteMeetingConfirmed` passes `$excludedPayoutUserIds` to `ProcessMeetingPayouts`. The unit tests for the action and the component toggle are separate; their integration is untested.
- **No test for non-staff meeting** — the preview and summary components are gated behind `isStaffMeeting()` in the parent; this guard is not independently tested.
- **SiteConfig cache invalidation** is not tested — if a config value is changed, stale cache could serve old amounts for up to 5 minutes.

---

## 17. File Map

**Models:**
- `app/Models/MeetingPayout.php`
- `app/Models/Meeting.php` (payouts relationship, isStaffMeeting method)

**Enums:**
- `app/Enums/StaffRank.php`

**Actions:**
- `app/Actions/ProcessMeetingPayouts.php`
- `app/Actions/SendMinecraftCommand.php` (dependency)
- `app/Actions/RecordActivity.php` (dependency)

**Policies:**
- `app/Policies/MeetingPolicy.php`

**Gates:** `AuthServiceProvider.php` — no custom gates; authorization through MeetingPolicy

**Notifications:** None specific to this feature

**Jobs:** None specific to this feature

**Services:**
- `app/Services/MinecraftRconService.php` (called by SendMinecraftCommand)

**Controllers:** None (managed-meeting uses Volt routing)

**Volt Components:**
- `resources/views/livewire/meeting/payout-preview.blade.php`
- `resources/views/livewire/meeting/payout-summary.blade.php`
- `resources/views/livewire/meetings/manage-meeting.blade.php` (modified)

**Routes:**
- `meeting.edit` → `GET /meetings/{meeting}/manage`

**Migrations:**
- `database/migrations/2026_03_22_000001_create_meeting_payouts_table.php`
- `database/migrations/2026_03_22_000002_seed_meeting_payout_site_config.php`

**Console Commands:** None

**Tests:**
- `tests/Feature/Actions/Actions/ProcessMeetingPayoutsTest.php`
- `tests/Feature/Livewire/PayoutPreviewTest.php`
- `tests/Feature/Livewire/PayoutSummaryTest.php`

**Config:**
- `site_config` table keys: `meeting_payout_jr_crew`, `meeting_payout_crew_member`, `meeting_payout_officer`

---

## 18. Known Issues & Improvement Opportunities

1. **Excluded users bypass form-submission check order.** The eligibility logic checks `$excludedUserIds` before "form not submitted," meaning a user excluded by the manager gets a `MeetingPayout` record with `amount` set to the rank's configured value (not 0), even though they were excluded. This is cosmetically odd (a skipped record with a non-zero amount) but functionally correct. Consider setting `amount = 0` for manager-excluded records.

2. **SiteConfig cache lag.** Payout amounts are cached for 5 minutes. If an admin changes an amount in ACP immediately before meeting completion, the old amount may be used. This is unlikely in practice but worth noting.

3. **No retry mechanism for failed payouts.** RCON failures mark the record as `'failed'` with no retry path. If the Minecraft server is temporarily unavailable during meeting completion, those users must be paid manually. The PRD explicitly calls this out of scope for v1.

4. **No payout notifications to staff.** Users receive Lumens silently in-game. There is no in-app notification, email, or Discord message. If a player is offline when the payout runs, they will see the Lumens next time they join — but they may not know why they received them.

5. **Non-staff-meeting guard only at the view layer.** `ProcessMeetingPayouts` has no guard against being called for a non-staff meeting. If called on a Community Meeting, it would attempt to process attendees. The guard (`$meeting->isStaffMeeting()`) is only enforced in the Livewire UI, not in the action itself.

6. **N+1 risk in `attendeesWithEligibility` computed property.** The payout-preview component calls `$attendee->primaryMinecraftAccount()` inside a `map()` loop over the attendees collection. The attendees are fetched without eager-loading their minecraft accounts, resulting in one query per attendee. For meetings with many staff, this could be significant. Consider eager-loading `minecraftAccounts` on the attendees query.

7. ~~**`payout-preview` state lost on Livewire poll.**~~ **Resolved.** The `payout-preview` component is rendered outside the `wire:poll` div in `manage-meeting.blade.php`, so Livewire's 30-second poll does not re-mount it. `$excludedUserIds` in the child and `$excludedPayoutUserIds` in the parent persist across poll cycles. Exclusions only reset on a full-page refresh.
