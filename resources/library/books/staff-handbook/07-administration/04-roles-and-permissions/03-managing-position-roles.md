---
title: 'Managing Position Roles'
visibility: staff
order: 3
summary: 'How to assign and remove roles on staff positions.'
---

## Overview

Roles are assigned to **staff positions**, not to individual people. When you assign a role to a position, whoever holds that position gets the permission. If the person leaves and someone new fills the position, the new person automatically gets the same roles.

## Who Can Do This

- **Admins** -- full access to assign and remove roles on any position
- **Manage Site Config** Role -- Can manage all positions' roles except their own.

## Where to Find It

1. Go to the [Admin Control Panel]({{url:/acp}}) and select the **Config** category
2. Open the **Staff Positions** tab
3. Find the position you want to manage in the table
4. Click the **Roles** button on that row

This opens the **Manage Roles** modal for that position.

## Assigning a Role

1. Open the **Manage Roles** modal for the position
2. Under **Add Role**, select a role from the dropdown
3. Click the **Add** button

The role appears immediately in the **Assigned Roles** list. The person holding that position now has the permission.

## Removing a Role

1. Open the **Manage Roles** modal for the position
2. Find the role you want to remove in the **Assigned Roles** list
3. Click the **X** button next to the role badge

The role is removed immediately. The person holding the position loses that permission right away.

## Using Allow All

Some leadership positions need access to everything. Instead of assigning every role individually, you can enable **Allow All**. This can only be managed by a user with the **Admin** permission.

1. Open the **Manage Roles** modal for the position
2. At the top, find the **Allow All Roles** toggle
3. Click **Enable**

When Allow All is on, the person in that position has every permission role automatically. You don't need to assign individual roles -- the modal will show a message that individual assignments aren't needed.

To turn it off, click **Disable** in the same spot. The position will go back to using only its individually assigned roles (if any).

## Viewing Roles in the Positions Table

The **Staff Positions** table has a **Roles** column that shows what each position has:

- An amber **Allow All** badge with a star icon means the position has Allow All enabled
- Colored badges show individually assigned roles
- "None" means no roles are assigned to that position

## Things to Keep In Mind

- Roles take effect immediately. There's no save button or confirmation step when adding or removing individual roles.
- If you remove a role from a position while someone holds it, they lose that permission right away.
- Enabling Allow All doesn't remove individually assigned roles -- it just overrides them. If you later disable Allow All, the individual roles are still there.
- You can't assign the same role twice to a position. The system handles this automatically.
