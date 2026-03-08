# Notification System -- Technical Documentation

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

The Notification System is the centralized infrastructure for delivering notifications to users across multiple channels: **email**, **Pushover** (mobile push), and **Discord DM**. It is not a standalone user-facing feature but rather a cross-cutting service layer used by virtually every other feature in the application (tickets, brig, promotions, discipline reports, announcements, parent portal, meetings, Minecraft commands).

The core of the system is the `TicketNotificationService` (a misnomer — it handles all notification categories, not just tickets). This service determines which channels a notification should be sent through based on the user's per-category preferences (`notification_preferences` JSON column) and channel availability (Pushover key configured, Discord account linked). It supports four notification categories: **tickets**, **account**, **staff_alerts**, and **announcements**.

Users configure their notification preferences through the Settings → Notifications page, where they can set their Pushover key, choose a ticket email digest frequency (immediate, daily, weekly), and toggle email/Pushover/Discord per category. The ticket digest system defers email delivery for users who prefer batched summaries, sending them via scheduled Artisan commands.

The system includes 21 notification classes, 2 custom channels (PushoverChannel and DiscordChannel), a digest command, a notification-read tracking middleware, and a background job for announcement broadcasting. All notifications except `ParentAccountNotification`, `TicketDigestNotification`, and `MinecraftCommandNotification` follow a standard pattern using `setChannels()` for dynamic channel configuration.

---

## 2. Database Schema

### `users` table (notification-related columns)

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `pushover_key` | string | Yes | null | User's Pushover API user key |
| `pushover_monthly_count` | integer | No | 0 | Monthly Pushover message counter |
| `pushover_count_reset_at` | timestamp | Yes | null | When the monthly counter was last reset |
| `last_notification_read_at` | timestamp | Yes | null | Updated by `track-notification-read` middleware |
| `email_digest_frequency` | string | No | 'immediate' | Cast to `EmailDigestFrequency` enum |
| `last_ticket_digest_sent_at` | timestamp | Yes | null | Tracks last digest send to prevent duplicates |
| `notification_preferences` | json | Yes | null | Per-category channel preferences |

**Migration(s):**
- `database/migrations/2026_02_12_214908_add_notification_fields_to_users_table.php` — adds `pushover_key`, `pushover_monthly_count`, `pushover_count_reset_at`, `last_notification_read_at`, `email_digest_frequency`
- `database/migrations/2026_02_13_011545_add_notification_preferences_to_users_table.php` — adds `notification_preferences` JSON column

---

## 3. Models & Relationships

### User (`app/Models/User.php`) — Notification-Related

**Traits:** `Notifiable` (Laravel's built-in trait enabling `$user->notify()`)

**Fillable (notification-related):** `pushover_key`, `email_digest_frequency`, `notification_preferences`

**Casts:**
- `email_digest_frequency` => `EmailDigestFrequency::class`
- `last_notification_read_at` => `datetime`
- `last_ticket_digest_sent_at` => `datetime`
- `pushover_count_reset_at` => `datetime`
- `notification_preferences` => `array`

**Key Methods:**
- `canSendPushover(): bool` — checks if user has a `pushover_key` and is under the 10,000/month limit; auto-resets counter at month boundary
- `incrementPushoverCount(): void` — increments `pushover_monthly_count` by 1
- `hasDiscordLinked(): bool` — checks if user has an active Discord account linked (via `discordAccounts()->active()->exists()`)

---

## 4. Enums Reference

### EmailDigestFrequency (`app/Enums/EmailDigestFrequency.php`)

| Case | Value | Label | Notes |
|------|-------|-------|-------|
| `Immediate` | `'immediate'` | Immediate | Ticket emails sent immediately |
| `Daily` | `'daily'` | Daily Digest | Ticket emails batched into daily digest |
| `Weekly` | `'weekly'` | Weekly Digest | Ticket emails batched into weekly digest |

**Methods:**
- `label(): string` — returns human-readable label

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

No dedicated gates for the notification system itself. The notification settings page requires authentication via route middleware only.

The Staff Alerts category in the UI is conditionally shown with `@can('manage-stowaway-users')`, which gates visibility of that preference section to staff who can review stowaways.

### Policies

Not applicable for this feature. Notification delivery is not gated by policies — it is controlled by user preferences and the `TicketNotificationService` channel resolution logic.

### Permissions Matrix

| User Type | Access Settings Page | Configure Email | Configure Pushover | Configure Discord | See Staff Alerts Category |
|-----------|---------------------|-----------------|-------------------|-------------------|--------------------------|
| Unauthenticated | No | — | — | — | — |
| Regular User | Yes | Yes | Yes (if key set) | Yes (if linked) | No |
| Staff (CrewMember+) | Yes | Yes | Yes (if key set) | Yes (if linked) | Yes |
| Admin | Yes | Yes | Yes (if key set) | Yes (if linked) | Yes |

---

## 6. Routes

| Method | URL | Middleware | Handler | Route Name |
|--------|-----|-----------|---------|------------|
| GET | `/settings/notifications` | auth | `Volt::route('settings.notifications')` | `settings.notifications` |

**Middleware note:** The `track-notification-read` middleware alias (→ `UpdateLastNotificationRead`) is applied to the `/tickets` route group, not the notification settings page. It updates `last_notification_read_at` whenever a user visits ticket pages, which the digest system uses to determine if immediate emails should be sent.

---

## 7. User Interface Components

### Notification Settings
**File:** `resources/views/livewire/settings/notifications.blade.php`
**Route:** `/settings/notifications` (route name: `settings.notifications`)

**Purpose:** Allows users to configure their notification delivery preferences across all channels and categories.

**Authorization:** Route-level `auth` middleware only.

**PHP Properties:**
- `$pushover_key` (string) — user's Pushover API key
- `$email_digest_frequency` (string) — immediate/daily/weekly
- `$pushover_monthly_count` (int) — display only
- `$pushover_count_reset_at` (string|null) — display only
- `$notify_{category}_{channel}` (bool) — 12 properties for 4 categories × 3 channels

**Key Methods:**
- `mount()` — loads current preferences from user model
- `updateNotificationSettings()` — validates all fields, saves `pushover_key`, `email_digest_frequency`, and builds `notification_preferences` JSON structure, calls `$user->save()`, shows success toast

**Validation Rules:**
- `pushover_key` — nullable, string, max 255
- `email_digest_frequency` — required, in:immediate,daily,weekly
- All 12 `notify_*` booleans — boolean

**UI Elements:**
- **Ticket Email Frequency** — radio group with 3 options (Immediate, Daily Digest, Weekly Digest)
- **Pushover User Key** — text input with link to pushover.net
- **Notification Preferences** — 4 category cards (Tickets, Account, Announcements, Staff Alerts), each with Email/Pushover/Discord switches
  - Pushover switches disabled with tooltip if no key configured
  - Discord switches disabled with tooltip if no Discord account linked
  - Staff Alerts card only visible to users who `@can('manage-stowaway-users')`
- **Save button**

---

## 8. Actions (Business Logic)

The notification system does not have its own Action classes. Instead, it is consumed by actions across the application. The following actions use `TicketNotificationService`:

| Action | Notification Sent | Category |
|--------|-------------------|----------|
| `PromoteUser` | `UserPromotedToStowawayNotification` (to staff) | `staff_alerts` |
| `PromoteUser` | `UserPromotedToTravelerNotification` (to user) | `account` |
| `PromoteUser` | `UserPromotedToResidentNotification` (to user) | `account` |
| `PutUserInBrig` | `UserPutInBrigNotification` | `account` |
| `ReleaseUserFromBrig` | `UserReleasedFromBrigNotification` | `account` |
| `CreateChildAccount` | `ChildWelcomeNotification` | `account` |
| `UpdateChildPermission` | `ParentAccountDisabledNotification` or `ParentAccountEnabledNotification` | `account` |
| `CreateDisciplineReport` | `DisciplineReportPendingReviewNotification` (to staff) | `staff_alerts` |
| `PublishDisciplineReport` | `DisciplineReportPublishedNotification` (to subject) | `account` |
| `PublishDisciplineReport` | `DisciplineReportPublishedParentNotification` (to parent) | `account` |
| `FlagMessage` | `MessageFlaggedNotification` (to staff) | `tickets` (default) |
| `SendMinecraftCommand` | `MinecraftCommandNotification` | N/A (direct notify, custom channel) |

**Livewire components that send notifications directly:**
- `create-ticket.blade.php` — `NewTicketNotification` to staff (category: `tickets` default)
- `create-admin-ticket.blade.php` — `NewTicketNotification` to target user and staff
- `view-ticket.blade.php` — `NewTicketReplyNotification` to participants; `TicketAssignedNotification` to assignee and creator
- `in-brig-card.blade.php` — `NewTicketNotification` to quartermasters (for brig appeal)

---

## 9. Notifications

### AccountUnlockedNotification (`app/Notifications/AccountUnlockedNotification.php`)

**Triggered by:** `ProcessAgeTransitions` command (when child turns 13 with no parent)
**Recipient:** The user whose account was unlocked
**Channels:** mail, pushover (optional), discord (optional)
**Mail subject:** "Your Lighthouse Account Has Been Unlocked!"
**Queued:** Yes

### BrigTimerExpiredNotification (`app/Notifications/BrigTimerExpiredNotification.php`)

**Triggered by:** `CheckBrigTimers` command
**Recipient:** User whose brig timer has expired
**Channels:** mail, pushover (optional), discord (optional)
**Mail subject:** "Your Brig Period Has Ended — You May Now Appeal"
**Queued:** Yes

### ChildWelcomeNotification (`app/Notifications/ChildWelcomeNotification.php`)

**Triggered by:** `CreateChildAccount` action
**Recipient:** The child user
**Channels:** mail, pushover (optional) — no discord
**Mail subject:** "Welcome to Lighthouse!"
**Queued:** Yes

### DisciplineReportPendingReviewNotification (`app/Notifications/DisciplineReportPendingReviewNotification.php`)

**Triggered by:** `CreateDisciplineReport` action
**Recipient:** Staff members (Officers+)
**Channels:** mail, pushover (optional) — no discord
**Mail subject:** "Discipline Report Pending Review"
**Queued:** Yes

### DisciplineReportPublishedNotification (`app/Notifications/DisciplineReportPublishedNotification.php`)

**Triggered by:** `PublishDisciplineReport` action
**Recipient:** The report subject (user)
**Channels:** mail, pushover (optional) — no discord
**Mail subject:** "Staff Report Recorded"
**Queued:** Yes

### DisciplineReportPublishedParentNotification (`app/Notifications/DisciplineReportPublishedParentNotification.php`)

**Triggered by:** `PublishDisciplineReport` action
**Recipient:** The parent of the report subject
**Channels:** mail, pushover (optional) — no discord
**Mail subject:** Dynamic — "Staff Conversation Recorded for Your Child" (Trivial/Minor) or "Staff Report Recorded for Your Child" (Moderate+)
**Queued:** Yes
**Special:** Has `isConversationSeverity()` method that changes wording for Trivial/Minor severity

### MeetingReportReminderNotification (`app/Notifications/MeetingReportReminderNotification.php`)

**Triggered by:** `SendMeetingReportReminders` command
**Recipient:** Staff members who haven't submitted check-ins
**Channels:** mail, pushover (optional), discord (optional)
**Mail subject:** "Reminder: Submit your check-in for {meeting title}"
**Queued:** Yes

### MessageFlaggedNotification (`app/Notifications/MessageFlaggedNotification.php`)

**Triggered by:** `FlagMessage` action
**Recipient:** Staff members
**Channels:** mail, pushover (optional), discord (optional)
**Mail subject:** "Message Flagged for Review"
**Queued:** Yes

### MinecraftCommandNotification (`app/Notifications/MinecraftCommandNotification.php`)

**Triggered by:** `SendMinecraftCommand` action
**Recipient:** On-demand (routed to 'minecraft' channel)
**Channels:** minecraft (custom — uses `MinecraftRconService`)
**Queued:** Yes
**Special:** Custom retry policy: `tries = 3`, `backoff = [60, 300, 900]` (1 min, 5 min, 15 min). Does not use `setChannels()` or `TicketNotificationService`.

### NewAnnouncementNotification (`app/Notifications/NewAnnouncementNotification.php`)

**Triggered by:** `SendAnnouncementNotifications` job
**Recipient:** All Traveler+ members (excluding author)
**Channels:** mail, pushover (optional), discord (optional)
**Mail subject:** "New Announcement: {title}"
**Queued:** Yes

### NewTicketNotification (`app/Notifications/NewTicketNotification.php`)

**Triggered by:** `create-ticket`, `create-admin-ticket`, `in-brig-card` Livewire components
**Recipient:** Staff members or target users
**Channels:** mail, pushover (optional), discord (optional)
**Mail subject:** "New Ticket: {subject}"
**Queued:** Yes

### NewTicketReplyNotification (`app/Notifications/NewTicketReplyNotification.php`)

**Triggered by:** `view-ticket` Livewire component
**Recipient:** Thread participants (excluding sender)
**Channels:** mail, pushover (optional), discord (optional)
**Mail subject:** "New Reply: {thread subject}"
**Queued:** Yes
**Special:** Uses `Str::limit()` for message preview (100 chars for Pushover, 200 for Discord)

### ParentAccountDisabledNotification (`app/Notifications/ParentAccountDisabledNotification.php`)

**Triggered by:** `UpdateChildPermission` action
**Recipient:** The child user
**Channels:** mail, pushover (optional) — no discord
**Mail subject:** "Your Account Has Been Restricted"
**Queued:** Yes

### ParentAccountEnabledNotification (`app/Notifications/ParentAccountEnabledNotification.php`)

**Triggered by:** `UpdateChildPermission` action
**Recipient:** The child user
**Channels:** mail, pushover (optional) — no discord
**Mail subject:** "Your Account Has Been Enabled"
**Queued:** Yes

### ParentAccountNotification (`app/Notifications/ParentAccountNotification.php`)

**Triggered by:** `CreateChildAccount` action (via `Notification::route('mail', $email)`)
**Recipient:** Parent's email address (on-demand, not a User model)
**Channels:** mail only (hardcoded)
**Mail subject:** "Your Child Has Created a Lighthouse Account"
**Queued:** Yes
**Special:** Exception to `TicketNotificationService` pattern — uses on-demand notification because the parent may not have a user account. Documented in PHPDoc.

### TicketAssignedNotification (`app/Notifications/TicketAssignedNotification.php`)

**Triggered by:** `view-ticket` Livewire component (assign action)
**Recipient:** Assigned staff member and ticket creator
**Channels:** mail, pushover (optional), discord (optional)
**Mail subject:** "Ticket Assigned: {subject}"
**Queued:** Yes

### TicketDigestNotification (`app/Notifications/TicketDigestNotification.php`)

**Triggered by:** `SendTicketDigests` command
**Recipient:** Users with daily/weekly digest preference
**Channels:** mail only (hardcoded)
**Mail subject:** "Ticket Digest - {date}"
**Queued:** Yes
**Special:** Shows first 10 tickets with remaining count. Does not use `setChannels()`.

### UserPromotedToResidentNotification (`app/Notifications/UserPromotedToResidentNotification.php`)

**Triggered by:** `PromoteUser` action
**Recipient:** The promoted user
**Channels:** mail, pushover (optional), discord (optional)
**Mail subject:** "Welcome, Resident {name}!"
**Queued:** Yes

### UserPromotedToStowawayNotification (`app/Notifications/UserPromotedToStowawayNotification.php`)

**Triggered by:** `PromoteUser` action
**Recipient:** Staff members (via `sendToMany`)
**Channels:** mail, pushover (optional), discord (optional)
**Mail subject:** "New Stowaway User: {name}"
**Queued:** Yes

### UserPromotedToTravelerNotification (`app/Notifications/UserPromotedToTravelerNotification.php`)

**Triggered by:** `PromoteUser` action
**Recipient:** The promoted user
**Channels:** mail, pushover (optional), discord (optional)
**Mail subject:** "Welcome to Traveler Status!"
**Queued:** Yes

### UserPutInBrigNotification (`app/Notifications/UserPutInBrigNotification.php`)

**Triggered by:** `PutUserInBrig` action
**Recipient:** The brigged user
**Channels:** mail, pushover (optional), discord (optional)
**Mail subject:** "You Have Been Placed in the Brig"
**Queued:** Yes
**Special:** Conditional messaging based on whether `expiresAt` is set

### UserReleasedFromBrigNotification (`app/Notifications/UserReleasedFromBrigNotification.php`)

**Triggered by:** `ReleaseUserFromBrig` action
**Recipient:** The released user
**Channels:** mail, pushover (optional), discord (optional)
**Mail subject:** "You Have Been Released from the Brig"
**Queued:** Yes

---

## 10. Background Jobs

### SendAnnouncementNotifications (`app/Jobs/SendAnnouncementNotifications.php`)

**Triggered by:** Announcement publishing (dispatched when an announcement is published)
**What it does:**
1. Re-verifies the announcement is still published
2. Queries all Traveler+ users (excluding the author)
3. Sends `NewAnnouncementNotification` via `TicketNotificationService::sendToMany()` with category `'announcements'`
4. Chunks users in batches of 100 for memory efficiency
5. Also calls `PostAnnouncementToDiscord::run()` for server-wide Discord channel post
**Queue/Delay:** Default queue, no delay

---

## 11. Console Commands & Scheduled Tasks

### `tickets:send-digests {frequency}`
**File:** `app/Console/Commands/SendTicketDigests.php`
**Scheduled:** Yes — daily at 08:00 (`daily`), Mondays at 08:00 (`weekly`)
**What it does:**
1. Queries users matching the given `EmailDigestFrequency` (Daily or Weekly)
2. For each user, finds threads they can access with activity since `last_ticket_digest_sent_at` (falls back to `last_notification_read_at`, `created_at`, or 30 days ago)
3. Respects ticket authorization (`viewAll`, `viewDepartment`, `viewFlagged`)
4. Builds ticket summaries with message counts (batch query)
5. Sends `TicketDigestNotification` directly via `$user->notify()` (bypasses `TicketNotificationService`)
6. Updates `last_ticket_digest_sent_at` timestamp

### `brig:check-timers`
**File:** `app/Console/Commands/CheckBrigTimers.php`
**Scheduled:** Yes — daily at 09:00
**What it does:** Finds users with expired brig timers (`brig_timer_notified = false`), sends `BrigTimerExpiredNotification` via `TicketNotificationService` with category `'account'`, sets `brig_timer_notified = true`

### `meetings:send-report-reminders`
**File:** `app/Console/Commands/SendMeetingReportReminders.php`
**Scheduled:** Yes — daily at 08:00
**What it does:** Finds upcoming staff meetings within `meeting_report_notify_days` window, sends `MeetingReportReminderNotification` to staff who haven't submitted or been notified, creates/updates `MeetingReport` records with `notified_at` timestamp. Uses category `'staff_alerts'`.

### `parent-portal:process-age-transitions`
**File:** `app/Console/Commands/ProcessAgeTransitions.php`
**Scheduled:** Yes — daily at 02:00
**What it does:** Among other things, sends `AccountUnlockedNotification` via `TicketNotificationService` with category `'account'` when a child turns 13 with no parent account.

---

## 12. Services

### TicketNotificationService (`app/Services/TicketNotificationService.php`)

**Purpose:** Central notification delivery service that determines which channels to use based on user preferences, then sends the notification. Despite the name, it handles all notification categories.

**Key methods:**

- `send(User $user, Notification $notification, string $category = 'tickets'): void`
  - Calls `determineChannels()` to get allowed channels
  - Returns early if no channels
  - Increments Pushover count if sending via Pushover
  - Calls `$notification->setChannels($channels, $user->pushover_key)` then `$user->notify($notification)`

- `sendToMany(iterable $users, Notification $notification, string $category = 'tickets'): void`
  - Iterates users and calls `send()` for each

- `shouldSendImmediate(User $user): bool`
  - Returns `true` if user prefers `Immediate` digest frequency
  - For Daily/Weekly users: returns `true` only if `last_notification_read_at` is within the last hour (meaning user is actively browsing)

- `determineChannels(User $user, string $category = 'tickets'): array`
  - Reads `notification_preferences[$category]` (with defaults)
  - Adds `'mail'` if email enabled; for tickets category, defers to digest if not immediate
  - Adds `'pushover'` if enabled and `canSendPushover()` returns true
  - Adds `'discord'` if enabled and `hasDiscordLinked()` returns true

- `defaultPreferences(string $category): array` (protected)
  - Returns `['email' => true, 'pushover' => false, 'discord' => false]` for all categories

**Called by:** 9 Action classes, 4 Livewire components, 3 Console Commands, 1 Job class (see Section 8)

### Custom Notification Channels

#### PushoverChannel (`app/Notifications/Channels/PushoverChannel.php`)

**Purpose:** Delivers notifications via the Pushover API.

**How it works:**
1. Checks notification has `toPushover()` method
2. Resolves Pushover key: prefers `$notification->getPushoverKey()`, falls back to `$notifiable->pushover_key`
3. Posts to `https://api.pushover.net/1/messages.json` with `token`, `user`, `message`, `title`, `url`, `priority`
4. Requires `services.pushover.token` config

#### DiscordChannel (`app/Notifications/Channels/DiscordChannel.php`)

**Purpose:** Delivers notifications as Discord DMs via `DiscordApiService`.

**How it works:**
1. Checks notification has `toDiscord()` method
2. Checks notifiable has `discordAccounts()` relationship
3. Iterates active Discord accounts and sends DM via `DiscordApiService::sendDirectMessage()`
4. Logs warnings on failure (does not throw)

---

## 13. Activity Log Entries

Not applicable for this feature. Notification delivery does not create activity log entries. (Individual features that send notifications may log their own activity — see their respective documentation.)

---

## 14. Data Flow Diagrams

### Sending a Notification (Standard Pattern)

```
Feature action/component needs to notify a user
  -> $notificationService = app(TicketNotificationService::class)
  -> $notificationService->send($user, new SomeNotification(...), 'category')
    -> determineChannels($user, 'category')
      -> Reads $user->notification_preferences['category']
      -> Checks email preference (for tickets: respects digest frequency)
      -> Checks pushover preference + canSendPushover()
      -> Checks discord preference + hasDiscordLinked()
      -> Returns array of channel strings: ['mail', 'pushover', 'discord']
    -> If channels empty → return (skip notification)
    -> If 'pushover' in channels → $user->incrementPushoverCount()
    -> $notification->setChannels($channels, $user->pushover_key)
    -> $user->notify($notification)
      -> Laravel dispatches to queue (ShouldQueue)
      -> Queue worker processes notification:
        -> $notification->via() returns $allowedChannels
        -> For 'mail': $notification->toMail() → sends email
        -> For 'pushover': PushoverChannel::send() → HTTP POST to Pushover API
        -> For 'discord': DiscordChannel::send() → DiscordApiService DM
```

### Updating Notification Preferences

```
User navigates to /settings/notifications
  -> GET /settings/notifications (middleware: auth)
    -> Volt component mounts, loads user preferences into 12 boolean properties
    -> Displays radio cards for digest frequency
    -> Displays Pushover key input
    -> Displays 4 category cards with Email/Pushover/Discord toggles
      -> Pushover toggles disabled if no key set
      -> Discord toggles disabled if no Discord account linked
      -> Staff Alerts card hidden unless @can('manage-stowaway-users')

User modifies settings and clicks "Save Notification Settings"
  -> updateNotificationSettings() fires
    -> Validates all 14+ fields
    -> Updates user.pushover_key
    -> Updates user.email_digest_frequency (cast to enum)
    -> Builds notification_preferences JSON with 4 categories × 3 channels
    -> $user->save()
    -> Flux::toast('Notification settings updated successfully!', variant: 'success')
```

### Ticket Digest Flow

```
Scheduler triggers tickets:send-digests daily (daily at 08:00)
  -> SendTicketDigests::handle()
    -> Query users with email_digest_frequency = Daily
    -> For each user:
      -> Determine "since" date (last_ticket_digest_sent_at or fallbacks)
      -> Query visible threads with activity since that date
      -> If no tickets → skip
      -> Batch-query message counts per thread
      -> Build ticket summary array
      -> $user->notify(new TicketDigestNotification($ticketSummary))
      -> Update user.last_ticket_digest_sent_at = now()
```

### Notification Read Tracking

```
User visits any /tickets/* route
  -> Middleware: track-notification-read (UpdateLastNotificationRead)
    -> $request->user()->update(['last_notification_read_at' => now()])
  -> This timestamp is used by:
    -> TicketNotificationService::shouldSendImmediate() — if visited within last hour, send immediate even for digest users
    -> SendTicketDigests — as fallback "since" date for digest queries
```

---

## 15. Configuration

| Key | Default | Purpose |
|-----|---------|---------|
| `services.pushover.token` | `env('PUSHOVER_TOKEN')` | Pushover API application token for sending push notifications |
| `lighthouse.meeting_report_notify_days` | `3` | Days before a meeting to start sending check-in reminders |

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Unit/Notifications/BrigTimerExpiredNotificationTest.php` | 10 | Channel routing, queuing, mail template, Pushover content |
| `tests/Unit/Notifications/UserPutInBrigNotificationTest.php` | 11 | Channel routing, queuing, mail template, Pushover content, expiry logic |
| `tests/Unit/Notifications/UserReleasedFromBrigNotificationTest.php` | 9 | Channel routing, queuing, mail template, Pushover content |
| `tests/Unit/Notifications/UserPromotedToStowawayNotificationTest.php` | 9 | Channel routing, queuing, mail template, Pushover content |
| `tests/Unit/Notifications/TicketDigestNotificationTest.php` | 8 | Digest construction, mail-only channel, truncation to 10 tickets |
| `tests/Unit/Notifications/ChildWelcomeNotificationTest.php` | 7 | Channel routing, queuing, mail template, Pushover content |
| `tests/Unit/Notifications/ParentAccountEnabledNotificationTest.php` | 7 | Channel routing, queuing, mail template, Pushover content |
| `tests/Unit/Notifications/ParentAccountDisabledNotificationTest.php` | 7 | Channel routing, queuing, mail template, Pushover content |
| `tests/Unit/Notifications/DisciplineReportPublishedParentNotificationTest.php` | 9 | Channel routing, queuing, severity-based wording |
| `tests/Feature/Notifications/ParentAccountNotificationTest.php` | 4 | On-demand mail, approval data, age-based content |
| `tests/Feature/Services/TicketNotificationServiceCategoryTest.php` | 9 | Category-based channel resolution, digest deferral, Discord inclusion/exclusion, default preferences, Pushover inclusion, sendToMany |

### Test Case Inventory

**BrigTimerExpiredNotificationTest:**
1. sends via mail channel when mail is allowed
2. sends via pushover channel when allowed and key is set
3. does not send via pushover without a key
4. is queued for background processing
5. toMail has correct subject
6. toMail uses brig-timer-expired markdown template
7. toPushover has correct title
8. toPushover includes dashboard url
9. toPushover message mentions appeal
10. setChannels returns self for fluent chaining

**UserPutInBrigNotificationTest:**
1. sends via mail channel when mail is allowed
2. sends via pushover channel when allowed and key is set
3. does not send via pushover without a key
4. is queued for background processing
5. toMail uses brig-placed markdown template
6. toMail passes null expiresAt when no expiry set
7. toMail passes expiresAt when set
8. toPushover includes reason in message
9. toPushover includes expiry when set
10. toPushover includes appeal-now message when no expiry
11. setChannels returns self for fluent chaining

**UserReleasedFromBrigNotificationTest:**
1. sends via mail channel when mail is allowed
2. sends via pushover channel when allowed and key is set
3. does not send via pushover without a key
4. is queued for background processing
5. toMail has correct subject
6. toMail uses brig-released markdown template
7. toPushover has correct title
8. toPushover includes dashboard url
9. setChannels returns self for fluent chaining

**UserPromotedToStowawayNotificationTest:**
1. sends via mail channel when mail is allowed
2. sends via pushover channel when allowed and key is set
3. does not send via pushover without a key
4. is queued for background processing
5. toMail subject includes stowaway username
6. toMail passes stowaway name to template
7. toPushover title includes stowaway username
8. toPushover message references the stowaway
9. setChannels returns self for fluent chaining

**TicketDigestNotificationTest:**
1. creates digest with ticket data
2. sends via mail channel only
3. is queued for background processing
4. formats email subject with current date
5. uses ticket-digest markdown template with correct data
6. truncates to 10 tickets maximum in view data
7. calculates remaining count correctly for 11 tickets
8. has zero remaining when 10 or fewer tickets

**ChildWelcomeNotificationTest:**
1. uses the child-welcome template
2. includes parent name in template data
3. has correct subject line
4. pushover includes parent name
5. sends via mail channel when allowed
6. sends via pushover channel when allowed and key is set
7. is queued for background processing

**ParentAccountEnabledNotificationTest:**
1. uses the parent-account-enabled template
2. includes parent name and dashboard url in template data
3. has correct subject line
4. pushover includes parent name
5. sends via mail channel when allowed
6. sends via pushover channel when allowed and key is set
7. is queued for background processing

**ParentAccountDisabledNotificationTest:**
1. uses the parent-account-disabled template
2. includes parent name in template data
3. has correct subject line
4. pushover includes parent name
5. sends via mail channel when allowed
6. sends via pushover channel when allowed and key is set
7. is queued for background processing

**DisciplineReportPublishedParentNotificationTest:**
1. uses the parent template
2. passes child name to template
3. uses conversation wording in subject for trivial/minor severity
4. uses staff report wording in subject for moderate+ severity
5. pushover uses conversation wording for trivial/minor severity
6. pushover uses staff report wording for moderate+ severity
7. sends via mail channel when allowed
8. sends via pushover channel when allowed and key is set
9. is queued for background processing

**ParentAccountNotificationTest:**
1. sends via mail channel
2. passes approval data for under-13 child
3. passes non-approval data for 13+ child
4. can be sent as an on-demand notification

**TicketNotificationServiceCategoryTest:**
1. always sends immediate email for account category regardless of digest preference
2. always sends immediate email for staff_alerts category regardless of digest preference
3. defers ticket emails to digest when user prefers daily and has not visited recently
4. includes discord channel when user has linked account and enabled preference
5. excludes discord channel when user has no linked account
6. returns empty channels when user disables all preferences for a category
7. uses default preferences when category has no saved preferences
8. includes pushover for account category when user has key and preference enabled
9. sends to multiple users with category parameter

### Coverage Gaps

- **No tests for the notification settings UI** — the `settings/notifications` Volt component's `mount()` and `updateNotificationSettings()` methods are untested
- **No tests for `UpdateLastNotificationRead` middleware** — the `track-notification-read` middleware is not directly tested
- **No tests for `PushoverChannel`** — the HTTP POST to Pushover API is untested
- **No tests for `DiscordChannel`** — the Discord DM delivery is untested
- **No tests for `SendTicketDigests` command** — the digest compilation logic (thread visibility, message counting, `since` date fallbacks) is untested
- **No tests for `SendAnnouncementNotifications` job** — chunked delivery, re-verification of published status are untested (though `tests/Feature/Jobs/SendAnnouncementNotificationsTest.php` exists — may cover some)
- **No tests for several notification classes:** `AccountUnlockedNotification`, `DisciplineReportPendingReviewNotification`, `DisciplineReportPublishedNotification`, `MeetingReportReminderNotification`, `MessageFlaggedNotification`, `MinecraftCommandNotification`, `NewAnnouncementNotification`, `NewTicketNotification`, `NewTicketReplyNotification`, `TicketAssignedNotification`, `UserPromotedToResidentNotification`, `UserPromotedToTravelerNotification` — these have no dedicated unit tests
- **No integration test** verifying end-to-end flow from action → service → channel delivery

---

## 17. File Map

**Models:**
- `app/Models/User.php` (notification-related columns and methods)

**Enums:**
- `app/Enums/EmailDigestFrequency.php`

**Actions:** None specific to the notification system (consumed by other feature actions)

**Policies:** None

**Gates:** None specific (Staff Alerts UI gated by `manage-stowaway-users` from `AuthServiceProvider`)

**Notifications:**
- `app/Notifications/AccountUnlockedNotification.php`
- `app/Notifications/BrigTimerExpiredNotification.php`
- `app/Notifications/ChildWelcomeNotification.php`
- `app/Notifications/DisciplineReportPendingReviewNotification.php`
- `app/Notifications/DisciplineReportPublishedNotification.php`
- `app/Notifications/DisciplineReportPublishedParentNotification.php`
- `app/Notifications/MeetingReportReminderNotification.php`
- `app/Notifications/MessageFlaggedNotification.php`
- `app/Notifications/MinecraftCommandNotification.php`
- `app/Notifications/NewAnnouncementNotification.php`
- `app/Notifications/NewTicketNotification.php`
- `app/Notifications/NewTicketReplyNotification.php`
- `app/Notifications/ParentAccountDisabledNotification.php`
- `app/Notifications/ParentAccountEnabledNotification.php`
- `app/Notifications/ParentAccountNotification.php`
- `app/Notifications/TicketAssignedNotification.php`
- `app/Notifications/TicketDigestNotification.php`
- `app/Notifications/UserPromotedToResidentNotification.php`
- `app/Notifications/UserPromotedToStowawayNotification.php`
- `app/Notifications/UserPromotedToTravelerNotification.php`
- `app/Notifications/UserPutInBrigNotification.php`
- `app/Notifications/UserReleasedFromBrigNotification.php`

**Custom Channels:**
- `app/Notifications/Channels/PushoverChannel.php`
- `app/Notifications/Channels/DiscordChannel.php`

**Jobs:**
- `app/Jobs/SendAnnouncementNotifications.php`

**Services:**
- `app/Services/TicketNotificationService.php`

**Controllers:** None

**Volt Components:**
- `resources/views/livewire/settings/notifications.blade.php`

**Middleware:**
- `app/Http/Middleware/UpdateLastNotificationRead.php`

**Routes:**
- `settings.notifications` — `GET /settings/notifications`

**Migrations:**
- `database/migrations/2026_02_12_214908_add_notification_fields_to_users_table.php`
- `database/migrations/2026_02_13_011545_add_notification_preferences_to_users_table.php`

**Console Commands:**
- `app/Console/Commands/SendTicketDigests.php` (`tickets:send-digests`)
- `app/Console/Commands/CheckBrigTimers.php` (`brig:check-timers`)
- `app/Console/Commands/SendMeetingReportReminders.php` (`meetings:send-report-reminders`)
- `app/Console/Commands/ProcessAgeTransitions.php` (`parent-portal:process-age-transitions`)

**Scheduled Tasks (from `routes/console.php`):**
- `tickets:send-digests daily` — daily at 08:00
- `tickets:send-digests weekly` — Mondays at 08:00
- `brig:check-timers` — daily at 09:00
- `meetings:send-report-reminders` — daily at 08:00
- `parent-portal:process-age-transitions` — daily at 02:00

**Tests:**
- `tests/Unit/Notifications/BrigTimerExpiredNotificationTest.php`
- `tests/Unit/Notifications/UserPutInBrigNotificationTest.php`
- `tests/Unit/Notifications/UserReleasedFromBrigNotificationTest.php`
- `tests/Unit/Notifications/UserPromotedToStowawayNotificationTest.php`
- `tests/Unit/Notifications/TicketDigestNotificationTest.php`
- `tests/Unit/Notifications/ChildWelcomeNotificationTest.php`
- `tests/Unit/Notifications/ParentAccountEnabledNotificationTest.php`
- `tests/Unit/Notifications/ParentAccountDisabledNotificationTest.php`
- `tests/Unit/Notifications/DisciplineReportPublishedParentNotificationTest.php`
- `tests/Feature/Notifications/ParentAccountNotificationTest.php`
- `tests/Feature/Services/TicketNotificationServiceCategoryTest.php`

**Config:**
- `config/services.php` — `services.pushover.token`
- `config/lighthouse.php` — `lighthouse.meeting_report_notify_days`

**Other:**
- `bootstrap/app.php` — middleware alias registration for `track-notification-read`

---

## 18. Known Issues & Improvement Opportunities

1. **Misleading service name** — `TicketNotificationService` handles all notification categories (tickets, account, staff_alerts, announcements), not just tickets. The name should be something like `NotificationDeliveryService` or `NotificationChannelService`.

2. **12 notifications lack unit tests** — `AccountUnlockedNotification`, `DisciplineReportPendingReviewNotification`, `DisciplineReportPublishedNotification`, `MeetingReportReminderNotification`, `MessageFlaggedNotification`, `MinecraftCommandNotification`, `NewAnnouncementNotification`, `NewTicketNotification`, `NewTicketReplyNotification`, `TicketAssignedNotification`, `UserPromotedToResidentNotification`, and `UserPromotedToTravelerNotification` have no dedicated tests.

3. **No test for notification settings UI** — The `settings/notifications` component has no test coverage for loading preferences, saving preferences, or validation.

4. **SendTicketDigests bypasses TicketNotificationService** — The digest command sends via `$user->notify()` directly, bypassing the service. While this makes sense (digest is already the deferred path), it's inconsistent with the pattern used everywhere else.

5. **Pushover monthly limit is hardcoded** — The 10,000/month Pushover limit in `canSendPushover()` is hardcoded in the User model. This should be a config value (`lighthouse.pushover_monthly_limit`).

6. **No error handling in TicketNotificationService** — If `$notification->setChannels()` or `$user->notify()` throws, the exception propagates unhandled. Since notifications are queued, this mainly affects the queue worker, but a try/catch with logging would be more resilient.

7. **Notification preferences not migrated for existing users** — When a user has `notification_preferences = null`, the system falls back to defaults (`email: true, pushover: false, discord: false`). This is correct, but if a user had Pushover configured before the preferences system was added, their Pushover preference would default to `false`, potentially breaking their expected behavior.

8. **`track-notification-read` middleware updates on every ticket page visit** — The middleware does an `UPDATE` query on every request to any ticket route. This could be optimized to only update if the timestamp is stale (e.g., more than 5 minutes old).

9. **Discord channel inconsistency** — Some notifications explicitly exclude Discord (`ChildWelcomeNotification`, all `DisciplineReport*`, `ParentAccount*` notifications), while others include it. The rationale for which notifications support Discord DMs vs. not is not documented in the code.

10. **No notification preferences validation on channel availability** — Users can enable Pushover or Discord preferences even if they later remove their Pushover key or unlink Discord. The preferences are saved but the `determineChannels()` method correctly ignores them — however, the UI could be clearer about this.

11. **`FlagMessage` sends with default category `'tickets'`** — The `MessageFlaggedNotification` is sent without an explicit category, defaulting to `'tickets'`. Since this is a staff alert about flagged content, it arguably should use category `'staff_alerts'` instead.
