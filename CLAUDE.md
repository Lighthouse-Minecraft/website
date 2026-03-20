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

## Workflow

### PRD-Driven Development

All non-trivial features follow this pipeline:

| Phase | Skill | Output |
|---|---|---|
| Requirements | `/write-a-prd` | GitHub issue with full PRD |
| Breakdown | `/prd-to-issues` | Vertical-slice GitHub issues (AFK/HITL) |
| Full Implementation | `/work-prd <number>` | All issues implemented, PR to staging |
| Single Issue | `/work-issue <number>` | One issue implemented on a branch |

### Branching Model

- **PRD branch**: `prd/<short-prd-name>` — created from `staging`, all issue work merges here.
- **Issue branch**: `prd/<short-prd-name>/<issue-short-name>` — created from the PRD branch.
- **Standalone branch**: `<descriptive-name>` — for quick fixes or single issues without a PRD.
- When all issues are complete, a PR is created from the PRD branch → `staging`.

### Issue Tracking

- All commits reference their GitHub issue number (e.g., `feat: add admin flag #281`).
- The final commit for an issue includes `Closes #<number>` in the message.
- Issues are labeled `in-progress` when work starts.
- A comment is added to the issue when work begins (agent name, branch).
- Before starting an issue, check for `in-progress` label to avoid collisions with other agents (e.g., Codex).

### Working a GitHub Issue

When implementing a GitHub issue (via `/work-issue` or manually):

1. **Check** the issue for `in-progress` label — skip if claimed by another agent.
2. **Label** the issue as `in-progress` and add a comment noting the branch.
3. **Read** the GitHub issue and its parent PRD for full context.
4. **Read** relevant existing files (don't guess at patterns).
5. **Build** one task at a time, following acceptance criteria.
6. **Test** with `./vendor/bin/pest` after each task.
7. **Commit** each logical unit with issue number reference. **Never push** — that's the user's step.
8. **Never** scatter authorization checks in Blade — use policies/gates only.

### Quick Fixes & Small Tasks

For tasks that don't warrant a PRD (bug fixes, small tweaks, single-file changes):
- Read the relevant code first.
- Implement, test, commit.
- No PRD or issue needed.

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
| Git/commit/test workflow | `/ai/AGENT_WORKFLOW.md` |
| Agent implementation guide | `/ai/AGENT_IMPLEMENTATION_GUIDE.md` |
| Authorization gates | `app/Providers/AuthServiceProvider.php` |
| User model | `app/Models/User.php` |
| Action example | `app/Actions/PromoteUser.php` |
| Volt component example | `resources/views/livewire/dashboard/stowaway-users-widget.blade.php` |
| Test example | `tests/Feature/Actions/Actions/PutUserInBrigTest.php` |
