---
title: 'Updating Brig Status'
visibility: staff
order: 4
summary: 'How to change a brigged user''s reason, timer, or release them without re-brigging.'
---

## Overview

The **Brig Status Manager** lets you update a brigged user's details in-place -- changing the reason, adjusting the timer, or releasing them -- without having to release and re-brig them. This is the right tool when you need to correct a reason, extend or shorten a timer, or quickly release someone after reviewing their appeal.

## Who Can Do This

Only staff with the **Brig Warden** role can use the Brig Status Manager.

## Where to Find It

The Brig Status Manager is accessible from three places:

- **Brig Warden Widget** on your Dashboard -- click **Manage** on any user in the approaching release list or View All modal
- **A user's profile page** -- look for the Manage Brig Status option in the Actions menu (only shown for brigged users)
- **A Brig Appeal thread** -- click the **Manage Brig Status** button in the thread header

All three open the same Brig Status Manager modal, pre-filled with the user's current brig details.

## Updating the Reason or Timer

1. Open the Brig Status Manager for the user
2. Edit the **Reason** field (minimum 5 characters)
3. Adjust the **Expires At** date and time, or clear it to make the brig indefinite
4. Optionally uncheck **Notify user of updates?** if you don't want to send the user a notification for this particular change
5. Click **Save Changes**

If nothing has changed from the current values, saving won't do anything -- no notification is sent and no log entry is written.

## The Notify Checkbox

By default, **Notify user of updates?** is checked. The user will receive a notification when you save changes. If you're making a minor correction (like fixing a typo in the reason), you can uncheck it to skip the notification.

The notify option is automatically forced on -- and locked -- when you toggle the **Permanent Confinement** checkbox. Permanent status changes always notify the user, regardless of the toggle state.

## Releasing a User (Quick Release)

The Brig Status Manager includes a **Quick Release** section at the bottom. Use this to release a user directly from the manager without navigating to their profile:

1. Enter a **release reason** (minimum 5 characters) in the release reason field
2. Click **Release from Brig**

Releasing from here has the same effect as releasing from the profile page: Minecraft access is restored, Discord roles are re-synced, and the user receives a notification.

## What Gets Logged

Every change made through the Brig Status Manager is recorded in the **Brig Log** in the Admin Control Panel:

- Reason or timer updates log as **brig_status_updated** with a summary of what changed
- Quick releases log as **brig_status_updated** followed by the standard release log entry
- Permanent flag changes log separately (see [[books/staff-handbook/user-management/the-brig/permanent-confinement|Permanent Confinement]])
