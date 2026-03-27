---
title: 'Updating Policy Content'
visibility: staff
order: 3
last_updated: '2026-03-26'
summary: 'How policy pages are edited and what to do after making changes.'
---

## How Policy Content Is Stored

Policy Manual pages are Markdown files stored in the codebase at:

```text
resources/library/books/policy-manual/
```

Each page has a YAML front matter block at the top followed by the page content in Markdown. There is no web-based editor for production — changes are made by editing the files directly, committing to git, and deploying.

## Who Makes Updates

Policy content is maintained by Command. If you believe a policy page needs to be updated — because it is outdated, unclear, or inaccurate — raise it in staff channels or open a Support Ticket directed to Command. Do not edit policy files unilaterally unless you are authorized to do so.

## How to Update a Policy Page

1. **Locate the file** for the page you want to update. The file structure mirrors the URL structure:

   | Policy Page | File Path |
   |-------------|-----------|
   | Code of Conduct | `resources/library/books/policy-manual/01-community-standards/01-code-of-conduct.md` |
   | Community Expectations | `resources/library/books/policy-manual/01-community-standards/02-community-expectations.md` |
   | Child Safety Policy | `resources/library/books/policy-manual/02-safety-and-privacy/01-child-safety-policy.md` |
   | Data Privacy Policy | `resources/library/books/policy-manual/02-safety-and-privacy/02-data-privacy-policy.md` |
   | Moderation Practices | `resources/library/books/policy-manual/03-moderation/01-moderation-practices.md` |
   | Staff Reports | `resources/library/books/policy-manual/03-moderation/02-staff-reports.md` |
   | The Brig | `resources/library/books/policy-manual/03-moderation/03-the-brig.md` |
   | Staff Requirements | `resources/library/books/policy-manual/04-staff-and-operations/01-staff-requirements/01-staff-requirements.md` |
   | Staff Authority & Conduct | `resources/library/books/policy-manual/04-staff-and-operations/01-staff-requirements/02-staff-authority-and-conduct.md` |
   | Operational Policies | `resources/library/books/policy-manual/04-staff-and-operations/02-operational-policies/01-operational-policies.md` |

2. **Edit the content** as needed. Policy pages use standard Markdown formatting.

3. **Update the `last_updated` field** in the front matter. This is the date shown to readers as "Last updated: [date]". It must be set manually whenever the content changes — the system does not update it automatically.

   The field uses `YYYY-MM-DD` format:
   ```yaml
   ---
   title: 'Code of Conduct'
   visibility: public
   order: 1
   last_updated: '2026-03-26'
   summary: 'The rules for behavior in all Lighthouse community spaces.'
   ---
   ```

   Set it to today's date when you publish the change. If you are making a minor formatting fix that does not change the meaning of the policy, use your judgment about whether to update the date.

4. **Commit and deploy** the change. Policy updates go through the normal git workflow — commit to the appropriate branch and deploy to production.

5. **Announce the update** if the change materially affects members. For significant policy changes, post a website announcement and/or Discord notification so members are aware. For minor clarifications, no announcement is needed.

## Front Matter Fields

Each policy page supports these YAML front matter fields:

| Field | Required | Notes |
|-------|----------|-------|
| `title` | Yes | The page heading shown to readers |
| `visibility` | Yes | Always `public` for Policy Manual pages |
| `order` | Yes | Controls display order within the chapter |
| `last_updated` | No | Date shown as "Last updated: [date]". Update manually when content changes. |
| `summary` | No | Short description shown in navigation and search results |

## Important Notes

- **`last_updated` is not automatic.** If you edit a page and forget to update this field, readers will see a stale date. Always update it.
- **All policy pages are `visibility: public`.** Do not change this. Policy content is intentionally accessible without login.
- **The Policy Manual has no version history visible to members.** If a policy change is significant, communicate it proactively rather than relying on members to notice the updated date.
- **Internal staff policies live in this manual too.** The Staff Requirements and Operational Policies pages are public. When you write or update these chapters, be aware that members, parents, and prospective staff can read them.

