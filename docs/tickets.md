# Ticket System Documentation

> **Audience:** Project managers, developers, and AI agents.
> **Last updated:** 2026-02-27

---

## Table of Contents

1. [Overview](#overview)
2. [Database Schema](#database-schema)
3. [Enums Reference](#enums-reference)
4. [Authorization & Permissions](#authorization--permissions)
5. [Ticket Lifecycle Flows](#ticket-lifecycle-flows)
6. [Auto-Assignment Logic](#auto-assignment-logic)
7. [Notification System](#notification-system)
8. [Caching Strategy](#caching-strategy)
9. [Routes & Middleware](#routes--middleware)
10. [File Map](#file-map)
11. [Business Rules](#business-rules)
12. [Test Coverage Map](#test-coverage-map)

---

## Overview

The ticket system is the primary communication channel between community members and staff. It is built on a generic **Thread → Message** model that also supports Direct Messages and Forum threads (reserved for future use), but currently only the **Ticket** type is active.

### Three Ticket Subtypes

| Subtype | Created By | Purpose |
|---------|-----------|---------|
| **Support** | Any non-brig user | General support requests directed to a staff department |
| **Admin Action** | Staff (CrewMember+) | Staff-initiated tickets about a specific user (e.g. discipline, account issues) |
| **Moderation Flag** | System (automated) | Created when a user flags a message; routed to the Quartermaster department for review |

### Core Concepts

- **Participants** — Users who are part of a ticket. Participants receive notifications on new replies.
- **Viewers** — Users who can see a ticket but are not full participants. They do NOT receive notifications. When a viewer replies, they are promoted to a full participant.
- **Internal Notes** — Staff-only messages within a ticket that are invisible to non-staff participants.
- **Message Flags** — A moderation tool where participants can flag inappropriate messages for Quartermaster review.

---

## Database Schema

### `threads` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint (PK) | No | auto | |
| `type` | string | No | — | `ticket`, `dm`, `forum` (indexed) |
| `subtype` | string | No | — | `support`, `admin_action`, `moderation_flag` (indexed) |
| `department` | string | Yes | null | StaffDepartment enum value (indexed) |
| `subject` | string | No | — | Max 255 chars |
| `status` | string | No | — | `open`, `pending`, `resolved`, `closed` (indexed) |
| `created_by_user_id` | foreignId | No | — | FK → `users.id` |
| `assigned_to_user_id` | foreignId | Yes | null | FK → `users.id` |
| `is_flagged` | boolean | No | false | True if any message has been flagged (indexed) |
| `has_open_flags` | boolean | No | false | True if unacknowledged flags exist (indexed) |
| `last_message_at` | timestamp | Yes | null | Updated on every new message (indexed) |
| `created_at` | timestamp | No | auto | |
| `updated_at` | timestamp | No | auto | |

**Migration:** `database/migrations/2026_02_12_214853_create_threads_table.php`

### `messages` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint (PK) | No | auto | |
| `thread_id` | foreignId | No | — | FK → `threads.id` (cascade delete) |
| `user_id` | foreignId | No | — | FK → `users.id` |
| `body` | text | No | — | |
| `kind` | string | No | `message` | `message`, `system`, `internal_note` |
| `created_at` | timestamp | No | auto | |
| `updated_at` | timestamp | No | auto | |

**Indexes:** Composite on `(thread_id, created_at)`

**Migration:** `database/migrations/2026_02_12_214857_create_messages_table.php`

### `thread_participants` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint (PK) | No | auto | |
| `thread_id` | foreignId | No | — | FK → `threads.id` (cascade delete) |
| `user_id` | foreignId | No | — | FK → `users.id` |
| `is_viewer` | boolean | No | false | Viewers don't get notifications |
| `last_read_at` | timestamp | Yes | null | Used for unread tracking |
| `created_at` | timestamp | No | auto | |
| `updated_at` | timestamp | No | auto | |

**Constraints:** Unique composite on `(thread_id, user_id)`. Index on `user_id`.

**Migrations:**
- `database/migrations/2026_02_12_214859_create_thread_participants_table.php`
- `database/migrations/2026_02_14_070835_add_is_viewer_to_thread_participants_table.php`

### `message_flags` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint (PK) | No | auto | |
| `message_id` | foreignId | No | — | FK → `messages.id` (cascade delete) |
| `thread_id` | foreignId | No | — | FK → `threads.id` (cascade delete) |
| `flagged_by_user_id` | foreignId | No | — | FK → `users.id` |
| `note` | text | No | — | Reason for flagging (min 10 chars enforced in UI) |
| `status` | string | No | `new` | `new` or `acknowledged` |
| `reviewed_by_user_id` | foreignId | Yes | null | FK → `users.id` |
| `reviewed_at` | timestamp | Yes | null | When flag was acknowledged |
| `staff_notes` | text | Yes | null | Staff notes on acknowledgment |
| `flag_review_ticket_id` | foreignId | Yes | null | FK → `threads.id` (the auto-created moderation ticket) |
| `created_at` | timestamp | No | auto | |
| `updated_at` | timestamp | No | auto | |

**Indexes:** Composite on `(message_id, status)` and `(thread_id, status)`.

**Migration:** `database/migrations/2026_02_12_214903_create_message_flags_table.php`

---

## Enums Reference

### ThreadType (`app/Enums/ThreadType.php`)

| Case | Value | Label |
|------|-------|-------|
| `Ticket` | `ticket` | Ticket |
| `DirectMessage` | `dm` | Direct Message |
| `Forum` | `forum` | Forum |

Only `Ticket` is currently used in production.

### ThreadStatus (`app/Enums/ThreadStatus.php`)

| Case | Value | Label | Notes |
|------|-------|-------|-------|
| `Open` | `open` | Open | Default for new tickets |
| `Pending` | `pending` | Pending | Waiting on additional info |
| `Resolved` | `resolved` | Resolved | Non-staff can set this when closing |
| `Closed` | `closed` | Closed | Staff-only final state; replies disabled |

### ThreadSubtype (`app/Enums/ThreadSubtype.php`)

| Case | Value | Label |
|------|-------|-------|
| `Support` | `support` | Support |
| `AdminAction` | `admin_action` | Admin Action |
| `ModerationFlag` | `moderation_flag` | Moderation Flag |

### MessageKind (`app/Enums/MessageKind.php`)

| Case | Value | Label | Visibility |
|------|-------|-------|------------|
| `Message` | `message` | Message | All participants |
| `System` | `system` | System | All participants |
| `InternalNote` | `internal_note` | Internal Note | Staff only (CrewMember+) |

### MessageFlagStatus (`app/Enums/MessageFlagStatus.php`)

| Case | Value | Label |
|------|-------|-------|
| `New` | `new` | New |
| `Acknowledged` | `acknowledged` | Acknowledged |

### StaffDepartment (`app/Enums/StaffDepartment.php`)

| Case | Value | Label |
|------|-------|-------|
| `Command` | `command` | Command |
| `Chaplain` | `chaplain` | Chaplain |
| `Engineer` | `engineer` | Engineer |
| `Quartermaster` | `quartermaster` | Quartermaster |
| `Steward` | `steward` | Steward |

### StaffRank (`app/Enums/StaffRank.php`)

| Case | Value (int) | Label |
|------|-------------|-------|
| `None` | 0 | None |
| `JrCrew` | 1 | Junior Crew Member |
| `CrewMember` | 2 | Crew Member |
| `Officer` | 3 | Officer |

The `isAtLeastRank()` method on User compares these integer values.

### EmailDigestFrequency (`app/Enums/EmailDigestFrequency.php`)

| Case | Value | Label |
|------|-------|-------|
| `Immediate` | `immediate` | Immediate |
| `Daily` | `daily` | Daily Digest |
| `Weekly` | `weekly` | Weekly Digest |

---

## Authorization & Permissions

Authorization is enforced through **policies only** — never through scattered `@if` checks in Blade templates. Two policies govern the ticket system.

### ThreadPolicy (`app/Policies/ThreadPolicy.php`)

**`before()` hook:** Admins and Command Officers (Command department + Officer rank) bypass all checks and can do everything.

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `viewAll` | Command Officers, Admins | Via `before()` hook only |
| `viewDepartment` | Staff (CrewMember+) | Must have an assigned `staff_department` |
| `viewFlagged` | Quartermaster staff (CrewMember+) | Department must be Quartermaster |
| `view` | Varies | Delegates to `Thread::isVisibleTo($user)` — see below |
| `create` | Any authenticated user | Must NOT be in the brig |
| `createAsStaff` | Staff (CrewMember+) | For creating Admin Action tickets |
| `reply` | Participants | Must pass `view` check on the thread |
| `internalNotes` | Staff (CrewMember+) | Must also pass `view` check |
| `changeStatus` | Staff (CrewMember+) | Must also pass `view` check |
| `assign` | Officers+ | Must also pass `view` check |
| `reroute` | Officers+ | Must also pass `view` check |
| `close` | Staff OR ticket creator | Staff: CrewMember+ who can view. Non-staff: only their own support tickets |

**`Thread::isVisibleTo(User $user)`** returns true if ANY of:
1. User can `viewAll` threads (Command Officers, Admins)
2. User can `viewFlagged` AND `thread->is_flagged` is true (Quartermaster)
3. User can `viewDepartment` AND `thread->department` matches their `staff_department`
4. User is a participant (viewer or full participant) on the thread

### MessagePolicy (`app/Policies/MessagePolicy.php`)

**`before()` hook:** Same as ThreadPolicy — Admins and Command Officers bypass all checks.

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `view` | Varies | Cannot view `InternalNote` messages unless staff (CrewMember+); otherwise delegates to thread visibility |
| `flag` | Participants | Cannot flag `System` messages; cannot flag own messages; must be a thread participant |

### Permissions Matrix

| User Type | View Own | View Dept | View Flagged | Create Support | Create Admin | Reply | Internal Notes | Change Status | Assign | Flag Messages | Acknowledge Flags |
|-----------|----------|-----------|--------------|----------------|--------------|-------|----------------|---------------|--------|---------------|-------------------|
| Regular (in brig) | Yes* | No | No | **No** | No | Yes* | No | No | No | Yes* | No |
| Regular (not in brig) | Yes | No | No | Yes | No | Yes | No | No | No | Yes | No |
| Staff (CrewMember) | Yes | **Own dept** | No | Yes | Yes | Yes | Yes | Yes | No | Yes | No |
| Staff (Officer) | Yes | **Own dept** | No | Yes | Yes | Yes | Yes | Yes | **Yes** | Yes | No |
| Quartermaster (CrewMember) | Yes | No** | **Yes** | Yes | Yes | Yes | Yes | Yes | No | Yes | **Yes** |
| Quartermaster (Officer) | Yes | No** | **Yes** | Yes | Yes | Yes | Yes | Yes | **Yes** | Yes | **Yes** |
| Command Officer | **All** | **All** | **Yes** | Yes | Yes | Yes | Yes | Yes | **Yes** | Yes | **Yes** |
| Admin | **All** | **All** | **Yes** | Yes | Yes | Yes | Yes | Yes | **Yes** | Yes | **Yes** |

\* Brig users can still view/reply to tickets they're already a participant on, but cannot create new tickets.
\** Quartermaster `viewDepartment` shows only Quartermaster-department tickets; `viewFlagged` shows flagged tickets from any department.

---

## Ticket Lifecycle Flows

### Creating a Support Ticket

**Component:** `resources/views/livewire/ready-room/tickets/create-ticket.blade.php`

1. User navigates to `/tickets/create`
2. Fills in: **Subject** (required, max 255), **Department** (required, defaults to Command), **Message** (required, min 10 chars)
3. On submit:
   - Thread created: `type=Ticket`, `subtype=Support`, `status=Open`, `last_message_at=now()`
   - Creator added as participant (`is_viewer=false`)
   - First Message created with `kind=Message`
   - Activity logged: `ticket_opened`
   - **All staff in the selected department** (except the creator) are notified via `NewTicketNotification`
   - User redirected to `/tickets/{id}`

### Creating an Admin Action Ticket

**Component:** `resources/views/livewire/ready-room/tickets/create-admin-ticket.blade.php`

1. Staff navigates to `/tickets/create-admin` (optionally with `?user_id=` to pre-fill target)
2. Selects **Target User**, fills in Subject, Department, Message
3. On submit:
   - Thread created: `type=Ticket`, `subtype=AdminAction`, `status=Open`
   - **Both target user and creator** added as participants
   - First Message created
   - Activity logged: `ticket_opened`
   - **Target user notified** via `NewTicketNotification`
   - **Department staff notified** (except creator) via `NewTicketNotification`
   - Redirect to ticket view

### Replying to a Ticket

**Component:** `resources/views/livewire/ready-room/tickets/view-ticket.blade.php`

1. User types reply in the text area
2. Optionally checks "Internal Note (Staff Only)" if staff
3. On submit (`sendReply()`):
   - Message created with `kind=Message` or `kind=InternalNote`
   - If replier was a **viewer**, they are promoted to full participant; `ticket_joined` activity logged
   - Replier's `last_read_at` updated to now
   - Thread's `last_message_at` updated to now
   - [Auto-assignment check](#auto-assignment-logic) runs
   - All **non-viewer participants except the replier** are notified via `NewTicketReplyNotification`
   - Internal notes do **NOT** trigger participant notifications
   - All participant ticket caches cleared

### Changing Status

1. Staff selects new status from dropdown (Open, Pending, Resolved, Closed)
2. Thread status updated
3. Activity logged: `status_changed` with old→new values
4. All participant caches cleared

### Assigning a Ticket

1. Officer selects assignee from staff dropdown (or "Unassigned")
2. If assigning to a user:
   - Validates the user exists and has a `staff_rank`
   - Updates `assigned_to_user_id`
   - Activity logged: `assignment_changed` with old→new assignee names
   - **New assignee notified** (unless assigning to self) via `TicketAssignedNotification`
   - **Ticket creator notified** (if different from new assignee) via `TicketAssignedNotification`
3. If unassigning:
   - Sets `assigned_to_user_id = null`
   - Activity logged
4. Caches cleared for all participants and assignees

### Closing a Ticket

1. User clicks "Close Ticket" (optionally with a final reply message)
2. If there's text in the reply box, it's sent as a regular reply first
3. Status set based on user role:
   - **Staff** → `Closed`
   - **Non-staff** → `Resolved`
4. System message created: `"{Name} closed this ticket"` or `"{Name} marked this ticket as resolved"`
5. Thread's `last_message_at` updated
6. Activity logged: `status_changed`
7. All participant caches cleared

### Flagging a Message

**Action:** `app/Actions/FlagMessage.php` (wrapped in a DB transaction)

1. Participant clicks flag icon on a message, enters reason (min 10 chars)
2. `FlagMessage::run()` executes:
   - `MessageFlag` created with `status=New`
   - Original thread updated: `is_flagged=true`, `has_open_flags=true`
   - New **Moderation Flag ticket** created:
     - `type=Ticket`, `subtype=ModerationFlag`, `department=Quartermaster`
     - `subject="Flag Review: {original subject}"`
     - `created_by_user_id` = system user (`system@lighthouse.local`)
   - System message created in the review ticket with escaped details (link to original ticket, flag ID, flagger, reason, original message body)
   - Flag linked to review ticket via `flag_review_ticket_id`
   - Activity logged on original thread: `message_flagged`
   - **Quartermaster staff notified** via `MessageFlaggedNotification`

### Acknowledging a Flag

**Action:** `app/Actions/AcknowledgeFlag.php`

1. Quartermaster views the moderation flag review ticket
2. Clicks "Acknowledge Flag", optionally enters staff notes
3. `AcknowledgeFlag::run()` executes:
   - Flag updated: `status=Acknowledged`, `reviewed_by_user_id`, `reviewed_at=now()`, `staff_notes`
   - Original thread's `has_open_flags` recalculated (true only if any remaining `New` status flags exist)
   - Activity logged on original thread: `flag_acknowledged`

---

## Auto-Assignment Logic

When a reply is sent via `processReply()`, the system checks whether to auto-assign the ticket. Auto-assignment happens when **ALL** of these conditions are true:

1. The ticket is currently **unassigned** (`assigned_to_user_id IS NULL`)
2. The replier is **not the ticket creator** (`user_id !== created_by_user_id`)
3. The replier has staff rank of **CrewMember or higher**
4. The reply is **not** an internal note (`kind !== InternalNote`)

When triggered:
- `assigned_to_user_id` is atomically set to the replier's user ID
- Activity logged: `assignment_changed` with note "Auto-assigned"

This ensures the first staff member who responds to an unassigned ticket takes ownership.

---

## Notification System

### Notification Classes

| Class | Trigger | Channels | Content |
|-------|---------|----------|---------|
| `NewTicketNotification` | Ticket created | Mail, Pushover, Discord | Department, creator name, subject, link |
| `NewTicketReplyNotification` | Reply sent (not internal notes) | Mail, Pushover, Discord | Thread subject, author, message preview (100-200 chars), link |
| `TicketAssignedNotification` | Ticket assigned | Mail, Pushover, Discord | Subject, assignee, department, link |
| `MessageFlaggedNotification` | Message flagged | Mail, Pushover, Discord | Original subject, flagger, reason, link to review ticket |
| `TicketDigestNotification` | Scheduled command | Mail only | Lists up to 10 tickets with update counts since last digest |

All notification classes are in `app/Notifications/`.

### Delivery via TicketNotificationService

**File:** `app/Services/TicketNotificationService.php`

All ticket notifications are sent through `TicketNotificationService::send($user, $notification)`, which determines active channels per user:

- **Email:** Respects `email_digest_frequency`. If set to Daily/Weekly and user hasn't visited recently, email is deferred to digest. If Immediate or user visited within the last hour, sends immediately.
- **Pushover:** Requires user to have Pushover enabled in preferences AND `canSendPushover()` returns true (has key + under 10,000 monthly limit).
- **Discord:** Requires user to have Discord enabled in preferences AND an active linked Discord account.

**Method: `sendToMany(iterable $users, Notification $notification)`** — Sends to multiple users, calling `send()` for each.

### Digest System

**Command:** `app/Console/Commands/SendTicketDigests.php`
**Signature:** `tickets:send-digests {frequency}` where frequency is `daily` or `weekly`

1. Finds all users with matching `email_digest_frequency` who have an email address
2. For each user, determines the "since" date: `last_ticket_digest_sent_at` → `last_notification_read_at` → `created_at` → 30 days ago
3. Queries threads visible to the user with `last_message_at` after the since date
4. Counts new messages per thread since the since date
5. Sends `TicketDigestNotification` with ticket summaries (up to 10 tickets shown, remainder counted)
6. Updates `user.last_ticket_digest_sent_at` to now

### Notification Recipients by Action

| Action | Who Gets Notified |
|--------|------------------|
| Support ticket created | All staff in the department (except creator) |
| Admin ticket created | Target user + all staff in department (except creator) |
| Reply sent | All non-viewer participants (except the replier) |
| Internal note sent | Nobody |
| Ticket assigned | New assignee (unless self-assigning) + ticket creator (if different from assignee) |
| Message flagged | Quartermaster staff |
| Ticket closed/resolved | Nobody (they see status change on next visit) |

---

## Caching Strategy

### Cache Keys

| Key Pattern | TTL | Purpose |
|-------------|-----|---------|
| `user.{id}.ticket_counts` | 5 minutes | Badge counts and filter counts for the ticket list |
| `user.{id}.actionable_tickets` | 60 minutes | Whether the user has actionable tickets (sidebar indicator) |
| `user.{id}.actionable_tickets.timestamp` | 60 minutes | Tracks when actionable tickets cache was last refreshed |

### Invalidation

`User::clearTicketCaches()` is called whenever ticket state changes:
- New reply sent
- Status changed
- Ticket assigned/unassigned
- Ticket closed
- Message flagged/acknowledged

The method is called for **all participants** of the affected thread, plus any newly added assignees.

### Background Refresh

`hasActionableTickets()` uses a 30-minute background refresh window within its 60-minute TTL. If the cache is older than 30 minutes, a background job recalculates the value after the response is sent, ensuring users see up-to-date data without blocking page loads.

---

## Routes & Middleware

### Routes

All routes are in `routes/web.php` under the `/tickets` prefix with `auth` middleware:

| Method | URI | Component | Route Name |
|--------|-----|-----------|------------|
| GET | `/tickets` | `ready-room.tickets.tickets-list` | `tickets.index` |
| GET | `/tickets/create` | `ready-room.tickets.create-ticket` | `tickets.create` |
| GET | `/tickets/create-admin` | `ready-room.tickets.create-admin-ticket` | `tickets.create-admin` |
| GET | `/tickets/{thread}` | `ready-room.tickets.view-ticket` | `tickets.show` |

The `/tickets/create-admin` route has additional middleware: `can:createAsStaff,App\Models\Thread`.

### Middleware

**`UpdateLastNotificationRead`** (`app/Http/Middleware/UpdateLastNotificationRead.php`)
- Registered as `track-notification-read`
- Applied to all ticket routes
- Updates `user.last_notification_read_at` to the current timestamp on every request
- Used by `TicketNotificationService` to determine if a user should receive immediate email notifications (visited within the last hour) even if their digest preference is Daily/Weekly

### Navigation Entry Points

- **Sidebar:** "Tickets" link with badge count (red if unread, zinc otherwise) — `resources/views/components/layouts/app/sidebar.blade.php`
- **Ready Room dashboard:** "View Tickets" button — `resources/views/dashboard/ready-room.blade.php`
- **Stowaway widget:** "Create Admin Ticket" link per user — `resources/views/livewire/dashboard/stowaway-users-widget.blade.php`

---

## File Map

### Models
| File | Purpose |
|------|---------|
| `app/Models/Thread.php` | Core ticket entity with relationships, visibility, unread tracking |
| `app/Models/Message.php` | Individual messages within threads |
| `app/Models/ThreadParticipant.php` | Tracks user participation and read status |
| `app/Models/MessageFlag.php` | Moderation flags on messages |

### Enums
| File | Purpose |
|------|---------|
| `app/Enums/ThreadType.php` | Thread type (ticket/dm/forum) |
| `app/Enums/ThreadStatus.php` | Ticket status (open/pending/resolved/closed) |
| `app/Enums/ThreadSubtype.php` | Ticket subtype (support/admin_action/moderation_flag) |
| `app/Enums/MessageKind.php` | Message kind (message/system/internal_note) |
| `app/Enums/MessageFlagStatus.php` | Flag review status (new/acknowledged) |
| `app/Enums/StaffDepartment.php` | Staff departments |
| `app/Enums/StaffRank.php` | Staff ranks with integer ordering |
| `app/Enums/EmailDigestFrequency.php` | Email notification frequency preference |

### Actions
| File | Purpose |
|------|---------|
| `app/Actions/FlagMessage.php` | Creates a flag + moderation review ticket (transactional) |
| `app/Actions/AcknowledgeFlag.php` | Acknowledges a flag, recalculates thread flag status |
| `app/Actions/RecordActivity.php` | Logs activity entries (used throughout ticket system) |

### Policies
| File | Purpose |
|------|---------|
| `app/Policies/ThreadPolicy.php` | All thread-level authorization |
| `app/Policies/MessagePolicy.php` | Message viewing and flagging authorization |

### Services
| File | Purpose |
|------|---------|
| `app/Services/TicketNotificationService.php` | Smart notification delivery with channel/digest logic |

### Notifications
| File | Purpose |
|------|---------|
| `app/Notifications/NewTicketNotification.php` | Sent when a ticket is created |
| `app/Notifications/NewTicketReplyNotification.php` | Sent on new replies |
| `app/Notifications/TicketAssignedNotification.php` | Sent when a ticket is assigned |
| `app/Notifications/MessageFlaggedNotification.php` | Sent to Quartermaster when a message is flagged |
| `app/Notifications/TicketDigestNotification.php` | Periodic digest email |

### Commands
| File | Purpose |
|------|---------|
| `app/Console/Commands/SendTicketDigests.php` | Artisan command for daily/weekly digest emails |

### Middleware
| File | Purpose |
|------|---------|
| `app/Http/Middleware/UpdateLastNotificationRead.php` | Tracks user's last visit to ticket pages |

### Livewire Volt Components
| File | Purpose |
|------|---------|
| `resources/views/livewire/ready-room/tickets/tickets-list.blade.php` | Ticket inbox with filters and badge counts |
| `resources/views/livewire/ready-room/tickets/view-ticket.blade.php` | Ticket detail view with conversation, reply, status, assignment, flagging |
| `resources/views/livewire/ready-room/tickets/create-ticket.blade.php` | Support ticket creation form |
| `resources/views/livewire/ready-room/tickets/create-admin-ticket.blade.php` | Admin action ticket creation form |

### Migrations
| File | Purpose |
|------|---------|
| `database/migrations/2026_02_12_214853_create_threads_table.php` | Creates `threads` table |
| `database/migrations/2026_02_12_214857_create_messages_table.php` | Creates `messages` table |
| `database/migrations/2026_02_12_214859_create_thread_participants_table.php` | Creates `thread_participants` table |
| `database/migrations/2026_02_12_214903_create_message_flags_table.php` | Creates `message_flags` table |
| `database/migrations/2026_02_14_070835_add_is_viewer_to_thread_participants_table.php` | Adds `is_viewer` column |

### Factories
| File | Purpose |
|------|---------|
| `database/factories/ThreadFactory.php` | Thread factory with states for all subtypes/statuses |
| `database/factories/MessageFactory.php` | Message factory with kind states |
| `database/factories/ThreadParticipantFactory.php` | Participant factory with viewer state |
| `database/factories/MessageFlagFactory.php` | Flag factory with acknowledged state |

---

## Business Rules

These are the invariants that must be preserved when modifying the ticket system. Each rule references the authoritative source file.

### Ticket Creation
1. Users in the brig **cannot** create new tickets. (`ThreadPolicy::create`)
2. Only staff (CrewMember+) can create Admin Action tickets. (`ThreadPolicy::createAsStaff`)
3. Every new ticket must have a first message with `kind=Message`. (create-ticket component)
4. The creator is always added as a full participant (not viewer). (create-ticket component)
5. Admin Action tickets add both the target user and creator as participants. (create-admin-ticket component)

### Visibility & Access
6. Non-staff users can only see tickets they are participants in. (`Thread::isVisibleTo`)
7. Department staff see all tickets in their department. (`ThreadPolicy::viewDepartment`)
8. Quartermaster staff see flagged tickets from any department. (`ThreadPolicy::viewFlagged`)
9. Command Officers and Admins see all tickets. (`ThreadPolicy::before`)
10. Internal notes are invisible to non-staff even if they are participants. (`MessagePolicy::view`)

### Replying
11. Nobody can reply to a `Closed` ticket. (view-ticket `canReply` computed)
12. Non-staff cannot reply to `Resolved` tickets. (view-ticket `canReply` computed)
13. Viewers are promoted to full participants when they reply. (view-ticket `processReply`)
14. Replying updates the sender's `last_read_at` and the thread's `last_message_at`. (view-ticket `processReply`)
15. Internal notes do **not** trigger notifications to participants. (view-ticket `processReply`)

### Auto-Assignment
16. Auto-assignment only triggers for unassigned tickets. (view-ticket `processReply`)
17. Auto-assignment does not trigger if the replier is the ticket creator. (view-ticket `processReply`)
18. Auto-assignment does not trigger on internal notes. (view-ticket `processReply`)
19. Auto-assignment requires the replier to be CrewMember+ rank. (view-ticket `processReply`)

### Status & Closing
20. Only staff (CrewMember+) can change ticket status via the dropdown. (`ThreadPolicy::changeStatus`)
21. When non-staff close a ticket, the status is set to `Resolved`, not `Closed`. (view-ticket `closeTicket`)
22. Closing always creates a system message documenting who closed/resolved it. (view-ticket `closeTicket`)

### Assignment
23. Only Officers+ can manually assign tickets. (`ThreadPolicy::assign`)
24. Assignment target must be a staff member (has `staff_rank`). (view-ticket `assignTo` validation)
25. Self-assignment does not send a notification. (view-ticket `assignTo`)

### Message Flagging
26. Users cannot flag their own messages. (`MessagePolicy::flag`)
27. System messages cannot be flagged. (`MessagePolicy::flag`)
28. Only thread participants can flag messages. (`MessagePolicy::flag`)
29. Flagging a message always creates a Moderation Flag ticket in the Quartermaster department. (`FlagMessage::run`)
30. Flagging sets both `is_flagged=true` and `has_open_flags=true` on the original thread. (`FlagMessage::run`)
31. Acknowledging a flag recalculates `has_open_flags` based on remaining unacknowledged flags. (`AcknowledgeFlag::run`)

### Notifications
32. Viewers do not receive reply notifications — only full participants do. (view-ticket `processReply`)
33. The replier never receives a notification for their own reply. (view-ticket `processReply`)
34. Notification channels respect user preferences and digest frequency settings. (`TicketNotificationService`)
35. Pushover notifications are capped at 10,000 per user per month. (`User::canSendPushover`)
36. Users who visited the site within the last hour get immediate email even if on Daily/Weekly digest. (`TicketNotificationService::shouldSendImmediate`)

### Caching
37. `ticket_counts` cache must be cleared for all participants whenever ticket state changes. (view-ticket, User model)
38. Badge count includes: non-closed participant tickets + unread closed participant tickets + unassigned visible tickets. (`User::ticketCounts`)

---

## Test Coverage Map

| Test File | What It Covers |
|-----------|----------------|
| `tests/Feature/Tickets/CreateTicketTest.php` | Support & admin ticket creation, participant setup, badge counting |
| `tests/Feature/Tickets/TicketsListTest.php` | Filter logic, visibility per role, unread tracking, badge counts, department badges, filter persistence |
| `tests/Feature/Tickets/ViewTicketTest.php` | Access control, replies, internal notes, auto-assignment, status changes, assignment, closing, flagging, acknowledging |
| `tests/Feature/Tickets/ThreadAuthorizationTest.php` | Policy abilities per role: viewAll, viewDepartment, viewFlagged, changeStatus, assign, reroute |
| `tests/Feature/Tickets/TicketActivityLoggingTest.php` | Activity log entries for ticket_opened, ticket_joined (reply and viewer promotion) |
| `tests/Feature/Tickets/MessageFlaggingTest.php` | Flag creation, thread status updates, moderation ticket creation, Quartermaster notifications, acknowledgment |
| `tests/Feature/Tickets/NotificationTest.php` | Immediate vs digest email, Pushover limits, assignment notifications, reply notifications |
| `tests/Feature/Tickets/SendTicketDigestsTest.php` | Daily/weekly digest command execution, invalid frequency rejection |
