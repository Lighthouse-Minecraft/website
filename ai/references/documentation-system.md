# Documentation System Reference

This is the technical reference for how the Lighthouse flat-file documentation system works.
Read this before writing any user documentation.

---

## File Structure

All documentation lives in `resources/library/`. There are two content types:

### Books (hierarchical)
```
resources/library/books/
  {book-name}/
    _index.md                    # Book intro page
    {NN}-{part-slug}/
      _index.md                  # Part intro page
      {NN}-{chapter-slug}/
        _index.md                # Chapter intro page
        {NN}-{page-slug}.md     # Content page
```

### Guides (flat, 1 level deep)
```
resources/library/guides/
  {guide-name}/
    _index.md                    # Guide intro page
    {NN}-{page-slug}.md         # Content page
```

### Naming Rules
- Directories and files use `NN-slug-name` format (e.g., `01-getting-started`, `02-minecraft`)
- The numeric prefix (`NN`) controls display order
- Slugs are kebab-case
- Every directory MUST have an `_index.md` file

---

## Front Matter (YAML)

Every `.md` file starts with YAML front matter between `---` fences:

```yaml
---
title: "Page Title"
visibility: public
order: 1
summary: "One-sentence description shown in listings."
---
```

### Required Fields

| Field | Type | Description |
|---|---|---|
| `title` | string | Display title (shown in headings, breadcrumbs, navigation) |
| `visibility` | string | Access control level (see below) |
| `order` | integer | Sort order within its parent directory |
| `summary` | string | Short description shown in parent listing pages |

### Visibility Levels

| Value | Who Can See It | When to Use |
|---|---|---|
| `public` | Anyone, including guests | Overview pages, "what is this" content, information that helps people decide to join |
| `users` | Logged-in users only | Actionable how-to content, step-by-step guides |
| `resident` | Resident level and above | Content specific to established members |
| `citizen` | Citizen level and above | Content specific to long-standing members |
| `staff` | Staff members only | Staff-facing procedures and guides |
| `officer` | Officers and above only | Leadership-level content |

**Visibility inheritance:** If a page doesn't specify visibility, it inherits from its
parent `_index.md`. Always set visibility explicitly for clarity.

**Key principle:** Public pages should answer "what is this and should I join?"
User-only pages should answer "how do I do this?"

---

## Markdown Content

After the front matter, write standard markdown. The body is rendered using Laravel's
`Str::markdown()` with CommonMark.

### Headers
- Use `##` (h2) and below in page content
- Never use `#` (h1) — the page title from front matter is rendered as h1 automatically
- Use headers to create scannable sections

### Special Features

#### Wiki Links — Link Between Pages

Use `[[path]]` or `[[path|Display Text]]` to link between documentation pages.

```markdown
See [[books/user-handbook/minecraft/accounts/joining-the-server]] for details.

Check the [[books/user-handbook/minecraft/accounts/linking-your-account|account linking guide]].
```

**Path format:** The path maps to the URL structure, NOT the file path. It uses the slug
names without numeric prefixes:
- File: `resources/library/books/user-handbook/02-minecraft/01-accounts/01-joining-the-server.md`
- Wiki link path: `books/user-handbook/minecraft/accounts/joining-the-server`

The system converts these to `/library/{path}` URLs automatically.

**Auto-labeling:** If you omit the label (`[[path]]`), the system generates one from the
last segment of the path, converting hyphens to spaces and capitalizing.

#### Config Variables — Dynamic Values

Use `{{config:key}}` to insert live configuration values instead of hardcoding numbers.

```markdown
You can link up to **{{config:lighthouse.max_minecraft_accounts}}** Minecraft accounts.
```

This renders as the actual config value (e.g., "2") at display time.

**Safe whitelist:** Only these config keys are allowed (others are silently ignored):

| Key | Description | Default |
|---|---|---|
| `lighthouse.max_minecraft_accounts` | Max MC accounts per user | 2 |
| `lighthouse.max_discord_accounts` | Max Discord accounts per user | 1 |
| `lighthouse.minecraft_verification_grace_period_minutes` | Verification code timeout | 30 |
| `lighthouse.minecraft.server_name` | Server display name | Lighthouse MC |
| `lighthouse.minecraft.server_host` | Server hostname | play.lighthousemc.net |
| `lighthouse.minecraft.server_port_java` | Java server port | 25565 |
| `lighthouse.minecraft.server_port_bedrock` | Bedrock server port | 19132 |
| `lighthouse.donation_goal` | Monthly donation goal | 60 |
| `app.name` | Application name | Lighthouse |

**Adding new config keys to the whitelist:**
Edit `app/Services/Docs/PageDTO.php` → `safeConfigKeys()` method. Only add keys that are
safe for any user to see. Never add API keys, tokens, secrets, or internal system settings.

---

## File Locations Quick Reference

| What | Where |
|---|---|
| Documentation content | `resources/library/books/` and `resources/library/guides/` |
| Documentation service | `app/Services/DocumentationService.php` |
| Page DTO (rendering, wiki links, config vars) | `app/Services/Docs/PageDTO.php` |
| Section listing component | `resources/views/components/library/section-listing.blade.php` |
| Reader component | `resources/views/components/library/reader.blade.php` |
| Navigation component | `resources/views/components/library/navigation.blade.php` |
| Livewire page components | `resources/views/livewire/library/*.blade.php` |
| Config file | `config/lighthouse.php` |
| Documentation tests | `tests/Feature/Docs/` |

---

## Creating New Sections

When creating a new part, chapter, or any directory level:

1. Create the directory with the `NN-slug` naming convention
2. Create an `_index.md` inside it with front matter
3. Keep `_index.md` content brief (1-3 sentences)
4. The `_index.md` summary appears in the parent's listing

### Example: Adding a new chapter

```
resources/library/books/user-handbook/02-minecraft/
  02-gameplay/                    # New chapter directory
    _index.md                     # Chapter intro (required)
    01-getting-around.md          # First page
    02-builds-and-projects.md     # Second page
```

The `_index.md` would be:
```markdown
---
title: "Gameplay"
visibility: public
order: 2
summary: "Tips and information about playing on the Lighthouse server."
---

Learn about the gameplay features, commands, and activities available
on the Lighthouse Minecraft server.
```
