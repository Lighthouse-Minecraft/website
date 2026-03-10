# Plan: Site Configs Table + Registration Question

**Date**: 2026-03-10
**Planned by**: Claude Code
**Status**: PENDING APPROVAL

## Summary

Create a `site_configs` table for dynamic key-value configuration that staff can edit from
the ACP (replacing the need to edit `.env` for certain settings). Seed it with: the AI
meeting notes prompt, donation goal settings, and a new registration question. Modify the
registration form to show a configurable question to all new users (with parent email
still gated to under-17). Display the user's registration answer (plus the question asked)
on the Stowaway dashboard card and on the profile page for authorized staff.

## Files to Read (for implementing agent context)

- `CLAUDE.md`
- `ai/CONVENTIONS.md`
- `ai/ARCHITECTURE.md`
- `app/Providers/AuthServiceProvider.php`
- `app/Models/User.php`
- `resources/views/livewire/auth/register.blade.php`
- `resources/views/livewire/dashboard/stowaway-users-widget.blade.php`
- `resources/views/livewire/admin-control-panel-tabs.blade.php`
- `resources/views/users/show.blade.php`
- `resources/views/livewire/users/display-basic-details.blade.php`
- `config/lighthouse.php`
- `app/Actions/FormatMeetingNotesWithAi.php`

## Authorization Rules

- New gate: `manage-site-config` — Admin OR Officer rank (any department)
  ```php
  Gate::define('manage-site-config', function ($user) {
      return $user->hasRole('Admin') || $user->isAtLeastRank(StaffRank::Officer);
  });
  ```
- Existing gate reused: `manage-stowaway-users` — for viewing registration answers on
  stowaway card and profile card

## Database Changes

### Migration 1: `site_configs` table

| Migration file | Table | Change |
|---|---|---|
| `2026_03_10_100000_create_site_configs_table.php` | `site_configs` | Create table |

Column details:
- `id` — bigIncrements, primary key
- `key` — string, unique, indexed. The config key identifier (e.g. `registration_question`)
- `value` — longText, nullable. The config value (supports large text like AI prompts)
- `description` — string, nullable. Human-readable description for the ACP UI
- `timestamps`

### Migration 2: Registration answer columns on `users`

| Migration file | Table | Change |
|---|---|---|
| `2026_03_10_100001_add_registration_answer_to_users_table.php` | `users` | Add columns |

Column details:
- `registration_question_text` — text, nullable. Snapshot of the question at time of registration
- `registration_answer` — text, nullable. The user's answer to the registration question

## Implementation Steps (execute in this exact order)

---

### Step 1: Migration — `site_configs` table
**File**: `database/migrations/2026_03_10_100000_create_site_configs_table.php`
**Action**: Create

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_configs', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_configs');
    }
};
```

---

### Step 2: Migration — Registration answer columns on `users`
**File**: `database/migrations/2026_03_10_100001_add_registration_answer_to_users_table.php`
**Action**: Create

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('registration_question_text')->nullable()->after('parent_email');
            $table->text('registration_answer')->nullable()->after('registration_question_text');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['registration_question_text', 'registration_answer']);
        });
    }
};
```

---

### Step 3: Model — `SiteConfig`
**File**: `app/Models/SiteConfig.php`
**Action**: Create

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteConfig extends Model
{
    protected $fillable = ['key', 'value', 'description'];

    /**
     * Get a config value by key, with optional default.
     */
    public static function getValue(string $key, ?string $default = null): ?string
    {
        return Cache::remember("site_config.{$key}", 300, function () use ($key, $default) {
            $config = static::where('key', $key)->first();
            return $config?->value ?? $default;
        });
    }

    /**
     * Set a config value by key (creates if missing).
     */
    public static function setValue(string $key, ?string $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        Cache::forget("site_config.{$key}");
    }
}
```

---

### Step 4: Model — Update `User`
**File**: `app/Models/User.php`
**Action**: Modify — add to `$fillable` array:

```php
'registration_question_text',
'registration_answer',
```

No casts needed (both are nullable text, default string handling is fine).

---

### Step 5: Seeder — Initial site configs
**File**: `database/seeders/SiteConfigSeeder.php`
**Action**: Create

This seeder inserts the initial config rows. It should be safe to re-run (uses `firstOrCreate`).

```php
<?php

namespace Database\Seeders;

use App\Models\SiteConfig;
use Illuminate\Database\Seeder;

class SiteConfigSeeder extends Seeder
{
    public function run(): void
    {
        $configs = [
            [
                'key' => 'registration_question',
                'value' => '',
                'description' => 'Question shown to new users during registration. Leave empty to skip.',
            ],
            [
                'key' => 'ai_meeting_notes_prompt',
                'value' => config('lighthouse.ai.meeting_notes_system_prompt'),
                'description' => 'System prompt for AI meeting notes summarization.',
            ],
            [
                'key' => 'donation_goal',
                'value' => (string) config('lighthouse.donation_goal', 60),
                'description' => 'Monthly donation goal amount in dollars.',
            ],
            [
                'key' => 'donation_current_month_amount',
                'value' => (string) config('lighthouse.donation_current_month_amount', 0),
                'description' => 'Current month donation amount received.',
            ],
            [
                'key' => 'donation_current_month_name',
                'value' => config('lighthouse.donation_current_month_name', ''),
                'description' => 'Current month name for donation display.',
            ],
            [
                'key' => 'donation_last_month_amount',
                'value' => (string) config('lighthouse.donation_last_month_amount', 0),
                'description' => 'Last month donation amount received.',
            ],
            [
                'key' => 'donation_last_month_name',
                'value' => config('lighthouse.donation_last_month_name', ''),
                'description' => 'Last month name for donation display.',
            ],
        ];

        foreach ($configs as $config) {
            SiteConfig::firstOrCreate(
                ['key' => $config['key']],
                ['value' => $config['value'], 'description' => $config['description']]
            );
        }
    }
}
```

Also call this seeder from `DatabaseSeeder.php`:
```php
$this->call(SiteConfigSeeder::class);
```

---

### Step 6: Gate — `manage-site-config`
**File**: `app/Providers/AuthServiceProvider.php`
**Action**: Modify — add inside `boot()`:

```php
Gate::define('manage-site-config', function ($user) {
    return $user->hasRole('Admin') || $user->isAtLeastRank(StaffRank::Officer);
});
```

---

### Step 7: ACP — Site Settings Livewire Component
**File**: `resources/views/livewire/admin-manage-site-configs-page.blade.php`
**Action**: Create

This component lists all `site_configs` rows and allows editing each value inline.

```php
<?php

use App\Models\SiteConfig;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component {
    public ?int $editingId = null;
    public string $editValue = '';

    public function getSiteConfigsProperty()
    {
        return SiteConfig::orderBy('key')->get();
    }

    public function startEdit(int $id): void
    {
        $this->authorize('manage-site-config');

        $config = SiteConfig::findOrFail($id);
        $this->editingId = $id;
        $this->editValue = $config->value ?? '';
        Flux::modal('edit-config-modal')->show();
    }

    public function saveEdit(): void
    {
        $this->authorize('manage-site-config');

        $config = SiteConfig::findOrFail($this->editingId);
        $config->value = $this->editValue;
        $config->save();

        // Clear cache for this key
        \Illuminate\Support\Facades\Cache::forget("site_config.{$config->key}");

        $this->editingId = null;
        $this->editValue = '';
        Flux::modal('edit-config-modal')->close();
        Flux::toast("Setting '{$config->key}' updated.", 'Saved', variant: 'success');
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->editValue = '';
        Flux::modal('edit-config-modal')->close();
    }
}; ?>

<flux:card>
    <flux:heading size="md" class="mb-4">Site Settings</flux:heading>
    <flux:text variant="subtle" class="mb-4">Dynamic configuration values that can be changed without redeploying. Edit a setting by clicking the edit button.</flux:text>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Key</flux:table.column>
            <flux:table.column>Description</flux:table.column>
            <flux:table.column>Value</flux:table.column>
            <flux:table.column>Actions</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach($this->siteConfigs as $config)
                <flux:table.row wire:key="config-{{ $config->id }}">
                    <flux:table.cell class="font-mono text-sm">{{ $config->key }}</flux:table.cell>
                    <flux:table.cell class="text-sm text-zinc-500">{{ $config->description ?? '—' }}</flux:table.cell>
                    <flux:table.cell class="text-sm max-w-xs truncate">{{ Str::limit($config->value ?? '(empty)', 80) }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:button wire:click="startEdit({{ $config->id }})" size="sm" icon="pencil-square" variant="ghost" />
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <!-- Edit Modal -->
    <flux:modal name="edit-config-modal" class="w-full lg:w-2/3">
        <div class="space-y-6">
            <flux:heading size="lg">Edit Setting</flux:heading>

            @if($editingId)
                @php $editingConfig = \App\Models\SiteConfig::find($editingId); @endphp
                @if($editingConfig)
                    <div>
                        <flux:text class="font-mono font-semibold">{{ $editingConfig->key }}</flux:text>
                        <flux:text variant="subtle" size="sm">{{ $editingConfig->description }}</flux:text>
                    </div>
                @endif
            @endif

            <flux:field>
                <flux:label>Value</flux:label>
                <flux:textarea wire:model="editValue" rows="8" />
            </flux:field>

            <div class="flex gap-2 justify-end">
                <flux:button wire:click="cancelEdit" variant="ghost">Cancel</flux:button>
                <flux:button wire:click="saveEdit" variant="primary">Save</flux:button>
            </div>
        </div>
    </flux:modal>
</flux:card>
```

---

### Step 8: ACP Tabs — Add Site Settings tab
**File**: `resources/views/livewire/admin-control-panel-tabs.blade.php`
**Action**: Modify

**8a.** In `hasConfigTabs()`, add the site config check:
```php
public function hasConfigTabs(): bool
{
    $user = auth()->user();

    return $user && (
        $user->can('manage-site-config')
        || $user->can('viewAny', \App\Models\Role::class)
        || $user->can('viewAny', \App\Models\ReportCategory::class)
        || $user->can('viewAny', \App\Models\PrayerCountry::class)
    );
}
```

**8b.** In `defaultTabFor('config')`, add site-settings as the first option:
```php
'config' => match (true) {
    $user?->can('manage-site-config') => 'site-settings',
    $user?->can('viewAny', \App\Models\Role::class) => 'role-manager',
    $user?->can('viewAny', \App\Models\ReportCategory::class) => 'report-category-manager',
    $user?->can('viewAny', \App\Models\PrayerCountry::class) => 'prayer-manager',
    default => 'role-manager',
},
```

**8c.** In the Config category Blade section, add the Site Settings tab and panel
(before the existing Roles tab):
```blade
@can('manage-site-config')
    <flux:tab name="site-settings">Site Settings</flux:tab>
@endcan
```

And the tab panel (before role-manager panel):
```blade
<flux:tab.panel name="site-settings">
    @can('manage-site-config')
        <livewire:admin-manage-site-configs-page />
    @endcan
</flux:tab.panel>
```

---

### Step 9: Update `FormatMeetingNotesWithAi` to use `SiteConfig`
**File**: `app/Actions/FormatMeetingNotesWithAi.php`
**Action**: Modify

Where the system prompt is read, change from:
```php
config('lighthouse.ai.meeting_notes_system_prompt')
```
to:
```php
\App\Models\SiteConfig::getValue('ai_meeting_notes_prompt', config('lighthouse.ai.meeting_notes_system_prompt'))
```

This falls back to the existing config/env value if no DB row exists yet.

---

### Step 10: Update donation references to use `SiteConfig`
**File**: Any file that reads `config('lighthouse.donation_goal')`, `config('lighthouse.donation_current_month_amount')`, etc.
**Action**: Modify — search for these config calls and replace with `SiteConfig::getValue(...)` with the existing config value as fallback.

Likely locations (search for `config('lighthouse.donation`):
- Donation page/component
- Any donation widget

Pattern:
```php
// Before:
config('lighthouse.donation_goal', 60)

// After:
(int) \App\Models\SiteConfig::getValue('donation_goal', (string) config('lighthouse.donation_goal', 60))
```

---

### Step 11: Registration Form — Show configurable question
**File**: `resources/views/livewire/auth/register.blade.php`
**Action**: Modify

**11a.** Add a new property and update `register()`:
```php
public string $registration_answer = '';
```

**11b.** Modify `register()` — instead of directly creating account for 17+, check if a registration question exists:
```php
public function register(): void
{
    $validated = $this->validate([
        'name' => ['required', 'string', 'max:32'],
        'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
        'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        'date_of_birth' => ['required', 'date', 'before:today'],
    ]);

    $age = \Carbon\Carbon::parse($this->date_of_birth)->age;
    $hasQuestion = ! empty(\App\Models\SiteConfig::getValue('registration_question'));

    if ($age >= 17 && ! $hasQuestion) {
        // Adult with no registration question — create immediately
        $this->createAccount();
        return;
    }

    // Under 17 (needs parent email) or has registration question — go to step 2
    $this->step = 2;
}
```

**11c.** Modify `submitParentEmail()` to also handle the registration answer:
```php
public function submitParentEmail(): void
{
    $age = \Carbon\Carbon::parse($this->date_of_birth)->age;
    $hasQuestion = ! empty(\App\Models\SiteConfig::getValue('registration_question'));

    $rules = [
        'name' => ['required', 'string', 'max:32'],
        'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
        'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        'date_of_birth' => ['required', 'date', 'before:today'],
    ];

    if ($age < 17) {
        $rules['parent_email'] = ['required', 'email', Rule::notIn([$this->email])];
    }

    if ($hasQuestion) {
        $rules['registration_answer'] = ['required', 'string', 'min:3'];
    }

    $this->validate($rules);

    $this->createAccount();
}
```

**11d.** Modify `createAccount()` to save the question text + answer:
```php
private function createAccount(): void
{
    $age = \Carbon\Carbon::parse($this->date_of_birth)->age;
    $questionText = \App\Models\SiteConfig::getValue('registration_question');

    $userData = [
        'name' => $this->name,
        'email' => $this->email,
        'password' => Hash::make($this->password),
        'date_of_birth' => $this->date_of_birth,
    ];

    if ($age < 17) {
        $userData['parent_email'] = $this->parent_email;
    }

    if (! empty($questionText) && ! empty($this->registration_answer)) {
        $userData['registration_question_text'] = $questionText;
        $userData['registration_answer'] = $this->registration_answer;
    }

    if ($age < 13) {
        $userData['parent_allows_site'] = false;
        $userData['parent_allows_minecraft'] = false;
        $userData['parent_allows_discord'] = false;
    }

    // ... rest of createAccount() stays the same
```

**11e.** Update the Step 2 Blade template to show both the parent email (when under 17)
and the registration question (when configured):
```blade
@else
    <x-auth-header title="Almost Done!" description="Just a couple more things before we create your account." />

    <form wire:submit="submitParentEmail" class="flex flex-col gap-6">
        @php
            $age = \Carbon\Carbon::parse($date_of_birth)->age;
            $questionText = \App\Models\SiteConfig::getValue('registration_question');
        @endphp

        @if($age < 17)
            <flux:field>
                <flux:label>Parent/Guardian Email</flux:label>
                <flux:input wire:model="parent_email" type="email" required placeholder="parent@example.com" />
                <flux:error name="parent_email" />
                <flux:description>We'll send your parent information about your account and how to manage it.</flux:description>
            </flux:field>
        @endif

        @if(! empty($questionText))
            <flux:field>
                <flux:label>{{ $questionText }}</flux:label>
                <flux:textarea wire:model="registration_answer" rows="3" required />
                <flux:error name="registration_answer" />
            </flux:field>
        @endif

        <flux:button type="submit" variant="primary" class="w-full">
            Create account
        </flux:button>
    </form>

    <div class="space-x-1 text-center text-sm text-zinc-600 dark:text-zinc-400">
        Already have an account?
        <x-text-link href="{{ route('login') }}">Log in</x-text-link>
    </div>
@endif
```

---

### Step 12: Stowaway Card — Show registration answer
**File**: `resources/views/livewire/dashboard/stowaway-users-widget.blade.php`
**Action**: Modify

In the user details modal (`<!-- User Details Modal -->`), add a new section after the
existing `<dl>` block (after the "Rules Accepted" row, before the `<flux:separator />`):

```blade
@if($selectedUser->registration_answer)
    <flux:separator />

    <div class="space-y-2">
        <flux:text variant="subtle" size="sm" class="font-medium">Registration Question</flux:text>
        <flux:text size="sm" class="italic">{{ $selectedUser->registration_question_text ?? 'N/A' }}</flux:text>
        <flux:text variant="subtle" size="sm" class="font-medium mt-2">Answer</flux:text>
        <flux:text size="sm">{{ $selectedUser->registration_answer }}</flux:text>
    </div>
@endif
```

---

### Step 13: Profile Page — Registration Answer Card
**File**: `resources/views/livewire/users/registration-answer-card.blade.php`
**Action**: Create

A small Livewire component that displays the registration Q&A for a user. Only shown
to users with `manage-stowaway-users` permission, and only when the user is at
Stowaway level.

```php
<?php

use App\Enums\MembershipLevel;
use App\Models\User;
use Livewire\Volt\Component;

new class extends Component {
    public User $user;

    public function mount(User $user): void
    {
        $this->user = $user;
    }
}; ?>

@if(
    auth()->user()->can('manage-stowaway-users')
    && $user->membership_level === MembershipLevel::Stowaway
    && $user->registration_answer
)
    <flux:card class="w-full">
        <flux:heading size="md" class="mb-2">Registration Response</flux:heading>

        <div class="space-y-3">
            <div>
                <flux:text variant="subtle" size="sm" class="font-medium">Question Asked</flux:text>
                <flux:text size="sm" class="italic">{{ $user->registration_question_text ?? 'N/A' }}</flux:text>
            </div>
            <div>
                <flux:text variant="subtle" size="sm" class="font-medium">Answer</flux:text>
                <flux:text>{{ $user->registration_answer }}</flux:text>
            </div>
        </div>
    </flux:card>
@endif
```

---

### Step 14: Profile Page — Include card
**File**: `resources/views/users/show.blade.php`
**Action**: Modify

Add the registration answer card between the basic details and the activity log button:

```blade
<x-layouts.app>
    <div class="my-6">
        <livewire:users.display-basic-details :user="$user" />
    </div>

    <div class="my-6">
        <livewire:users.registration-answer-card :user="$user" />
    </div>

    @can('viewActivityLog', $user)
        <div class="w-full my-6 flex justify-end">
            ...
```

The component itself handles the visibility logic (only shows for Stowaway users
with a registration answer, to users with `manage-stowaway-users` permission).

---

### Step 15: Tests — SiteConfig Model
**File**: `tests/Feature/Models/SiteConfigTest.php`
**Action**: Create

Test cases:
- `it('can get a config value by key')`
- `it('returns default when key does not exist')`
- `it('can set a config value')`
- `it('updates existing config when setting value')`
- `it('clears cache when value is set')`

---

### Step 16: Tests — Registration with question
**File**: `tests/Feature/Auth/RegistrationQuestionTest.php`
**Action**: Create

Test cases:
- `it('shows step 2 for adult users when registration question is configured')`
- `it('skips step 2 for adult users when no registration question is configured')`
- `it('saves registration answer and question text on account creation')`
- `it('requires registration answer when question is configured')`
- `it('still requires parent email for under 17 users')`
- `it('does not save registration answer fields when no question configured')`

---

### Step 17: Tests — ACP Site Settings
**File**: `tests/Feature/Admin/SiteConfigsPageTest.php`
**Action**: Create

Test cases:
- `it('allows admins to view site settings page')`
- `it('allows officers to view site settings page')`
- `it('denies crew members from viewing site settings page')`
- `it('allows editing a config value')`

---

### Step 18: Tests — Registration Answer Display
**File**: `tests/Feature/Dashboard/StowawayRegistrationAnswerTest.php`
**Action**: Create

Test cases:
- `it('shows registration answer in stowaway modal for authorized users')`
- `it('shows registration answer card on profile for stowaway users')`
- `it('hides registration answer card for non-stowaway users')`
- `it('hides registration answer card from unauthorized users')`

---

## Edge Cases

1. **Empty registration question** — When `registration_question` config value is empty
   or null, Step 2 is skipped entirely for 17+ users (same as current behavior).
   Under-17 users still see Step 2 for parent email only.

2. **Question changed after registration** — The question text is snapshotted into
   `registration_question_text` at registration time, so staff always see what was
   actually asked, even if the question changes later.

3. **No answer for existing users** — Existing users will have null for both
   `registration_question_text` and `registration_answer`. The card/display simply
   doesn't show when these are null.

4. **SiteConfig cache** — Values are cached for 5 minutes (300 seconds). After editing
   in ACP, cache is cleared immediately for that key. Worst case for other servers in a
   multi-server setup: 5 minute delay.

5. **Donation config fallback** — `SiteConfig::getValue()` falls back to the existing
   `config()` values, so the transition is seamless. Old `.env` values still work as
   defaults until overridden in the DB.

6. **Under-17 user with question + parent email** — Step 2 shows both fields. Parent
   email is required, registration answer is required. Both are validated together in
   `submitParentEmail()`.

## Known Risks

1. **Migration ordering** — The seeder references `config()` values, so it must run
   after the migration creates the table. Standard `migrate --seed` handles this.

2. **Large AI prompt** — The `ai_meeting_notes_prompt` is very long (~3KB). Using
   `longText` column and a textarea with 8 rows handles this. No truncation on save.

## Definition of Done

- [ ] `php artisan migrate:fresh --seed` passes
- [ ] `./vendor/bin/pest` passes with zero failures
- [ ] All test cases from this plan are implemented
- [ ] No ad-hoc auth checks in Blade templates (gates/policies only)
- [ ] Registration form shows question when configured, skips when not
- [ ] Stowaway card shows registration Q&A in modal
- [ ] Profile page shows registration answer card for authorized staff viewing Stowaway users
- [ ] ACP Site Settings tab allows Officers/Admins to edit all config values
- [ ] Donation and AI prompt values read from `SiteConfig` with fallback to `config()`
