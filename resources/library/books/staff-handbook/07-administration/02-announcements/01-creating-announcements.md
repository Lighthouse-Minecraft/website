---
title: 'Creating Announcements'
visibility: officer
order: 1
summary: 'How to create, schedule, and publish announcements.'
---

## Overview

Announcements are how you communicate important information to the community. They appear on member dashboards as a banner until acknowledged, and are listed in an announcements widget. Announcements can also be automatically posted to Discord.

## Who Can Create Announcements

- Admins
- Command Officers
- Officers
- Crew Members in the **Engineer** or **Steward** departments
- Users with the **Announcement Editor** role

## How to Create an Announcement

1. Go to the **Admin Control Panel**
2. Open the **Announcements** tab under Content
3. Click **Create Announcement**
4. Fill in the form:
   - **Title** -- a clear, concise headline
   - **Content** -- the announcement body (supports Markdown formatting)
   - **Published** -- toggle on to make it live immediately, or leave off as a draft
   - **Published At** -- optionally schedule it for a future date/time
   - **Expires At** -- optionally set an expiration date when it auto-unpublishes
5. Click **Save**

## Publishing States

| State | What It Means |
|---|---|
| **Draft** | Not visible to members. You're still working on it. |
| **Published** | Live on the dashboard now. |
| **Scheduled** | Published flag is on but the publish date is in the future. Will go live automatically. |
| **Expired** | The expiration date has passed. No longer visible to members. |

## What Happens When Published

When an announcement goes live:

- It appears on every member's **Dashboard** as a banner they need to acknowledge
- It shows in the **Announcements widget** on the Dashboard
- **Traveler+ members** receive a notification (email, Pushover, and/or Discord DM based on their preferences)
- The announcement is automatically **posted to the configured Discord channel**
- Members must click to acknowledge the announcement to dismiss the banner

## Content Formatting

Announcement content supports **Markdown**:
- Use `**bold**` for emphasis
- Use headers, lists, and links as needed
- HTML is stripped for security -- stick to Markdown syntax
- Keep announcements concise. Members are more likely to read short, focused announcements.

## Important Notes

- Notifications are sent once -- if you edit an announcement after publishing, members won't be re-notified
- The notification dispatch happens lazily -- the first dashboard load after publication triggers it
- Expired announcements are automatically hidden but not deleted. You can remove the expiration date to make them visible again
- Acknowledgment tracking lets you know how many members have read each announcement
