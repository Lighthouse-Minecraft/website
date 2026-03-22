---
title: 'Managing Position Roles'
visibility: staff
order: 3
summary: 'How to assign and remove roles on staff positions using the grouped picker.'
---

## Overview

Roles are assigned to **staff positions**, not to individual people. When you assign a role to a position, whoever holds that position gets the permission. If the person leaves and someone new fills the position, the new person automatically gets the same roles.

## Who Can Do This

- **Admins** -- full access to assign and remove roles on any position
- **Site Config - Manager** role -- can manage all positions' roles except their own

## Where to Find It

1. Go to the [Admin Control Panel]({{url:/acp}}) and select the **Config** category
2. Open the **Staff Positions** tab
3. Find the position you want to manage in the table
4. Click the **Roles** button on that row

This opens the **Manage Roles** modal with the grouped role picker.

## Using the Grouped Role Picker

Roles are organized by feature group (Ticket, Task, Meeting, etc.) in collapsible sections. Each section shows how many roles from that group are assigned (e.g., "2/3").

1. Click a **group header** to expand or collapse it
2. Click any **role badge** to toggle it on or off
3. Assigned roles show in color with a check icon; unassigned roles appear faded

Changes take effect immediately. There's no save button -- each click adds or removes the role right away.

## Using Allow All

Some leadership positions need access to everything. Instead of assigning every role individually, you can enable **Allow All**. This can only be managed by admins.

1. Open the **Manage Roles** modal for the position
2. At the top, find the **Allow All Roles** toggle
3. Click **Enable**

When Allow All is on, the person in that position has every permission role automatically. The grouped picker is hidden since individual assignments aren't needed.

To turn it off, click **Disable** in the same spot. The position will go back to using only its individually assigned roles (if any).

## Viewing Roles in the Positions Table

The **Staff Positions** table has a **Roles** column that shows what each position has:

- An amber **Allow All** badge with a star icon means Allow All is enabled
- Colored badges show individually assigned roles
- "None" means no roles are assigned

## Things to Keep In Mind

- Changes take effect immediately with each click. The person holding the position gains or loses that permission right away.
- Enabling Allow All doesn't remove individually assigned roles -- it just overrides them. If you later disable Allow All, the individual roles are still there.
- You can't manage roles on your own position. This prevents accidentally giving yourself more permissions.
