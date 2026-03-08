# Community Updates -- Technical Documentation

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

Community Updates is a public-facing, read-only feature that displays sanitized meeting notes to the Lighthouse community. When staff complete a meeting through the Meetings System, the community-facing notes (stored in `community_minutes`) are published to this page for all users -- including guests -- to read.

The page is accessible to everyone without authentication. However, the amount of content visible depends on the user's membership level: guests and users below Traveler rank see only the most recent update, while Traveler-level members and above can browse the full paginated history.

This feature is tightly coupled with the Meetings System. It does not have its own models, actions, or write operations. It reads from the `meetings` table, filtering on `status = completed` and `show_community_updates = true`. The content itself is generated during the meeting finalization process (often AI-formatted) and stored in the `community_minutes` column. The `show_community_updates` toggle is controlled by meeting managers during the Finalizing stage.

The Community Updates link appears in the sidebar navigation under the "Community" section for all users (authenticated and guest), making it one of the most publicly visible features of the application.

---

## 2. Database Schema

This feature reads from the `meetings` table. It does not have its own dedicated tables.

### `meetings` table (relevant columns only)

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | No | auto | |
| title | string | No | | Meeting title, displayed in accordion heading |
| day | string | No | | Date string (Y-m-d), displayed in accordion heading |
| status | string | No | `pending` | Must be `completed` to appear on Community Updates |
| community_minutes | text | Yes | null | AI-formatted or manually edited community-facing notes |
| show_community_updates | boolean | No | true | Toggle to include/exclude from Community Updates page |
| created_at | timestamp | No | | |
| updated_at | timestamp | No | | |

**Relevant Migrations:**
- `database/migrations/2025_08_08_034207_create_meetings_table.php`
- `database/migrations/2025_08_15_000816_update_meetings_add_minutes_fields.php` (adds `community_minutes`)
- `database/migrations/2026_03_05_160632_add_show_community_updates_to_meetings_table.php` (adds `show_community_updates`)

For full schema, see the [Meetings System](Meetings%20System.md) technical documentation.

---

## 3. Models & Relationships

### Meeting (`app/Models/Meeting.php`)

This feature uses the Meeting model in read-only mode. Only the columns and casts relevant to Community Updates are documented here.

**Relevant Fillable:** `community_minutes`, `show_community_updates`

**Relevant Casts:**
- `status` => `MeetingStatus`
- `show_community_updates` => `boolean`

For full model documentation including all relationships, see the [Meetings System](Meetings%20System.md) technical documentation.

---

## 4. Enums Reference

### MeetingStatus (`app/Enums/MeetingStatus.php`)

Only the `Completed` case is relevant to this feature -- meetings must have `status = completed` to appear on the Community Updates page.

| Case | Value | Label | Notes |
|------|-------|-------|-------|
| `Completed` | `completed` | Completed | Required for visibility on Community Updates |

For all enum cases, see the [Meetings System](Meetings%20System.md) technical documentation.

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

| Gate Name | Who Can Pass | Logic Summary |
|-----------|-------------|---------------|
| `view-all-community-updates` | Traveler+ or Admin | `$user->isAtLeastLevel(MembershipLevel::Traveler) \|\| $user->hasRole('Admin')` |

This gate controls whether a user sees the full paginated history or only the latest update. The page itself has **no authentication requirement** -- guests can access it.

### Policies

No policies are specific to this feature. The Community Updates page is read-only and does not perform any model mutations.

### Permissions Matrix

| User Type | Access Page | See Latest Update | See Full History (Paginated) |
|-----------|------------|-------------------|------------------------------|
| Guest (unauthenticated) | Yes | Yes | No |
| Stowaway | Yes | Yes | No |
| Traveler | Yes | Yes | Yes |
| Resident | Yes | Yes | Yes |
| Citizen | Yes | Yes | Yes |
| Jr Crew / Crew / Officer | Yes | Yes | Yes |
| Admin | Yes | Yes | Yes |

---

## 6. Routes

| Method | URL | Middleware | Handler | Route Name |
|--------|-----|-----------|---------|------------|
| GET | `/community-updates` | (none) | `CommunityUpdatesController@index` | `community-updates.index` |

Note: This route has **no middleware** -- it is accessible to unauthenticated users.

---

## 7. User Interface Components

### Community Updates List
**File:** `resources/views/livewire/community-updates/list.blade.php`
**Route:** `/community-updates` (route name: `community-updates.index`)
**Layout:** `resources/views/community-updates/index.blade.php`

**Purpose:** Displays community updates from completed meetings in an accordion format.

**Authorization:** No authentication required to view. The `view-all-community-updates` gate determines whether the full paginated history or only the latest update is shown.

**PHP Class Logic:**
- Uses `WithPagination` trait
- Computed property `canViewAll`: checks `view-all-community-updates` gate
- Query: `Meeting::where('status', MeetingStatus::Completed)->where('show_community_updates', true)->orderBy('day', 'desc')`
- If `canViewAll`: paginates with 10 per page
- Otherwise: limits to 1 result (latest only)

**UI Elements:**
- `flux:accordion` (exclusive mode) with each meeting as an accordion item
- Accordion heading: `{meeting title} - {meeting day}`
- First item expanded by default
- Card containing `{!! nl2br($meeting->community_minutes) !!}`
- Empty state: "No community updates available."
- Pagination links (for privileged users with multiple pages)
- CTA card for non-privileged users: "Community members can see all past updates. Join our community to access the full archive!"

### Sidebar Navigation
**File:** `resources/views/components/layouts/app/sidebar.blade.php` (line 59)

**Purpose:** Displays the "Community Updates" link in the sidebar under the "Community" heading.

**Details:** Always visible (no gate or auth check). Uses `newspaper` icon. Active state when route matches `community-updates.index`.

---

## 8. Actions (Business Logic)

Not applicable for this feature. Community Updates is read-only. The content is written by the Meetings System during meeting finalization (see `manage-meeting.blade.php` `CompleteMeetingConfirmed()` and `toggleCommunityUpdates()` methods documented in the [Meetings System](Meetings%20System.md) docs).

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

Not applicable for this feature directly. The `toggle_community_updates` activity is logged by the Meetings System's manage-meeting component (see [Meetings System](Meetings%20System.md) docs).

---

## 14. Data Flow Diagrams

### Viewing Community Updates (Guest)

```text
Guest navigates to /community-updates
  -> GET /community-updates (no middleware)
    -> CommunityUpdatesController@index
      -> Renders community-updates.index blade layout
        -> Mounts livewire:community-updates.list
          -> Gate::check('view-all-community-updates') -> false (no auth)
          -> Query: Meeting::where(status=completed, show_community_updates=true)
                    ->orderBy('day', 'desc')
                    ->limit(1)->get()
          -> Renders accordion with latest community update
          -> Shows "Join our community" CTA card
```

### Viewing Community Updates (Traveler+ Member)

```text
Authenticated Traveler navigates to /community-updates
  -> GET /community-updates (no middleware)
    -> CommunityUpdatesController@index
      -> Renders community-updates.index blade layout
        -> Mounts livewire:community-updates.list
          -> Gate::check('view-all-community-updates') -> true
          -> Query: Meeting::where(status=completed, show_community_updates=true)
                    ->orderBy('day', 'desc')
                    ->paginate(10)
          -> Renders accordion with all community updates
          -> Shows pagination links if > 10 results
```

### How Content Gets Published (Cross-Feature Reference)

```text
Officer completes a meeting (Meetings System)
  -> CompleteMeetingConfirmed() in manage-meeting.blade.php
    -> Copies community note content to $meeting->community_minutes
    -> $meeting->completeMeeting() sets status = Completed
    -> Meeting now appears on Community Updates page
       (if show_community_updates = true)
```

---

## 15. Configuration

Not applicable for this feature. The Community Updates page has no feature-specific configuration values. The content generation (AI prompt, provider, model) is part of the Meetings System configuration.

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/Meeting/CommunityUpdatesTest.php` | 16 | Navigation, page access, content visibility, permissions, pagination, filtering |

### Test Case Inventory

**Community Updates Navigation page:**
- `it('shows a link in the sidebar navigation for authenticated users')`
- `it('shows the link for all membership levels')` (parametrized)
- `it('shows the link for Stowaway and below')` (parametrized)

**Community Updates page:**
- `it('loads the Community Updates page')`
- `it('is accessible without authentication')`
- `it('is accessible to all membership levels')` (parametrized)

**Community Updates List:**
- `it('lists finalized meetings with community updates for privileged users')`
- `it('shows only the latest update for guests')`
- `it('shows only the latest update for non-privileged users')` (parametrized)
- `it('shows all updates for privileged users')` (parametrized)
- `it('does not show meetings that are not completed')`
- `it('paginates meetings with 10 per page for privileged users')`
- `it('shows empty state when no completed meetings exist')`
- `it('renders accordion with first item expanded')`
- `it('does not show meetings with show_community_updates disabled')`
- `it('shows meetings with show_community_updates enabled')`

### Related Tests in Other Files

- `tests/Feature/Meeting/MeetingTypeTest.php` -- tests that `show_community_updates` defaults to `true` for staff meetings and `false` for non-staff meetings
- `tests/Feature/Meeting/MeetingEditTest.php` -- tests the community updates toggle during meeting finalization

### Coverage Gaps

- No test verifies the "Join our community" CTA card text for non-privileged users
- No test verifies the sidebar link is visible to guests (unauthenticated users)
- No test for markdown/HTML rendering behavior in `community_minutes` content

---

## 17. File Map

**Models:** `app/Models/Meeting.php` (shared with Meetings System)

**Enums:** `app/Enums/MeetingStatus.php` (shared with Meetings System)

**Actions:** None

**Policies:** None

**Gates:** `app/Providers/AuthServiceProvider.php` -- gate: `view-all-community-updates`

**Notifications:** None

**Jobs:** None

**Services:** None

**Controllers:** `app/Http/Controllers/CommunityUpdatesController.php`

**Volt Components:** `resources/views/livewire/community-updates/list.blade.php`

**Blade Views:**
- `resources/views/community-updates/index.blade.php` (layout)
- `resources/views/components/layouts/app/sidebar.blade.php` (navigation link, line 59)

**Routes:** `community-updates.index` -- GET `/community-updates`

**Migrations:**
- `database/migrations/2025_08_08_034207_create_meetings_table.php`
- `database/migrations/2025_08_15_000816_update_meetings_add_minutes_fields.php`
- `database/migrations/2026_03_05_160632_add_show_community_updates_to_meetings_table.php`

**Console Commands:** None

**Tests:**
- `tests/Feature/Meeting/CommunityUpdatesTest.php`
- `tests/Feature/Meeting/MeetingTypeTest.php` (related)
- `tests/Feature/Meeting/MeetingEditTest.php` (related)

**Config:** None

---

## 18. Known Issues & Improvement Opportunities

1. **XSS Risk in Content Rendering**: The component uses `{!! nl2br($meeting->community_minutes) !!}` which renders raw HTML. If the AI-generated or manually edited community minutes contain malicious HTML, it would be rendered unescaped. Should use `{!! nl2br(e($meeting->community_minutes)) !!}` or a markdown renderer like `Str::markdown()`.

2. **No Markdown Rendering**: The `community_minutes` content is AI-generated with markdown formatting (headings, bullet points, blockquotes), but the view only applies `nl2br()` which doesn't render markdown. The content would display better with a proper markdown renderer.

3. **Query Inefficiency for Non-Privileged Users**: When `canViewAll` is false, the component uses `->limit(1)->get()` which returns a Collection, not a Paginator. This means the template's `@if($meetings->hasPages())` check will throw an error if called on a Collection. However, since the pagination section is gated behind `@if($this->canViewAll)`, this is not a runtime issue -- just an inconsistency in the data type returned by `with()`.

4. **Sidebar Link Always Visible**: The Community Updates sidebar link has no auth gate, so it appears for all users including guests. This is likely intentional (the page is public), but it's worth noting that it's one of the few ungated sidebar items.

5. **Missing `community_minutes` Null Check in Template**: The template renders `{!! nl2br($meeting->community_minutes) !!}` without checking if `community_minutes` is null. While meetings that reach Completed status should have community minutes populated, a meeting could theoretically be completed without them (e.g., if the community note was empty during finalization). This would render an empty card rather than showing a "no content" message.

6. **Date Display Format**: The accordion heading uses `$meeting->day` (a raw string like "2026-03-07") rather than a formatted date. Other parts of the application format dates more readably (e.g., "March 7, 2026").
