# Support Tickets -- Technical Documentation

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

The Support Tickets feature provides a messaging and issue-tracking system for the Lighthouse community. Users can create support tickets directed to specific staff departments, and staff members can manage, assign, and resolve those tickets. The system is built on a Thread/Message architecture that also supports admin-action tickets (staff-initiated) and moderation flag review tickets (system-generated).

All authenticated users who are not in the Brig can create support tickets. Staff members (CrewMember rank and above) can view tickets in their department, change statuses, and add internal notes. Officers can assign tickets and reroute them between departments. Command Officers and Admins can view all tickets across departments. Quartermaster staff have special permissions to view flagged tickets from any department.

The feature includes a message flagging system where participants can flag concerning messages. Flags create a separate moderation review ticket in the Quartermaster department and notify QM staff. The system also supports email digest notifications -- users can choose to receive ticket notifications immediately or as daily/weekly digest summaries.

Key terminology: "Thread" is the underlying model for a ticket. "Participant" is a user who is part of a ticket (can see and reply). "Viewer" is a user who can see a ticket but doesn't receive reply notifications. "Internal Note" is a staff-only message visible only to CrewMember+ rank. "Flag" is a user-submitted concern about a specific message.

---

## 2. Database Schema

### `threads` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | No | auto | |
| type | string | No | | `ticket`, `dm`, `forum` (indexed) |
| subtype | string | No | | `support`, `admin_action`, `moderation_flag` (indexed) |
| department | string | Yes | null | StaffDepartment enum value (indexed) |
| subject | string | No | | Ticket subject line |
| status | string | No | | `open`, `pending`, `resolved`, `closed` (indexed) |
| created_by_user_id | foreignId | No | | FK to `users.id` |
| assigned_to_user_id | foreignId | Yes | null | FK to `users.id` |
| is_flagged | boolean | No | false | Whether thread has any flags (indexed) |
| has_open_flags | boolean | No | false | Whether thread has unacknowledged flags (indexed) |
| last_message_at | timestamp | Yes | null | Time of most recent message (indexed) |
| created_at | timestamp | No | | |
| updated_at | timestamp | No | | |

**Indexes:** `type`, `subtype`, `department`, `status`, `is_flagged`, `has_open_flags`, `last_message_at`
**Foreign Keys:** `created_by_user_id` -> `users.id`, `assigned_to_user_id` -> `users.id`
**Migration(s):** `database/migrations/2026_02_12_214853_create_threads_table.php`

### `messages` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | No | auto | |
| thread_id | foreignId | No | | FK to `threads.id` (cascade) |
| user_id | foreignId | No | | FK to `users.id` |
| body | text | No | | Message content (rendered as Markdown) |
| kind | string | No | `message` | `message`, `system`, `internal_note` |
| created_at | timestamp | No | | |
| updated_at | timestamp | No | | |

**Indexes:** composite `(thread_id, created_at)`
**Foreign Keys:** `thread_id` -> `threads.id` (cascade), `user_id` -> `users.id`
**Migration(s):** `database/migrations/2026_02_12_214857_create_messages_table.php`

### `thread_participants` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | No | auto | |
| thread_id | foreignId | No | | FK to `threads.id` (cascade) |
| user_id | foreignId | No | | FK to `users.id` |
| is_viewer | boolean | No | false | Viewers don't get reply notifications |
| last_read_at | timestamp | Yes | null | When user last read this thread |
| created_at | timestamp | No | | |
| updated_at | timestamp | No | | |

**Indexes:** unique `(thread_id, user_id)`, `user_id`
**Foreign Keys:** `thread_id` -> `threads.id` (cascade), `user_id` -> `users.id`
**Migration(s):**
- `database/migrations/2026_02_12_214859_create_thread_participants_table.php`
- `database/migrations/2026_02_14_070835_add_is_viewer_to_thread_participants_table.php`

### `message_flags` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | No | auto | |
| message_id | foreignId | No | | FK to `messages.id` (cascade) |
| thread_id | foreignId | No | | FK to `threads.id` (cascade) |
| flagged_by_user_id | foreignId | No | | FK to `users.id` |
| note | text | No | | Reason for flagging |
| status | string | No | `new` | `new`, `acknowledged` (indexed) |
| reviewed_by_user_id | foreignId | Yes | null | FK to `users.id` |
| reviewed_at | timestamp | Yes | null | When flag was reviewed |
| staff_notes | text | Yes | null | Staff notes on review |
| flag_review_ticket_id | foreignId | Yes | null | FK to `threads.id` (the review ticket) |
| created_at | timestamp | No | | |
| updated_at | timestamp | No | | |

**Indexes:** composite `(message_id, status)`, composite `(thread_id, status)`
**Foreign Keys:** `message_id` -> `messages.id` (cascade), `thread_id` -> `threads.id` (cascade), `flagged_by_user_id` -> `users.id`, `reviewed_by_user_id` -> `users.id`, `flag_review_ticket_id` -> `threads.id`
**Migration(s):** `database/migrations/2026_02_12_214903_create_message_flags_table.php`

---

## 3. Models & Relationships

### Thread (`app/Models/Thread.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `createdBy()` | belongsTo | User | via `created_by_user_id` |
| `assignedTo()` | belongsTo | User | via `assigned_to_user_id`, nullable |
| `messages()` | hasMany | Message | |
| `participants()` | hasMany | ThreadParticipant | |
| `flags()` | hasMany | MessageFlag | |

**Scopes:** None

**Key Methods:**
- `addParticipant(User $user, bool $isViewer = false): void` -- Ensures user is a participant. If creating, sets `is_viewer`. If existing viewer and `$isViewer` is false, promotes to full participant.
- `addViewer(User $user): void` -- Convenience method, calls `addParticipant($user, isViewer: true)`.
- `isVisibleTo(User $user): bool` -- Checks if user can view: `viewAll` policy, `viewFlagged` + `is_flagged`, `viewDepartment` + matching dept, participant, or parent of participant/creator (for Ticket type only).
- `isUnreadFor(User $user): bool` -- Checks if `last_message_at` > participant's `last_read_at`.

**Casts:**
- `type` => `ThreadType::class`
- `subtype` => `ThreadSubtype::class`
- `department` => `StaffDepartment::class`
- `status` => `ThreadStatus::class`
- `is_flagged` => `boolean`
- `has_open_flags` => `boolean`
- `last_message_at` => `datetime`

### Message (`app/Models/Message.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `thread()` | belongsTo | Thread | |
| `user()` | belongsTo | User | |
| `flags()` | hasMany | MessageFlag | |

**Casts:**
- `kind` => `MessageKind::class`

### ThreadParticipant (`app/Models/ThreadParticipant.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `thread()` | belongsTo | Thread | |
| `user()` | belongsTo | User | |

**Casts:**
- `last_read_at` => `datetime`
- `is_viewer` => `boolean`

### MessageFlag (`app/Models/MessageFlag.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `message()` | belongsTo | Message | |
| `thread()` | belongsTo | Thread | |
| `flaggedBy()` | belongsTo | User | via `flagged_by_user_id` |
| `reviewedBy()` | belongsTo | User | via `reviewed_by_user_id` |
| `flagReviewTicket()` | belongsTo | Thread | via `flag_review_ticket_id` |

**Casts:**
- `status` => `MessageFlagStatus::class`
- `reviewed_at` => `datetime`

### User (`app/Models/User.php`) -- ticket-related methods

**Key Methods:**
- `ticketCounts(): array` -- Cached (5 min) aggregate counts for filter badges: `my-open`, `my-open-unread`, `my-closed-unread`, `open`, `closed`, `assigned-to-me`, `unassigned`, `flagged`. Uses a single query with visibility filtering.
- `clearTicketCaches(): void` -- Clears the `user.{id}.ticket_counts` cache key.

---

## 4. Enums Reference

### ThreadType (`app/Enums/ThreadType.php`)

| Case | Value | Label | Notes |
|------|-------|-------|-------|
| Ticket | `ticket` | Ticket | Primary type for support tickets |
| DirectMessage | `dm` | Direct Message | Future use |
| Forum | `forum` | Forum | Future use |

### ThreadSubtype (`app/Enums/ThreadSubtype.php`)

| Case | Value | Label | Notes |
|------|-------|-------|-------|
| Support | `support` | Support | User-created support tickets |
| AdminAction | `admin_action` | Admin Action | Staff-created tickets about a user |
| ModerationFlag | `moderation_flag` | Moderation Flag | System-created flag review tickets |

### ThreadStatus (`app/Enums/ThreadStatus.php`)

| Case | Value | Label | Notes |
|------|-------|-------|-------|
| Open | `open` | Open | Default status for new tickets |
| Pending | `pending` | Pending | Awaiting action |
| Resolved | `resolved` | Resolved | When non-staff closes (marks resolved) |
| Closed | `closed` | Closed | When staff closes |

### MessageKind (`app/Enums/MessageKind.php`)

| Case | Value | Label | Notes |
|------|-------|-------|-------|
| Message | `message` | Message | Regular user messages |
| System | `system` | System | System-generated messages (close, etc.) |
| InternalNote | `internal_note` | Internal Note | Staff-only messages, hidden from non-staff |

### MessageFlagStatus (`app/Enums/MessageFlagStatus.php`)

| Case | Value | Label | Notes |
|------|-------|-------|-------|
| New | `new` | New | Unreviewed flag |
| Acknowledged | `acknowledged` | Acknowledged | Reviewed by staff |

### EmailDigestFrequency (`app/Enums/EmailDigestFrequency.php`)

| Case | Value | Label | Notes |
|------|-------|-------|-------|
| Immediate | `immediate` | Immediate | Send notifications immediately |
| Daily | `daily` | Daily Digest | Batch into daily digest |
| Weekly | `weekly` | Weekly Digest | Batch into weekly digest |

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

No ticket-specific gates are defined in `AuthServiceProvider`. All ticket authorization is handled through the `ThreadPolicy` and `MessagePolicy`.

### Policies

#### ThreadPolicy (`app/Policies/ThreadPolicy.php`)

**`before()` hook:** Admins and Command Officers (Command dept + Officer rank) return `true` for all abilities.

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `viewAll` | Command Officers, Admins (via before) | Always `false` for non-admins; `before()` grants access |
| `viewDepartment` | Staff with department + CrewMember+ rank | `staff_department !== null && isAtLeastRank(CrewMember)` |
| `viewFlagged` | Quartermaster CrewMember+ | `isInDepartment(Quartermaster) && isAtLeastRank(CrewMember)` |
| `view` | Users with visibility | Delegates to `$thread->isVisibleTo($user)` |
| `create` | Non-brigged users | `!$user->in_brig` |
| `createAsStaff` | CrewMember+ | `isAtLeastRank(CrewMember)` |
| `reply` | Users who can view | Delegates to `$thread->isVisibleTo($user)` |
| `internalNotes` | Staff (CrewMember+) who can view | `isAtLeastRank(CrewMember) && thread->isVisibleTo($user)` |
| `changeStatus` | Staff (CrewMember+) who can view | Same as `internalNotes` |
| `assign` | Officers+ who can view | `isAtLeastRank(Officer) && thread->isVisibleTo($user)` |
| `reroute` | Officers+ who can view | Same as `assign` |
| `close` | Staff who can view OR ticket creator | Staff: `isAtLeastRank(CrewMember) && isVisibleTo`. Non-staff: `created_by_user_id === $user->id` |

#### MessagePolicy (`app/Policies/MessagePolicy.php`)

**`before()` hook:** Admins and Command Officers return `true` for all abilities.

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `view` | Users who can view thread, with kind filter | Internal notes hidden from non-staff; otherwise delegates to thread visibility |
| `flag` | Thread participants (not own messages, not system) | Cannot flag system messages or own messages; must be thread participant |

### Permissions Matrix

| User Type | Create Ticket | Create Admin Ticket | View Own Tickets | View Dept Tickets | View All Tickets | View Flagged | Reply | Internal Notes | Change Status | Assign | Close Own | Close Any | Flag Messages |
|-----------|--------------|-------------------|-----------------|------------------|-----------------|-------------|-------|---------------|--------------|--------|-----------|-----------|--------------|
| Brigged User | No | No | Yes | No | No | No | Yes (if participant) | No | No | No | Yes | No | Yes |
| Regular User | Yes | No | Yes | No | No | No | Yes | No | No | No | Yes (resolved) | No | Yes |
| Parent | Yes | No | Yes + child's | No | No | No | Yes | No | No | No | Yes | No | Yes |
| JrCrew | Yes | No | Yes | No | No | No | Yes | No | No | No | Yes | No | Yes |
| CrewMember | Yes | Yes | Yes | Yes (own dept) | No | If QM | Yes | Yes | Yes | No | Yes | Yes | Yes |
| Officer | Yes | Yes | Yes | Yes (own dept) | No | If QM | Yes | Yes | Yes | Yes | Yes | Yes | Yes |
| Command Officer | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes |
| Admin | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes |

---

## 6. Routes

| Method | URL | Middleware | Handler | Route Name |
|--------|-----|-----------|---------|------------|
| GET | `/tickets` | `auth` | Volt: `ready-room.tickets.tickets-list` | `tickets.index` |
| GET | `/tickets/create` | `auth` | Volt: `ready-room.tickets.create-ticket` | `tickets.create` |
| GET | `/tickets/create-admin` | `auth`, `can:createAsStaff,App\Models\Thread` | Volt: `ready-room.tickets.create-admin-ticket` | `tickets.create-admin` |
| GET | `/tickets/{thread}` | `auth` | Volt: `ready-room.tickets.view-ticket` | `tickets.show` |

---

## 7. User Interface Components

### Tickets List
**File:** `resources/views/livewire/ready-room/tickets/tickets-list.blade.php`
**Route:** `/tickets` (route name: `tickets.index`)

**Purpose:** Displays a filterable list of tickets with unread indicators and badge counts.

**Authorization:** All authenticated users can access. Staff filters (All Open, Closed, Assigned to Me, Unassigned, Flagged) are shown only to staff with `viewAll`, `viewDepartment`, or `viewFlagged` permissions.

**User Actions Available:**
- Click filter buttons to switch views (My Open, My Closed, All Open, All Closed, Assigned to Me, Unassigned, Flagged)
- Click a ticket row to navigate to the ticket detail page
- Click "Create Ticket" to create a support ticket
- Click "Create Admin Ticket" (staff only) to create an admin-action ticket

**UI Elements:**
- Filter buttons with badge counts (cached via `User::ticketCounts()`)
- Unread badge (red "New") based on `last_read_at` vs `last_message_at`
- Red flag badge for tickets with open flags
- Department badge, subtype label, creator name, time ago
- Status badge (green=Open, amber=Pending, zinc=other)
- Assigned user avatar
- Empty state message when no tickets match filter

### Create Ticket
**File:** `resources/views/livewire/ready-room/tickets/create-ticket.blade.php`
**Route:** `/tickets/create` (route name: `tickets.create`)

**Purpose:** Form to create a new support ticket.

**Authorization:** `ThreadPolicy::create` -- user must not be in brig.

**User Actions Available:**
- Fill in Department (select, defaults to Command), Subject (max 255), Message (min 10)
- Submit -> creates Thread + Message, records activity, notifies department staff, redirects to ticket

**UI Elements:**
- Department select (all 5 departments), Subject input, Message textarea
- Create Ticket / Cancel buttons

### Create Admin Ticket
**File:** `resources/views/livewire/ready-room/tickets/create-admin-ticket.blade.php`
**Route:** `/tickets/create-admin` (route name: `tickets.create-admin`)

**Purpose:** Staff-only form to create an admin-action ticket about a specific user.

**Authorization:** `ThreadPolicy::createAsStaff` -- must be CrewMember+ rank. Also enforced via route middleware `can:createAsStaff,App\Models\Thread`.

**User Actions Available:**
- Select target user (searchable select of all users), Department, Subject, Message
- Submit -> creates Thread (AdminAction subtype) with target user as participant, notifies target + department staff

**UI Elements:**
- Target User searchable select, Department select, Subject input, Message textarea
- Pre-fills target_user_id from query string if `?user_id=` is provided

### View Ticket
**File:** `resources/views/livewire/ready-room/tickets/view-ticket.blade.php`
**Route:** `/tickets/{thread}` (route name: `tickets.show`)

**Purpose:** Displays a ticket's full message history with reply, status management, assignment, and flagging capabilities.

**Authorization:** `ThreadPolicy::view` on mount. Various per-action policies checked inline.

**User Actions Available:**
- **Reply** -> `sendReply()` -> creates Message, updates `last_message_at`, auto-assigns if first staff reply, notifies participants
- **Internal Note** -> same as reply but with `kind = InternalNote`, no notifications
- **Change Status** -> `changeStatus()` -> updates thread status, records activity
- **Assign** -> `assignTo($userId)` -> updates assignee, records activity, notifies new assignee + creator
- **Close Ticket** -> `closeTicket()` -> sends pending reply (if any), sets status to Closed (staff) or Resolved (non-staff), creates system message
- **Flag Message** -> `submitFlag()` -> calls `FlagMessage::run()` -> creates flag, creates QM review ticket, notifies QM staff
- **Acknowledge Flag** -> `acknowledgeFlag()` -> calls `AcknowledgeFlag::run()` -> updates flag status, recalculates `has_open_flags`

**Special Behaviors:**
- On mount, adds current user as viewer (for read tracking) and marks thread as read
- Auto-assigns unassigned tickets when staff replies (not internal notes, not own ticket)
- Non-staff cannot reply to Resolved tickets
- No one can reply to Closed tickets
- Clears `ticketCounts` cache for all participants after replies, status changes, assignments

**UI Elements:**
- Thread header: subject, target user link, department, subtype, status, created time
- Status dropdown (staff only), Assignment dropdown (officers only)
- Message list with avatars, timestamps (user timezone), markdown rendering
- Internal Note badge (amber), System badge (zinc)
- Flag button on other users' messages
- Flag display for QM staff (flagged by, reason, acknowledged status, staff notes)
- Reply textarea with Internal Note checkbox (staff only)
- Close Ticket button
- Flag Message modal, Acknowledge Flag modal
- Back to Tickets button (preserves filter parameter)

---

## 8. Actions (Business Logic)

### FlagMessage (`app/Actions/FlagMessage.php`)

**Signature:** `handle(Message $message, User $flaggingUser, string $note): MessageFlag`

**Step-by-step logic (in DB transaction):**
1. Creates `MessageFlag` record with status `New`
2. Updates original thread: `is_flagged = true`, `has_open_flags = true`
3. Creates a new Quartermaster moderation review ticket (Thread with subtype `ModerationFlag`)
4. Creates system message in review ticket with flag details (original ticket link, flagged message ID, flagger name, reason, original message quoted)
5. Links flag to review ticket via `flag_review_ticket_id`
6. Logs activity: `RecordActivity::run($thread, 'message_flagged', "Message flagged by {name}")`
7. Notifies all Quartermaster staff via `TicketNotificationService::send()` with `MessageFlaggedNotification`

**Called by:** `view-ticket` component's `submitFlag()` method

### AcknowledgeFlag (`app/Actions/AcknowledgeFlag.php`)

**Signature:** `handle(MessageFlag $flag, User $reviewer, ?string $staffNotes = null): void`

**Step-by-step logic:**
1. Updates flag: status to `Acknowledged`, sets `reviewed_by_user_id`, `reviewed_at`, `staff_notes`
2. Recalculates `has_open_flags` on the original thread (checks if any flags still have status `New`)
3. Logs activity: `RecordActivity::run($thread, 'flag_acknowledged', "Flag acknowledged by {name}{notes preview}")`

**Called by:** `view-ticket` component's `acknowledgeFlag()` method

### RecordActivity (`app/Actions/RecordActivity.php`)

Called throughout the ticket system for logging (see Activity Log Entries section).

---

## 9. Notifications

### NewTicketNotification (`app/Notifications/NewTicketNotification.php`)

**Triggered by:** `create-ticket` and `create-admin-ticket` components
**Recipient:** Department staff (for support tickets), target user + department staff (for admin tickets)
**Channels:** mail, Pushover, Discord (via `TicketNotificationService`)
**Mail subject:** `"New Ticket: {subject}"`
**Content summary:** Thread subject, department, creator name, link to ticket
**Queued:** Yes

### NewTicketReplyNotification (`app/Notifications/NewTicketReplyNotification.php`)

**Triggered by:** `view-ticket` component's `processReply()` method
**Recipient:** All non-viewer participants except the sender
**Channels:** mail, Pushover, Discord (via `TicketNotificationService`)
**Mail subject:** `"New Reply: {subject}"`
**Content summary:** Thread subject, replier name, message preview (100 chars), link to ticket
**Queued:** Yes

### TicketAssignedNotification (`app/Notifications/TicketAssignedNotification.php`)

**Triggered by:** `view-ticket` component's `assignTo()` method
**Recipient:** New assignee (if not self-assigning) + ticket creator (if different from assignee)
**Channels:** mail, Pushover, Discord (via `TicketNotificationService`)
**Mail subject:** `"Ticket Assigned: {subject}"`
**Content summary:** Thread subject, assigned-to name, department, link to ticket
**Queued:** Yes

### MessageFlaggedNotification (`app/Notifications/MessageFlaggedNotification.php`)

**Triggered by:** `FlagMessage` action
**Recipient:** All Quartermaster staff
**Channels:** mail, Pushover, Discord (via `TicketNotificationService`)
**Mail subject:** `"Message Flagged for Review"`
**Content summary:** Flagger name, reason, link to review ticket
**Queued:** Yes

### TicketDigestNotification (`app/Notifications/TicketDigestNotification.php`)

**Triggered by:** `SendTicketDigests` console command
**Recipient:** Users with daily/weekly digest preference
**Channels:** mail only (no Pushover for digests)
**Mail subject:** `"Ticket Digest - {date}"`
**Content summary:** List of up to 10 tickets with new message counts, link to tickets list
**Queued:** Yes

---

## 10. Background Jobs

Not applicable for this feature. Notifications are queued individually via `ShouldQueue` but no dedicated Job classes exist for tickets.

---

## 11. Console Commands & Scheduled Tasks

### `tickets:send-digests {frequency}`
**File:** `app/Console/Commands/SendTicketDigests.php`
**Scheduled:** Yes
- `tickets:send-digests daily` -- daily at 8am
- `tickets:send-digests weekly` -- weekly on Mondays at 8am

**What it does:**
1. Queries users with matching `email_digest_frequency` preference
2. For each user, finds threads visible to them with activity since `last_ticket_digest_sent_at` (falls back to `last_notification_read_at`, `created_at`, or 30 days ago)
3. Counts new messages per ticket since last digest
4. Sends `TicketDigestNotification` with ticket summaries (up to 10 tickets displayed)
5. Updates `last_ticket_digest_sent_at` on user

---

## 12. Services

### TicketNotificationService (`app/Services/TicketNotificationService.php`)

**Purpose:** Smart notification delivery that respects user preferences for channel selection and digest frequency.

**Key methods:**
- `send(User $user, Notification $notification, string $category = 'tickets'): void` -- Determines channels based on user preferences for the category, sets channels on the notification, sends
- `sendToMany(iterable $users, Notification $notification, string $category = 'tickets'): void` -- Sends to multiple users
- `determineChannels(User $user, string $category): array` -- Returns array of channel names based on user's `notification_preferences` JSON and category. For tickets, defers email to digest if user prefers non-immediate and hasn't visited recently.
- `shouldSendImmediate(User $user): bool` -- Returns false if user prefers digest AND hasn't visited in the last hour
- `canSendPushover(User $user): bool` -- Delegates to `$user->canSendPushover()`

**Categories:** `tickets`, `account`, `staff_alerts`, `announcements`

---

## 13. Activity Log Entries

| Action String | Logged By | Subject Model | Description |
|---------------|-----------|---------------|-------------|
| `ticket_opened` | `create-ticket`, `create-admin-ticket` components | Thread | `"Opened ticket: {subject}"` |
| `ticket_joined` | `view-ticket` component (`processReply`) | Thread | `"Joined ticket: {subject}"` (when staff first replies or viewer becomes participant) |
| `status_changed` | `view-ticket` component (`changeStatus`, `closeTicket`) | Thread | `"Status changed: {old} -> {new}"` |
| `assignment_changed` | `view-ticket` component (`assignTo`, auto-assign) | Thread | `"Assigned to: {name}"` or `"Assignment changed: {old} -> {new}"` or `"Assignment removed..."` or `"Auto-assigned to {name} on first reply"` |
| `message_flagged` | `FlagMessage` action | Thread | `"Message flagged by {name}"` |
| `flag_acknowledged` | `AcknowledgeFlag` action | Thread | `"Flag acknowledged by {name}{notes preview}"` |

---

## 14. Data Flow Diagrams

### Creating a Support Ticket

```
User clicks "Create Ticket" on tickets list
  -> GET /tickets/create (middleware: auth)
    -> create-ticket component renders form
User fills in Department, Subject, Message and submits
  -> create-ticket::createTicket()
    -> $this->validate() (subject: required|max:255, department: required, message: required|min:10)
    -> Thread::create(type: Ticket, subtype: Support, status: Open, ...)
    -> $thread->addParticipant(auth()->user())
    -> Message::create(kind: Message, body: $message)
    -> RecordActivity::run($thread, 'ticket_opened', '...')
    -> Query department staff (staff_department match, has staff_rank, not creator)
    -> For each staff: TicketNotificationService::send($staff, NewTicketNotification)
    -> Flux::toast('Your ticket has been created successfully!', variant: 'success')
    -> Redirect to /tickets/{thread_id}
```

### Replying to a Ticket

```
User types reply on view-ticket page
  -> view-ticket::sendReply()
    -> Validates replyMessage (required|min:1)
    -> processReply($body, $isInternalNote):
      -> $this->authorize('reply', $thread)
      -> If internal note: $this->authorize('internalNotes', $thread)
      -> Message::create(kind: Message or InternalNote)
      -> Ensure sender is non-viewer participant (promote or create)
        -> If new participant/promoted: RecordActivity::run($thread, 'ticket_joined', '...')
      -> Update participant's last_read_at
      -> Update thread's last_message_at
      -> Auto-assign: if unassigned, not creator, staff, not internal note
        -> Atomic UPDATE threads SET assigned_to_user_id WHERE id AND assigned_to_user_id IS NULL
        -> RecordActivity::run($thread, 'assignment_changed', 'Auto-assigned...')
      -> If not internal note: notify non-viewer participants
        -> TicketNotificationService::send($participant->user, NewTicketReplyNotification)
      -> Clear ticket caches for all participants
    -> Reset replyMessage, isInternalNote
    -> Flux::toast('Reply sent successfully!', variant: 'success')
```

### Closing a Ticket

```
Staff clicks "Close Ticket" on view-ticket page
  -> view-ticket::closeTicket()
    -> $this->authorize('close', $thread)
    -> If replyMessage is non-empty: processReply() first
    -> Determine new status: Closed (staff) or Resolved (non-staff)
    -> $thread->update(['status' => $newStatus])
    -> Find system@lighthouse.local user
    -> Create system Message: "{name} closed/resolved this ticket"
    -> Update thread last_message_at
    -> RecordActivity::run($thread, 'status_changed', '...')
    -> Clear ticket caches for all participants
    -> Flux::toast('Ticket closed/resolved successfully!', variant: 'success')
```

### Flagging a Message

```
User clicks flag icon on a message
  -> view-ticket::openFlagModal($messageId)
    -> $this->authorize('flag', $message)
    -> Opens flag-message modal
User fills in reason (min 10 chars) and submits
  -> view-ticket::submitFlag()
    -> Validates flagReason (required|min:10)
    -> $this->authorize('flag', $message)
    -> FlagMessage::run($message, auth()->user(), $flagReason)
      -> DB::transaction:
        -> MessageFlag::create(status: New)
        -> Thread update: is_flagged=true, has_open_flags=true
        -> Create QM review Thread (subtype: ModerationFlag, dept: Quartermaster)
        -> Create system Message in review ticket with flag details
        -> Link flag -> review ticket
        -> RecordActivity::run($originalThread, 'message_flagged', '...')
        -> Notify all QM staff: MessageFlaggedNotification
    -> Close modal, show toast
```

### Acknowledging a Flag

```
QM staff clicks "Acknowledge Flag" on view-ticket page
  -> view-ticket::openAcknowledgeModal($flagId)
    -> Checks canViewFlagged permission
    -> Opens acknowledge-flag modal
Staff adds optional notes and submits
  -> view-ticket::acknowledgeFlag()
    -> Checks canViewFlagged permission
    -> AcknowledgeFlag::run($flag, auth()->user(), $staffNotes)
      -> Flag updated: status=Acknowledged, reviewed_by, reviewed_at, staff_notes
      -> Recalculate thread.has_open_flags (any remaining New flags?)
      -> RecordActivity::run($thread, 'flag_acknowledged', '...')
    -> Close modal, show toast
```

### Assigning a Ticket

```
Officer selects a staff member from the Assigned To dropdown
  -> view-ticket::assignTo($userId)
    -> $this->authorize('assign', $thread)
    -> If null: unassign, record activity, clear caches, toast
    -> Validate user exists and is staff
    -> $thread->update(['assigned_to_user_id' => $userId])
    -> RecordActivity::run($thread, 'assignment_changed', '...')
    -> Notify new assignee (if not self): TicketAssignedNotification
    -> Notify ticket creator (if different from assignee): TicketAssignedNotification
    -> Clear ticket caches for all participants + old/new assignees
    -> Flux::toast('Ticket assigned successfully!', variant: 'success')
```

---

## 15. Configuration

Not applicable for this feature. No feature-specific configuration values. Notification channel configuration is handled per-user via `notification_preferences` JSON column and `TicketNotificationService`.

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/Tickets/TicketsListTest.php` | 23 tests | List rendering, filters, badge counts, unread tracking, caching |
| `tests/Feature/Tickets/ViewTicketTest.php` | 28 tests | Viewing, replying, status changes, assignment, flagging, auto-assign, close, caching |
| `tests/Feature/Tickets/CreateTicketTest.php` | 8 tests | Support ticket creation, admin ticket creation, validation, participant setup |
| `tests/Feature/Tickets/ThreadAuthorizationTest.php` | 9 tests | Policy enforcement for various abilities |
| `tests/Feature/Tickets/NotificationTest.php` | 10 tests | Immediate vs digest, Pushover, assigned, reply notifications |
| `tests/Feature/Tickets/MessageFlaggingTest.php` | 11 tests | Flag creation, thread updates, review ticket, QM notification, acknowledgment |
| `tests/Feature/Tickets/TicketActivityLoggingTest.php` | 5 tests | Activity logging for open, join, viewer-to-participant, no log for regular replies/internal notes |
| `tests/Feature/Tickets/SendTicketDigestsTest.php` | 3 tests | Digest command with daily/weekly/invalid frequency |
| `tests/Feature/Services/TicketNotificationServiceCategoryTest.php` | 9 tests | Category-based channel determination, discord, pushover, defaults |

### Test Case Inventory

**TicketsListTest.php:**
- `it('can render for authenticated users')`
- `it('shows tickets for user as participant')`
- `it('shows all tickets across departments for any staff in open filter')`
- `it('shows all tickets for Command Officers')`
- `it('shows all tickets for Quartermaster in open filter')`
- `it('filters by open status')`
- `it('filters by assigned to me')`
- `it('filters by unassigned')`
- `it('shows flagged filter only for Quartermaster')`
- `it('filters by flagged tickets for Quartermaster')`
- `it('displays red flag badge for tickets with open flags')`
- `it('marks ticket as unread when user has never read it')`
- `it('marks ticket as read when user has read it after last message')`
- `it('marks ticket as unread when new message arrives after user read it')`
- `it('shows New badge only for current users participant record')`
- `it('shows department badge in ticket list')`
- `it('shows my tickets filter by default for regular users')`
- `it('allows regular users to switch between my open and my closed tickets')`
- `it('allows staff to switch between my tickets and staff filters')`
- `it('prevents duplicate tickets when staff member is participant in department ticket')`
- `it('preserves filter parameter when navigating to ticket')`
- `it('displays filter counts for all filter categories')`
- `it('caches filter counts for performance')`
- `it('clears filter count cache when ticket state changes')`

**ViewTicketTest.php:**
- `it('can render for participants')`
- `it('can render for staff in department')`
- `it('hides internal notes from non-staff users')`
- `it('shows internal notes to staff')`
- `it('allows staff to change ticket status')`
- `it('allows officers to assign tickets')`
- `it('allows assigning tickets to staff from different departments')`
- `it('allows participants to reply to tickets')`
- `it('allows staff to create internal notes')`
- `it('adds replying staff as participant if not already')`
- `it('allows users to flag messages')`
- `it('Quartermaster has viewFlagged permission')`
- `it('allows Quartermaster to acknowledge flags')`
- `it('adds staff as participant when they view department ticket as observer')`
- `it('marks ticket as read for observer viewing it')`
- `it('converts viewer to participant when they reply')`
- `it('does not notify viewers when new message is posted')`
- `it('posts message and closes ticket when close button clicked with text')`
- `it('closes ticket without posting when close button clicked with empty text')`
- `it('clears ticket caches when reply is sent')`
- `it('counts participant tickets correctly - non-closed or unread closed')`
- `it('preserves filter parameter in back button URL')`
- `it('displays back button with correct filter URL')`
- `it('auto-assigns unassigned ticket when staff replies')`
- `it('does not change assignment when ticket is already assigned')`
- `it('does not auto-assign when creator replies to own ticket')`
- `it('does not auto-assign on internal note')`

**CreateTicketTest.php:**
- `it('can render for authenticated users')`
- `it('validates required fields')` (support ticket)
- `it('creates support ticket with correct data')`
- `it('adds creator as participant')`
- `it('staff badge includes unassigned tickets in their visible scope')`
- `it('staff badge does not include assigned tickets they are not part of')`
- `it('can render for staff with createAsStaff permission')`
- `it('validates required fields')` (admin ticket)
- `it('creates admin action ticket with correct data')`
- `it('adds both creator and target user as participants')`

**ThreadAuthorizationTest.php:**
- `it('allows Command Officers to view all threads')`
- `it('allows staff to view threads in their department')`
- `it('allows Quartermaster to view flagged threads across departments')`
- `it('allows participants to view threads they are part of')`
- `it('allows staff to change thread status in their department')`
- `it('allows officers to assign threads in their department')`
- `it('prevents non-officers from assigning threads')`
- `it('allows officers to reroute threads in their department')`
- `it('allows Admin to do everything')`

**NotificationTest.php:**
- `it('sends immediate email notification when preference is immediate')`
- `it('queues digest instead of immediate when preference is daily and no recent visit')`
- `it('sends immediate email even with digest preference if user visited recently')`
- `it('sends Pushover notification when user has key and under limit')`
- `it('does not send Pushover when user is over monthly limit')`
- `it('resets Pushover count monthly')`
- `it('notifies assigned user when ticket is assigned')`
- `it('sends digest notifications to users with daily preference')`
- `it('sends reply notification when someone replies to ticket')`
- `it('sends reply notification via Pushover when configured')`

**MessageFlaggingTest.php:**
- `it('allows users to flag messages they did not create')`
- `it('prevents users from flagging their own messages')`
- `it('prevents users from flagging system messages')`
- `it('creates flag record when message is flagged')`
- `it('updates thread flagging status when message is flagged')`
- `it('creates moderation ticket for Quartermaster when message is flagged')`
- `it('sends notification to Quartermaster staff when message is flagged')`
- `it('allows Quartermaster to acknowledge flags with staff notes')`
- `it('clears has_open_flags when all flags are acknowledged')`
- `it('keeps has_open_flags true if some flags remain unacknowledged')`

**TicketActivityLoggingTest.php:**
- `it('logs activity when a ticket is opened')`
- `it('logs activity when staff joins a ticket by replying')`
- `it('logs activity when viewer becomes participant')`
- `it('does not log activity for regular replies')`
- `it('does not log activity for internal notes')`

**SendTicketDigestsTest.php:**
- `it('runs successfully with daily frequency')`
- `it('runs successfully with weekly frequency')`
- `it('fails with invalid frequency')`

**TicketNotificationServiceCategoryTest.php:**
- `it('always sends immediate email for account category regardless of digest preference')`
- `it('always sends immediate email for staff_alerts category regardless of digest preference')`
- `it('defers ticket emails to digest when user prefers daily and has not visited recently')`
- `it('includes discord channel when user has linked account and enabled preference')`
- `it('excludes discord channel when user has no linked account')`
- `it('returns empty channels when user disables all preferences for a category')`
- `it('uses default preferences when category has no saved preferences')`
- `it('includes pushover for account category when user has key and preference enabled')`
- `it('sends to multiple users with category parameter')`

### Coverage Gaps

- **Reroute (department change)**: The `reroute` policy ability is defined but no UI or test exercises it. No component method implements rerouting.
- **Admin ticket pre-fill from query string**: The `?user_id=` pre-fill in create-admin-ticket has no test coverage.
- **Ticket digest content**: Only basic command execution tests exist; no tests verify the actual digest email content or that correct tickets are included.
- **Parent viewing child's tickets**: The `isVisibleTo()` parent-child check is defined but has no dedicated test.
- **Forum and DirectMessage thread types**: Defined in `ThreadType` enum but unused; no UI or tests exist.

---

## 17. File Map

**Models:**
- `app/Models/Thread.php`
- `app/Models/Message.php`
- `app/Models/ThreadParticipant.php`
- `app/Models/MessageFlag.php`
- `app/Models/User.php` (ticket-related methods: `ticketCounts()`, `clearTicketCaches()`)

**Enums:**
- `app/Enums/ThreadType.php`
- `app/Enums/ThreadSubtype.php`
- `app/Enums/ThreadStatus.php`
- `app/Enums/MessageKind.php`
- `app/Enums/MessageFlagStatus.php`
- `app/Enums/EmailDigestFrequency.php`
- `app/Enums/StaffDepartment.php`
- `app/Enums/StaffRank.php`

**Actions:**
- `app/Actions/FlagMessage.php`
- `app/Actions/AcknowledgeFlag.php`
- `app/Actions/RecordActivity.php`

**Policies:**
- `app/Policies/ThreadPolicy.php`
- `app/Policies/MessagePolicy.php`

**Gates:** None specific to tickets (all via policies)

**Notifications:**
- `app/Notifications/NewTicketNotification.php`
- `app/Notifications/NewTicketReplyNotification.php`
- `app/Notifications/TicketAssignedNotification.php`
- `app/Notifications/MessageFlaggedNotification.php`
- `app/Notifications/TicketDigestNotification.php`

**Jobs:** None

**Services:**
- `app/Services/TicketNotificationService.php`

**Controllers:** None (all routes use Volt components)

**Volt Components:**
- `resources/views/livewire/ready-room/tickets/tickets-list.blade.php`
- `resources/views/livewire/ready-room/tickets/create-ticket.blade.php`
- `resources/views/livewire/ready-room/tickets/create-admin-ticket.blade.php`
- `resources/views/livewire/ready-room/tickets/view-ticket.blade.php`

**Routes:**
- `tickets.index` -- `GET /tickets`
- `tickets.create` -- `GET /tickets/create`
- `tickets.create-admin` -- `GET /tickets/create-admin`
- `tickets.show` -- `GET /tickets/{thread}`

**Migrations:**
- `database/migrations/2026_02_12_214853_create_threads_table.php`
- `database/migrations/2026_02_12_214857_create_messages_table.php`
- `database/migrations/2026_02_12_214859_create_thread_participants_table.php`
- `database/migrations/2026_02_12_214903_create_message_flags_table.php`
- `database/migrations/2026_02_14_070835_add_is_viewer_to_thread_participants_table.php`

**Console Commands:**
- `app/Console/Commands/SendTicketDigests.php`

**Scheduled Tasks (routes/console.php):**
- `tickets:send-digests daily` -- daily at 8am
- `tickets:send-digests weekly` -- weekly on Mondays at 8am

**Tests:**
- `tests/Feature/Tickets/TicketsListTest.php`
- `tests/Feature/Tickets/ViewTicketTest.php`
- `tests/Feature/Tickets/CreateTicketTest.php`
- `tests/Feature/Tickets/ThreadAuthorizationTest.php`
- `tests/Feature/Tickets/NotificationTest.php`
- `tests/Feature/Tickets/MessageFlaggingTest.php`
- `tests/Feature/Tickets/TicketActivityLoggingTest.php`
- `tests/Feature/Tickets/SendTicketDigestsTest.php`
- `tests/Feature/Services/TicketNotificationServiceCategoryTest.php`

**Config:** None specific

**Other:**
- `resources/views/mail/new-ticket.blade.php` (mail template)
- `resources/views/mail/new-ticket-reply.blade.php` (mail template)
- `resources/views/mail/ticket-assigned.blade.php` (mail template)
- `resources/views/mail/message-flagged.blade.php` (mail template)
- `resources/views/mail/ticket-digest.blade.php` (mail template)
- `app/Notifications/Channels/PushoverChannel.php` (custom notification channel)
- `app/Notifications/Channels/DiscordChannel.php` (custom notification channel)

---

## 18. Known Issues & Improvement Opportunities

1. **Reroute ability defined but unused**: `ThreadPolicy::reroute()` is defined and tested but no UI or component method implements department rerouting. Either implement the feature or remove the dead policy method.

2. **Ticket creation logic in components, not actions**: Both `create-ticket` and `create-admin-ticket` components contain inline business logic for creating threads, messages, and sending notifications. This should be extracted into `CreateTicket` and `CreateAdminTicket` action classes per project conventions.

3. **`closeTicket()` depends on `system@lighthouse.local` user**: The method calls `User::where('email', 'system@lighthouse.local')->firstOrFail()`, which will throw an exception if the system user doesn't exist. This should be handled more gracefully.

4. **`view-ticket` component uses `RecordActivity::run()` in `processReply()` but `FlagMessage` also calls it**: Consistent, but the component has significant business logic that could be extracted to actions.

5. **`create-admin-ticket` loads ALL users**: The `users()` computed property queries `User::orderBy('name')->get()` with no pagination or filtering. This could cause performance issues as the user base grows.

6. **No authorization check on `create-ticket`**: The `createTicket()` method doesn't call `$this->authorize('create', Thread::class)` -- it relies on the user being able to reach the page. If a brigged user navigates directly, they could create a ticket. The route should have `can:create,App\Models\Thread` middleware (like `create-admin` does).

7. **Potential N+1 in ticket list**: While the query eager-loads several relationships, the `isUnread()` method accesses `$thread->participants` collection which is pre-loaded only for the current user. This is efficient but could be confusing for maintenance.

8. **Forum and DirectMessage thread types unused**: `ThreadType::DirectMessage` and `ThreadType::Forum` are defined but have no implementation, routes, or UI.

9. **Digest command doesn't include participants-only filtering**: The digest query for non-viewAll users includes participant tickets but doesn't filter by `is_viewer`, so viewers may receive digest entries for tickets they're only viewing.

10. **Missing `Flux` import in `create-ticket` component**: The `createTicket()` method calls `Flux::toast()` but doesn't import `use Flux\Flux;` at the top. This likely works due to auto-resolution but is inconsistent with other components.
