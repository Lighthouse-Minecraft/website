---
title: 'Using the Backup Dashboard'
visibility: officer
order: 1
summary: 'How to create, download, upload, and delete backups from the dashboard.'
---

## Overview

The **Backup Dashboard** is your central hub for managing database backups. You can reach it from the Ready Room -- the **Backups** button only appears if you have the Backup Manager role.

The dashboard shows three backup sources: local files stored on the server, backups stored in S3 (cloud), and an upload panel for importing a backup from another server.

## Who Can Use This

Only users assigned the **Backup Manager** role can access the Backup Dashboard. Admins also have access by default.

## Creating a Backup

1. Go to Ready Room and click **Backups**.
2. In the **Local Backups** section, click **Create Backup Now**.
3. You'll see a "Backup job queued" confirmation. The backup runs in the background -- refresh the page after a minute to see it appear in the list.

The backup file is named `backup_YYYY-MM-DD_HH-MM-SS_<dbtype>.sql.gz` and stored on the server. Backups also run automatically every day at 3:00 AM -- you only need to click this manually when you want a backup right now (before a deployment, for example).

## Downloading a Backup

To download a local backup file to your computer:

1. Find the backup in the **Local Backups** table.
2. Click **Download** in that row.
3. The file will download as a `.sql.gz` compressed archive.

To download from S3, use the **Download** button in the **S3 Backups** section instead.

## Uploading a Backup

If you have a backup file from another server and want to bring it into this one:

1. Scroll to the **Upload Backup** section.
2. Click the file picker and select your `.sql.gz` file.
3. Click **Upload**. The file will appear in the Local Backups list.

The file must end in `.sql.gz` -- plain `.gz` files that aren't SQL dumps will be rejected.

## Deleting a Backup

To delete a local backup:

1. Find it in the **Local Backups** table.
2. Click **Delete** and confirm in the popup.

To delete an S3 backup, use the **Delete** button in the **S3 Backups** section. Both actions are permanent -- there's no undo.

## The S3 Panel

If S3 is configured, the **S3 Backups** section shows all backups stored in cloud storage with a connectivity status badge at the top:

- **S3 Connected** (green) -- S3 is reachable and the file list is live.
- **S3 Unreachable** (red) -- Credentials are set but S3 isn't responding. Check with an Admin.
- **S3 Not Configured** (grey) -- No S3 credentials are set up on this server.

## Storage Stats

The **File Asset Storage** panel at the bottom shows file counts and total sizes for each asset directory (staff photos, message images, blog images, etc.). This is informational only -- use it to get a sense of how much disk space uploaded files are consuming.

## Settings

At the bottom of the dashboard are two toggles:

- **Take site offline during backup** -- Puts the site into maintenance mode while a backup runs. Off by default. Only useful if you're concerned about data consistency during large backups.
- **Take site offline during restore** -- Puts the site into maintenance mode during a restore. On by default. Leave this on -- restoring while the site is live can cause data corruption.
