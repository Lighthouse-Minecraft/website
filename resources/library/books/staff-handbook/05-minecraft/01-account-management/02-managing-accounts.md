---
title: 'Managing Accounts'
visibility: staff
order: 2
summary: 'How to view, revoke, and troubleshoot member Minecraft accounts.'
---

## Overview

Staff can view and manage linked Minecraft accounts through the Admin Control Panel and member profile pages. This is useful when helping members with linking issues or when you need to revoke access.

## Viewing Accounts

### From a Member's Profile

When viewing a member's profile, you can see their linked Minecraft accounts, including usernames, UUIDs, account type (Java/Bedrock), and current status.

### From the Admin Control Panel

Go to the **Admin Control Panel** and open the **Minecraft Accounts** tab under the Users category. This shows all linked accounts across the community in a searchable, sortable table.

## Common Troubleshooting

### "My username isn't found"

- Make sure they're entering the exact Minecraft username (case-sensitive for Java)
- For Bedrock players, they should enter their Xbox gamertag
- The Mojang/GeyserMC API may be temporarily down -- try again later

### "My verification code expired"

- Verification codes have a time limit. The member can generate a new one by starting the linking process again
- Make sure they're joining the correct server

### "I can't link my account"

Check that the member:
- Is at least Traveler rank
- Is not in the Brig
- Hasn't reached the account limit ({{config:lighthouse.max_minecraft_accounts}} accounts)
- Has parental permission (if a child account)

### "I'm whitelisted but can't join"

- The server may need a moment to process the whitelist command
- If they were recently released from the Brig, their accounts should auto-restore -- check that the status shows Active

## Important Notes

- The Minecraft Accounts tab in the ACP lets you see account statuses at a glance -- use it to quickly verify whether someone's account is active, banned, or in another state
- When a member is brigged, all their Minecraft accounts are automatically banned. You don't need to manually remove them
- Account data is preserved when unlinked -- the record stays in the system with a "Removed" status
