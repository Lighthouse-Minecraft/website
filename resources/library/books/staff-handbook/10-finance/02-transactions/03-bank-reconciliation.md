---
title: 'Bank Reconciliation'
visibility: staff
order: 3
summary: 'How to reconcile a bank account for a fiscal period against the bank statement.'
---

## Overview

**Bank reconciliation** is the process of matching the transactions in the Finance System against your bank statement to confirm they agree. Each bank account must be reconciled before a fiscal period can be closed.

Requires **Finance - Record**.

## Getting There

Go to **Finance → Periods**, then click the reconciliation link for the bank account and period you want to reconcile. Each bank account has its own link per period.

## What You'll See

The reconciliation page has two columns:

- **Uncleared Items** — Posted journal entry lines for this account/period that haven't been matched to the statement yet
- **Cleared Items** — Lines you've confirmed appear on the bank statement

At the top, you enter the **Statement Ending Balance** from your bank statement. The summary panel shows:
- **Statement Balance** — What you entered
- **Cleared Balance** — The running total of all cleared items
- **Difference** — Statement Balance minus Cleared Balance (must be zero to complete)

## Step-by-Step Reconciliation

1. Get your bank statement for the period (from your banking portal or email)
2. Enter the **Statement Ending Balance** (the closing balance shown on the statement) in the field at the top
3. Go through the **Uncleared Items** list. For each transaction that appears on your statement:
   - Click **Clear** — it moves to the Cleared Items column
4. If you accidentally cleared the wrong item, click **Unclear** to move it back
5. Watch the **Difference** number. You're done when it reaches **$0.00**
6. Once the difference is zero and you've entered a statement balance, click **Complete Reconciliation**

## What Happens When You Complete

The reconciliation is marked as **Completed** for that account/period. Once all bank accounts for a period are reconciled, the **Close Period** button becomes available on the Periods page.

## Tips

- Work through the statement line by line, top to bottom — it's less likely you'll miss something.
- If the difference isn't zeroing out, look for transactions in the uncleared list that don't appear on the statement (they may belong to a different period or were entered with the wrong date).
- A difference that's exactly double a known amount often means an entry was cleared when it shouldn't have been.
- Items in the Finance System but not on the statement are called "outstanding" — this is normal for checks or transfers that haven't cleared yet. Leave them uncleared and they'll appear in next month's reconciliation.
