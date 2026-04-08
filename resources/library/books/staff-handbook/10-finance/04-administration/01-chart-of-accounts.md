---
title: 'Chart of Accounts'
visibility: staff
order: 1
summary: 'How to manage the chart of accounts — adding, editing, and deactivating accounts.'
---

## Overview

The **Chart of Accounts** is the list of all financial accounts used by the Finance System. Every journal entry line must be assigned to an account. The chart comes pre-seeded with standard accounts, but you can add more, edit existing ones, or deactivate accounts you no longer use.

Requires **Finance - Manage**.

## Getting There

Click **Accounts** in the Finance nav.

## Account Types

Each account has a type that determines how it behaves:

| Type | Examples | Normal Balance |
|---|---|---|
| Asset | Bank accounts, Cash on Hand | Debit |
| Liability | Amounts owed | Credit |
| Net Assets | Unrestricted and Restricted equity | Credit |
| Revenue | Donations, Contributions | Credit |
| Expense | Hosting, Fees, Software | Debit |

"Normal balance" just means which side (debit or credit) increases the account. The system uses this for correct report calculations.

## Adding an Account

1. Click **New Account**
2. Fill in:
   - **Code** — A numeric code (e.g., 5070). Codes should follow the existing numbering pattern.
   - **Name** — Clear, descriptive name (e.g., "Insurance & Compliance")
   - **Type** — Asset, liability, net_assets, revenue, or expense
   - **Subtype** — A finer categorization within the type (e.g., "hosting" or "donations"). Used for grouping in reports.
   - **Fund Type** — Unrestricted or restricted. Relevant for net asset accounts and restricted fund tracking.
   - **Description** — Optional. Explains the account's purpose for future reference.
   - **Is Bank Account** — Toggle on only for accounts that represent actual bank/payment accounts (e.g., RelayFi, Stripe). Bank accounts appear in reconciliation workflows.
3. Click **Save**

## Editing an Account

1. Find the account in the list
2. Click **Edit**
3. Update the name, subtype, description, or fund type as needed
4. Click **Save**

You can't change an account's type or code after creation — if you need a different type, deactivate the old account and create a new one.

## Deactivating and Reactivating Accounts

You can't delete accounts that have been used in journal entries. Instead, deactivate them:

1. Find the account
2. Click **Deactivate**
3. Confirm the action

Inactive accounts no longer appear in account dropdowns when recording transactions, but their historical data remains. To bring an account back, click **Reactivate**.

## Standard Accounts (Pre-Seeded)

The following accounts are seeded by default and should not normally be modified:

| Code | Name | Type |
|---|---|---|
| 1000 | Cash on Hand | Asset |
| 1010 | Stripe Account | Asset (Bank) |
| 1020 | RelayFi Checking | Asset (Bank) |
| 1030 | RelayFi Savings | Asset (Bank) |
| 3000 | Net Assets — Unrestricted | Net Assets |
| 3100 | Net Assets — Restricted | Net Assets |
| 4000 | Donations — General | Revenue |
| 4100 | Contributions — Leadership | Revenue |
| 4200 | Other Income | Revenue |
| 5000–5060 | Hosting, Software, Fees, etc. | Expense |

Add new accounts rather than repurposing these, so historical reporting stays consistent.
