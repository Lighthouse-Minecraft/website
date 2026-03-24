---
title: "Repairing Minecraft Permissions"
visibility: officer
order: 2
summary: "How to run the bulk permission repair command after a server restart or data migration."
---

## Overview

The `minecraft:repair-permissions` command re-applies whitelist, rank, and staff permissions for every active Minecraft account in the database. It's the tool to reach for when the server's in-game state has drifted out of sync with the website -- for example after a server restart, a plugin reinstall, or a bulk data migration.

The command evaluates the same eligibility logic used by normal lifecycle events (promotions, brig releases, etc.). It's safe to run at any time.

## Who Can Run This

This command requires **shell (SSH) access to the server**. It's an admin/ops tool -- you can't run it from the website. If you don't have SSH access and think a bulk repair is needed, contact an Admin.

## How to Run It

### Dry Run First

Always start with a dry run. This prints everything the command would do without sending any RCON commands to the server:

```
php artisan minecraft:repair-permissions --dry-run
```

Review the output carefully. Each account shows whether it would be added to the whitelist or removed, what rank would be set, and what staff group (if any) would be applied.

### Live Execution

Once you're satisfied with what the dry run shows, run it for real:

```
php artisan minecraft:repair-permissions
```

The command will iterate every active Minecraft account and send the appropriate RCON commands. When it finishes, it prints a summary table showing how many whitelist adds, whitelist removes, rank changes, staff changes, and failures occurred.

### Pacing (Optional)

By default, the command sends commands as fast as the server can handle them. If you're concerned about flooding the Minecraft server with RCON traffic -- for example on a busy server during peak hours -- use the `--pace` flag to add a delay between commands:

```
php artisan minecraft:repair-permissions --pace=2
```

This adds a 2-second pause between outbound commands. You can set `--pace` to any whole number of seconds.

## What the Command Repairs

For each active Minecraft account, the command evaluates eligibility using the member's current website state:

- **Eligible** (Traveler or higher, not in the Brig, parent permissions allow Minecraft): adds the account to the whitelist, sets the in-game rank to match the membership level, and applies or removes the staff department group depending on whether the member holds a staff position
- **Ineligible** (Drifter/Stowaway, in the Brig, or parent has disabled Minecraft): removes the account from the whitelist

Accounts in `verifying`, `banned`, `cancelled`, `removed`, or `parent_disabled` status are skipped -- the command only processes accounts with `active` status.

## Checking the Results

After a live run, check the **MC Command Log** in the Admin Control Panel (Logs category) for any failed entries. A failed `lh` plugin command means the server responded with something other than a `Success:` prefix -- the log entry shows the exact response text, which helps narrow down what went wrong.

See [[books/staff-handbook/minecraft/server-operations/rank-and-staff-syncing|Rank & Staff Syncing]] for more detail on how the command log works and what to look for.

## Important Notes

- The repair command does **not** write to the Activity Log. Results are only visible in the MC Command Log.
- Running the command multiple times is safe -- it's idempotent. Sending a `whitelist add` for someone who's already whitelisted doesn't break anything.
- If the Minecraft server is offline when you run the command, every RCON command will fail. Wait until the server is back up and run it again.
- The `--dry-run` flag never sends RCON commands, even if the server is online. It's always safe to use for inspection.
