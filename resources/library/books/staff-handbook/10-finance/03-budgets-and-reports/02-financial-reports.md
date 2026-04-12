---
title: 'Financial Reports'
visibility: staff
order: 2
summary: 'How to use the six financial report tabs: Activities, General Ledger, Trial Balance, Balance Sheet, Cash Flow, and Budget vs. Actual.'
---

## Overview

The **Reports** page has six tabs covering different views of the financial data. All reports use posted entries only — drafts are excluded from everything here.

Requires **Finance - View** (or higher).

## Getting There

Click **Reports** in the Finance nav.

## Shared Filters

Most tabs share a common set of filters at the top:

- **FY Year** — Fiscal year to show data for
- **Period** — Narrow to a specific month (optional)
- **Date From / Date To** — Narrow to a specific date range (optional)

The **Budget vs. Actual** tab doesn't use period or date filters — only the FY selector applies.

## Statement of Activities

Shows revenue and expense totals for the selected time range. Revenue accounts are listed with their total income, expense accounts with their total spending, and the bottom line shows net change (revenue minus expenses).

This is the primary report for answering "how did we do this month/year?" Use the Period or date filters to zoom in on a specific time range.

## General Ledger

Shows every posted transaction line for a single selected account, in date order, with a running balance. Use this to trace every transaction that touched a specific account.

Filters:
- **Account** — Select which account to view (required)
- **Date From / Date To** — Optional date range
- **Entry Type** — Optional type filter (income, expense, transfer, etc.)

You can export the General Ledger to a CSV file by clicking **Export CSV**. The export includes all filtered rows.

## Trial Balance

Shows every account with its total debits and credits for the selected period. The bottom row shows whether debits and credits are balanced (they should always be). Use this to quickly check that the books are in order.

If you see an "Unbalanced" warning, something unexpected has happened — contact whoever manages the Finance System.

## Balance Sheet

Shows the financial position as of a selected date:

- **Assets** — The cumulative balance of all asset accounts
- **Net Assets** — Split into unrestricted and restricted net assets
- **Assets = Net Assets** check — Should always be true

The Balance Sheet reflects cumulative history, not just a single period. Use the **As of Date** filter to see the balance as of any date.

## Cash Flow (Operating Activities Only)

Shows cash inflows (revenue) and outflows (expenses) for the selected period, with a net change in cash. This report approximates cash flow from operating activities based on revenue and expense entries — it doesn't track actual bank movements or timing differences.

The tab is labeled **Operating Activities Only** to make clear that it's not a full statement of cash flows. It's useful for a quick sense of net cash movement, but not a substitute for a full cash flow analysis.

## Budget vs. Actual

Shows budgeted vs. actual amounts per account per month for the selected fiscal year. This is the same view as the Budget vs. Actual tab on the Budgets page, just from a reporting perspective.

Color coding follows the same rules: green = favorable, red = unfavorable.

## Printing and PDF Export

All report tabs are print-friendly. Use your browser's **Print** function (Ctrl+P / Cmd+P) to print or save as PDF. The print layout hides navigation and filters so only the report content shows.

Printed reports include a header with the **Lighthouse logo image**, organization name ({{config:app.name}}), the report title (e.g., "General Ledger" or "Balance Sheet"), and the date the report was generated. This makes printed or PDF copies clearly identifiable.
