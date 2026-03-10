---
title: 'How Discord Linking Works'
visibility: staff
order: 1
summary: 'Understanding Discord OAuth linking and account statuses.'
---

## Overview

Members connect their Discord account to the website using **OAuth2** -- they click a "Link Discord" button, authorize the connection through Discord, and the account is linked. Unlike Minecraft linking, there's no verification code step.

## Who Can Link

Members need to meet all of these conditions:
- At least **Stowaway** membership level
- **Not** in the Brig
- Parent allows Discord (for child accounts)

## Account Statuses

| Status | Meaning |
|---|---|
| **Active** | Linked and working normally -- roles are synced |
| **Brigged** | User is in the Brig -- all managed roles have been stripped |
| **Parent Disabled** | Parent disabled Discord access via the Parent Portal |

## What Gets Synced

Once linked, the website automatically manages these Discord roles:

- **Membership level roles** -- Traveler, Resident, or Citizen roles matching their website level
- **Staff department roles** -- department-specific roles when assigned to a staff position
- **Staff rank roles** -- Jr Crew, Crew Member, or Officer roles

These roles are added and removed automatically as things change. You don't need to manually manage Discord roles for linked members.

## Viewing Linked Accounts

You can see all linked Discord accounts in the **Admin Control Panel** under the Discord Accounts tab in the Users category. This shows each account's Discord username, linked website user, status, and when it was linked.

## Automatic Actions

The following happen automatically:

- **Brig entry** -- all managed roles are stripped, account status set to Brigged
- **Brig release** -- roles are restored, account status set back to Active
- **Promotion/demotion** -- membership level Discord roles are updated
- **Staff position change** -- department and rank Discord roles are updated
- **Parent disabling access** -- roles stripped, status set to Parent Disabled

## Discord Notifications

Members with linked Discord accounts can receive notifications via **Discord DM** in addition to email and Pushover. This includes ticket replies, announcements, and other system notifications.

## The Discord API Log

All Discord API calls (role adds, role removes, DMs sent) are logged. Officers and Engineering staff can view this log in the **Admin Control Panel** under Logs. This is helpful for debugging if roles aren't syncing correctly.

## Important Notes

- Discord role syncing is handled via the Discord bot API -- the bot must be online and have proper permissions in the server for syncing to work
- If syncing fails, the error is logged but the website action still completes. Check the API log if roles seem out of sync
- Announcements can be automatically cross-posted to a configured Discord channel when published
