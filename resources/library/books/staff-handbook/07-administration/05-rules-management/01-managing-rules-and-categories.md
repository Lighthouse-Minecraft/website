---
title: "Managing Rules and Categories"
visibility: officer
order: 1
summary: "How to add, edit, and organize rules and categories in a draft version."
---

## Overview

The **Rules Admin page** is where you manage the Community Rules -- creating draft versions, editing rule content, organizing categories, and updating the header and footer text. All changes go into a draft first and only go live when published.

To get there, open the Admin Control Panel and navigate to the **Rules** tab.

## Who Can Do This

Staff with the **Rules -- Manage** permission. This is separate from the **Rules -- Approve** permission (which is required to publish a version).

## Working with Draft Versions

The rules system uses **versions** -- a snapshot of the entire ruleset at a point in time. You can't edit rules directly in the published version. Instead:

1. **Create a new draft** -- Click **Create New Draft** at the top of the page. The draft is seeded automatically with all currently active rules.
2. Make your changes to the draft (add, edit, deactivate rules and categories).
3. When ready, **submit** the draft for approval. A different staff member with the Rules -- Approve permission must then review and publish it.

There can only be one active draft at a time. If a draft already exists, the Create button won't appear.

## Adding a New Rule

1. Find the **category** you want to add the rule to
2. Click **Add Rule** within that category
3. Enter a **title** (a short identifier like "No Spamming") and a **description** (Markdown is supported)
4. Click **Add**

The new rule appears in the draft with a **Draft** badge. It won't be active until the version is published.

## Editing an Existing Rule

Editing a rule in the draft creates a **replacement entry**. The original rule stays active until the version is published.

1. Find the rule you want to edit in the draft
2. Click **Edit**
3. Update the title and/or description
4. Click **Save**

When the new version is published, the old rule is deactivated and the new one becomes active. Members will see the rule flagged as **UPDATED** when they re-agree, and they'll be able to view the previous version side-by-side.

## Deactivating a Rule

If a rule is being removed entirely:

1. Find the rule in the draft
2. Click **Deactivate**

The rule is marked for deactivation and will be removed from the active set when the version publishes. The rule is never deleted from the database -- it stays in the record so old reports that reference it remain accurate.

## Managing Categories

At the top of each category, you can:
- **Rename** the category
- **Reorder** it using the up/down arrows
- Reorder individual rules within the category the same way

To add a new category, use the **Add Category** button at the bottom of the category list.

## Editing Header and Footer Text

The rules page has a **header** (shown above the rules) and **footer** (shown below). These support Markdown formatting.

Scroll to the top of the Rules Admin page to find the **Header** and **Footer** text fields. Edit the content and click **Save Header & Footer**.

These changes take effect immediately -- they don't need a new version to go live.
