---
title: 'How Linking Works'
visibility: staff
order: 1
summary: 'Understanding the Minecraft account linking and verification process.'
---

## Overview

Members link their Minecraft accounts through the website's Settings page. Understanding how the process works helps you troubleshoot issues when members need help.

## The Linking Process

1. The member goes to **Settings** and enters their Minecraft username, selecting Java or Bedrock edition
2. The system looks up their account via the Mojang API (Java) or GeyserMC/McProfile API (Bedrock) to find their UUID
3. The account is temporarily whitelisted on the server
4. A 6-character **verification code** is generated and displayed to the member
5. The member joins the server and enters the code using an in-game command
6. The in-game plugin sends a webhook back to the website confirming verification
7. The account status changes to **Active** and the member's in-game rank is synced to their membership level

## Account Statuses

| Status | Meaning |
|---|---|
| **Verifying** | Linking started but code not yet entered -- temporary whitelist active |
| **Active** | Verified and whitelisted -- normal operating state |
| **Banned** | User is in the Brig -- whitelist removed |
| **Removed** | User unlinked the account or staff revoked it |
| **Parent Disabled** | Parent disabled Minecraft access via the Parent Portal |
| **Cancelled** | Verification was cancelled before completion |

## Who Can Link Accounts

Members need to meet all of these conditions:
- At least **Traveler** membership level
- **Not** in the Brig
- Parent allows Minecraft (for child accounts)

Members can link up to **{{config:lighthouse.max_minecraft_accounts}}** accounts total.

## Primary Account

Members can set one account as their **primary** account. The primary account's avatar is used as their profile picture on the website.

## Automatic Syncing

Several things sync automatically when changes happen:

- **Promotion/demotion** -- the member's in-game rank updates to match their membership level
- **Staff position change** -- staff department group is synced in-game
- **Brig entry** -- all active accounts are banned (whitelist removed)
- **Brig release** -- previously active accounts are restored and re-whitelisted
- **Username changes** -- a scheduled task periodically checks for Minecraft username changes via the Mojang API
