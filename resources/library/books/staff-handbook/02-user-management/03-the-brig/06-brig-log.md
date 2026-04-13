---
title: 'Brig Log'
visibility: staff
order: 6
summary: 'How to use the Brig Log in the Admin Control Panel to review brig activity.'
---

## Overview

The **Brig Log** is an audit trail of all brig-related actions taken on the site. It lives in the Admin Control Panel under the **Logs** tab and shows who was brigged, released, or updated -- and by whom. Use it when you need to review the history of a particular user's brig status, check what another warden did, or investigate a timeline discrepancy.

## Who Can Access It

Only staff with the **Brig Warden** role can view the Brig Log. If you have this role, the Logs tab in the ACP will appear automatically, defaulting to the Brig Log view.

## How to Access It

1. Go to the **Admin Control Panel** (`{{url:/acp}}`)
2. Click the **Logs** tab
3. Select **Brig Log**

## What It Shows

The Brig Log displays entries in reverse chronological order (newest first), paginated 25 per page. Each row shows:

- **Date/Time** -- when the action occurred (in your timezone)
- **Target User** -- the user the action was taken on, linked to their profile
- **Action** -- a badge indicating what happened (see below)
- **By** -- the staff member who took the action, or "System" for automated actions
- **Description** -- a plain-language summary of what changed

## Action Types

| Badge | Meaning |
|-------|---------|
| user_put_in_brig | User was placed in the Brig |
| user_released_from_brig | User was released from the Brig |
| brig_status_updated | Reason, timer, or quick release was applied |
| brig_appeal_submitted | User submitted an appeal |
| permanent_brig_set | Permanent confinement was applied |
| permanent_brig_removed | Permanent confinement was removed |

## Important Notes

- The Brig Log does not have a search or filter -- entries are shown in date order across all users. To see all history for a specific user, use the Activity Log in their profile (if you have access to the general activity log)
- Entries from automated processes (like scheduled timer checks) appear with "System" in the By column
- The log is read-only -- you can't edit or delete entries
