---
title: 'Editing Credentials'
visibility: staff
order: 3
summary: 'How to update usernames, passwords, TOTP secrets, notes, and recovery codes.'
---

## Who Can Edit

Any staff member whose position has access to a credential can edit it. This lets you keep the information current -- for example, updating the password after a rotation or adding notes for the next person who logs in.

**Vault Managers** can also edit the credential's name and website URL, which position holders cannot change.

## How to Edit a Credential

1. Open the credential from the [[books/staff-handbook/administration/credential-vault/02-viewing-credentials|vault index]] or navigate directly to its detail page.
2. Click **Edit** in the top-right corner of the page.
3. A panel opens with the fields you can update.
4. Make your changes and click **Save Changes**.

## What You Can Edit

For position holders (staff with assigned access):

- **Username** -- the login name for the account
- **Email** -- the email address for the account
- **New Password** -- leave this blank if you don't want to change the password
- **TOTP Secret** -- the raw TOTP secret key (not a code -- this is the secret used to generate codes)
- **Notes** -- free-form notes about the account
- **Recovery Codes** -- backup codes for account recovery

Vault Managers can also edit:

- **Name** -- the display name for this credential
- **Website URL** -- the URL of the login page

## Updating a Password

When you open the edit panel, the **New Password** field is blank on purpose. If you leave it blank and save, the existing password is kept as-is. Only fill in the **New Password** field if you're actually changing the password.

After rotating a password on the external service, update it here right away so the credential stays accurate for everyone who needs it.

## Clearing the "Needs Rotation" Flag

The **Needs Rotation** flag is automatically cleared when you update the password. If you see a credential flagged for rotation, update the password on the external service first, then save the new password in the vault. The badge will disappear once the credential is saved.
