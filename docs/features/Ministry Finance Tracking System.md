# Ministry Finance Tracking System

**Generated:** 2026-04-04  
**Feature status:** Active  
**PRD:** Implemented via migrations dated 2026-04-04

---

## 1. Overview

The Ministry Finance Tracking System provides Lighthouse Minecraft Ministry with a complete internal bookkeeping tool. It covers:

- **Accounts** — Bank accounts, payment processors, and cash holdings, each with an `opening_balance` and a computed running balance.
- **Categories and subcategories** — Two-level classification tree (income or expense) with configurable sort order.
- **Tags** — Freeform labels that can be attached to any income or expense transaction for cross-cutting reporting.
- **Transactions** — Individual financial events: income, expense, or inter-account transfer.
- **Monthly budgets** — Planned spending per top-level category for a given calendar month, with automatic pre-fill from the prior month and 3-month rolling trend data.
- **Period reports** — A publish action that locks a calendar month, making all its transactions read-only and surfacing the month on the public transparency page.
- **Board reports** — Multi-period income statement, balance sheet, and cash-flow statement aimed at leadership/board review, with downloadable PDF output.
- **Public transparency page** — An unauthenticated route that shows published-month summaries at three levels of detail based on the viewer's membership level.

All monetary amounts are stored and computed in **cents** (integer). Display values are divided by 100 and formatted with two decimal places.

---

## 2. Database Schema

### `financial_accounts`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | Auto-increment |
| `name` | string | Human-readable label (e.g. "RelayFi Checking") |
| `type` | enum | `checking`, `savings`, `payment-processor`, `cash` |
| `opening_balance` | unsigned bigint | Cents; seed balance before any transactions |
| `is_archived` | boolean | `false` = active; archived accounts hidden from entry forms |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

Default seed records: **Cash**, **Stripe**, **RelayFi Checking**, **RelayFi Savings**.

### `financial_categories`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `name` | string | |
| `parent_id` | bigint FK nullable | Self-referencing; `null` = top-level. `nullOnDelete` — deleting a parent sets children's `parent_id` to NULL. Maximum depth: 2 (top-level + one subcategory level). |
| `type` | enum | `income`, `expense` |
| `sort_order` | unsigned int | Display order within the same parent+type scope |
| `is_archived` | boolean | Archived categories hidden from entry forms |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

Default seed expense top-level + subcategories:
- Infrastructure → Minecraft Hosting, Web Hosting, Domain & Email
- Software & Tools → Discord Bots, Web Dev Tools, Other Software
- Administration → Fees, Taxes, Board/Legal
- Ministry/Community → Events, Donations to Other Ministries, Other Ministry Costs

Default seed income (top-level only, no subcategories): **Donations**, **Staff Cash Contributions**.

### `financial_tags`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `name` | string | |
| `created_by` | bigint FK | References `users.id`; `cascadeOnDelete` |
| `is_archived` | boolean | |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### `financial_transactions`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `account_id` | bigint FK | References `financial_accounts.id`; `cascadeOnDelete` |
| `type` | enum | `income`, `expense`, `transfer` |
| `amount` | unsigned bigint | Cents |
| `transacted_at` | date | Date the transaction occurred |
| `financial_category_id` | bigint FK nullable | References `financial_categories.id`; `nullOnDelete`. `null` for transfer type. |
| `target_account_id` | bigint FK nullable | References `financial_accounts.id`; `nullOnDelete`. Set only for `transfer` type. |
| `notes` | text nullable | Free-text notes |
| `entered_by` | bigint FK | References `users.id`; `cascadeOnDelete` |
| `external_reference` | string nullable | Reserved for future external system integration; always `null` currently. |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### `financial_transaction_tags` (pivot)

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `financial_transaction_id` | bigint FK | `cascadeOnDelete` |
| `financial_tag_id` | bigint FK | `cascadeOnDelete` |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

Unique constraint on `(financial_transaction_id, financial_tag_id)`.

### `monthly_budgets`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `financial_category_id` | bigint FK | References `financial_categories.id`; `cascadeOnDelete` |
| `month` | date | Always stored as the first of the month (`YYYY-MM-01`) |
| `planned_amount` | unsigned bigint | Cents |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

Unique constraint on `(financial_category_id, month)`.

### `financial_period_reports`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `month` | date unique | First of the month (`YYYY-MM-01`) |
| `published_at` | timestamp nullable | `null` = not yet published; non-null = locked |
| `published_by` | bigint FK nullable | References `users.id`; `nullOnDelete` |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

---

## 3. Models & Relationships

### `FinancialAccount`

```
hasMany   transactions()       → FinancialTransaction (account_id)
hasMany   incomingTransfers()  → FinancialTransaction (target_account_id)
```

**Computed method** `currentBalance(): int` — sums opening balance + income credits − expense debits − transfers out + transfers in across **all time** (not month-scoped).

### `FinancialCategory`

```
belongsTo  parent()           → FinancialCategory (parent_id)
hasMany    children()         → FinancialCategory (parent_id)
hasMany    transactions()     → FinancialTransaction
hasMany    monthlyBudgets()   → MonthlyBudget
```

### `FinancialTransaction`

```
belongsTo     account()       → FinancialAccount (account_id)
belongsTo     category()      → FinancialCategory (financial_category_id)
belongsTo     targetAccount() → FinancialAccount (target_account_id)
belongsTo     enteredBy()     → User (entered_by)
belongsToMany tags()          → FinancialTag via financial_transaction_tags
```

**Method** `isInPublishedMonth(): bool` — checks whether a `FinancialPeriodReport` with a matching month start date and a non-null `published_at` exists. Used to gate edits and deletes.

### `FinancialTag`

```
belongsTo     creator()      → User (created_by)
belongsToMany transactions() → FinancialTransaction via financial_transaction_tags
```

### `FinancialTransactionTag` (Pivot)

Extends `Illuminate\Database\Eloquent\Relations\Pivot`. `$incrementing = true`. Direct relationships: `transaction()`, `tag()`.

### `MonthlyBudget`

```
belongsTo  category() → FinancialCategory (financial_category_id)
```

### `FinancialPeriodReport`

```
belongsTo  publishedBy() → User (published_by)
```

**Method** `isPublished(): bool` — returns `true` when `published_at` is not null.

---

## 4. Enums Reference

No dedicated PHP Enum classes exist for this feature. Type constraints are enforced via database `enum` columns and `in:` validation rules in Livewire components:

| Field | Allowed Values |
|---|---|
| `financial_accounts.type` | `checking`, `savings`, `payment-processor`, `cash` |
| `financial_categories.type` | `income`, `expense` |
| `financial_transactions.type` | `income`, `expense`, `transfer` |

The `MembershipLevel` enum (app-wide) is consumed in the `public.blade.php` component to determine the display tier.

---

## 5. Authorization & Permissions

### Gates (defined in `AuthServiceProvider`)

All three finance gates follow a **hierarchical inclusion** pattern: higher-privilege roles automatically satisfy lower-privilege checks.

| Gate | Role(s) Required | Description |
|---|---|---|
| `financials-view` | `Financials - View` OR `Financials - Treasurer` OR `Financials - Manage` | Read-only access to the ledger, budget, and period reports |
| `financials-treasurer` | `Financials - Treasurer` OR `Financials - Manage` | Enter/edit/delete transactions, save budgets, publish period reports |
| `financials-manage` | `Financials - Manage` | Create/rename/archive accounts, categories, and tags; access board reports; download income-statement/balance-sheet/cash-flow PDFs |

### Roles (seeded in migration `2026_04_04_000009`)

| Role Name | Color | Icon | Description |
|---|---|---|---|
| `Financials - View` | green | eye | Read-only access to the full ledger and all financial reports |
| `Financials - Treasurer` | blue | banknotes | Enter and edit transactions, set monthly budgets, publish period reports |
| `Financials - Manage` | purple | cog-6-tooth | Full financial access including managing accounts, categories, and tags |

### Permissions Matrix

| Action | View | Treasurer | Manage |
|---|:---:|:---:|:---:|
| Access `/finances/dashboard` | ✓ | ✓ | ✓ |
| View transaction ledger | ✓ | ✓ | ✓ |
| Record / edit / delete transactions | — | ✓ | ✓ |
| Access `/finances/accounts` | ✓ | ✓ | ✓ |
| Create / rename / archive accounts | — | — | ✓ |
| Access `/finances/categories` | ✓ | ✓ | ✓ |
| Create / rename / archive categories | — | — | ✓ |
| Create / archive tags | — | — | ✓ |
| Access `/finances/budget` | ✓ | ✓ | ✓ |
| Save monthly budget | — | ✓ | ✓ |
| Access `/finances/reports` | ✓ | ✓ | ✓ |
| Publish a period report | — | ✓ | ✓ |
| Download period report PDF | ✓ | ✓ | ✓ |
| Access `/finances/board-reports` | — | — | ✓ |
| Download income statement PDF | — | — | ✓ |
| Download balance sheet PDF | — | — | ✓ |
| Download cash-flow PDF | — | — | ✓ |
| Access `/finances` (public page) | All | All | All |

### Public Page Tiers

| Tier | Condition | Data Shown |
|---|---|---|
| `public` | Not logged in, or below Resident | Income/expense totals per published month + YTD; no category detail |
| `resident` | Logged in + at least Resident level | Adds top-level category breakdown per published month |
| `staff` | Logged in + `financials-view` gate | Adds subcategory breakdown with transaction counts |

---

## 6. Routes

All authenticated finance routes are protected by `auth` middleware and the `can:financials-view` middleware. Board-report PDF routes perform an additional `financials-manage` gate check inside the controller.

| Method | URL | Middleware | Name | Handler |
|---|---|---|---|---|
| GET | `/finances` | none (public) | `finances.public` | Volt: `finances.public` |
| GET | `/finances/dashboard` | auth, can:financials-view | `finances.dashboard` | Volt: `finances.dashboard` |
| GET | `/finances/accounts` | auth, can:financials-view | `finances.accounts` | Volt: `finances.accounts` |
| GET | `/finances/categories` | auth, can:financials-view | `finances.categories` | Volt: `finances.categories` |
| GET | `/finances/budget/{month?}` | auth, can:financials-view | `finances.budget` | Volt: `finances.budget` |
| GET | `/finances/reports` | auth, can:financials-view | `finances.reports` | Volt: `finances.reports` |
| GET | `/finances/reports/{month}/pdf` | auth, can:financials-view | `finances.reports.pdf` | `PeriodReportPdfController` |
| GET | `/finances/board-reports` | auth, can:financials-view | `finances.board-reports` | Volt: `finances.board-reports` |
| GET | `/finances/board-reports/income-statement/pdf` | auth, can:financials-view | `finances.board-reports.income-statement.pdf` | `IncomeStatementPdfController` |
| GET | `/finances/board-reports/balance-sheet/pdf` | auth, can:financials-view | `finances.board-reports.balance-sheet.pdf` | `BalanceSheetPdfController` |
| GET | `/finances/board-reports/cash-flow/pdf` | auth, can:financials-view | `finances.board-reports.cash-flow.pdf` | `CashFlowPdfController` |

**Note on `finances.reports.pdf`:** The route accepts a `{month}` path parameter in `Y-m` format (e.g., `2026-03`). The controller requires the month to already be published or it returns 404.

---

## 7. User Interface Components

### Volt Components

#### `finances.dashboard` — Finance Dashboard

**Route:** `/finances/dashboard`  
**Auth required:** `financials-view`

Provides two main sections:

1. **Account Balances panel** — Card grid showing every non-archived account's name, current balance, and type. Balance is computed via `currentBalance()` on each account.

2. **Transaction Entry Form** — Visible only to users with `financials-treasurer`. Fields:
   - **Type** (live-updates categories/transfer UI): `income`, `expense`, `transfer`
   - **Account** (or "From Account" for transfers): select from active accounts
   - **To Account** (transfers only): must differ from source account
   - **Amount** (integer cents, min 1)
   - **Date** (defaults to today)
   - **Category** (live; filtered by transaction type): selects a top-level category
   - **Subcategory** (conditional: shown when selected category has children)
   - **Tags** (checkbox list; hidden for transfers)
   - **Notes** (optional textarea)

3. **Transaction Ledger** — Full paginated-style table (no server-side pagination; returns all matching rows). Filterable by:
   - Date from / to
   - Account (all accounts including archived)
   - Top-level category (matches the category and all its subcategories)
   - Tag

   Each row shows date, account, type badge, category path, amount, tags, notes, and the user who entered the transaction. Treasurer users see **Edit** and **Delete** buttons; these are hidden on rows belonging to a published month (which instead shows "Published").

4. **Edit Transaction Modal** — Opened via `openEditModal(id)`. Allows changing type (income/expense only — not transfer), account, amount, date, category/subcategory, tags, and notes. Not available for transactions in a published month.

**Livewire actions:** `submitTransaction`, `openEditModal`, `updateTransaction`, `deleteTransaction`

---

#### `finances.accounts` — Financial Accounts

**Route:** `/finances/accounts`  
**Auth required:** `financials-view` (manage actions require `financials-manage`)

Displays a table of all accounts (active and archived) showing name, type, current balance, and status badge. Users with `financials-manage` see:
- **New Account** button → opens create modal (name, type, opening balance in cents)
- **Rename** button per row → opens edit modal (name only; type/opening balance are immutable after creation)
- **Archive** button per non-archived row

**Livewire actions:** `createAccount`, `openEditModal`, `updateAccount`, `archiveAccount`

---

#### `finances.categories` — Categories & Tags

**Route:** `/finances/categories`  
**Auth required:** `financials-view` (manage actions require `financials-manage`)

Two sections:

**Categories:** Table grouped by type (`expense` then `income`). Top-level rows are shown in bold; their subcategories are indented with a `↳` prefix. Each non-archived row shows a sort-order value. Manage users see Rename, Reorder, and Archive actions.

The **Create Category** modal supports:
- Name
- Type (`income`/`expense`)
- Parent (optional; dropdown restricted to top-level categories of the same type that are not archived)

The system enforces that subcategories cannot themselves be parents (max depth 2).

**Tags:** Simple table of all tags with name and archived/active status. Manage users can create new tags and archive existing ones. Tags cannot be deleted, only archived.

**Livewire actions:** `createCategory`, `openEditCategoryModal`, `updateCategory`, `archiveCategory`, `openReorderModal`, `reorderCategory`, `createTag`, `archiveTag`

---

#### `finances.budget` — Monthly Budget

**Route:** `/finances/budget/{month?}`  
**Auth required:** `financials-view` (save requires `financials-treasurer`)

Displays a month-navigable budget table. The optional `{month}` route parameter sets the initial month; defaults to the current month.

**Columns:** Category, Type badge, Planned ($), Actual ($), Variance ($), 3-Month Trend ($).

- **Planned** — Editable input for treasurers; read-only formatted value for view-only users.
- **Actual** — Sum of all income/expense transactions in that month for the top-level category and all its subcategories.
- **Variance** — Planned minus actual; shown green when ≥ 0, red when negative.
- **3-Month Trend** — Average actual spending across the three most recent **published** months. Shown as `—` when no published months exist.

**Pre-fill logic:** When no budget rows exist for the selected month, the component pre-fills all planned amounts from the previous month's budget. If no prior month exists, all inputs start blank.

Navigation buttons (`previousMonth`, `nextMonth`) re-load the budget data for the adjacent month.

**Livewire actions:** `previousMonth`, `nextMonth`, `saveBudget`

---

#### `finances.reports` — Period Reports

**Route:** `/finances/reports`  
**Auth required:** `financials-view`

Lists all months that have transactions (for treasurers) or only published months (for view-only users). Each row shows total income, total expenses, net change, and published/unpublished status.

Treasurer users see a **Publish** button for unpublished months; all authorized users see **View** and **PDF** buttons for published months.

**View modal** — Shows income, expense, net totals; account balances as of end of month; budget variance table per category.

**Publish modal** — Shows the same pre-publish summary, then a **Confirm & Publish** button. Publishing is irreversible (no unpublish action exists).

**PDF download** — Links to `finances.reports.pdf` route, which serves a DomPDF-rendered file named `period-report-{YYYY-MM}.pdf`.

**Note:** Month enumeration uses `strftime('%Y-%m', transacted_at)` SQLite aggregate for the treasurer view; ensure the database engine supports this function.

**Livewire actions:** `openViewModal`, `openPublishModal`, `confirmPublish`

---

#### `finances.board-reports` — Board Reports

**Route:** `/finances/board-reports`  
**Auth required:** `financials-manage`

Date-range picker (start month / end month) with quick **T1 (Jan–Apr)**, **T2 (May–Aug)**, **T3 (Sep–Dec)** preset buttons. Defaults to current year start → current month.

Displays three reports inline, each with a **Download PDF** button:

1. **Income Statement** — Hierarchical income and expense breakdown by category (with subcategory detail), plus summary cards (total income, total expense, net income).
2. **Balance Sheet** — Account balances as of the end of the selected end-month, plus net-assets total.
3. **Cash Flow Statement** — Operating activities (income/expense by top-level category) and financing activities (inter-account transfers as dated rows), plus net cash change.

**Livewire actions:** `applyTrimester`

---

#### `finances.public` — Public Finances Page

**Route:** `/finances`  
**Auth required:** None

Three-tier display based on the authenticated user's level:

| Tier | Condition |
|---|---|
| `public` | Guest or below Resident |
| `resident` | At least `MembershipLevel::Resident` |
| `staff` | Has `financials-view` gate |

Always visible: Year-to-date income/expense/net for the current calendar year (published months only), then per-month cards for all published months.

Residents additionally see top-level category breakdowns per month. Staff (`financials-view`) additionally see subcategory breakdowns with transaction counts.

---

### PDF Controller Pages

All four controllers use the `barryvdh/laravel-dompdf` package (`Barryvdh\DomPDF\Facade\Pdf`). Each controller returns a direct file download response.

| Controller | Route | Auth | Template | Filename Pattern |
|---|---|---|---|---|
| `PeriodReportPdfController` | `finances.reports.pdf` | `financials-view` + published check | `finances.period-report-pdf` | `period-report-{YYYY-MM}.pdf` |
| `IncomeStatementPdfController` | `finances.board-reports.income-statement.pdf` | `financials-manage` | `finances.income-statement-pdf` | `income-statement-{start}-to-{end}.pdf` |
| `BalanceSheetPdfController` | `finances.board-reports.balance-sheet.pdf` | `financials-manage` | `finances.balance-sheet-pdf` | `balance-sheet-as-of-{YYYY-MM}.pdf` |
| `CashFlowPdfController` | `finances.board-reports.cash-flow.pdf` | `financials-manage` | `finances.cash-flow-pdf` | `cash-flow-{start}-to-{end}.pdf` |

Query parameters for `IncomeStatementPdfController` and `CashFlowPdfController`: `start` and `end` (format `YYYY-MM`, required, validated via regex).  
Query parameter for `BalanceSheetPdfController`: `end` (format `YYYY-MM`, required).

---

### Meeting Integration (Finance Red Flag)

The `meetings.manage-meeting` Volt component includes a `financeRedFlag()` method that queries `FinancialPeriodReport` for any record with `published_at >= now() - 14 days`. When no such record exists, a red callout banner is shown at the top of the meeting management page: **"Finance Report Overdue"**, with a link to `finances.dashboard` shown to users who have `financials-view`.

---

## 8. Actions

All actions use the `Lorisleiva\Actions\Concerns\AsAction` trait and are invoked via `ClassName::run(...)`.

### `CreateFinancialAccount`

**Signature:** `handle(string $name, string $type, int $openingBalance): FinancialAccount`

1. Calls `FinancialAccount::create()` with `name`, `type`, `opening_balance`, and `is_archived = false`.
2. Returns the created model.

---

### `UpdateFinancialAccount`

**Signature:** `handle(FinancialAccount $account, string $name): void`

1. Sets `$account->name` to the new value.
2. Saves the model.

Note: account type and opening balance are not editable after creation.

---

### `ArchiveFinancialAccount`

**Signature:** `handle(FinancialAccount $account): void`

1. Sets `$account->is_archived = true`.
2. Saves the model.

There is no unarchive action.

---

### `CreateFinancialCategory`

**Signature:** `handle(string $name, string $type, ?int $parentId = null): FinancialCategory`

1. If `$parentId` is provided, loads the parent and throws `InvalidArgumentException` if the parent itself has a `parent_id` (enforces max depth of 2).
2. Queries `max(sort_order)` for categories with the same `parent_id` and `type`; defaults to -1 when none exist.
3. Creates the category with `sort_order = max + 1`, `is_archived = false`.
4. Returns the created model.

---

### `UpdateFinancialCategory`

**Signature:** `handle(FinancialCategory $category, string $name): void`

1. Sets `$category->name`.
2. Saves the model.

---

### `ArchiveFinancialCategory`

**Signature:** `handle(FinancialCategory $category): void`

1. Sets `$category->is_archived = true`.
2. Saves the model.

---

### `ReorderFinancialCategory`

**Signature:** `handle(FinancialCategory $category, int $newSortOrder): void`

1. Sets `$category->sort_order = $newSortOrder`.
2. Saves the model.

Note: This is a direct assignment; it does not resequence sibling categories. Gaps and collisions are possible.

---

### `CreateFinancialTag`

**Signature:** `handle(string $name, User $createdBy): FinancialTag`

1. Creates a `FinancialTag` with `name`, `created_by = $createdBy->id`, and `is_archived = false`.
2. Returns the created model.

---

### `ArchiveFinancialTag`

**Signature:** `handle(FinancialTag $tag): void`

1. Sets `$tag->is_archived = true`.
2. Saves the model.

---

### `RecordFinancialTransaction`

**Signature:**  
`handle(User $enteredBy, int $accountId, string $type, int $amount, string $transactedAt, ?int $categoryId, ?string $notes, array $tagIds = [], ?int $targetAccountId = null): FinancialTransaction`

1. Creates a `FinancialTransaction` with all provided fields. `external_reference` is always set to `null`.
2. Notes are stored as `null` when the provided string is empty.
3. If `$tagIds` is not empty, syncs the tag pivot table via `$transaction->tags()->sync($tagIds)`.
4. Returns the created transaction.

---

### `UpdateFinancialTransaction`

**Signature:**  
`handle(FinancialTransaction $transaction, int $accountId, string $type, int $amount, string $transactedAt, ?int $categoryId, ?string $notes, array $tagIds = []): void`

1. Calls `$transaction->isInPublishedMonth()` and throws `RuntimeException('Cannot edit a transaction in a published month.')` if true.
2. Calls `$transaction->update()` with new field values.
3. Syncs the tag pivot with `$tagIds`.

Note: `target_account_id` is **not** updatable via this action (transfers cannot be edited from the dashboard).

---

### `DeleteFinancialTransaction`

**Signature:** `handle(FinancialTransaction $transaction): void`

1. Calls `$transaction->isInPublishedMonth()` and throws `RuntimeException('Cannot delete a transaction in a published month.')` if true.
2. Detaches all tags via `$transaction->tags()->detach()`.
3. Deletes the transaction.

---

### `SaveMonthlyBudget`

**Signature:** `handle(string $monthStart, array $plannedAmounts): void`

`$plannedAmounts` is a map of `category_id (int) => planned_amount_in_cents (int)`.

1. Iterates all entries; skips any where `$plannedAmount < 0`.
2. For each valid entry, calls `MonthlyBudget::updateOrCreate()` matching on `(financial_category_id, month)` and setting `planned_amount`.

This means zero-valued entries are persisted (a budget row of 0 is valid). Negative values are silently skipped.

---

### `PublishPeriodReport`

**Signature:** `handle(string $monthStart, User $publishedBy): FinancialPeriodReport`

1. Looks up an existing `FinancialPeriodReport` for the month; throws `RuntimeException('This month has already been published.')` if it is already published.
2. Counts transactions in the month date range; throws `RuntimeException('Cannot publish a month with no transactions.')` if count is 0.
3. Calls `FinancialPeriodReport::updateOrCreate()` matching on `month`, setting `published_at = now()` and `published_by = $publishedBy->id`.
4. Returns the upserted report.

---

## 9. Notifications

**Not applicable.** The finance system does not send email or Pushover notifications. The only finance-adjacent alert is the meeting red-flag banner, which is a UI element, not a notification.

---

## 10. Background Jobs

**Not applicable.** All finance calculations are synchronous. There are no queued jobs associated with this feature.

---

## 11. Console Commands

**Not applicable.** No artisan commands are part of this feature.

---

## 12. Services

**Not applicable.** No dedicated service class wraps this feature. The PDF generation is handled directly inside each controller using the `Barryvdh\DomPDF\Facade\Pdf` facade.

---

## 13. Activity Log Entries

**Not applicable.** The finance system does not call `RecordActivity::run()` for any of its actions. Financial changes are traceable via the `entered_by` column on transactions and `published_by` on period reports, but no entries are written to `activity_logs`.

---

## 14. Data Flow Diagrams

### 14.1 Recording a Transaction

```
Treasurer fills form (dashboard.blade.php)
  → submitTransaction()
      → authorize('financials-treasurer')
      → validate (type-aware rules)
      → RecordFinancialTransaction::run(user, accountId, type, amount, date,
                                        categoryId, notes, tagIds, targetAccountId)
          → FinancialTransaction::create(...)
          → tags()->sync($tagIds) [if tagIds non-empty]
  → Flux::toast('Transaction recorded.')
  → reset form fields
  → Livewire re-renders ledger (reads fresh from DB)
```

### 14.2 Editing a Transaction

```
Treasurer clicks Edit (dashboard.blade.php)
  → openEditModal(id)
      → authorize('financials-treasurer')
      → FinancialTransaction::with('tags')->findOrFail(id)
      → isInPublishedMonth()? → show error toast, return
      → populate edit* properties
      → Flux::modal('edit-tx-modal')->show()

Treasurer submits edit form
  → updateTransaction()
      → authorize('financials-treasurer')
      → validate edit fields
      → UpdateFinancialTransaction::run(tx, accountId, type, amount, date, categoryId, notes, tagIds)
          → isInPublishedMonth()? → throw RuntimeException
          → tx->update(...)
          → tags()->sync(tagIds)
      → Flux::modal close + toast
```

### 14.3 Publishing a Period Report

```
Treasurer clicks Publish (reports.blade.php)
  → openPublishModal(monthStart)
      → authorize('financials-treasurer')
      → set publishMonth, show modal with pre-computed summary

Treasurer clicks Confirm & Publish
  → confirmPublish()
      → authorize('financials-treasurer')
      → PublishPeriodReport::run(monthStart, user)
          → check double-publish → throw if already published
          → count transactions in month → throw if zero
          → FinancialPeriodReport::updateOrCreate(month, {published_at, published_by})
      → Flux::modal close + toast
      → Livewire re-renders months list (row now shows Published badge + PDF button)
      → All transactions in this month now return true from isInPublishedMonth()
        → Edit/Delete buttons are hidden in the ledger
```

### 14.4 Downloading a Period Report PDF

```
User clicks PDF button (reports.blade.php)
  → GET /finances/reports/{YYYY-MM}/pdf
      → PeriodReportPdfController::__invoke(month)
          → parse YYYY-MM → monthStart, monthEnd
          → FinancialPeriodReport::whereDate('month', monthStart)->first()
          → abort_unless(report && report->isPublished(), 404)
          → buildSummary(monthStart, monthEnd):
              - sum income and expense transactions
              - per-account balance as of monthEnd
              - budget variance per top-level category (actual vs planned)
              - income by category (grouped, sorted by amount)
              - expense by category (grouped, sorted by amount)
          → Pdf::loadView('finances.period-report-pdf', [...])
          → return $pdf->download('period-report-{month}.pdf')
```

### 14.5 Public Transparency Page Display

```
GET /finances (no auth required)
  → finances.public Volt component
      → viewTier():
          - 'staff' if financials-view gate passes
          - 'resident' if isAtLeastLevel(Resident)
          - 'public' otherwise
      → yearToDate():
          - published months in current year only
          - sum income and expense transactions per published month
      → publishedMonths():
          - for each published FinancialPeriodReport (desc order):
              - compute income, expense, net
              - if resident or staff: categoryBreakdown()
                  - top-level categories with amounts
                  - if staff: include subcategories with counts
      → render cards with tiered detail
```

---

## 15. Configuration

**Not applicable.** The finance system has no `config/` file entries and no environment variables. The only configuration-adjacent item is the DomPDF library, which is configured via `config/dompdf.php` (app-wide, not finance-specific).

---

## 16. Test Coverage

All tests live in `tests/Feature/Finances/`. Tests use Pest with `Livewire\Volt\Volt::test()` for Volt component interactions and `$this->get(...)` for controller route tests.

### `AccountManagementTest.php`

1. calculates balance as opening_balance when no transactions exist
2. adds income transactions to balance
3. subtracts expense transactions from balance
4. debits the source account for a transfer
5. credits the destination account for a transfer
6. excludes transfer amounts from income/expense totals on destination
7. financials-manage user can create an account
8. financials-manage user can rename an account
9. financials-manage user can archive an account
10. user without financials-manage cannot create an account
11. user without financials-manage cannot rename an account
12. user without financials-manage cannot archive an account
13. unauthenticated user cannot access finance accounts route
14. user without financials-view cannot access finance accounts route

### `CategoryTagManagementTest.php`

1. allows creating a top-level expense category
2. allows creating a subcategory under a top-level category
3. rejects creating a subcategory under another subcategory
4. assigns sort_order sequentially within a parent scope
5. financials-manage user can rename a category
6. financials-manage user can archive a category
7. financials-manage user can reorder a category
8. financials-manage user can create a tag
9. financials-manage user can archive a tag
10. user without financials-manage cannot create a category
11. user without financials-manage cannot rename a category
12. user without financials-manage cannot archive a category
13. user without financials-manage cannot create a tag
14. user without financials-manage cannot archive a tag

### `TransactionEntryTest.php`

1. financials-treasurer can submit an income transaction
2. financials-treasurer can submit an expense transaction
3. income transaction updates account balance
4. expense transaction decreases account balance
5. uses subcategory as category when both are selected
6. attaches tags to a transaction
7. financials-view user cannot submit a transaction
8. unauthenticated user cannot access finance dashboard route
9. user without financials-view cannot access finance dashboard route
10. finance button appears in ready room for financials-view users
11. finance button does not appear in ready room for users without financials-view

### `TransactionLedgerTest.php`

1. ledger shows all transactions sorted newest first
2. ledger filters by date from
3. ledger filters by date to
4. ledger filters by account
5. ledger filters by top-level category and includes its subcategories
6. ledger filters by tag
7. treasurer can edit an unpublished-month transaction
8. treasurer can delete an unpublished-month transaction
9. treasurer cannot edit a transaction in a published month
10. treasurer cannot delete a transaction in a published month
11. financials-view user cannot edit a transaction
12. financials-view user cannot delete a transaction
13. financials-view user cannot open edit modal

### `TransferTransactionTest.php`

1. treasurer can submit a transfer transaction
2. transfer decreases source account balance
3. transfer increases destination account balance
4. cannot transfer to the same account
5. transfer requires a target account
6. transfer does not appear in category totals
7. transfer appears in ledger with both account names

### `MonthlyBudgetTest.php`

1. treasurer can access the budget page
2. financials-view user can access the budget page
3. budget page defaults to current month
4. navigates to previous month
5. navigates to next month
6. pre-fills from previous month when no budget exists for current month
7. starts blank when no prior month budget exists
8. loads existing budget for the selected month instead of pre-filling
9. treasurer can save planned amounts
10. saving budget updates existing rows
11. view-only user cannot save planned amounts
12. variance is planned minus actual
13. actual includes subcategory transactions
14. only counts transactions in the selected month for actuals
15. trend is null when no published months exist
16. trend averages actual spending across one published month
17. trend averages actual spending across two published months
18. trend uses only the three most recent published months
19. unpublished months do not count toward the trend

### `PeriodReportPublishingTest.php`

1. treasurer can access the reports page
2. financials-view user can access the reports page
3. publish action creates a period report with published_at set
4. publish action fails when no transactions exist in the month
5. publish action fails when month is already published
6. treasurer sees publish button for unpublished month with transactions
7. treasurer can publish a month via confirmPublish
8. published month shows as published in report list
9. view-only user cannot publish a month
10. transactions in a published month cannot be edited via dashboard
11. transactions in a published month cannot be deleted via dashboard

### `PeriodReportViewTest.php`

1. view-only user sees only published months in the list
2. treasurer sees both published and unpublished months
3. view-only user can open view modal for a published month
4. financials-view user can download a PDF for a published month
5. PDF download returns 404 for unpublished month
6. unauthenticated user cannot download PDF

### `IncomeStatementTest.php`

1. financials-manage user can access board reports page
2. financials-treasurer cannot access board reports page
3. financials-view user cannot access board reports page
4. unauthenticated user is redirected from board reports
5. income statement sums income by top-level category
6. income statement sums expenses by top-level category
7. income statement includes subcategory breakdown under parent
8. income statement computes correct net income
9. transfers are excluded from income statement totals
10. income statement respects the date range
11. income statement covers a multi-month range
12. trimester 1 sets start and end months correctly
13. trimester 2 sets start and end months correctly
14. trimester 3 sets start and end months correctly
15. financials-manage user can download income statement PDF
16. financials-treasurer cannot download income statement PDF
17. unauthenticated user cannot download income statement PDF

### `BalanceSheetCashFlowTest.php`

1. balance sheet shows each account balance as of end date
2. balance sheet sums net assets across all accounts
3. balance sheet accounts for transfers correctly
4. balance sheet only counts transactions up to end date
5. cash flow operating section shows income and expense by category
6. cash flow financing section lists inter-account transfers
7. transfers do not affect cash flow net change
8. financials-manage user can download balance sheet PDF
9. financials-treasurer cannot download balance sheet PDF
10. unauthenticated user cannot download balance sheet PDF
11. financials-manage user can download cash flow PDF
12. financials-treasurer cannot download cash flow PDF
13. unauthenticated user cannot download cash flow PDF

### `FinanceRedFlagTest.php`

1. shows red flag when no period report published in last 14 days
2. shows red flag when last published report was more than 14 days ago
3. does not show red flag when a period report was published within last 14 days
4. does not show red flag when a period report was published today
5. finance red flag method returns true when no recent report
6. finance red flag method returns false when recent report exists

### `PublicFinancesPageTest.php`

1. public finances page is accessible without login
2. public finances page is accessible when logged in
3. unpublished months do not appear on public page
4. only published months appear on public page
5. public tier shows income and expense totals per published month
6. unauthenticated user gets public tier with no category breakdown
7. resident user sees top-level category breakdown
8. financials-view staff sees subcategory breakdown with transaction counts
9. year-to-date totals only include published months
10. page renders correctly when no months have been published

---

## 17. File Map

### Models

| File | Purpose |
|---|---|
| `app/Models/FinancialAccount.php` | Account model; `currentBalance()` computed method |
| `app/Models/FinancialCategory.php` | Two-level category tree |
| `app/Models/FinancialTransaction.php` | Transaction model; `isInPublishedMonth()` guard method |
| `app/Models/FinancialTransactionTag.php` | Pivot model for the many-to-many |
| `app/Models/FinancialTag.php` | Tag model |
| `app/Models/MonthlyBudget.php` | Monthly planned amounts per category |
| `app/Models/FinancialPeriodReport.php` | Month-level publish lock record |

### Actions

| File | Purpose |
|---|---|
| `app/Actions/CreateFinancialAccount.php` | Create a new account |
| `app/Actions/UpdateFinancialAccount.php` | Rename an account |
| `app/Actions/ArchiveFinancialAccount.php` | Archive an account |
| `app/Actions/CreateFinancialCategory.php` | Create a category or subcategory |
| `app/Actions/UpdateFinancialCategory.php` | Rename a category |
| `app/Actions/ArchiveFinancialCategory.php` | Archive a category |
| `app/Actions/ReorderFinancialCategory.php` | Set a category's sort_order directly |
| `app/Actions/CreateFinancialTag.php` | Create a tag |
| `app/Actions/ArchiveFinancialTag.php` | Archive a tag |
| `app/Actions/RecordFinancialTransaction.php` | Create a new transaction with optional tags |
| `app/Actions/UpdateFinancialTransaction.php` | Update a transaction (blocks published-month edits) |
| `app/Actions/DeleteFinancialTransaction.php` | Delete a transaction (blocks published-month deletes) |
| `app/Actions/SaveMonthlyBudget.php` | Upsert monthly budget rows |
| `app/Actions/PublishPeriodReport.php` | Publish and lock a month |

### Controllers

| File | Purpose |
|---|---|
| `app/Http/Controllers/Finances/PeriodReportPdfController.php` | Generate period-report PDF download |
| `app/Http/Controllers/Finances/IncomeStatementPdfController.php` | Generate income-statement PDF download |
| `app/Http/Controllers/Finances/BalanceSheetPdfController.php` | Generate balance-sheet PDF download |
| `app/Http/Controllers/Finances/CashFlowPdfController.php` | Generate cash-flow statement PDF download |

### Volt Components

| File | Route |
|---|---|
| `resources/views/livewire/finances/dashboard.blade.php` | `/finances/dashboard` |
| `resources/views/livewire/finances/accounts.blade.php` | `/finances/accounts` |
| `resources/views/livewire/finances/categories.blade.php` | `/finances/categories` |
| `resources/views/livewire/finances/budget.blade.php` | `/finances/budget/{month?}` |
| `resources/views/livewire/finances/reports.blade.php` | `/finances/reports` |
| `resources/views/livewire/finances/board-reports.blade.php` | `/finances/board-reports` |
| `resources/views/livewire/finances/public.blade.php` | `/finances` |

### PDF Blade Templates

| File | Used By |
|---|---|
| `resources/views/finances/period-report-pdf.blade.php` | `PeriodReportPdfController` |
| `resources/views/finances/income-statement-pdf.blade.php` | `IncomeStatementPdfController` |
| `resources/views/finances/balance-sheet-pdf.blade.php` | `BalanceSheetPdfController` |
| `resources/views/finances/cash-flow-pdf.blade.php` | `CashFlowPdfController` |

### Migrations

| File | Purpose |
|---|---|
| `database/migrations/2026_04_04_000001_create_financial_accounts_table.php` | `financial_accounts` table |
| `database/migrations/2026_04_04_000002_create_financial_categories_table.php` | `financial_categories` table |
| `database/migrations/2026_04_04_000003_create_financial_tags_table.php` | `financial_tags` table |
| `database/migrations/2026_04_04_000004_create_financial_transactions_table.php` | `financial_transactions` table |
| `database/migrations/2026_04_04_000005_create_financial_transaction_tags_table.php` | `financial_transaction_tags` pivot table |
| `database/migrations/2026_04_04_000006_create_monthly_budgets_table.php` | `monthly_budgets` table |
| `database/migrations/2026_04_04_000007_create_financial_period_reports_table.php` | `financial_period_reports` table |
| `database/migrations/2026_04_04_000008_seed_financial_accounts_and_categories.php` | Seeds default accounts and category tree |
| `database/migrations/2026_04_04_000009_seed_financial_roles.php` | Seeds the three financial roles |

### Factories

| File | Model |
|---|---|
| `database/factories/FinancialAccountFactory.php` | `FinancialAccount` |
| `database/factories/FinancialCategoryFactory.php` | `FinancialCategory` |
| `database/factories/FinancialTagFactory.php` | `FinancialTag` |
| `database/factories/FinancialTransactionFactory.php` | `FinancialTransaction` |
| `database/factories/FinancialPeriodReportFactory.php` | `FinancialPeriodReport` |
| `database/factories/MonthlyBudgetFactory.php` | `MonthlyBudget` |

### Tests

| File | Covers |
|---|---|
| `tests/Feature/Finances/AccountManagementTest.php` | Balance calculation; CRUD account actions; access control |
| `tests/Feature/Finances/CategoryTagManagementTest.php` | Category/subcategory/tag CRUD; sort order; depth enforcement |
| `tests/Feature/Finances/TransactionEntryTest.php` | Income/expense submission; balance effects; access control; ready-room nav link |
| `tests/Feature/Finances/TransactionLedgerTest.php` | Ledger display/filtering; edit/delete; published-month guard |
| `tests/Feature/Finances/TransferTransactionTest.php` | Transfer entry; balance debits/credits; same-account rejection |
| `tests/Feature/Finances/MonthlyBudgetTest.php` | Budget CRUD; pre-fill; variance; trend calculation |
| `tests/Feature/Finances/PeriodReportPublishingTest.php` | Publish action; guards; UI state transitions |
| `tests/Feature/Finances/PeriodReportViewTest.php` | View-modal; PDF download; access tiers |
| `tests/Feature/Finances/IncomeStatementTest.php` | Board reports access; income statement data; trimester presets; PDF download |
| `tests/Feature/Finances/BalanceSheetCashFlowTest.php` | Balance sheet; cash flow; PDF downloads |
| `tests/Feature/Finances/FinanceRedFlagTest.php` | Meeting red-flag banner logic |
| `tests/Feature/Finances/PublicFinancesPageTest.php` | Public page tiers; YTD; category breakdown |

### Authorization

| File | Finance-relevant content |
|---|---|
| `app/Providers/AuthServiceProvider.php` | Defines `financials-view`, `financials-treasurer`, `financials-manage` gates |

### Integration Points

| File | Integration |
|---|---|
| `resources/views/livewire/meetings/manage-meeting.blade.php` | `financeRedFlag()` method + banner |
| `routes/web.php` | Finance route group definition |

---

## 18. Known Issues & Improvement Opportunities

1. **No pagination on the ledger.** The `ledger()` method in `dashboard.blade.php` returns all matching transactions with no limit. On high-volume instances this will produce large queries and slow renders. Livewire pagination or a per-page limit should be added.

2. **SQLite-specific `strftime` aggregate.** The `reports.blade.php` component uses `strftime('%Y-%m', transacted_at)` in a raw query to enumerate distinct months. This is SQLite syntax and will break on MySQL/PostgreSQL deployments. A database-agnostic approach (e.g., `selectRaw("DATE_FORMAT(transacted_at, '%Y-%m') as ym")` with a DB driver check, or using Laravel's `whereBetween` with Carbon) should be used before migrating off SQLite.

3. **No unarchive action for accounts, categories, or tags.** Once archived, items are permanently hidden from entry forms. There is no UI path or action to restore archived records. This may be intentional (soft-archive as near-delete) but should be documented for operators.

4. **No unarchive action for tags.** Same concern as above — once a tag is archived it cannot be re-activated through the UI.

5. **Transfers are not editable.** `UpdateFinancialTransaction` does not accept `target_account_id` as a parameter, and the edit modal in `dashboard.blade.php` only shows `income`/`expense` options for type. If a transfer is recorded with the wrong source or target, it must be deleted and re-entered.

6. **`ReorderFinancialCategory` does not resequence siblings.** Setting a category to sort_order `0` when another category already holds `0` produces a tie. A proper drag-and-drop or sequential reorder would require a bulk-update approach.

7. **Activity logging is absent.** No `RecordActivity` calls exist in any finance action, so the activity log provides no audit trail for financial changes. For a financial system, logging who created/edited/deleted transactions and who published reports is a meaningful governance gap.

8. **Period report publishing is irreversible.** There is no admin action to unpublish a month, which makes correcting an erroneously published month impossible without a direct database edit.

9. **`external_reference` column is unused.** The column is always written as `null` by `RecordFinancialTransaction` and is not exposed in any UI. Its intended integration (payment processor webhooks, import CSV, etc.) has not yet been implemented.

10. **`SaveMonthlyBudget` silently skips negative values.** A negative planned amount is quietly dropped rather than surfacing a validation error. The Livewire component uses `min="0"` on the number input, so this only matters if the action is called directly (e.g., via tests or future API).

11. **Board reports page is inaccessible to the treasurer role.** Only `financials-manage` can view board reports; a treasurer (`financials-treasurer`) cannot. This is intentional per the current design, but may be worth revisiting if the treasurer role is expected to produce board-facing reports independently.

12. **Balance calculations are unbounded in time.** `FinancialAccount::currentBalance()` sums all transactions ever recorded, not those up to a particular date. For accurate point-in-time balances (e.g., on a balance sheet as of a past month), the controllers perform their own date-bounded queries. These two paths use slightly different code and should ideally share a helper method.
