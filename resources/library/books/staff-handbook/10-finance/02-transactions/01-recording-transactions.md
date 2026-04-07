---
title: 'Recording Income, Expenses, and Transfers'
visibility: staff
order: 1
summary: 'How to create income, expense, and transfer journal entries using the guided entry form.'
---

## Overview

The **Create Journal Entry** page walks you through recording income, expenses, and bank transfers. It handles the double-entry accounting automatically — you fill in the details, it generates the correct debit and credit lines.

Requires **Finance - Record**.

## Getting There

From any Finance page, click **Journal** in the top nav, then click **New Entry** in the top-right corner.

## Entry Types

The form has three tabs — pick the one that matches your transaction:

- **Income** — Money received (donations, contributions). Records a debit to the bank account and a credit to the revenue account.
- **Expense** — Money paid out. Records a debit to the expense account and a credit to the bank account.
- **Transfer** — Moving money between bank accounts. Records a debit to the destination account and a credit to the source account.

## Creating an Income Entry

1. Select the **Income** tab
2. Fill in the fields:
   - **Date** — The actual transaction date (not today's date unless it happened today)
   - **Description** — A clear, brief description (e.g., "Stripe donation — John S.")
   - **Revenue Account** — Which revenue account this belongs to (e.g., Donations — General)
   - **Bank Account** — Which bank account received the funds (e.g., RelayFi Checking)
   - **Amount** — Dollar amount
   - **Donor Email** — Optional. The donor's email if you have it.
   - **Restricted Fund** — Optional. If this donation was given for a specific fund (e.g., Server Fund Drive 2025), select it here.
   - **Reference** — Optional. A transaction ID, check number, or other reference.
   - **Tags** — Optional. Assign any relevant tags for reporting.
3. Click **Preview**
4. Review the projected debit/credit lines to confirm they look right
5. Click **Save as Draft** to save without posting, or **Post** to record it immediately

## Creating an Expense Entry

1. Select the **Expense** tab
2. Fill in the fields:
   - **Date** — The actual payment date
   - **Description** — What was paid for (e.g., "Monthly Minecraft hosting — Apex")
   - **Expense Account** — Which expense category (e.g., Minecraft Hosting)
   - **Bank Account** — Which account was debited
   - **Amount** — Dollar amount
   - **Vendor** — Optional but recommended. Select or create a vendor. Click the search icon to open the vendor picker.
   - **Restricted Fund** — Optional. If this expense was paid from a restricted fund.
   - **Reference** — Optional.
   - **Tags** — Optional.
3. Click **Preview** and verify the lines
4. Click **Save as Draft** or **Post**

## Creating a Transfer Entry

1. Select the **Transfer** tab
2. Fill in the fields:
   - **Date** — Transfer date
   - **Description** — What the transfer is for (e.g., "Move savings to checking for hosting payment")
   - **From Account** — Bank account sending the funds
   - **To Account** — Bank account receiving the funds
   - **Amount** — Dollar amount
3. Click **Preview** and verify
4. Click **Save as Draft** or **Post**

## Draft vs. Posted

- **Draft** entries are saved but not counted in any reports or balances. Use drafts when you need someone to review before it's official.
- **Posted** entries are final and appear in all reports. Posted entries can only be undone by creating a reversing entry — they can't be edited or deleted.

The system won't let you post to a closed fiscal period. If the date falls in a closed period, you'll get an error — contact your Finance - Manage person to discuss the correct approach.

## Complex Entries

For transactions that don't fit the income/expense/transfer pattern (e.g., depreciation, adjusting entries, or anything requiring more than two lines), use the **Manual Entry** button on the Journal page instead. See [[books/staff-handbook/finance/transactions/viewing-and-managing-entries|Viewing and Managing Entries]] for details on that workflow.
