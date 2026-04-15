---
title: 'Viewing Credentials'
visibility: staff
order: 2
summary: 'How to find, open, and read credentials -- including revealing passwords and TOTP codes.'
---

## Opening the Vault

Navigate to the vault by clicking **Vault** in the Ready Room. You'll see a table listing every credential your position has access to. Each row shows the credential name, the website it's for, and when it was last accessed (and by whom).

If a credential shows an orange **Needs Rotation** badge, the password should be updated -- see [[books/staff-handbook/administration/credential-vault/03-editing-credentials|Editing Credentials]] for how to do that.

## Viewing a Credential

Click any credential's name to open its detail page. The detail page shows:

- **Username** and **Email** for the account
- **Password** -- hidden by default (shown as dots)
- **TOTP** badge and a **Show Code** button (if the account uses two-factor authentication)
- **Notes** and **Recovery Codes** (if recorded)

At the top, you'll also see when the credential was added and when it was last updated.

## Revealing a Password

Passwords aren't shown in plaintext until you confirm your identity. Here's how it works:

1. Click **Reveal** next to the password field.
2. If your vault session is already unlocked, the password appears immediately.
3. If your session has expired or this is your first reveal today, a **Confirm Your Identity** modal will appear. Enter your **Lighthouse website password** and click **Unlock & Reveal**.
4. The password is shown in plaintext on the detail page.

Your vault session stays unlocked for 30 minutes after you re-authenticate. Within that window, clicking **Reveal** on any credential will show the password right away -- no need to re-enter your password each time.

## Viewing a TOTP Code

**TOTP** (Time-based One-Time Password) codes are used for two-factor authentication. They're short numeric codes that change every 30 seconds. If a credential has a TOTP secret configured, you'll see a **Show Code** button on the detail page.

1. Click **Show Code** next to the TOTP badge.
2. A **Confirm Your Identity** modal will appear -- TOTP always requires re-authentication, even if your vault session is unlocked.
3. Enter your **Lighthouse website password** and click **Unlock & Reveal**.
4. A popup shows the current six-digit code with a countdown timer.
5. The code auto-refreshes so it's always current. Close the popup when you're done.

## The "Needs Rotation" Flag

If a credential shows a **Needs Rotation** badge in orange, it means a staff member who previously accessed this credential has since left the position they used it from. The password may have been exposed and should be changed.

If you see this badge and have edit access, update the password as soon as possible. If you're not a Vault Manager, flag it to someone who is.
