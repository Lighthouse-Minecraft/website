# Documentation Style Guide

This guide defines how user-facing documentation for Lighthouse should read, feel, and
be structured. Every handbook page must follow these standards.

---

## Who We're Writing For

The Lighthouse User Handbook serves three overlapping audiences. Keep all of them in mind:

### Community Members (Primary)
- Ages range from teens to adults
- Varying technical skill levels — some are very comfortable with computers, others are not
- They want to know how to do things quickly and get back to playing
- They appreciate being treated like people, not ticket numbers

### Parents and Guardians
- Evaluating whether Lighthouse is safe and trustworthy for their children
- Looking for professionalism, clear rules, and evidence of good moderation
- Want to understand what their kids are getting into
- May read public-facing pages before allowing their child to join
- The Parent Portal documentation specifically serves them

### Returning Members
- Know the basics but need a refresher on a specific feature
- Scanning for the answer to a specific question
- Want to find information fast, not read a novel

---

## Voice and Tone

### The Lighthouse Voice
Write as a **helpful friend** who happens to know how everything works. Warm, encouraging,
clear. Like a friendly staff member walking someone through something in person.

### Tone Rules

**DO:**
- Use "you" and "your" — speak directly to the reader
- Be encouraging — "You're all set!" rather than "Process complete."
- Be reassuring — "Don't worry, you can undo this" when describing reversible actions
- Be honest — If something takes time or has limitations, say so plainly
- Be specific — "Click the Minecraft Accounts tab" not "Navigate to the appropriate section"
- Use contractions — "you'll", "don't", "it's" (natural speech)
- Acknowledge the reader's feelings — "It can be frustrating if your code expires"

**DON'T:**
- Sound like a technical manual — no "The system shall..." or "Users must..."
- Sound like marketing — no "Amazing features!" or "You'll love this!"
- Use jargon without explanation — if you must use a technical term, explain it on first use
- Be condescending — don't over-explain obvious things
- Use passive voice when active is clearer — "Staff will review your account" not "Your account will be reviewed"
- Add unnecessary filler — get to the point
- Use emojis — unless the user specifically asks for them

### Example Comparisons

**Too technical:**
> The verification code is generated using a 6-character alphanumeric string and has a
> configurable TTL defined in the lighthouse.minecraft_verification_grace_period_minutes
> configuration key.

**Too casual:**
> So basically you get this code thingy and you gotta type it in before it goes poof lol

**Just right:**
> Once your account is found, the system generates a **verification code** and displays it
> on the screen. You'll have a limited time to join the server and enter the code before it
> expires. If it does expire, you can always generate a new one.

---

## Content Structure

### Page Layout Pattern

Most handbook pages follow this structure:

```markdown
---
title: "Page Title"
visibility: public
order: 1
summary: "One clear sentence about what this page covers."
---

## What This Is / Overview
Brief explanation of the feature or concept. 2-3 sentences max.

## How It Works / Before You Start
Prerequisites, context, or high-level process. Help the reader
understand what they're about to do.

## Step-by-Step (if applicable)
### 1. First Step
Clear instruction with expected outcome.

### 2. Second Step
Continue the sequence.

## Additional Details
Configuration options, edge cases, or "good to know" information.

## Troubleshooting (if applicable)
### "Common problem"
Solution or explanation.
```

### Formatting Rules

- **Paragraphs:** 2-3 sentences max. Break up walls of text.
- **Headers:** Use `##` to create scannable sections. A reader should be able to skim
  headers and find what they need.
- **Bold:** Use on first mention of key terms: **whitelist**, **verification code**,
  **primary account**. Don't bold everything.
- **Numbered lists:** For sequential steps (do this, then this, then this).
- **Bullet lists:** For non-sequential items (features, options, examples).
- **Tables:** For structured comparisons (ranks, features by level).
- **Code formatting:** Use `backticks` for commands the user types (e.g., `/verify ABC123`).
- **Line breaks:** Use `--` for em dashes, not `—` (markdown rendering consistency).

### Summaries (front matter)

The `summary` field appears in parent listing pages. Write it as one clear sentence:
- Describe what the page helps the reader DO, not what the page IS
- Good: "Step-by-step guide to connecting your Minecraft account."
- Bad: "This page describes the Minecraft account linking process."
- Keep under ~80 characters

### Index Pages (_index.md)

Keep these brief — 1-3 sentences. Their job is to:
1. Tell the reader what this section covers
2. Set context for the child pages listed below

Don't repeat information that's in the child pages.

---

## Visibility Decisions

Choose visibility based on the reader's needs at that point:

| Content Type | Visibility | Reasoning |
|---|---|---|
| "What is this feature?" | `public` | Helps prospective members understand what we offer |
| "What do I need before I start?" | `public` | Prerequisites help people prepare |
| "How do I set this up?" (step-by-step) | `users` | Requires being logged in to act on |
| "How do I manage/change this?" | `users` | Requires being logged in |
| Rank-specific features | Match the rank | Only relevant to those members |
| Staff procedures | `staff` | Internal only |
| Rules and expectations | `public` | Transparency builds trust with parents |

**Important:** Public pages serve double duty. They're documentation for members AND
a signal to parents that this community is well-organized and transparent.

---

## Terminology Glossary

Use these terms consistently. Don't invent synonyms.

### Community Terms

| Term | Usage | Notes |
|---|---|---|
| Lighthouse | The community name | Always capitalized |
| staff | Community volunteers | Lowercase unless starting a sentence |
| member | Anyone with an account | Generic term for all users |
| player | Someone on the Minecraft server | Use when referring to in-game context |

### Membership Levels (in order)

| Term | Description | Notes |
|---|---|---|
| Drifter | Brand new account, hasn't accepted rules | First level, very limited |
| Stowaway | Accepted community rules | Can start linking accounts |
| Traveler | Approved by staff | First level with server access |
| Resident | Established community member | Has additional perks |
| Citizen | Long-standing trusted member | Highest membership level |

### Feature Terms

| Use This | Not This | Context |
|---|---|---|
| link (your account) | connect, attach, bind | Minecraft/Discord account linking |
| unlink | disconnect, remove, detach | Removing a linked account |
| verify / verification | confirm, authenticate | The in-game code verification step |
| verification code | auth code, OTP, token | The 6-character code |
| whitelist | allowlist, approved list | Server access list |
| the Brig | ban, suspension, jail | Lighthouse discipline system |
| support ticket | help request, issue | How users get help |
| membership level | rank (on website) | Website-side progression |
| in-game rank | server rank | Minecraft-side rank display |
| primary account | main account, default | The MC account used for avatar |
| promote / promotion | level up, upgrade | Moving to a higher membership level |
| Dashboard | home page, main page | The user's main page after login |
| profile page | user page, my page | The user's public profile |

### Things to Never Mention in User Docs

- Internal staff procedures or tools
- Specific moderation actions against individual users
- How to circumvent restrictions or game the system
- Technical implementation details (database tables, API endpoints, class names)
- Exact security measures (don't help bad actors)
- Revenue/financial details beyond the donation page
- Information about the AI systems used internally

---

## Writing About Sensitive Topics

### The Brig (Discipline System)
- Mention it exists: "If your account has been restricted by staff..."
- Don't explain how to get out of it in public pages
- Don't describe what actions lead to it (that's in the rules)
- Keep tone neutral — not threatening, not dismissive

### Age and Safety
- Never reference specific age requirements in docs (they're in the registration flow)
- Describe safety features positively: "we keep things safe and friendly" not "we prevent predators"
- Parent Portal docs should be reassuring and transparent

### Staff Actions
- Describe from the user's perspective: "Staff will review your account" not "Staff run the PromoteUser action"
- Don't explain internal decision-making processes
- Present staff as helpful people, not authorities

---

## Config Variables in Practice

When a value comes from configuration, ALWAYS use `{{config:key}}` instead of hardcoding:

**Wrong:**
```markdown
You can link up to **2** Minecraft accounts.
```

**Right:**
```markdown
You can link up to **{{config:lighthouse.max_minecraft_accounts}}** Minecraft accounts.
```

Before using a config key, verify it's in the safe whitelist by checking
`app/Services/Docs/PageDTO.php` → `safeConfigKeys()`. If you need a key that's not
whitelisted, note it in your output so the developer can add it.

---

## Site URLs in Practice

When linking to pages on the Lighthouse website (not other documentation pages), use the
`{{url:/path}}` syntax instead of hardcoding URLs:

**Wrong:**
```markdown
Visit the [Staff Page](https://lighthousemc.net/staff) to see the team.
```

**Right:**
```markdown
Visit the [Staff Page]({{url:/staff}}) to see the team.
```

This generates the correct full URL for whatever environment the site is running on.

Use `{{url:/path}}` for site pages. Use `[[wiki links]]` for other documentation pages.

---

## Wiki Links in Practice

Link to related pages when it genuinely helps the reader. Don't over-link.

**Good linking:**
```markdown
Before you can join, you'll need to
[[books/user-handbook/minecraft/accounts/linking-your-account|link your Minecraft account]].
```

**Over-linking (don't do this):**
```markdown
You can check your [[books/user-handbook/getting-started/welcome/introduction|membership]]
on your [[books/user-handbook/getting-started/welcome/introduction|profile page]] and
[[books/user-handbook/minecraft/accounts/linking-your-account|link]] your
[[books/user-handbook/minecraft/accounts/joining-the-server|Minecraft account]].
```

**Rules:**
- Link on first mention of a concept that has its own page
- Don't link the same page twice in one section
- Use descriptive labels: `[[path|link your Minecraft account]]` not `[[path|click here]]`
- Verify the path points to a real page before using it

---

## Quality Checklist

Before considering a page done, verify:

- [ ] Front matter is complete (title, visibility, order, summary)
- [ ] Summary is a clear, actionable sentence under ~80 characters
- [ ] Visibility level is appropriate for the content
- [ ] All facts have been verified against the actual code
- [ ] Config variables use `{{config:key}}` syntax (not hardcoded values)
- [ ] All config keys used are on the safe whitelist
- [ ] Site page links use `{{url:/path}}` syntax (not hardcoded domains)
- [ ] Wiki links point to real, existing pages
- [ ] Terminology matches the glossary
- [ ] Paragraphs are 2-3 sentences max
- [ ] Headers create a scannable structure
- [ ] A parent reading this would feel confident about the community
- [ ] No internal/technical details are exposed
- [ ] Tone sounds like a helpful friend, not a manual or marketing copy
