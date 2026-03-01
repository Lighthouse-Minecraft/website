# Plan: Parent Portal (Phase 1)

**Date**: 2026-02-28
**Planned by**: Claude Code
**Status**: PENDING APPROVAL

## Context

Lighthouse Minecraft is a ministry serving minors. For COPPA compliance and parental trust, we need a Parent Portal. This adds age-awareness to registration, gates minors behind parental oversight, and gives parents a clean dashboard to manage their children's permissions, view linked accounts, see tickets, and check brig status.

**Key design decision**: Site-access restrictions use the existing Brig system rather than a separate `account_disabled` field. A new `brig_type` column differentiates disciplinary from parental holds, and the dashboard brig card displays differently based on the type.

## Summary

Under-17 users must provide a parent's email. Under-13 accounts are placed in the brig (type: `parental_pending`) until a parent approves. Parents get a portal to manage permission toggles (site, Minecraft, Discord), view child accounts, and see tickets/brig status. Existing users with null DOB are prompted via middleware. Age milestones (13, 17, 19) trigger automatic transitions. Staff can lock accounts for age verification.

## Files to Read (for implementing agent context)
- `CLAUDE.md`, `ai/CONVENTIONS.md`, `ai/ARCHITECTURE.md`
- `app/Models/User.php` — User model, relationships, brig methods
- `app/Models/MinecraftAccount.php`, `app/Models/DiscordAccount.php`
- `app/Enums/MinecraftAccountStatus.php`, `app/Enums/DiscordAccountStatus.php`
- `app/Providers/AuthServiceProvider.php` — All gates
- `app/Actions/PutUserInBrig.php` — Brig enforcement (bans MC, strips Discord roles)
- `app/Actions/ReleaseUserFromBrig.php` — Brig release (restores MC, syncs Discord)
- `app/Services/DiscordApiService.php` — `removeAllManagedRoles()` method
- `resources/views/livewire/auth/register.blade.php` — Current registration flow
- `resources/views/livewire/dashboard/in-brig-card.blade.php` — Brig card display + appeal
- `resources/views/dashboard.blade.php` — Dashboard layout, `@can('view-community-content')`
- `resources/views/components/layouts/app/sidebar.blade.php` — Navigation
- `resources/views/livewire/settings/minecraft-accounts.blade.php` — MC linking (inline brig check at line 557)
- `bootstrap/app.php` — Middleware registration
- `app/Console/Commands/CheckBrigTimers.php` — Scheduled brig timer pattern

## Design Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Site access restriction | Use Brig system with `brig_type` | Reuses existing enforcement, dashboard card, and appeal infrastructure |
| MC/Discord when parent disables | New `ParentDisabled` status | Reversible when parent re-enables |
| Toggle defaults for 13-16 | All ON | Parent has oversight but doesn't need to opt-in |
| Toggle defaults for under-13 | All OFF | Requires explicit parent approval |
| Child account password (parent-created) | Email password-reset link | Child sets their own password |
| Multiple parents per child | Yes | Multiple guardians, last-write-wins on toggles |
| Permission toggle storage | On `users` table | Any parent can change; avoids per-parent conflicts |
| Brig + ParentDisabled interaction | Discipline overrides parental; on release, check parent_allows_site | Clean state transitions |
| `date_of_birth` null (existing users) | Middleware prompts for DOB | All users get age-gated |
| Parent Portal sidebar visibility | 18+ OR has children | Gate: `view-parent-portal` |
| Age 13 transition | Auto-release from parental_pending if no parent registered | Child notified |
| Age 17 transition | Parent gets "Release to adult" button | Manual release |
| Age 19 transition | Automatic release | Scheduled job dissolves parent links |

---

## Database Changes

### Migration 1: `add_parental_fields_to_users_table`

| Column | Type | Default | Purpose |
|---|---|---|---|
| `date_of_birth` | date, nullable | null | Age calculation |
| `parent_email` | string(255), nullable | null | For auto-linking before parent registers |
| `brig_type` | string(30), nullable | null | Differentiates brig reasons: discipline, parental_pending, parental_disabled, age_lock |
| `parent_allows_site` | boolean | true | Parent's site-access preference (independent of brig state) |
| `parent_allows_minecraft` | boolean | true | Parent toggle for MC |
| `parent_allows_discord` | boolean | true | Parent toggle for Discord |

```php
Schema::table('users', function (Blueprint $table) {
    $table->date('date_of_birth')->nullable()->after('email');
    $table->string('parent_email')->nullable()->after('date_of_birth');
    $table->string('brig_type', 30)->nullable()->after('brig_timer_notified');
    $table->boolean('parent_allows_site')->default(true)->after('brig_type');
    $table->boolean('parent_allows_minecraft')->default(true)->after('parent_allows_site');
    $table->boolean('parent_allows_discord')->default(true)->after('parent_allows_minecraft');
});
```

### Migration 2: `create_parent_child_links_table`

```php
Schema::create('parent_child_links', function (Blueprint $table) {
    $table->id();
    $table->foreignId('parent_user_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('child_user_id')->constrained('users')->cascadeOnDelete();
    $table->timestamps();
    $table->unique(['parent_user_id', 'child_user_id']);
});
```

---

## Authorization Rules

### New Gates

```php
Gate::define('view-parent-portal', fn ($user) =>
    $user->isAdult() || $user->children()->exists()
);

Gate::define('link-minecraft-account', fn ($user) =>
    ! $user->in_brig && $user->parent_allows_minecraft
);
```

### Modified Gates

**`link-discord`** — add parent check:
```php
Gate::define('link-discord', fn ($user) =>
    $user->isAtLeastLevel(MembershipLevel::Traveler)
        && ! $user->in_brig
        && $user->parent_allows_discord
);
```

**`view-community-content`** — no change needed (already checks `! $user->in_brig`, which now covers parental holds too).

### New Policy: `ParentChildLinkPolicy`

```php
public function manage(User $parent, User $child): bool
{
    return $parent->children()->where('child_user_id', $child->id)->exists();
}
```

---

## Implementation Steps

---

### Step 1: Migration — Users Table
**File**: `database/migrations/2026_02_28_000001_add_parental_fields_to_users_table.php`
**Action**: Create (see schema above)

---

### Step 2: Migration — Parent-Child Links Table
**File**: `database/migrations/2026_02_28_000002_create_parent_child_links_table.php`
**Action**: Create (see schema above)

---

### Step 3: Enum — BrigType
**File**: `app/Enums/BrigType.php`
**Action**: Create

```php
<?php
namespace App\Enums;

enum BrigType: string
{
    case Discipline = 'discipline';
    case ParentalPending = 'parental_pending';
    case ParentalDisabled = 'parental_disabled';
    case AgeLock = 'age_lock';

    public function label(): string
    {
        return match ($this) {
            self::Discipline => 'Disciplinary',
            self::ParentalPending => 'Pending Parental Approval',
            self::ParentalDisabled => 'Restricted by Parent',
            self::AgeLock => 'Age Verification Required',
        };
    }

    public function isDisciplinary(): bool
    {
        return $this === self::Discipline;
    }

    public function isParental(): bool
    {
        return in_array($this, [self::ParentalPending, self::ParentalDisabled]);
    }
}
```

---

### Step 4: Enum — MinecraftAccountStatus
**File**: `app/Enums/MinecraftAccountStatus.php`
**Action**: Modify — add case + label + color

```php
case ParentDisabled = 'parent_disabled';
// label: 'Disabled by Parent'
// color: 'purple'
```

---

### Step 5: Enum — DiscordAccountStatus
**File**: `app/Enums/DiscordAccountStatus.php`
**Action**: Modify — add case + label + color (same pattern: 'Disabled by Parent', 'purple')

---

### Step 6: Model — ParentChildLink
**File**: `app/Models/ParentChildLink.php`
**Action**: Create

```php
class ParentChildLink extends Model
{
    protected $fillable = ['parent_user_id', 'child_user_id'];

    public function parent(): BelongsTo { return $this->belongsTo(User::class, 'parent_user_id'); }
    public function child(): BelongsTo { return $this->belongsTo(User::class, 'child_user_id'); }
}
```

**Factory**: `database/factories/ParentChildLinkFactory.php` — `parent_user_id => User::factory(), child_user_id => User::factory()`

---

### Step 7: Model — Update User
**File**: `app/Models/User.php`
**Action**: Modify

**Add to `$fillable`:**
```php
'date_of_birth', 'parent_email', 'brig_type',
'parent_allows_site', 'parent_allows_minecraft', 'parent_allows_discord',
```

**Add to `casts()`:**
```php
'date_of_birth' => 'date',
'brig_type' => BrigType::class,
'parent_allows_site' => 'boolean',
'parent_allows_minecraft' => 'boolean',
'parent_allows_discord' => 'boolean',
```

**Add relationships:**
```php
public function children(): BelongsToMany
{
    return $this->belongsToMany(User::class, 'parent_child_links', 'parent_user_id', 'child_user_id')
        ->withTimestamps();
}

public function parents(): BelongsToMany
{
    return $this->belongsToMany(User::class, 'parent_child_links', 'child_user_id', 'parent_user_id')
        ->withTimestamps();
}
```

**Add helper methods:**
```php
public function isAdult(): bool
{
    return $this->date_of_birth === null || $this->date_of_birth->age >= 18;
}

public function isMinor(): bool
{
    return $this->date_of_birth !== null && $this->date_of_birth->age < 17;
}

public function isUnder13(): bool
{
    return $this->date_of_birth !== null && $this->date_of_birth->age < 13;
}

public function age(): ?int
{
    return $this->date_of_birth?->age;
}

public function hasParents(): bool { return $this->parents()->exists(); }
public function hasChildren(): bool { return $this->children()->exists(); }
```

**Update UserFactory** — add states: `minor(int $age = 15)`, `underThirteen()`, `adult()`.

---

### Step 8: Modify PutUserInBrig
**File**: `app/Actions/PutUserInBrig.php`
**Action**: Modify

**Changes:**
1. Add `BrigType $brigType = BrigType::Discipline` parameter to `handle()`
2. Add `bool $notify = true` parameter (parental brigs skip notification)
3. Set `$target->brig_type = $brigType` alongside existing brig fields
4. In MC account query, also catch `MinecraftAccountStatus::ParentDisabled`:
   ```php
   ->whereIn('status', [Active, Verifying, ParentDisabled])
   ```
5. In Discord account query, also catch `DiscordAccountStatus::ParentDisabled`
6. Only send notification if `$notify === true`

**Backward compatible**: existing callers don't pass `brigType` → defaults to `Discipline`.

For parental brigs: called with `expiresAt: null, appealAvailableAt: null, brigType: BrigType::ParentalPending, notify: false`.

---

### Step 9: Modify ReleaseUserFromBrig
**File**: `app/Actions/ReleaseUserFromBrig.php`
**Action**: Modify

**Changes at the end of the release logic:**

After clearing brig fields and restoring accounts, add:
```php
// Check if parental hold should re-engage
if (! $target->parent_allows_site && $target->isMinor()) {
    // Re-brig with parental type instead of full release
    $target->in_brig = true;
    $target->brig_type = BrigType::ParentalDisabled;
    $target->brig_reason = 'Site access restricted by parent.';
    $target->brig_expires_at = null;
    $target->next_appeal_available_at = null;
    $target->save();
    // MC/Discord already restored above — re-disable them if parent toggles are off
    // (UpdateChildPermission handles MC/Discord separately)
    return;
}

$target->brig_type = null; // Clear brig type on full release
$target->save();
```

**MC restoration now checks parent toggle:**
```php
// Instead of always restoring to Active:
$newStatus = $target->parent_allows_minecraft
    ? MinecraftAccountStatus::Active
    : MinecraftAccountStatus::ParentDisabled;
```

**Discord restoration checks parent toggle:**
```php
$newStatus = $target->parent_allows_discord
    ? DiscordAccountStatus::Active
    : DiscordAccountStatus::ParentDisabled;
```

Only whitelist-add and sync ranks/roles when restoring to Active.

---

### Step 10: Modify In-Brig Card
**File**: `resources/views/livewire/dashboard/in-brig-card.blade.php`
**Action**: Modify

Show different content based on `auth()->user()->brig_type`:

**Discipline (existing behavior, default/null):**
- Lock icon, "You Are In the Brig", red badge
- Reason, appeal timer, Submit Appeal button
- "Your Minecraft server access has been suspended."

**ParentalPending:**
- Shield icon, "Account Pending Approval", amber badge
- "Your account requires parental approval. We've sent an email to your parent or guardian."
- "Once they create an account and approve your access, you'll be able to use the site."
- No appeal button

**ParentalDisabled:**
- Shield icon, "Account Restricted by Parent", orange badge
- "Your parent or guardian has restricted your access to the site."
- "Please speak with your parent if you believe this is an error."
- No appeal button

**AgeLock:**
- Lock icon, "Account Locked", red badge
- "Your account has been locked for age verification."
- "Please update your date of birth to continue." (they'll be redirected by middleware)
- No appeal button

Implementation: wrap existing appeal logic in `@if($user->brig_type?->isDisciplinary() ?? true)` so appeal section only shows for disciplinary brigs. Add `@elseif` blocks for parental types with appropriate messaging.

---

### Step 11: Middleware — EnsureDateOfBirthIsSet
**File**: `app/Http/Middleware/EnsureDateOfBirthIsSet.php`
**Action**: Create

```php
<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureDateOfBirthIsSet
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && $request->user()->date_of_birth === null) {
            if (! $request->routeIs('birthdate.*', 'logout')) {
                return redirect()->route('birthdate.show');
            }
        }
        return $next($request);
    }
}
```

---

### Step 12: Register Middleware
**File**: `bootstrap/app.php`
**Action**: Modify

Add alias:
```php
'ensure-dob' => \App\Http\Middleware\EnsureDateOfBirthIsSet::class,
```

Apply to authenticated web routes (add to the `auth` middleware group, or apply to specific route groups).

---

### Step 13: Route — Birthdate Collection
**File**: `routes/web.php`
**Action**: Modify

```php
Volt::route('/birthdate', 'auth.collect-birthdate')
    ->name('birthdate.show')
    ->middleware(['auth']); // NOT verified, NOT ensure-dob (exempt)
```

---

### Step 14: Livewire — Collect Birthdate Page
**File**: `resources/views/livewire/auth/collect-birthdate.blade.php`
**Action**: Create

Uses `#[Layout('components.layouts.auth')]` (same as login/register — no sidebar/brig gate).

**Properties:**
```php
public int $step = 1;
public string $date_of_birth = '';
public string $parent_email = '';
```

**Methods:**

`submitDateOfBirth()`:
1. Validate `date_of_birth` (required, date, before:today)
2. Calculate age
3. If 17+ → save DOB, handle age_lock release if applicable, redirect to dashboard
4. If < 17 → set `$this->step = 2` (show parent email form)

`submitParentEmail()`:
1. Validate `parent_email` (required, email, different from user email)
2. Save DOB and parent_email on user
3. Calculate age:
   - Under 13:
     - Set `parent_allows_site = false, parent_allows_minecraft = false, parent_allows_discord = false`
     - If user is in age_lock brig → change brig_type to parental_pending, update reason
     - If not in brig → call PutUserInBrig with type=ParentalPending, notify=false
   - 13-16:
     - If in age_lock brig → release from brig
     - Parental toggles stay at defaults (all true)
4. Send `ParentAccountNotification` to parent_email
5. Redirect to dashboard (brig card shows if applicable)

**Blade template:**
- Step 1: "Please enter your date of birth" + date input + submit button
- Step 2: "A parent or guardian email is required" + email input + submit button

---

### Step 15: Registration Flow — Multi-Step
**File**: `resources/views/livewire/auth/register.blade.php`
**Action**: Modify (significant rewrite)

**Properties:** Add `$step`, `$date_of_birth`, `$parent_email` to existing properties.

**Flow:**

Step 1 (existing form + DOB):
- Validate all fields including `date_of_birth`
- If age >= 17 → create account normally, log in, redirect
- If age < 17 → `$this->step = 2`

Step 2 (parent email):
- Validate `parent_email`
- Call `createAccount()`

`createAccount()`:
1. Create user (DOB, parent_email set)
2. If under 13: `parent_allows_site/minecraft/discord = false`
3. Fire Registered event, record activity
4. Call `AutoLinkParentOnRegistration::run($user)`
5. If minor: send `ParentAccountNotification` via `Notification::route('mail', $parentEmail)`
6. If under 13: call `PutUserInBrig::run()` with type=ParentalPending, notify=false. Show step 3 message ("Thanks! We've emailed your parent."). Do NOT log in.
7. If 13-16: log in, redirect to dashboard

---

### Step 16: Notification — ParentAccountNotification
**File**: `app/Notifications/ParentAccountNotification.php`
**Action**: Create

On-demand notification (no user account needed). Sent via `Notification::route('mail', $parentEmail)`.

```php
public function __construct(public User $child, public bool $requiresApproval) {}

public function toMail(object $notifiable): MailMessage
{
    // If requiresApproval (under 13):
    //   "Your approval is required before [child] can access the community."
    //   Action: "Create Your Account" → route('register')
    // Else (13-16):
    //   "[child] has created an account. Create your own to manage their permissions."
    //   Action: "Create Your Account" → route('register')
    // Both include: description of Lighthouse, mention of Parent Portal controls
}
```

---

### Step 17: Action — AutoLinkParentOnRegistration
**File**: `app/Actions/AutoLinkParentOnRegistration.php`
**Action**: Create

Called after any `User::create()`. Checks if child users have `parent_email` matching the new user's email. Creates `ParentChildLink` records. Records activity per child.

```php
public function handle(User $newUser): void
{
    $children = User::where('parent_email', $newUser->email)->get();
    foreach ($children as $child) {
        if (ParentChildLink::where('parent_user_id', $newUser->id)
            ->where('child_user_id', $child->id)->exists()) {
            continue;
        }
        ParentChildLink::create([...]);
        RecordActivity::run($child, 'parent_linked', "Parent account ({$newUser->email}) automatically linked.");
    }
}
```

Integrate into: registration `createAccount()` method AND collect-birthdate page (when parent_email is set and auto-linking could match existing users).

---

### Step 18: Action — UpdateChildPermission
**File**: `app/Actions/UpdateChildPermission.php`
**Action**: Create

Handles toggling a parent permission with side effects.

```php
public function handle(User $child, User $parent, string $permission, bool $enabled): void
{
    match ($permission) {
        'use_site' => $this->toggleSiteAccess($child, $parent, $enabled),
        'minecraft' => $this->toggleMinecraft($child, $parent, $enabled),
        'discord' => $this->toggleDiscord($child, $parent, $enabled),
    };
}
```

**`toggleSiteAccess()`:**
- Set `$child->parent_allows_site = $enabled`
- If disabling AND child is NOT in disciplinary brig:
  - `PutUserInBrig::run($child, $parent, 'Site access restricted by parent.', brigType: ParentalDisabled, notify: false)`
- If enabling AND child is in parental brig (ParentalPending or ParentalDisabled):
  - `ReleaseUserFromBrig::run($child, $parent, 'Site access enabled by parent.')`
- If enabling AND child is in disciplinary brig: just save parent_allows_site (will take effect on discipline release)
- Record activity

**`toggleMinecraft()`:**
- Set `$child->parent_allows_minecraft = $enabled`
- If disabling: change Active/Verifying MC accounts to `ParentDisabled`, whitelist remove
- If enabling: change `ParentDisabled` MC accounts to `Active`, whitelist add, sync ranks
- Record activity
- (Uses `SendMinecraftCommand::run()` for RCON, `SyncMinecraftRanks::run()` for rank sync)

**`toggleDiscord()`:**
- Set `$child->parent_allows_discord = $enabled`
- If disabling: `DiscordApiService::removeAllManagedRoles()`, set status to ParentDisabled
- If enabling: set ParentDisabled accounts to Active, `SyncDiscordRoles::run()`
- Record activity

---

### Step 19: Action — CreateChildAccount
**File**: `app/Actions/CreateChildAccount.php`
**Action**: Create

```php
public function handle(User $parent, string $name, string $email, string $dateOfBirth): User
{
    $child = User::create([
        'name' => $name,
        'email' => $email,
        'password' => bcrypt(Str::random(32)),
        'date_of_birth' => $dateOfBirth,
        // Parent is creating → implicitly approving → all toggles ON, not in brig
        'parent_allows_site' => true,
        'parent_allows_minecraft' => true,
        'parent_allows_discord' => true,
    ]);

    ParentChildLink::create([
        'parent_user_id' => $parent->id,
        'child_user_id' => $child->id,
    ]);

    Password::sendResetLink(['email' => $email]);
    RecordActivity::run($child, 'child_account_created', "Account created by parent {$parent->name}.");
    return $child;
}
```

---

### Step 20: Action — LockAccountForAgeVerification
**File**: `app/Actions/LockAccountForAgeVerification.php`
**Action**: Create

Staff action when someone is suspected of lying about their age.

```php
public function handle(User $target, User $admin): void
{
    $target->date_of_birth = null; // Force re-entry via middleware

    if (! $target->isInBrig()) {
        PutUserInBrig::run(
            target: $target,
            admin: $admin,
            reason: 'Account locked: age verification required by staff.',
            brigType: BrigType::AgeLock,
            notify: false
        );
    } else {
        // Already in brig — just update type and reason
        $target->brig_type = BrigType::AgeLock;
        $target->brig_reason = 'Account locked: age verification required by staff.';
        $target->save();
    }

    RecordActivity::run($target, 'account_age_locked', "Account locked for age verification by {$admin->name}.");
}
```

---

### Step 21: Action — ReleaseChildToAdult
**File**: `app/Actions/ReleaseChildToAdult.php`
**Action**: Create

Parent can release 17+ child. Also used by scheduled auto-release at 19.

```php
public function handle(User $child, ?User $releasedBy = null): void
{
    // Dissolve all parent-child links
    ParentChildLink::where('child_user_id', $child->id)->delete();

    // Reset parental toggles to defaults
    $child->parent_allows_site = true;
    $child->parent_allows_minecraft = true;
    $child->parent_allows_discord = true;
    $child->parent_email = null;
    $child->save();

    // If in parental brig, release
    if ($child->isInBrig() && $child->brig_type?->isParental()) {
        ReleaseUserFromBrig::run($child, $releasedBy ?? $child, 'Released to adult account.');
    }

    $desc = $releasedBy
        ? "Released to adult account by {$releasedBy->name}."
        : 'Automatically released to adult account (age 19+).';
    RecordActivity::run($child, 'child_released_to_adult', $desc);
}
```

---

### Step 22: Gates & Policy Registration
**File**: `app/Providers/AuthServiceProvider.php`
**Action**: Modify

Add `ParentChildLink => ParentChildLinkPolicy` to `$policies`.

Add gates: `view-parent-portal`, `link-minecraft-account`.

Modify `link-discord` gate: add `&& $user->parent_allows_discord`.

---

### Step 23: Policy — ParentChildLinkPolicy
**File**: `app/Policies/ParentChildLinkPolicy.php`
**Action**: Create

```php
public function manage(User $parent, User $child): bool
{
    return $parent->children()->where('child_user_id', $child->id)->exists();
}
```

---

### Step 24: Enforce `link-minecraft-account` Gate
**File**: `resources/views/livewire/settings/minecraft-accounts.blade.php`
**Action**: Modify

Line 557: Replace inline `!auth()->user()->isInBrig()` with gate check:
```php
@elseif($remainingSlots > 0 && !$verificationCode && Gate::allows('link-minecraft-account'))
```

Line 628: Same replacement for reactivate button:
```php
@if($remainingSlots > 0 && Gate::allows('link-minecraft-account'))
```

Also add `$this->authorize('link-minecraft-account')` inside `generateCode()` method for server-side enforcement.

---

### Step 25: Route — Parent Portal
**File**: `routes/web.php`
**Action**: Modify

```php
Volt::route('/parent-portal', 'parent-portal.index')
    ->name('parent-portal.index')
    ->middleware(['auth', 'verified', 'ensure-dob']);
```

---

### Step 26: Sidebar — Parent Portal Link
**File**: `resources/views/components/layouts/app/sidebar.blade.php`
**Action**: Modify — add after Tickets item in "Platform" group

```blade
@can('view-parent-portal')
    <flux:navlist.item
        icon="user-group"
        :href="route('parent-portal.index')"
        :current="request()->routeIs('parent-portal.*')"
        wire:navigate
    >
        Parent Portal
    </flux:navlist.item>
@endcan
```

---

### Step 27: Parent Portal Page
**File**: `resources/views/livewire/parent-portal/index.blade.php`
**Action**: Create

**Properties:**
```php
public string $newChildName = '';
public string $newChildEmail = '';
public string $newChildDob = '';
```

**Computed property:**
```php
#[Computed]
public function children() {
    return auth()->user()->children()
        ->with(['minecraftAccounts', 'discordAccounts'])
        ->get();
}
```

**Methods:**
- `mount()` — `$this->authorize('view-parent-portal')`
- `togglePermission(int $childId, string $permission)` — authorize via policy, call `UpdateChildPermission::run()`, toast
- `createChildAccount()` — validate (name, email unique, DOB before:today), call `CreateChildAccount::run()`, close modal, toast
- `releaseToAdult(int $childId)` — authorize via policy, verify child is 17+, call `ReleaseChildToAdult::run()`, toast

**Blade layout — card per child:**

```
┌─────────────────────────────────────────┐
│ ChildName                               │
│ Age 12 · child@email.com                │
│                          [In the Brig]  │  ← badge if applicable
│                                         │
│ ⚠ Brig callout with reason              │  ← only if brigged
│                                         │
│ ── Permissions ─────────────────────── │
│ Use the Site                    [toggle] │
│ Join Minecraft Server           [toggle] │
│ Join Discord Server             [toggle] │
│                                         │
│ ── Linked Accounts ──────────────────── │
│ Minecraft: Player1 (Active)             │
│ Discord: User#1234 (Active)             │
│ No Discord accounts linked              │
│                                         │
│ ── Tickets ─────────────────────────── │
│ Help with account            [Open]     │
│ Question about rules         [Closed]   │
│                                         │
│ [Release to Adult Account]              │  ← only if child is 17+
└─────────────────────────────────────────┘

[+ Add Child Account]  ← opens modal
```

**Ticket query**: Load in computed property or `with()`. Show threads where `created_by_user_id = $child->id`, type = Ticket, status in [Open, Closed, Resolved], latest 10.

**Add Child Modal**: Name, Email, DOB inputs → calls `createChildAccount()`.

---

### Step 28: Staff UI — Lock for Age Verification Button
**File**: `resources/views/livewire/users/display-basic-details.blade.php`
**Action**: Modify

Add button near existing brig controls (visible to staff with `manage-stowaway-users` gate):

```blade
@can('manage-stowaway-users')
    @if(! $user->isInBrig())
        <flux:button wire:click="lockForAgeVerification" size="sm" variant="danger" icon="shield-exclamation">
            Lock for Age Verification
        </flux:button>
    @endif
@endcan
```

Add method:
```php
public function lockForAgeVerification(): void
{
    $this->authorize('manage-stowaway-users');
    LockAccountForAgeVerification::run($this->user, Auth::user());
    Flux::toast("{$this->user->name} locked for age verification.", 'Account Locked', variant: 'warning');
    $this->dispatch('$refresh');
}
```

---

### Step 29: Scheduled Command — ProcessAgeTransitions
**File**: `app/Console/Commands/ProcessAgeTransitions.php`
**Action**: Create

Runs daily. Three checks:

**1. Turned 13, in parental_pending, no parent registered:**
```php
User::where('in_brig', true)
    ->where('brig_type', BrigType::ParentalPending)
    ->whereDate('date_of_birth', '<=', now()->subYears(13))
    ->whereDoesntHave('parents')
    ->each(function ($user) {
        ReleaseUserFromBrig::run($user, $user, 'Automatically released: turned 13.');
        $user->notify(new AccountUnlockedNotification());
    });
```

**2. Turned 17 — notify parents about release option:**
```php
User::whereDate('date_of_birth', now()->subYears(17)->toDateString())
    ->whereHas('parents')
    ->each(function ($child) {
        // Notify each parent
        foreach ($child->parents as $parent) {
            // Send notification: "Your child has turned 17. You may release them to a full adult account."
        }
    });
```

**3. Turned 19 — auto-release:**
```php
User::whereDate('date_of_birth', '<=', now()->subYears(19))
    ->whereHas('parents')
    ->each(function ($child) {
        ReleaseChildToAdult::run($child);
    });
```

Register in `routes/console.php`:
```php
Schedule::command('parent-portal:process-age-transitions')->daily();
```

---

### Step 30: Notification — AccountUnlockedNotification
**File**: `app/Notifications/AccountUnlockedNotification.php`
**Action**: Create

Sent to child when their account is auto-released at age 13.

```php
public function toMail(object $notifiable): MailMessage
{
    return (new MailMessage)
        ->subject('Your Lighthouse account has been unlocked!')
        ->line('Great news! Your Lighthouse Minecraft account has been unlocked.')
        ->line('You can now log in and access the community.')
        ->action('Go to Dashboard', route('dashboard'));
}
```

---

### Step 31: Tests

**`tests/Feature/Actions/AutoLinkParentOnRegistrationTest.php`**
- `it('links parent to child when emails match')`
- `it('does not create duplicate links')`
- `it('links parent to multiple children with same parent_email')`
- `it('does nothing when no children have matching parent_email')`

**`tests/Feature/Actions/UpdateChildPermissionTest.php`**
- `it('disables site access and puts child in parental brig')`
- `it('enables site access and releases child from parental brig')`
- `it('does not release child from disciplinary brig when site enabled')`
- `it('disables minecraft and sets accounts to ParentDisabled')`
- `it('enables minecraft and restores ParentDisabled accounts')`
- `it('does not change Banned accounts when disabling minecraft')`
- `it('disables discord and removes roles')`
- `it('enables discord and restores accounts')`
- `it('records activity for each permission change')`

**`tests/Feature/Actions/CreateChildAccountTest.php`**
- `it('creates child account with correct fields')`
- `it('links parent to new child')`
- `it('sends password reset email to child')`
- `it('records activity')`

**`tests/Feature/Actions/LockAccountForAgeVerificationTest.php`**
- `it('puts user in brig with age_lock type')`
- `it('clears date_of_birth')`
- `it('records activity')`
- `it('updates existing brig to age_lock type')`

**`tests/Feature/Actions/ReleaseChildToAdultTest.php`**
- `it('dissolves parent-child links')`
- `it('resets parental toggles to defaults')`
- `it('releases from parental brig')`
- `it('does not release from disciplinary brig')`
- `it('records activity')`

**`tests/Feature/Actions/PutUserInBrigWithParentTest.php`**
- `it('sets brig_type on user')`
- `it('changes ParentDisabled MC accounts to Banned when brigged')`
- `it('defaults to Discipline brig_type')`
- `it('skips notification when notify is false')`

**`tests/Feature/Actions/ReleaseUserFromBrigWithParentTest.php`**
- `it('re-brigs with ParentalDisabled when parent_allows_site is false')`
- `it('restores MC to ParentDisabled when parent has MC disabled')`
- `it('restores MC to Active when parent has MC enabled')`
- `it('fully releases when parent_allows_site is true')`

**`tests/Feature/Auth/RegistrationWithAgeTest.php`**
- `it('shows date_of_birth field on registration')`
- `it('registers 17+ user normally')`
- `it('shows parent email step for under 17')`
- `it('puts under 13 in brig with parental_pending type')`
- `it('does not log in under 13 user')`
- `it('logs in 13-16 user after registration')`
- `it('sends parent notification for minor')`
- `it('auto-links parent on registration')`

**`tests/Feature/Auth/CollectBirthdateTest.php`**
- `it('redirects users without DOB to birthdate page')`
- `it('does not redirect users with DOB')`
- `it('puts existing user under 13 in parental_pending brig')`
- `it('transitions age_lock to parental_pending for under 13')`
- `it('releases age_lock for 13-16 and collects parent email')`
- `it('releases age_lock for 17+')`
- `it('sends parent notification after DOB collection')`

**`tests/Feature/Gates/ParentPortalGatesTest.php`**
- `it('allows adult to view parent portal')`
- `it('allows user with children to view parent portal')`
- `it('denies minor without children')`
- `it('blocks MC linking when parent_allows_minecraft is false')`
- `it('blocks discord linking when parent_allows_discord is false')`

**`tests/Feature/Livewire/ParentPortalTest.php`**
- `it('renders for authorized parent')`
- `it('shows child details')`
- `it('toggles permissions')`
- `it('creates child account')`
- `it('shows brig status')`
- `it('shows release button for 17+ child')`
- `it('denies access to non-parent minor')`

**`tests/Feature/Livewire/InBrigCardParentalTest.php`**
- `it('shows parental pending message')`
- `it('shows parental disabled message')`
- `it('shows age lock message')`
- `it('shows appeal button only for disciplinary brig')`
- `it('hides appeal button for parental brig')`

---

## Edge Cases

1. **Child registers, parent never creates account** — child stays in parental_pending brig. Auto-released at age 13.
2. **Discipline brig + parent disables site** — brig_type stays Discipline. parent_allows_site set to false. On discipline release, re-brigs with ParentalDisabled.
3. **Parent enables site for child in disciplinary brig** — parent_allows_site set to true, but child stays in disciplinary brig. Takes effect on release.
4. **Multiple parents, conflicting toggles** — last-write-wins on user fields. Any parent can change.
5. **Existing users with null DOB** — middleware redirects to /birthdate. Under-13 → brig. 13-16 → parent email. 17+ → save and continue.
6. **Staff locks account (age verification)** — DOB cleared, user brigged with age_lock. Middleware forces re-entry.
7. **Parent disables MC, child has Verifying account** — set to ParentDisabled.
8. **Auto-release at 19** — dissolves parent links regardless of parent preference.
9. **Child turns 13 with parent linked and site enabled** — no change (already active).
10. **Child turns 13 with parent linked and site disabled** — stays in parental_disabled brig (parent must enable).

## Known Risks

1. **RCON failures** — same risk as existing brig system. Fire-and-forget.
2. **Discord rate limits** — same pattern as brig. Acceptable.
3. **Email bounce for parent notification** — child stays in brig. Phase 2: re-send mechanism.
4. **DOB honesty** — users can lie. Staff "lock for age" action mitigates.
5. **Middleware performance** — adds one DB read per request (checking DOB). Cached after first check via model attribute access.

## Verification

1. `php artisan migrate:fresh` passes
2. `./vendor/bin/pest` passes with zero failures
3. Manual test: register as under-13 → verify brig card shows → register as parent → verify auto-link → enable site toggle → verify child can access dashboard
4. Manual test: register as 15-year-old → verify parent email sent → verify 13-16 defaults
5. Manual test: staff locks account → verify DOB middleware redirects → re-enter DOB
6. Manual test: parent creates child from portal → child receives password reset email

## Definition of Done

- [ ] All migrations run cleanly
- [ ] All test cases implemented and passing
- [ ] Registration flow works for all age groups
- [ ] DOB middleware prompts existing users
- [ ] Brig card shows correct content per brig_type
- [ ] Parent Portal renders with toggles, accounts, tickets, brig
- [ ] Permission toggles correctly enable/disable MC/Discord accounts
- [ ] Brig + parental state transitions handle all overlap cases
- [ ] Staff "Lock for Age" button works
- [ ] Scheduled age transitions work (13, 17, 19)
- [ ] No ad-hoc auth checks in Blade — gates/policies only
- [ ] Sidebar shows Parent Portal for eligible users
