---
title: "Managing Accounts, Categories, and Tags"
visibility: officer
order: 7
summary: "How to create, rename, and archive accounts, categories, and tags."
---

## Overview

The Manage role can configure the building blocks of the finance system: accounts, categories, and tags. These are the lists that treasurers use when entering transactions.

## Who Can Do This

**Financials - Manage** role only.

---

## Accounts

Go to **Accounts** (`/finances/accounts`) to see all active and archived accounts.

### Creating an Account

1. Click **New Account**.
2. Enter a name (e.g., "RelayFi Checking"), select the type (checking, savings, payment-processor, or cash), and set the opening balance in dollars.
3. Click **Save**.

The opening balance is the starting point -- it's the balance before any transactions you've entered into the system.

### Renaming or Archiving an Account

Click the edit icon on any account row to rename it. Click **Archive** to hide it from transaction entry forms. Archived accounts still appear in historical data and reports -- archiving doesn't delete anything.

---

## Categories

Go to **Categories** (`/finances/categories`) to see and manage income and expense categories.

Categories have two levels: top-level (e.g., "Infrastructure") and subcategories (e.g., "Minecraft Hosting"). The system supports exactly one level of nesting.

### Creating a Category

1. Click **New Category** (or **Add Subcategory** under an existing top-level).
2. Enter the name, select the type (income or expense), and set a sort order.
3. Click **Save**.

### Renaming or Archiving

Click the edit icon to rename. Click **Archive** to hide it from transaction entry. Archived categories don't appear in entry forms, but historical transactions still reference them.

### Category Order

The sort order controls how categories appear in dropdowns and reports. Lower numbers appear first. Adjust sort order by editing the category.

---

## Tags

Tags appear on the **Categories** page in a separate section. Tags are simple labels that can be applied to any income or expense transaction.

### Creating a Tag

Click **New Tag**, enter a name, and click **Save**. Tags are available immediately for use in transaction entry.

### Archiving a Tag

Click **Archive** on a tag to hide it from future transaction entry. Existing tagged transactions keep their tag associations.

---

## Important Notes

- Archiving is the right way to retire an account, category, or tag. Deleting is not available through the UI to protect historical data integrity.
- If a top-level category is deleted (currently only possible via admin tools), its subcategories become top-level. Design your category tree carefully.
- Changes to accounts and categories take effect immediately in the transaction entry form.
