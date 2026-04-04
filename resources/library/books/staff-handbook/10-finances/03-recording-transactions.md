---
title: "Recording Transactions"
visibility: staff
order: 3
summary: "How to enter income, expenses, and transfers."
---

## Overview

The treasurer (or anyone with the Treasurer or Manage role) records all ministry transactions through the **Finance Dashboard**. There are three types: income, expense, and inter-account transfer.

## Who Can Do This

**Financials - Treasurer** and **Financials - Manage** roles.

## Recording an Income or Expense

1. On the Finance Dashboard, find the entry form at the top.
2. Select **Income** or **Expense** from the type dropdown.
3. Fill in:
   - **Date** -- when the transaction happened
   - **Amount** -- in dollars (e.g., "25.00")
   - **Account** -- which account received or paid this
   - **Category** -- the top-level category, and subcategory if applicable
   - **Tags** -- optional labels (e.g., "test-server", "live-server")
   - **Notes** -- optional free-text detail
4. Click **Save Transaction**.

You'll get a confirmation toast and the new transaction appears at the top of the ledger.

## Recording a Transfer

Transfers move money between two ministry accounts without affecting income or expense totals.

1. Select **Transfer** from the type dropdown.
2. Fill in:
   - **Date** -- when the transfer happened
   - **Amount** -- in dollars
   - **From Account** -- the source account
   - **To Account** -- the destination account
   - **Notes** -- optional
3. Click **Save Transaction**.

You can't select a category or tags for a transfer -- those fields disappear when you choose Transfer type. You also can't select the same account as both source and destination.

## Editing and Deleting

You can edit or delete any transaction in an **unpublished month** by clicking the edit (pencil) or delete (trash) icon on that row in the ledger.

Once a month is published, its transactions are locked. You'll see an error message if you try to edit or delete a published transaction.

## Important Notes

- All amounts are entered in dollars (the system stores them internally as cents).
- Tags are optional but help with cross-cutting reporting -- you can filter the ledger by tag at any time.
- Transfers appear in the ledger but are excluded from all category reports and income/expense totals.
