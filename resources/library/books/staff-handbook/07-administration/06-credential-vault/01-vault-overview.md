---
title: 'Vault Overview'
visibility: staff
order: 1
summary: 'Learn what the Credential Vault is and who can access what.'
---

## What Is the Credential Vault?

The **Staff Credential Vault** is a secure, centralized place to store the usernames, passwords, and two-factor authentication codes for services that multiple staff members need to access -- things like hosting control panels, social media accounts, or community tools. Instead of sharing passwords in Discord or email, everything lives in one place with proper access controls.

You can reach the vault by clicking **Vault** in the Ready Room navigation.

## Who Can See What

Access to the vault depends on your staff role. There are two levels:

- **Vault Managers** -- can see every credential in the vault, create new ones, edit or delete them, and control which staff positions have access. Vault Managers are typically senior staff or admins.
- **Position holders** -- if your staff position has been granted access to a specific credential, you can view and edit that credential. You'll only see credentials that are assigned to your position.

If you're staff but don't hold a position assigned to any credential, you'll see the vault page but it'll be empty. That's expected -- it means you don't need any shared credentials for your current role.

## What Gets Stored

Each credential entry can hold:

- **Name** -- a clear label like "Apex Hosting Admin" or "Community Twitter"
- **Website URL** -- the login page (optional but helpful)
- **Username** and **Email** -- the account login details
- **Password** -- stored encrypted, requires re-authentication to reveal
- **TOTP** -- a two-factor authentication secret, if the account uses TOTP codes
- **Notes** -- anything useful to know about the account
- **Recovery Codes** -- backup codes in case 2FA is unavailable

## Security and Re-Authentication

Passwords and TOTP codes are treated with extra care. The vault uses a **vault session** system to protect sensitive data:

- When you first try to reveal a password or view a TOTP code, you'll be asked to confirm your identity by entering your **Lighthouse website password** (not your Minecraft password).
- After re-authenticating, your vault session stays unlocked for **30 minutes**. During that window, you can reveal passwords without re-entering your password each time.
- **TOTP codes always require re-authentication**, even if your session is already unlocked. This extra step keeps two-factor codes protected.

If your session times out or you haven't authenticated yet, the vault will prompt you to re-authenticate before showing sensitive data.
