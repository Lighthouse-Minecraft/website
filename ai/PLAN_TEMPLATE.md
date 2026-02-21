# Plan Template — How Claude Code Produces Handoff Plans

This file explains the plan format and serves as the template Claude Code fills out
when a feature is being planned for handoff to another agent (Codex, Copilot, etc.).

Plans are saved to: `ai/plans/YYYY-MM-DD-feature-name.md`

---

## How to Invoke Planning Mode

Tell Claude Code:
> "Plan the [feature] feature. Generate a handoff plan file."

Claude Code will:
1. Read all relevant existing files.
2. Ask all questions needed to fully understand requirements before writing the plan.
3. Write the completed plan to `ai/plans/YYYY-MM-DD-feature-name.md`.
4. Wait for your approval before any code is written.

---

## What Makes a Plan "Handoff-Ready"

The implementing agent (Codex) should be able to execute the plan **without reading
any additional codebase files**. This means:

- Code patterns are **embedded in the plan**, not just referenced by file path.
- Every file is listed with its exact path and action (Create / Modify / Delete).
- Steps are **strictly ordered** (migrations before models, models before actions, etc.).
- Authorization rules are spelled out explicitly — no "same as existing".
- Test cases are listed as concrete `it('...', fn)` descriptions.

---

## Plan File Format

Copy this template for every new plan:

```markdown
# Plan: [Feature Name]

**Date**: YYYY-MM-DD
**Planned by**: Claude Code
**Status**: PENDING APPROVAL → APPROVED → IN PROGRESS → COMPLETE

## Summary
[One paragraph: what this feature does, who uses it, why it exists.]

## Files to Read (for implementing agent context)
- `CLAUDE.md`
- `ai/CONVENTIONS.md`
- `ai/ARCHITECTURE.md`
- [any other specific files the implementer should skim]

## Authorization Rules
- Gate(s) to add/reuse:
  - `Gate::define('gate-name', fn($user) => ...)` — who is allowed
- Policy method(s) to add:
  - `ModelPolicy::methodName(User $user, Model $model): bool`
- Where to add gates: `app/Providers/AuthServiceProvider.php`

## Database Changes
| Migration file | Table | Change |
|---|---|---|
| `YYYY_MM_DD_HHmmss_description.php` | `table_name` | Add columns: ... |

Column details:
- `column_name` — type, nullable/required, default, purpose

## Implementation Steps (execute in this exact order)

---

### Step 1: Migration
**File**: `database/migrations/YYYY_MM_DD_HHmmss_description.php`
**Action**: Create

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('new_column')->nullable();
    $table->timestamp('new_date')->nullable();
});
```

Rollback:
```php
Schema::table('users', function (Blueprint $table) {
    $table->dropColumn(['new_column', 'new_date']);
});
```

---

### Step 2: Model Changes (if any)
**File**: `app/Models/SomeModel.php`
**Action**: Modify

Add to `$fillable`:
```php
'new_column',
'new_date',
```

Add to `casts()`:
```php
'new_date' => 'datetime',
```

Add helper method:
```php
public function hasNewThing(): bool
{
    return $this->new_column !== null;
}
```

---

### Step 3: Gate / Policy
**File**: `app/Providers/AuthServiceProvider.php`
**Action**: Modify — add inside `boot()`:

```php
Gate::define('do-new-thing', function ($user) {
    return $user->hasRole('Admin') || $user->isAtLeastRank(StaffRank::Officer);
});
```

---

### Step 4: Action Class
**File**: `app/Actions/DoNewThing.php`
**Action**: Create

```php
<?php

namespace App\Actions;

use App\Models\User;
use App\Notifications\NewThingHappenedNotification;
use App\Services\TicketNotificationService;
use Lorisleiva\Actions\Concerns\AsAction;

class DoNewThing
{
    use AsAction;

    public function handle(User $target, User $admin, string $reason): void
    {
        // 1. Mutate + save
        $target->new_column = $reason;
        $target->new_date = now();
        $target->save();

        // 2. Activity log
        RecordActivity::run($target, 'new_thing_done', "New thing done by {$admin->name}. Reason: {$reason}.");

        // 3. Notify
        app(TicketNotificationService::class)->send(
            $target,
            new NewThingHappenedNotification($target, $reason)
        );
    }
}
```

---

### Step 5: Notification Class
**File**: `app/Notifications/NewThingHappenedNotification.php`
**Action**: Create

```php
<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewThingHappenedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $allowedChannels = ['mail'];
    protected ?string $pushoverKey = null;

    public function __construct(
        public User $user,
        public string $reason
    ) {}

    public function setChannels(array $channels, ?string $pushoverKey = null): self
    {
        $this->allowedChannels = $channels;
        $this->pushoverKey = $pushoverKey;
        return $this;
    }

    public function via(object $notifiable): array
    {
        $channels = [];
        if (in_array('mail', $this->allowedChannels)) {
            $channels[] = 'mail';
        }
        if (in_array('pushover', $this->allowedChannels) && $this->pushoverKey) {
            $channels[] = PushoverChannel::class;
        }
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Subject Line Here')
            ->line('First line of the email.')
            ->line('**Reason:** ' . $this->reason)
            ->action('Go to Dashboard', route('dashboard'));
    }

    public function toPushover(object $notifiable): array
    {
        return [
            'title' => 'Short Title',
            'message' => 'Short message. Reason: ' . $this->reason,
        ];
    }
}
```

---

### Step 6: Livewire Volt Component
**File**: `resources/views/livewire/path/component-name.blade.php`
**Action**: Create (or Modify — specify which methods change)

```php
<?php

use App\Actions\DoNewThing;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public string $reason = '';

    public function doTheThing(int $userId): void
    {
        $this->authorize('do-new-thing');

        $this->validate([
            'reason' => 'required|string|min:5',
        ]);

        $target = User::findOrFail($userId);

        try {
            DoNewThing::run($target, Auth::user(), $this->reason);
            Flux::toast("{$target->name} updated.", 'Done', variant: 'success');
            $this->dispatch('$refresh');
        } catch (\Exception $e) {
            Flux::toast('Failed. Please try again.', 'Error', variant: 'danger');
        }
    }
}; ?>

<flux:card>
    {{-- Blade template here --}}
</flux:card>
```

---

### Step 7: Route (if new)
**File**: `routes/web.php`
**Action**: Modify — add inside appropriate middleware group:

```php
Volt::route('/path/to/page', 'path.component-name')->name('route.name');
```

---

### Step 8: Job Class (if deferred work needed)
**File**: `app/Jobs/DoSomethingInBackground.php`
**Action**: Create

```php
<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DoSomethingInBackground implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $user) {}

    public function handle(): void
    {
        SomeAction::run($this->user);
    }
}
```

---

### Step 9: Tests — Action
**File**: `tests/Feature/Actions/Actions/DoNewThingTest.php`
**Action**: Create

Test cases to write (one `it()` block each):
- `it('does the expected thing')`
- `it('records activity with correct action string')`
- `it('sends notification to target user')`
- `it('does not do X when Y condition is false')` [guard cases]
- `it('works without optional parameter')`

Pattern:
```php
<?php
declare(strict_types=1);

use App\Actions\DoNewThing;
use App\Models\User;
use App\Notifications\NewThingHappenedNotification;
use Illuminate\Support\Facades\Notification;

uses()->group('domain', 'actions');

it('does the expected thing', function () {
    $admin = loginAsAdmin();
    $target = User::factory()->create();

    DoNewThing::run($target, $admin, 'reason here');

    expect($target->fresh()->new_column)->toBe('reason here');
});
```

---

### Step 10: Tests — Authorization
**File**: `tests/Feature/Policies/...` or inline in component test
**Action**: Create or Modify

Test cases:
- `it('authorized user can do the thing')`
- `it('unauthorized user cannot do the thing')`

---

## Edge Cases
[List every edge case the implementer must handle]

## Known Risks
[Anything that could go wrong and how to handle it]

## Definition of Done
- [ ] `php artisan migrate:fresh` passes
- [ ] `./vendor/bin/pest` passes with zero failures
- [ ] All test cases from this plan are implemented
- [ ] No ad-hoc auth checks in Blade templates
```

---

## Tips for Claude Code When Writing Plans

- **Embed code, don't reference it.** If the implementer needs to follow a pattern,
  paste the relevant pattern directly into the plan. Do not say "follow the pattern in
  `app/Actions/PromoteUser.php`" — paste the stripped-down version.
- **Be explicit about method signatures.** Never say "similar to existing".
- **List every file.** If a file is touched at all, it gets a step.
- **Specify modify vs. create.** For modify steps, show exactly what to add/change,
  not the whole file.
- **Include the test case descriptions.** Codex will write better tests when the
  expected behavior is spelled out as `it('...')` strings.
