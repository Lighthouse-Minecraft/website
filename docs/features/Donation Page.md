# Donation Page -- Technical Documentation

> **Audience:** Project owner, developers, AI agents
> **Generated:** 2026-03-08
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

The Donation Page is a simple, publicly accessible page that allows visitors and community members to financially support LighthouseMC. It displays current and previous month donation progress against a configurable monthly goal, provides a one-time donation link, and embeds a Stripe pricing table for recurring monthly subscriptions. A link to the Stripe customer portal allows existing donors to manage their subscriptions.

The page is accessible to anyone (no authentication required) and is linked in the application sidebar under the "Get Involved" section with a "Donate" badge. All donation processing is handled externally by Stripe — the Lighthouse Website does not process payments, store payment information, or track individual donations. Donation amounts displayed on the page are manually configured via environment variables.

This is one of the simplest features in the application: a single controller, a single Blade view, and configuration values. There are no models, actions, notifications, jobs, or database tables associated with this feature.

---

## 2. Database Schema

Not applicable for this feature. The Donation Page has no database tables — all displayed data comes from configuration values (environment variables).

---

## 3. Models & Relationships

Not applicable for this feature.

---

## 4. Enums Reference

Not applicable for this feature.

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

No gates for this feature. The donation page is publicly accessible.

### Policies

Not applicable for this feature.

### Permissions Matrix

| User Type | View Donation Page |
|-----------|--------------------|
| Unauthenticated | Yes |
| Regular User | Yes |
| Staff | Yes |
| Admin | Yes |

---

## 6. Routes

| Method | URL | Middleware | Handler | Route Name |
|--------|-----|-----------|---------|------------|
| GET | `/donate` | (none) | `DonationController@index` | `donate` |

---

## 7. User Interface Components

### Donation Page
**File:** `resources/views/donation/index.blade.php`
**Route:** `/donate` (route name: `donate`)

**Purpose:** Displays donation progress and provides links to donate via Stripe.

**Authorization:** None — publicly accessible.

**UI Elements:**
- **Page heading:** "Support Lighthouse"
- **Introductory text:** Explains the community is donor-supported
- **Community Support section** — grid of up to 3 cards:
  - **Current month card** (conditional on `lighthouse.donation_current_month_name` being set): Shows current month name, amount donated, and monthly goal
  - **Last month card** (conditional on `lighthouse.donation_last_month_name` being set): Shows previous month name, amount donated, and monthly goal
  - **One-Time Gift card** (conditional on `lighthouse.stripe.one_time_donation_url` being set): Button linking to Stripe one-time donation page
- **Monthly Support section:** Embedded Stripe pricing table (`<stripe-pricing-table>`) using `donation_pricing_table_id` and `donation_pricing_table_key`
- **Callout box:** Mission statement emphasizing 100% of donations go to ministry expenses, with "Manage Subscription" button linking to Stripe customer portal

### Sidebar Link
**File:** `resources/views/components/layouts/app/sidebar.blade.php` (line 82)

**UI Element:** Navigation item under "Get Involved" group with gift icon, "Support Lighthouse" label, and amber "Donate" badge. Uses `wire:navigate` for SPA-style navigation.

---

## 8. Actions (Business Logic)

Not applicable for this feature.

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

Not applicable for this feature.

---

## 14. Data Flow Diagrams

### Viewing the Donation Page

```
User navigates to /donate (or clicks "Support Lighthouse" in sidebar)
  -> GET /donate (no middleware)
    -> DonationController@index()
      -> return view('donation.index')
        -> Blade template reads config values:
          -> config('lighthouse.donation_current_month_name')
          -> config('lighthouse.donation_current_month_amount')
          -> config('lighthouse.donation_last_month_name')
          -> config('lighthouse.donation_last_month_amount')
          -> config('lighthouse.donation_goal')
          -> config('lighthouse.stripe.one_time_donation_url')
          -> config('lighthouse.stripe.donation_pricing_table_id')
          -> config('lighthouse.stripe.donation_pricing_table_key')
          -> config('lighthouse.stripe.customer_portal_url')
        -> Renders page with donation progress and Stripe embeds
```

### Making a Donation (External)

```
User clicks "Make a One-Time Gift" button
  -> Browser navigates to external Stripe checkout URL
  -> Payment processed entirely by Stripe
  -> No callback/webhook to Lighthouse Website

User selects a monthly plan from the Stripe pricing table
  -> Stripe pricing table JS handles checkout flow
  -> Subscription processed entirely by Stripe
  -> No callback/webhook to Lighthouse Website

User clicks "Manage Subscription"
  -> Browser navigates to external Stripe customer portal URL
  -> Subscription management handled entirely by Stripe
```

---

## 15. Configuration

| Key | Env Variable | Default | Purpose |
|-----|-------------|---------|---------|
| `lighthouse.donation_goal` | `DONATION_GOAL` | `60` | Monthly donation goal in dollars |
| `lighthouse.donation_current_month_amount` | `DONATION_CURRENT_MONTH_AMOUNT` | `0` | Current month's total donations |
| `lighthouse.donation_current_month_name` | `DONATION_CURRENT_MONTH_NAME` | `''` | Current month label (e.g., "March 2026"); empty hides card |
| `lighthouse.donation_last_month_amount` | `DONATION_LAST_MONTH_AMOUNT` | `0` | Previous month's total donations |
| `lighthouse.donation_last_month_name` | `DONATION_LAST_MONTH_NAME` | `''` | Previous month label; empty hides card |
| `lighthouse.stripe.donation_pricing_table_id` | `STRIPE_DONATION_PRICING_TABLE_ID` | `''` | Stripe pricing table ID for monthly plans |
| `lighthouse.stripe.donation_pricing_table_key` | `STRIPE_DONATION_PRICING_TABLE_KEY` | `''` | Stripe publishable key for pricing table |
| `lighthouse.stripe.customer_portal_url` | `STRIPE_CUSTOMER_PORTAL_URL` | `''` | URL to Stripe customer portal for subscription management |
| `lighthouse.stripe.one_time_donation_url` | `STRIPE_ONE_TIME_DONATION_URL` | `''` | URL to Stripe one-time donation checkout; empty hides card |

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/Donation/DonationDashboardTest.php` | 2 | Page loads and sidebar link |

### Test Case Inventory

**DonationDashboardTest:**
1. `it('loads successfully')` — verifies GET `/donate` returns 200 and uses `donation.index` view
2. `it('is linked in the sidebar')` — verifies the dashboard page contains "Donate" text and the donate route URL

### Coverage Gaps

- **No test for unauthenticated access** — both tests use `loginAsAdmin()`, but the route has no auth middleware. Should verify unauthenticated users can access the page.
- **No test for conditional card rendering** — the view conditionally shows current month, last month, and one-time donation cards based on config values. These conditionals are untested.
- **No test for Stripe pricing table rendering** — the embedded Stripe pricing table and its configuration are untested.
- **No test for empty/missing config values** — behavior when config values are empty or missing is not tested.

---

## 17. File Map

**Models:** None

**Enums:** None

**Actions:** None

**Policies:** None

**Gates:** None

**Notifications:** None

**Jobs:** None

**Services:** None

**Controllers:**
- `app/Http/Controllers/DonationController.php`

**Volt Components:** None

**Views:**
- `resources/views/donation/index.blade.php`

**Routes:**
- `donate` — `GET /donate`

**Migrations:** None

**Console Commands:** None

**Tests:**
- `tests/Feature/Donation/DonationDashboardTest.php`

**Config:**
- `config/lighthouse.php` — `donation_goal`, `donation_current_month_amount`, `donation_current_month_name`, `donation_last_month_amount`, `donation_last_month_name`, `stripe.donation_pricing_table_id`, `stripe.donation_pricing_table_key`, `stripe.customer_portal_url`, `stripe.one_time_donation_url`

**Other:**
- `resources/views/components/layouts/app/sidebar.blade.php` (sidebar navigation link)

---

## 18. Known Issues & Improvement Opportunities

1. **Donation amounts are manually configured** — Current and previous month donation amounts are set via environment variables and must be manually updated. There is no integration with Stripe's API to automatically fetch donation totals. Consider adding a scheduled command that queries Stripe for monthly totals.

2. **No Stripe webhook integration** — The application has no webhook endpoint for Stripe events. This means there is no automated tracking of donations, no ability to thank donors in-app, and no way to tie donations to user accounts.

3. **Tests use authenticated admin** — Both tests log in as admin, but the route has no auth middleware. The tests should verify that unauthenticated users can access the donation page.

4. **Stripe pricing table rendered even with empty config** — If `STRIPE_DONATION_PRICING_TABLE_ID` or `STRIPE_DONATION_PRICING_TABLE_KEY` are empty, the `<stripe-pricing-table>` element is still rendered with empty attributes, which would show a broken or empty Stripe widget. The one-time donation card and month cards correctly use `@if` guards, but the pricing table does not.

5. **No content management** — The page text ("LighthouseMC runs off of support...") is hardcoded in the Blade template. Unlike other content that uses the CMS Pages feature, this page cannot be edited by staff through the admin interface.

6. **External script loaded without nonce** — The Stripe pricing table JS (`https://js.stripe.com/v3/pricing-table.js`) is loaded with `async` but without a CSP nonce, which could be an issue if a Content Security Policy is enforced in the future.
