---
title: 'Managing the Vault'
visibility: officer
order: 4
summary: 'Vault Manager guide: adding credentials, controlling position access, and deletions.'
---

## Who This Page Is For

This page is for **Vault Managers** -- the staff members responsible for keeping the vault organized and ensuring credentials are properly secured. Vault Managers can see all credentials, create and delete entries, and control which positions have access to each one.

## Adding a New Credential

1. Go to the [vault index]({{url:/vault}}).
2. Click **Add Credential** in the top-right corner.
3. Fill in the credential details:
   - **Name** (required) -- use something descriptive, like "Apex Hosting Admin" or "Community YouTube"
   - **Website URL** (optional) -- the login page, so staff can navigate there easily
   - **Username** (required)
   - **Email** (optional)
   - **Password** (required)
   - **TOTP Secret** (optional) -- only needed if the account uses TOTP two-factor authentication
   - **Notes** (optional) -- useful context about the account
   - **Recovery Codes** (optional) -- paste backup codes here
4. Click **Add Credential** to save.

After creating the credential, it appears in the vault but no positions have access yet. Staff won't see it until you assign position access.

## Managing Position Access

Position access controls which staff positions can view and edit a credential. Every staff member currently holding one of those positions will have access.

To manage access:

1. Open the credential's detail page.
2. Click **Manage Access** at the top of the page.
3. In the **Manage Position Access** modal, use the dropdown to select a position and click **Add**.
4. To remove a position's access, click **Remove** next to it in the list.

Keep access limited to positions that genuinely need the credential. It's easier to add access later than to deal with a breach.

## What Happens When a Position Holder Leaves

When a staff member is removed from a position, the system automatically checks if they accessed any credentials through that position. If they did, those credentials are flagged with a **Needs Rotation** badge.

This flag means: "someone who is no longer in this role had access to this password." You should:

1. Log in to the external service and change the password.
2. Update the credential in the vault with the new password.

The **Needs Rotation** badge clears automatically when the password is updated. Keep an eye on the vault index for any orange badges -- they're a prompt to act.

## Deleting a Credential

Deleting a credential is permanent. All access logs for that credential are also removed.

1. Open the credential's detail page.
2. Click **Delete** in the top-right corner.
3. A confirmation modal will ask you to confirm. Click **Delete Permanently** to proceed.

Only delete credentials when an account is being fully decommissioned or the credential is no longer needed by anyone on staff. If the password has just changed, use **Edit** instead.
