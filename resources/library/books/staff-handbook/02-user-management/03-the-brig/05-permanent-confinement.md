---
title: 'Permanent Confinement'
visibility: staff
order: 5
summary: 'What permanent confinement is, when to use it, and how to apply or remove it.'
---

## Overview

**Permanent confinement** is a stronger restriction than a standard brig placement with no timer. When you mark a user as permanently confined, they cannot submit appeals and their brig dashboard card shows a specific "Permanently Confined" message rather than the normal brig status. Use this for situations where community access should not be restored under any circumstances.

## How It Differs from a Standard Brig

A regular brig placement -- even with no expiry timer -- still allows the user to submit appeals after the initial waiting period. Permanent confinement blocks that entirely:

- The appeal button and form are hidden from the user's Dashboard
- The daily brig timer check skips permanently confined users (they won't receive "your appeal window has opened" notifications)
- Their Dashboard shows "Permanently Confined" instead of the standard brig details

The user can still log in and see their Dashboard -- they just can't submit an appeal or take any action on their brig status.

## Who Can Apply This

Only staff with the **Brig Warden** role can apply or remove permanent confinement.

## Applying Permanent Confinement

1. Open the [[books/staff-handbook/user-management/the-brig/updating-brig-status|Brig Status Manager]] for the user
2. Check the **Permanent Confinement** checkbox
3. The expires-at field will be hidden automatically -- permanent users don't have a timer
4. Click **Save Changes**

The user will always receive a notification when permanent confinement is applied, regardless of the notify checkbox setting. This is intentional -- the user deserves to know this decision was made.

## Removing Permanent Confinement

Permanent confinement can be removed if circumstances change (for example, a successful contact outside the appeal system):

1. Open the Brig Status Manager for the user
2. Uncheck the **Permanent Confinement** checkbox
3. Click **Save Changes**

When permanent confinement is removed, the system automatically recalculates the user's next appeal availability -- based on their expiry date if one exists, or 24 hours from now if not. The user will always receive a notification when permanent confinement is removed.

## What Gets Logged

Permanent confinement changes are logged separately in the Brig Log:

- Applying it logs as **permanent_brig_set**
- Removing it logs as **permanent_brig_removed**

Both entries appear in the [[books/staff-handbook/user-management/the-brig/brig-log|Brig Log]] in the Admin Control Panel.

## Important Notes

- Permanent confinement is separate from the brig timer. A user can be permanently confined with or without an expiry date -- though in practice permanent users won't have a timer since appeals are blocked
- If a user contacts staff outside the system about a permanent confinement they believe was applied in error, that's a team decision -- removing permanent status is as easy as unchecking the box, but coordinate before doing it
