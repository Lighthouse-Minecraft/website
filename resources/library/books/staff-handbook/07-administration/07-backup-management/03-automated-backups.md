---
title: 'Automated Backup Schedule'
visibility: officer
order: 3
summary: 'How automatic backups, cleanup, and S3 uploads are scheduled.'
---

## Overview

The server runs several backup-related tasks automatically. You don't need to manage these day-to-day -- they run on their own. This page explains what's happening and when, so you know what to expect if something looks off.

## What Runs Automatically

| Schedule | What Happens |
|----------|-------------|
| Daily at 3:00 AM | New database backup is created and saved locally |
| Daily at 4:00 AM | Old local backups are deleted (default: files older than 7 days) |
| Every 3 days at 3:30 AM | Most recent local backup is uploaded to S3 |

The daily backup and cleanup happen regardless of whether S3 is configured. The S3 upload only runs when S3 credentials are set up on the server.

## Email Notifications

When the daily backup job runs, all Backup Manager users receive an email:
- **"Database Backup Created"** -- backup succeeded, with the filename
- **"Database Backup Failed"** -- backup failed, with the error message

These notifications only come from the automated schedule -- if you create a backup manually from the dashboard, you won't get an email.

## Local Retention Policy

The server keeps 7 days of local backups by default. Files older than that are deleted automatically during the 4:00 AM cleanup. If you want to keep a backup longer than that, download it to your local machine before it expires.

## S3 Retention Policy

S3 keeps backups on a tiered schedule designed to minimize storage costs while keeping useful recovery points:

- **2 most recent uploads** -- always kept
- **1 per week** for the past 4 weeks
- **1 per month** for the past 3 months

Backups older than 3 months with no matching tier are automatically deleted from S3. This runs each time a new backup is uploaded to S3.

## If a Backup Fails

You'll get a failure notification email with the error message. Common causes:
- The database tool isn't available (`pg_dump`, `mysqldump`)
- The server is out of disk space in `storage/app/backups/`
- Permissions issue on the backups directory

If you're getting repeated failure emails, raise it with whoever manages server infrastructure.
