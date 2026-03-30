# Ticket Escalation — Technical Documentation

> **Audience:** Project owner, developers, AI agents
> **Generated:** 2026-03-29
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

The Ticket Escalation feature automatically alerts designated staff members when a support ticket goes unassigned for longer than a configurable threshold. Without this feature, unassigned tickets are only visible to staff who actively check the dashboard — time-sensitive requests (e.g., new members awaiting server access) can sit ignored for hours.

The system runs a background job every minute. For each open, unassigned ticket whose `created_at` exceeds the configured threshold (default 30 minutes), it sends a `TicketEscalationNotification` to every user holding the `Ticket Escalation - Receiver` role, posts a visible system message in the ticket thread so participants know action is being taken, and stamps `escalated_at` on the thread to prevent repeat notifications.

Escalation recipients are staff members who have been explicitly assigned the `Ticket Escalation - Receiver` role — typically Command officers, who receive it automatically via their "All Roles" staff position. The role can also be granted temporarily to other staff (e.g., a Quartermaster covering Engineering). The threshold and role are both configurable without code changes.

Key concepts:
- **Escalation threshold:** The number of minutes before a ticket is considered overdue. Configured via `ticket_escalation_threshold_minutes` in Site Config (default: 30).
- **Escalation lock:** The `escalated_at` column on `threads`. Null = not yet escalated; populated = already escalated. Reset to null when a ticket is unassigned, allowing re-escalation.
- **`Ticket Escalation - Receiver` role:** The permission role that determines who receives escalation notifications.
- **`receive-ticket-escalations` gate:** The authorization gate used to query eligible recipients.

---

## 2. Database Schema

### `threads` table (escalation-related columns)

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `escalated_at` | timestamp | YES | NULL | Populated when escalation fires; null means not yet escalated or has been reset |

All other `threads` columns pre-exist this feature. The `assigned_to_user_id` column (also on `threads`) controls whether a ticket is eligible for escalation (must be NULL).

**Migration:** `database/migrations/2026_03_29_131834_add_escalated_at_to_threads_table.php`

### `roles` table (escalation role)

| Column | Value |
|--------|-------|
| `name` | `Ticket Escalation - Receiver` |
| `description` | `Receive escalation notifications when a ticket goes unassigned past the configured threshold` |
| `color` | `orange` |
| `icon` | `bell-alert` |

**Migration:** `database/migrations/2026_03_29_141401_seed_ticket_escalation_receiver_role.php`

### `site_config` table (escalation threshold)

| Key | Default Value | Description |
|-----|--------------|-------------|
| `ticket_escalation_threshold_minutes` | `30` | Minutes before an unassigned open ticket is escalated to Ticket Escalation - Receiver role holders (0 to disable) |

**Migration:** `database/migrations/2026_03_29_141421_seed_ticket_escalation_threshold_site_config.php`

---

## 3. Models & Relationships

### Thread (`app/Models/Thread.php`)

The `Thread` model is the primary entity modified by this feature. Only escalation-relevant fields are documented here; the full model has many additional columns.

**Escalation-relevant `$fillable` fields:**
- `escalated_at`

**Escalation-relevant `$casts`:**
- `escalated_at` => `datetime`

**Escalation-relevant columns:**
- `assigned_to_user_id` — must be `NULL` for a ticket to be eligible for escalation
- `escalated_at` — null until first escalation fires; reset to null on unassign; set to `now()` after escalation

No new relationships were added for this feature.

---

## 4. Enums Reference

No new enums were added for this feature. Existing enums used:

### ThreadStatus (`app/Enums/ThreadStatus.php`)

| Case | Value | Notes |
|------|-------|-------|
| `Open` | `open` | Only Open tickets are eligible for escalation |
| `Pending` | `pending` | Not eligible |
| `Resolved` | `resolved` | Not eligible |
| `Closed` | `closed` | Not eligible |

### ThreadType (`app/Enums/ThreadType.php`)

| Case | Value | Notes |
|------|-------|-------|
| `Ticket` | `ticket` | Only Ticket-type threads are escalated |
| Others | various | Not eligible |

### MessageKind (`app/Enums/MessageKind.php`)

| Case | Notes |
|------|-------|
| `System` | Used for the escalation system message posted in the thread |

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

| Gate Name | Who Can Pass | Logic Summary |
|-----------|-------------|---------------|
| `receive-ticket-escalations` | Users with `Ticket Escalation - Receiver` role | `$user->hasRole('Ticket Escalation - Receiver')` — also passes for admins and users with "All Roles" staff position |

The `receive-ticket-escalations` gate is **not enforced on a request** — it is used internally by `EscalateUnassignedTickets` to filter the recipient list at notification time.

### Role Assignment

The `Ticket Escalation - Receiver` role is assigned to users via the standard role assignment UI (ACP → Staff Management). Officers with "All Roles" positions (`has_all_roles_at IS NOT NULL`) pass the gate automatically without explicit role assignment.

### Policies

Not applicable — no new policy was added for this feature.

### Permissions Matrix

| User Type | Receives escalation notification | Passes `receive-ticket-escalations` gate | Can configure threshold |
|-----------|----------------------------------|------------------------------------------|------------------------|
| Regular user / Stowaway | No | No | No |
| Staff (no role) | No | No | No |
| Staff with `Ticket Escalation - Receiver` role | Yes | Yes | No |
| Officer (All Roles position) | Yes | Yes | No |
| Admin | Yes | Yes | Yes (via ACP) |
| Site Config - Manager role | No* | No* | Yes (via ACP) |

*Site Config managers can change the threshold but won't receive notifications unless they also hold the Ticket Escalation - Receiver role.

---

## 6. Routes

No new routes were added for this feature. Escalation is entirely driven by a background job. The existing ticket view route (`GET /tickets/{thread}`) is referenced in notification payloads.

| Method | URL | Handler | Route Name |
|--------|-----|---------|------------|
| GET | `/tickets/{thread}` | Volt: `ready-room.tickets.view-ticket` | `tickets.show` |

---

## 7. User Interface Components

### View Ticket (`resources/views/livewire/ready-room/tickets/view-ticket.blade.php`)

This component was modified — the `assignTo(?int $userId)` method now also clears `escalated_at` when a ticket is unassigned.

**Change:** When `$userId === null` (unassign), the update call is:
```php
$this->thread->update(['assigned_to_user_id' => null, 'escalated_at' => null]);
```

**Reason:** Resetting `escalated_at` allows a re-orphaned ticket to escalate again once the threshold passes. Without this reset, a ticket that was escalated, then assigned, then unassigned again would never escalate a second time.

**No UI changes** — this modification is purely server-side. The escalation system message posted in the thread is visible to all participants in the ticket view as a `MessageKind::System` message.

### Admin Control Panel — Site Config
**File:** `resources/views/livewire/admin-manage-site-configs-page.blade.php`

The `ticket_escalation_threshold_minutes` config key is automatically surfaced in the ACP's Site Settings table (no code changes needed — the table renders all `SiteConfig` rows). Administrators with the `manage-site-config` gate can click the edit button to update the threshold value.

---

## 8. Actions (Business Logic)

No new Action classes were created for this feature. Logic lives in the job directly. The escalation reset on unassign is a one-liner in the Volt component.

Not applicable for standalone actions.

---

## 9. Notifications

### TicketEscalationNotification (`app/Notifications/TicketEscalationNotification.php`)

**Triggered by:** `EscalateUnassignedTickets::handle()` — once per eligible ticket per run
**Recipient:** All users who pass the `receive-ticket-escalations` gate
**Channels:** mail, Pushover (if user has key), Discord (if user has linked account)
**Category:** `staff_alerts` (respects each user's Staff Alerts channel preferences)
**Mail subject:** `Unassigned Ticket Alert: {thread.subject}`
**Mail template:** `resources/views/mail/ticket-escalation.blade.php`
**Mail content:** Thread subject, department label, creator name, "View Ticket" button
**Pushover title:** `Unassigned Ticket Alert`
**Pushover message:** Thread subject with URL
**Discord message:** `**Unassigned Ticket Alert:** {subject}\n**Department:** {dept}\n**From:** {creator}\n{url}`
**Queued:** Yes (`ShouldQueue`)

The notification follows the exact same channel-selection pattern as `NewTicketNotification`, delegating channel determination to `TicketNotificationService::send()`.

---

## 10. Background Jobs

### EscalateUnassignedTickets (`app/Jobs/EscalateUnassignedTickets.php`)

**Triggered by:** Laravel scheduler — every minute (see `routes/console.php`)
**Queue:** Default queue via `ShouldQueue`

**Step-by-step logic:**
1. Reads `ticket_escalation_threshold_minutes` from `SiteConfig` (default: `30`)
2. Queries `threads` for: `type = Ticket`, `status = Open`, `assigned_to_user_id IS NULL`, `escalated_at IS NULL`, `created_at <= now() - threshold`
3. If no matching tickets, returns immediately (no DB queries for users)
4. Fetches all users and filters to those passing `receive-ticket-escalations` gate
5. Fetches the system user (`email = system@lighthouse.local`)
6. For each eligible ticket:
   a. Sends `TicketEscalationNotification` to each recipient via `staff_alerts` category
   b. If system user exists: creates a `MessageKind::System` message in the thread
   c. Sets `escalated_at = now()` on the ticket (prevents re-escalation)

**Idempotency:** The `escalated_at IS NULL` filter ensures running the job twice in the same minute does not double-notify.

**Performance note:** Step 4 uses `User::all()->filter(...)` — acceptable for a small community application but would need a DB-query approach at scale (see Known Issues).

---

## 11. Console Commands & Scheduled Tasks

No new Artisan commands were added. The job is registered directly in the scheduler.

### Scheduled Job: `EscalateUnassignedTickets`
**File:** `routes/console.php`
**Schedule:** Every minute (`everyMinute()`)
**Registration:**
```php
Schedule::job(new \App\Jobs\EscalateUnassignedTickets)
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();
```

---

## 12. Services

### TicketNotificationService (`app/Services/TicketNotificationService.php`)

This pre-existing service is called by `EscalateUnassignedTickets` with the `staff_alerts` category. No changes were made to the service itself.

**Relevant method:**
- `send(User $user, Notification $notification, string $category = 'tickets'): void` — determines channels based on user preferences for the given category, increments Pushover count if needed, and dispatches the notification.

---

## 13. Activity Log Entries

No new activity log entries are created by this feature. The existing `assignment_changed` activity log entry (from `RecordActivity::run()` in `view-ticket.blade.php`) is unchanged — the escalation reset on unassign does not add a new log entry.

Not applicable for escalation-specific activity logging.

---

## 14. Data Flow Diagrams

### Escalation Job (Automatic — every minute)

```
Laravel Scheduler fires every minute
  -> EscalateUnassignedTickets::handle(TicketNotificationService)
    -> SiteConfig::getValue('ticket_escalation_threshold_minutes', '30')
    -> Thread::where(type=Ticket, status=Open, assigned_to_user_id=null,
                     escalated_at=null, created_at <= now()-threshold)->get()
    -> [return if empty]
    -> User::all()->filter(can('receive-ticket-escalations'))
    -> User::where('email','system@lighthouse.local')->first()
    -> foreach ticket:
         foreach recipient:
           -> TicketNotificationService::send($recipient,
                new TicketEscalationNotification($ticket), 'staff_alerts')
             -> determineChannels($user, 'staff_alerts')
             -> $notification->setChannels([...])
             -> $user->notify($notification)  [queued]
               -> toMail() -> "Unassigned Ticket Alert: {subject}" email
               -> toPushover() -> push to user's Pushover key
               -> toDiscord() -> DM in user's linked Discord
         -> Message::create([thread_id, user_id=systemUser, body='...escalated...', kind=System])
         -> $ticket->update(['escalated_at' => now()])
```

### Unassign Reset (Staff action in ticket view)

```
Staff clicks "Unassign" in ticket view
  -> POST /livewire/update (Livewire request)
    -> view-ticket@assignTo(null)
      -> $this->authorize('assign', $this->thread)
      -> $this->thread->update(['assigned_to_user_id' => null, 'escalated_at' => null])
      -> RecordActivity::run($thread, 'assignment_changed', 'Assignment removed: ...')
      -> [cache clearing for all participants]
      -> Flux::toast('Ticket unassigned successfully!', variant: 'success')
  -> Thread now eligible for re-escalation once threshold passes again
```

---

## 15. Configuration

| Key | Location | Default | Purpose |
|-----|----------|---------|---------|
| `ticket_escalation_threshold_minutes` | `site_config` table / ACP | `30` | Minutes before an unassigned open ticket triggers escalation. Set to `0` or a negative value to disable escalation entirely (values `<= 0` cause the job to return early without processing). |

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/Jobs/EscalateUnassignedTicketsTest.php` | 13 | Full job behavior |
| `tests/Feature/Tickets/ViewTicketTest.php` | 2 (new) | `escalated_at` reset on unassign |
| `tests/Feature/Gates/RoleBasedGatesTest.php` | 4 (new) | `receive-ticket-escalations` gate |

### Test Case Inventory

**`EscalateUnassignedTicketsTest.php`:**
- `it sends escalation notification to users with Ticket Escalation - Receiver role`
- `it sets escalated_at after sending notification`
- `it does not escalate already-assigned tickets`
- `it does not escalate closed tickets`
- `it does not escalate resolved tickets`
- `it does not re-escalate already-escalated tickets`
- `it does not escalate tickets created within the threshold window`
- `it respects the ticket_escalation_threshold_minutes site config`
- `it sends to multiple recipients when multiple users have the role`
- `it posts a system message in the thread when escalating`
- `it is idempotent — running twice does not double-escalate`
- `it does not escalate non-ticket thread types`
- `it does not escalate when threshold is set to 0 (escalation disabled)`

**`ViewTicketTest.php` (new tests):**
- `it resets escalated_at to null when ticket is unassigned #419`
- `it does not reset escalated_at when ticket is assigned to a user #419`

**`RoleBasedGatesTest.php` (new tests):**
- `it grants receive-ticket-escalations to user with Ticket Escalation - Receiver role`
- `it denies receive-ticket-escalations without Ticket Escalation - Receiver role`
- `it grants receive-ticket-escalations to admin`
- `it grants receive-ticket-escalations to user with allow-all position`

### Coverage Gaps

- **No test for "zero recipients"** — if no user holds the `Ticket Escalation - Receiver` role, the job silently sends nothing. This is correct behavior but not explicitly tested.
- **Pending ticket status** — not tested explicitly (the job only targets `Open`). This is covered implicitly by the closed/resolved tests but `Pending` is a distinct status.

---

## 17. File Map

**Models:**
- `app/Models/Thread.php` (modified: `escalated_at` added to `$fillable` and `$casts`)
- `app/Models/SiteConfig.php` (unchanged, used for threshold lookup)
- `app/Models/User.php` (unchanged, `hasRole()` drives gate)
- `app/Models/Message.php` (unchanged, system message created via `Message::create()`)

**Enums:**
- `app/Enums/ThreadStatus.php` (used, unchanged)
- `app/Enums/ThreadType.php` (used, unchanged)
- `app/Enums/MessageKind.php` (used, unchanged)

**Actions:** None added.

**Policies:** None added.

**Gates:** `app/Providers/AuthServiceProvider.php` — gate: `receive-ticket-escalations`

**Notifications:**
- `app/Notifications/TicketEscalationNotification.php` (new)

**Jobs:**
- `app/Jobs/EscalateUnassignedTickets.php` (new)

**Services:**
- `app/Services/TicketNotificationService.php` (used, unchanged)

**Controllers:** None.

**Volt Components:**
- `resources/views/livewire/ready-room/tickets/view-ticket.blade.php` (modified: `escalated_at` reset on unassign)
- `resources/views/livewire/admin-manage-site-configs-page.blade.php` (unchanged, surfaces config automatically)

**Routes:**
- `routes/console.php` (modified: `EscalateUnassignedTickets` registered every minute)
- `routes/web.php` (unchanged, `tickets.show` route used in notification URLs)

**Migrations:**
- `database/migrations/2026_03_29_131834_add_escalated_at_to_threads_table.php` (new)
- `database/migrations/2026_03_29_141401_seed_ticket_escalation_receiver_role.php` (new)
- `database/migrations/2026_03_29_141421_seed_ticket_escalation_threshold_site_config.php` (new)

**Mail views:**
- `resources/views/mail/ticket-escalation.blade.php` (new)

**Tests:**
- `tests/Feature/Jobs/EscalateUnassignedTicketsTest.php` (new, 13 tests)
- `tests/Feature/Tickets/ViewTicketTest.php` (modified, +2 tests)
- `tests/Feature/Gates/RoleBasedGatesTest.php` (modified, +4 tests)

**Config:** `ticket_escalation_threshold_minutes` in `site_config` table (managed via ACP)

---

## 18. Known Issues & Improvement Opportunities

1. **`User::all()->filter(...)` in the job** — `EscalateUnassignedTickets` loads all users into memory to filter by gate. For a small community this is fine, but at scale a DB-level query joining `staff_positions`, `role_staff_position`, and `roles` would be more efficient. A HITL issue was noted in PRD #416's out-of-scope section.

2. **No ACP validation for threshold** — The `ticket_escalation_threshold_minutes` config has a `<= 0` guard in the job (returns early, disabling escalation), but there is no validation on the ACP input field itself. An admin could set an unexpectedly large value (e.g., `99999`) with no warning, effectively suppressing escalation without realizing it.

3. **No Pending status escalation** — Tickets with `status = Pending` are not escalated even if they are unassigned and old. The PRD explicitly targets `Open` status only; this is intentional design, not a bug, but worth noting for future reviewers.

4. **System message requires system user** — If `User::where('email', 'system@lighthouse.local')->first()` returns null (e.g., on a fresh install without running `seed_system_user` migration), the escalation system message is silently skipped. The notification is still sent. This is consistent with how other features handle missing system user.

5. **No escalation history UI** — Once `escalated_at` is set, there is no UI to see when a ticket was escalated or how many times it has been re-escalated after unassign/reassign cycles. This is listed as out-of-scope in PRD #416.

6. **Escalation resets on any unassign, not just staff unassigns** — The escalation reset in `view-ticket.blade.php` fires whenever `assignTo(null)` is called. If the unassign path is ever called programmatically (e.g., by a future action), escalation would reset without the operator intending it.
