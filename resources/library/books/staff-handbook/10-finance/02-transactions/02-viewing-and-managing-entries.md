---
title: 'Viewing and Managing Journal Entries'
visibility: staff
order: 2
summary: 'How to use the Journal Entries list, filter entries, post drafts, create manual entries, and reverse mistakes.'
---

## Overview

The **Journal** page shows all journal entries — drafts and posted. You can filter by date, type, account, vendor, and tag. Finance - Record users can also post draft entries, create manual entries, and reverse posted entries if corrections are needed.

## Getting There

Click **Journal** in the Finance nav.

## Filtering the List

Use the filter bar at the top to narrow down entries:

- **Date From / Date To** — Filter by entry date
- **Type** — Income, expense, transfer, journal (manual), or closing
- **Account** — Show only entries that include a specific account
- **Vendor** — Filter by vendor
- **Tag** — Filter by tag

Click **Clear Filters** to reset.

## Reading the Entry List

Each row shows the date, description, type, status (draft or posted), and the amount. If an entry is related to another (a reversal or the original being reversed), you'll see a "Reverses #ID" or "Reversed by #ID" link on the row.

Click on an entry to see its full debit/credit lines.

## Posting a Draft Entry

If an entry is in **Draft** status, a **Post** button appears on the entry detail view. Click it to post the entry. Once posted, it counts in all reports and balances and cannot be edited.

You need **Finance - Record** to post entries.

## Creating a Manual Journal Entry

For complex entries with more than two lines (or entries that don't fit income/expense/transfer):

1. Click **New Entry** in the top-right, then select **Manual Entry**
2. Add lines one at a time — each line needs an account, a side (debit or credit), and an amount
3. Add as many lines as needed. The form shows running totals for total debits and total credits.
4. The entry must be **balanced** (total debits = total credits) before you can post it. The balance indicator shows whether you're balanced.
5. Click **Save as Draft** or **Post**

Manual entries appear in the journal with type "Journal."

## Reversing an Entry

If a posted entry was recorded incorrectly, use **Reverse** to correct it rather than trying to edit or delete it. Reversing creates a new draft entry with all the debits and credits flipped, which zeroes out the effect of the original entry.

To reverse:
1. Find the posted entry in the Journal list
2. Click **Reverse** (only visible on posted entries that haven't already been reversed)
3. A new draft reversing entry is created automatically
4. Review the reversing entry, then post it

The reversing entry is dated today (or placed in the nearest open period if today is in a closed period). The original entry's row will show "Reversed by #ID" after the reversal is posted.

You need **Finance - Record** to reverse entries.

## Important Notes

- You cannot edit or delete a posted entry — only reverse it.
- You cannot post to a closed fiscal period.
- Draft entries don't appear in any reports or balances until posted.
