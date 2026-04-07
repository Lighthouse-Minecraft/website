---
title: 'Restricted Funds, Vendors, and Tags'
visibility: staff
order: 2
summary: 'Managing restricted funds, vendor records, and transaction tags.'
---

## Restricted Funds

### What They Are

**Restricted funds** are named funds for donations given with a specific purpose — for example, "Server Fund Drive 2025." When donors give to a restricted fund, those funds should only be used for that purpose. The Finance System tracks received, spent, and remaining balances for each fund automatically based on journal entries that reference the fund.

Requires **Finance - Manage** to create or modify funds.

### Managing Restricted Funds

Go to **Restricted Funds** in the Finance nav.

The funds list shows each fund's current **Received**, **Spent**, and **Remaining** balances, calculated from posted entries only.

**Creating a fund:**
1. Click **New Fund**
2. Enter the **Fund Name** (e.g., "Server Fund Drive 2025")
3. Add an optional **Description** to explain the fund's purpose
4. Click **Create Fund**

**Editing a fund:**
1. Click **Edit** next to the fund
2. Update the name or description
3. Click **Save Changes**

**Deactivating a fund:**
Click **Deactivate** next to the fund. Inactive funds won't appear in the fund selector when recording new transactions, but existing entries referencing the fund are unaffected. Click **Reactivate** to restore a fund.

### Using Restricted Funds When Recording Transactions

When creating an income or expense entry, use the **Restricted Fund** selector to tag the entry to a specific fund. Income entries increase "Received," expense entries increase "Spent." The Remaining balance = Received − Spent.

---

## Vendors

**Vendors** are the payees you record when logging expenses (e.g., "Apex Hosting," "Google," "Stripe"). Using vendors makes it easy to filter the journal and see total spending per vendor.

Requires **Finance - Manage** to manage the vendor list.

### Managing Vendors

Go to **Vendors** in the Finance nav.

**Creating a vendor:** Click **New Vendor**, enter the name, and click **Save**.

**Renaming a vendor:** Click **Edit**, update the name, and click **Save**.

**Deactivating a vendor:** Click **Deactivate**. Inactive vendors won't appear in vendor pickers for new transactions. Historical entries referencing the vendor are unaffected. Click **Reactivate** to restore.

---

## Tags

**Tags** are color-coded labels you can apply to journal entries for flexible categorization and filtering. Use them for things that don't fit cleanly into accounts or vendors (e.g., "annual," "reimbursable," "server-event").

Requires **Finance - Manage** to manage tags.

### Managing Tags

Go to **Tags** in the Finance nav.

**Creating a tag:**
1. Click **New Tag**
2. Enter the **Tag Name**
3. Choose a **Color** (from the Flux UI color palette)
4. Click **Save**

**Editing a tag:** Click **Edit**, update the name or color, and click **Save**.

**Deleting a tag:** Click **Delete**. You can only delete tags that aren't in use on any journal entries. If a tag is still being used, deactivate it instead (or remove it from the relevant entries first).

### Using Tags When Recording Transactions

When creating a journal entry, use the **Tags** field to search for and apply one or more tags. The journal list and reports can then be filtered by tag.
