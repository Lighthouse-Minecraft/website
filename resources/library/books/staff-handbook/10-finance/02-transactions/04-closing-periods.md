---
title: 'Closing Fiscal Periods'
visibility: staff
order: 4
summary: 'How to close a fiscal period once all bank accounts are reconciled.'
---

## Overview

**Closing a fiscal period** locks it so no new entries can be posted to it. This preserves the historical record and signals that the period has been fully reconciled and reviewed. Closing is a one-way operation — a closed period cannot be reopened.

Requires **Finance - Record**.

## Before You Can Close

All bank accounts must be fully reconciled for the period. The **Periods** page shows the reconciliation status for each bank account under each period — look for green checkmarks or "Completed" labels.

If any bank account shows as **In Progress** or has no reconciliation started, complete that reconciliation first. See [[books/staff-handbook/finance/transactions/bank-reconciliation|Bank Reconciliation]] for steps.

## How to Close a Period

1. Go to **Finance → Periods**
2. Find the period you want to close (it must be for the current fiscal year)
3. Confirm all bank account reconciliations show as **Completed**
4. Click **Close Period**
5. Confirm the action when prompted

## What Happens When a Period Closes

- The period status changes to **Closed**
- Closing entries are automatically generated to roll net revenue/expense into net assets accounts (standard accounting practice)
- No further entries can be posted to this period
- The period's data is immediately available in all historical reports

## Important Notes

- Closing is permanent. If you realize there was an error after closing, you'll need to record an adjusting entry in the next open period (not reopen the closed one).
- Closing entries are system-generated journal entries of type "closing" — you'll see them in the journal. They're not something you create manually.
- The system prevents two users from accidentally closing the same period at the same time.
- If you get an error saying the period is already closed, refresh the page — another user may have just closed it.
