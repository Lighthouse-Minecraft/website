# Agent Workflow

Rules for how Claude (and other agents) operate in this repository during a session.

---

## Branch Strategy

### Branch Types

| Type | Pattern | Base | Purpose |
|---|---|---|---|
| PRD branch | `prd-<short-prd-name>` | `staging` | Integration branch for an entire PRD (hyphen, not slash) |
| Issue branch | `prd-<short-prd-name>/<issue-short-name>` | PRD branch | Single issue from a PRD (deleted after merge) |
| Standalone branch | `<descriptive-name>` | `staging` | Quick fix or standalone issue |

### Rules

- **Never push** any branch — push is always a manual step done by the user.
- Never force-push to `main` or `staging`.
- Never commit directly to `main` or `staging`.
- Use kebab-case for all branch names.
- Name branches after their purpose, not issue numbers.

### PRD Branching Flow

```
staging
  └── prd-role-migration              (PRD branch — hyphen separator)
        ├── prd-role-migration/admin-flag       (issue branch → merge back → delete)
        ├── prd-role-migration/seed-roles       (issue branch → merge back → delete)
        ├── prd-role-migration/gate-refactor    (issue branch → merge back → delete)
        └── ... (each issue merges into PRD branch, then branch is deleted)

  When all issues are done:
    PR: prd-role-migration → staging
```

**Why hyphen?** Git cannot have both `prd/foo` (branch ref) and `prd/foo/bar` (sub-ref) simultaneously. Using `prd-foo` for the PRD branch and `prd-foo/bar` for issue branches avoids this conflict.

---

## PRD-Driven Development Pipeline

Features flow through these phases:

| Phase | Skill | Output |
|---|---|---|
| Requirements | `/write-a-prd` | GitHub issue with full PRD |
| Breakdown | `/prd-to-issues` | Vertical-slice GitHub issues (AFK/HITL) |
| Full Implementation | `/work-prd <number>` | All issues implemented, PR to staging |
| Single Issue | `/work-issue <number>` | One issue implemented on a branch |

### `/work-prd` Orchestration

The `/work-prd` skill manages the full lifecycle:
1. Creates the PRD branch from staging
2. Works each issue in dependency order, spawning a fresh agent per issue
3. Merges each issue branch into the PRD branch after completion
4. Pauses between issues for user review
5. Stops for HITL issues that require human input
6. Runs documentation updates after all issues are complete
7. The user creates the PR from the PRD branch → staging

### Working a GitHub Issue

When implementing via `/work-issue`:

1. **Check** the issue for `in-progress` label — skip if claimed by another agent.
2. **Label** the issue `in-progress` and add a comment (agent name, branch).
3. **Read** the GitHub issue and parent PRD for full context.
4. **Read** relevant codebase files (don't guess at patterns).
5. **Build** one acceptance criterion at a time.
6. **Test** with `./vendor/bin/pest` after each logical unit.
7. **Commit** with issue number reference (e.g., `feat: add admin flag #281`).
8. **Final commit** includes `Closes #<number>` in the message.
9. **Never push** — that's the user's step.

---

## Issue Tracking

### Labels

- `in-progress` — Added when an agent starts working an issue. Check before starting to avoid collisions with other agents (e.g., Codex working the same PRD).

### Commit Messages

All commits for a GitHub issue must reference the issue number:

```
<type>: <short summary> #<issue-number>

<optional body: why, not what>

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>
```

The **final commit** for an issue uses `Closes` to auto-close when merged to main:

```
<type>: <short summary>

Closes #<issue-number>

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>
```

### Commit Types

Types: `feat`, `fix`, `test`, `refactor`, `docs`, `chore`.

---

## Before Writing Any Code

1. Read `CLAUDE.md`.
2. Read the files relevant to the task (use the key file table in `CLAUDE.md`).
3. If working from a GitHub issue, read the issue and parent PRD.
4. Confirm understanding before proceeding.

---

## Commit Discipline

- Commit each logical unit (migration, action, component, tests) separately.
- Never commit `.env` or secrets.
- Never skip pre-commit hooks (`--no-verify`).
- Never amend a published commit.

---

## Test Discipline

Run tests after each logical unit of work:
```bash
./vendor/bin/pest
# or a targeted subset:
./vendor/bin/pest tests/Feature/Actions/
./vendor/bin/pest --group=brig
```

Rules:
- All tests must pass before committing.
- No skipped tests without a documented reason.
- Mock `MinecraftRconService` in any test that touches Minecraft actions.
- Notifications are globally faked in `tests/Pest.php` — do not re-fake unless asserting specific sends.

---

## Safe Refactor Approach

- Only refactor code that is directly in scope of the current task.
- Do not "clean up" surrounding code unless asked.
- Do not add docstrings, comments, or type hints to code you didn't change.
- If a refactor would change behavior, confirm with the user first.

---

## Documentation Updates

Documentation is updated after all issues are complete but **before** the PR is created (in both `/work-prd` and `/auto-process-prd`):
1. `/document-feature <feature-name>` — technical documentation
2. `/write-user-docs <feature-name>` — if the PRD has user-facing aspects
3. `/write-staff-docs <feature-name>` — if the PRD has staff-facing aspects

This ensures docs reflect the complete feature, not partial slices. Documentation changes are committed to the PRD branch before pushing and opening the PR.

---

## PR Description Template

```markdown
## Summary
- What this PR does (2-3 bullets)
- Why it was needed

## Changes
- [ ] Migration(s): list tables/columns
- [ ] Action(s): list classes
- [ ] Notification(s): list classes
- [ ] Gate/Policy: list new rules
- [ ] Livewire component(s): list paths
- [ ] Tests: list test files added/modified

## Test Plan
- `./vendor/bin/pest` passes
- Manually verified: [describe what you clicked/tested]

## Edge Cases Considered
- [list any]

🤖 Generated with Claude Code
```

---

## Risky Operations — Always Confirm First

Never do these without explicit user confirmation:
- `git push` — **NEVER push. Ever. Pushing is always done by the user.**
- Creating or closing GitHub Issues or PRs
- Dropping database tables or columns
- `git reset --hard` or `git checkout .`
- Modifying CI/CD configuration
- Changing environment variables or secrets

---

## Session Startup Checklist

At the start of every session:

- [ ] Read `CLAUDE.md`.
- [ ] Check `MEMORY.md` for active work or context.
- [ ] Run `git status` to understand the current branch and any uncommitted changes.
- [ ] If starting a new project: checkout `staging`, pull, then create a new descriptive branch.
- [ ] If continuing existing work: confirm you are on the correct branch.
- [ ] Ask: "Is there in-progress work to continue, or a new task?"
