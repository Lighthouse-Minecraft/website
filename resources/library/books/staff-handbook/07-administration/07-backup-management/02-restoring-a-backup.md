---
title: 'Restoring a Backup'
visibility: officer
order: 2
summary: 'Step-by-step guide to restoring the database from a backup file.'
---

## Overview

Restoring a backup replaces the current database with the contents of a backup file. This is a destructive, irreversible operation -- **everything in the database after the backup was made will be lost**. Only restore when you need to, and always make a fresh backup immediately before you start.

## Who Can Do This

Only users with the **Backup Manager** role (or Admins) can initiate a restore.

## Before You Restore

Take a fresh backup first. If the restore goes wrong or the backup file is corrupted, you'll want a snapshot of the current state to fall back to.

1. Click **Create Backup Now** on the Backup Dashboard and wait for it to finish.
2. Confirm the new backup appears at the top of the **Local Backups** list.

## How to Restore

1. Go to Ready Room → **Backups**.
2. In the **Local Backups** table, find the backup file you want to restore from.
3. Click **Restore** in that row.
4. Read the confirmation carefully -- it shows you exactly which file you're restoring from.
5. Click **Restore** in the modal to confirm.

You'll see a "Restore job queued" confirmation. The restore runs in the background. If the **Take site offline during restore** setting is on (it is by default), the site will enter maintenance mode automatically and come back up when the restore finishes.

## What Happens During a Restore

- The current database is wiped and replaced with the contents of the backup file.
- If maintenance mode is enabled, the site shows an "under maintenance" page to visitors during the restore.
- All Backup Manager users receive an email notification when the restore completes or fails.

The restore matches backup type to the current database -- a SQLite backup restores to a SQLite database, a PostgreSQL backup restores to PostgreSQL, and so on. Cross-type restores (e.g., restoring a PostgreSQL backup to a SQLite database) require additional server-side tooling and may not be available.

## After a Restore

Check that the site is working as expected after the restore completes. Log out and back in to make sure your session is fresh. If anything looks wrong, you can restore again from a different backup file.

## Important Notes

- **There is no undo.** Once the restore starts, there's no way to cancel it.
- **The restore runs asynchronously.** The dashboard confirmation means the job was queued, not that it finished. Wait for the notification email to know whether it succeeded.
- **Restoring doesn't notify other staff proactively.** If you're restoring due to an incident, let your team know manually.
