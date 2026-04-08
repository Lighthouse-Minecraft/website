# Double-Entry Accounting Ledger System -- Technical Documentation

> **Audience:** Project owner, developers, AI agents
> **Generated:** 2026-04-05
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

The Double-Entry Accounting Ledger System is a full GAAP-compliant accounting module built for the Lighthouse Minecraft community. It manages the organization's finances from transaction entry through monthly close, reporting, and community transparency. Every transaction is recorded as a journal entry with balanced debit and credit lines across two accounts — the core double-entry bookkeeping principle that guarantees the books always balance.

The system covers the complete accounting lifecycle: chart of accounts management, monthly fiscal period generation, journal entry creation (income, expense, transfer, manual, and reversing entries), bank reconciliation, GAAP period close with automatic closing entries, multi-dimensional reporting (Statement of Activities, General Ledger, Trial Balance, Balance Sheet, Cash Flow, Budget vs. Actual), restricted fund tracking, and public/community-facing dashboards.

All monetary values are stored as unsigned integers in cents to avoid floating-point precision errors. Fiscal years run October through September by default (configurable via `SiteConfig::finance_fy_start_month`), with FY N covering October of year N-1 through September of year N.

The feature has a four-tier access model: public guests see a high-level dashboard with aggregate totals; Resident+ members see closed period category summaries; Finance staff (View/Record/Manage roles) access full transaction detail, entry creation, reconciliation, and management tools; and Finance - Manage users additionally control accounts, vendors, tags, budgets, and restricted funds.

---

## 2. Database Schema

### `financial_accounts` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint | No | auto | Primary key |
| code | unsignedInteger | No | — | Unique account code (e.g. 1000, 4000) |
| name | string | No | — | Account name |
| type | enum | No | — | asset, liability, net_assets, revenue, expense |
| subtype | string | Yes | null | cash, donations, contributions, other, hosting, software, fees, professional, taxes, unrestricted, restricted |
| description | text | Yes | null | Optional description |
| normal_balance | enum | No | — | debit or credit |
| fund_type | enum | No | unrestricted | unrestricted or restricted |
| is_bank_account | boolean | No | false | True for bank/payment processor accounts |
| is_active | boolean | No | true | Soft-disable without deletion |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

**Indexes:** UNIQUE(`code`)
**Migration:** `database/migrations/2026_04_05_000001_create_financial_tables.php`

---

### `financial_restricted_funds` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint | No | auto | Primary key |
| name | string | No | — | Fund name (e.g. "Server Fund Drive 2025") |
| description | text | Yes | null | Purpose description |
| is_active | boolean | No | true | Soft-disable |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

**Migration:** `database/migrations/2026_04_05_000001_create_financial_tables.php`

---

### `financial_periods` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint | No | auto | Primary key |
| fiscal_year | unsignedSmallInteger | No | — | FY year (e.g. 2026) |
| month_number | unsignedTinyInteger | No | — | 1-12 (not calendar month number) |
| name | string | No | — | Human-readable (e.g. "October 2025") |
| start_date | date | No | — | First day of the period |
| end_date | date | No | — | Last day of the period |
| status | enum | No | open | open, reconciling, or closed |
| closed_at | timestamp | Yes | null | Timestamp of period close |
| closed_by_id | foreignId | Yes | null | User who closed the period |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

**Indexes:** UNIQUE(`fiscal_year`, `month_number`)
**Foreign Keys:** `closed_by_id` → `users.id` (nullOnDelete)
**Migration:** `database/migrations/2026_04_05_000001_create_financial_tables.php`

---

### `financial_vendors` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint | No | auto | Primary key |
| name | string | No | — | Vendor name |
| is_active | boolean | No | true | Soft-disable |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

**Migration:** `database/migrations/2026_04_05_000001_create_financial_tables.php`

---

### `financial_tags` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint | No | auto | Primary key |
| name | string | No | — | Tag name |
| color | string | No | zinc | Flux UI color name |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

**Migration:** `database/migrations/2026_04_05_000001_create_financial_tables.php`

---

### `financial_journal_entries` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint | No | auto | Primary key |
| period_id | foreignId | No | — | Fiscal period |
| date | date | No | — | Transaction date |
| description | string | No | — | Transaction description |
| reference | string | Yes | null | Optional reference code |
| entry_type | enum | No | — | income, expense, transfer, journal, closing |
| status | enum | No | draft | draft or posted |
| posted_at | timestamp | Yes | null | When posted |
| posted_by_id | foreignId | Yes | null | User who posted |
| reverses_entry_id | foreignId | Yes | null | ID of entry this reverses |
| donor_email | string | Yes | null | Donor email (income only) |
| vendor_id | foreignId | Yes | null | Vendor (expense/transfer) |
| restricted_fund_id | foreignId | Yes | null | Restricted fund designation |
| created_by_id | foreignId | Yes | null | Creating user |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

**Foreign Keys:**
- `period_id` → `financial_periods.id`
- `posted_by_id` → `users.id` (nullOnDelete)
- `reverses_entry_id` → `financial_journal_entries.id` (nullOnDelete, self-reference)
- `vendor_id` → `financial_vendors.id` (nullOnDelete)
- `restricted_fund_id` → `financial_restricted_funds.id` (nullOnDelete)
- `created_by_id` → `users.id` (nullOnDelete)

**Migration:** `database/migrations/2026_04_05_000001_create_financial_tables.php`

---

### `financial_journal_entry_lines` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint | No | auto | Primary key |
| journal_entry_id | foreignId | No | — | Parent entry |
| account_id | foreignId | No | — | Account being debited or credited |
| debit | unsignedInteger | No | 0 | Debit amount in cents |
| credit | unsignedInteger | No | 0 | Credit amount in cents |
| memo | string | Yes | null | Optional line memo |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

**Foreign Keys:**
- `journal_entry_id` → `financial_journal_entries.id` (cascadeOnDelete)
- `account_id` → `financial_accounts.id`

**Migration:** `database/migrations/2026_04_05_000001_create_financial_tables.php`

---

### `financial_journal_entry_tags` table (pivot)

| Column | Type | Notes |
|--------|------|-------|
| journal_entry_id | foreignId | cascadeOnDelete |
| tag_id | foreignId | cascadeOnDelete |

**Primary Key:** (`journal_entry_id`, `tag_id`)
**Migration:** `database/migrations/2026_04_05_000001_create_financial_tables.php`

---

### `financial_budgets` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint | No | auto | Primary key |
| account_id | foreignId | No | — | Account (revenue or expense) |
| period_id | foreignId | No | — | Fiscal period |
| amount | unsignedInteger | No | 0 | Budgeted amount in cents |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

**Indexes:** UNIQUE(`account_id`, `period_id`)
**Foreign Keys:**
- `account_id` → `financial_accounts.id`
- `period_id` → `financial_periods.id`

**Migration:** `database/migrations/2026_04_05_000001_create_financial_tables.php`

---

### `financial_reconciliations` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint | No | auto | Primary key |
| account_id | foreignId | No | — | Bank account being reconciled |
| period_id | foreignId | No | — | Fiscal period |
| statement_date | date | Yes | null | Bank statement date |
| statement_ending_balance | integer | No | 0 | Statement ending balance in cents |
| status | enum | No | in_progress | in_progress or completed |
| completed_at | timestamp | Yes | null | When reconciliation completed |
| completed_by_id | foreignId | Yes | null | User who completed |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

**Indexes:** UNIQUE(`account_id`, `period_id`)
**Foreign Keys:**
- `account_id` → `financial_accounts.id`
- `period_id` → `financial_periods.id`
- `completed_by_id` → `users.id` (nullOnDelete)

**Migration:** `database/migrations/2026_04_05_000001_create_financial_tables.php`

---

### `financial_reconciliation_lines` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint | No | auto | Primary key |
| reconciliation_id | foreignId | No | — | Parent reconciliation |
| journal_entry_line_id | foreignId | No | — | The line being marked cleared |
| cleared_at | timestamp | No | — | When the line was cleared |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

**Indexes:** UNIQUE(`reconciliation_id`, `journal_entry_line_id`)
**Foreign Keys:**
- `reconciliation_id` → `financial_reconciliations.id` (cascadeOnDelete)
- `journal_entry_line_id` → `financial_journal_entry_lines.id` (cascadeOnDelete)

**Migration:** `database/migrations/2026_04_05_000001_create_financial_tables.php`

---

### Seeded Standard Chart of Accounts

Seeded by `database/migrations/2026_04_05_000002_seed_financial_accounts.php` (safe to re-run):

| Code | Name | Type | Subtype | Normal Balance | Bank Account |
|------|------|------|---------|----------------|-------------|
| 1000 | Cash on Hand | asset | cash | debit | No |
| 1010 | Stripe Account | asset | cash | debit | **Yes** |
| 1020 | RelayFi Checking | asset | cash | debit | **Yes** |
| 1030 | RelayFi Savings | asset | cash | debit | **Yes** |
| 3000 | Net Assets — Unrestricted | net_assets | unrestricted | credit | No |
| 3100 | Net Assets — Restricted | net_assets | restricted | credit | No |
| 4000 | Donations — General | revenue | donations | credit | No |
| 4100 | Contributions — Leadership | revenue | contributions | credit | No |
| 4200 | Other Income | revenue | other | credit | No |
| 5000 | Minecraft Hosting | expense | hosting | debit | No |
| 5010 | Web Hosting | expense | hosting | debit | No |
| 5020 | Domain & Email | expense | hosting | debit | No |
| 5030 | Software Subscriptions | expense | software | debit | No |
| 5040 | Payment Processing Fees | expense | fees | debit | No |
| 5050 | Professional Fees | expense | professional | debit | No |
| 5060 | Taxes & Compliance | expense | taxes | debit | No |

---

## 3. Models & Relationships

### FinancialAccount (`app/Models/FinancialAccount.php`)

**Relationships:**

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `journalEntryLines()` | HasMany | FinancialJournalEntryLine | Lines using this account |
| `budgets()` | HasMany | FinancialBudget | Budget entries for this account |
| `reconciliations()` | HasMany | FinancialReconciliation | Bank reconciliations (bank accounts only) |

**Casts:**
- `is_bank_account` → `boolean`
- `is_active` → `boolean`

---

### FinancialJournalEntry (`app/Models/FinancialJournalEntry.php`)

**Relationships:**

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `period()` | BelongsTo | FinancialPeriod | The fiscal period |
| `postedBy()` | BelongsTo | User | User who posted the entry |
| `reversesEntry()` | BelongsTo | FinancialJournalEntry (self) | Entry this one reverses |
| `reversedBy()` | HasOne | FinancialJournalEntry (self) | Reversing entry (if any) |
| `vendor()` | BelongsTo | FinancialVendor | Vendor (expense/transfer only) |
| `restrictedFund()` | BelongsTo | FinancialRestrictedFund | Fund designation (optional) |
| `createdBy()` | BelongsTo | User | Creating user |
| `lines()` | HasMany | FinancialJournalEntryLine | Debit and credit lines |
| `tags()` | BelongsToMany | FinancialTag | Via `financial_journal_entry_tags` pivot |

**Casts:**
- `date` → `date`
- `posted_at` → `datetime`

---

### FinancialJournalEntryLine (`app/Models/FinancialJournalEntryLine.php`)

**Relationships:**

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `journalEntry()` | BelongsTo | FinancialJournalEntry | Parent entry |
| `account()` | BelongsTo | FinancialAccount | Account being debited/credited |
| `reconciliationLines()` | HasMany | FinancialReconciliationLine | Bank reconciliation clearings |

**Casts:**
- `debit` → `integer`
- `credit` → `integer`

---

### FinancialPeriod (`app/Models/FinancialPeriod.php`)

**Relationships:**

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `closedBy()` | BelongsTo | User | User who closed the period |
| `journalEntries()` | HasMany | FinancialJournalEntry | All entries in this period |
| `budgets()` | HasMany | FinancialBudget | Budget entries for this period |
| `reconciliations()` | HasMany | FinancialReconciliation | Bank reconciliations for this period |

**Casts:**
- `start_date` → `date`
- `end_date` → `date`
- `closed_at` → `datetime`

---

### FinancialBudget (`app/Models/FinancialBudget.php`)

**Relationships:**

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `account()` | BelongsTo | FinancialAccount | Budgeted account |
| `period()` | BelongsTo | FinancialPeriod | Budget period |

**Casts:**
- `amount` → `integer`

---

### FinancialTag (`app/Models/FinancialTag.php`)

**Relationships:**

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `journalEntries()` | BelongsToMany | FinancialJournalEntry | Via `financial_journal_entry_tags` |

---

### FinancialVendor (`app/Models/FinancialVendor.php`)

**Relationships:**

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `journalEntries()` | HasMany | FinancialJournalEntry | Entries against this vendor |

**Casts:**
- `is_active` → `boolean`

---

### FinancialRestrictedFund (`app/Models/FinancialRestrictedFund.php`)

**Relationships:**

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `journalEntries()` | HasMany | FinancialJournalEntry | Entries designated to this fund |

**Casts:**
- `is_active` → `boolean`

---

### FinancialReconciliation (`app/Models/FinancialReconciliation.php`)

**Relationships:**

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `account()` | BelongsTo | FinancialAccount | Bank account being reconciled |
| `period()` | BelongsTo | FinancialPeriod | Fiscal period |
| `completedBy()` | BelongsTo | User | User who completed reconciliation |
| `lines()` | HasMany | FinancialReconciliationLine | Cleared journal entry lines |

**Casts:**
- `statement_date` → `date`
- `statement_ending_balance` → `integer`
- `completed_at` → `datetime`

---

### FinancialReconciliationLine (`app/Models/FinancialReconciliationLine.php`)

**Relationships:**

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `reconciliation()` | BelongsTo | FinancialReconciliation | Parent reconciliation |
| `journalEntryLine()` | BelongsTo | FinancialJournalEntryLine | The cleared line |

**Casts:**
- `cleared_at` → `datetime`

---

## 4. Enums Reference

No PHP-backed Enums are used by this feature. Column enums are defined inline in migrations:

| Table | Column | Values |
|-------|--------|--------|
| financial_accounts | type | asset, liability, net_assets, revenue, expense |
| financial_accounts | normal_balance | debit, credit |
| financial_accounts | fund_type | unrestricted, restricted |
| financial_periods | status | open, reconciling, closed |
| financial_journal_entries | entry_type | income, expense, transfer, journal, closing |
| financial_journal_entries | status | draft, posted |
| financial_reconciliations | status | in_progress, completed |

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

| Gate Name | Who Can Pass | Logic Summary |
|-----------|-------------|---------------|
| `finance-view` | Finance - View, Finance - Record, or Finance - Manage role | Has any finance role |
| `finance-record` | Finance - Record or Finance - Manage role | Has record or manage role |
| `finance-manage` | Finance - Manage role only | Has manage role only |
| `finance-community-view` | Resident+ membership, not in brig | `!in_brig && isAtLeastLevel(Resident)` |

### Policies

No Eloquent Policies are used. Authorization is enforced exclusively via Gates.

### Permissions Matrix

| User Type | Public Dashboard | Community View | View Entries/Reports | Create/Post Entries | Reconcile/Close | Manage Accounts/Budgets |
|-----------|-----------------|----------------|----------------------|---------------------|-----------------|------------------------|
| Guest (unauthenticated) | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Traveler/Stowaway | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Resident+ (no finance role) | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Finance - View | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Finance - Record | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| Finance - Manage | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

---

## 6. Routes

| Method | URL | Middleware | Volt Component | Route Name |
|--------|-----|-----------|----------------|------------|
| GET | `/finance` | (none) | `finance.public-dashboard` | `finance.public.index` |
| GET | `/finance/overview` | auth, can:finance-community-view | `finance.community-finance` | `finance.community.index` |
| GET | `/finance/accounts` | auth, can:finance-view | `finance.chart-of-accounts` | `finance.accounts.index` |
| GET | `/finance/periods` | auth, can:finance-view | `finance.fiscal-periods` | `finance.periods.index` |
| GET | `/finance/vendors` | auth, can:finance-view | `finance.vendors` | `finance.vendors.index` |
| GET | `/finance/tags` | auth, can:finance-view | `finance.tags` | `finance.tags.index` |
| GET | `/finance/journal` | auth, can:finance-view | `finance.journal-entries` | `finance.journal.index` |
| GET | `/finance/journal/create` | auth, can:finance-view | `finance.create-journal-entry` | `finance.journal.create` |
| GET | `/finance/journal/create/manual` | auth, can:finance-view | `finance.create-manual-entry` | `finance.journal.create-manual` |
| GET | `/finance/budgets` | auth, can:finance-view | `finance.budgets` | `finance.budgets.index` |
| GET | `/finance/restricted-funds` | auth, can:finance-view | `finance.restricted-funds` | `finance.restricted-funds.index` |
| GET | `/finance/reconciliation/{accountId}/{periodId}` | auth, can:finance-view | `finance.bank-reconciliation` | `finance.reconciliation.show` |
| GET | `/finance/reports` | auth, can:finance-view | `finance.reports` | `finance.reports.index` |

---

## 7. User Interface Components

### Public Dashboard
**File:** `resources/views/livewire/finance/public-dashboard.blade.php`
**Route:** `/finance` (route name: `finance.public.index`)
**Authorization:** None (public)

**Purpose:** Public-facing finance summary visible to all visitors. Shows the last 3 closed fiscal periods with aggregate income and expense totals (no account names, no transaction detail). Shows a donation goal progress bar for the current open month, driven by budget amounts for `subtype = 'donations'` revenue accounts. Falls back to the legacy `SiteConfig::donation_goal` value if no budget is set.

**User Actions Available:** Read-only, no interactions.

**UI Elements:** Donation goal progress bar with percentage, 3-column grid of month cards (income / expenses / net).

---

### Community Finance View
**File:** `resources/views/livewire/finance/community-finance.blade.php`
**Route:** `/finance/overview` (route name: `finance.community.index`)
**Authorization:** `finance-community-view` gate (Resident+, not in brig)

**Purpose:** Read-only monthly summaries for Resident+ members. Shows all closed periods most-recent-first. Each period shows revenue by account, expenses by account, and net change. Restricted fund activity (received/spent/remaining) is shown per period where applicable. Finance - View+ users see a "Staff Finance Portal" link.

**User Actions Available:** Read-only, no interactions.

**Key Computed Methods:**
- `getClosedPeriodsProperty()`: All closed periods, newest first
- `getPeriodRevenueSummary(int $periodId)`: Revenue by account for a period
- `getPeriodExpenseSummary(int $periodId)`: Expenses by account for a period
- `getPeriodRestrictedFundSummary(int $periodId)`: Fund activity for a period

---

### Chart of Accounts
**File:** `resources/views/livewire/finance/chart-of-accounts.blade.php`
**Route:** `/finance/accounts` (route name: `finance.accounts.index`)
**Authorization:** `finance-view` (view), `finance-manage` (modify)

**Purpose:** Full management of the chart of accounts. Accounts grouped by type. Finance - Manage users can add new accounts, edit names/subtype/description/fund type, and deactivate/reactivate accounts.

**User Actions Available:**
- Add account → `addAccount()` → creates `FinancialAccount`
- Edit account → `openEdit()` / `saveEdit()` → updates `FinancialAccount`
- Deactivate → `deactivate()` → sets `is_active = false`
- Reactivate → `reactivate()` → sets `is_active = true`

---

### Fiscal Periods
**File:** `resources/views/livewire/finance/fiscal-periods.blade.php`
**Route:** `/finance/periods` (route name: `finance.periods.index`)
**Authorization:** `finance-view` (view), `finance-record` (close periods)

**Purpose:** View and manage fiscal periods. Automatically generates current FY periods on mount. Current FY shown expanded with period status, reconciliation status per bank account (with links to the reconciliation page), and a "Close Period" button for Finance - Record users. Prior FYs shown in a collapsed section.

**User Actions Available:**
- Close Period → `closePeriod(int $periodId)` → calls `CloseFinancialPeriod::run()`
- Links to bank reconciliation page per account per period

**Key Computed Properties:**
- `currentFyYear`: Integer FY year based on SiteConfig start month
- `currentFyPeriods`: Periods for current FY
- `priorFyPeriods`: Prior FY periods grouped by year
- `bankAccounts`: Active bank accounts
- `reconciliationStatus`: Array keyed `"{account_id}_{period_id}"` with reconciliation records

---

### Journal Entries List
**File:** `resources/views/livewire/finance/journal-entries.blade.php`
**Route:** `/finance/journal` (route name: `finance.journal.index`)
**Authorization:** `finance-view` (view), `finance-record` (reverse)

**Purpose:** Paginated list of all journal entries. Filterable by date range, entry type, account, vendor, and tag. Posted entries without an existing reversing entry show a "Reverse" button. Shows "Reverses #ID" and "Reversed by #ID" links for related entries.

**User Actions Available:**
- Filter entries → updates filter properties → re-queries
- Clear filters → `clearFilters()`
- Reverse entry → `reverse(int $entryId)` → calls `CreateReversingEntry::run()`

---

### Create Journal Entry
**File:** `resources/views/livewire/finance/create-journal-entry.blade.php`
**Route:** `/finance/journal/create` (route name: `finance.journal.create`)
**Authorization:** `finance-record`

**Purpose:** Guided form for creating income, expense, or transfer entries. Three tabs (income/expense/transfer) with type-specific fields. Includes vendor search modal, tag search modal, restricted fund selector, and donor email (income only). Validates required fields before showing a preview with projected debit/credit lines. Finance - Record users can save as draft or post directly from preview.

**User Actions Available:**
- Switch entry type tab → updates `entryType`
- Select vendor → `onVendorSelected()` (via vendor-search-modal dispatch)
- Select/remove tag → `onTagSelected()` / `removeTag()`
- Preview → `preview()` → validates and shows debit/credit preview
- Save as Draft → `save('draft')`
- Post → `save('posted')`

**Key Logic:**
- `resolvePeriod()`: Finds fiscal period matching the entry date (throws if closed)
- `buildPreviewLines()`: Shows projected double-entry lines before saving
- Income: Dr bank / Cr revenue; Expense: Dr expense / Cr bank; Transfer: Dr to-account / Cr from-account

---

### Create Manual Entry
**File:** `resources/views/livewire/finance/create-manual-entry.blade.php`
**Route:** `/finance/journal/create/manual` (route name: `finance.journal.create-manual`)
**Authorization:** `finance-record`

**Purpose:** N-line manual journal entry form for complex entries not fitting income/expense/transfer. Users add lines with account, side (debit/credit), amount, and memo. Running totals show total debits and credits with a balance indicator. "Post Entry" button disabled unless balanced (debits = credits). Entries can be saved as draft or posted.

**User Actions Available:**
- Add line → appends empty line to `lines` array
- Remove line → removes line from array
- Save as draft → `save('draft')`
- Post → `save('posted')` (blocked if unbalanced)

---

### Budget Management
**File:** `resources/views/livewire/finance/budgets.blade.php`
**Route:** `/finance/budgets` (route name: `finance.budgets.index`)
**Authorization:** `finance-view` (view), `finance-manage` (edit)

**Purpose:** Two-tab interface. "Budget Entry" tab: inline editable grid of revenue/expense accounts × 12 monthly periods. Users type amounts and cells save on change (`wire:change`). FY total column. "Budget vs. Actual" tab: same grid with budget, actual posted amounts, and variance columns per month (green = favorable, red = unfavorable). Finance - Manage users can trigger "Copy from Prior FY" to populate all budgets from the previous year's values.

**User Actions Available:**
- Edit budget cell → `updateBudget(accountId, periodId, amount)` → updates/creates `FinancialBudget`
- Copy from prior FY → `copyPriorYear()` → calls `CopyPriorYearBudgets::run()`

---

### Bank Reconciliation
**File:** `resources/views/livewire/finance/bank-reconciliation.blade.php`
**Route:** `/finance/reconciliation/{accountId}/{periodId}` (route name: `finance.reconciliation.show`)
**Authorization:** `finance-record`

**Purpose:** Reconcile a specific bank account for a specific fiscal period. Shows two columns: Uncleared Items and Cleared Items. Finance - Record users click "Clear" to move lines to the cleared column, "Unclear" to move them back. Statement ending balance entry at top. Running summary shows: Statement Balance, Cleared Balance, Difference. "Complete Reconciliation" button enabled only when Difference = 0 and statement balance > 0.

**User Actions Available:**
- Enter statement balance → `updateStatementBalance()`
- Mark line cleared → `markCleared(int $lineId)`
- Unmark cleared → `unmarkCleared(int $reconciliationLineId)`
- Complete reconciliation → `complete()` → calls `CompleteReconciliation::run()`

**Key Computed Properties:**
- `unclearedLines`: Posted lines for this account/period not yet cleared
- `clearedLines`: Lines already cleared in this reconciliation
- `clearedBalance`: Sum of (debit - credit) for cleared lines
- `statementBalanceCents`: Statement ending balance parsed to cents
- `difference`: Statement balance minus cleared balance
- `isBalanced`: True when statement balance > 0 and difference = 0

---

### Financial Reports
**File:** `resources/views/livewire/finance/reports.blade.php`
**Route:** `/finance/reports` (route name: `finance.reports.index`)
**Authorization:** `finance-view`

**Purpose:** Six-tab report interface. All reports use posted entries only (drafts excluded). Shared filters (FY year, period, date range) apply to Activities, Trial Balance, and Cash Flow tabs. Print/PDF export via browser print (`window.print()`).

**Report Tabs:**

1. **Statement of Activities** — Revenue by account, expense by account, net change. Filterable by FY/period/date range. Excludes closing entries.
2. **General Ledger** — All posted lines for a selected account with running balance. Filterable by account, date range, and entry type. CSV export via `exportGlCsv()`.
3. **Trial Balance** — All accounts with total debit and credit columns. Balanced indicator. Filterable by FY/period/date range.
4. **Balance Sheet** — Cumulative asset account balances as of a selected date, Net Assets split unrestricted/restricted. Assets = Net Assets check.
5. **Cash Flow** — Cash inflows (revenue) and outflows (expenses) for selected period. Net change in cash. Uses same data as Activities.
6. **Budget vs. Actual** — Budget, actual, and variance per account per month for a selected FY. Color-coded (green = favorable, red = unfavorable).

**User Actions Available:**
- Switch tabs
- Adjust filter controls
- Export General Ledger to CSV → `exportGlCsv()` → `StreamedResponse`
- Print/PDF → JavaScript `window.print()`

---

### Vendors Management
**File:** `resources/views/livewire/finance/vendors.blade.php`
**Route:** `/finance/vendors` (route name: `finance.vendors.index`)
**Authorization:** `finance-view` (view), `finance-manage` (modify)

**Purpose:** Manage vendor list. Finance - Manage users can create, rename, deactivate, and reactivate vendors.

---

### Tags Management
**File:** `resources/views/livewire/finance/tags.blade.php`
**Route:** `/finance/tags` (route name: `finance.tags.index`)
**Authorization:** `finance-view` (view), `finance-manage` (modify)

**Purpose:** Manage transaction tags with color coding. Finance - Manage users can create, edit, and delete tags (only if unused by journal entries).

---

### Vendor Search Modal
**File:** `resources/views/livewire/finance/vendor-search-modal.blade.php`
**Purpose:** Inline modal for searching/selecting a vendor or creating a new one. Dispatches `vendor-selected` event with `vendorId` and `vendorName`. Must have a root `<div>` wrapper (Livewire requirement).

---

### Tag Search Modal
**File:** `resources/views/livewire/finance/tag-search-modal.blade.php`
**Purpose:** Inline modal for searching/selecting tags or creating new ones. Excludes already-selected tags. Dispatches `tag-selected` event. Must have a root `<div>` wrapper (Livewire requirement).

---

### Restricted Funds Management
**File:** `resources/views/livewire/finance/restricted-funds.blade.php`
**Route:** `/finance/restricted-funds` (route name: `finance.restricted-funds.index`)
**Authorization:** `finance-view` (view), `finance-manage` (modify)

**Purpose:** Manage restricted funds. Finance - Manage users can create, edit name/description, deactivate, and reactivate funds. Each active fund shows a summary card with received (income entries), spent (expense entries), and remaining balance calculated from posted entries only.

---

## 8. Actions (Business Logic)

### ParseDollarAmount (`app/Actions/ParseDollarAmount.php`)

**Signature:** `handle(string $input): int`

**Step-by-step logic:**
1. Trims input whitespace
2. Validates against regex `^\d+(\.\d{0,2})?$` — must be non-negative, max 2 decimal places
3. Throws `\InvalidArgumentException` if invalid
4. Returns `(int) round((float) $input * 100)` — integer cents

**Called by:** `create-journal-entry.blade.php`, `bank-reconciliation.blade.php`, `budgets.blade.php`

---

### CreateJournalEntry (`app/Actions/CreateJournalEntry.php`)

**Signature:** `handle(User $user, string $type, int $periodId, string $date, string $description, int $amountCents, int $primaryAccountId, int $bankAccountId, string $status = 'draft', ?string $donorEmail = null, ?int $vendorId = null, ?int $restrictedFundId = null, array $tagIds = [], ?string $reference = null): FinancialJournalEntry`

**Step-by-step logic:**
1. Loads period via `findOrFail($periodId)`, throws `\RuntimeException` if period is closed
2. Determines debit/credit account pair based on type:
   - `income`: debit = bank, credit = primary (revenue)
   - `expense`: debit = primary (expense), credit = bank
   - `transfer`: debit = bank (to-account), credit = primary (from-account)
3. Creates `FinancialJournalEntry` with all fields; sets `posted_at`/`posted_by_id` if status = posted
4. Creates two `FinancialJournalEntryLine` records (debit line and credit line)
5. Syncs tags via `$entry->tags()->sync($tagIds)` if tagIds provided

**Called by:** `create-journal-entry.blade.php`, `restricted-funds.blade.php` tests, test helpers

---

### PostJournalEntry (`app/Actions/PostJournalEntry.php`)

**Signature:** `handle(User $user, FinancialJournalEntry $entry): void`

**Step-by-step logic:**
1. Throws `\RuntimeException` if `$entry->status === 'posted'`
2. Throws `\RuntimeException` if `$entry->period->status === 'closed'`
3. Sums all line debits and credits; throws `\RuntimeException` if not equal
4. Updates entry: `status = 'posted'`, `posted_at = now()`, `posted_by_id = $user->id`

**Called by:** `create-manual-entry.blade.php`

---

### CreateReversingEntry (`app/Actions/CreateReversingEntry.php`)

**Signature:** `handle(User $user, FinancialJournalEntry $entry): FinancialJournalEntry`

**Step-by-step logic:**
1. Throws `\RuntimeException` if entry status is not `posted`
2. Finds an open (non-closed) fiscal period containing today; falls back to entry's own period
3. Creates new `FinancialJournalEntry` as draft with:
   - `reverses_entry_id = $entry->id`
   - `description = "Reversing: {$entry->description}"`
   - Same entry_type, donor_email, vendor_id, restricted_fund_id
4. Creates inverted lines: each original line's debit becomes credit and vice versa
5. Returns the new reversing entry

**Called by:** `journal-entries.blade.php` → `reverse()` method

---

### GenerateFinancialPeriods (`app/Actions/GenerateFinancialPeriods.php`)

**Signature:** `handle(int $fyYear, int $startMonth): void`
**Static helper:** `generateForCurrentFY(): void`

**Step-by-step logic:**
1. Iterates 12 month slots (month_number 1–12)
2. Calculates calendar month and year for each slot:
   - If `startMonth = 1`: FY year = calendar year (Jan–Dec)
   - Otherwise: Slot 1 starts on `startMonth` of `fyYear - 1`, wrapping into `fyYear`
3. Uses `firstOrCreate` on `[fiscal_year, month_number]` to avoid duplicates
4. Sets name to "MonthName Year" (e.g. "October 2025")

**Static `generateForCurrentFY()`:**
- Reads `SiteConfig::finance_fy_start_month` (default 10)
- Determines current FY year: if `now()->month >= startMonth`, year = `now()->year + 1`, else `now()->year`
- Calls `self::run($fyYear, $startMonth)`

**Called by:** `fiscal-periods.blade.php` → `mount()`, `budgets.blade.php` → `ensurePeriodsExist()`

---

### CompleteReconciliation (`app/Actions/CompleteReconciliation.php`)

**Signature:** `handle(FinancialReconciliation $reconciliation, User $user): void`

**Step-by-step logic:**
1. Throws `\RuntimeException` if `$reconciliation->status === 'completed'`
2. Queries cleared balance: `SUM(jel.debit) - SUM(jel.credit)` across all reconciliation lines
3. Calculates difference: `statement_ending_balance - clearedBalance`
4. Throws `\RuntimeException` if difference ≠ 0
5. Updates reconciliation: `status = 'completed'`, `completed_at = now()`, `completed_by_id = $user->id`
6. Logs activity: `RecordActivity::run($reconciliation->account, 'reconciliation_completed', "Bank reconciliation completed for {$account->name} — {$period->name} by {$user->name}.")`

**Called by:** `bank-reconciliation.blade.php` → `complete()`

---

### CloseFinancialPeriod (`app/Actions/CloseFinancialPeriod.php`)

**Signature:** `handle(FinancialPeriod $period, User $user): void`

**Step-by-step logic:**
1. Throws `\RuntimeException` if period is already closed
2. Loads all active bank accounts; checks that each has a `completed` reconciliation for this period
3. Throws `\RuntimeException` listing any accounts missing completed reconciliation
4. Loads Net Assets — Unrestricted account (`subtype = 'unrestricted'`); throws if not found
5. Loads Net Assets — Restricted account (`subtype = 'restricted'`)
6. Calculates posted account balances for revenue and expense accounts, split by restricted/unrestricted
7. Within a DB transaction:
   a. Generates revenue closing entry: Dr each revenue account (its net credit balance), Cr Net Assets — Unrestricted (unrestricted portion) and/or Cr Net Assets — Restricted (restricted portion). Skipped if all balances are zero.
   b. Generates expense closing entry: Dr Net Assets — Unrestricted (total expense balance), Cr each expense account. Skipped if all balances are zero.
   c. Updates period: `status = 'closed'`, `closed_at = now()`, `closed_by_id = $user->id`
8. After transaction: `RecordActivity::run($period, 'period_closed', "Period {$period->name} closed by {$user->name}.")`

**Called by:** `fiscal-periods.blade.php` → `closePeriod()`

---

### CopyPriorYearBudgets (`app/Actions/CopyPriorYearBudgets.php`)

**Signature:** `handle(int $fromFyYear, int $toFyYear): int`

**Step-by-step logic:**
1. Loads all periods for `fromFyYear`, keyed by `month_number`
2. Loads all periods for `toFyYear`, keyed by `month_number`
3. For each budget in `fromFyYear`: finds matching `month_number` in `toFyYear`; creates or updates budget using `updateOrCreate(['account_id', 'period_id'], ['amount'])`
4. Returns count of budgets copied/updated (0 if no budgets in prior year)

**Called by:** `budgets.blade.php` → `copyPriorYear()`

---

## 9. Notifications

Not applicable for this feature. No notifications are sent by the accounting system.

---

## 10. Background Jobs

Not applicable for this feature. All accounting operations are synchronous.

---

## 11. Console Commands & Scheduled Tasks

Not applicable for this feature. Period generation is triggered on page mount, not scheduled.

---

## 12. Services

Not applicable for this feature.

---

## 13. Activity Log Entries

| Action String | Logged By | Subject Model | Description Template |
|---------------|-----------|---------------|---------------------|
| `reconciliation_completed` | `CompleteReconciliation` | `FinancialAccount` | "Bank reconciliation completed for {account.name} — {period.name} by {user.name}." |
| `period_closed` | `CloseFinancialPeriod` | `FinancialPeriod` | "Period {period.name} closed by {user.name}." |

---

## 14. Data Flow Diagrams

### Creating an Income Journal Entry

```
Finance - Record user fills out income form at /finance/journal/create
  -> POST (Livewire) → create-journal-entry::save('draft' or 'posted')
    -> $this->authorize('finance-record')
    -> $this->validateForm() — validates type-specific required fields
    -> $period = $this->resolvePeriod() — finds period matching date; throws if closed
    -> ParseDollarAmount::run($this->amount) — converts '$50.00' to 5000 cents
    -> CreateJournalEntry::run(
         user: auth()->user(),
         type: 'income',
         periodId: $period->id,
         date: $this->date,
         description: $this->description,
         amountCents: 5000,
         primaryAccountId: $this->revenueAccountId,
         bankAccountId: $this->bankAccountId,
         status: 'draft',           // or 'posted'
         donorEmail: $this->donorEmail,
         restrictedFundId: $this->restrictedFundId,
         tagIds: $this->tagIds,
       )
         -> Validates period not closed
         -> Creates FinancialJournalEntry (status: draft or posted)
         -> Creates line: debit bank $50.00 (5000 cents)
         -> Creates line: credit revenue $50.00 (5000 cents)
         -> Syncs tags
    -> Flux::toast('Entry saved.', variant: 'success')
    -> Redirect to finance.journal.index
```

---

### Bank Reconciliation Workflow

```
Finance - Record user opens /finance/reconciliation/{accountId}/{periodId}
  -> GET → bank-reconciliation::mount(accountId, periodId)
    -> $this->authorize('finance-record')
    -> Validates account is a bank account (abort 403 if not)
    -> FinancialReconciliation::firstOrCreate([account_id, period_id], defaults)
    -> Loads statement balance from existing reconciliation if > 0

User enters statement balance
  -> wire:change → updateStatementBalance()
    -> ParseDollarAmount::run($this->statementBalance) → cents
    -> $this->reconciliation->update(['statement_ending_balance' => cents])

User clicks "Clear" on an uncleared line
  -> wire:click → markCleared(int $lineId)
    -> $this->authorize('finance-record')
    -> $this->reconciliation->lines()->firstOrCreate(['journal_entry_line_id' => $lineId])

User clicks "Complete Reconciliation" (only visible when isBalanced = true)
  -> wire:click → complete()
    -> $this->authorize('finance-record')
    -> updateStatementBalance()
    -> CompleteReconciliation::run($reconciliation->fresh(), auth()->user())
         -> Validates not already completed
         -> SELECT SUM(debit) - SUM(credit) from cleared lines
         -> Validates difference = 0
         -> Updates reconciliation: status=completed, completed_at, completed_by_id
         -> RecordActivity::run(account, 'reconciliation_completed', ...)
    -> Flux::toast('Reconciliation completed successfully.', 'Done', variant: 'success')
```

---

### Period Close Workflow

```
Finance - Record user clicks "Close Period" on /finance/periods
  -> wire:confirm dialog shown
  -> User confirms
  -> wire:click → fiscal-periods::closePeriod(periodId)
    -> $this->authorize('finance-record')
    -> CloseFinancialPeriod::run($period, auth()->user())
         -> Validates period not already closed
         -> Checks all bank accounts have completed reconciliations
         -> Calculates revenue balances (unrestricted + restricted) per account
         -> Calculates expense balances per account
         -> DB::transaction {
              -> Creates "closing" journal entry for revenues:
                   Dr each revenue account (net credit balance)
                   Cr Net Assets — Unrestricted (unrestricted revenue total)
                   Cr Net Assets — Restricted (restricted revenue total, if any)
              -> Creates "closing" journal entry for expenses:
                   Dr Net Assets — Unrestricted (total expense balance)
                   Cr each expense account (its net debit balance)
              -> period->update(status=closed, closed_at, closed_by_id)
           }
         -> RecordActivity::run($period, 'period_closed', ...)
    -> Flux::toast("Period {name} has been closed.", 'Period Closed', variant: 'success')
```

---

### General Ledger CSV Export

```
Finance - View user on /finance/reports (General Ledger tab)
  -> Selects account from dropdown → glAccountId updated (wire.live)
  -> Clicks "Export CSV"
    -> wire:click → reports::exportGlCsv()
      -> $this->authorize('finance-view')
      -> Queries all posted lines for selected account with optional date/type filters
      -> Computes running balance (debit - credit accumulation)
      -> response()->streamDownload(function() use ($lines) {
           -> fopen('php://output', 'w')
           -> fputcsv(handle, headers)
           -> foreach line: fputcsv(handle, [date, description, vendor, type, debit, credit, running_balance])
           -> fclose(handle)
         }, 'general-ledger-{code}-{date}.csv', ['Content-Type' => 'text/csv'])
```

---

## 15. Configuration

| Key | Location | Default | Purpose |
|-----|----------|---------|---------|
| `finance_fy_start_month` | `SiteConfig` table | `'10'` | Month number (1-12) when the fiscal year begins. October = 10. |
| `donation_goal` | `SiteConfig` table | `config('lighthouse.donation_goal', 60)` | Legacy fallback donation goal in **dollars** (not cents) when no budget is set for donation accounts. |
| `donation_current_month_name` | `SiteConfig` table | — | Legacy donation display (pre-accounting system). Not used by the new accounting system. |
| `donation_current_month_amount` | `SiteConfig` table | — | Legacy donation display. Not used by the new accounting system. |

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/Finance/FinanceGatesTest.php` | 15 | Gate authorization for finance-view, finance-record, finance-manage; route access protection |
| `tests/Feature/Finance/JournalEntryTest.php` | 25 | ParseDollarAmount parsing; CreateJournalEntry with income/expense/transfer/tags/donorEmail; PostJournalEntry validation; journal list access and filters; create form |
| `tests/Feature/Finance/BankReconciliationTest.php` | 12 | Page access; CompleteReconciliation action; mark/unmark cleared; statement balance; complete via component; reconciliation status on periods page |
| `tests/Feature/Finance/PeriodCloseTest.php` | 9 | Already closed rejection; missing reconciliation rejection; in_progress reconciliation rejection; successful close; balanced revenue closing entry; balanced expense closing entry; period lock; UI access |
| `tests/Feature/Finance/RestrictedFundsTest.php` | 13 | Page access; fund CRUD; validation; fund summaries (received/spent/remaining); draft exclusion; restricted fund on journal entry |
| `tests/Feature/Finance/BudgetManagementTest.php` | 13 | Page access; budget set/update; amount stored as cents; FY rollup; CopyPriorYearBudgets; trigger copy via component; variance report actuals; draft exclusion in variance |
| `tests/Feature/Finance/ChartOfAccountsTest.php` | 12 | Seeded accounts; type coverage; bank accounts; page access; grouped display; add/deactivate/reactivate by role; validation |
| `tests/Feature/Finance/FiscalPeriodsTest.php` | 12 | Period generation (12 per FY); October start date ranges; all 12 months; no duplicates; January start; all open; generateForCurrentFY SiteConfig; page access; auto-generate on mount; badges; closed period display |
| `tests/Feature/Finance/ManualEntryTest.php` | 12 | CreateReversingEntry (inverted lines, reverses_entry_id, draft rejection); reversedBy relationship; page access; validation; balanced/unbalanced save; post; reverse button and access control |
| `tests/Feature/Finance/VendorTagManagementTest.php` | 22 | Vendor page access/CRUD; tag CRUD; vendor search modal; tag search modal; create-and-dispatch events |
| `tests/Feature/Finance/ReportsTest.php` | 13 | Reports page access; Statement of Activities revenue/expense totals and net change; draft exclusion; General Ledger lines and running balance; draft exclusion; CSV export; Trial Balance balanced check; draft exclusion |
| `tests/Feature/Finance/BalanceSheetCashFlowTest.php` | 5 | Balance Sheet asset balance; unrestricted/restricted net assets properties; draft exclusion; Cash Flow net change; draft exclusion |
| `tests/Feature/Finance/VarianceReportTest.php` | 5 | Under-budget expense variance; over-budget revenue variance; no-budget account; FY rollup period inclusion; draft exclusion |
| `tests/Feature/Finance/CommunityFinanceTest.php` | 10 | Resident access; Traveler/Stowaway rejection; unauthenticated rejection; closed-only periods; revenue grouping; expense grouping; restricted fund summary; Finance - View sees staff link; resident without finance role does not see link |
| `tests/Feature/Finance/PublicDashboardTest.php` | 9 | Guest access; authenticated user access; 3-period limit; open periods excluded; graceful empty state; income totals; donation goal from budget; progress tracking; percent capped at 100 |

**Total: 187 tests**

### Test Case Inventory

**FinanceGatesTest.php:**
- grants finance-view to user with Finance - View role
- grants finance-view to user with Finance - Record role
- grants finance-view to user with Finance - Manage role
- denies finance-view to user with no finance role
- grants finance-record to user with Finance - Record role
- grants finance-record to user with Finance - Manage role
- denies finance-record to user with only Finance - View role
- denies finance-record to user with no finance role
- grants finance-manage to user with Finance - Manage role
- denies finance-manage to user with only Finance - Record role
- denies finance-manage to user with only Finance - View role
- denies finance-manage to user with no finance role
- denies unauthenticated user access to finance routes
- denies user without finance role access to finance routes
- allows Finance - View user access to finance routes

**JournalEntryTest.php:**
- parses whole dollar amount
- parses amount with one decimal
- parses amount with two decimals
- parses sub-dollar amount
- rejects non-numeric input
- rejects negative input
- CreateJournalEntry creates income entry with correct debit/credit lines
- CreateJournalEntry creates expense entry with correct debit/credit lines
- CreateJournalEntry creates transfer entry with correct debit/credit lines
- CreateJournalEntry attaches tags to entry
- CreateJournalEntry stores donor email on income entry
- PostJournalEntry posts a draft entry and makes it immutable
- PostJournalEntry rejects already-posted entry
- PostJournalEntry rejects posting to a closed period
- PostJournalEntry rejects unbalanced entries
- Finance - View user can access journal entries page
- non-finance user is forbidden from journal entries page
- journal entries list shows draft and posted status
- journal entries list filters by entry type
- journal entries list filters by date range
- Finance - Record user can access the create journal entry page
- Finance - View user is forbidden from create journal entry page
- create journal entry form saves as draft
- create journal entry form validates required fields
- entries in closed periods cannot be posted via the form

**BankReconciliationTest.php:**
- Finance - Record user can access the reconciliation page
- Finance - View user cannot access the reconciliation page
- non-bank account is rejected
- CompleteReconciliation marks reconciliation as completed when difference is zero
- CompleteReconciliation throws when difference is not zero
- CompleteReconciliation throws when already completed
- user can mark a line as cleared
- user can unmark a cleared line
- complete button is disabled when difference is not zero
- complete button is enabled when difference is zero and statement balance is set
- reconciliation can be completed through the Livewire component
- reconciliation status appears on fiscal periods page for bank accounts

**PeriodCloseTest.php:**
- blocks close if period is already closed
- blocks close if bank account lacks completed reconciliation
- blocks close if reconciliation is in_progress
- closes the period when all reconciliations are complete
- generates balanced revenue closing entry
- generates balanced expense closing entry
- period is locked against new entries after close
- Finance - Record user sees Close Period button on open periods
- Finance - View user cannot call closePeriod

**BudgetManagementTest.php:**
- Finance - View user can access budgets page
- non-finance user is forbidden from budgets page
- Finance - Manage user can set a budget amount for an account and period
- Finance - Manage user can update an existing budget amount
- Finance - View user cannot update budget amounts
- budget amounts are stored as integer cents
- FY rollup shows total budgeted per account across all periods
- CopyPriorYearBudgets copies all budget entries from prior year
- CopyPriorYearBudgets updates existing budget if one already exists for the target year
- CopyPriorYearBudgets returns 0 when prior year has no budgets
- Finance - Manage user can trigger copy prior year via component
- variance report shows actual amounts from posted entries
- variance report excludes draft entries from actuals

### Coverage Gaps

1. **Partial period close with no revenue/expense entries** — The CloseFinancialPeriod action skips generating closing entries when balances are zero, but no test covers this path explicitly.
2. **Transfer entry type in General Ledger** — No test verifies transfer entries appear correctly in the General Ledger report.
3. **Restricted fund variance in closing entries** — The restricted/unrestricted split in closing entries is tested via the action, but the community finance view's restricted fund display on a post-close period is not tested end-to-end.
4. **Balance Sheet as-of-date filtering** — The `bsAsOfDate` filter is not explicitly tested; only the default (today) path is covered.
5. **Print/PDF export** — No test for the `window.print()` button (browser-side only, not server-testable).
6. **Reversing entry finds open period** — The fallback to entry's own period (when no current open period exists) is not directly tested.

---

## 17. File Map

**Models:**
- `app/Models/FinancialAccount.php`
- `app/Models/FinancialBudget.php`
- `app/Models/FinancialJournalEntry.php`
- `app/Models/FinancialJournalEntryLine.php`
- `app/Models/FinancialPeriod.php`
- `app/Models/FinancialReconciliation.php`
- `app/Models/FinancialReconciliationLine.php`
- `app/Models/FinancialRestrictedFund.php`
- `app/Models/FinancialTag.php`
- `app/Models/FinancialVendor.php`

**Enums:** None (column enums defined inline in migrations)

**Actions:**
- `app/Actions/CloseFinancialPeriod.php`
- `app/Actions/CompleteReconciliation.php`
- `app/Actions/CopyPriorYearBudgets.php`
- `app/Actions/CreateJournalEntry.php`
- `app/Actions/CreateReversingEntry.php`
- `app/Actions/GenerateFinancialPeriods.php`
- `app/Actions/ParseDollarAmount.php`
- `app/Actions/PostJournalEntry.php`

**Policies:** None (gate-only authorization)

**Gates:** `app/Providers/AuthServiceProvider.php` — gates: `finance-view`, `finance-record`, `finance-manage`, `finance-community-view`

**Notifications:** None

**Jobs:** None

**Services:** None

**Controllers:** None (all routes use Volt components)

**Volt Components:**
- `resources/views/livewire/finance/bank-reconciliation.blade.php`
- `resources/views/livewire/finance/budgets.blade.php`
- `resources/views/livewire/finance/chart-of-accounts.blade.php`
- `resources/views/livewire/finance/community-finance.blade.php`
- `resources/views/livewire/finance/create-journal-entry.blade.php`
- `resources/views/livewire/finance/create-manual-entry.blade.php`
- `resources/views/livewire/finance/fiscal-periods.blade.php`
- `resources/views/livewire/finance/journal-entries.blade.php`
- `resources/views/livewire/finance/public-dashboard.blade.php`
- `resources/views/livewire/finance/reports.blade.php`
- `resources/views/livewire/finance/restricted-funds.blade.php`
- `resources/views/livewire/finance/tag-search-modal.blade.php`
- `resources/views/livewire/finance/tags.blade.php`
- `resources/views/livewire/finance/vendor-search-modal.blade.php`
- `resources/views/livewire/finance/vendors.blade.php`

**Routes:**
- `finance.public.index` → `GET /finance`
- `finance.community.index` → `GET /finance/overview`
- `finance.accounts.index` → `GET /finance/accounts`
- `finance.periods.index` → `GET /finance/periods`
- `finance.vendors.index` → `GET /finance/vendors`
- `finance.tags.index` → `GET /finance/tags`
- `finance.journal.index` → `GET /finance/journal`
- `finance.journal.create` → `GET /finance/journal/create`
- `finance.journal.create-manual` → `GET /finance/journal/create/manual`
- `finance.budgets.index` → `GET /finance/budgets`
- `finance.restricted-funds.index` → `GET /finance/restricted-funds`
- `finance.reconciliation.show` → `GET /finance/reconciliation/{accountId}/{periodId}`
- `finance.reports.index` → `GET /finance/reports`

**Migrations:**
- `database/migrations/2026_04_05_000001_create_financial_tables.php`
- `database/migrations/2026_04_05_000002_seed_financial_accounts.php`

**Factories:**
- `database/factories/FinancialAccountFactory.php`
- `database/factories/FinancialBudgetFactory.php`
- `database/factories/FinancialJournalEntryFactory.php`
- `database/factories/FinancialJournalEntryLineFactory.php`
- `database/factories/FinancialPeriodFactory.php`
- `database/factories/FinancialReconciliationFactory.php` (if exists)
- `database/factories/FinancialRestrictedFundFactory.php`
- `database/factories/FinancialTagFactory.php`
- `database/factories/FinancialVendorFactory.php`

**Tests:**
- `tests/Feature/Finance/BalanceSheetCashFlowTest.php`
- `tests/Feature/Finance/BankReconciliationTest.php`
- `tests/Feature/Finance/BudgetManagementTest.php`
- `tests/Feature/Finance/ChartOfAccountsTest.php`
- `tests/Feature/Finance/CommunityFinanceTest.php`
- `tests/Feature/Finance/FinanceGatesTest.php`
- `tests/Feature/Finance/FiscalPeriodsTest.php`
- `tests/Feature/Finance/JournalEntryTest.php`
- `tests/Feature/Finance/ManualEntryTest.php`
- `tests/Feature/Finance/PeriodCloseTest.php`
- `tests/Feature/Finance/PublicDashboardTest.php`
- `tests/Feature/Finance/ReportsTest.php`
- `tests/Feature/Finance/RestrictedFundsTest.php`
- `tests/Feature/Finance/VarianceReportTest.php`
- `tests/Feature/Finance/VendorTagManagementTest.php`

**Config:** `SiteConfig` keys: `finance_fy_start_month`, `donation_goal` (legacy)

---

## 18. Known Issues & Improvement Opportunities

1. **Duplicate fiscal year convention logic** — The FY year calculation (`($now->month >= $startMonth) ? $now->year + 1 : $now->year`) is duplicated in at least three places: `fiscal-periods.blade.php`, `reports.blade.php`, and `GenerateFinancialPeriods::generateForCurrentFY()`. Should be extracted into a shared helper or the `GenerateFinancialPeriods` action.

2. **Reconciliation status key collision risk** — The reconciliation status uses `"{$account->id}_{$period->id}"` as a string key. While account IDs and period IDs are both positive integers, a very large account ID with a small period ID could theoretically collide with a small account ID and large period ID if the separator character is absent. Using a delimiter like `|` instead of `_` would be safer.

3. **N+1 in community-finance and public-dashboard** — `getPeriodRevenueSummary()` and `getPeriodExpenseSummary()` are called inside a `@foreach` loop in `community-finance.blade.php`, and `getPeriodIncome()`/`getPeriodExpenses()` in `public-dashboard.blade.php`. Each call fires a separate DB query. For the public dashboard (max 3 periods) this is acceptable, but for the community view (all closed periods) this could grow costly over time. Consider pre-computing all period summaries in a single query.

4. **Manual entry does not call PostJournalEntry** — `create-manual-entry.blade.php` saves entries with `status: 'posted'` directly via DB without going through `PostJournalEntry::run()`. This bypasses the balanced-check logic in `PostJournalEntry`. The component does its own balance check, but the two paths are inconsistent and a future refactor of `PostJournalEntry` would not automatically apply to manual entries.

5. **Closing entries excluded from reports but not filtered on all paths** — `whereNot('je.entry_type', 'closing')` is applied in the Statement of Activities and community/public views, but not consistently in every report query. The General Ledger and Trial Balance will include closing entries if an account with `type = closing` is selected. This may be intentional (closing entries are legitimate posted entries) but could confuse users.

6. **No activity log for journal entry creation or posting** — Unlike period close and reconciliation completion, individual journal entry creation and posting do not generate activity log entries. Adding these would improve the audit trail for compliance purposes.

7. **Legacy donation page not reconciled** — `resources/views/donation/index.blade.php` still reads from `SiteConfig` keys (`donation_current_month_amount`, `donation_last_month_name`, etc.) that are manually updated. The new public dashboard reads from the actual accounting system. The two pages can show conflicting figures if the SiteConfig values are not kept up to date. The legacy donation page should be updated to pull from the accounting system.

8. **No soft-delete on journal entries** — Posted journal entries cannot be deleted; they must be reversed. Draft entries have no deletion mechanism in the UI. The only way to remove a draft is directly via the database. Consider adding a "delete draft" capability.

9. **Statement of Cash Flows is simplified** — The current cash flow report equates revenue = inflows and expenses = outflows. A true indirect-method cash flow statement would start from net income and adjust for non-cash items. The current implementation is a simplified direct-method approximation that does not account for transfers between bank accounts or balance sheet changes.

10. **Reconciliation `statement_ending_balance` uses signed integer** — The column type is `integer` (not `unsignedInteger`), allowing negative values. While negative bank balances are theoretically possible (overdraft), the UI only accepts non-negative values through `ParseDollarAmount`. The schema mismatch is not harmful but is slightly inconsistent with the `debit`/`credit` columns which use `unsignedInteger`.
