---
title: "The Onboarding Wizard"
visibility: staff
order: 3
summary: "What the onboarding wizard is, how it works, and what activity log entries to expect."
---

## Overview

When a new user reaches the Stowaway or Traveler membership level, the website guides them through account setup with a step-by-step **onboarding wizard**. The wizard replaces the normal dashboard for new users and walks them through connecting Discord, waiting for staff review, and linking their Minecraft account.

There's no staff UI to manage the wizard -- it's fully automatic. But understanding how it works helps you know what to expect when reviewing new users and reading their activity logs.

## What Users See

The wizard presents a single card at a time. Stowaways go through two steps:

1. **Connect Discord** -- Prompts them to link their Discord account or skip it. If their parent has disabled Discord, they see an explanation instead and click Continue.
2. **Waiting for Approval** -- An informational card explaining that staff will review their account. No action required from the user -- they just wait.

Once a Stowaway is promoted to **Traveler**, the wizard automatically moves to the next step on their next page load:

3. **Link Minecraft** -- Prompts them to link their Minecraft account or skip it. If their parent has disabled Minecraft, they see an explanation instead and click Continue.

After the Minecraft step, the wizard finishes and shows a "Welcome to Lighthouse!" modal with next-step suggestions. The normal dashboard appears from that point on.

Users can also dismiss the wizard at any step using the **Dismiss** button. Dismissal permanently hides the wizard without completing it -- no welcome modal.

## Activity Log Entries

Every wizard interaction is recorded in the user's activity log. When you're reviewing a new user, these entries tell you what they've done:

| Activity | What It Means |
|---|---|
| `onboarding_discord_skipped` | User clicked "Skip for now" on the Discord step |
| `onboarding_discord_disabled` | User continued past the Discord step because Discord is disabled by a parent |
| `onboarding_minecraft_skipped` | User clicked "Skip for now" on the Minecraft step |
| `onboarding_minecraft_disabled` | User continued past the Minecraft step because Minecraft is disabled by a parent |
| `onboarding_wizard_completed` | User finished the full wizard and saw the welcome modal |
| `onboarding_wizard_dismissed` | User dismissed the wizard early using the Dismiss button |

## When the Wizard Disappears

The wizard is re-evaluated on every page load based on the user's current state. It disappears automatically when:

- The user completes it (both Discord and Minecraft steps resolved)
- The user dismisses it (clicks Dismiss at any step)
- The user is promoted past Traveler (Resident and above never see it)
- The user is in the Brig (the Brig blocks access to the dashboard community section)

When you promote a Stowaway to Traveler, the waiting step card disappears and the Minecraft step appears the next time they load the page. There's no push notification to the user -- they need to refresh or navigate to see the change.

## Important Notes

- **No staff reset exists.** There's no way to reset or reshow the wizard for a user. If a user dismissed it early and wants to go back, point them to their account settings -- they can link Discord and Minecraft from there directly.
- **Existing members never see it.** The wizard was backfilled on launch: anyone who already had a Discord or Minecraft account linked when the feature shipped had it automatically suppressed. It only shows to new users going forward.
- **A "Resume Account Setup" sidebar link** appears for users while the wizard is active. It links back to the dashboard. Once the wizard is dismissed or completed, this link disappears.
- **Skipping is fine.** If a user skips Discord or Minecraft during the wizard, they can still link those accounts later from their settings. The wizard just makes it easier to do upfront.

## Cross-References

- [[books/staff-handbook/user-management/reviewing-new-users/reviewing-stowaways|Reviewing Stowaways]] -- How to review and promote Stowaway users
- [[books/staff-handbook/user-management/reviewing-new-users/membership-levels|Membership Levels]] -- Overview of what each level unlocks
