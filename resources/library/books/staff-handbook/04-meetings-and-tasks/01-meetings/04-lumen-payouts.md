---
title: Lumen Payouts
visibility: staff
order: 4
summary: 'How automatic Lumen rewards work for meeting participants, and how managers control them.'
---

## Overview

When a meeting is completed, the system automatically pays out Lumens -- our in-game currency -- to eligible staff members. This replaces manual payout tracking. Lumens are sent directly to each person's primary Minecraft account via RCON when the meeting manager clicks **Complete Meeting**.

Payouts only apply to staff meetings (not community or other meeting types).

## Eligibility by Rank

Eligibility requirements differ by rank:

| Rank | Requirements |
|------|-------------|
| Junior Crew Member | Submit staff update form |
| Crew Member | Submit staff update form |
| Officer | Submit staff update form **and** be marked as attending |

Users with no linked Minecraft account are automatically skipped -- the system can't send Lumens without an in-game username. If a rank's payout amount is set to zero in the site config, everyone of that rank is skipped for that meeting.

## The Payout Preview (Meeting Managers)

During the **Finalizing** step, a **Payout Preview** table appears below the community notes editor. This shows every attendee in the meeting and their eligibility status before you complete the meeting.

**Reading the table:**
- Green checkmarks indicate the eligibility factor is met
- Red X icons indicate the factor is not met
- Ineligible rows are grayed out with a reason shown below the person's name
- Eligible staff have the **Include** toggle turned on by default

**To exclude someone from the payout:**
1. Find their row in the Payout Preview table
2. Toggle their **Include** switch to off
3. Their payout will be recorded as "Excluded by manager" in the permanent record

Exclusion toggles are not saved to the database -- if you refresh the page or the Finalizing step re-polls (every 30 seconds), your exclusions will be reset. Make your exclusion decisions right before clicking Complete Meeting.

If the Payout Preview doesn't appear, all rank payout amounts are currently set to zero in the site config (payouts are disabled).

## Completing the Meeting with Payouts

The payout process runs automatically when you click **Complete Meeting** and confirm. You don't need to take any additional steps. The system:

1. Evaluates each attendee against eligibility rules
2. Applies any manager exclusions you set
3. Creates a permanent payout record for each person (paid, skipped, or failed)
4. Sends the `money give` command to the Minecraft server for eligible staff

If an RCON connection problem occurs during payout, affected users are marked as "Failed" -- the meeting still completes normally. Failed payouts need to be processed manually.

## Viewing Results

After a meeting is completed, the **Payout Summary** appears at the bottom of the completed meeting page. It shows:

- A summary line: X paid (Y Lumens total) · Z skipped · W failed
- A detail table with each person's name, amount, status, and skip/fail reason

This is the permanent audit trail. All staff with site access can see it.

## What Affects Payout Amounts

Payout amounts per rank are configured by admins in the ACP Site Config page (keys `meeting_payout_jr_crew`, `meeting_payout_crew_member`, `meeting_payout_officer`). Meeting managers cannot change these amounts -- contact an admin if adjustments are needed.

## Important Notes

- The payout preview only shows attendees added to the meeting's attendee list. If someone's missing, add them via **Manage Attendees** before completing.
- Exclusion toggles reset if the page polls or refreshes -- make exclusions immediately before completing.
- Failed payouts are recorded but not retried automatically. Manual Lumen grants are needed for those users.
- Staff without a linked Minecraft account are skipped every meeting -- remind them to [[books/user-handbook/minecraft/accounts/joining-the-server|link their account]] to start receiving payouts.

## Troubleshooting

### Someone didn't receive their Lumens
Check the Payout Summary on the completed meeting page. Find their entry and check the status:

- **Skipped / Form not submitted** -- They didn't submit their staff update form in time
- **Skipped / Did not attend** -- They weren't marked as attending (Officers only)
- **Skipped / No Minecraft account** -- They haven't linked a Minecraft account
- **Skipped / Excluded by manager** -- A manager excluded them during Finalizing
- **Skipped / Rank payout disabled** -- Their rank's payout amount is set to zero
- **Failed** -- RCON error; grant Lumens manually via the server console

### The Payout Preview is missing
Either this isn't a staff meeting, or all three rank payout amounts are set to zero in site config. Contact an admin to verify the config if payouts should be active.
