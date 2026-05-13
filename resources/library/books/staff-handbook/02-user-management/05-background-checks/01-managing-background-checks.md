---
title: "Managing Background Check Records"
visibility: officer
order: 1
summary: "How to add, update, and annotate background check records on a member's profile."
---

## Overview

Background check records live on the member's profile page inside the **Background Checks** card. Each record tracks the service provider, completion date, status, notes, and any uploaded PDF documents. The card is also embedded on the staff application review page so reviewers can manage checks without leaving the application.

## Who Can Do This

- **Manage** (add, update status, add notes, upload/delete documents): requires the **Background Checks - Manage** role, assigned by an admin
- **View** (read-only, download documents): requires the **Background Checks - View** or **Background Checks - Manage** role
- Members can see their own check history on their profile, but cannot take any actions

## Finding the Background Checks Card

Open the member's profile page (find them via user search, a support ticket link, or an application). Scroll down past the registration answers -- the **Background Checks** section appears below. If you have the View or Manage role, you'll see the card. If neither applies and it's not your own profile, you won't see it.

## Adding a New Check Record

1. Click **Add Check** in the top-right of the Background Checks card
2. Fill in the form:
   - **Service / Provider** -- the name of the screening company (e.g., "Checkr", "Sterling Volunteers")
   - **Completed Date** -- the date the check was run; must not be in the future
   - **Initial Notes** -- optional, but useful for context
3. Click **Add Check** to confirm

New records start with **Pending** status. Update the status once results are in.

## Updating a Check Status

Status can only be changed on non-terminal records. Once a record reaches Passed, Failed, or Waived, it's locked -- you can still add notes but can't change the status.

1. Find the check record in the list
2. In the **Set status:** row, click the status button you want to transition to
3. Confirm the change in the dialog

Available statuses:

| Status | When to Use |
|---|---|
| **Pending** | Default -- check initiated, awaiting results |
| **Deliberating** | Results received, reviewing before finalizing |
| **Passed** | Check cleared -- record is now locked |
| **Failed** | Check did not pass -- record is now locked |
| **Waived** | No check required for this position -- record is now locked |

When a record transitions to a terminal status (Passed, Failed, or Waived), a lock icon appears on the record and all status/delete actions disappear. Notes and document uploads remain available.

## Adding Notes

You can add notes to any check record, including locked ones. Notes are appended in chronological order with your name and a timestamp -- you can't edit or delete them, so write carefully.

1. Click **Add Note** on the check record
2. Type your note in the text area
3. Click **Save Note**

## Uploading Documents

Use this to attach a copy of the check report or any supporting materials. PDFs only.

1. Click **Upload PDF** on the check record
2. Select the file(s) to upload
3. Click **Upload**

Each file must be a PDF. The maximum file size is configurable by admins.

## Deleting Documents

Documents can only be deleted from non-terminal (unlocked) records. Once the check is Passed, Failed, or Waived, attached documents are permanent.

Click the **trash icon** next to the document, then confirm the deletion. This removes the file from storage permanently.

## Important Notes

- All actions are logged in the activity log for the check record
- There's no way to delete an entire check record from the UI -- contact an admin if a record needs to be removed
- The check history is shared between the user's profile page and the staff application review page -- managing records in either place updates the same data
