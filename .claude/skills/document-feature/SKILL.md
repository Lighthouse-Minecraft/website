---
name: document-feature
description: Generate comprehensive technical documentation for a single feature of the Lighthouse Website. Use when you need to document, audit, or understand a feature end-to-end.
argument-hint: [feature-name]
user-invocable: true
allowed-tools: Read, Grep, Glob, Bash, Write
---

# Feature Documentation Generator

You are a technical documentation agent for the Lighthouse Website, a Laravel + Livewire Volt
community management application. Your job is to produce a comprehensive technical reference
document for the feature named: **$ARGUMENTS**

**Output file:** `docs/features/$ARGUMENTS.md`

---

## Project Layout Reference

Every feature touches a subset of these locations:

```
app/
  Actions/           # Business logic (AsAction trait, ::run() invocation)
  Console/Commands/  # Artisan commands, some scheduled
  Enums/             # PHP backed enums with values/labels
  Http/Controllers/  # Thin controllers (renders, redirects, webhooks)
  Http/Middleware/    # Custom middleware
  Jobs/              # Queued background work
  Models/            # Eloquent models
  Notifications/     # Mail + Pushover notification classes
  Policies/          # Model-level authorization
  Providers/
    AuthServiceProvider.php  # Gate definitions (feature-level authorization)
  Services/          # Service classes (RCON, Discord, notifications, etc.)

config/              # App configuration files
database/migrations/ # Schema definitions

resources/views/
  livewire/          # Volt components (inline PHP class + Blade template)
  *.blade.php        # Layout and partial views

routes/web.php       # All routes (controller + Volt::route)
routes/console.php   # Scheduled tasks

tests/Feature/       # Pest test files organized by domain
```

---

## Phase 1: Discovery

Systematically search for ALL code related to the feature. Execute these searches in order,
building a complete picture. Be thorough -- missing files leads to incomplete documentation.

### 1a. Models

Search for models whose names relate to the feature:
- `Glob: app/Models/*.php` and grep for feature-related terms
- For each model found, read the entire file
- Note: `$fillable`, `casts()`, relationships (`hasMany`, `belongsTo`, `belongsToMany`, etc.),
  scopes, helper methods, computed properties, and boot/booted methods

### 1b. Enums

- `Grep` for feature-related terms in `app/Enums/`
- For each enum, record every case with its value and any label/helper methods

### 1c. Actions

- `Grep` for model names and feature terms in `app/Actions/`
- Read each relevant action completely. Document:
  - Method signature (`handle()` parameters and return type)
  - What models it creates/updates/deletes
  - Whether it calls `RecordActivity::run()` (and with what action string)
  - Whether it sends notifications (and which ones)
  - Whether it dispatches jobs
  - Whether it calls other actions (e.g., `SyncMinecraftRanks::run()`)

### 1d. Gates and Policies

**Gates:**
- Read `app/Providers/AuthServiceProvider.php`
- Extract every gate that references models, enums, or concepts related to this feature

**Policies:**
- `Grep` for model names in `app/Policies/`
- Read each relevant policy completely
- Document the `before()` hook (if any) and every method with its authorization logic

### 1e. Routes

- `Grep` for feature-related URL segments, controller names, and Volt component paths in `routes/web.php`
- Also check `routes/auth.php` if relevant
- For each route: HTTP method, URL, middleware, controller/component, route name

### 1f. Livewire Volt Components

- `Grep` for model names, action class names, and feature terms in `resources/views/livewire/`
- `Glob: resources/views/livewire/*feature-name-guess*/**/*.blade.php`
- For each Volt component, read the entire file and document:
  - PHP class: public properties, methods, authorization checks, validation rules,
    which actions are called, Flux toast messages, modal interactions, computed properties
  - Blade template: UI elements, data displayed, user interactions (buttons, forms, modals)

### 1g. Controllers

- `Grep` for feature terms and model names in `app/Http/Controllers/`
- Read relevant controllers and document each method

### 1h. Notifications

- `Grep` for feature terms and model names in `app/Notifications/`
- For each notification: what triggers it, channels (mail/Pushover), subject, content summary

### 1i. Jobs

- `Grep` for feature terms and model names in `app/Jobs/`
- Document: trigger, what it does, retry/delay configuration

### 1j. Console Commands & Scheduled Tasks

- `Grep` for feature terms in `app/Console/Commands/`
- Check `routes/console.php` for any scheduled tasks related to this feature

### 1k. Services

- `Grep` for feature terms and model names in `app/Services/`
- Document the service's public API and what calls it

### 1l. Middleware

- `Grep` for feature terms in `app/Http/Middleware/`

### 1m. Migrations

- `Grep` for table names (from models found) in `database/migrations/`
- Read each relevant migration to get exact schema: column types, nullability, defaults, indexes, foreign keys

### 1n. Tests

- `Grep` for model names, action names, and feature terms in `tests/Feature/`
- `Glob: tests/Feature/*feature-domain-guess*/**/*.php`
- List every test file and summarize what it covers (the `it('...')` descriptions)

### 1o. Config

- `Grep` for feature terms in `config/`
- Note any feature-specific configuration values or env variables

### 1p. Cross-Reference Sweep

After completing all searches above, do a final sweep:
- Grep the entire `app/` and `resources/views/` directories for each model class name found
- Grep for each action class name to find all callers
- Grep for each notification class name to find all senders
- Check dashboard widgets: `Grep` in `resources/views/livewire/dashboard/` and `resources/views/dashboard.blade.php`
- This catches indirect references that name-based searches miss

---

## Phase 2: Data Flow Tracing

For each major user interaction in the feature, trace the complete flow:

```
User Action (click/submit)
  -> Route (URL + middleware)
    -> Volt Component method (authorize, validate)
      -> Action::run(...) (business logic)
        -> Model mutations
        -> Activity log entry
        -> Notifications sent
        -> Jobs dispatched
        -> External syncs (Minecraft, Discord)
      -> UI feedback (Flux::toast, modal close, redirect)
```

Trace at minimum:
1. **Create** -- how is the primary entity created?
2. **View** -- how does a user see the entity? What authorization gates it?
3. **Update** -- how is the entity modified?
4. **Delete** -- how is the entity removed (if applicable)?
5. **Special workflows** -- state transitions, approvals, escalations, automated processes

---

## Phase 3: Write the Documentation

Produce a single markdown file with this exact structure. Every section is REQUIRED. If a
section has no content for this feature, write "Not applicable for this feature." so the
reader knows it was considered, not forgotten.

````markdown
# [Feature Name] -- Technical Documentation

> **Audience:** Project owner, developers, AI agents
> **Generated:** [today's date]
> **Generator:** `/document-feature` skill

---

## Table of Contents

[Generate based on sections below]

---

## 1. Overview

[2-4 paragraphs explaining:]
- What the feature does in plain language
- Who uses it (which user roles/membership levels)
- How it fits into the broader application
- Key concepts and terminology specific to this feature

---

## 2. Database Schema

For each table involved:

### `table_name` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| ... | ... | ... | ... | ... |

**Indexes:** [list notable indexes]
**Foreign Keys:** [list FK constraints]
**Migration(s):** `database/migrations/YYYY_MM_DD_HHMMSS_description.php`

---

## 3. Models & Relationships

For each model:

### ModelName (`app/Models/ModelName.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `methodName()` | hasMany | OtherModel | ... |

**Scopes:** [list query scopes]

**Key Methods:**
- `methodName(): ReturnType` -- description

**Casts:**
- `column_name` => `CastType`

---

## 4. Enums Reference

For each enum:

### EnumName (`app/Enums/EnumName.php`)

| Case | Value | Label | Notes |
|------|-------|-------|-------|
| ... | ... | ... | ... |

[Document any helper methods: points(), color(), label(), etc.]

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

| Gate Name | Who Can Pass | Logic Summary |
|-----------|-------------|---------------|
| `gate-name` | Role/rank description | Brief logic |

### Policies

#### ModelPolicy (`app/Policies/ModelPolicy.php`)

**`before()` hook:** [describe who bypasses, or "None"]

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `method` | Description | Logic details |

### Permissions Matrix

[Matrix table: rows = user types (Regular, Staff CrewMember, Officer, Admin, etc.),
columns = key actions for this feature]

---

## 6. Routes

| Method | URL | Middleware | Handler | Route Name |
|--------|-----|-----------|---------|------------|
| GET | /path | auth, ... | Component or Controller@method | route.name |

---

## 7. User Interface Components

For each Volt component or controller view:

### Component Name
**File:** `resources/views/livewire/path/component.blade.php`
**Route:** `/url-path` (route name: `route.name`)

**Purpose:** What this page/component does

**Authorization:** What gate/policy check is performed

**User Actions Available:**
- Action description -> calls `ActionClass::run()` -> result/feedback

**UI Elements:**
- Forms, tables, modals, badges, buttons (brief inventory)

---

## 8. Actions (Business Logic)

For each action:

### ActionName (`app/Actions/ActionName.php`)

**Signature:** `handle(Type $param, ...): ReturnType`

**Step-by-step logic:**
1. Validates/checks preconditions
2. Mutates model(s): [describe changes]
3. Logs activity: `RecordActivity::run($model, 'action_string', 'description')`
4. Sends notification: `NotificationClass` to [recipient]
5. Dispatches job: `JobClass` [if applicable]
6. Syncs external: [if applicable]

**Called by:** [list all callers -- components, other actions, commands, jobs]

---

## 9. Notifications

For each notification:

### NotificationClass (`app/Notifications/NotificationClass.php`)

**Triggered by:** ActionName or ComponentName
**Recipient:** Who receives it
**Channels:** mail, Pushover
**Mail subject:** "Subject line"
**Content summary:** Brief description
**Queued:** Yes/No

---

## 10. Background Jobs

[For each job, or "Not applicable for this feature."]

### JobClass (`app/Jobs/JobClass.php`)

**Triggered by:** What dispatches it
**What it does:** Description
**Queue/Delay:** Configuration details

---

## 11. Console Commands & Scheduled Tasks

[For each command, or "Not applicable for this feature."]

### `command:signature`
**File:** `app/Console/Commands/ClassName.php`
**Scheduled:** Yes (frequency) / No
**What it does:** Description

---

## 12. Services

[For each service, or "Not applicable for this feature."]

### ServiceName (`app/Services/ServiceName.php`)
**Purpose:** What the service encapsulates
**Key methods:**
- `methodName(params): ReturnType` -- description

---

## 13. Activity Log Entries

| Action String | Logged By | Subject Model | Description |
|---------------|-----------|---------------|-------------|
| `snake_case` | ActionName | ModelName | "Human description" |

---

## 14. Data Flow Diagrams

For each major workflow:

### [Workflow Name] (e.g., "Creating a New Entity")

```
User clicks [button] on [page]
  -> POST /route (middleware: auth, ...)
    -> VoltComponent::methodName()
      -> $this->authorize('gate-name')
      -> $this->validate([...])
      -> ActionName::run($params)
        -> Model created/updated: [fields]
        -> RecordActivity::run(...)
        -> NotificationClass sent to [recipient]
      -> Flux::toast('message', variant: 'success')
```

---

## 15. Configuration

[Env variables, config values, or "Not applicable for this feature."]

| Key | Default | Purpose |
|-----|---------|---------|
| `ENV_VAR_NAME` | value | What it controls |

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/Path/TestFile.php` | N tests | Brief description |

### Test Case Inventory

For each test file, list every `it('...')` description.

### Coverage Gaps

[Note any areas of the feature that appear to lack test coverage]

---

## 17. File Map

Complete list of every file involved in this feature:

**Models:** [list with full paths]
**Enums:** [list with full paths]
**Actions:** [list with full paths]
**Policies:** [list with full paths]
**Gates:** `AuthServiceProvider.php` -- gates: [list gate names]
**Notifications:** [list with full paths]
**Jobs:** [list with full paths]
**Services:** [list with full paths]
**Controllers:** [list with full paths]
**Volt Components:** [list with full paths]
**Routes:** [list route names and URLs]
**Migrations:** [list with full paths]
**Console Commands:** [list with full paths]
**Tests:** [list with full paths]
**Config:** [list relevant config keys]
**Other:** [middleware, partials, etc.]

---

## 18. Known Issues & Improvement Opportunities

[Based on your code analysis, note any:]
- Potential logic inconsistencies or edge cases
- Missing validation or authorization gaps
- Dead code or unused paths
- Performance concerns (N+1 queries, missing eager loads)
- Missing test coverage for important paths
- Hardcoded values that should be configurable
- TODO/FIXME comments found in the code
- Suggestions for future improvements
````

---

## Phase 4: Save the Documentation

Write the completed documentation to: `docs/features/$ARGUMENTS.md`

The Write tool will create intermediate directories automatically.

---

## Quality Checklist

Before saving, verify ALL of these:

- [ ] Every model relationship is documented (both sides)
- [ ] Every enum case is listed with value AND label
- [ ] Every gate and policy method is documented with who-can logic
- [ ] A permissions matrix shows user types vs. actions
- [ ] Every route is listed with method, URL, middleware, handler, and name
- [ ] Every action's step-by-step logic is documented
- [ ] Every notification's trigger, recipient, and content is documented
- [ ] Data flow diagrams trace from user click through to database and back
- [ ] The file map is COMPLETE -- no file referenced in the doc is missing from the map
- [ ] Migration-derived schema matches what the models expect
- [ ] Test coverage inventory lists every `it()` block
- [ ] Coverage gaps are explicitly called out
- [ ] The "Known Issues" section contains at least one observation (even if minor)

---

## Important Rules

- **Be exhaustive.** Better to document something trivial than miss something important.
- **Use exact file paths.** Always use the full path from project root.
- **Quote code identifiers exactly.** Gate names, action strings, enum cases, method names -- spell them exactly as they appear in the code.
- **Do not invent or assume.** If you cannot find something, say so explicitly. Never fabricate code that does not exist.
- **Cross-reference.** When an action sends a notification, reference the notification section. When a component calls an action, reference the action section.
- **Read entire files.** Do not skim. Authorization logic and edge cases hide in the details.
