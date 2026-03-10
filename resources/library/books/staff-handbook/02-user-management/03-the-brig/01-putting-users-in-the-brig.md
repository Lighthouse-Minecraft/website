---
title: 'Putting Users in the Brig'
visibility: staff
order: 1
summary: ''
---

## Overview

The **Brig** is the Lighthouse discipline system. Putting a user in the Brig restricts their access to community features, removes their Minecraft whitelist, and strips their Discord roles. Use it when a member has violated community rules or their behavior warrants restricted access.

## Who Can Do This

- Admins
- Junior Crew and above in the **Quartermaster** department
- Junior Crew and above in the **Command** department

You cannot put another staff member in the Brig, and you cannot brig yourself.

## How to Put a User in the Brig

### From the Stowaway Widget

1. Open the **Stowaway Users** widget on your Dashboard
2. Click the **Put in Brig** button next to the user
3. In the modal, enter a **reason** (minimum 5 characters) explaining why
4. Optionally set a **timer** (1-365 days) -- this doesn't auto-release them, but notifies them when the timer expires and they become eligible to appeal
5. Click **Confirm**

### From a User's Profile

1. Navigate to the user's **profile page**
2. Click the **Actions** menu on their profile card
3. Select **Put in Brig**
4. Enter a **reason** and optional **timer** in the modal
5. Click **Confirm**

## What Happens Immediately

When you put a user in the Brig, the system automatically:

- Sets their account as brigged with the reason you entered
- **Bans all their Minecraft accounts** -- removes them from the server whitelist and removes all luckperms permissions
- **Strips all their Discord roles** -- removes managed roles from their Discord accounts
- Records the action in the **activity log**
- Sends the user a **notification** about their brig placement
- Sets their first appeal availability to **24 hours** from now unless you entered a longer timer

## The Brig Timer

The timer is optional but recommended. It sets a date when the user will be notified that they're eligible to submit an appeal. Important things to know:

- The timer does **not** automatically release the user -- staff must manually release them
- A scheduled task checks daily for expired timers and sends the user a notification
- If you don't set a timer, the user can still appeal after the initial 24-hour cooldown

## What the User Sees

When a brigged user logs in, their Dashboard is replaced with a **Brig card** showing:

- That they're in the Brig
- The reason you entered
- When they can submit an appeal (or that they can appeal now)
- A form to submit an appeal message

They lose access to most community features while brigged.

## Important Notes

- The Brig blocks access to community content, Minecraft, and Discord account linking through authorization gates -- you don't need to manually revoke anything
- Always write a clear, factual reason. The user will see it, and other staff can review it in the activity log
- The Brig also handles non-disciplinary situations (parental pending, parental disabled, age lock) -- those are managed automatically by the system, not by staff
