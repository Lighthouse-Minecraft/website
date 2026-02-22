# Conventions

---

## Action Classes

**Location**: `app/Actions/ClassName.php`

**Pattern**:
```php
namespace App\Actions;

use Lorisleiva\Actions\Concerns\AsAction;

class DoSomething
{
    use AsAction;

    public function handle(User $user, string $reason): void
    {
        // 1. Mutate + save model(s)
        $user->some_field = $value;
        $user->save();

        // 2. Log activity
        RecordActivity::run($user, 'something_done', "Description: {$reason}.");

        // 3. Sync Minecraft if membership/brig changed
        SyncMinecraftRanks::run($user);

        // 4. Notify via service
        app(TicketNotificationService::class)->send($user, new SomeNotification($user));
    }
}
```

**Rules**:
- `use AsAction` — always.
- Invoke with `ClassName::run(...)` — never `new ClassName()->handle(...)`.
- `RecordActivity::run(...)` — same `::run()` convention, no exceptions.
- No HTTP/Livewire logic inside Actions. Actions are pure business logic.
- Actions may call other Actions.

---

## Livewire Volt Components

**Location**: `resources/views/livewire/**/*.blade.php`

**Pattern** (inline class):
```php
<?php

use App\Models\User;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component {
    public string $someProperty = '';

    public function doSomething(): void
    {
        $this->authorize('gate-name');

        $this->validate([
            'someProperty' => 'required|string|min:5',
        ]);

        try {
            SomeAction::run($this->someProperty);
            Flux::toast('Done!', 'Success', variant: 'success');
            $this->dispatch('$refresh');
        } catch (\Exception $e) {
            Flux::toast('Failed. Try again.', 'Error', variant: 'danger');
        }
    }
}; ?>

<flux:card>
    ...
</flux:card>
```

**Rules**:
- Authorization: `$this->authorize(...)` — always before doing anything sensitive.
- Validation: `$this->validate([...])` — Laravel validation rules.
- Feedback: `Flux::toast(message, title, variant: 'success'|'danger'|'warning')`.
- Refresh: `$this->dispatch('$refresh')` after state-changing actions.
- Modals: `Flux::modal('modal-name')->show()` / `->close()`.
- Computed properties: `public function getXxxProperty()` returns data, accessed as `$this->xxx`.
- `wire:key` on every repeated element: `wire:key="prefix-{{ $item->id }}"`.

---

## Flux UI Component Cheatsheet

```blade
<flux:card>...</flux:card>
<flux:heading size="md|lg">Text</flux:heading>
<flux:text variant="subtle">Text</flux:text>

<flux:table>
  <flux:table.columns>
    <flux:table.column>Label</flux:table.column>
  </flux:table.columns>
  <flux:table.rows>
    <flux:table.row wire:key="key">
      <flux:table.cell>Value</flux:table.cell>
    </flux:table.row>
  </flux:table.rows>
</flux:table>

<flux:button variant="primary|ghost|danger" size="sm" icon="heroicon-name" wire:click="method">Label</flux:button>
<flux:button href="/path" variant="ghost" icon="inbox">Link Button</flux:button>

<flux:modal name="modal-name" wire:model="boolProperty" class="w-full lg:w-1/2">
  ...
  <flux:button variant="ghost" x-on:click="$flux.modal('modal-name').close()">Cancel</flux:button>
</flux:modal>

<flux:field>
  <flux:label>Label <span class="text-red-500">*</span></flux:label>
  <flux:description>Helper text</flux:description>
  <flux:input wire:model.live="property" type="text" />
  <flux:textarea wire:model.live="property" rows="4" />
  <flux:error name="property" />
</flux:field>

<flux:badge variant="primary|success|danger">Text</flux:badge>
<flux:link href="/path">Link text</flux:link>
<flux:spacer />
```

**Authorization in Blade**:
```blade
@can('gate-name') ... @endcan
@can('policy-ability', $model) ... @endcan
@cannot('gate-name') ... @endcannot
```

---

## Activity Logging

```php
RecordActivity::run($subjectModel, 'snake_case_action', 'Human readable description.');
```

- Subject is any Eloquent model (usually `User`).
- Action is a `snake_case` string — be consistent within a domain.
- Description should be a complete sentence with key facts.

---

## Notifications

**Class structure** (`app/Notifications/`):
```php
class SomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $allowedChannels = ['mail'];
    protected ?string $pushoverKey = null;

    public function __construct(public User $user, ...) {}

    public function setChannels(array $channels, ?string $pushoverKey = null): self { ... }
    public function via(object $notifiable): array { ... }
    public function toMail(object $notifiable): MailMessage { ... }
    public function toPushover(object $notifiable): array { return ['title' => '...', 'message' => '...']; }
}
```

**Sending** (always via service, never `$user->notify()` directly):
```php
$notificationService = app(TicketNotificationService::class);
$notificationService->send($user, new SomeNotification($user));
```

---

## Background Jobs

**Location**: `app/Jobs/ClassName.php`

Use Laravel Job classes for all deferred or queued work. Do **not** use anonymous
`dispatch(static fn(){})` closures — those are legacy; new code uses proper Job classes.

**Pattern**:
```php
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
        // background logic here
        // may call Action classes: SomeAction::run($this->user);
    }
}
```

**Dispatching**:
```php
DoSomethingInBackground::dispatch($user);
DoSomethingInBackground::dispatch($user)->delay(now()->addMinutes(5));
```

**Rules**:
- Job classes are for deferred/queued work only — not for synchronous logic.
- Synchronous business logic belongs in Action classes.
- Jobs may call Actions internally.

---

## UI Routing — Volt vs. Controllers

- **Prefer Volt components** for all interactive UI pages and admin panels.
  Volt co-locates logic and template in one file, making it easier to read, test, and maintain.
- **Use controllers** only for:
  - Non-interactive pages that just render a view with no Livewire interactivity.
  - Redirect-only routes.
  - External webhook endpoints (e.g., `POST /api/minecraft/verify`).
- When in doubt: if the page has any user interaction, use Volt.

---

## Authorization — Gates vs. Policies

| Use | When |
|---|---|
| `Gate::define('gate-name', fn($user) => ...)` | Feature-level access (view a section, perform a workflow action) |
| Policy class method | Model CRUD operations (view, create, update, delete a specific model instance) |

- Gates are defined in `app/Providers/AuthServiceProvider.php`.
- Policies are registered in `$policies` array or via `registerPolicies()`.
- `UserPolicy::before()` grants admin/command bypass — do not duplicate this logic elsewhere.
- **Never** enforce brig status directly in Blade templates. Use the `view-community-content` gate.

---

## Naming Conventions

| Thing | Convention | Example |
|---|---|---|
| Action class | `PascalCase` verb phrase | `PutUserInBrig`, `PromoteUser` |
| Gate name | `kebab-case` | `manage-stowaway-users`, `view-ready-room` |
| Activity action string | `snake_case` | `user_put_in_brig`, `user_promoted` |
| Volt component path | `dot.separated` matching folder path | `ready-room.tickets.view-ticket` |
| Route name | `dot.separated.resource` | `tickets.show`, `meeting.edit` |
| Notification class | `PascalCase` description + `Notification` | `UserPutInBrigNotification` |

---

## Testing Conventions

**Framework**: Pest (not raw PHPUnit).

**Structure**:
```php
<?php
declare(strict_types=1);

use App\Actions\SomeAction;
use App\Models\User;

uses()->group('domain', 'actions'); // group tags

it('does the expected thing', function () {
    $admin = loginAsAdmin();         // or User::factory()->admin()->create()
    $target = User::factory()->create();

    // Mock RCON if Minecraft is involved
    $this->mock(MinecraftRconService::class)
        ->shouldReceive('executeCommand')
        ->andReturn(['success' => true, 'response' => null, 'error' => null]);

    SomeAction::run($target, $admin, 'reason');

    expect($target->fresh()->some_field)->toBe('expected')
        ->and($target->fresh()->other_field)->toBeNull();
});

it('records activity', function () {
    // ...run action...
    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => User::class,
        'subject_id' => $target->id,
        'action' => 'some_action',
    ]);
});

it('sends notification', function () {
    Notification::fake(); // Note: globally faked in Pest.php already
    // ...run action...
    Notification::assertSentTo($target, SomeNotification::class);
});
```

**Helper functions** (from `tests/Pest.php` and `tests/Support/Users.php`):
- `loginAsAdmin()` — creates admin user and acts as them.
- `loginAs(User $user)` — acts as the given user.
- `membershipTraveler()`, `membershipDrifter()` — factory shortcuts for membership levels.
- `officerCommand()`, `officerQuartermaster()`, `crewCommand()`, `crewQuartermaster()` — staff factory shortcuts.

**Rules**:
- One assertion per `it()` block where possible, or group related assertions with `->and()`.
- Always use `$model->fresh()` when asserting DB state after an action.
- Notifications are globally faked (`Notification::fake()` in `beforeEach`) — no need to re-fake.
- Mock `MinecraftRconService` whenever an action may issue RCON commands.
- Use `$this->assertDatabaseHas()` for database state, `expect()->toBe()` for value assertions.
