---
title: 'Handling Cancelled Minecraft Accounts'
visibility: public
order: 5
summary: 'What to do when your child''s Minecraft account shows a Cancelled or Cancelling Verification status.'
---

## What Does "Cancelled" Mean?

When you set up a Minecraft account for your child, they need to join the server and run a `/verify` command with a short code before the account is fully active. If that step doesn't happen -- because the code expires, the child forgets, or something else gets in the way -- the account ends up in a **Cancelled** or **Cancelling Verification** state.

A Cancelled account was never successfully verified, so it's not taking up a whitelist slot on the server. It's essentially a half-finished setup that needs to be either completed or cleared out.

The good news: you have two options right from the Parent Portal.

## Your Two Options

When a child's Minecraft account shows **Cancelled** or **Cancelling Verification** status, two buttons appear next to it:

- **Restart Verification** (the arrow icon) -- Starts the verification process over with a fresh code
- **Remove** (the X icon) -- Permanently deletes the account record so you can start fresh

Removing the account asks you to confirm before anything happens. Restart Verification runs immediately when you click the button.

## Restarting Verification

If you want your child to verify the account they already set up, use **Restart Verification**. This:

1. Re-adds the Minecraft username to the server whitelist
2. Generates a new **verification code**
3. Shows the code in the Parent Portal, just like when you first set up the account

Once you see the new code, have your child:

1. Join the Minecraft server
2. Type `/verify CODE` in chat (replacing `CODE` with the code shown in the portal)

They have **{{config:lighthouse.minecraft_verification_grace_period_minutes}} minutes** to do this before the code expires.

If the code expires again, you can restart verification as many times as you need. Just be aware that if something is blocking the account -- such as Minecraft access being disabled or your child's account being restricted by staff -- the restart won't work until that's resolved. You'll see a message explaining the reason if that happens.

## Removing the Account

If you'd prefer to start completely fresh -- for example, if the Minecraft username changed or the account was set up by mistake -- use the **Remove** button instead. This permanently deletes the account record.

Because the account was never verified, there's nothing to remove from the server. The process is instant. Once removed, the username is released and can be linked again at any time if needed.

## After Restarting

Once your child successfully runs the `/verify` command in-game, the account status will update to **Active** and everything will work normally from that point on.

If you have trouble getting the account verified and aren't sure what's wrong, open a [[books/user-handbook/website/getting-help/support-tickets|support ticket]] and staff will be happy to help sort it out.
