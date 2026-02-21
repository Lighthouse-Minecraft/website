# Agent Workflow

Rules for how Claude (and other agents) operate in this repository during a session.

---

## Branch Strategy

- **Base branch**: `staging` — all new project branches are created from `staging`.
- **Main branch**: `main` — PRs target main, never worked on directly.
- **Project branches**: `short-descriptive-name` (no prefix required, just be descriptive).
- Never force-push to `main` or `staging`.
- Never commit directly to `main` or `staging`.
- **Never push** any branch — push is always a manual step done by the user.

---

## Split Workflow — Plan with Claude Code, Implement with Codex

The recommended approach for non-trivial features when token cost is a concern:

| Phase | Agent | Task |
|---|---|---|
| Plan | Claude Code | Reads codebase, asks questions, writes handoff plan |
| Review | You | Approve the plan file |
| Implement | Codex/Copilot | Reads plan + 3 reference files, implements step by step |

### Invoking Claude Code for Planning Only

Tell Claude Code:
> "Plan the [feature] feature. Generate a handoff plan. Do not write any code."

Claude Code will produce `ai/plans/YYYY-MM-DD-feature-name.md` following the format
in `ai/PLAN_TEMPLATE.md`. Review and approve the plan, then hand it off.

### What to Give Codex

Minimum context for Codex to implement a plan (4 files):
1. `CLAUDE.md`
2. `ai/CONVENTIONS.md`
3. `ai/ARCHITECTURE.md`
4. `ai/plans/YYYY-MM-DD-feature-name.md`

Suggested Codex prompt:
> "Read CLAUDE.md, ai/CONVENTIONS.md, ai/ARCHITECTURE.md, and the plan at
> ai/plans/[plan-file].md. Implement the plan exactly, one step at a time.
> Run `./vendor/bin/pest` after each step. Do not proceed to the next step
> if tests are failing."

### Plan Storage

All plans live in `ai/plans/`. File name: `YYYY-MM-DD-feature-name.md`.
Update the `Status` header as work progresses:
`PENDING APPROVAL` → `APPROVED` → `IN PROGRESS` → `COMPLETE`

---

## Before Writing Any Code

1. Read `CLAUDE.md`.
2. Read the files relevant to the task (use the key file table in `CLAUDE.md`).
3. If the task is non-trivial, enter PLAN MODE and follow `/ai/FEATURE_PLANNING_PROTOCOL.md`.
4. Confirm the plan with the user before proceeding.

---

## Commit Discipline

- Commit each logical unit (migration, action, component, tests) separately.
- Commit message format:
  ```
  <type>: <short summary>

  <optional body: why, not what>

  Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
  ```
- Types: `feat`, `fix`, `test`, `refactor`, `docs`, `chore`.
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

## Starting a New Project — Step by Step

1. Ensure `staging` is up to date:
   ```bash
   git checkout staging && git pull
   ```
2. Create a new branch with a descriptive name based on the feature:
   ```bash
   git checkout -b descriptive-project-name
   ```
   Use kebab-case. Be specific: `brig-appeal-system`, not `feature1`. Avoid generic names like `feature` or `fix`.
3. Enter PLAN MODE. Ask questions. Write the plan to `ai/plans/YYYY-MM-DD-feature-name.md`.
4. Commit the plan file once approved:
   ```bash
   git add ai/plans/YYYY-MM-DD-feature-name.md
   git commit -m "docs: add plan for [feature name]"
   ```
5. Work through the file-by-file task list in order.
6. After each file: run tests.
7. After all tasks: run full `./vendor/bin/pest`.
8. Commit each logical unit (migration, action, component, tests) separately.
9. **Do not push.** The user handles pushing and PRs.

---

## Session Startup Checklist

At the start of every session:

- [ ] Read `CLAUDE.md`.
- [ ] Check `MEMORY.md` for active plan or in-progress work.
- [ ] Run `git status` to understand the current branch and any uncommitted changes.
- [ ] If starting a new project: checkout `staging`, pull, then create a new descriptive branch.
- [ ] If continuing existing work: confirm you are on the correct branch.
- [ ] Ask: "Is there an in-progress plan to continue, or a new task?"
