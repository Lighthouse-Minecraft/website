---
title: 'Rank & Staff Syncing'
visibility: staff
order: 1
summary: 'How membership levels and staff positions sync to in-game ranks.'
---

## Overview

The website automatically keeps the Minecraft server in sync with membership levels and staff positions. When something changes on the website, commands are sent to the server to update the player's in-game status.

## Membership Rank Sync

When a member's membership level changes (promotion or demotion), the system sends RCON commands to update their in-game rank for each of their active Minecraft accounts. The rank names match the membership levels: Traveler, Resident, and Citizen.

This happens automatically during:
- Staff promoting or demoting a member
- Brig entry (rank access is effectively revoked via whitelist removal)
- Brig release (ranks are restored when accounts are re-whitelisted)

## Staff Position Sync

When a staff member is assigned to or removed from a position, the system syncs their staff group on the Minecraft server:

- **Assigned to a position** -- sends a command to set their staff department group in-game
- **Removed from a position** -- sends a command to remove their staff group

This lets the Minecraft server display staff designations and grant any department-specific in-game permissions.

## RCON Commands

The website communicates with the Minecraft server using **RCON** (Remote Console). Commands are dispatched as background jobs so they don't block the website. Key commands include:

- Whitelist add/remove (for account linking and brig actions)
- Rank set (for membership level changes)
- Staff set/remove (for staff position changes)
- Verification code processing (for the linking flow)

## The Command Log

All RCON commands sent to the Minecraft server are logged. Officers and Engineering department staff can view these logs in the **Admin Control Panel** under the Logs category. This is useful for debugging if a rank sync or whitelist change didn't seem to go through.

The **MC Command Log** shows each command alongside its status (success, failed, or timeout) and the actual response text returned by the server. For `lh` plugin commands, a successful response will show `Success:` followed by a confirmation message. A failed entry will show the raw error or empty response that the plugin returned -- this is the first place to look when a rank or staff sync doesn't behave as expected.

## Important Notes

- RCON commands are sent as background jobs -- there may be a brief delay between a website action and the in-game change
- If the Minecraft server is offline when a command is sent, the job may fail. Check the command log if a sync seems stuck
- New player rewards (like starter items) are also sent via RCON when a player first verifies their account
