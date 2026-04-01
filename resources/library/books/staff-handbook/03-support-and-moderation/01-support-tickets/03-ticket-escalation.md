---
title: 'Ticket Escalation'
visibility: staff
order: 3
summary: 'How automatic ticket escalation works and what to do when you receive an alert.'
---

## Overview

The ticket escalation system watches for support tickets that have gone unassigned for too long. When a ticket sits open and unassigned past the response threshold, the system automatically notifies everyone with the **Ticket Escalation - Receiver** role. This ensures tickets don't slip through the cracks during busy periods or when the right department isn't watching.

## Who Receives Escalation Alerts

Only staff members with the **Ticket Escalation - Receiver** role get these notifications. Admins can assign this role to any staff member. If your team wants specific people to be the fallback net for unassigned tickets, that's who should have it.

## What Triggers an Escalation

A ticket triggers escalation when all of the following are true:

- The ticket is **Open**
- It has **no assigned staff member**
- It has **not been escalated before** (each ticket only escalates once)
- It was created more than **30 minutes ago** (default -- this is configurable)

The check runs automatically in the background every minute. You don't need to do anything to make it fire.

## What Happens When a Ticket Escalates

1. **You receive a notification** -- an email alert with the ticket subject, department, and who submitted it, plus a direct link to the ticket.
2. **A system message is posted** in the ticket thread -- the member can see a note that the ticket has been escalated and is getting attention.
3. The ticket is flagged internally so it won't trigger another escalation alert.

## What To Do When You Get the Alert

When you receive an escalation notification:

1. Open the ticket using the link in the email
2. Review the ticket and assign it to the appropriate staff member (or claim it yourself)
3. Reply to the member or post an internal note for your team

The goal is to make sure the member gets a timely response. Assigning the ticket is the most important step -- it signals to the rest of the team that someone is on it.

## Resetting Escalation

If a ticket is **unassigned** (the assigned staff member is removed), the escalation resets. This means the ticket can be escalated again if it stays unassigned past the threshold. This is intentional -- if a ticket falls back to unassigned, the system should alert the team again.

## Important Notes

- Escalation only fires for **unassigned** tickets. Once a ticket has an assigned staff member, it won't escalate.
- Closed and resolved tickets never escalate.
- Only **Open** tickets are checked -- Pending tickets are not included.
- If no one has the Ticket Escalation - Receiver role, the system won't send any notifications (but the system message will still appear in the thread if the system account exists).

## Configuration

The escalation threshold defaults to 30 minutes. Admins can change it in the Site Config settings. If your team handles a high volume of tickets and needs more response time, talk to an admin about adjusting the threshold.

See [[books/staff-handbook/administration/roles-and-permissions/role-reference|Role Reference]] for more on the Ticket Escalation - Receiver role.
