# Rules System — Technical Documentation

> **Audience:** Project owner, developers, AI agents
> **Generated:** 2026-04-09
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

The Rules System replaces the previous static rules page with a fully dynamic, versioned rules management system. Community rules are stored in the database and organized into categories. Each rule has a title, Markdown description, and can be updated by creating a replacement rule entry that tracks what it supersedes. Rules are bundled into published versions; users must explicitly agree to the current version.

**Who uses it:**

- **All Stowaway+ users** must agree to the current published rules version to access the dashboard. Agreement is tracked per-version so users must re-agree whenever a new version is published.
- **Drifter users** (new/unverified) are presented with rules agreement as part of onboarding; agreeing promotes them to Stowaway.
- **Parent users** can submit proxy agreements for linked child accounts that have not agreed.
- **Staff with "Rules - Manage"** can create draft rule versions, add/edit/deactivate rules, manage categories, and edit header/footer content.
- **Staff with "Rules - Approve"** (different person from the draft creator) can review and publish or reject submitted versions.
- **Admins** have access to full agreement history and compliance monitoring.

**Key concepts:**

- **Rule Version**: A snapshot of the active ruleset at a point in time. A new draft is created, rules are modified, then submitted for approval by a second reviewer, and finally published.
- **Agreement**: A `user_rule_agreements` record ties a user to a specific version at a specific timestamp. Agreements can optionally record a `proxy_user_id` (for parent proxy agreements).
- **Rules Non-Compliance Brig**: Users who do not agree within 28 days of a version being published are automatically placed in a brig of type `RulesNonCompliance`. This brig auto-lifts when they agree.
- **Rule Classification**: When re-agreeing, each rule in the current version is classified as `new`, `updated`, or `unchanged` relative to the user's last agreement.

---

## 2. Database Schema

### `rule_categories` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned | No | auto | Primary key |
| name | varchar(255) | No | | Category display name |
| sort_order | int | No | 0 | Display order (ascending) |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

**Migration:** `database/migrations/2026_04_09_000001_create_rules_tables.php`, `2026_04_09_000005_add_sort_order_to_rules.php`

---

### `rules` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned | No | auto | Primary key |
| rule_category_id | bigint unsigned | No | | FK → rule_categories.id |
| title | varchar(255) | No | | Short identifier (e.g. "No Griefing") |
| description | text | No | | Markdown rule text |
| status | varchar(255) | No | 'draft' | 'draft', 'active', 'inactive' |
| supersedes_rule_id | bigint unsigned | Yes | null | FK → rules.id; points to replaced rule |
| created_by_user_id | bigint unsigned | Yes | null | FK → users.id |
| sort_order | int | No | 0 | Display order within category |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

**Indexes:** `rule_category_id`, `supersedes_rule_id`, `created_by_user_id`
**Migration:** `2026_04_09_000001_create_rules_tables.php`, `2026_04_09_000005_add_sort_order_to_rules.php`

---

### `rule_versions` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned | No | auto | Primary key |
| version_number | int | No | | Auto-incremented per version |
| status | varchar(255) | No | 'draft' | 'draft', 'submitted', 'published' |
| created_by_user_id | bigint unsigned | Yes | null | FK → users.id |
| approved_by_user_id | bigint unsigned | Yes | null | FK → users.id; must differ from creator |
| rejection_note | text | Yes | null | Feedback when version rejected |
| published_at | timestamp | Yes | null | When version was published |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

**Migration:** `2026_04_09_000001_create_rules_tables.php`

---

### `rule_version_rules` pivot table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| rule_version_id | bigint unsigned | No | | FK → rule_versions.id, cascadeOnDelete |
| rule_id | bigint unsigned | No | | FK → rules.id, cascadeOnDelete |
| deactivate_on_publish | tinyint(1) | No | 0 | If 1, rule is deactivated when version is published |

**Primary Key:** `(rule_version_id, rule_id)`
**Migration:** `2026_04_09_000001_create_rules_tables.php`, `2026_04_09_000004_add_deactivate_on_publish_to_rule_version_rules.php`

---

### `user_rule_agreements` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned | No | auto | Primary key |
| user_id | bigint unsigned | No | | FK → users.id, cascadeOnDelete |
| rule_version_id | bigint unsigned | No | | FK → rule_versions.id, cascadeOnDelete |
| agreed_at | timestamp | No | | When agreement was recorded |
| proxy_user_id | bigint unsigned | Yes | null | FK → users.id, nullOnDelete; parent user if proxy agreement |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

**Unique Index:** `(user_id, rule_version_id)`
**Migration:** `2026_04_09_000001_create_rules_tables.php`, `2026_04_09_000006_add_proxy_user_id_to_user_rule_agreements.php`

---

### `discipline_report_rules` pivot table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| discipline_report_id | bigint unsigned | No | | FK → discipline_reports.id, cascadeOnDelete |
| rule_id | bigint unsigned | No | | FK → rules.id, cascadeOnDelete |

**Primary Key:** `(discipline_report_id, rule_id)`
**Migration:** `2026_04_09_000001_create_rules_tables.php`

---

### `users` table additions

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| rules_accepted_at | timestamp | Yes | null | Legacy; last time user agreed to any rules |
| rules_accepted_by_user_id | bigint unsigned | Yes | null | FK → users.id; who recorded the agreement |
| rules_reminder_sent_at | timestamp | Yes | null | When the 2-week reminder email was last sent |

**Migration:** `2025_08_05_040555_update_users_add_rules_acceptance_field.php`, `2026_03_31_000001_add_rules_accepted_by_user_id_to_users.php`, `2026_04_09_000001_create_rules_tables.php`

---

## 3. Models & Relationships

### `Rule` (`app/Models/Rule.php`)

**Relationships:**

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `ruleCategory()` | BelongsTo | RuleCategory | |
| `supersedes()` | BelongsTo | Rule (self) | Points to the rule this entry replaces |
| `ruleVersions()` | BelongsToMany | RuleVersion | Via `rule_version_rules`; pivot includes `deactivate_on_publish` |

**Key Methods:**
- `isDraft(): bool` — returns `status === 'draft'`
- `isActive(): bool` — returns `status === 'active'`

**Casts:** None

---

### `RuleVersion` (`app/Models/RuleVersion.php`)

**Relationships:**

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `createdBy()` | BelongsTo | User | |
| `approvedBy()` | BelongsTo | User | |
| `rules()` | BelongsToMany | Rule | Via `rule_version_rules`; pivot includes `deactivate_on_publish` |
| `activeRules()` | BelongsToMany | Rule | Same pivot; filtered to `deactivate_on_publish = false` |
| `agreements()` | HasMany | UserRuleAgreement | |

**Scopes/Static Methods:**
- `currentPublished(): ?RuleVersion` — static method; returns the latest published version
- `currentDraft(): ?RuleVersion` — static method; returns the latest draft or submitted version

**Key Methods:**
- `isDraft(): bool` — returns `status === 'draft'`
- `isPublished(): bool` — returns `status === 'published'`

**Casts:**
- `published_at` → `datetime`

---

### `RuleCategory` (`app/Models/RuleCategory.php`)

**Relationships:**

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `rules()` | HasMany | Rule | Ordered by `sort_order` |

---

### `UserRuleAgreement` (`app/Models/UserRuleAgreement.php`)

**Relationships:**

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `user()` | BelongsTo | User | The user who the agreement is for |
| `ruleVersion()` | BelongsTo | RuleVersion | |
| `proxyUser()` | BelongsTo | User | The parent who submitted the agreement (if proxy) |

**Key Methods:**
- `isProxy(): bool` — returns `proxy_user_id !== null`

**Casts:**
- `agreed_at` → `datetime`

---

### `User` (`app/Models/User.php`) — rules-related additions

**Relationships:**

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `ruleAgreements()` | HasMany | UserRuleAgreement | |
| `rulesAcceptedBy()` | BelongsTo | User | Via `rules_accepted_by_user_id` |
| `children()` | BelongsToMany | User | Via `parent_child_links` |
| `parents()` | BelongsToMany | User | Via `parent_child_links` (reverse) |

**Key Methods:**
- `hasAgreedToCurrentRules(): bool` — checks for a `user_rule_agreements` record for the current published version; returns `true` if no published version exists
- `unagreedChildren(): Collection` — returns linked children (via `parent_child_links`) who do not have an agreement for the current published version

---

### `DisciplineReport` (`app/Models/DisciplineReport.php`) — rules-related addition

**Relationship:**

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `violatedRules()` | BelongsToMany | Rule | Via `discipline_report_rules` pivot |

---

## 4. Enums Reference

### `BrigType` (`app/Enums/BrigType.php`) — rules-related case

| Case | Value | Label | Notes |
|------|-------|-------|-------|
| `RulesNonCompliance` | `'rules_non_compliance'` | "Rules Non-Compliance" | Auto-placed at 28+ days; auto-lifted on agreement |

Other BrigType cases exist but are unrelated to the rules system. See the Brig System documentation for the full enum.

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

| Gate Name | Who Can Pass | Logic Summary |
|-----------|-------------|---------------|
| `rules.manage` | Staff with "Rules - Manage" role | `$user->hasRole('Rules - Manage')` |
| `rules.approve` | Staff with "Rules - Approve" role | `$user->hasRole('Rules - Approve')` |

**Roles seeded by migration `2026_04_09_000003_seed_rules_permission_roles.php`:**
- `Rules - Manage` — create/edit rules, manage categories, edit header/footer
- `Rules - Approve` — submit version for approval, approve/reject submitted versions

### Policies

No dedicated policy class for rules models. Authorization uses gates directly via `$this->authorize('rules.manage')` in components.

The `EnsureRulesAgreed` middleware enforces agreement at the route level (not via policy).

### Permissions Matrix

| User Type | View Rules Page | Agree to Rules | Proxy Agree for Child | View Compliance List | View Agreement History | Manage Draft | Approve Version |
|-----------|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| Drifter | ✓ | ✓ | — | — | — | — | — |
| Stowaway+ | ✓ | ✓ | ✓ (if parent) | — | — | — | — |
| Staff (Rules - Manage) | ✓ | ✓ | ✓ | ✓ | — | ✓ | — |
| Admin | ✓ | ✓ | ✓ | ✓ | ✓ | — | — |
| Staff (Rules - Approve, different from draft creator) | ✓ | ✓ | ✓ | — | — | — | ✓ |

---

## 6. Routes

| Method | URL | Middleware | Handler | Route Name |
|--------|-----|-----------|---------|------------|
| GET | `/rules` | `auth`, `verified`, `ensure-dob` | `rules-page` Volt component | `rules.show` |
| GET | `/dashboard` | `auth`, `verified`, `ensure-dob`, `ensure-rules-agreed` | `dashboard` Volt component | `dashboard` |
| GET | `/acp/rules` | `auth`, `verified`, staff-access gate | `admin-manage-rules-page` Volt component | ACP tab |

The `ensure-rules-agreed` middleware is registered in `bootstrap/app.php` as `'ensure-rules-agreed' => \App\Http\Middleware\EnsureRulesAgreed::class`.

---

## 7. User Interface Components

### Rules Agreement Page
**File:** `resources/views/livewire/rules-page.blade.php`
**Route:** `/rules` (route name: `rules.show`)

**Purpose:** Allows users to read and agree to the current published rules version. Also handles parent proxy agreement for child accounts.

**Authorization:** No explicit gate; accessible to any authenticated verified user with date-of-birth confirmed.

**User Actions Available:**
- **Agree to rules** (self): Check all rule checkboxes → click sticky "I Have Read and Agree to All Rules" button → calls `AgreeToRulesVersion::run(auth()->user(), auth()->user())` → redirects to dashboard
- **Agree on behalf of children** (proxy): Select child accounts via checkboxes → click "Submit Proxy Agreement" → calls `AgreeToRulesVersion::run($child, $parent)` for each selected child → redirects to dashboard when all children are covered

**UI Elements:**
- Optional top notice banner (amber for self, blue for proxy requirement)
- Optional header markdown (from `SiteConfig::getValue('rules_header')`)
- Per-category sections with rule cards
- Each rule card: title, `NEW`/`UPDATED` badges, Markdown description
- `UPDATED` rules show a collapsible `<details>` section with the previous rule text
- Checkboxes per rule (hidden once user has already agreed)
- Sticky bottom bar: text hint + agree button (disabled until all rules checked)
- "You have agreed" confirmation when already agreed
- Proxy agreement section (visible only when user has agreed but has unagreed children): child list with checkboxes, submit button

---

### Admin Manage Rules Page
**File:** `resources/views/livewire/admin-manage-rules-page.blade.php`
**Route:** ACP tab (within `/acp`)

**Purpose:** Full rules administration UI for managing categories, rules, draft versions, approval workflow, and header/footer content.

**Authorization:** `$this->authorize('rules.manage')` for management actions; `$this->authorize('rules.approve')` for approve/reject.

**User Actions Available:**
- Edit header/footer text → calls `UpdateRulesHeaderFooter::run($header, $footer)`
- Add category → calls `CreateCategory::run(...)` (inline RuleCategory::create)
- Move category up/down → swaps `sort_order` values
- Add rule to draft → calls `AddRuleToDraft::run(...)`
- Edit rule in draft → calls `UpdateRuleInDraft::run(...)`
- Deactivate rule in draft → calls `DeactivateRuleInDraft::run(...)`
- Move rule up/down → swaps `sort_order` values
- Create new draft version → calls `CreateRuleVersion::run(...)`
- Submit draft for approval → calls `SubmitVersionForApproval::run(...)`
- Approve version → calls `ApproveAndPublishVersion::run(...)`
- Reject version → calls `RejectDraftVersion::run(...)`

**Embedded sub-components:**
- `<livewire:rules-version-history />` — version history table (visible to `rules.manage` users)
- `<livewire:rules-compliance-list />` — non-compliance monitoring (visible to `rules.manage` users)
- `<livewire:rules-agreement-history />` — full agreement audit trail (visible to admins only)

**UI Elements:** Header/footer textareas; category list with reorder arrows and add button; rule list per category with status badges, edit/deactivate/reorder buttons; draft workflow section with submit/approve/reject buttons and rejection note; embedded sub-components.

---

### Rules Compliance List
**File:** `resources/views/livewire/rules-compliance-list.blade.php`
**Embedded in:** `admin-manage-rules-page`

**Purpose:** Shows all Stowaway+ users who have not agreed to the current published version.

**Authorization:** Visible only to users with `rules.manage` gate.

**UI Elements:** Table with columns: User (name + email), Membership Level, Days Overdue (amber badge at 14+ days, red badge at 28+ days), Reminder Sent (date or "—").

---

### Rules Agreement History
**File:** `resources/views/livewire/rules-agreement-history.blade.php`
**Embedded in:** `admin-manage-rules-page`

**Purpose:** Full searchable, paginated audit trail of all `user_rule_agreements` records.

**Authorization:** Visible only to admins.

**User Actions Available:**
- Search by user name or email (live with debounce)

**UI Elements:** Search input; table with columns: User (name + email), Version (badge), Agreed At (formatted datetime); pagination links.

---

### Rules Version History
**File:** `resources/views/livewire/rules-version-history.blade.php`
**Embedded in:** `admin-manage-rules-page`

**Purpose:** Shows all published rule versions with approval metadata.

**UI Elements:** Table with columns: Version number, Published date, Created By, Approved By.

---

### Dashboard View Rules Widget
**File:** `resources/views/livewire/dashboard/view-rules.blade.php`

**Purpose:** Dashboard widget for the community/onboarding section. Shows a "Read & Accept Rules" button for unagreed users, or "View Rules" for those who have already agreed.

---

## 8. Actions (Business Logic)

### `AgreeToRulesVersion` (`app/Actions/AgreeToRulesVersion.php`)

**Signature:** `handle(User $user, User $actingUser): void`

**Step-by-step logic:**
1. Fetches `RuleVersion::currentPublished()`. Returns early if none exists.
2. Calls `UserRuleAgreement::updateOrCreate(['user_id', 'rule_version_id'], ['agreed_at' => now(), 'proxy_user_id' => null (self) or $actingUser->id (proxy)])`.
3. Updates `$user->rules_accepted_at = now()` and `$user->rules_accepted_by_user_id = $actingUser->id`.
4. If user is Drifter: logs activity `rules_accepted` with promotion message, calls `PromoteUser::run($user, MembershipLevel::Stowaway)`.
5. Otherwise: logs activity `rules_accepted` with version number.
6. If user is in a `RulesNonCompliance` brig: calls `ReleaseUserFromBrig::run($user, $user, '...', notify: false)`.

**Called by:** `rules-page.blade.php` (both `agreeToRules()` and `agreeForChildren()`), `PutUserInRulesBrig` (not called), legacy code.

---

### `GetRulesAgreementStatus` (`app/Actions/GetRulesAgreementStatus.php`)

**Signature:** `handle(User $user): array`

**Returns:**
```php
[
    'has_agreed' => bool,
    'current_version' => ?RuleVersion,
    'categories' => Collection,  // RuleCategory objects with ->rules collection
]
```
Each rule object in the categories collection is annotated with:
- `agreement_status`: `'new'` | `'updated'` | `'unchanged'`
- `previous_rule`: the superseded Rule object (only for `'updated'`)

**Classification logic:**
1. Builds set of all rule IDs the user has ever agreed to (across all versions).
2. Builds set of rule IDs in the user's most recently agreed version.
3. For each rule in the current version:
   - If rule ID was in any prior agreed version → `unchanged`
   - If rule's `supersedes_rule_id` was in the previous version → `updated`
   - Otherwise → `new`

**Called by:** `rules-page.blade.php`

---

### `CreateRuleVersion` (`app/Actions/CreateRuleVersion.php`)

**Signature:** `handle(User $createdBy): RuleVersion`

**Step-by-step logic:**
1. Computes next `version_number` (max existing + 1, minimum 1).
2. Creates `RuleVersion` with status `draft`.
3. Copies all currently active rules from the latest published version into `rule_version_rules` with `deactivate_on_publish = false`.

**Called by:** `admin-manage-rules-page.blade.php`

---

### `AddRuleToDraft` (`app/Actions/AddRuleToDraft.php`)

**Signature:** `handle(RuleVersion $draft, RuleCategory $category, string $title, string $description, User $createdBy): Rule`

**Step-by-step logic:**
1. Creates `Rule` with status `draft`, `created_by_user_id = $createdBy->id`.
2. Attaches rule to draft version via `rule_version_rules` with `deactivate_on_publish = false`.

**Called by:** `admin-manage-rules-page.blade.php`

---

### `UpdateRuleInDraft` (`app/Actions/UpdateRuleInDraft.php`)

**Signature:** `handle(RuleVersion $draft, Rule $oldRule, string $newTitle, string $newDescription, User $createdBy, ?RuleCategory $newCategory): Rule`

**Step-by-step logic:**
1. Creates new `Rule` with `supersedes_rule_id = $oldRule->id`, status `draft`.
2. Attaches new rule to draft version with `deactivate_on_publish = false`.
3. Updates the pivot for old rule to set `deactivate_on_publish = true`.

**Called by:** `admin-manage-rules-page.blade.php`

---

### `DeactivateRuleInDraft` (`app/Actions/DeactivateRuleInDraft.php`)

**Signature:** `handle(RuleVersion $draft, Rule $rule): void`

**Step-by-step logic:**
1. Updates the `rule_version_rules` pivot for this rule in this version to set `deactivate_on_publish = true`.

**Called by:** `admin-manage-rules-page.blade.php`

---

### `SubmitVersionForApproval` (`app/Actions/SubmitVersionForApproval.php`)

**Signature:** `handle(RuleVersion $version, User $submittedBy): void`

**Step-by-step logic:**
1. Validates `$version->status === 'draft'`.
2. Sets status to `submitted`.

**Called by:** `admin-manage-rules-page.blade.php`

---

### `ApproveAndPublishVersion` (`app/Actions/ApproveAndPublishVersion.php`)

**Signature:** `handle(RuleVersion $version, User $approvedBy): void`

**Step-by-step logic:**
1. Validates `$version->status === 'submitted'`.
2. Validates `$approvedBy->id !== $version->created_by_user_id` (two-person rule).
3. Sets `approved_by_user_id = $approvedBy->id`, `status = published`, `published_at = now()`.
4. Activates all draft rules in the version (sets `status = active`).
5. Deactivates rules marked with `deactivate_on_publish = true` (sets `status = inactive`).
6. Sends `RulesVersionPublishedNotification` to all Stowaway+ users.

**Called by:** `admin-manage-rules-page.blade.php`

---

### `RejectDraftVersion` (`app/Actions/RejectDraftVersion.php`)

**Signature:** `handle(RuleVersion $version, User $rejectedBy, string $rejectionNote): void`

**Step-by-step logic:**
1. Validates `$version->status === 'submitted'`.
2. Sets `status = draft`, stores `rejection_note`.

**Called by:** `admin-manage-rules-page.blade.php`

---

### `PutUserInRulesBrig` (`app/Actions/PutUserInRulesBrig.php`)

**Signature:** `handle(User $user): void`

**Step-by-step logic:**
1. Checks if user is already in any brig. If so, returns silently.
2. Finds the system admin user (`system@lighthouse.local`) as the acting admin, or falls back to `$user` itself.
3. Calls `PutUserInBrig::run($user, $admin, '...', brigType: BrigType::RulesNonCompliance)` with no expiry and no appeal delay.

**Called by:** `CheckRulesAgreementJob`

---

### `SendRulesAgreementReminder` (`app/Actions/SendRulesAgreementReminder.php`)

**Signature:** `handle(User $user, RuleVersion $version): void`

**Step-by-step logic:**
1. Sends `RulesAgreementReminderNotification` via `TicketNotificationService`.
2. Sets `$user->rules_reminder_sent_at = now()`.

**Called by:** `CheckRulesAgreementJob`

---

### `UpdateRulesHeaderFooter` (`app/Actions/UpdateRulesHeaderFooter.php`)

**Signature:** `handle(string $header, string $footer): void`

**Step-by-step logic:**
1. Saves `SiteConfig::setValue('rules_header', $header)`.
2. Saves `SiteConfig::setValue('rules_footer', $footer)`.

**Called by:** `admin-manage-rules-page.blade.php`

---

### `AgreeToRules` (`app/Actions/AgreeToRules.php`) — Legacy

**Signature:** `handle(User $user, User $actingUser): array`

**Returns:** `['success' => bool, 'message' => string]`

Legacy action from before the versioned rules system. Only handles Drifter-level users agreeing. Not used in the new flow. Retained for compatibility.

---

## 9. Notifications

### `RulesAgreementReminderNotification` (`app/Notifications/RulesAgreementReminderNotification.php`)

**Triggered by:** `SendRulesAgreementReminder` action (called from `CheckRulesAgreementJob`)
**Recipient:** The Stowaway+ user who has not agreed after 14+ days
**Channels:** Mail (primary); Pushover (optional)
**Mail subject:** "Reminder: Please Agree to the Updated Community Rules"
**Content summary:** Warns the user that they must agree to the current rules version; notes they have approximately 2 more weeks before brig placement. Includes a button linking to `route('rules.show')`.
**Queued:** Yes (`ShouldQueue`)

---

### `RulesVersionPublishedNotification` (`app/Notifications/RulesVersionPublishedNotification.php`)

**Triggered by:** `ApproveAndPublishVersion` action
**Recipient:** All Stowaway+ users (bulk send when a new version is published)
**Channels:** Mail (primary); Pushover (optional)
**Mail subject:** "Community Rules Updated — Please Review and Agree"
**Content summary:** Notifies the user that a new rules version has been published and that continued access requires re-agreement. Includes a button linking to the dashboard (which will redirect to `rules.show` via middleware).
**Queued:** Yes (`ShouldQueue`)

---

## 10. Background Jobs

### `CheckRulesAgreementJob` (`app/Jobs/CheckRulesAgreementJob.php`)

**Triggered by:** Laravel scheduler (daily at 07:00)
**Implements:** `ShouldQueue`

**What it does:**
1. Fetches the current published `RuleVersion`. Exits if none or no `published_at`.
2. Queries all `user_rule_agreements` for the current version to get agreed user IDs.
3. Finds all Stowaway+ users (`membership_level >= Stowaway`) who are NOT in that agreed set.
4. Computes days since `published_at`.
5. For each unagreed user:
   - If `days >= 28` and user is not already in a `RulesNonCompliance` brig → calls `PutUserInRulesBrig::run($user)`
   - Else if `days >= 14` and `rules_reminder_sent_at` is null → calls `SendRulesAgreementReminder::run($user, $version)`
6. The 28-day brig check takes priority over the 14-day reminder check.

**Queue/Delay:** Runs daily; `withoutOverlapping(10)` (10-minute timeout lock); `onOneServer()` to prevent duplicate runs in multi-server deployments.

---

## 11. Console Commands & Scheduled Tasks

### Scheduled: `CheckRulesAgreementJob`

**File:** `app/Jobs/CheckRulesAgreementJob.php`
**Scheduled in:** `routes/console.php`
**Schedule expression:** `dailyAt('07:00')->withoutOverlapping(10)->onOneServer()`
**What it does:** Sends reminders at 14+ days and places non-compliant users in rules brig at 28+ days after a version is published.

No standalone Artisan commands for this feature.

---

## 12. Services

### `TicketNotificationService` (`app/Services/TicketNotificationService.php`)

Used by `SendRulesAgreementReminder` and `ApproveAndPublishVersion` to send notifications. See the Notification System documentation for full details.

No rules-specific service class exists.

---

## 13. Activity Log Entries

| Action String | Logged By | Subject Model | Description |
|---------------|-----------|---------------|-------------|
| `rules_accepted` | `AgreeToRulesVersion` | User (the agreeing user) | "User agreed to rules version {N}." or "User accepted community rules and was promoted to Stowaway." or "Community rules agreed on behalf of user by {parent name} (parent). User promoted to Stowaway." |

---

## 14. Data Flow Diagrams

### User Agreeing to Rules (Self)

```
User visits /dashboard
  -> GET /dashboard (middleware: auth, verified, ensure-dob, ensure-rules-agreed)
    -> EnsureRulesAgreed::handle()
      -> $user->hasAgreedToCurrentRules() = false
        -> redirect to /rules

User is on /rules page
  -> rules-page Volt component loads
    -> GetRulesAgreementStatus::run(auth()->user())
      -> Queries user_rule_agreements for current version
      -> Classifies each rule as new/updated/unchanged
      -> Returns {has_agreed: false, categories: [...]

User checks all rule checkboxes -> sticky "Agree" button enables
User clicks "I Have Read and Agree to All Rules"
  -> wire:click="agreeToRules"
    -> allChecked() validates all rules are checked
    -> AgreeToRulesVersion::run($user, $user)
      -> UserRuleAgreement::updateOrCreate(proxy_user_id: null)
      -> $user->rules_accepted_at = now()
      -> If Drifter: PromoteUser::run($user, Stowaway)
      -> RecordActivity::run($user, 'rules_accepted', ...)
      -> If in RulesNonCompliance brig: ReleaseUserFromBrig::run(...)
    -> Flux::toast('Rules accepted successfully!')
    -> redirect to /dashboard
```

---

### Parent Proxy Agreement

```
Parent visits /dashboard
  -> EnsureRulesAgreed::handle()
    -> $parent->hasAgreedToCurrentRules() = true (parent has agreed)
    -> $parent->unagreedChildren()->isNotEmpty() = true
      -> redirect to /rules

Parent is on /rules page
  -> GetRulesAgreementStatus::run($parent) = {has_agreed: true}
  -> $this->unagreedChildren = [$child1, $child2]
  -> Page shows proxy agreement section with child checkboxes

Parent checks child accounts, clicks "Submit Proxy Agreement"
  -> wire:click="agreeForChildren"
    -> For each selected child:
      -> AgreeToRulesVersion::run($child, $parent)
        -> UserRuleAgreement::updateOrCreate(proxy_user_id: $parent->id)
        -> $child->rules_accepted_by_user_id = $parent->id
        -> If Drifter child: PromoteUser::run($child, Stowaway)
        -> RecordActivity::run($child, 'rules_accepted', 'agreed on behalf by {parent}')
        -> If child in brig: ReleaseUserFromBrig::run(...)
    -> Flux::toast("Rules accepted for N child account(s).")
    -> If all children now agreed: redirect to /dashboard
```

---

### New Version Publication Workflow

```
Staff (Rules - Manage) opens rules admin page
  -> Creates draft version: CreateRuleVersion::run($staff)
    -> RuleVersion created (status: draft, version_number: N+1)
    -> Active rules from current published version seeded into rule_version_rules

Staff adds new rule:
  -> AddRuleToDraft::run($draft, $category, $title, $description, $staff)
    -> Rule created (status: draft)
    -> Linked to draft via rule_version_rules (deactivate_on_publish: false)

Staff edits existing rule:
  -> UpdateRuleInDraft::run($draft, $oldRule, ...)
    -> New Rule created (status: draft, supersedes_rule_id: $oldRule->id)
    -> New rule linked to draft (deactivate_on_publish: false)
    -> Old rule pivot updated (deactivate_on_publish: true)

Staff submits for approval:
  -> SubmitVersionForApproval::run($draft, $staff)
    -> version.status = 'submitted'

Different staff (Rules - Approve) reviews and approves:
  -> ApproveAndPublishVersion::run($version, $approver)
    -> Validates: status === 'submitted' AND approver ≠ creator
    -> version.status = 'published', published_at = now()
    -> All draft rules in version: status → 'active'
    -> All deactivate_on_publish rules: status → 'inactive'
    -> RulesVersionPublishedNotification sent to all Stowaway+ users

Daily at 07:00, CheckRulesAgreementJob runs:
  -> Finds Stowaway+ users who have not agreed
  -> At 14+ days: SendRulesAgreementReminder::run($user, $version)
    -> RulesAgreementReminderNotification sent to user
    -> $user->rules_reminder_sent_at = now()
  -> At 28+ days: PutUserInRulesBrig::run($user)
    -> PutUserInBrig::run($user, $admin, BrigType::RulesNonCompliance)

User who was bricked finally agrees:
  -> AgreeToRulesVersion::run($user, $user)
    -> UserRuleAgreement created
    -> ReleaseUserFromBrig::run($user, $user, ..., notify: false)
    -> Brig auto-lifted
```

---

### Discipline Report with Violated Rules

```
Staff opens user profile -> clicks "New Report"
  -> create-report-modal opens
    -> getActiveRulesProperty() loads all active rules grouped by category
    -> Staff selects rules via checkbox list (formRuleIds)

Staff submits report:
  -> wire:click="createReport" in discipline-reports-card
    -> CreateDisciplineReport::run(..., ruleIds: [1, 3, 7])
      -> DisciplineReport::create(...)
      -> $report->violatedRules()->sync($ruleIds)
      -> RecordActivity::run(...)
    -> Modals close, toast shown

On view-report page:
  -> $report->load([..., 'violatedRules'])
  -> Rules displayed as red badges in report details card
```

---

## 15. Configuration

| Key | Storage | Purpose |
|-----|---------|---------|
| `rules_header` | `SiteConfig` key/value table | Markdown text displayed above the rules list (e.g. scripture quote) |
| `rules_footer` | `SiteConfig` key/value table | Markdown text displayed below the rules list (e.g. officer discretion disclaimer) |

Both values are editable from the rules admin page by users with the `rules.manage` gate. They are seeded by `database/migrations/2026_04_09_000002_seed_rules_data.php` with the community's existing scripture quote and disclaimer text.

No environment variables are specific to this feature.

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/Rules/RulesSchemaTest.php` | ~15 | Database schema, seeded data, BrigType enum, SiteConfig keys |
| `tests/Feature/Rules/DashboardGateTest.php` | 3 | Middleware: redirect for non-agreed, pass-through for agreed, rules page accessible without agreement |
| `tests/Feature/Rules/ComplianceAndHistoryTest.php` | 3 | Compliance query accuracy, Drifter exclusion, agreement history records |
| `tests/Feature/Rules/ParentProxyAgreementTest.php` | 8 | Proxy user tracking, Drifter promotion via proxy, brig lift via proxy, unagreedChildren(), dashboard gate for parents, cross-account isolation |
| `tests/Feature/Actions/Rules/CreateRuleVersionTest.php` | ~3 | Draft creation, version_number increment, active rule seeding |
| `tests/Feature/Actions/Rules/AddRuleToDraftTest.php` | ~3 | Rule creation, pivot linking, status tracking |
| `tests/Feature/Actions/Rules/UpdateRuleInDraftTest.php` | ~4 | Replacement rule creation, supersedes_rule_id, deactivate_on_publish flag |
| `tests/Feature/Actions/Rules/DeactivateRuleInDraftTest.php` | ~2 | Pivot deactivate_on_publish flag set without immediate deactivation |
| `tests/Feature/Actions/Rules/AgreeToRulesVersionTest.php` | 6 | Agreement record creation, idempotency, Drifter promotion, brig lift |
| `tests/Feature/Actions/Rules/GetRulesAgreementStatusTest.php` | 6 | new/updated/unchanged classification logic |
| `tests/Feature/Actions/Rules/VersionApprovalTest.php` | ~8 | Submit/approve/reject workflow, creator ≠ approver validation, rule activation/deactivation, notification sending |
| `tests/Feature/Actions/Rules/UpdateRulesHeaderFooterTest.php` | ~2 | SiteConfig storage |
| `tests/Feature/Actions/Rules/ReorderRulesTest.php` | ~4 | Category and rule sort_order swapping |
| `tests/Feature/Jobs/CheckRulesAgreementJobTest.php` | 7 | 14-day reminder (once), 28-day brig (no double-brig), Drifter exclusion, already-agreed exclusion |
| `tests/Feature/Actions/DisciplineReports/DisciplineReportRuleLinkingTest.php` | 5 | Create with rules, create without rules, update replaces rules, update clears rules, relationship titles |
| `tests/Feature/Gates/RulesGatesTest.php` | ~4 | rules.manage and rules.approve gate authorization |

### Test Case Inventory

**`ParentProxyAgreementTest.php`:**
- it records proxy_user_id when parent agrees on behalf of child
- it does not set proxy_user_id when user agrees for themselves
- it promotes Drifter child to Stowaway when parent agrees on their behalf
- it lifts rules_non_compliance brig when parent agrees on behalf of child
- it unagreedChildren returns only children who have not agreed
- it dashboard gate redirects parent with unagreed children to rules page
- it dashboard gate allows parent through when all children have agreed
- it proxy agreement does not cross-contaminate: agreeing for one child does not agree for another

**`CheckRulesAgreementJobTest.php`:**
- it sends a reminder to users overdue 14+ days who have not had a reminder
- it does not send a reminder if already sent
- it does not send a reminder if overdue less than 14 days
- it places a user in rules brig when overdue 28+ days
- it does not double-brig a user already in rules_non_compliance brig
- it does not act on users who have already agreed
- it does not act on Drifter-level users

**`ComplianceAndHistoryTest.php`:**
- it compliance query returns users who have not agreed to the current version
- it compliance query excludes Drifter users
- it agreement history returns all user_rule_agreements records

**`DashboardGateTest.php`:**
- it redirects unagreed users to rules page
- it allows agreed users through to dashboard
- it rules page is accessible without agreement

**`DisciplineReportRuleLinkingTest.php`:**
- it creates a discipline report with violated rules
- it creates a discipline report with no violated rules when none provided
- it updates a discipline report with new violated rules
- it clears violated rules when empty array passed on update
- it violatedRules relationship returns rules with titles

**`AgreeToRulesVersionTest.php`:**
- it records a user_rule_agreements entry for the current version
- it is idempotent (safe to call twice)
- it promotes a Drifter user to Stowaway
- it auto-lifts rules_non_compliance brig on agreement
- it sets rules_accepted_at and rules_accepted_by_user_id
- it does nothing when no published version exists

**`GetRulesAgreementStatusTest.php`:**
- it returns has_agreed false for unagreed user
- it returns has_agreed true for agreed user
- it classifies rules as new when user has never agreed
- it classifies rules as unchanged when user previously agreed to same rule
- it classifies rules as updated when rule supersedes one user agreed to
- it returns previous_rule for updated rules

### Coverage Gaps

- No Livewire component tests for `rules-page.blade.php` (the proxy agreement flow in particular)
- No Livewire component tests for `admin-manage-rules-page.blade.php` (complex multi-step admin workflow)
- No test for `RulesVersionPublishedNotification` content/delivery
- No end-to-end test for the full version lifecycle (create draft → add rules → submit → approve → publish → users notified)
- No test verifying the rules compliance list UI correctly computes days-overdue
- Brig placement in `CheckRulesAgreementJob` only verifies `isInBrig()` — no test verifies the specific `brig_type` on the newly created brig entry

---

## 17. File Map

**Models:**
- `app/Models/Rule.php`
- `app/Models/RuleVersion.php`
- `app/Models/RuleCategory.php`
- `app/Models/UserRuleAgreement.php`
- `app/Models/User.php` (additions: ruleAgreements, hasAgreedToCurrentRules, unagreedChildren)
- `app/Models/DisciplineReport.php` (addition: violatedRules)

**Enums:**
- `app/Enums/BrigType.php` (RulesNonCompliance case)

**Actions:**
- `app/Actions/AgreeToRulesVersion.php`
- `app/Actions/AgreeToRules.php` (legacy)
- `app/Actions/GetRulesAgreementStatus.php`
- `app/Actions/CreateRuleVersion.php`
- `app/Actions/AddRuleToDraft.php`
- `app/Actions/UpdateRuleInDraft.php`
- `app/Actions/DeactivateRuleInDraft.php`
- `app/Actions/SubmitVersionForApproval.php`
- `app/Actions/ApproveAndPublishVersion.php`
- `app/Actions/RejectDraftVersion.php`
- `app/Actions/PutUserInRulesBrig.php`
- `app/Actions/SendRulesAgreementReminder.php`
- `app/Actions/UpdateRulesHeaderFooter.php`

**Policies:** None specific to this feature.

**Gates:** `app/Providers/AuthServiceProvider.php` — gates: `rules.manage`, `rules.approve`

**Middleware:**
- `app/Http/Middleware/EnsureRulesAgreed.php`
- `bootstrap/app.php` (alias registration: `ensure-rules-agreed`)

**Notifications:**
- `app/Notifications/RulesAgreementReminderNotification.php`
- `app/Notifications/RulesVersionPublishedNotification.php`

**Jobs:**
- `app/Jobs/CheckRulesAgreementJob.php`

**Services:** Uses `app/Services/TicketNotificationService.php` (shared service)

**Volt Components:**
- `resources/views/livewire/rules-page.blade.php`
- `resources/views/livewire/admin-manage-rules-page.blade.php`
- `resources/views/livewire/rules-compliance-list.blade.php`
- `resources/views/livewire/rules-agreement-history.blade.php`
- `resources/views/livewire/rules-version-history.blade.php`
- `resources/views/livewire/dashboard/view-rules.blade.php`
- `resources/views/livewire/users/discipline-reports-card.blade.php` (Rules Violated in create/edit/view modals)
- `resources/views/livewire/reports/view-report.blade.php` (Rules Violated display)

**Routes:**
- `/rules` → `rules.show` (in `routes/web.php`)
- `/dashboard` → uses `ensure-rules-agreed` middleware

**Migrations:**
- `database/migrations/2025_08_05_040555_update_users_add_rules_acceptance_field.php`
- `database/migrations/2026_03_31_000001_add_rules_accepted_by_user_id_to_users.php`
- `database/migrations/2026_04_09_000001_create_rules_tables.php`
- `database/migrations/2026_04_09_000002_seed_rules_data.php`
- `database/migrations/2026_04_09_000003_seed_rules_permission_roles.php`
- `database/migrations/2026_04_09_000004_add_deactivate_on_publish_to_rule_version_rules.php`
- `database/migrations/2026_04_09_000005_add_sort_order_to_rules.php`
- `database/migrations/2026_04_09_000006_add_proxy_user_id_to_user_rule_agreements.php`

**Console Commands:** None (uses job scheduling directly in `routes/console.php`)

**Tests:**
- `tests/Feature/Rules/RulesSchemaTest.php`
- `tests/Feature/Rules/DashboardGateTest.php`
- `tests/Feature/Rules/ComplianceAndHistoryTest.php`
- `tests/Feature/Rules/ParentProxyAgreementTest.php`
- `tests/Feature/Actions/Rules/CreateRuleVersionTest.php`
- `tests/Feature/Actions/Rules/AddRuleToDraftTest.php`
- `tests/Feature/Actions/Rules/UpdateRuleInDraftTest.php`
- `tests/Feature/Actions/Rules/DeactivateRuleInDraftTest.php`
- `tests/Feature/Actions/Rules/AgreeToRulesVersionTest.php`
- `tests/Feature/Actions/Rules/GetRulesAgreementStatusTest.php`
- `tests/Feature/Actions/Rules/VersionApprovalTest.php`
- `tests/Feature/Actions/Rules/UpdateRulesHeaderFooterTest.php`
- `tests/Feature/Actions/Rules/ReorderRulesTest.php`
- `tests/Feature/Jobs/CheckRulesAgreementJobTest.php`
- `tests/Feature/Actions/DisciplineReports/DisciplineReportRuleLinkingTest.php`
- `tests/Feature/Gates/RulesGatesTest.php`

**Config:** `SiteConfig` keys: `rules_header`, `rules_footer`

---

## 18. Known Issues & Improvement Opportunities

1. **`EnsureRulesAgreed` N+1 query risk**: `$user->unagreedChildren()` calls `RuleVersion::currentPublished()` and does a `whereNotIn` subquery on every request that passes the middleware. For a parent with many children this is manageable, but caching the published version ID would reduce repeated queries.

2. **`PutUserInRulesBrig` admin user fallback**: When placing a user in the rules brig, the action looks up `User::where('email', 'system@lighthouse.local')->first()` as the acting admin. If that account is deleted, it falls back to `$user` itself (the person being brigned). This is a fragile dependency; a dedicated system account lookup should be centralized.

3. **No validation that `ruleIds` belong to active rules**: In `CreateDisciplineReport` and `UpdateDisciplineReport`, the `array $ruleIds` parameter is synced directly without validating that the IDs correspond to active rules. A deactivated or draft rule could theoretically be linked to a report.

4. **Legacy `AgreeToRules` action**: The old `AgreeToRules` action still exists and only handles Drifter users. It is no longer called by the new rules page flow. It may be called from legacy parent-portal code. It should be audited and either removed or updated to delegate to `AgreeToRulesVersion`.

5. **`CheckRulesAgreementJob` threshold vs. published_at**: The job computes "days since published" globally, not per-user. If a user was a Stowaway before the version was published and just joined, they all get the same 14-day/28-day window. This is intentional by design but may cause issues if users join the day before the brig threshold.

6. **Missing coverage for admin UI**: The `admin-manage-rules-page` component is complex (23+ properties, 15+ methods, 4 modals) but has no Livewire component test. A regression in the approval workflow would go undetected by automated tests.

7. **`rules_reminder_sent_at` not reset on new version**: When a new version is published, `rules_reminder_sent_at` is NOT cleared. This means if a user received a reminder for v1 but didn't agree, then v2 is published immediately after, the user won't receive a reminder for v2 until the 14-day threshold is met again — but the `rules_reminder_sent_at` check will prevent it if the old timestamp is recent. A `rules_reminder_sent_at` reset should be triggered when `ApproveAndPublishVersion` runs.

8. **Proxy agreement UI only shows if parent has already agreed**: The proxy agreement section on the rules page requires `$status['has_agreed'] === true`. A Drifter parent who hasn't agreed themselves cannot see the proxy section. By design, but worth noting for support scenarios.
