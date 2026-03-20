# Agent Implementation Guide

How AI agents should operate in this repository when implementing features.

---

## How You Will Typically Be Invoked

You will usually receive a **GitHub issue number** from a PRD breakdown.
The issue contains acceptance criteria, blocked-by references, and links to a parent PRD.

**Your job is to implement the issue exactly as specified — not to redesign or extend it.**

### Startup Sequence (Issue-Based)

1. Read `CLAUDE.md`.
2. Read `ai/CONVENTIONS.md` — all code patterns you must follow.
3. Read `ai/ARCHITECTURE.md` — where things live.
4. Read the GitHub issue (`gh issue view <number>`).
5. Read the parent PRD issue for broader context.
6. Explore the codebase to understand existing patterns relevant to the work.
7. Work through the acceptance criteria in order.
8. After each logical unit: run `./vendor/bin/pest`. Fix failures before moving on.

### If Something in the Issue Is Unclear

- Do not guess. Stop and ask.
- Do not substitute a different pattern — the issue was written for this codebase.
- Do not add anything not in the issue's acceptance criteria.

---

## Before Generating Any Code (No Issue Provided)

If you have not been given a GitHub issue:

1. Read `CLAUDE.md` — the mandatory "read first" file.
2. Read `ai/ARCHITECTURE.md` — understand domain modules and data flow.
3. Read `ai/CONVENTIONS.md` — understand all patterns (Actions, Volt, Flux, tests).
4. Read the specific existing files related to your task. Do not guess patterns.
5. Produce a task list before writing any code. Confirm it before proceeding.

---

## Core Rules

- **Never invent patterns.** Copy the exact style of existing files.
- **Never call `$user->notify()` directly.** Always use `TicketNotificationService::send()`.
- **Never put business logic in Livewire components.** Extract to an Action class.
- **Never add authorization checks in Blade templates.** Use gates (`@can`) and policies only.
- **Never scatter `@if($user->in_brig)` in views.** The `view-community-content` gate handles this.
- **Never skip tests.** Every new Action and Notification needs Pest test coverage.

---

## Incremental Step Protocol

Work in small, verifiable steps:

1. **One file at a time.** Complete a file fully before starting the next.
2. **Order**: migration → model changes → Action class → Notification class → Gate/Policy → Livewire component → tests.
3. **After each file**: summarize what was added and what remains.
4. **After all files**: run `./vendor/bin/pest` and report pass/fail.

---

## Summarize Context After Each Batch

After completing each group of related files, output a short summary:

```
COMPLETED:
- app/Actions/DoSomething.php — handles X, records activity, sends Y notification
- app/Notifications/SomethingNotification.php — mail + Pushover

REMAINING:
- Gate in AuthServiceProvider
- Livewire component
- Tests
```

---

## Pattern Quick Reference

### Invoke an Action
```php
SomeAction::run($user, $param);
// NOT: (new SomeAction)->handle($user, $param);
```

### Log Activity
```php
RecordActivity::run($user, 'action_string', 'Description sentence.');
```

### Send a Notification
```php
app(TicketNotificationService::class)->send($user, new SomeNotification($user));
```

### Dispatch a Background Job
```php
// Create app/Jobs/DoSomethingInBackground.php (implements ShouldQueue, use Queueable)
DoSomethingInBackground::dispatch($user);
// NOT: dispatch(static fn() => ...);  ← legacy anonymous closures
```

### Livewire Authorization
```php
$this->authorize('gate-name');
// or
$this->authorize('policy-method', $model);
```

### Livewire Feedback
```php
Flux::toast('Message', 'Title', variant: 'success'); // or 'danger', 'warning'
Flux::modal('modal-name')->show();
Flux::modal('modal-name')->close();
```

### Test Structure
```php
it('does the thing', function () {
    $admin = loginAsAdmin();
    $target = User::factory()->create();

    $this->mock(MinecraftRconService::class)
        ->shouldReceive('executeCommand')
        ->andReturn(['success' => true, 'response' => null, 'error' => null]);

    SomeAction::run($target, $admin, 'reason');

    expect($target->fresh()->field)->toBe('value');
});
```

---

## Things Agents Must NOT Do

- Do not add Laravel Controllers for interactive UI pages.
  Use Volt components — they co-locate logic and template in one file for cleaner, easier-to-maintain code.
  Controllers are only acceptable for: simple view-only renders, redirects, and webhook endpoints.
- Do not add `declare(strict_types=1)` to Action or model files
  (only test files use it).
- Do not use anonymous `dispatch(static fn(){})` closures for background work — create a proper Job class in `app/Jobs/`.
- Do not use `$user->notify(...)` — always `TicketNotificationService::send(...)`.
- Do not add Eloquent scopes without confirming the pattern is used elsewhere.
- Do not generate comments or docblocks for unchanged code.
- Do not refactor code outside the scope of the current task.

---

## Validation Reference

In Livewire components, use `$this->validate([...])`:
```php
$this->validate([
    'reason' => 'required|string|min:5',
    'days' => 'nullable|integer|min:1|max:365',
]);
```

Standard Laravel validation rules apply. No custom rule classes unless they already exist.

---

## File Naming Reference

| What | Where | Name format |
|---|---|---|
| Action | `app/Actions/` | `PascalCaseVerb.php` |
| Job | `app/Jobs/` | `PascalCaseDescriptionJob.php` |
| Notification | `app/Notifications/` | `EventDescriptionNotification.php` |
| Volt component | `resources/views/livewire/` | `kebab-case.blade.php` |
| Test — Action | `tests/Feature/Actions/Actions/` | `ActionNameTest.php` |
| Test — Livewire | `tests/Feature/Livewire/` | `ComponentNameTest.php` |
| Test — Policy | `tests/Feature/Policies/` | `ModelPolicyTest.php` |
| Migration | `database/migrations/` | `YYYY_MM_DD_HHMMSS_description.php` |
