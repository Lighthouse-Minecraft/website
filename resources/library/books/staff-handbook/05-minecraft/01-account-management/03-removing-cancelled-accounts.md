---
title: 'Removing Cancelled Accounts'
visibility: staff
order: 3
summary: 'How to permanently remove Cancelled or Cancelling Minecraft accounts from a member''s profile.'
---

## What This Is

A **Cancelled** or **Cancelling Verification** account is one where the member (or their parent) started the linking process but never completed the in-game `/verify` step. These accounts were never fully activated -- they hold no whitelist slot on the server -- and they can accumulate on a member's profile over time.

Admins with the **User - Manager** role can permanently remove these accounts through the member's profile page. This clears the record entirely and frees the UUID for future use.

## Who Can Do This

Only users with the **User - Manager** role can remove Cancelled and Cancelling accounts. This is an admin-only action -- it is not available to standard staff.

## How to Remove a Cancelled Account

1. Go to the member's profile page (search for them in the Admin Control Panel, or navigate to their profile directly).
2. Scroll down to the **Minecraft Accounts** section.
3. Find the account showing a **Cancelled** or **Cancelling Verification** status.
4. Click the **Remove** button next to that account.
5. A confirmation dialog will appear -- confirm that you want to permanently delete the account.
6. The account is removed immediately. You'll see a success message, and the account will no longer appear in the list.

## What Happens After

- The account record is **permanently deleted** from the database. This cannot be undone.
- The Minecraft UUID is released and can be registered again in the future if needed.
- The deletion is recorded in the member's **activity log** so there's a record of the action.
- No server (RCON) commands are issued -- Cancelled and Cancelling accounts are already off the whitelist, so no server-side cleanup is needed.

## How This Differs From Revoking

These two actions are easy to mix up:

- **Revoke** -- Used for **Active** accounts. This removes the player from the server whitelist via RCON and sets the account status to Removed. The record stays in the system.
- **Remove** -- Used for **Cancelled/Cancelling** accounts. This permanently deletes the record. No RCON command is needed because the account was never fully verified.

If you need to remove an Active account, use Revoke instead. The Remove button only appears on Cancelled and Cancelling accounts (as well as Removed accounts, where it shows as "Delete").

## When to Use This

Common reasons to remove a cancelled account:

- A member asks you to clear out a failed linking attempt so they can start fresh.
- A member's profile has accumulated old cancelled accounts and they want them cleaned up.
- A child account had a cancelled verification and the parent has already removed it through the Parent Portal, but you're confirming the cleanup is complete.

If a member wants to complete verification rather than remove the account, parents can restart verification from the Parent Portal instead -- they'll see a **Restart** button alongside the Remove button on Cancelled accounts. See [[books/staff-handbook/minecraft/account-management/how-linking-works|How Linking Works]] for background on the verification process. For non-child accounts, the member can start the linking process again themselves.
