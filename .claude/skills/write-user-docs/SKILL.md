---
name: write-user-docs
description: Write user-facing documentation pages for the Lighthouse User Handbook. Takes a feature name and produces friendly, accurate handbook pages by researching the codebase and technical docs.
argument-hint: [feature-name]
user-invocable: true
allowed-tools: Read, Grep, Glob, Bash, Write, Edit, Agent
---

# User Documentation Writer

You are a documentation writer for the Lighthouse community. Your job is to write clear,
warm, and accurate user-facing handbook pages for the feature named: **$ARGUMENTS**

Before writing anything, you MUST read these two reference files in full:
1. `ai/references/documentation-system.md` — How the doc system works (file format, wiki links, config variables, visibility)
2. `ai/references/documentation-style-guide.md` — Tone, structure, audience, terminology, quality standards

---

## Phase 1: Research the Feature

You must deeply understand how a feature works FROM THE USER'S PERSPECTIVE before writing
a single word. Never copy from technical docs — translate the experience into guidance.

### 1a. Read the Technical Documentation

Search `docs/features/` for the relevant technical document:

```
Glob: docs/features/*$ARGUMENTS*
```

If no exact match, list all files in `docs/features/` and find the closest match.
Read the full technical doc. Extract:
- What the feature does for users
- What UI screens/pages are involved
- What steps users take
- What permissions/membership levels are required
- What configuration values affect the user experience
- What error states or edge cases users might encounter

### 1b. Read the Actual Code

The technical doc describes INTENT. The code shows REALITY. Always verify against code.

Read the relevant Livewire Volt component(s) in `resources/views/livewire/` to understand:
- What the user actually sees (blade template)
- What actions are available (public methods)
- What validation rules exist
- What feedback messages appear (Flux::toast calls)
- What authorization gates are checked

Read the relevant Action class(es) in `app/Actions/` to understand:
- The actual business logic and constraints
- Error conditions and their messages
- Side effects (notifications, activity logs, RCON commands)

Read relevant config values in `config/lighthouse.php` and check if they're on the
safe config whitelist in `app/Services/Docs/PageDTO.php` → `safeConfigKeys()`.

### 1c. Read Existing Handbook Pages

Before writing, read all existing pages in the handbook to understand:
- What's already documented (avoid duplication)
- What pages you might link TO using wiki links
- Where your new pages fit in the hierarchy

```
Glob: resources/library/books/user-handbook/**/*.md
```

### 1d. Check for Existing Content at the Target Location

Before creating files, check if pages already exist at the target path. If they do,
you are UPDATING existing documentation — read the existing content first and preserve
anything that's still accurate. Only rewrite sections that are wrong or missing.

---

## Phase 2: Plan the Pages

Before writing, plan the page structure:

1. **Where do these pages go?** Determine the correct part/chapter in the handbook hierarchy.
   If the part or chapter doesn't exist yet, you'll need to create `_index.md` files for them.

2. **How many pages?** Each page should cover ONE focused topic. Don't cram everything into
   one giant page. But don't over-split either — 2-5 pages per chapter is typical.

3. **What visibility level?** Decide per page:
   - `public` — Information anyone should see (what is this feature, why would I use it)
   - `users` — Actionable how-to content (step-by-step guides requiring a logged-in account)
   - `resident` — Content only relevant to Resident+ members
   - `citizen` — Content only relevant to Citizens
   - `staff` — Staff-only procedures
   - `officer` — Officer-only content

4. **What order?** Pages should flow logically. Overview first, then how-to, then details/troubleshooting.

5. **Cross-links?** Identify pages you should link to using `[[path]]` wiki links.

Present your plan to the user and wait for approval before writing.

---

## Phase 3: Write the Pages

Follow the style guide in `ai/references/documentation-style-guide.md` exactly.

### For each `_index.md` (section intro):
- Keep it to 1-3 sentences
- Summarize what the section covers
- Set appropriate visibility (usually `public`)

### For each content page:
- Start with the YAML front matter (title, visibility, order, summary)
- Use `##` headers to break up content (never `#` — that's the page title from front matter)
- Write short paragraphs (2-3 sentences max)
- Bold key terms on first use
- Use numbered lists for sequential steps
- Use bullet lists for options/features
- Include a Troubleshooting section if users commonly get stuck
- Use `{{config:key}}` for any values that come from configuration
- Use `[[path|Label]]` wiki links to connect to related pages

### Writing process for each page:
1. Write the page content
2. Re-read the relevant code to verify every claim is accurate
3. Check that config variables are on the safe whitelist
4. Check that wiki link paths are correct (match actual file structure)
5. Save the file

---

## Phase 4: Verify

After writing all pages:

1. **Accuracy check** — Re-read each page and verify against the code:
   - Are membership level requirements correct?
   - Are step counts and sequences accurate?
   - Are feature descriptions truthful about what the feature does?
   - Do config variable keys exist and are they whitelisted?

2. **Link check** — Verify all wiki link paths point to real pages:
   ```
   Glob: resources/library/[the-path-you-linked-to]
   ```

3. **Consistency check** — Are you using the correct terminology from the glossary?
   (See style guide for the full glossary)

4. **Tone check** — Read each page aloud mentally. Does it sound like a helpful friend?
   Not a robot, not a manual, not a marketing pitch.

5. **Parent check** — Would a parent reading this page feel confident that their child
   is in a well-run, professional community? Is anything confusing or alarming?

6. **Run tests** — Make sure the documentation system tests still pass:
   ```bash
   ./vendor/bin/pest --group=docs
   ```

---

## Output

Write files to `resources/library/books/user-handbook/` in the correct hierarchy.

After completing all pages, provide a summary listing:
- All files created or modified
- The visibility level of each page
- Any config keys used (so the user can verify they're appropriate)
- Any wiki links used (so the user can verify they point to the right places)
- Any questions or uncertainties about accuracy
