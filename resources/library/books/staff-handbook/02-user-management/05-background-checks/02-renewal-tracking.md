---
title: "Renewal Status Tracking"
visibility: officer
order: 2
summary: "How to read background check renewal badges on the staff page and meeting reports."
---

## Overview

Background checks aren't a one-time thing. Passed checks are valid for two years from the completion date. The website automatically flags staff members who are overdue for renewal or coming up on their expiry date.

These flags appear in two places:
- **Staff Page** -- a small colored badge on each filled position card
- **Department Report Cards** -- badges shown alongside each staff member's name during meetings

## Badge Meanings

| Badge | Color | Meaning |
|---|---|---|
| **Waived** | Gray | The most recent terminal check was Waived -- no check required for this person |
| **Overdue** | Red | No Passed check on file, or the most recent Passed check expired more than 2 years ago |
| **Due Soon** | Amber | The most recent Passed check expires within 90 days |
| *(no badge)* | -- | A current, valid Passed check is on file |

The badge reflects the most recent **terminal** check (Passed, Failed, or Waived). Pending and Deliberating records don't affect the badge -- only completed checks count.

## Reading the Staff Page Badges

Visit the [Staff Page]({{url:/staff}}) and look at the filled position cards. Hover over the **BG Check** badge to see a tooltip with details:

- **Passed check** -- shows the date the check was completed
- **Waived** -- shows "A background check is not required for this position"
- **No terminal record** -- shows a configurable message (set in the Admin Control Panel under SiteConfig)

Unfilled positions don't show a badge.

## Reading Meeting Report Card Badges

When running a [[books/staff-handbook/meetings-and-tasks/meetings|department meeting]], the report cards show a colored badge next to each staff member's name:

- **Waived** -- violet badge
- **Overdue** -- red badge
- **Due Soon** -- amber badge
- No badge if the check is current

Use these during department check-ins to identify who needs to be flagged for renewal.

## When to Act on These Badges

If you see an **Overdue** or **Due Soon** badge for a staff member:

1. Open their profile and check the Background Checks card for context
2. Coordinate with the appropriate reviewer to initiate a new check
3. Add the check record once the screening is underway
4. Update the status as results come in

Department leads are responsible for ensuring their team members have current checks. Command oversees the overall renewal schedule.

## Important Notes

- The renewal window is based on `completed_date`, not when the record was created or locked
- Only the most recent Passed check counts for the expiry calculation -- earlier ones are ignored once superseded
- A Failed check shows as Overdue (the member still needs a cleared check to be current)
- Renewal badges are only visible to staff with the **Background Checks - View** or **Background Checks - Manage** role -- regular members and non-privileged viewers don't see them
