# Policy Manual — Technical Documentation

> **Audience:** Project owner, developers, AI agents
> **Generated:** 2026-03-26
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

The Policy Manual is a publicly accessible book in the Lighthouse library system that contains the official community policies. It is built entirely on the existing `DocumentationService` + file-based library infrastructure — no new database tables, models, controllers, or routes were added. The `DocumentationService` discovers the book automatically by scanning `resources/library/books/`.

The book has four parts, each with two chapters:

| Part | Chapter 1 | Chapter 2 |
|------|-----------|-----------|
| Community Standards | Code of Conduct | Community Expectations |
| Safety & Privacy | Child Safety Policy | Data Privacy Policy |
| Moderation | Moderation Practices | Discipline System |
| Staff & Operations | Staff Requirements | Operational Policies |

All pages are `visibility: public`, meaning they are readable by unauthenticated visitors without any login gate.

Two enhancements to the existing library infrastructure were shipped alongside the Policy Manual content:

1. **`last_updated` YAML field** — An optional `last_updated` string field was added to `PageDTO`. When present, `DocumentationService::buildPage()` and `buildGuidePage()` pass the value to `PageDTO::$lastUpdated`. The `reader.blade.php` component displays "Last updated: [formatted date]" beneath the page title when this field is non-null.
2. **Automatic book discovery** — No change was needed here; `DocumentationService::getAllBooks()` already scans the `books/` directory, so adding a new subdirectory is sufficient.

---

## 2. Database Schema

No new database tables or migrations were added for this feature. The Policy Manual is entirely file-based, stored under `resources/library/books/policy-manual/`.

---

## 3. Models & Relationships

No new models were introduced. The library system uses DTOs (Data Transfer Objects) rather than Eloquent models.

### PageDTO (`app/Services/Docs/PageDTO.php`)

The `PageDTO` constructor gained one new optional parameter:

| Parameter | Type | Default | Notes |
|-----------|------|---------|-------|
| `$lastUpdated` | `?string` | `null` | Populated from the `last_updated` YAML front-matter field. Passed as a raw string; formatted in the view layer via `Carbon::parse()->format('F j, Y')`. |

No other DTO classes were modified.

---

## 4. Enums Reference

No enums were introduced or modified by this feature.

---

## 5. Authorization & Permissions

### Visibility System

The library system uses a `visibility` field in each `_index.md` and page file. The Policy Manual sets `visibility: public` on the book root (`_index.md`), which propagates to all parts, chapters, and pages via `DocumentationService::resolveVisibility()`. Child items may override visibility individually, but none do in this book.

| Visibility Value | Who Can View |
|-----------------|-------------|
| `public` | Anyone, including unauthenticated visitors |
| `member` | Authenticated users (any membership level) |
| `staff` | Staff members only |

### Gates

No new gates were added. Access control is handled by `CheckDocumentVisibility` action called from the Livewire page components.

| Gate Name | Used By | Logic |
|-----------|---------|-------|
| `edit-docs` | `reader.blade.php` (Edit Page button) | Staff only; edit UI is also restricted to `app()->isLocal()` |

### Permissions Matrix

| User Type | Can View Policy Manual | Edit Page Button Visible |
|-----------|----------------------|--------------------------|
| Unauthenticated | Yes (all pages are public) | No |
| Any authenticated user | Yes | No (local env only) |
| Staff in local env | Yes | Yes (if `edit-docs` gate passes) |

---

## 6. Routes

No new routes were added. The Policy Manual is served by the existing library book routes.

| Method | URL Pattern | Route Name | Handler |
|--------|-------------|-----------|---------|
| GET | `/library/books/policy-manual` | `library.books.show` | `livewire/library/book-show.blade.php` |
| GET | `/library/books/policy-manual/{part}` | `library.books.part` | `livewire/library/part-show.blade.php` |
| GET | `/library/books/policy-manual/{part}/{chapter}` | `library.books.chapter` | `livewire/library/chapter-show.blade.php` |
| GET | `/library/books/policy-manual/{part}/{chapter}/{page}` | `library.books.page` | `livewire/library/page-show.blade.php` |

URL slugs are derived by stripping numeric prefixes from directory/file names (e.g., `01-community-standards` → `community-standards`).

---

## 7. User Interface Components

### `x-library.reader` (Blade component)

**File:** `resources/views/components/library/reader.blade.php`

**Change:** Added display of the `$lastUpdated` prop. When non-null, a line reading "Last updated: [formatted date]" is rendered beneath the page heading using `Carbon::parse($lastUpdated)->format('F j, Y')`.

```blade
@if($lastUpdated)
    <flux:text variant="subtle" class="text-sm mt-1">Last updated: {{ \Carbon\Carbon::parse($lastUpdated)->format('F j, Y') }}</flux:text>
@endif
```

The prop flows from `page-show.blade.php` → `reader.blade.php` via `:lastUpdated="$this->pageData->lastUpdated"`.

### `livewire/library/page-show.blade.php`

No code changes — already passes `$this->pageData->lastUpdated` to the reader component.

### `livewire/library/guide-page.blade.php`

No code changes — the guide page component also passes `lastUpdated` to the reader; this path gains the display capability automatically.

---

## 8. Actions (Business Logic)

### CheckDocumentVisibility (`app/Actions/CheckDocumentVisibility.php`)

No changes were made to this action. It continues to abort with `login_required` or `staff_only` for non-public pages.

No new actions were introduced.

---

## 9. Notifications

No notifications are associated with this feature.

---

## 10. Background Jobs

No background jobs are associated with this feature.

---

## 11. Console Commands & Scheduled Tasks

No console commands or scheduled tasks are associated with this feature.

---

## 12. Services

### DocumentationService (`app/Services/DocumentationService.php`)

**Changes:**

#### `buildPage()` (line ~509)

Added extraction of `last_updated` from page front-matter and passed it as the `lastUpdated` named argument to `PageDTO`:

```php
lastUpdated: $meta['last_updated'] ?? null,
```

#### `buildGuidePage()` (line ~559)

Same change applied symmetrically for guide pages:

```php
lastUpdated: $meta['last_updated'] ?? null,
```

No other changes were made to `DocumentationService`. Book discovery, visibility resolution, navigation, and adjacent-page logic are unchanged.

---

## 13. Activity Log Entries

No activity log entries are recorded for reading library content.

---

## 14. Data Flow Diagrams

### Policy Manual page request (unauthenticated visitor)

```
Browser GET /library/books/policy-manual/community-standards/code-of-conduct/code-of-conduct
    → Route: library.books.page
    → Livewire: page-show.blade.php::mount()
        → DocumentationService::findBookPage('policy-manual', 'community-standards', 'code-of-conduct', 'code-of-conduct')
            → buildBook() → buildPart() → buildChapter() → buildPage()
            → parseFile() reads resources/library/books/policy-manual/01-community-standards/01-code-of-conduct/01-code-of-conduct.md
            → Returns PageDTO (visibility: 'public', lastUpdated: null or date string)
        → CheckDocumentVisibility::run('public') — passes for all visitors
    → x-library.reader rendered with html, navigation, breadcrumbs, lastUpdated
```

### `last_updated` rendering flow

```
Markdown file front-matter: last_updated: '2026-03-01'
    → parseFile() → $meta['last_updated'] = '2026-03-01'
    → buildPage() → PageDTO($lastUpdated = '2026-03-01')
    → page-show.blade.php passes :lastUpdated="$this->pageData->lastUpdated"
    → reader.blade.php: Carbon::parse('2026-03-01')->format('F j, Y') → "March 1, 2026"
    → Displayed as: "Last updated: March 1, 2026"
```

---

## 15. Configuration

No new configuration keys were added. The library base path is registered in a service provider and resolves to `resources/library`.

---

## 16. Test Coverage

### Existing tests that cover the modified code

| Test File | What It Covers |
|-----------|---------------|
| `tests/Feature/Docs/DocumentationServiceTest.php` | Book discovery, part/chapter/page parsing, visibility resolution, navigation building |
| `tests/Feature/Docs/PageDTOTest.php` | Wiki link processing, config variable substitution, URL substitution |

### `last_updated` field coverage

`last_updated` rendering behavior is covered in `tests/Feature/Docs/DocumentViewerTest.php`, including:
- rendering the "Last updated:" line when metadata is present
- omitting the line when `last_updated` is absent

### Policy Manual content

No tests exist specifically for Policy Manual pages. Given all pages are `visibility: public` and the content is static markdown, the risk surface is low. Integration tests for public page rendering are covered by the existing library Livewire component tests.

---

## 17. File Map

### New files (Policy Manual content)

```
resources/library/books/policy-manual/
├── _index.md                                                   Book index (title, order, summary)
├── 01-community-standards/
│   ├── _index.md                                               Part index
│   ├── 01-code-of-conduct/
│   │   ├── _index.md                                           Chapter index
│   │   └── 01-code-of-conduct.md                              Page content
│   └── 02-community-expectations/
│       ├── _index.md
│       └── 01-community-expectations.md
├── 02-safety-and-privacy/
│   ├── _index.md
│   ├── 01-child-safety-policy/
│   │   ├── _index.md
│   │   └── 01-child-safety-policy.md
│   └── 02-data-privacy-policy/
│       ├── _index.md
│       └── 01-data-privacy-policy.md
├── 03-moderation/
│   ├── _index.md
│   ├── 01-moderation-practices/
│   │   ├── _index.md
│   │   └── 01-moderation-practices.md
│   └── 02-discipline-system/
│       ├── _index.md
│       └── 01-discipline-system.md
└── 04-staff-and-operations/
    ├── _index.md
    ├── 01-staff-requirements/
    │   ├── _index.md
    │   └── 01-staff-requirements.md
    └── 02-operational-policies/
        ├── _index.md
        └── 01-operational-policies.md
```

### Modified files

| File | Change |
|------|--------|
| `app/Services/Docs/PageDTO.php` | Added `?string $lastUpdated = null` constructor parameter |
| `app/Services/DocumentationService.php` | `buildPage()` and `buildGuidePage()` now pass `lastUpdated: $meta['last_updated'] ?? null` to PageDTO |
| `resources/views/components/library/reader.blade.php` | Renders "Last updated: [date]" beneath heading when `$lastUpdated` is non-null |

---

## 18. Known Issues & Improvement Opportunities

- **`last_updated` field test coverage:** The rendering and omission of the "Last updated" line is tested in `DocumentViewerTest.php`. Consider adding a unit test in `PageDTOTest.php` to verify front-matter parsing if more granular coverage is desired.
- **Date format is hardcoded:** The "F j, Y" Carbon format is inline in `reader.blade.php`. If the project adopts a configurable date format, this should be extracted to a helper or config key.
- **No validation of `last_updated` format:** The field accepts any string; invalid dates (e.g., `last_updated: 'soon'`) will cause a Carbon parse exception in the view. Consider adding a try/catch around the Carbon parse or validating the field in `buildPage()`.
- **Policy Manual content is not version-controlled via CMS:** The policy pages are static markdown files. Any policy updates require a code deploy. If policies need frequent updates by non-developer staff, consider integrating with the library editor (currently local-env-only) or a CMS workflow.
