---
title: "Viewing the Transaction Ledger"
visibility: staff
order: 2
summary: "How to view and filter the full transaction history."
---

## Overview

The **Finance Dashboard** (`/finances/dashboard`) is the main page for viewing all ministry transactions. Anyone with a finance role can see the full ledger -- income, expenses, and inter-account transfers.

## Who Can Do This

All three finance roles (View, Treasurer, Manage) can view the ledger.

## Reading the Ledger

The ledger table shows every transaction with:

- **Date** -- when the transaction occurred
- **Type** -- Income, Expense, or Transfer
- **Account** -- which account it's in (or "from → to" for transfers)
- **Category** -- top-level and subcategory (blank for transfers)
- **Amount** -- in dollars
- **Tags** -- any labels applied to the transaction
- **Notes** -- optional free-text

Transactions are sorted newest first by default.

## Filtering Transactions

You can narrow the ledger with these filters:

- **Date from / Date to** -- limit to a date range
- **Account** -- show only a specific account
- **Category** -- show a specific category (includes transactions in its subcategories)
- **Tag** -- show only transactions tagged with a specific label

Set filters and the list updates automatically.

## Account Balances

The dashboard also shows the current balance of each active account at the top of the page. These are running totals -- opening balance plus all income, minus all expenses, and adjusted for transfers in or out.

## Important Notes

- Transactions in a **published month** are read-only. You'll see them in the ledger but can't edit or delete them.
- Transfers are shown in the ledger but don't count as income or expense -- they're just movement between accounts.
