# CLAUDE.md — Read This First

This file is the entry point for every AI session on the Lighthouse Website project.
Read this entire file before touching any code. Then read the files in `/ai/` that are
relevant to your task.

---

## Repo Purpose

The Lighthouse Website is a **Laravel + Livewire Volt** community management app for a
Minecraft server community. It manages user membership levels, staff positions, the "Brig"
discipline system, meeting notes, support tickets, prayer tracking, announcements, and
Minecraft account verification/synchronization.

---

## Architecture Summary

See `/ai/ARCHITECTURE.md` for full details.

- **Backend**: Laravel (PHP), no API layer — all logic goes through Action classes.
- **Frontend**: Livewire Volt (full-stack blade components with inline PHP classes).
- **UI**: Flux UI component library (`flux:*` tags).
- **Auth/Authorization**: Laravel Policies + Gates (in `AuthServiceProvider`).
- **Notifications**: `TicketNotificationService` wrapping Laravel Notifications (mail + Pushover).
- **Testing**: Pest (not PHPUnit raw). All tests in `tests/Feature/`.
- **Background jobs**: Laravel Job classes in `app/Jobs/` (not anonymous dispatch closures).

---

## Modes of Operation

### PLAN MODE — Claude Code Only (Handoff to Codex)
Use PLAN MODE before writing any code for a feature that:
- touches more than 2 files, OR
- involves a migration, OR
- changes authorization/policies, OR
- requires a new Action or Notification class

**In PLAN MODE you must:**
1. Read all relevant existing files first.
2. Ask whatever clarifying questions are needed to produce a complete, unambiguous plan.
3. Write a self-contained handoff plan to `ai/plans/YYYY-MM-DD-feature-name.md`
   following the format in `/ai/PLAN_TEMPLATE.md`.
4. Get explicit approval before switching to BUILD MODE or handing off to Codex.

**To invoke planning only (no implementation):**
> "Plan the [feature] feature. Generate a handoff plan. Do not write any code."

### BUILD MODE
Only enter BUILD MODE after a plan is approved. Then:
- Implement exactly what was planned, no scope creep.
- Run tests after each logical unit of work.
- Mark each task complete as you finish it.

---

## Required Workflow for Feature Work

1. **Branch** — checkout `staging`, pull, create a new descriptive branch.
2. **Read** relevant existing files (don't guess at patterns).
3. **Ask** all questions needed to fully understand requirements before planning.
4. **Plan** — write to `ai/plans/YYYY-MM-DD-name.md`, commit the plan file, get approval.
5. **Build** one task at a time.
6. **Test** with `./vendor/bin/pest` after each task.
7. **Commit** each logical unit. **Never push** — that's the user's step.
8. **Never** scatter authorization checks in Blade — use policies/gates only.

---

## Key Conventions (Quick Reference)

Full details in `/ai/CONVENTIONS.md`.

- Actions: `app/Actions/ClassName.php`, `use AsAction`, invoke via `ClassName::run(...)`.
- Activity log: `RecordActivity::run($model, 'snake_action', 'Description.')`.
- Notifications: always via `TicketNotificationService::send($user, $notification)`.
- Auth in Livewire: `$this->authorize('gate-name')`.
- Auth in Blade: `@can('gate-name') ... @endcan`.
- Authorization enforcement: **policies and gates only** — no `@if($user->in_brig)` spread through views.
- Livewire feedback: `Flux::toast('message', 'Title', variant: 'success|danger')`.
- Modals: `Flux::modal('name')->show()` / `->close()`.

---

## Files to Read for Context

| Topic | File |
|---|---|
| Architecture | `/ai/ARCHITECTURE.md` |
| Naming & patterns | `/ai/CONVENTIONS.md` |
| Planning a feature | `/ai/FEATURE_PLANNING_PROTOCOL.md` |
| Handoff plan format | `/ai/PLAN_TEMPLATE.md` |
| Approved plans | `/ai/plans/` |
| Git/PR + split workflow | `/ai/AGENT_WORKFLOW.md` |
| Codex guidance | `/ai/CODEX_COMPAT.md` |
| Authorization gates | `app/Providers/AuthServiceProvider.php` |
| User model | `app/Models/User.php` |
| Action example | `app/Actions/PromoteUser.php` |
| Volt component example | `resources/views/livewire/dashboard/stowaway-users-widget.blade.php` |
| Test example | `tests/Feature/Actions/Actions/PutUserInBrigTest.php` |
