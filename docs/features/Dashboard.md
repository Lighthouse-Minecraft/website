# Dashboard -- Technical Documentation

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

The Dashboard is the primary landing page for all authenticated users in the Lighthouse Website. It serves as a centralized hub that adapts its content based on the user's membership level, staff rank, department, and brig status. The dashboard is composed of multiple Livewire Volt widget components organized into thematic sections.

The Dashboard is used by all authenticated users, but what each user sees varies significantly. New users who haven't accepted community rules see a rules acceptance prompt. Users in the Brig see a restricted view with appeal options. Regular community members see announcements, account linking options, and donation links. Staff members with Quartermaster responsibilities see user management widgets (Stowaway and Traveler promotions, discipline reports). Command staff see engagement metrics and analytics dashboards.

The Dashboard also integrates several cross-cutting features: it triggers announcement notification dispatch (lazy dispatch on first page load), displays in-progress meeting alerts for staff, links to the Ready Room (a separate staff-only page), and provides quick access to account management and donations. The "Community Updates" page is a separate public-facing view of completed meeting community minutes, gated by the `view-all-community-updates` gate.

Key terminology: "Iteration" refers to the time period between completed staff meetings, used by command dashboard widgets to track engagement metrics. "Brig" is the discipline/restriction system. "Stowaway" and "Traveler" are early membership levels requiring staff promotion.

---

## 2. Database Schema

### `announcements` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | No | auto | |
| title | string | No | | Announcement title |
| content | text | No | | Announcement body (HTML or markdown) |
| author_id | foreignId | Yes | null | FK to `users.id`, set null on delete |
| is_published | boolean | No | false | Whether the announcement is published |
| published_at | timestamp | Yes | null | Scheduled publish date |
| expired_at | timestamp | Yes | null | Expiration date |
| notifications_sent_at | timestamp | Yes | null | When notifications were dispatched |
| created_at | timestamp | No | | |
| updated_at | timestamp | No | | |

**Foreign Keys:** `author_id` -> `users.id` (onDelete: set null)
**Migration(s):**
- `database/migrations/2025_08_07_163817_create_announcements_table.php`
- `database/migrations/2026_03_06_054753_add_expired_at_and_cleanup_announcements.php`

### `announcement_user` table (pivot)

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | No | auto | |
| user_id | foreignId | No | | FK to `users.id` |
| announcement_id | foreignId | No | | FK to `announcements.id` |
| created_at | timestamp | No | | |
| updated_at | timestamp | No | | |

**Foreign Keys:** `user_id` -> `users.id` (cascade), `announcement_id` -> `announcements.id` (cascade)
**Migration(s):** `database/migrations/2025_08_10_181306_create_announcement_user_table.php`

Note: The dashboard also displays data from many other tables (users, meetings, threads, tasks, minecraft_accounts, discord_accounts, discipline_reports, prayer data, staff_positions, activity_logs) but those are documented in their respective feature docs.

---

## 3. Models & Relationships

### Announcement (`app/Models/Announcement.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `author()` | belongsTo | User | via `author_id` |
| `acknowledgers()` | belongsToMany | User | pivot table `announcement_user`, with timestamps |

**Scopes:**
- `scopePublished($query)` -- published, not future-scheduled, and not expired
- `scopeExpired($query)` -- published but `expired_at` is in the past

**Key Methods:**
- `isExpired(): bool` -- checks if `expired_at` is past
- `isAuthoredBy(User $user): bool` -- checks if announcement was authored by given user
- `authorName(): string` -- returns author name or "Unknown Author"
- `renderedContent(): string` -- renders content as HTML, converting markdown and stripping unsafe HTML

**Casts:**
- `is_published` => `boolean`
- `published_at` => `datetime`
- `expired_at` => `datetime`
- `notifications_sent_at` => `datetime`

**Eager Loads:** `$with = ['author']`

---

## 4. Enums Reference

### MeetingStatus (`app/Enums/MeetingStatus.php`)

Used by `alert-in-progress-meeting` and `community-updates/list` components.

| Case | Value | Notes |
|------|-------|-------|
| Pending | `pending` | |
| InProgress | `in_progress` | Triggers alert on dashboard |
| Completed | `completed` | Used by community updates list |
| Cancelled | `cancelled` | |

### MeetingType (`app/Enums/MeetingType.php`)

Used by `GetIterationBoundaries` action.

| Case | Value | Notes |
|------|-------|-------|
| StaffMeeting | `staff_meeting` | Defines iteration boundaries |
| BoardMeeting | `board_meeting` | Ignored for iterations |
| CommunityMeeting | `community_meeting` | Ignored for iterations |

### MembershipLevel (`app/Enums/MembershipLevel.php`)

Used by rules acceptance flow and user management widgets.

| Case | Value | Notes |
|------|-------|-------|
| Drifter | 0 | Pre-registration |
| Stowaway | 1 | After rules acceptance |
| Traveler | 2 | Promoted by staff |
| Resident | 3 | Promoted by staff |
| Citizen | 4 | |

### BrigType (`app/Enums/BrigType.php`)

Used by `in-brig-card` to determine which restricted view to show.

| Case | Notes |
|------|-------|
| ParentalPending | Account awaiting parental approval |
| ParentalDisabled | Parent restricted access |
| AgeLock | Age verification required |
| Discipline | Disciplinary restriction (default) |

### StaffRank (`app/Enums/StaffRank.php`)

Used across many dashboard gates.

| Case | Value | Notes |
|------|-------|-------|
| JrCrew | `jr_crew` | Lowest staff rank |
| CrewMember | `crew_member` | |
| Officer | `officer` | |

### StaffDepartment (`app/Enums/StaffDepartment.php`)

Used by department-based gating.

| Case | Value | Notes |
|------|-------|-------|
| Command | `command` | |
| Chaplain | `chaplain` | |
| Engineer | `engineer` | |
| Quartermaster | `quartermaster` | |
| Steward | `steward` | |

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

| Gate Name | Who Can Pass | Logic Summary |
|-----------|-------------|---------------|
| `view-community-content` | Users not in brig | `!$user->in_brig` |
| `view-all-community-updates` | Traveler+ or Admin | `$user->isAtLeastLevel(MembershipLevel::Traveler) \|\| $user->hasRole('Admin')` |
| `manage-stowaway-users` | Admin, Officer+, or QM CrewMember | Admin OR Officer+ OR (CrewMember in Quartermaster dept) |
| `manage-traveler-users` | Admin, Officer+, or QM CrewMember | Same logic as `manage-stowaway-users` |
| `view-ready-room` | Admin or JrCrew+ | `$user->hasRole('Admin') \|\| $user->isAtLeastRank(StaffRank::JrCrew)` |
| `view-ready-room-command` | Admin, Officer+, or Command JrCrew+ | Admin OR Officer+ OR (JrCrew+ in Command dept) |
| `view-ready-room-chaplain` | Admin, Officer+, or Chaplain JrCrew+ | Same pattern for Chaplain dept |
| `view-ready-room-engineer` | Admin, Officer+, or Engineer JrCrew+ | Same pattern for Engineer dept |
| `view-ready-room-quartermaster` | Admin, Officer+, or QM JrCrew+ | Same pattern for Quartermaster dept |
| `view-ready-room-steward` | Admin, Officer+, or Steward JrCrew+ | Same pattern for Steward dept |
| `view-command-dashboard` | Admin or Command dept | `$user->isAdmin() \|\| $user->isInDepartment(StaffDepartment::Command)` |
| `manage-discipline-reports` | Admin or JrCrew+ | `$user->hasRole('Admin') \|\| $user->isAtLeastRank(StaffRank::JrCrew)` |
| `link-minecraft-account` | Traveler+ not in brig, parent allows | `isAtLeastLevel(Traveler) && !in_brig && parent_allows_minecraft` |
| `link-discord` | Stowaway+ not in brig, parent allows | `isAtLeastLevel(Stowaway) && !in_brig && parent_allows_discord` |

### Policies

#### MeetingPolicy (`app/Policies/MeetingPolicy.php`)

**`before()` hook:** Admins and Command Officers always return true (bypass all checks).

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `viewAny` | CrewMember+ or Meeting Secretary | `isAtLeastRank(CrewMember) \|\| hasRole('Meeting Secretary')` |
| `view` | CrewMember+ or Meeting Secretary | Same as viewAny |
| `attend` | CrewMember+ or Meeting Secretary | Same as viewAny |
| `viewAnyPrivate` | Officer+ or Meeting Secretary | `isAtLeastRank(Officer) \|\| hasRole('Meeting Secretary')` |
| `viewAnyPublic` | Resident+ or Meeting Secretary | `isAtLeastLevel(Resident) \|\| hasRole('Meeting Secretary')` |
| `create` | Officer+ or Meeting Secretary | |
| `update` | Officer+ or Meeting Secretary | |
| `delete` | Nobody | Always returns false |

#### AnnouncementPolicy

Referenced in the `view-announcements` component via `$this->authorize('acknowledge', $latestAnnouncement)`.

### Permissions Matrix

| User Type | View Dashboard | See Community Content | See Announcements | Accept Rules | Link Accounts | View Ready Room | View Command Dashboard | Manage Stowaways | Manage Travelers | Manage Discipline Reports | Submit Brig Appeal |
|-----------|---------------|----------------------|-------------------|-------------|---------------|----------------|----------------------|-----------------|-----------------|--------------------------|-------------------|
| Guest | No (redirect) | No | No | No | No | No | No | No | No | No | No |
| Drifter (no rules) | Yes | No | No | Yes | No | No | No | No | No | No | No |
| Stowaway | Yes | Yes | Yes | N/A | Yes | No | No | No | No | No | No |
| Traveler+ | Yes | Yes | Yes | N/A | Yes | No | No | No | No | No | No |
| Brigged User | Yes | No | Yes | N/A | No | Depends on rank | Depends | Depends | Depends | Depends | Yes |
| JrCrew | Yes | Yes | Yes | N/A | Yes | Yes (own dept) | No | No | No | Yes | No |
| CrewMember | Yes | Yes | Yes | N/A | Yes | Yes (own dept) | No | If QM dept | If QM dept | Yes | No |
| Officer | Yes | Yes | Yes | N/A | Yes | Yes (all depts) | No | Yes | Yes | Yes | No |
| Command Dept | Yes | Yes | Yes | N/A | Yes | Yes (all if Officer) | Yes | Depends | Depends | Depends | No |
| Admin | Yes | Yes | Yes | N/A | Yes | Yes | Yes | Yes | Yes | Yes | No |

---

## 6. Routes

| Method | URL | Middleware | Handler | Route Name |
|--------|-----|-----------|---------|------------|
| GET | `/dashboard` | `auth`, `verified` | Blade view: `dashboard` | `dashboard` |
| GET | `/ready-room` | `auth` | `DashboardController@readyRoom` | `ready-room.index` |
| GET | `/community-updates` | (varies) | Volt: `community-updates.list` | (check routes) |

---

## 7. User Interface Components

### Alert In-Progress Meeting
**File:** `resources/views/livewire/dashboard/alert-in-progress-meeting.blade.php`
**Route:** `/dashboard` (embedded widget)

**Purpose:** Shows a callout banner when a meeting is currently in progress.

**Authorization:** `@can('attend', $meeting)` via MeetingPolicy

**UI Elements:**
- Flux callout (amber) showing meeting title with "Join Meeting" link to `meeting.edit` route
- Only visible when a meeting has `status = 'in_progress'`

### View Announcements (Unacknowledged Banner)
**File:** `resources/views/livewire/dashboard/view-announcements.blade.php`
**Route:** `/dashboard` (embedded widget)

**Purpose:** Displays a banner for the latest unacknowledged published announcement and handles lazy notification dispatch.

**Authorization:** `$this->authorize('acknowledge', $latestAnnouncement)` via AnnouncementPolicy

**User Actions Available:**
- Click "Read Announcement" -> opens modal with full announcement content
- Click "Acknowledge" -> calls `AcknowledgeAnnouncement::run()` -> closes modal, shows success toast, loads next unacknowledged announcement

**UI Elements:**
- Flux callout (fuchsia, megaphone icon) with announcement title and author
- Modal with full announcement content (rendered markdown/HTML), author avatar, publish date
- Acknowledge button

**Special Behavior:** On mount, calls `dispatchPendingNotifications()` which atomically claims unpublished announcements and dispatches `SendAnnouncementNotifications` job.

### Announcements Widget
**File:** `resources/views/livewire/dashboard/announcements-widget.blade.php`
**Route:** `/dashboard` (embedded in Community section)

**Purpose:** Paginated table of all published announcements with view modal.

**Authorization:** Visible within `@can('view-community-content')` gate

**User Actions Available:**
- Click announcement title -> opens detail modal with full content

**UI Elements:**
- Flux card with "Community Announcements" heading
- Paginated table (5 per page) with title and date columns
- Detail modal with announcement content

### View Rules
**File:** `resources/views/livewire/dashboard/view-rules.blade.php`
**Route:** `/dashboard` (embedded widget)

**Purpose:** Displays community rules in a flyout modal. If user hasn't accepted rules, shows acceptance button.

**User Actions Available:**
- Click "Read & Accept Rules" / "View Rules" -> opens flyout modal
- Click "I Have Read the Rules and Agree to Follow Them" -> calls `acceptRules()`:
  - Updates `rules_accepted_at` on user
  - Calls `RecordActivity::run()` with `'rules_accepted'`
  - Calls `PromoteUser::run()` to Stowaway level
  - Clears cache, closes modal, shows toast, redirects to dashboard

**UI Elements:**
- Flux button trigger (primary if unaccepted, xs if already accepted)
- Flyout modal with full rules content
- Accept button (only shown if rules not yet accepted or user is Drifter level)

### In-Brig Card
**File:** `resources/views/livewire/dashboard/in-brig-card.blade.php`
**Route:** `/dashboard` (shown when `view-community-content` gate fails)

**Purpose:** Shows restriction status and appeal options for brigged users. Different UI for each brig type.

**User Actions Available:**
- Submit Appeal / Contact Staff -> opens appeal modal -> calls `submitAppeal()`:
  - Validates appeal message (min 20 chars)
  - Creates a Quartermaster ticket (Thread + Message) in a DB transaction with row-level locking
  - Records activity: `'ticket_opened'`
  - Sets 7-day cooldown: `next_appeal_available_at`
  - Notifies QM staff via `TicketNotificationService::sendToMany()`
  - Shows success toast

**UI Elements:**
- Four card variants: ParentalPending (amber), ParentalDisabled (orange), AgeLock (red), Discipline (red)
- Each shows relevant icon, heading, badge, and contextual message
- Appeal/Contact modal with textarea (min 20 chars)
- Cooldown display showing next available appeal date

### Account Linking Card
**File:** `resources/views/dashboard.blade.php` (inline, lines 27-76)

**Purpose:** Shows Minecraft and Discord account linking status with "Manage" buttons.

**Authorization:** `@canany(['link-minecraft-account', 'link-discord'])`

**UI Elements:**
- Flux card with Minecraft and Discord sections
- Each shows linked account count or prompt to link
- "Manage" buttons linking to settings pages

### Donations Card
**File:** `resources/views/dashboard.blade.php` (inline, lines 78-86)

**Purpose:** Links to donation page and Stripe customer portal.

**UI Elements:**
- "Support Lighthouse" button linking to `donate` route
- "Manage Subscription" button linking to Stripe customer portal URL from config

### Stowaway Users Widget
**File:** `resources/views/livewire/dashboard/stowaway-users-widget.blade.php`
**Route:** `/dashboard` (Quartermaster section)

**Purpose:** Lists Stowaway-level users (not in brig) for staff management.

**Authorization:** `manage-stowaway-users` gate

**User Actions Available:**
- View user details -> modal with user info
- Promote to Traveler -> calls `PromoteUser::run()` -> toast
- Put in Brig -> calls `PutUserInBrig::run()` -> toast

**UI Elements:**
- Paginated user table
- View/promote/brig action buttons
- User detail modal
- Brig confirmation modal with reason textarea

### Traveler Users Widget
**File:** `resources/views/livewire/dashboard/traveler-users-widget.blade.php`
**Route:** `/dashboard` (Quartermaster section)

**Purpose:** Lists Traveler-level users sorted by `promoted_at` (oldest first) for promotion to Resident.

**Authorization:** `manage-traveler-users` gate

**User Actions Available:**
- View user details -> modal
- Promote to Resident -> calls `PromoteUser::run()` -> toast

**UI Elements:**
- Paginated user table with joined/promoted dates
- View/promote action buttons
- User detail modal

### Discipline Reports Widget
**File:** `resources/views/livewire/dashboard/discipline-reports-widget.blade.php`
**Route:** `/dashboard` (Quartermaster section)

**Purpose:** Shows recent discipline reports (last 7 days), top risk users (cached 5 min), and pending draft count.

**Authorization:** `manage-discipline-reports` gate

**User Actions Available:**
- View report -> navigates to report detail page
- Publish report -> calls `PublishDisciplineReport::run()` -> toast

**UI Elements:**
- Recent reports table
- Top risk users list
- Pending draft count badge
- Publish confirmation

### Command Community Engagement
**File:** `resources/views/livewire/dashboard/command-community-engagement.blade.php`
**Route:** `/dashboard` (Command Staff section)

**Purpose:** Shows community growth metrics for the current iteration compared to previous iteration.

**Authorization:** `view-command-dashboard` gate

**User Actions Available:**
- Click on metric cards -> opens 3-month timeline chart modal

**UI Elements:**
- Metric cards: New Users, New MC Accounts, New Discord Accounts, Active Users, Pending MC Verification
- Each card shows current count, previous count, and trend indicator
- Detail modal with 3-month timeline charts

### Command Department Engagement
**File:** `resources/views/livewire/dashboard/command-department-engagement.blade.php`
**Route:** `/dashboard` (Command Staff section)

**Purpose:** Per-department ticket/todo metrics, discipline report counts, staff report completion %, meeting attendance %.

**Authorization:** `view-command-dashboard` gate

**User Actions Available:**
- Click discipline report counts -> opens timeline modal

**UI Elements:**
- Department table with tickets opened/remaining, todos created/completed
- Discipline reports: published count, draft count with attention badge
- Staff report completion % and meeting attendance % (from previous meeting)
- Detail modal for discipline timeline

### Command Staff Engagement
**File:** `resources/views/livewire/dashboard/command-staff-engagement.blade.php`
**Route:** `/dashboard` (Command Staff section)

**Purpose:** Paginated table of individual staff member engagement metrics.

**Authorization:** `view-command-dashboard` gate

**User Actions Available:**
- Click staff name -> opens detail modal with 3-month history per person

**UI Elements:**
- Paginated staff table showing: todos worked/open, tickets worked/open, reports submitted (3mo), meetings attended/missed (3mo)
- Detail modal with monthly breakdown

### Ready Room
**File:** `resources/views/livewire/dashboard/ready-room.blade.php`
**Route:** `/ready-room` (route name: `ready-room.index`)

**Purpose:** Staff-only area with tab-based layout for department tasks and meetings.

**Authorization:** `view-ready-room` gate (page access), per-department gates for tabs

**UI Elements:**
- Tab list: "My Board" + one tab per department (gated)
- My Board tab: upcoming meetings + personal tasks
- Department tabs: department meetings, tasks, and meeting notes

### Ready Room Department
**File:** `resources/views/livewire/dashboard/ready-room-department.blade.php`

**Purpose:** Content for each department tab in the Ready Room.

**UI Elements:**
- Upcoming meetings for the department
- Department task list
- Meeting notes display

### Ready Room My Tasks
**File:** `resources/views/livewire/dashboard/ready-room-my-tasks.blade.php`

**Purpose:** Shows tasks assigned to the current user, grouped by section_key.

### Ready Room Upcoming Meetings
**File:** `resources/views/livewire/dashboard/ready-room-upcoming-meetings.blade.php`

**Purpose:** Shows up to 3 pending meetings with check-in button status.

### Community Updates List
**File:** `resources/views/livewire/community-updates/list.blade.php`

**Purpose:** Public-facing page showing completed meeting community minutes.

**Authorization:** `view-all-community-updates` gate controls whether user sees all or just latest

**UI Elements:**
- Flux accordion with meeting titles and dates
- First item expanded by default
- Paginated for authorized users, limited to 1 for others
- Prompt to join community for full archive access

---

## 8. Actions (Business Logic)

### AcknowledgeAnnouncement (`app/Actions/AcknowledgeAnnouncement.php`)

**Signature:** `handle(Announcement $announcement, ?User $user): void`

**Step-by-step logic:**
1. If no user passed, falls back to authenticated user (throws exception if not authenticated)
2. Syncs without detaching the announcement ID to the user's `acknowledgedAnnouncements` relationship

**Called by:** `dashboard.view-announcements` component

### GetIterationBoundaries (`app/Actions/GetIterationBoundaries.php`)

**Signature:** `handle(): array`

**Step-by-step logic:**
1. Checks cache for `iteration_boundaries` key (24h TTL)
2. Finds the most recent completed staff meeting (by `end_time` desc)
3. If no meeting found, returns fallback: 30-day window to now, no previous iteration
4. Current iteration: from last meeting's `end_time` to now
5. If a second-to-last meeting exists, computes previous iteration between those two meetings
6. Builds `iterations_3mo` array: all completed staff meetings within 3 months, with start/end boundaries
7. Caches and returns result array with keys: `current_start`, `current_end`, `current_meeting`, `previous_start`, `previous_end`, `previous_meeting`, `has_previous`, `iterations_3mo`

**Called by:** `command-community-engagement`, `command-department-engagement`, `command-staff-engagement` components

### PostAnnouncementToDiscord (`app/Actions/PostAnnouncementToDiscord.php`)

**Signature:** `handle(Announcement $announcement): bool`

**Step-by-step logic:**
1. Gets Discord announcements channel ID from config
2. If no channel ID configured, returns false
3. Formats announcement content from HTML to Discord markdown (handles headings, bold, italic, underline, strikethrough, links)
4. Constructs message: `## title\n\nbody\n\nurl`
5. Truncates to 2000 char Discord limit if needed
6. Sends via `DiscordApiService::sendChannelMessage()`
7. Returns success/failure boolean

**Called by:** `SendAnnouncementNotifications` job

### PromoteUser (`app/Actions/PromoteUser.php`)

**Called by:** `view-rules` (to Stowaway), `stowaway-users-widget` (to Traveler), `traveler-users-widget` (to Resident)

### PutUserInBrig (`app/Actions/PutUserInBrig.php`)

**Called by:** `stowaway-users-widget`

### PublishDisciplineReport (`app/Actions/PublishDisciplineReport.php`)

**Called by:** `discipline-reports-widget`

### RecordActivity (`app/Actions/RecordActivity.php`)

**Called by:** `view-rules` (`rules_accepted`), `in-brig-card` (`ticket_opened`)

---

## 9. Notifications

### NewAnnouncementNotification (`app/Notifications/NewAnnouncementNotification.php`)

**Triggered by:** `SendAnnouncementNotifications` job (see section 10)
**Recipient:** All users at Traveler level or above, excluding the author
**Channels:** mail, Pushover (via `PushoverChannel`), Discord (via `DiscordChannel`) -- determined by `TicketNotificationService::sendToMany()` which sets channels per user preferences
**Mail subject:** `"New Announcement: {title}"`
**Content summary:** Announcement title, author name, link to dashboard
**Queued:** Yes (implements `ShouldQueue`)

### NewTicketNotification

**Triggered by:** `in-brig-card` `submitAppeal()` method (for brig appeals/staff contacts)
**Recipient:** All Quartermaster staff
**Channels:** Via `TicketNotificationService::sendToMany()`
**Content summary:** New ticket notification for the appeal/contact thread

---

## 10. Background Jobs

### SendAnnouncementNotifications (`app/Jobs/SendAnnouncementNotifications.php`)

**Triggered by:** `view-announcements` component's `dispatchPendingNotifications()` on mount
**What it does:**
1. Re-verifies announcement is still published at handle time
2. Queries all users at Traveler level or above (excluding author)
3. Chunks users in batches of 100
4. Sends `NewAnnouncementNotification` via `TicketNotificationService::sendToMany()` with category `'announcements'`
5. Posts announcement to Discord via `PostAnnouncementToDiscord::run()`
**Queue/Delay:** Default queue, no delay. Implements `ShouldQueue`.

---

## 11. Console Commands & Scheduled Tasks

Not applicable for this feature. The dashboard itself has no scheduled commands. (Meeting-related scheduling is part of the Meetings feature.)

---

## 12. Services

### TicketNotificationService (`app/Services/TicketNotificationService.php`)

**Purpose:** Wraps Laravel notifications with smart delivery (checks user notification preferences, supports mail + Pushover + Discord channels).

**Key methods used by Dashboard:**
- `sendToMany($users, $notification, $category)` -- sends notification to multiple users respecting their preferences

### DiscordApiService (`app/Services/DiscordApiService.php`)

**Purpose:** Sends messages to Discord channels via the Discord API.

**Key methods used by Dashboard:**
- `sendChannelMessage($channelId, $content): bool` -- sends a message to a Discord channel

---

## 13. Activity Log Entries

| Action String | Logged By | Subject Model | Description |
|---------------|-----------|---------------|-------------|
| `rules_accepted` | `view-rules` component | User | "User accepted community rules and was promoted to Stowaway" |
| `ticket_opened` | `in-brig-card` component | Thread | "Brig appeal submitted: {subject}" or "Staff contact submitted: {subject}" |
| `user_promoted` | `PromoteUser` action (via widgets) | User | Logged by PromoteUser action |

---

## 14. Data Flow Diagrams

### Viewing the Dashboard (New User - Rules Not Accepted)

```
User visits /dashboard (middleware: auth, verified)
  -> Blade renders dashboard.blade.php
    -> Checks auth()->user()->rules_accepted_at
    -> rules_accepted_at is null:
      -> Shows "Welcome to Lighthouse!" message
      -> Renders <livewire:dashboard.view-rules /> with "Read & Accept Rules" button
      -> User clicks button -> flyout modal opens with rules
      -> User clicks "I Have Read the Rules and Agree to Follow Them"
        -> view-rules::acceptRules()
          -> User updated: rules_accepted_at = now()
          -> RecordActivity::run(user, 'rules_accepted', '...')
          -> PromoteUser::run(user, MembershipLevel::Stowaway)
          -> Cache::forget('user:{id}:is_stowaway')
          -> Flux::modal('view-rules-modal')->close()
          -> Flux::toast('Rules accepted successfully!', variant: 'success')
          -> redirect()->route('dashboard')
```

### Viewing the Dashboard (Regular Community Member)

```
User visits /dashboard (middleware: auth, verified)
  -> <livewire:dashboard.alert-in-progress-meeting /> renders (if meeting in progress)
  -> <livewire:dashboard.view-announcements /> renders
    -> mount(): dispatchPendingNotifications() (lazy dispatch)
    -> mount(): loadLatest() finds unacknowledged announcement
    -> Shows callout banner if unacknowledged announcement exists
  -> Community section (rules accepted):
    -> @can('view-community-content') passes (not in brig):
      -> <livewire:dashboard.announcements-widget /> (paginated table)
      -> Account Linking card (inline, shows MC/Discord status)
      -> Donations card (inline, links to donate + Stripe portal)
  -> Spiritual Discipleship section:
    -> @can('viewPrayer', PrayerCountry::class):
      -> Prayer widget + Prayer graph
  -> Quartermaster section (Officer+ or QM dept or Admin only)
  -> Command Staff section (@can('view-command-dashboard') only)
```

### Acknowledging an Announcement

```
User sees callout banner on dashboard
  -> Clicks "Read Announcement"
    -> Modal opens with full announcement content
  -> Clicks "Acknowledge"
    -> view-announcements::acknowledgeAnnouncement()
      -> $this->authorize('acknowledge', $latestAnnouncement)
      -> AcknowledgeAnnouncement::run($announcement, $user)
        -> $user->acknowledgedAnnouncements()->syncWithoutDetaching([$announcement->id])
      -> Flux::modal('view-latest-announcement')->close()
      -> Flux::toast('Announcement acknowledged.', variant: 'success')
      -> loadLatest() -- loads next unacknowledged announcement (if any)
```

### Announcement Notification Dispatch (Lazy)

```
First user loads dashboard after announcement is published
  -> view-announcements::mount()
    -> dispatchPendingNotifications()
      -> Queries Announcement::published()->whereNull('notifications_sent_at')
      -> For each pending announcement:
        -> Atomic claim: UPDATE announcements SET notifications_sent_at = now() WHERE id = ? AND notifications_sent_at IS NULL
        -> If claimed (affected rows > 0):
          -> SendAnnouncementNotifications::dispatch($announcement)
            -> Job handle():
              -> Re-verifies announcement is still published
              -> User::where(membership_level >= Traveler)->chunk(100, ...)
                -> TicketNotificationService::sendToMany($users, NewAnnouncementNotification, 'announcements')
              -> PostAnnouncementToDiscord::run($announcement)
                -> DiscordApiService::sendChannelMessage(...)
```

### Submitting a Brig Appeal

```
Brigged user on dashboard sees in-brig-card
  -> Clicks "Submit Appeal" / "Contact Staff"
    -> Modal opens with textarea
  -> Fills in message (min 20 chars) and clicks "Submit Appeal"
    -> in-brig-card::submitAppeal()
      -> Checks $user->canAppeal() (cooldown check)
      -> Validates appealMessage (required, string, min:20)
      -> DB::transaction:
        -> Row-locks user to prevent duplicate appeals
        -> Creates Thread (type: Ticket, subtype: AdminAction, dept: Quartermaster)
        -> Creates Message with appeal text
        -> RecordActivity::handle($thread, 'ticket_opened', '...')
        -> Sets user.next_appeal_available_at = now() + 7 days
      -> Notifies QM staff: TicketNotificationService::sendToMany($quartermasters, NewTicketNotification)
      -> Clears appealMessage
      -> Flux::modal('brig-appeal-modal')->close()
      -> Flux::toast('Your appeal has been submitted...', variant: 'success')
```

### Promoting a Stowaway to Traveler

```
Admin/Officer/QM staff on dashboard sees Stowaway Users Widget
  -> Clicks user name -> viewUser() -> modal opens with user details
  -> Clicks "Promote" button
    -> stowaway-users-widget::promoteToTraveler()
      -> Gate::authorize('manage-stowaway-users')
      -> PromoteUser::run($selectedUser, MembershipLevel::Traveler)
        -> Updates user.membership_level
        -> RecordActivity::run(user, 'user_promoted', '...')
      -> Flux::toast('...promoted to Traveler', variant: 'success')
```

### Viewing the Ready Room

```
Staff member clicks "Staff Ready Room" link in sidebar
  -> GET /ready-room (middleware: auth)
    -> DashboardController::readyRoom()
      -> Gate::authorize('view-ready-room')
      -> Returns view 'dashboard.ready-room'
    -> <livewire:dashboard.ready-room /> renders
      -> "My Board" tab: upcoming meetings + my tasks
      -> Department tabs (conditionally shown per gate):
        -> @can('view-ready-room-{dept}')
        -> Each tab: <livewire:dashboard.ready-room-department :department="..." />
```

---

## 15. Configuration

| Key | Default | Purpose |
|-----|---------|---------|
| `services.discord.announcements_channel_id` | `env('DISCORD_ANNOUNCEMENTS_CHANNEL_ID')` | Discord channel for posting announcements |
| `lighthouse.stripe.customer_portal_url` | `env('STRIPE_CUSTOMER_PORTAL_URL', '')` | Stripe customer portal URL for donation management |

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/DashboardTest.php` | 14 tests | Dashboard access, stowaway widget visibility, promotion, activity recording, edge cases |
| `tests/Feature/Livewire/Dashboard/StowawayUsersWidgetTest.php` | 12 tests | Widget rendering, user display, promotion, authorization, modal |
| `tests/Feature/Livewire/Dashboard/TravelerUsersWidgetTest.php` | 12 tests | Widget rendering, user display, sorting, promotion, authorization |
| `tests/Feature/Livewire/Dashboard/CommandStaffEngagementTest.php` | 12 tests | Rendering, staff table, todo/ticket/report/meeting counts, detail modal, visibility |
| `tests/Feature/Livewire/Dashboard/CommandCommunityEngagementTest.php` | 14 tests | Rendering, new user/MC/Discord counts, active users, detail modal, visibility by dept |
| `tests/Feature/Livewire/Dashboard/CommandDepartmentEngagementTest.php` | 13 tests | Rendering, ticket/todo counts, discipline reports, staff report %, attendance %, visibility |
| `tests/Feature/Dashboard/DashboardAnnouncementTest.php` | 1 test | Dashboard loads without error when published announcement exists |
| `tests/Feature/Dashboard/DashboardMeetingTest.php` | 1 test | In-progress meetings show on dashboard for officers/crew |
| `tests/Feature/Actions/GetIterationBoundariesTest.php` | 7 tests | Fallback boundaries, current/previous iteration computation, 3mo array, meeting type filtering, caching |
| `tests/Feature/DepartmentReadyRoom/ReadyRoomPageTest.php` | 10 tests | Page access by rank, department tab visibility, meeting notes, task display |
| `tests/Feature/DepartmentReadyRoom/UpcomingMeetingsTest.php` | 2 tests | Widget display, meeting details shown |

### Test Case Inventory

**DashboardTest.php:**
- `it('redirects guests to the login page')`
- `it('allows authenticated users to visit the dashboard')`
- `it('shows the stowaway widget only for admin users')`
- `it('does not show the stowaway widget for non-admin users')`
- `it('displays stowaway users in the widget for admins')`
- `it('shows empty state when no stowaway users exist')`
- `it('can view user details through the modal')`
- `it('allows admins to promote stowaway users to traveler')`
- `it('prevents non-admin users from promoting users')`
- `it('records activity when promoting a user')`
- `it('handles edge cases gracefully')`
- `it('closes modal properly')`
- `it('uses the PromoteUser action correctly')`
- `it('respects max promotion level in PromoteUser action')`

**StowawayUsersWidgetTest.php:**
- `it('can render')`
- `it('displays stowaway users in the table')`
- `it('shows empty state when no stowaway users exist')`
- `it('can open user details modal')`
- `it('can promote stowaway to traveler')`
- `it('prevents non-admin users from promoting')`
- `it('can close the modal')`
- `it('can be seen by officers')`
- `it('can be seen by crew members in the quartermaster department')`
- `it('cannot be viewed by non-officers')`
- `it('cannot be viewed by JrCrew')`
- `it('allows officers to promote stowaway users')`

**TravelerUsersWidgetTest.php:**
- `it('can render')`
- `it('displays traveler users in the table')`
- `it('shows empty state when no traveler users exist')`
- `it('displays users sorted by promoted_at oldest first')`
- `it('can open user details modal')`
- `it('displays joined and promoted_at dates')`
- `it('handles null promoted_at gracefully')`
- `it('can promote traveler to resident')`
- `it('can close the modal')`
- `it('is visible to admins')`
- `it('is visible to officers')`
- `it('is not visible to regular users')`

**CommandStaffEngagementTest.php:**
- `it('can render for authorized users')`
- `it('shows paginated table of staff members')`
- `it('does not show non-staff users in the table')`
- `it('shows current iteration todo assigned and completed counts per staff')`
- `it('shows reports submitted count over last 3 months')`
- `it('shows meetings attended count over last 3 months')`
- `it('shows meetings missed only for crew member and above, not jr crew')`
- `it('opens staff detail modal with 3-month history')`
- `it('is visible to command department staff on the dashboard')`
- `it('is visible to admins on the dashboard')`
- `it('is not visible to non-command officers')`
- `it('is not visible to regular members')`

**CommandCommunityEngagementTest.php:**
- `it('can render for authorized users')`
- `it('counts new users created in the current iteration')`
- `it('counts new minecraft accounts in the current iteration')`
- `it('counts pending minecraft verification accounts')`
- `it('counts new discord accounts in the current iteration')`
- `it('shows active users metric')`
- `it('opens detail modal')`
- `it('is visible to command department staff on the dashboard')`
- `it('is visible to admins on the dashboard')`
- `it('is visible to command department jr crew')`
- `it('is not visible to non-command officers')`
- `it('is not visible to non-command crew members')`
- `it('is not visible to regular members')`

**CommandDepartmentEngagementTest.php:**
- `it('can render for authorized users')`
- `it('counts tickets opened in current iteration by department')`
- `it('counts tickets remaining open')`
- `it('counts todos created and completed by department')`
- `it('shows discipline reports published count')`
- `it('shows draft reports count with attention badge')`
- `it('shows staff report completion percentage for previous meeting')`
- `it('shows meeting attendance percentage excluding jr crew')`
- `it('shows dashes when no previous meeting exists')`
- `it('opens detail modal for discipline timeline')`
- `it('is visible to command department staff on the dashboard')`
- `it('is visible to admins on the dashboard')`
- `it('is not visible to non-command staff')`
- `it('is not visible to regular members')`

**DashboardAnnouncementTest.php:**
- `it('loads the page without errors when there is a published announcement')`

**DashboardMeetingTest.php:**
- `it('meetings in progress show up in the dashboard for officers and crew')`

**GetIterationBoundariesTest.php:**
- `it('returns fallback boundaries when no completed staff meetings exist')`
- `it('computes current iteration from last completed staff meeting to now')`
- `it('computes previous iteration between second-to-last and last meetings')`
- `it('builds iterations_3mo array for completed meetings within 3 months')`
- `it('only considers staff meetings, not board or community meetings')`
- `it('only considers completed meetings, not pending or cancelled')`
- `it('caches results')`

**ReadyRoomPageTest.php:**
- `it('shows the Ready Room link in the sidebar for all ranks')`
- `it('does not show the Ready Room link in the sidebar for members')`
- `it('loads the Ready Room page')`
- `it('is accessible by all ranks')`
- `it('is not accessible by members')`
- `it('is not accessible to guests')`
- `it('displays the list of departments as a tab list')`
- `it('allows Officers to view all departments')`
- `it('allows JrCrew and Crew Members to view their department')`
- `it('displays the recent meeting notes')`
- `it('displays the current task list')`

**UpcomingMeetingsTest.php:**
- `it('displays the upcoming meetings widget')`
- `it('shows the next meeting in the widget')`

### Coverage Gaps

- **Announcement acknowledgment flow** -- Only 1 test for announcements on dashboard (just page load). No tests for acknowledging announcements, the modal flow, or dispatch of pending notifications.
- **View-rules component** -- No dedicated test for rules acceptance flow (accepting rules, promotion to Stowaway, redirect).
- **In-brig-card** -- No tests for brig appeal submission flow, different brig type card rendering, or cooldown enforcement from the dashboard.
- **Discipline reports widget** -- No dedicated widget tests (only covered indirectly via command dashboard tests).
- **Community Updates page** -- No test coverage found for `community-updates/list.blade.php`.
- **Ready Room "My Tasks" tab** -- No tests for the `ready-room-my-tasks` component specifically.
- **Ready Room department tab content** -- Limited testing of the `ready-room-department` component beyond basic rendering.
- **Account linking card** -- No tests for the inline account linking display logic.
- **Donations card** -- No tests for the inline donations card rendering.

---

## 17. File Map

**Models:**
- `app/Models/Announcement.php`
- `app/Models/Meeting.php` (referenced, documented in Meetings feature)
- `app/Models/User.php` (referenced, documented in Auth feature)
- `app/Models/Thread.php` (referenced by in-brig-card)
- `app/Models/Message.php` (referenced by in-brig-card)
- `app/Models/Task.php` (referenced by ready-room)

**Enums:**
- `app/Enums/MeetingStatus.php`
- `app/Enums/MeetingType.php`
- `app/Enums/MembershipLevel.php`
- `app/Enums/BrigType.php`
- `app/Enums/StaffRank.php`
- `app/Enums/StaffDepartment.php`
- `app/Enums/MessageKind.php`
- `app/Enums/ThreadStatus.php`
- `app/Enums/ThreadSubtype.php`
- `app/Enums/ThreadType.php`

**Actions:**
- `app/Actions/AcknowledgeAnnouncement.php`
- `app/Actions/GetIterationBoundaries.php`
- `app/Actions/PostAnnouncementToDiscord.php`
- `app/Actions/PromoteUser.php`
- `app/Actions/PutUserInBrig.php`
- `app/Actions/PublishDisciplineReport.php`
- `app/Actions/RecordActivity.php`

**Policies:**
- `app/Policies/MeetingPolicy.php`

**Gates:** `app/Providers/AuthServiceProvider.php` -- gates: `view-community-content`, `view-all-community-updates`, `manage-stowaway-users`, `manage-traveler-users`, `view-ready-room`, `view-ready-room-command`, `view-ready-room-chaplain`, `view-ready-room-engineer`, `view-ready-room-quartermaster`, `view-ready-room-steward`, `view-command-dashboard`, `manage-discipline-reports`, `link-minecraft-account`, `link-discord`

**Notifications:**
- `app/Notifications/NewAnnouncementNotification.php`
- `app/Notifications/NewTicketNotification.php`

**Jobs:**
- `app/Jobs/SendAnnouncementNotifications.php`

**Services:**
- `app/Services/TicketNotificationService.php`
- `app/Services/DiscordApiService.php`

**Controllers:**
- `app/Http/Controllers/DashboardController.php`

**Volt Components:**
- `resources/views/livewire/dashboard/alert-in-progress-meeting.blade.php`
- `resources/views/livewire/dashboard/view-announcements.blade.php`
- `resources/views/livewire/dashboard/announcements-widget.blade.php`
- `resources/views/livewire/dashboard/view-rules.blade.php`
- `resources/views/livewire/dashboard/in-brig-card.blade.php`
- `resources/views/livewire/dashboard/stowaway-users-widget.blade.php`
- `resources/views/livewire/dashboard/traveler-users-widget.blade.php`
- `resources/views/livewire/dashboard/discipline-reports-widget.blade.php`
- `resources/views/livewire/dashboard/command-community-engagement.blade.php`
- `resources/views/livewire/dashboard/command-department-engagement.blade.php`
- `resources/views/livewire/dashboard/command-staff-engagement.blade.php`
- `resources/views/livewire/dashboard/ready-room.blade.php`
- `resources/views/livewire/dashboard/ready-room-department.blade.php`
- `resources/views/livewire/dashboard/ready-room-my-tasks.blade.php`
- `resources/views/livewire/dashboard/ready-room-upcoming-meetings.blade.php`
- `resources/views/livewire/community-updates/list.blade.php`

**Views:**
- `resources/views/dashboard.blade.php`
- `resources/views/dashboard/ready-room.blade.php`

**Routes:**
- `dashboard` -- `GET /dashboard`
- `ready-room.index` -- `GET /ready-room`

**Migrations:**
- `database/migrations/2025_08_07_163817_create_announcements_table.php`
- `database/migrations/2025_08_10_181306_create_announcement_user_table.php`
- `database/migrations/2026_03_06_054753_add_expired_at_and_cleanup_announcements.php`

**Console Commands:** None specific to Dashboard

**Tests:**
- `tests/Feature/DashboardTest.php`
- `tests/Feature/Livewire/Dashboard/StowawayUsersWidgetTest.php`
- `tests/Feature/Livewire/Dashboard/TravelerUsersWidgetTest.php`
- `tests/Feature/Livewire/Dashboard/CommandStaffEngagementTest.php`
- `tests/Feature/Livewire/Dashboard/CommandCommunityEngagementTest.php`
- `tests/Feature/Livewire/Dashboard/CommandDepartmentEngagementTest.php`
- `tests/Feature/Dashboard/DashboardAnnouncementTest.php`
- `tests/Feature/Dashboard/DashboardMeetingTest.php`
- `tests/Feature/Actions/GetIterationBoundariesTest.php`
- `tests/Feature/DepartmentReadyRoom/ReadyRoomPageTest.php`
- `tests/Feature/DepartmentReadyRoom/UpcomingMeetingsTest.php`

**Config:**
- `config/services.php` -- `discord.announcements_channel_id`
- `config/lighthouse.php` -- `stripe.customer_portal_url`

**Other:**
- Prayer widget/graph components (referenced from dashboard, documented in Prayer feature)

---

## 18. Known Issues & Improvement Opportunities

1. **Lazy notification dispatch is fragile**: The `dispatchPendingNotifications()` method in `view-announcements` runs on every dashboard page load. While the atomic claim prevents duplicate dispatches, this approach means notifications depend on someone visiting the dashboard. If no one visits, notifications are never sent. A scheduled command would be more reliable.

2. **Quartermaster section uses inline `@if` instead of gate**: Line 109 of `dashboard.blade.php` uses `auth()->user()->isAtLeastRank(Officer) || auth()->user()->isInDepartment(Quartermaster) || auth()->user()->isAdmin()` directly in the Blade template instead of a dedicated gate. This violates the project convention of using gates/policies only for authorization.

3. **Account Linking and Donations cards are inline**: These are not Livewire components but inline Blade code in `dashboard.blade.php`. This is fine for simplicity but makes them harder to test independently.

4. **Missing test coverage for critical flows**: Announcement acknowledgment, rules acceptance, brig appeal submission, and community updates list have minimal or no test coverage (see Coverage Gaps section).

5. **`in-brig-card` calls `RecordActivity::handle()` directly**: Line 80 uses `RecordActivity::handle()` instead of the conventional `RecordActivity::run()` static method. Both work (AsAction trait), but `::run()` is the project convention.

6. **`view-rules` component uses `Cache::forget()` directly**: The `acceptRules()` method calls `Cache::forget('user:' . auth()->user()->id . ':is_stowaway')` -- this cache key management is scattered and could be centralized in the `PromoteUser` action.

7. **Potential N+1 in command widgets**: The command engagement widgets query multiple related models (todos, tickets, reports, meetings) per staff member. While some use aggregate queries, the staff engagement widget's detail modal loads 3-month history per person which could benefit from eager loading optimization.

8. **Community Updates page authorization**: The `community-updates/list` component shows only 1 meeting for non-authorized users. It would be cleaner to either fully gate the page or show a proper "upgrade" CTA rather than a limited preview.
