# Prayer Tracking -- Technical Documentation

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

The Prayer Tracking feature encourages community members to pray daily by integrating with the "Operation World" resource, which assigns a country/topic to each day of the year. Each day, users see a prayer widget on their dashboard showing today's prayer topic with links to Operation World and PrayerCast resources. Users can click "I Prayed Today" to record their prayer, which tracks a personal prayer streak and contributes to a community-wide prayer count.

The feature has three components: a **dashboard prayer widget** (visible to Stowaway+ members), a **community prayer graph** (also on the dashboard, showing 7-day participation trends), and an **admin management panel** (in the ACP Config tab) where authorized staff can set up the prayer country/topic for each day of the year via a calendar interface.

Prayer data is organized by day (stored as "month-day" strings like "3-7" for March 7th), and user prayer records are tracked per country per year to prevent duplicate prayers. The system is timezone-aware — it uses each user's configured timezone (defaulting to America/New_York) for determining the current day, calculating streaks, and managing year boundaries.

Key concepts:
- **Prayer Country** — a country/topic assigned to a specific day of the year, with optional Operation World and PrayerCast URLs
- **Prayer Streak** — consecutive days a user has prayed, tracked on the User model
- **Prayer Stats** — per-country, per-year aggregate count of how many community members prayed
- **Prayer Year** — the calendar year in the user's timezone, used to scope prayer records

---

## 2. Database Schema

### `prayer_countries` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint (auto) | No | — | Primary key |
| `day` | string | No | — | Day identifier in "month-day" format (e.g., "3-7"); unique |
| `name` | string | No | — | Country/topic name |
| `operation_world_url` | string | Yes | NULL | Link to Operation World page |
| `prayer_cast_url` | string | Yes | NULL | Link to PrayerCast video |
| `created_at` | timestamp | Yes | — | Laravel timestamp |
| `updated_at` | timestamp | Yes | — | Laravel timestamp |

**Indexes:**
- `prayer_countries_day_unique` — unique index on `day`

**Migration(s):**
- `database/migrations/2025_08_23_221734_create_prayer_countries_table.php`
- `database/migrations/2025_08_24_025913_update_prayer_countries_make_key_unique.php`

### `prayer_country_user` table (pivot)

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint (auto) | No | — | Primary key |
| `user_id` | foreignId | No | — | FK to users |
| `prayer_country_id` | foreignId | No | — | FK to prayer_countries |
| `year` | year | No | — | Calendar year the prayer was recorded |
| `created_at` | timestamp | Yes | — | Laravel timestamp |
| `updated_at` | timestamp | Yes | — | Laravel timestamp |

**Foreign Keys:**
- `user_id` → `users.id` (ON DELETE CASCADE)
- `prayer_country_id` → `prayer_countries.id` (ON DELETE CASCADE)

**Migration(s):** `database/migrations/2025_08_24_192956_create_prayer_user_table.php`

### `prayer_country_stats` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint (auto) | No | — | Primary key |
| `prayer_country_id` | foreignId | No | — | FK to prayer_countries |
| `year` | year | No | — | Calendar year |
| `count` | integer | No | 0 | Number of users who prayed |
| `created_at` | timestamp | Yes | — | Laravel timestamp |
| `updated_at` | timestamp | Yes | — | Laravel timestamp |

**Indexes:**
- `country_year_unique` — unique composite on `(prayer_country_id, year)`

**Foreign Keys:**
- `prayer_country_id` → `prayer_countries.id` (ON DELETE CASCADE)

**Migration(s):** `database/migrations/2025_08_24_204703_create_prayer_country_stats_table.php`

### `users` table (added columns)

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `prayer_streak` | integer | No | 0 | Consecutive days prayed |
| `last_prayed_at` | timestamp | Yes | NULL | Last prayer timestamp (UTC) |

**Migration(s):** `database/migrations/2025_08_24_200909_update_users_add_prayer_streak_fields.php`

---

## 3. Models & Relationships

### PrayerCountry (`app/Models/PrayerCountry.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `stats()` | hasMany | PrayerCountryStat | Per-year prayer counts |

**Scopes:** None

**Key Methods:** None

**Casts:** None explicit

**Fillable:** `day`, `name`, `operation_world_url`, `prayer_cast_url`

**Factory:** Has factory (`HasFactory` trait)

### PrayerCountryStat (`app/Models/PrayerCountryStat.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `prayerCountry()` | belongsTo | PrayerCountry | Parent country |

**Scopes:** None

**Key Methods:** None

**Casts:** None explicit

**Fillable:** `prayer_country_id`, `year`, `count`

**Factory:** Has factory (`HasFactory` trait)

### User (`app/Models/User.php`) — related fields and methods

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `prayerCountries()` | belongsToMany | PrayerCountry | Via `prayer_country_user` pivot; withPivot('year'), withTimestamps() |

**Fields:** `prayer_streak` (integer), `last_prayed_at` (timestamp)

---

## 4. Enums Reference

Not applicable for this feature.

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

No dedicated gates for prayer tracking. Authorization is handled via the `PrayerCountryPolicy`.

### Policies

#### PrayerCountryPolicy (`app/Policies/PrayerCountryPolicy.php`)

**`before()` hook:** Admin OR (Command department AND Officer rank) → returns `true` (full bypass)

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `viewAny` | Chaplain Officer | `isInDepartment(Chaplain) && isAtLeastRank(Officer)` |
| `viewPrayer` | Stowaway+ members | `isAtLeastLevel(MembershipLevel::Stowaway)` |
| `create` | Chaplain Officer | `isInDepartment(Chaplain) && isAtLeastRank(Officer)` |
| `update` | Chaplain Officer | `isInDepartment(Chaplain) && isAtLeastRank(Officer)` |

**Registered in:** `AuthServiceProvider` (implicit — not in the explicit `$policies` array, uses Laravel auto-discovery)

### Permissions Matrix

| User Type | View Prayer Widget | Mark as Prayed | Manage Prayer Data (ACP) |
|-----------|-------------------|----------------|--------------------------|
| Drifter | No | No | No |
| Stowaway+ | Yes | Yes | No |
| Non-Chaplain Staff | Yes | Yes | No |
| Chaplain JrCrew/CrewMember | Yes | Yes | No |
| Chaplain Officer | Yes | Yes | Yes |
| Command Officer | Yes | Yes | Yes |
| Admin | Yes | Yes | Yes |

---

## 6. Routes

The Prayer Tracking feature is embedded as dashboard widgets and an ACP tab. It has no dedicated routes.

| Method | URL | Middleware | Handler | Route Name |
|--------|-----|-----------|---------|------------|
| GET | `/dashboard` | auth | Dashboard view → `prayer.prayer-widget` + `prayer.prayer-graph` | `dashboard` |
| GET | `/acp?category=config&tab=prayer-manager` | auth | ACP → `prayer.manage-months` | `acp.index` |

---

## 7. User Interface Components

### Prayer Widget (Dashboard)
**File:** `resources/views/livewire/prayer/prayer-widget.blade.php`
**Route:** `/dashboard` (embedded in dashboard, gated by `@can('viewPrayer', PrayerCountry::class)`)

**Purpose:** Shows today's prayer country/topic and allows users to mark they prayed today.

**Authorization:** `@can('viewPrayer', App\Models\PrayerCountry::class)` on the dashboard

**PHP Properties:**
- `$day` — current day in "month-day" format
- `$prayerCountry` — PrayerCountry model for today (or null)
- `$hasPrayedToday` — boolean, whether user already prayed this year for this country
- `$user` — authenticated User model
- `$prayerStats` — PrayerCountryStat for today's country and current year
- `$userTimezone` — user's timezone (default: America/New_York)
- `$currentDate` — current date in user's timezone
- `$currentYear` — current year in user's timezone

**Key Methods:**
- `mount()` — loads today's prayer data, checks if user has prayed
- `checkIfUserHasPrayedThisYear()` — cached check via `Cache::flexible()` with prayer_cache_ttl
- `markAsPrayedToday()` — records prayer, updates streak, increments stats count, clears cache
- `loadPrayerData($month, $day)` — fetches PrayerCountry from cache or DB, creates stats record if needed

**UI Elements:**
- Card with "Pray Today" heading
- Prayer streak display (bolt icon + count)
- Community prayer count (user-group icon + count)
- Operation World section with country name
- "Prayer Details" button (links to operation_world_url)
- "PrayerCast Video" button (links to prayer_cast_url, shown only if URL exists)
- "Lighthouse Prayer List" link (from config)
- "I Prayed Today" button (or disabled "Thank you for Praying" if already prayed)

### Prayer Graph (Dashboard)
**File:** `resources/views/livewire/prayer/prayer-graph.blade.php`
**Route:** `/dashboard` (embedded in dashboard, gated by `@can('viewPrayer', PrayerCountry::class)`)

**Purpose:** Shows a line/area chart of community prayer participation over the last 7 days.

**Authorization:** Same as prayer widget — `@can('viewPrayer', PrayerCountry::class)`

**PHP Properties:**
- `$data` — array of date/prayers objects for the chart

**Key Methods:**
- `mount()` — builds 7-day dataset from PrayerCountryStat records, initializes all days to 0 prayers, then fills in actual counts

**UI Elements:**
- Card with "Community Prayer Participation" heading
- Line chart (Flux chart component) with sky-blue line and area fill
- Y-axis with compact notation, X-axis with date labels

### Prayer Management (ACP Config Tab)
**File:** `resources/views/livewire/prayer/manage-months.blade.php`
**Route:** `/acp?category=config&tab=prayer-manager` (embedded in ACP)

**Purpose:** Admin interface for setting up prayer country data for each day of the year.

**Authorization:** `$this->authorize('update', $this->prayerCountry)` or `$this->authorize('create', PrayerCountry::class)` in `savePrayerData()`

**PHP Properties:**
- `$prayerCountry` — currently loaded PrayerCountry (or null)
- `$month`, `$monthName`, `$year`, `$day`, `$date` — date selection state
- `$months` — array of month number → name mappings
- `$prayerName`, `$prayerDay`, `$prayerOperationWorldUrl`, `$prayerPrayerCastUrl` — form data

**Key Methods:**
- `mount()` — initializes year, month map
- `openMonthModal($month)` — opens modal for a month, loads prayer data for day 1
- `updatedDate()` — fires when calendar date changes, loads prayer data for selected date
- `savePrayerData()` — authorizes create/update, validates, upserts PrayerCountry, clears cache
- `loadPrayerData($month, $day)` — fetches from cache or DB, populates form fields
- `resetPrayerData()` — clears form fields

**Modals:**
- `month-modal` — contains calendar widget, day display, country name input, Operation World URL, Prayer Cast URL, Save button

**UI Elements:**
- Table with 12 rows (one per month), each clickable to open the modal
- Modal with: Flux calendar (date picker), day display text, country name input, two URL inputs, Save button

---

## 8. Actions (Business Logic)

The Prayer Tracking feature does not use action classes. All business logic is handled directly in the Livewire Volt components:

- **Recording a prayer** — handled in `prayer-widget.blade.php` → `markAsPrayedToday()`
- **Managing prayer data** — handled in `manage-months.blade.php` → `savePrayerData()`

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

Not applicable for this feature. Prayer tracking does not call `RecordActivity::run()`.

---

## 14. Data Flow Diagrams

### Marking "I Prayed Today"

```
User clicks "I Prayed Today" on dashboard prayer widget
  -> markAsPrayedToday() fires
    -> Guard: if no prayerCountry, toast error and return
    -> Guard: if hasPrayedToday, toast warning and return
    -> Guard: check DB pivot for duplicate (year + country), toast warning if exists
    -> Attach prayer record: user.prayerCountries().attach(countryId, { year })
    -> Calculate streak:
      -> Get last_prayed_at in user timezone
      -> If prayed yesterday → increment prayer_streak
      -> Else if not today → reset prayer_streak to 1
    -> Update user: last_prayed_at = now(), prayer_streak
    -> Increment prayerStats.count
    -> Clear cache: "user_prayer_{userId}_{countryId}_{year}"
    -> Set hasPrayedToday = true
    -> Flux::toast('Thank you for praying today!')
```

### Managing Prayer Country Data (Admin)

```
Admin clicks month name in ACP Prayer Nations tab
  -> openMonthModal($month) fires
    -> Sets month, monthName, date, prayerDay
    -> loadPrayerData($month, 1) — loads day 1 data from cache/DB
    -> Flux::modal('month-modal')->show()

Admin clicks a different date on the calendar
  -> wire:model.live="date" triggers updatedDate()
    -> Parses date string into year/month/day
    -> loadPrayerData($month, $day) — loads data from cache/DB
    -> Form populates with existing data or resets

Admin fills form and clicks Save
  -> savePrayerData() fires
    -> $this->authorize('update'|'create', PrayerCountry)
    -> validate(prayerName required, URLs optional valid URLs)
    -> PrayerCountry::updateOrCreate({ day: prayerDay }, { name, urls })
    -> Cache::forget("prayer_country_{month}_{day}")
    -> Flux::toast('Prayer data saved successfully.')
```

### Viewing the Prayer Graph

```
User navigates to dashboard
  -> @can('viewPrayer', PrayerCountry::class) passes
    -> <livewire:prayer.prayer-graph /> mounts
      -> Build 7-day array with 0 default prayers
      -> Query PrayerCountryStat where created_at >= 7 days ago
      -> Fill in actual counts by matching date
      -> Render Flux chart with line + area
```

---

## 15. Configuration

| Key | Default | Purpose |
|-----|---------|---------|
| `lighthouse.prayer_cache_ttl` | `86400` (24 hours) | Cache TTL in seconds for prayer country data and user prayer status |
| `lighthouse.prayer_list_url` | `https://app.echoprayer.com/user/feeds/4068` | External URL to the Lighthouse prayer list |
| `PRAYER_CACHE_TTL` | `86400` | Env variable override for cache TTL |
| `PRAYER_LIST_URL` | (see above) | Env variable override for prayer list URL |

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/Prayer/PrayerDashboardPanelTest.php` | 14 tests | Dashboard widget behavior, permissions, streaks, caching |
| `tests/Feature/Prayer/PrayerManagementPanelTest.php` | 9 tests | ACP management panel, CRUD, permissions |
| `tests/Feature/Prayer/PrayerTimezoneTest.php` | 7 tests | Timezone handling for prayers and streaks |
| `tests/Feature/Prayer/PrayerGraphTest.php` | 2 tests | Graph widget rendering and data loading |

### Test Case Inventory

#### `PrayerDashboardPanelTest.php`

1. `displays the prayer widget on the dashboard`
2. `shows a link to the lighthouse prayer list`
3. `shows a link to Operation World and PrayerCast`
4. `does not show PrayerCast if the URL is null`
5. `should allow Stowaway and above to view the panel`
6. `should not allow Drifters to view the panel`
7. `should display a button to mark the prayer as prayed for today`
8. `should save an entry in the db when clicked`
9. `should disable the button if the user has already prayed for this country this year`
10. `should cache the users prayer status`
11. `should update the users prayer streak on their profile`
12. `should reset the users prayer streak if they miss a day`
13. `should show the users prayer streak`
14. `adds to the stats when clicking I Prayed`

#### `PrayerManagementPanelTest.php`

1. `should display the Prayer management tab`
2. `should display the Prayer Management component`
3. `should display a list of months`
4. `should open a modal with a list of days when a month is clicked`
5. `should show a new form for today if no data exists`
6. `should load existing data for today if it exists`
7. `should save the changes to the database`
8. `should allow Command and Chaplain departments to view the panel`
9. `should prevent other officer departments from viewing the panel`

#### `PrayerTimezoneTest.php`

1. `uses America/New_York as default timezone when user timezone is null`
2. `uses the users timezone when set`
3. `uses user timezone for prayer year calculations`
4. `sets last_prayed_at timestamp when marking as prayed`
5. `calculates prayer streaks based on user timezone for consecutive days`
6. `resets streak when user misses more than one day in their timezone`
7. `handles different timezones correctly`

#### `PrayerGraphTest.php`

1. `should show the graph widget`
2. `should load 7 days of data`

### Coverage Gaps

- **No test for duplicate prayer prevention at the DB level** — the pivot table has no unique constraint on `(user_id, prayer_country_id, year)`, relying on application-level checks.
- **No test for `PrayerCountryPolicy` in isolation** — the policy is tested indirectly through the management panel and dashboard tests, but there is no dedicated policy test file.
- **No test for cache invalidation edge cases** — clearing caches after save/pray is tested, but stale cache scenarios (e.g., data changed by another user) are not.
- **No test for prayer graph with missing data** — the graph test checks 7-day loading but doesn't test edge cases like no data at all or sparse data.

---

## 17. File Map

**Models:**
- `app/Models/PrayerCountry.php`
- `app/Models/PrayerCountryStat.php`
- `app/Models/User.php` (related: `prayer_streak`, `last_prayed_at`, `prayerCountries()`)

**Enums:** None

**Actions:** None

**Policies:**
- `app/Policies/PrayerCountryPolicy.php`

**Gates:** None (uses policy abilities)

**Notifications:** None

**Jobs:** None

**Services:** None

**Controllers:** None

**Volt Components:**
- `resources/views/livewire/prayer/prayer-widget.blade.php` (dashboard widget)
- `resources/views/livewire/prayer/prayer-graph.blade.php` (dashboard graph)
- `resources/views/livewire/prayer/manage-months.blade.php` (ACP management)

**Routes:**
- Dashboard — `GET /dashboard` (prayer widget and graph embedded)
- ACP — `GET /acp?category=config&tab=prayer-manager`

**Migrations:**
- `database/migrations/2025_08_23_221734_create_prayer_countries_table.php`
- `database/migrations/2025_08_24_025913_update_prayer_countries_make_key_unique.php`
- `database/migrations/2025_08_24_192956_create_prayer_user_table.php`
- `database/migrations/2025_08_24_204703_create_prayer_country_stats_table.php`
- `database/migrations/2025_08_24_200909_update_users_add_prayer_streak_fields.php`

**Console Commands:** None

**Tests:**
- `tests/Feature/Prayer/PrayerDashboardPanelTest.php`
- `tests/Feature/Prayer/PrayerManagementPanelTest.php`
- `tests/Feature/Prayer/PrayerTimezoneTest.php`
- `tests/Feature/Prayer/PrayerGraphTest.php`

**Config:**
- `config/lighthouse.php` — keys: `prayer_cache_ttl`, `prayer_list_url`

**Other:**
- `resources/views/dashboard.blade.php` — embeds prayer widget and graph with `@can('viewPrayer')` gate

---

## 18. Known Issues & Improvement Opportunities

1. **No unique constraint on pivot table** — The `prayer_country_user` table has no unique constraint on `(user_id, prayer_country_id, year)`. Duplicate prayer records could theoretically be created if two requests race. The application-level check in `markAsPrayedToday()` prevents this under normal conditions, but a database constraint would provide stronger protection.

2. **Business logic in Livewire components instead of actions** — The `markAsPrayedToday()` method contains significant business logic (streak calculation, stats increment, cache management) that would be better encapsulated in an Action class following the project convention. Similarly, `savePrayerData()` could be an Action.

3. **No activity logging** — Prayer-related actions (marking as prayed, saving prayer data) do not call `RecordActivity::run()`. This is inconsistent with other features that log administrative changes.

4. **Prayer graph uses `created_at` instead of `year`/day** — `prayer-graph.blade.php` queries `PrayerCountryStat::where('created_at', '>=', $startDate)` which relies on the stat record's creation timestamp rather than the actual prayer day. If a stat record was created at a different time than the prayers occurred (e.g., `firstOrCreate` on a different day), the graph data could be inaccurate.

5. **Stats count can become inconsistent** — The `prayerStats->count` is incremented directly in the widget (`$this->prayerStats->count++; $this->prayerStats->save()`). If multiple users pray simultaneously, race conditions could cause lost increments. An atomic `increment()` call would be safer.

6. **`viewPrayer` ability is not a standard Laravel convention** — The policy uses `viewPrayer` (a custom ability name) rather than the conventional `view` or a gate. This works but is unusual in the codebase where most viewing is handled by `viewAny` or gates.

7. **Cache::flexible() usage** — The feature uses `Cache::flexible()` with a stale-while-revalidate pattern. This is good for performance but means users may see slightly stale data (up to 7x the cache TTL) if the cache is not explicitly cleared.

8. **PrayerCountryPolicy not in explicit policy map** — Unlike other policies, `PrayerCountryPolicy` is not listed in the `$policies` array in `AuthServiceProvider`. It relies on Laravel's auto-discovery, which works but is inconsistent with the explicit registration pattern used for other policies.

9. **Hardcoded default timezone** — `America/New_York` is hardcoded as the default timezone in multiple places. This could be a config value for consistency.
