---
title: Managing Payouts
visibility: staff
order: 2
summary: 'How meeting managers preview and adjust Lumen payouts before completing a meeting.'
---

## The Payout Preview

When you're in the **Finalizing** step of a meeting, you'll see a **Payout Preview** table before you complete the meeting. This table shows every staff member in the meeting and tells you whether they'll receive Lumens -- and why or why not.

The table has columns for each eligibility factor:

- **Form** -- Whether they submitted their staff update form
- **Attended** -- Whether they were marked as attending
- **MC Account** -- Whether they have a linked Minecraft account
- **Amount** -- The Lumen amount they'd receive if eligible
- **Include** -- A toggle you can use to exclude individual users

Eligible staff members have the Include toggle turned on by default. You only need to act if you want to exclude someone.

## Excluding a Staff Member

If you need to withhold a payout for a specific person, just toggle their Include switch to off in the Payout Preview table. They'll still appear in the payout summary after the meeting, but their record will show "Excluded by manager" as the reason.

This is intended for edge cases -- if someone is eligible on paper but shouldn't receive their payout for a reason not captured by the automatic rules. If you're excluding someone regularly, it's worth a conversation with them about what's going on.

## What Managers Cannot Change

The payout amounts per rank are set by admins in the site configuration, not by meeting managers. If you think the amounts need adjusting, contact an admin. You also can't change the eligibility rules -- those are determined by rank.

## When Payouts Are Hidden

If all three rank payout amounts are currently set to zero by admins, the entire Payout Preview section won't appear. This means payouts are disabled for the time being -- completing the meeting will still work normally, but no Lumens will be sent.

## Completing the Meeting

Once you've reviewed the payout preview and adjusted any exclusions, click **Complete Meeting** and confirm. Payouts are sent automatically at that moment. You'll see a permanent **Payout Summary** on the completed meeting page showing who was paid, who was skipped, and any failures.

If a payout fails (usually due to a server connection issue), it's recorded as "Failed" in the summary. Those users will need to be paid manually -- the system doesn't retry automatically.

## Troubleshooting

### The Payout Preview doesn't appear
This meeting may not be a staff meeting type, or payouts may be disabled by admins (all amounts set to zero). Non-staff meeting types don't trigger payouts.

### Someone eligible is missing from the table
The table shows all staff members added to the meeting's attendee list. If someone is missing, they may not have been added to the meeting. Check the attendee list and add them if needed while still in the Finalizing step.
