---
title: 'Finance Roles and Access'
visibility: staff
order: 1
summary: 'The three Finance roles and what each one can do in the Finance System.'
---

## Overview

The Finance System uses three roles to control who can do what. These are assigned separately from your normal staff rank — holding an Officer rank doesn't automatically give you Finance access. You need to be explicitly assigned one of the Finance roles.

## The Three Finance Roles

### Finance - View

Read-only access to the Finance System. You can see journal entries, reports, fiscal periods, and all account information — but you can't create, edit, or post anything.

Use this role for staff members who need financial visibility without write access.

### Finance - Record

Everything Finance - View can do, plus:
- Create income, expense, and transfer journal entries (save as draft or post directly)
- Create manual journal entries (for complex multi-line transactions)
- Post draft entries
- Reverse posted entries
- Perform bank reconciliations
- Close fiscal periods (once all bank accounts are reconciled)

This is the working role for anyone actively recording transactions.

### Finance - Manage

Everything Finance - Record can do, plus:
- Edit budget amounts and copy budgets from a prior year
- Manage the chart of accounts (add, edit, deactivate accounts)
- Manage restricted funds (create, edit, deactivate)
- Manage vendors (create, rename, deactivate)
- Manage transaction tags (create, edit, delete)

This role is for whoever oversees the Finance System. Only one or two people typically need it.

## Permissions Summary

| Task | Finance - View | Finance - Record | Finance - Manage |
|---|---|---|---|
| View journal entries and reports | ✅ | ✅ | ✅ |
| View fiscal periods and account list | ✅ | ✅ | ✅ |
| Create income/expense/transfer entries | ❌ | ✅ | ✅ |
| Reverse posted entries | ❌ | ✅ | ✅ |
| Bank reconciliation | ❌ | ✅ | ✅ |
| Close fiscal periods | ❌ | ✅ | ✅ |
| Set and edit budgets | ❌ | ❌ | ✅ |
| Manage chart of accounts | ❌ | ❌ | ✅ |
| Manage restricted funds | ❌ | ❌ | ✅ |
| Manage vendors and tags | ❌ | ❌ | ✅ |

## Getting to the Finance System

Go to **Finance → Journal** (or any Finance nav link) to reach the Staff Finance Portal. From there, the top navigation bar takes you to every section you have access to.

Finance - Manage users see additional nav buttons: **Accounts**, **Budgets**, **Restricted Funds**, **Vendors**, and **Tags**.

Note: Residents and above can also see a public-facing finance summary at `/finance` and a more detailed community view at `/finance/overview`, but the Staff Finance Portal is separate and only accessible to users with a Finance role.
