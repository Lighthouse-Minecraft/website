# Feature Planning Protocol

Follow this protocol before writing a single line of code for any non-trivial feature.

---

## Step 1 — Clarifying Questions

Ask as many questions as needed to produce a complete, unambiguous plan. Cover any of the following that are unclear:

- [ ] Which users/roles trigger this feature?
- [ ] What model(s) are created/modified/deleted?
- [ ] Are there Minecraft side effects (RCON, rank sync)?
- [ ] What notifications should be sent, and to whom?
- [ ] Should activity be logged? What action string?
- [ ] Is there a UI component or is it admin-only?
- [ ] Are there time-based or scheduled aspects?
- [ ] What are the authorization rules?

If all of the above are obvious from context, skip to Step 2.

---

## Step 2 — Required Spec Artifacts

Write a spec (in a plan file or in-chat) that includes ALL of the following sections.
Do not skip sections — write "N/A" if not applicable.

### 2a. Feature Summary
One paragraph: what this does, who uses it, why it exists.

### 2b. Authorization Rules
- Gate name(s) to create or reuse.
- Policy method(s) to add or modify.
- Which roles/ranks/membership levels are allowed.

### 2c. Database Changes
- List every migration needed.
- For each: table name, columns added/modified/removed, and their types/defaults.
- Foreign keys, indexes.

### 2d. Action Classes
For each new Action:
- Class name
- Parameters (`handle(User $user, string $reason, ...)`)
- Side effects checklist:
  - [ ] Model mutations + save
  - [ ] RecordActivity call (with action string)
  - [ ] SyncMinecraftRanks (if applicable)
  - [ ] Notifications (which class, to whom)

### 2e. Notification Classes
For each new Notification:
- Class name
- Recipient(s)
- Channels (mail, Pushover, or both)
- Subject line
- Key content (what info to include)
- Triggered by which Action

### 2f. Livewire Volt Components
For each new or modified component:
- File path
- Public properties
- Methods (and what they authorize/validate/do)
- Flux UI elements needed (modals, tables, forms)

### 2g. Routes
- New route(s): method, path, handler, middleware
- Route name(s)

### 2h. Test Plan
For each Action, list the test cases:
- Happy path
- Each guard/early-return case
- Activity log recorded
- Notification sent
- DB state after action

For each Livewire component, list:
- Renders correctly for authorized users
- Renders blocked for unauthorized users
- Each method: success and failure paths

### 2i. Edge Cases & Risks
- What happens if the user is already in the target state?
- What if Minecraft RCON fails?
- Concurrent requests? Race conditions?
- Notification flooding risk?

### 2j. Rollout Notes
- Any feature flags needed?
- Any data backfill needed after migration?
- Can this be deployed without downtime?

---

## Step 3 — File-by-File Task List

Write an ordered list of every file to create or modify:

```
[ ] database/migrations/YYYY_MM_DD_create_xxx_table.php
[ ] app/Actions/DoSomething.php
[ ] app/Notifications/SomethingHappenedNotification.php
[ ] app/Providers/AuthServiceProvider.php  (add gate)
[ ] resources/views/livewire/path/component-name.blade.php
[ ] routes/web.php  (add route)
[ ] tests/Feature/Domain/DoSomethingTest.php
[ ] tests/Feature/Livewire/ComponentNameTest.php
```

Work through this list top-to-bottom. Do not jump ahead.

---

## Definition of Done

A feature is complete when ALL of these are true:

- [ ] All migrations run cleanly (`php artisan migrate:fresh` passes).
- [ ] All new/modified Actions have test coverage.
- [ ] All authorization rules are tested (authorized and unauthorized cases).
- [ ] All new Notifications have test coverage (sent to correct recipients).
- [ ] Activity logging is tested.
- [ ] All Livewire components render and function (tested with Livewire test utilities).
- [ ] `php artisan test` (or `./vendor/bin/pest`) passes with no failures or skips.
- [ ] No scattered `@if($user->in_brig)` or ad-hoc auth checks in Blade templates.
- [ ] Code follows all conventions in `/ai/CONVENTIONS.md`.

---

## Anti-Patterns to Avoid

- Do NOT put business logic in Livewire component methods — extract to an Action.
- Do NOT call `$user->notify(...)` directly — use `TicketNotificationService::send(...)`.
- Do NOT add brig/auth checks in Blade templates — use gates and policies.
- Do NOT write migrations without planning the rollback.
- Do NOT implement a feature before getting plan approval.
- Do NOT add unrelated "improvements" while implementing a planned feature.
