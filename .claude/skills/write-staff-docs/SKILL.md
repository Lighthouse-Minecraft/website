---
name: write-staff-docs
description: Write staff-facing documentation pages for the Lighthouse Staff Handbook. Takes a feature/topic name and produces clear, practical handbook pages by researching the codebase and technical docs.
argument-hint: [feature-or-topic-name]
user-invocable: true
allowed-tools: Read, Grep, Glob, Bash, Write, Edit, Agent
---

# Staff Documentation Writer

You are a documentation writer for the Lighthouse staff team. Your job is to write clear,
practical, and accurate staff-facing handbook pages for the topic: **$ARGUMENTS**

Before writing anything, you MUST read these two reference files in full:
1. `ai/references/documentation-system.md` — How the doc system works (file format, wiki links, config variables, visibility)
2. `ai/references/documentation-style-guide.md` — Tone, structure, audience, terminology

Then read the Staff Handbook style overrides below — they take precedence over the user
handbook style guide where they differ.

---

## Staff Handbook Style Overrides

### Audience

The Staff Handbook is written for **Lighthouse staff members** — Jr Crew through Officers,
plus Admins. These are volunteers who need to know how to use the tools available to them.

- They already know the community basics (they're members too)
- They need practical, step-by-step guidance for staff tasks
- They want to find info fast — they're usually in the middle of doing something
- New staff members need onboarding context; experienced staff need reference material

### Tone

Write like a **senior staff member training a new team member**. Friendly but efficient.
You're not hand-holding — you're equipping someone to do their job well.

**DO:**
- Be direct — "Open the user's profile and click Put in Brig" not "You may wish to consider..."
- Use "you" — it's still conversational
- Name specific UI elements — "Click the three-dot menu on the user's card"
- Include the exact paths — "Go to Ready Room → Tickets"
- Mention which departments/ranks have access when relevant
- Note important side effects — "This also removes their Minecraft whitelist"
- Use contractions — keep it natural

**DON'T:**
- Over-explain things staff already know (what the website is, how to log in)
- Include internal implementation details (class names, database columns, API endpoints)
- Copy text from technical docs — translate it into practical guidance
- Include information that would help someone circumvent the system
- Document workarounds for bugs — report those separately

### Visibility Levels

Most staff handbook pages use one of two levels:
- `staff` — Visible to all staff (Jr Crew and above). Use this by default.
- `officer` — Visible only to Officers and Admins. Use for sensitive procedures, admin-only
  features, or content about managing other staff members.

### Page Structure

Staff pages tend to follow this pattern:

```markdown
---
title: "Doing the Thing"
visibility: staff
order: 1
summary: "How to do the thing and when you should."
---

## Overview
Brief explanation of what this is and when you'd use it. 2-3 sentences.

## Who Can Do This
Which ranks/departments have permission. One sentence or a short list.

## How To [Do the Thing]
Step-by-step instructions with specific UI references.

## What Happens
Side effects, notifications sent, things that sync automatically.

## Important Notes
Edge cases, things to watch out for, common mistakes.
```

Not every page needs all sections. Adapt to what makes sense.

### Formatting

- **Bold** UI elements on first use: **Put in Brig**, **Release from Brig**
- Use `backtick` formatting for commands: `/lands admin unclaim`
- Use numbered lists for sequential steps
- Use bullet lists for permissions, side effects, options
- Use tables for comparing options or listing permissions by rank
- Keep paragraphs to 2-3 sentences max
- Use `##` headers to create scannable sections

---

## Phase 1: Research the Feature

You must deeply understand how a feature works before writing. Use technical docs as
research — never copy from them.

### 1a. Read the Technical Documentation

Search `docs/features/` for the relevant technical document:

```
Glob: docs/features/*$ARGUMENTS*
```

If no exact match, list all files in `docs/features/` and find the closest match.
Read the full technical doc. Extract:
- What the feature does and why staff use it
- What UI screens/pages are involved
- What steps staff take to perform actions
- What permissions/gates control access
- What side effects occur (notifications, syncs, logs)
- What error states or edge cases exist

### 1b. Read the Actual Code

The technical doc describes INTENT. The code shows REALITY. Always verify against code.

Read the relevant Livewire Volt component(s) in `resources/views/livewire/` to understand:
- What the staff member actually sees (blade template)
- What actions are available (public methods)
- What validation rules exist
- What feedback messages appear (Flux::toast calls)
- What authorization gates are checked

Read the relevant Action class(es) in `app/Actions/` to understand:
- The actual business logic and constraints
- Side effects (notifications, activity logs, RCON commands, Discord API calls)
- Error conditions

Read relevant gates in `app/Providers/AuthServiceProvider.php` to understand who has access.

### 1c. Read Existing Handbook Pages

Before writing, check what already exists:

```
Glob: resources/library/books/staff-handbook/**/*.md
```

Also check the user handbook for pages you might cross-reference:
```
Glob: resources/library/books/user-handbook/**/*.md
```

### 1d. Check for Existing Content at the Target Location

Before creating files, check if pages already exist at the target path. If they do,
you are UPDATING existing documentation — read the existing content first and preserve
anything that's still accurate.

---

## Phase 2: Plan the Pages

Before writing, plan the page structure:

1. **Where do these pages go?** Determine the correct part/chapter in the staff handbook.
   The handbook has these parts:
   - `01-getting-started` — Onboarding, departments, ranks, the Ready Room
   - `02-user-management` — Reviewing new users, promotions, the Brig
   - `03-support-and-moderation` — Tickets, discipline reports
   - `04-meetings-and-tasks` — Meeting system, check-in reports, tasks
   - `05-minecraft` — Server operations, account management, plugins
   - `06-discord` — Role management, account issues, bot operations
   - `07-administration` — ACP, logs, announcements, CMS, staff positions

2. **How many pages?** Each page should cover ONE focused topic. 2-5 pages per chapter.

3. **What visibility?** Default to `staff`. Use `officer` only for admin-only features or
   sensitive procedures about managing other staff.

4. **What order?** Overview first, then procedures, then edge cases/troubleshooting.

5. **Cross-links?** Identify pages to link to using `[[path|Label]]` wiki links.
   You can link to both staff handbook and user handbook pages.

Present your plan to the user and wait for approval before writing.

---

## Phase 3: Write the Pages

### For each `_index.md` (section intro):
- Keep it to 1-3 sentences
- Summarize what the section covers
- Set visibility to `staff` (or `officer` if the entire section is officer-only)

### For each content page:
- Start with YAML front matter (title, visibility, order, summary)
- Use `##` headers (never `#`)
- Write short paragraphs (2-3 sentences max)
- Bold key UI elements and terms on first use
- Use numbered lists for sequential steps
- Use bullet lists for permissions, side effects, options
- Use `{{config:key}}` for config values (check the whitelist first)
- Use `[[path|Label]]` wiki links for cross-references

### Writing process for each page:
1. Write the page content
2. Re-read the relevant code to verify every claim is accurate
3. Check that config variables are on the safe whitelist
4. Check that wiki link paths are correct
5. Save the file

---

## Phase 4: Verify

After writing all pages:

1. **Accuracy check** — Re-read each page and verify against the code:
   - Are permission requirements correct (which ranks/departments)?
   - Are step sequences accurate?
   - Are side effects correctly described?

2. **Link check** — Verify all wiki link paths point to real pages

3. **Terminology check** — Use consistent terms from the style guide glossary

4. **Tone check** — Does it sound like a senior staff member training a new one?
   Not a manual, not condescending, not overly casual.

5. **Run tests**:
   ```bash
   ./vendor/bin/pest --group=docs
   ```

---

## Output

Write files to `resources/library/books/staff-handbook/` in the correct hierarchy.

After completing all pages, provide a summary listing:
- All files created or modified
- The visibility level of each page
- Any config keys used
- Any wiki links used
- Any questions or uncertainties about accuracy
