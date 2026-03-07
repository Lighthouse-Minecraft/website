# Announcements -- Technical Documentation

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

The Announcements feature allows staff and authorized users to create, publish, schedule, and manage community announcements. Announcements appear on the dashboard in two forms: a **banner** (callout) prompting users to read and acknowledge unread announcements, and a **widget** table listing all published announcements with click-to-view detail modals.

**Who uses it:**
- **All authenticated users** see announcements on the dashboard, can read them, and acknowledge them.
- **Staff with content permissions** (Admins, Command Officers, Announcement Editors, Officers, CrewMembers in Engineer/Steward departments) can create, edit, and delete announcements via the Admin Control Panel (ACP).
- **Traveler+ members** receive notifications (email, Pushover, Discord DM) when new announcements are published, respecting their notification preferences.

**Key concepts:**
- **Publishing states**: Draft (unpublished), Published (live now), Scheduled (published_at in the future), Expired (expired_at in the past).
- **Acknowledgment**: Users must explicitly acknowledge announcements to dismiss the dashboard banner. Acknowledgment is tracked via the `announcement_user` pivot table.
- **Lazy notification dispatch**: Notifications are not sent at creation time. Instead, the first dashboard load after publication atomically claims the announcement and dispatches a queued `SendAnnouncementNotifications` job.
- **Discord cross-post**: When notifications are sent, the announcement is also posted to a configured Discord channel via the Discord API.
- **Markdown content**: Announcement content supports Markdown rendering with XSS protection (HTML stripping, unsafe link blocking).
- **Timezone-aware scheduling**: The admin UI converts datetime inputs from the user's timezone to UTC for storage.

---

## 2. Database Schema

### `announcements` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint (PK) | No | auto | Primary key |
| `title` | string | No | - | Announcement title (max 255) |
| `content` | text | No | - | Markdown content (max 10000 validated in UI) |
| `author_id` | foreignId | Yes | null | FK -> `users.id`, `SET NULL` on delete |
| `is_published` | boolean | No | false | Whether the announcement is published |
| `published_at` | timestamp | Yes | null | When the announcement becomes visible; future = scheduled |
| `expired_at` | timestamp | Yes | null | Auto-unpublish after this time; null = never expires |
| `notifications_sent_at` | timestamp | Yes | null | Set atomically when notification job is dispatched |
| `created_at` | timestamp | Yes | null | Laravel timestamp |
| `updated_at` | timestamp | Yes | null | Laravel timestamp |

**Foreign Keys:** `author_id` -> `users.id` (SET NULL on delete)

**Migrations:**
- `database/migrations/2025_08_07_163817_create_announcements_table.php` -- Creates base table with `title`, `content`, `author_id`, `is_published`, `published_at`
- `database/migrations/2026_03_06_054753_add_expired_at_and_cleanup_announcements.php` -- Adds `expired_at`, `notifications_sent_at`; drops legacy `announcement_tag`, `announcement_category`, `comments`, `tags`, `categories` tables

### `announcement_user` table (pivot -- acknowledgments)

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint (PK) | No | auto | Primary key |
| `user_id` | foreignId | No | - | FK -> `users.id`, CASCADE on delete |
| `announcement_id` | foreignId | No | - | FK -> `announcements.id`, CASCADE on delete |
| `created_at` | timestamp | Yes | null | When user acknowledged |
| `updated_at` | timestamp | Yes | null | Laravel timestamp |

**Foreign Keys:** `user_id` -> `users.id` (CASCADE), `announcement_id` -> `announcements.id` (CASCADE)

**Migration:** `database/migrations/2025_08_10_181306_create_announcement_user_table.php`

### Dropped Legacy Tables

The following tables were created in early migrations but dropped in `2026_03_06_054753`:
- `announcement_tag` (migration: `2025_08_10_033037`)
- `announcement_category` (migration: `2025_08_10_033041`)
- `comments` (created in `2025_08_10_005600`)
- `tags`, `categories` -- parent tables for the above pivots

These are no longer part of the schema.

---

## 3. Models & Relationships

### Announcement (`app/Models/Announcement.php`)

**Fillable:** `title`, `content`, `author_id`, `is_published`, `published_at`, `expired_at`, `notifications_sent_at`

**Eager Loads (default `$with`):** `['author']`

**Casts:**
- `is_published` => `boolean`
- `published_at` => `datetime`
- `expired_at` => `datetime`
- `notifications_sent_at` => `datetime`

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `author()` | belongsTo | User | Via `author_id` |
| `acknowledgers()` | belongsToMany | User | Via `announcement_user` pivot, with timestamps |

**Scopes:**
- `scopePublished($query)` -- Returns announcements where `is_published = true`, `published_at` is null or <= now, AND `expired_at` is null or > now. Excludes future-scheduled and expired announcements.
- `scopeExpired($query)` -- Returns announcements where `is_published = true`, `expired_at` is not null, and `expired_at` <= now.

**Key Methods:**
- `isExpired(): bool` -- Returns true if `expired_at` is non-null and in the past.
- `isAuthoredBy(User $user): bool` -- Checks if `author_id` matches `$user->id`.
- `authorName(): string` -- Returns author's name or "Unknown Author" if null.
- `renderedContent(): string` -- Renders content as HTML via `Str::markdown()`. Detects legacy HTML content and strips tags before markdown conversion to prevent XSS. Uses `html_input: 'strip'` and `allow_unsafe_links: false`.

### User (`app/Models/User.php`) -- Announcement-related

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `acknowledgedAnnouncements()` | belongsToMany | Announcement | Via `announcement_user` pivot, with timestamps |

---

## 4. Enums Reference

Not applicable for this feature. Announcements do not use dedicated enums. Publishing states (Draft, Published, Scheduled, Expired) are derived from the combination of `is_published`, `published_at`, and `expired_at` columns rather than an enum.

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

No announcement-specific gates are defined. Authorization is handled entirely by `AnnouncementPolicy`.

The `view-community-content` gate (which checks `!$user->in_brig`) is used on the dashboard to wrap the announcements widget, preventing brigged users from seeing the widget.

### Policies

#### AnnouncementPolicy (`app/Policies/AnnouncementPolicy.php`)

**`before()` hook:** Admins and Command department Officers bypass all checks and return `true`.

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `viewAny` | Any authenticated user | Always true |
| `view` | Any authenticated user | Always true |
| `create` | Announcement Editor role, Officer+, or CrewMember in Engineer/Steward dept | Role OR rank-based |
| `update` | Announcement Editor role, Officer+, or CrewMember in Engineer/Steward dept | Same as create |
| `acknowledge` | Any authenticated user | `$user != null` |
| `delete` | Announcement Editor role, or Officer+ in Engineer/Steward dept | More restrictive than create/update |

### Permissions Matrix

| User Type | View | Acknowledge | Create | Edit | Delete | Receive Notification |
|-----------|------|-------------|--------|------|--------|---------------------|
| Regular User (< Traveler) | Yes | Yes | No | No | No | No |
| Traveler+ | Yes | Yes | No | No | No | Yes |
| Announcement Editor (role) | Yes | Yes | Yes | Yes | Yes | Yes (if Traveler+) |
| CrewMember (Engineer/Steward) | Yes | Yes | Yes | Yes | No | Yes |
| Officer | Yes | Yes | Yes | Yes | Yes (Eng/Steward) | Yes |
| Command Officer | Yes (bypass) | Yes | Yes | Yes | Yes | Yes |
| Admin | Yes (bypass) | Yes | Yes | Yes | Yes | Yes |
| Brigged User | Banner only* | Yes | No | No | No | No |

\* The announcements widget is gated by `@can('view-community-content')`, but the banner (`view-announcements`) is not gated by this, so brigged users still see the banner.

---

## 6. Routes

The announcements feature does not have dedicated routes. It is accessed through:

| Method | URL | Middleware | Handler | Route Name |
|--------|-----|-----------|---------|------------|
| GET | `/dashboard` | auth, verified, ensure-dob | `resources/views/dashboard.blade.php` | `dashboard` |
| GET | `/acp` | auth | `AdminControlPanelController@index` | `acp.index` |

- **Dashboard**: Renders `<livewire:dashboard.view-announcements />` (banner) and `<livewire:dashboard.announcements-widget />` (widget table).
- **ACP**: The `admin-control-panel-tabs` component renders the `<livewire:admin-manage-announcements-page />` component under the "Content > Announcements" tab, gated by `@can('viewAny', Announcement::class)`.

---

## 7. User Interface Components

### View Announcements Banner (`dashboard.view-announcements`)
**File:** `resources/views/livewire/dashboard/view-announcements.blade.php`
**Route:** Embedded on `/dashboard`

**Purpose:** Shows the newest unacknowledged announcement as a callout banner with "Read Announcement" button. Also handles lazy notification dispatch.

**Authorization:** None (visible to all authenticated users on dashboard).

**PHP class logic:**
- `mount()`: Calls `dispatchPendingNotifications()` then `loadLatest()`
- `dispatchPendingNotifications()`: Finds published announcements with null `notifications_sent_at`, atomically claims them (UPDATE WHERE NULL), dispatches `SendAnnouncementNotifications` job. Reverts claim on failure.
- `loadLatest()`: Queries for the newest published announcement not yet acknowledged by the current user.
- `acknowledgeAnnouncement()`: Authorizes (`acknowledge` policy), calls `AcknowledgeAnnouncement::run()`, closes modal, shows success toast, reloads latest.

**UI Elements:**
- `flux:callout` (fuchsia, megaphone icon) with announcement title and author name
- "Read Announcement" button triggers `flux:modal` (`view-latest-announcement`)
- Modal shows: title, author avatar + name, published date, rendered markdown content, "Acknowledge" button
- Hidden when no unacknowledged announcements exist

---

### Announcements Widget (`dashboard.announcements-widget`)
**File:** `resources/views/livewire/dashboard/announcements-widget.blade.php`
**Route:** Embedded on `/dashboard` (inside `@can('view-community-content')` block)

**Purpose:** Paginated table of all published announcements with click-to-view detail modal.

**Authorization:** Gated by `view-community-content` gate (excludes brigged users).

**PHP class logic:**
- `getAnnouncementsProperty()`: Returns `Announcement::published()->orderBy('published_at', 'desc')->paginate(5)`
- `viewAnnouncement(int $id)`: Loads announcement, opens detail modal

**UI Elements:**
- `flux:card` with heading "Community Announcements"
- `flux:table` with columns: Title (clickable link), Date
- Paginated (5 per page, pageName: `ann_widget_page`)
- `flux:modal` (`view-announcement-detail`) showing title, author avatar + name, published date, rendered content
- "No announcements yet" message when empty

---

### Admin Manage Announcements (`admin-manage-announcements-page`)
**File:** `resources/views/livewire/admin-manage-announcements-page.blade.php`
**Route:** Embedded in ACP at `/acp?category=content&tab=announcement-manager`

**Purpose:** Full CRUD management of announcements for authorized staff.

**Authorization:** `@can('viewAny', Announcement::class)` at the ACP tab level. Individual actions checked via policy (`create`, `update`, `delete`).

**PHP class logic:**
- **Sorting**: `sort(string $column)` toggles sort direction on allowed columns (`created_at`, `title`, `author_id`, `is_published`)
- **Create**: `createAnnouncement()` -- validates, creates Announcement with UTC-converted dates, records activity, closes modal, shows toast
- **Edit**: `openEditModal(int $id)` -- loads announcement data into edit form with timezone-converted dates
- **Update**: `updateAnnouncement()` -- validates, updates with UTC dates, handles schedule logic, records activity
- **Delete**: `deleteAnnouncement(int $id)` -- authorizes, records activity, deletes, shows toast
- **Timezone helpers**: `toUtc(?string)` and `toLocal(?\DateTimeInterface)` convert between user timezone and UTC
- **Preview**: Toggle between markdown editor and rendered preview for both create and edit forms

**Validation (Create):**
- `newTitle`: required, string, max:255
- `newContent`: required, string, max:10000
- `newIsPublished`: boolean
- `newPublishedAt`: nullable, date
- `newExpiredAt`: nullable, date; must be after publish date (or in the future if no publish date set)

**Publishing logic:**
- If `newIsPublished` is true OR `newPublishedAt` is set: announcement is published
- If `newPublishedAt` is set: uses that as `published_at`; otherwise uses `now()`
- Setting `newPublishedAt` to a future date creates a scheduled announcement (auto-published when time arrives via `scopePublished`)

**Status badges:**
- Expired (zinc) -- `isExpired()` true
- Scheduled (blue) -- `is_published` true and `published_at` is future
- Published (green) -- `is_published` true
- Draft (yellow) -- `is_published` false

**UI Elements:**
- Sortable `flux:table` with columns: Title, Author (with avatar), Created, Status (badge), Actions
- Paginated (10 per page)
- "Create Announcement" button (gated by `@can('create')`)
- Create flyout modal with: Title input, Content markdown editor with preview toggle, "Publish now" switch, Schedule datetime picker, Expiry datetime picker, Create button
- Edit flyout modal with same fields
- Delete button with `wire:confirm` dialog
- Timezone display in datetime labels (e.g., "Eastern Time")

---

### Notification Settings (`settings.notifications`)
**File:** `resources/views/livewire/settings/notifications.blade.php`
**Route:** `/settings/notifications`

**Relevant to announcements:** Users can configure their announcement notification preferences:
- `notify_announcements_email` (default: true)
- `notify_announcements_pushover` (default: false, requires Pushover key)
- `notify_announcements_discord` (default: false, requires linked Discord)

These preferences are stored in the `notification_preferences` JSON column on the User model under the `announcements` key, and are respected by `TicketNotificationService::sendToMany()`.

---

### Admin Control Panel Tabs (`admin-control-panel-tabs`)
**File:** `resources/views/livewire/admin-control-panel-tabs.blade.php`
**Route:** `/acp`

**Relevant to announcements:** The Announcements tab appears under the "Content" category. Visibility is gated by `@can('viewAny', Announcement::class)`. The tab name is `announcement-manager` and renders `<livewire:admin-manage-announcements-page />`.

---

## 8. Actions (Business Logic)

### AcknowledgeAnnouncement (`app/Actions/AcknowledgeAnnouncement.php`)

**Signature:** `handle(Announcement $announcement, ?User $user): void`

**Step-by-step logic:**
1. If `$user` is null, falls back to `Auth::user()`. If not authenticated, throws Exception.
2. Calls `$user->acknowledgedAnnouncements()->syncWithoutDetaching([$announcement->id])` -- idempotent, creates pivot row only if not already present.

**Called by:** `dashboard.view-announcements` component (`acknowledgeAnnouncement()` method)

---

### PostAnnouncementToDiscord (`app/Actions/PostAnnouncementToDiscord.php`)

**Signature:** `handle(Announcement $announcement): bool`

**Step-by-step logic:**
1. Reads `config('services.discord.announcements_channel_id')`. Returns false if not configured.
2. Builds message: `## {title}\n\n{content}\n\n{dashboard_url}`
3. Truncates to 2000 characters (Discord limit) if needed, preserving the URL suffix.
4. Calls `DiscordApiService::sendChannelMessage($channelId, $content)`.
5. Logs warning on failure. Returns boolean success.

**Called by:** `SendAnnouncementNotifications` job (after sending user notifications)

---

### RecordActivity (`app/Actions/RecordActivity.php`)

Called by the admin management component for announcement lifecycle events. See [Activity Log Entries](#13-activity-log-entries).

---

## 9. Notifications

### NewAnnouncementNotification (`app/Notifications/NewAnnouncementNotification.php`)

**Triggered by:** `SendAnnouncementNotifications` job via `TicketNotificationService::sendToMany()`
**Recipient:** All users with `membership_level >= Traveler` except the announcement author
**Channels:** Configurable per-user via `setChannels()` -- mail, Pushover (`PushoverChannel`), Discord DM (`DiscordChannel`)
**Queued:** Yes (`ShouldQueue`)

**Mail:**
- Subject: `"New Announcement: {title}"`
- Body: "A new announcement has been posted:" + bold title + author name + "View on Dashboard" button

**Pushover:**
- Title: "New Announcement"
- Message: `{title}`
- URL: dashboard route

**Discord DM:**
- `**New Announcement:** {title}\n**By:** {authorName}\n{dashboard_url}`

**Channel selection:** The `TicketNotificationService::send()` method reads the user's `notification_preferences['announcements']` and calls `setChannels()` to configure which channels are active for each user.

---

## 10. Background Jobs

### SendAnnouncementNotifications (`app/Jobs/SendAnnouncementNotifications.php`)

**Triggered by:** `dashboard.view-announcements` component's `dispatchPendingNotifications()` method (lazy dispatch on first dashboard load after publication)

**What it does:**
1. Re-verifies the announcement is still published (guards against unpublish between dispatch and execution)
2. Queries all users with `membership_level >= Traveler` excluding the author, chunked by 100
3. For each chunk, calls `TicketNotificationService::sendToMany($users, new NewAnnouncementNotification($announcement), 'announcements')`
4. After all notifications, calls `PostAnnouncementToDiscord::run($announcement)` to cross-post to Discord channel

**Queue/Delay:** Default queue, no explicit delay. Implements `ShouldQueue`.

---

## 11. Console Commands & Scheduled Tasks

Not applicable for this feature. Notification dispatch uses a lazy approach triggered by dashboard loads rather than a scheduled command.

---

## 12. Services

### TicketNotificationService (`app/Services/TicketNotificationService.php`)

**Relevant method:**
- `sendToMany(iterable $users, Notification $notification, string $category = 'tickets'): void` -- Iterates users and calls `send()` for each, which reads the user's `notification_preferences[$category]` to determine which channels (email, Pushover, Discord DM) to use for delivery. For the `'announcements'` category, defaults to `['email' => true, 'pushover' => false, 'discord' => false]`.

### DiscordApiService (`app/Services/DiscordApiService.php`)

**Relevant method:**
- `sendChannelMessage(string $channelId, string $content): bool` -- POSTs a message to a Discord channel via the Discord API. Returns boolean success. Logs failures.

---

## 13. Activity Log Entries

| Action String | Logged By | Subject Model | Description |
|---------------|-----------|---------------|-------------|
| `announcement_created` | `admin-manage-announcements-page` | Announcement | "Announcement created." |
| `announcement_updated` | `admin-manage-announcements-page` | Announcement | "Announcement updated." |
| `announcement_deleted` | `admin-manage-announcements-page` | Announcement | "Announcement deleted." |

---

## 14. Data Flow Diagrams

### Creating an Announcement

```
Staff user clicks "Create Announcement" on ACP Content > Announcements tab
  -> flux:modal flyout opens
  -> Fills in: title, content (markdown), publish toggle, optional schedule date, optional expiry date
  -> Submits form
    -> admin-manage-announcements-page::createAnnouncement()
      -> $this->authorize('create', Announcement::class) [AnnouncementPolicy]
      -> $this->validate([title, content, published, dates])
      -> toUtc() converts user-timezone dates to UTC
      -> Announcement::create([title, content, author_id, is_published, published_at, expired_at])
      -> RecordActivity::run($announcement, 'announcement_created', 'Announcement created.')
      -> Flux::modal('create-announcement-modal')->close()
      -> Flux::toast('Announcement created.', 'Created', variant: 'success')
```

### Viewing & Acknowledging an Announcement (Dashboard Banner)

```
User loads /dashboard
  -> dashboard.blade.php renders <livewire:dashboard.view-announcements />
  -> view-announcements::mount()
    -> dispatchPendingNotifications():
      -> Finds published announcements with null notifications_sent_at
      -> Atomically claims each (UPDATE WHERE NULL)
      -> Dispatches SendAnnouncementNotifications job for each
    -> loadLatest():
      -> Queries newest published announcement not acknowledged by user
      -> Sets $latestAnnouncement
  -> If $latestAnnouncement exists:
    -> Renders callout banner with title + author
    -> User clicks "Read Announcement"
      -> flux:modal opens with full content (rendered markdown)
    -> User clicks "Acknowledge"
      -> acknowledgeAnnouncement()
        -> $this->authorize('acknowledge', $latestAnnouncement) [AnnouncementPolicy]
        -> AcknowledgeAnnouncement::run($latestAnnouncement, auth()->user())
          -> $user->acknowledgedAnnouncements()->syncWithoutDetaching([$id])
        -> Flux::modal('view-latest-announcement')->close()
        -> Flux::toast('Announcement acknowledged.', 'Done', variant: 'success')
        -> loadLatest() [loads next unacknowledged, or null]
```

### Notification Dispatch Flow

```
First dashboard load after announcement published:
  -> view-announcements::dispatchPendingNotifications()
    -> Announcement::published()->whereNull('notifications_sent_at')
    -> For each: atomic UPDATE SET notifications_sent_at = now() WHERE id = X AND notifications_sent_at IS NULL
    -> If claimed (update count > 0):
      -> SendAnnouncementNotifications::dispatch($announcement)
        [Queued job executes later:]
        -> Re-checks announcement is still published
        -> User::where('membership_level', '>=', Traveler)->where('id', '!=', author_id)->chunk(100)
          -> TicketNotificationService::sendToMany($users, NewAnnouncementNotification, 'announcements')
            -> For each user: reads notification_preferences['announcements']
            -> Sends via configured channels (mail/Pushover/Discord DM)
        -> PostAnnouncementToDiscord::run($announcement)
          -> DiscordApiService::sendChannelMessage($channelId, $formattedMessage)
```

### Editing an Announcement

```
Staff user clicks "Edit" button on announcement row in ACP
  -> admin-manage-announcements-page::openEditModal($id)
    -> $this->authorize('update', $announcement)
    -> Loads data into edit form properties (dates converted to user timezone)
    -> flux:modal('edit-announcement-modal') opens
  -> User modifies fields, submits
    -> updateAnnouncement()
      -> $this->authorize('update', $announcement)
      -> Validate fields
      -> Determine publish state (scheduled vs immediate vs draft)
      -> $announcement->update([...])
      -> RecordActivity::run($announcement, 'announcement_updated', 'Announcement updated.')
      -> Flux::modal close + toast
```

### Deleting an Announcement

```
Staff user clicks "Delete" button on announcement row in ACP
  -> wire:confirm dialog: "Delete this announcement? This cannot be undone."
  -> If confirmed:
    -> admin-manage-announcements-page::deleteAnnouncement($id)
      -> $announcement = Announcement::findOrFail($id)
      -> $this->authorize('delete', $announcement)
      -> RecordActivity::run($announcement, 'announcement_deleted', 'Announcement deleted.')
      -> $announcement->delete()
      -> Flux::toast('Announcement deleted.', 'Deleted', variant: 'success')
```

### Viewing Announcements in Widget

```
User loads /dashboard
  -> @can('view-community-content') gate check
  -> Renders <livewire:dashboard.announcements-widget />
    -> getAnnouncementsProperty(): Announcement::published()->orderBy('published_at', 'desc')->paginate(5)
  -> User clicks announcement title
    -> viewAnnouncement($id)
      -> Loads announcement with author
      -> flux:modal('view-announcement-detail') opens with full rendered content
```

---

## 15. Configuration

| Key | Default | Purpose |
|-----|---------|---------|
| `DISCORD_ANNOUNCEMENTS_CHANNEL_ID` | null | Discord channel ID for cross-posting announcements. If not set, Discord posting is skipped. |

Config path: `config/services.php` -> `discord.announcements_channel_id`

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/Announcements/AnnouncementAdminTest.php` | 6 | Admin CRUD: create, update, delete, expiry, scheduling, unauthorized user |
| `tests/Feature/Announcements/AnnouncementBannerTest.php` | 3 | Banner: newest unacknowledged shown, hidden after acknowledge, expired not shown |
| `tests/Feature/Announcements/AnnouncementExpirationTest.php` | 5 | Scopes: published excludes expired/future, includes future expiry, isExpired() helper |
| `tests/Feature/Announcements/AnnouncementNotificationTest.php` | 5 | Lazy dispatch: fires for new published, skips already-notified/draft/expired, sets notifications_sent_at |
| `tests/Feature/Announcements/DashboardAnnouncementViewTest.php` | 4 | Dashboard display: banner visible, acknowledged hidden, widget loads, widget shows titles |
| `tests/Feature/Dashboard/DashboardAnnouncementTest.php` | 1 | Regression: dashboard loads without errors when published announcement exists (all member types) |
| `tests/Feature/Jobs/SendAnnouncementNotificationsTest.php` | 1 | Job sends to Traveler+ excluding author, skips Stowaway |
| `tests/Feature/Actions/AcknowledgeAnnouncementTest.php` | 4 | Action: acknowledges, idempotent, falls back to auth user, throws when unauthenticated |
| `tests/Feature/Livewire/AdminControlPanelTabsTest.php` | varies | ACP tab visibility (includes announcement tab access checks) |

### Test Case Inventory

**AnnouncementAdminTest:**
- `it admin can create announcement via modal`
- `it admin can update announcement via modal`
- `it admin can delete announcement`
- `it stores expired_at when creating announcement`
- `it setting published_at without toggling publish creates a scheduled announcement`
- `it unauthorized user cannot create announcement`

**AnnouncementBannerTest:**
- `it shows only the newest unacknowledged announcement on dashboard`
- `it hides banner after acknowledging the newest announcement`
- `it does not show expired announcements in banner`

**AnnouncementExpirationTest:**
- `it scopePublished excludes expired announcements`
- `it scopePublished excludes future-scheduled announcements`
- `it scopePublished includes announcements with future expiry`
- `it isExpired returns true for past expired_at`
- `it isExpired returns false when expired_at is null`

**AnnouncementNotificationTest:**
- `it dispatches notification job on dashboard load for newly published announcement`
- `it does not dispatch notification job for announcement already notified`
- `it does not dispatch notification job for draft announcements`
- `it sets notifications_sent_at when dispatching`
- `it does not dispatch for expired announcements`

**DashboardAnnouncementViewTest:**
- `it displays the newest unacknowledged announcement`
- `it does not display acknowledged announcements`
- `it loads the announcements widget component`
- `it displays announcement titles in the widget`

**DashboardAnnouncementTest:**
- `it loads the page without errors when there is a published announcement` (parameterized: all member types)

**SendAnnouncementNotificationsTest:**
- `it sends notification to all traveler+ users except the author`

**AcknowledgeAnnouncementTest:**
- `it acknowledges a published announcement for a user`
- `it is idempotent when acknowledging the same announcement twice`
- `it acknowledges an announcement for the authenticated user if no user is passed`
- `it throws an exception if the user is not authenticated`

### Coverage Gaps

- No test for `PostAnnouncementToDiscord` action (Discord cross-posting)
- No test for the 2000-character truncation logic in `PostAnnouncementToDiscord`
- No test for `renderedContent()` method (markdown rendering, XSS protection, legacy HTML handling)
- No test for timezone conversion in the admin management component (`toUtc`, `toLocal`)
- No test for the expiry date validation (must be after publish date)
- No test for the atomic claim mechanism's race condition handling (concurrent dashboard loads)
- No test for the claim rollback on job dispatch failure
- No test for notification channel selection (verifying user preferences are respected for the `announcements` category)
- No test for the announcement widget's pagination
- No test verifying brigged users cannot see the announcements widget (gate: `view-community-content`)
- No test for the `scopeExpired` query scope
- No test for the edit modal loading data correctly (`openEditModal`)

---

## 17. File Map

**Models:**
- `app/Models/Announcement.php`
- `app/Models/User.php` (relationship: `acknowledgedAnnouncements()`)

**Enums:** None

**Actions:**
- `app/Actions/AcknowledgeAnnouncement.php`
- `app/Actions/PostAnnouncementToDiscord.php`
- `app/Actions/RecordActivity.php` (called by admin component)

**Policies:**
- `app/Policies/AnnouncementPolicy.php`

**Gates:** None specific to announcements. `view-community-content` in `AuthServiceProvider` gates the widget.

**Notifications:**
- `app/Notifications/NewAnnouncementNotification.php`

**Jobs:**
- `app/Jobs/SendAnnouncementNotifications.php`

**Services:**
- `app/Services/TicketNotificationService.php` (`sendToMany()`)
- `app/Services/DiscordApiService.php` (`sendChannelMessage()`)

**Controllers:**
- `app/Http/Controllers/AdminControlPanelController.php` (renders ACP page)

**Volt Components:**
- `resources/views/livewire/dashboard/view-announcements.blade.php`
- `resources/views/livewire/dashboard/announcements-widget.blade.php`
- `resources/views/livewire/admin-manage-announcements-page.blade.php`
- `resources/views/livewire/admin-control-panel-tabs.blade.php` (renders announcement tab)
- `resources/views/livewire/settings/notifications.blade.php` (announcement notification preferences)

**Routes:**
- `dashboard` -- GET `/dashboard` (renders both announcement components)
- `acp.index` -- GET `/acp` (renders ACP with announcement management tab)

**Migrations:**
- `database/migrations/2025_08_07_163817_create_announcements_table.php`
- `database/migrations/2025_08_10_181306_create_announcement_user_table.php`
- `database/migrations/2025_08_10_033037_create_announcement_tag_pivot_table.php` (legacy, dropped)
- `database/migrations/2025_08_10_033041_create_announcement_category_pivot_table.php` (legacy, dropped)
- `database/migrations/2025_08_10_005600_create_comments_table.php` (legacy, dropped)
- `database/migrations/2026_03_06_054753_add_expired_at_and_cleanup_announcements.php`

**Factory:**
- `database/factories/AnnouncementFactory.php`

**Console Commands:** None

**Tests:**
- `tests/Feature/Announcements/AnnouncementAdminTest.php`
- `tests/Feature/Announcements/AnnouncementBannerTest.php`
- `tests/Feature/Announcements/AnnouncementExpirationTest.php`
- `tests/Feature/Announcements/AnnouncementNotificationTest.php`
- `tests/Feature/Announcements/DashboardAnnouncementViewTest.php`
- `tests/Feature/Dashboard/DashboardAnnouncementTest.php`
- `tests/Feature/Jobs/SendAnnouncementNotificationsTest.php`
- `tests/Feature/Actions/AcknowledgeAnnouncementTest.php`
- `tests/Feature/Livewire/AdminControlPanelTabsTest.php`

**Config:**
- `config/services.php` -> `discord.announcements_channel_id`
- User model `notification_preferences` JSON -> `announcements` key

---

## 18. Known Issues & Improvement Opportunities

1. **Lazy notification dispatch relies on dashboard traffic.** If no user visits the dashboard after an announcement is published, notifications are never sent. A scheduled command (e.g., every 5 minutes) checking for unclaimed published announcements would be more reliable.

2. **No ActivityLog for acknowledgments.** The `AcknowledgeAnnouncement` action does not call `RecordActivity::run()`. While acknowledgments are tracked in the pivot table, they don't appear in the activity log for auditing.

3. **Banner not gated by `view-community-content`.** The `view-announcements` banner component is rendered outside the `@can('view-community-content')` block on the dashboard, so brigged users see the announcement banner but not the widget. This may be intentional (announcements are important enough to show to brigged users) but is inconsistent.

4. **`delete` policy is more restrictive than `create`/`update`.** Officers can create and update but cannot delete unless they're in Engineer or Steward department. This asymmetry may be intentional but could confuse staff who can edit but not remove their own content.

5. **No `RecordActivity` in `AcknowledgeAnnouncement` or `PostAnnouncementToDiscord`.** These actions don't log activity, making it harder to audit who acknowledged what and whether Discord posting succeeded.

6. **`acknowledge` policy check is trivially true.** The `acknowledge` ability returns `$user != null`, which is always true for authenticated users. The `before()` hook already bypasses for admins/officers. This check provides no meaningful authorization beyond authentication.

7. **No unique constraint on `announcement_user` pivot.** The `syncWithoutDetaching` call prevents duplicates at the application level, but the database schema lacks a `unique(user_id, announcement_id)` constraint. A race condition could theoretically create duplicate rows.

8. **Markdown preview uses different rendering path than stored content.** The create/edit preview uses `Str::markdown($content, ...)` directly, while `renderedContent()` on the model has additional legacy HTML detection and stripping. Content could render differently in preview vs. actual display.

9. **No test for Discord cross-posting.** The `PostAnnouncementToDiscord` action has zero test coverage, including its 2000-character truncation logic and error handling.

10. **Notification job chunks by 100 but doesn't handle partial failures.** If `sendToMany` fails partway through a chunk, some users in that chunk may miss the notification with no retry mechanism for just those users.

11. **`scheduledForFuture()` factory state sets `is_published` to false.** This doesn't match the actual behavior where scheduled announcements have `is_published = true` with a future `published_at`. The factory state may produce incorrect test data.
