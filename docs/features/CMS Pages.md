# CMS Pages -- Technical Documentation

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

The CMS Pages feature provides a simple content management system for creating and publishing static pages on the Lighthouse Website. Pages have a title, URL slug, HTML content (edited via a rich text editor), and a published/draft status. The site's home page (`/`) redirects to the `home` slug page, which is seeded by a migration.

Pages are managed through the Admin Control Panel (ACP) under the Content category's "Pages" tab. Authorized staff can view the list of all pages, navigate to separate create and edit pages (outside the ACP tabs), and publish/unpublish pages. Published pages are publicly accessible at `/{slug}` — the catch-all route at the bottom of the routes file.

Management access is restricted: Admins and Command Officers have full access via the policy's `before()` hook. Beyond that, only Officers or users with the "Page Editor" role — and only those in the Steward or Engineer departments — can create and edit pages. The PagePolicy auto-discovers (not explicitly registered in `AuthServiceProvider`).

---

## 2. Database Schema

### `pages` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint (auto) | No | — | Primary key |
| `title` | string | No | — | Page title |
| `content` | text | No | — | HTML content (rich text) |
| `slug` | string | No | — | URL slug; unique |
| `is_published` | boolean | No | false | Whether page is publicly visible |
| `created_at` | timestamp | Yes | — | Laravel timestamp |
| `updated_at` | timestamp | Yes | — | Laravel timestamp |

**Indexes:**
- `pages_slug_unique` — unique index on `slug`

**Foreign Keys:** None

**Migration(s):**
- `database/migrations/2025_08_04_024907_create_pages_table.php` — creates the table
- `database/migrations/2025_08_04_044524_pages_add_home_page.php` — seeds the default "Home" page

---

## 3. Models & Relationships

### Page (`app/Models/Page.php`)

**Relationships:** None

**Scopes:**
- `scopePublished($query)` — filters to `is_published = true`

**Key Methods:**
- `getRouteKeyName(): string` — returns `'slug'` (route model binding uses slug instead of id)

**Casts:** None explicit

**Fillable:** `title`, `slug`, `content`, `is_published`

---

## 4. Enums Reference

Not applicable for this feature.

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

No dedicated gates for CMS Pages. The `Page::class` model is not explicitly registered in the `$policies` array — it uses Laravel auto-discovery to find `PagePolicy`.

### Policies

#### PagePolicy (`app/Policies/PagePolicy.php`)

**`before()` hook:** Admin OR (Command department AND Officer rank) → returns `true` (full bypass)

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `viewAny` | Officer+ OR "Page Editor" role | For ACP Pages tab visibility |
| `view` | Published pages: anyone; Draft pages: "Page Editor" role OR CrewMember+ | Controls draft page access |
| `create` | ("Page Editor" OR Officer+) AND (Steward OR Engineer dept) | Department-restricted creation |
| `update` | ("Page Editor" OR Officer+) AND (Steward OR Engineer dept) | Same logic as create |
| `delete` | Same as create, OR (Officer+ AND Command dept) | Create permissions + Command Officers |
| `restore` | Nobody | Returns false |
| `forceDelete` | Nobody | Returns false |

### Permissions Matrix

| User Type | View Published | View Drafts | viewAny (ACP) | Create | Update | Delete |
|-----------|---------------|-------------|---------------|--------|--------|--------|
| Unauthenticated | Yes | No | No | No | No | No |
| Regular User | Yes | No | No | No | No | No |
| JrCrew | Yes | No | No | No | No | No |
| CrewMember (non-Steward/Eng) | Yes | Yes | No | No | No | No |
| Page Editor (Steward/Eng) | Yes | Yes | Yes | Yes | Yes | Yes |
| Officer (Steward/Eng) | Yes | Yes | Yes | Yes | Yes | Yes |
| Officer (Command) | Yes | Yes | Yes | Yes* | Yes* | Yes |
| Admin | Yes | Yes | Yes | Yes | Yes | Yes |

*Command Officers pass via `before()` hook, bypassing the department check.

---

## 6. Routes

| Method | URL | Middleware | Handler | Route Name |
|--------|-----|-----------|---------|------------|
| GET | `/` | (none) | Redirect to `pages.show` with slug `home` | — |
| GET | `/acp/pages/create` | auth, `can:create,App\Models\Page` | `PageController@create` | `admin.pages.create` |
| GET | `/acp/pages/{page}/edit` | auth, `can:update,page` | `PageController@edit` | `admin.pages.edit` |
| GET | `/{slug}` | (none — catch-all) | `PageController@show` | `pages.show` |
| GET | `/acp?category=content&tab=page-manager` | auth | ACP → `admin-manage-pages-page` | `acp.index` |

**Note:** The `/{slug}` route is a catch-all at the bottom of `routes/web.php`. It only matches published pages (the controller filters by `is_published = true`).

---

## 7. User Interface Components

### Page List (ACP Tab)
**File:** `resources/views/livewire/admin-manage-pages-page.blade.php`
**Route:** `/acp?category=content&tab=page-manager` (embedded in ACP)

**Purpose:** Lists all CMS pages with links to view and edit.

**Authorization:** Parent ACP tab uses `@can('viewAny', Page::class)`

**PHP Properties:**
- `$pages` — all Page models, loaded in `mount()`

**UI Elements:**
- Table with columns: Title (linked to `pages.show`), Slug, Published (Yes/No), Actions (Edit button)
- "Create New Page" button linking to `admin.pages.create`

### Page Create
**File:** `resources/views/livewire/pages-create.blade.php`
**Route:** `/acp/pages/create` (route name: `admin.pages.create`)

**Purpose:** Form for creating a new CMS page.

**Authorization:** `Gate::authorize('create', Page::class)` in `PageController@create`

**PHP Properties:**
- `$pageTitle`, `$pageSlug`, `$pageContent` (strings)
- `$isPublished` (boolean, default false)

**Key Methods:**
- `savePage()` — validates, creates Page, redirects to ACP page manager tab

**Validation Rules:**
- `pageTitle` — required, string, max 255
- `pageSlug` — required, string, max 255, unique in pages table
- `pageContent` — required, string
- `isPublished` — boolean

**UI Elements:**
- Title input
- Slug input
- Rich text editor (Flux editor component)
- Published toggle (switch)
- Save button

### Page Edit
**File:** `resources/views/livewire/pages-edit.blade.php`
**Route:** `/acp/pages/{page}/edit` (route name: `admin.pages.edit`)

**Purpose:** Form for editing an existing CMS page.

**Authorization:** `Gate::authorize('update', $page)` in `PageController@edit`

**PHP Properties:**
- `$page` (Page model)
- `$pageTitle`, `$pageSlug`, `$pageContent` (strings, loaded from page)
- `$isPublished` (boolean, loaded from page)

**Key Methods:**
- `mount(Page $page)` — loads page data into form fields
- `updatePage()` — validates, updates page, redirects to ACP page manager tab

**Validation Rules:**
- `pageTitle` — required, string, max 255
- `pageSlug` — required, string, max 255, unique (excluding current page)
- `pageContent` — required, string
- `isPublished` — boolean

**UI Elements:**
- Title input
- Slug input
- Rich text editor (Flux editor)
- Published checkbox
- Cancel button (links back to ACP)
- Update button

### Page Show (Public)
**File:** `resources/views/pages/show.blade.php`
**Route:** `/{slug}` (route name: `pages.show`)

**Purpose:** Displays a published page's content to the public.

**Authorization:** None for viewing (only published pages shown). Edit button gated by `@can('update', $page)`.

**UI Elements:**
- Page title heading
- Page content rendered as raw HTML (`{!! $page->content !!}`)
- "Edit Page" button (visible only to authorized editors)

---

## 8. Actions (Business Logic)

Not applicable for this feature. CMS Pages uses direct model operations (`Page::create()`, `$page->update()`) in the Livewire components rather than Action classes.

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

Not applicable for this feature. CMS page creation and updates do not call `RecordActivity::run()`.

---

## 14. Data Flow Diagrams

### Viewing a Published Page

```
User navigates to /{slug}
  -> GET /{slug} (no middleware — catch-all route)
    -> PageController@show($slug)
      -> Page::where('slug', $slug)->where('is_published', true)->firstOrFail()
      -> If not found or not published → 404
      -> return view('pages.show', ['page' => $page])
        -> Renders title and raw HTML content
        -> @can('update', $page) shows "Edit Page" button
```

### Creating a Page

```
Editor navigates to /acp/pages/create
  -> GET /acp/pages/create (middleware: auth, can:create,Page)
    -> PageController@create()
      -> Gate::authorize('create', Page::class) → PagePolicy@create()
      -> return view('pages.create') → <livewire:pages-create />

Editor fills form and clicks "Save Page"
  -> savePage() fires
    -> validate(pageTitle, pageSlug unique, pageContent, isPublished)
    -> Page::create([title, slug, content, is_published])
    -> Flux::toast('Page created successfully!')
    -> redirect to acp.index with tab=page-manager
```

### Editing a Page

```
Editor clicks "Edit" on a page in ACP or "Edit Page" on public page
  -> GET /acp/pages/{page}/edit (middleware: auth, can:update,page)
    -> PageController@edit($page)
      -> Gate::authorize('update', $page) → PagePolicy@update()
      -> return view('pages.edit', compact('page')) → <livewire:pages-edit :page="$page" />

Editor modifies fields and clicks "Update Page"
  -> updatePage() fires
    -> validate(pageTitle, pageSlug unique excluding self, pageContent, isPublished)
    -> $page->update([title, slug, content, is_published])
    -> Flux::toast('Page updated successfully!')
    -> redirect to acp.index with tab=page-manager
```

### Home Page Redirect

```
User navigates to /
  -> Route returns redirect to route('pages.show', ['slug' => 'home'])
    -> GET /home
      -> PageController@show('home')
        -> Renders the seeded Home page
```

---

## 15. Configuration

Not applicable for this feature.

---

## 16. Test Coverage

### Test Files

No dedicated test files exist for the CMS Pages feature.

The only file referencing pages routes is `tests/Feature/Auth/GuestRedirectTest.php`, which tests that unauthenticated users are redirected from protected routes — but this is not specific to CMS Pages.

### Test Case Inventory

None.

### Coverage Gaps

- **No tests at all** — CMS Pages has zero dedicated test coverage
- **No test for PagePolicy** — authorization logic for viewAny, view, create, update, delete is untested
- **No test for page creation** — the `savePage()` Livewire method, validation, and slug uniqueness are untested
- **No test for page editing** — the `updatePage()` method is untested
- **No test for public page viewing** — `PageController@show()` with published/unpublished filtering is untested
- **No test for home page redirect** — the `/` → `/home` redirect is untested
- **No test for the "Page Editor" role** — the role-based access path through the policy is untested
- **No test for draft page access** — whether non-staff can access unpublished pages is untested
- **No test for slug-based routing** — the `getRouteKeyName()` override is untested

---

## 17. File Map

**Models:**
- `app/Models/Page.php`

**Enums:** None

**Actions:** None

**Policies:**
- `app/Policies/PagePolicy.php`

**Gates:** None (uses auto-discovered policy)

**Notifications:** None

**Jobs:** None

**Services:** None

**Controllers:**
- `app/Http/Controllers/PageController.php`

**Volt Components:**
- `resources/views/livewire/admin-manage-pages-page.blade.php` (ACP list)
- `resources/views/livewire/pages-create.blade.php` (create form)
- `resources/views/livewire/pages-edit.blade.php` (edit form)

**Views:**
- `resources/views/pages/show.blade.php` (public view)
- `resources/views/pages/create.blade.php` (layout wrapper)
- `resources/views/pages/edit.blade.php` (layout wrapper)

**Routes:**
- `/` — redirect to `pages.show` with slug `home`
- `admin.pages.create` — `GET /acp/pages/create`
- `admin.pages.edit` — `GET /acp/pages/{page}/edit`
- `pages.show` — `GET /{slug}`

**Migrations:**
- `database/migrations/2025_08_04_024907_create_pages_table.php`
- `database/migrations/2025_08_04_044524_pages_add_home_page.php`

**Console Commands:** None

**Tests:** None

**Config:** None

**Other:** None

---

## 18. Known Issues & Improvement Opportunities

1. **Zero test coverage** — The CMS Pages feature has no tests whatsoever. Given that it handles public content rendering and has role-based authorization, this is a significant gap.

2. **XSS vulnerability** — `resources/views/pages/show.blade.php` uses `{!! $page->content !!}` to render raw HTML. While content is created by trusted editors, the lack of HTML sanitization means that if an editor's account were compromised, malicious JavaScript could be injected into public pages.

3. **No activity logging** — Page creation, updates, and publishing changes are not logged via `RecordActivity::run()`. This means there is no audit trail for content changes.

4. **No delete functionality in UI** — The `PagePolicy` defines a `delete` method, but no controller action, route, or UI button exists to actually delete a page. The `PageController@destroy()` method is empty.

5. **Empty controller methods** — `PageController` has stub methods for `index()`, `store()`, `update()`, and `destroy()` that do nothing. These should either be implemented or removed.

6. **No authorization in Livewire components** — The `pages-create.blade.php` and `pages-edit.blade.php` components do not call `$this->authorize()`. Authorization relies entirely on the route middleware (`can:create,Page` and `can:update,page`), but the Livewire `savePage()` and `updatePage()` methods can be called directly via wire:click without re-checking authorization.

7. **Business logic in components** — Page creation and update use direct `Page::create()` and `$page->update()` calls in Livewire components, not Action classes. This is inconsistent with the project convention.

8. **Inconsistent published toggle** — The create form uses `<flux:switch>` for the published toggle, while the edit form uses `<flux:checkbox>`. This is a UI inconsistency.

9. **Catch-all route risk** — The `/{slug}` route is a catch-all at the bottom of `routes/web.php`. If a page slug collides with a future route (e.g., someone creates a page with slug "dashboard"), the specific route takes precedence, but it could cause confusion.

10. **No content versioning** — There is no revision history or ability to revert page changes. Each update overwrites the previous content.

11. **Auto-generated slug not supported** — The slug must be manually entered. There's no auto-generation from the title, which increases the chance of errors or inconsistent URL patterns.
