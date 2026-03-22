---
title: 'Managing Rank Roles'
visibility: officer
order: 4
summary: 'How admins assign roles to staff ranks so all staff at that rank share permissions.'
---

## Overview

In addition to position-specific roles, roles can be assigned to **staff ranks** -- Junior Crew Member, Crew Member, and Officer. Everyone at that rank automatically gets the rank's roles. This is how baseline permissions are set for each level of staff.

For example, you might assign "Staff Access" and "Ticket - User" to the Crew Member rank so every Crew Member can access the Ready Room and handle tickets without needing those roles on each individual position.

## Who Can Do This

Only **Admins** can manage rank roles. Staff with the Site Config - Manager role can view the rank cards but cannot edit them.

## Where to Find It

1. Go to the [Admin Control Panel]({{url:/acp}}) and select the **Config** category
2. Open the **Staff Positions** tab
3. At the top of the page, you'll see three **rank cards** -- one for each rank

Each card shows the rank name and the roles currently assigned to it.

## Adding or Removing Roles

1. Click the **Edit** button on the rank card you want to change
2. The grouped role picker opens, showing all roles organized by feature group
3. Click any role badge to toggle it on or off
4. Changes take effect immediately

The picker works the same way as the position role picker -- groups are collapsible, and assigned roles show in color with a check icon.

## How Rank Roles Interact with Position Roles

A staff member's total permissions come from combining their rank roles and position roles. There's no conflict -- if you have "Ticket - User" from your rank and "Meeting - Manager" from your position, you get both.

Important: **ranks do not inherit from each other**. If you assign "Staff Access" to Crew Member, Officers do NOT automatically get it. You need to assign it to the Officer rank separately if Officers should have it too.

## Viewing Rank Roles

**On the Staff Positions page:** The three rank cards at the top show colored role badges for each rank. All staff with access to this page can see the cards.

**On profile pages:** Staff members see a dedicated **Rank Roles** section in the Staff Details card (e.g., "Crew Member Roles") separate from their position roles. This helps everyone understand which permissions come from rank vs. position.

## Typical Rank Role Setup

Here's a common starting configuration:

| Rank | Typical Roles |
|------|--------------|
| Junior Crew Member | Staff Access |
| Crew Member | Staff Access, Ticket - User, Task - Department, Meeting - Department |
| Officer | Staff Access, Ticket - User, Ticket - Manager, Task - Manager, Meeting - Secretary, Internal Note - Manager, Applicant Review - Department |

Your community's setup may differ -- admins can customize this to match how your team operates.
