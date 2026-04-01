# Child Account Onboarding Enforcement -- Technical Documentation

> **Audience:** Project owner, developers, AI agents
> **Generated:** 2026-03-31
> **Generator:** `/document-feature` skill

---

## Table of Contents

1. [Overview](#1-overview)
2. [Database Schema](#2-database-schema)
3. [Models & Relationships](#3-models--relationships)
4. [Enums Reference](#4-enums-reference)
5. [Authorization & Permissions](#5-authorization--permissions)
6. [Routes](#6-routes)
7. [User Interface Components](#7-user-interface-components)
8. [Actions (Business Logic)](#8-actions-business-logic)
9. [Notifications](#9-notifications)
10. [Background Jobs](#10-background-jobs)
11. [Console Commands & Scheduled Tasks](#11-console-commands--scheduled-tasks)
12. [Services](#12-services)
13. [Activity Log Entries](#13-activity-log-entries)
14. [Data Flow Diagrams](#14-data-flow-diagrams)
15. [Configuration](#15-configuration)
16. [Test Coverage](#16-test-coverage)
17. [File Map](#17-file-map)
18. [Known Issues & Improvement Opportunities](#18-known-issues--improvement-opportunities)

---

## 1. Overview

The **Child Account Onboarding Enforcement** feature tracks and enforces rule acceptance for user accounts, with special support for child accounts managed by parents. When a user creates an account, they begin at the **Drifter** membership level. To access community features (Discord, Minecraft), they must first read and agree to the Lighthouse Community Rules, which promotes them to **Stowaway** membership level.

For child accounts (users with a date of birth under 18), parents can agree to the rules on their behalf through the parent portal, eliminating the need for children to navigate the rules agreement process independently. The system tracks **who** agreed to the rules (the user themselves or a parent) via the `rules_accepted_by_user_id` column and timestamp via `rules_accepted_at`.

This feature is critical for:
- **Regulatory compliance:** Ensuring parental consent for minors before community participation
- **Community safety:** Enforcing explicit rule acceptance before access to communication channels
- **Staff transparency:** Providing visibility into who authorized rule acceptance for each user

The enforcement guards in `generateChildMcCode()` prevent Drifter and Stowaway users from linking Minecraft accounts, while the parent portal provides clear UI state indicating the next steps for each child.

---

## 2. Database Schema

### `users` table additions

| Column | Type | Nullable | Default | Constraints | Purpose |
|--------|------|----------|---------|-------------|---------|
| `rules_accepted_at` | timestamp | ✓ | NULL | — | Records the exact moment the user (or their parent) agreed to the community rules |
| `rules_accepted_by_user_id` | unsignedBigInteger | ✓ | NULL | FK → `users.id` (nullOnDelete) | Tracks who agreed: the user's own ID (self) or a parent's ID (parent agreement) |

**Migration(s):** `database/migrations/2026_03_31_000001_add_rules_accepted_by_user_id_to_users.php`

**Backfill Logic:** The migration backfills `rules_accepted_by_user_id` for all existing Stowaway+ users (membership_level >= 1) to their own ID, indicating they agreed themselves.

---

## 3. Models & Relationships

### User (`app/Models/User.php`)

#### Fillable Fields

```php
protected $fillable = [
    'rules_accepted_at',
    'rules_accepted_by_user_id',
    // ... other fields ...
];
```

#### Relationship: `rulesAcceptedBy()`

```php
public function rulesAcceptedBy(): BelongsTo
{
    return $this->belongsTo(User::class, 'rules_accepted_by_user_id');
}
```

Returns the User who agreed to the rules on behalf of this user. If `rules_accepted_by_user_id` equals the user's own ID, they agreed themselves. If it points to a different user, that user (typically a parent) agreed on their behalf.

#### Cast

```php
protected function casts(): array
{
    return [
        'rules_accepted_at' => 'datetime',
        // ... other casts ...
    ];
}
```

#### Related Methods

**`isAtLeastLevel(MembershipLevel $level): bool`**
- Returns `true` if the user's membership_level value is >= the provided level's value
- Used to guard rule agreement: users already at Stowaway or above cannot agree again

**`isLevel(MembershipLevel $level): bool`**
- Returns `true` if the user's membership_level exactly matches the provided level
- Used to display appropriate UI states in parent portal and other components

---

## 4. Enums Reference

### MembershipLevel (`app/Enums/MembershipLevel.php`)

Relevant to this feature:

| Case | Value | Discord Role | Minecraft Rank | Significance |
|------|-------|--------------|-----------------|--------------|
| `Drifter` | 0 | None | None | Initial state; must agree to rules before proceeding |
| `Stowaway` | 1 | None | None | Promoted after rule acceptance; awaiting staff review for higher access |
| `Traveler` | 2 | traveler | traveler | Full member; can link Minecraft; minimum staff-approved level |
| `Resident` | 3 | resident | resident | Senior member |
| `Citizen` | 4 | citizen | citizen | Leadership member |

**Note:** Only `Traveler` and above can link Minecraft accounts. `Drifter` and `Stowaway` are blocked by guards in `generateChildMcCode()`.

---

## 5. Authorization & Permissions

### Gates

No new gates were created for this feature. Existing gates relevant to enforcement:

**`manage-stowaway-users`**
- Checked by: User profiles display (Rules Agreed By visibility), Stowaway widget modal
- Holder: Users with `Membership Level - Manager` role
- Purpose: Restrict visibility of rule agreement details to staff management

### Permissions Matrix

| User Type | View Rules Agreed By | Agree on Own Behalf | Agree on Behalf of Child |
|-----------|----------------------|---------------------|--------------------------|
| Drifter | No | Yes | No (not a parent) |
| Stowaway+ (own profile) | No | N/A (already agreed) | No |
| Stowaway+ (staff with manage gate) | Yes | N/A | No (not implementation role) |
| Parent | N/A | Yes (self-agreement) | Yes (in parent portal) |
| Staff (Membership Level - Manager) | Yes (via modal/profile) | N/A | N/A |

---

## 6. Routes

Not applicable. This feature does not introduce new routes. It extends existing livewire components and their methods:
- Parent portal component (`parent-portal.index`)
- Stowaway widget component (`dashboard.stowaway-users-widget`)
- User profile display component (`users.display-basic-details`)
- View rules modal component (`dashboard.view-rules`)

---

## 7. User Interface Components

### Parent Portal (`resources/views/livewire/parent-portal/index.blade.php`)

#### Three-State Minecraft Section

The parent portal displays different UI for children based on their membership level:

**State 1: Drifter + Minecraft Enabled** (Rules Agreement Required)
- Displays a blue `flux:card` titled "Rules Agreement Required"
- Shows the Lighthouse Community Rules in a collapsible details element
- Renders a confirmation button: "I agree to the community rules on behalf of [child name]"
- Button triggers `agreeToRulesOnBehalf($childId)`
- Confirmation dialog asks parent to verify they've read and agree to the rules

**State 2: Stowaway + Minecraft Enabled** (Awaiting Staff Review)
- Displays a yellow/amber `flux:card` titled "Awaiting Staff Review"
- Indicates the child has agreed but is waiting for staff to promote them to Traveler before Minecraft access is enabled
- No action buttons; informational only

**State 3: Traveler+ + Minecraft Enabled** (Link Minecraft Account)
- Displays the Minecraft username input, account type selector (Java/Bedrock), and "Generate Code" button
- Allows parent to initiate the Minecraft verification flow

#### Method: `agreeToRulesOnBehalf(int $childId)`

```php
public function agreeToRulesOnBehalf(int $childId): void
```

**Purpose:** Allow a parent to agree to the community rules on behalf of their Drifter child.

**Validation:**
1. Reject if called during staff viewing mode (`isStaffViewing`)
2. Reject if the child is not a direct child of the acting parent (via `ParentChildLink`)
3. Reject if the child is already Stowaway or above (via `isAtLeastLevel()`)

**Action:**
- Calls `AgreeToRules::run($child, $parent)` where `$parent` is the acting user
- On success: Updates `rules_accepted_by_user_id`, `rules_accepted_at`, promotes to Stowaway, logs activity
- On success: Unsets cached children, shows success toast, refreshes UI
- On failure: Shows error toast

**Test Coverage:** `tests/Feature/Livewire/ParentPortalAgreeToRulesTest.php`

#### Method: `generateChildMcCode(int $childId)`

```php
public function generateChildMcCode(int $childId): void
```

**Purpose:** Generate a Minecraft verification code for a child's account.

**Membership Level Guards (Enforcement):**
1. **Drifter Guard** (Line 267–271):
   ```php
   if ($child->isLevel(\App\Enums\MembershipLevel::Drifter)) {
       Flux::toast('This child must agree to the community rules before linking a Minecraft account.', 'Rules Required', variant: 'danger');
       return;
   }
   ```
   - Prevents code generation if child is Drifter
   - Message directs parent to rules agreement flow

2. **Stowaway Guard** (Line 273–277):
   ```php
   if ($child->isLevel(\App\Enums\MembershipLevel::Stowaway)) {
       Flux::toast('This child is awaiting staff review before they can link a Minecraft account.', 'Awaiting Review', variant: 'danger');
       return;
   }
   ```
   - Prevents code generation if child is Stowaway
   - Indicates child needs staff promotion before Minecraft access

**Note:** These guards are the enforcement mechanism for the onboarding flow. They prevent children from bypassing the rule agreement and staff review steps.

**Test Coverage:** `tests/Feature/Livewire/ParentPortalMcCodeGuardTest.php`

### Stowaway Users Widget (`resources/views/livewire/dashboard/stowaway-users-widget.blade.php`)

#### Rules Agreed By Section

Displayed in the user details modal when viewing a stowaway user (requires `manage-stowaway-users` gate):

```blade
<div class="flex justify-between gap-4">
    <dt class="text-zinc-500 dark:text-zinc-400 font-medium shrink-0">Rules Agreed By</dt>
    <dd>
        @if(is_null($selectedUser->rules_accepted_by_user_id))
            <span class="text-zinc-400 italic">Not yet agreed</span>
        @elseif($selectedUser->rules_accepted_by_user_id === $selectedUser->id)
            Self
        @else
            @php $agreedBy = $selectedUser->rulesAcceptedBy; @endphp
            @if($agreedBy)
                <flux:link href="{{ route('profile.show', $agreedBy) }}">{{ $agreedBy->name }}</flux:link>
                (parent) — {{ $agreedBy->email }}
            @else
                <span class="text-zinc-400 italic">Unknown (user not found)</span>
            @endif
        @endif
    </dd>
</div>
```

**States:**
- `null` → "Not yet agreed"
- `user->id` → "Self"
- Other ID → Link to parent profile + "(parent)" label + parent email
- Deleted parent → "Unknown (user not found)" (via nullOnDelete foreign key cascade)

**Test Coverage:** `tests/Feature/Livewire/Dashboard/StowawayUsersWidgetTest.php` (Rules Agreed By describe block)

### User Profile (`resources/views/livewire/users/display-basic-details.blade.php`)

#### Rules Agreed By Section

Displayed on the user profile page when viewing from a staff perspective (requires `manage-stowaway-users` gate):

```blade
@can('manage-stowaway-users')
    <div class="flex items-center gap-2 text-sm">
        <flux:text class="text-zinc-500 dark:text-zinc-400 shrink-0">Rules Agreed By:</flux:text>
        @if(is_null($user->rules_accepted_by_user_id))
            <flux:text class="italic text-zinc-400">Not yet agreed</flux:text>
        @elseif($user->rules_accepted_by_user_id === $user->id)
            <flux:text>Self</flux:text>
        @else
            @php $agreedBy = $user->rulesAcceptedBy; @endphp
            @if($agreedBy)
                <flux:link href="{{ route('profile.show', $agreedBy) }}">{{ $agreedBy->name }}</flux:link>
                <flux:text>(parent) — {{ $agreedBy->email }}</flux:text>
            @else
                <flux:text class="italic text-zinc-400">Unknown (user not found)</flux:text>
            @endif
        @endif
    </div>
@endcan
```

**Visibility:** Only visible to staff with `manage-stowaway-users` gate (Membership Level Manager role).

**Test Coverage:** `tests/Feature/Livewire/ProfileRulesAgreedByTest.php`

### View Rules Modal (`resources/views/livewire/dashboard/view-rules.blade.php`)

#### Rules Agreement Button

Displayed at the bottom of the rules modal to users who are Drifter or haven't yet agreed:

```blade
@if (!auth()->user()->rules_accepted_at || auth()->user()->isLevel(MembershipLevel::Drifter))
    <flux:button color="amber" wire:click="acceptRules" variant="primary">
        I Have Read the Rules and Agree to Follow Them
    </flux:button>
@endif
```

**Visibility:** Only shown if user is Drifter OR has no `rules_accepted_at` timestamp.

#### Method: `acceptRules()`

```php
public function acceptRules()
{
    $result = \App\Actions\AgreeToRules::run(auth()->user(), auth()->user());

    if (! $result['success']) {
        Flux::toast($result['message'], 'Error', variant: 'danger');
        return;
    }

    Flux::modal('view-rules-modal')->close();
}
```

**Action:** Calls `AgreeToRules::run()` with the authenticated user as both the agreeing user and acting user (self-agreement).

### Community Rules Partial (`resources/views/partials/community-rules.blade.php`)

Static partial included in:
- Parent portal (collapsible details element)
- View rules modal

Contains the Lighthouse Community Rules HTML. No dynamic behavior; used for display only.

---

## 8. Actions (Business Logic)

### AgreeToRules (`app/Actions/AgreeToRules.php`)

**Type:** Lorisleiva Actions library action (uses `AsAction` trait)

**Signature:**
```php
public function handle(User $user, User $actingUser): array{success: bool, message: string}
```

**Parameters:**
- `$user` — The User for whom agreement is being recorded
- `$actingUser` — The User performing the agreement (may be the user themselves or a parent)

**Behavior:**

1. **Guard Check** (Line 20–22):
   ```php
   if ($user->isAtLeastLevel(MembershipLevel::Stowaway)) {
       return ['success' => false, 'message' => 'User has already agreed to the rules.'];
   }
   ```
   - Rejects agreement if user is already Stowaway or above
   - Prevents re-agreement or agreement loops

2. **Timestamp & Attribution** (Line 24–26):
   ```php
   $user->rules_accepted_at = now();
   $user->rules_accepted_by_user_id = $actingUser->id;
   $user->save();
   ```
   - Sets `rules_accepted_at` to current timestamp
   - Sets `rules_accepted_by_user_id` to `$actingUser->id`
   - If `$user === $actingUser`, this records self-agreement; otherwise parent agreement

3. **Activity Logging** (Line 28–34):
   ```php
   $isSelf = $user->id === $actingUser->id;
   $description = $isSelf
       ? 'User accepted community rules and was promoted to Stowaway.'
       : "Community rules agreed on behalf of user by {$actingUser->name} (parent). User promoted to Stowaway.";
   RecordActivity::run($user, 'rules_accepted', $description, $actingUser);
   ```
   - Logs a `rules_accepted` activity entry with contextual description
   - Indicates whether agreement was self or parent-initiated

4. **Promotion** (Line 36):
   ```php
   PromoteUser::run($user, MembershipLevel::Stowaway);
   ```
   - Automatically promotes the user to Stowaway level
   - PromoteUser handles Discord role assignment, Minecraft rank sync, and related notifications

5. **Return Success** (Line 38):
   ```php
   return ['success' => true, 'message' => 'Rules accepted. User promoted to Stowaway.'];
   ```

**Invocation Sites:**
- Parent portal: `ParentPortalIndex::agreeToRulesOnBehalf()`
- View rules modal: `ViewRulesComponent::acceptRules()`

**Test Coverage:** `tests/Feature/Actions/Actions/AgreeToRulesTest.php`

---

## 9. Notifications

Not applicable for this feature. No notifications are sent upon rule agreement. (PromoteUser may send membership level promotion notifications.)

---

## 10. Background Jobs

Not applicable for this feature.

---

## 11. Console Commands & Scheduled Tasks

Not applicable for this feature.

---

## 12. Services

Not applicable for this feature. This feature uses existing services (RecordActivity, PromoteUser) but does not define new services.

---

## 13. Activity Log Entries

### `rules_accepted` Action

**Table:** `activity_logs`

**Columns:**
- `subject_type` = `App\Models\User::class`
- `subject_id` = The user who agreed (or had agreement made on their behalf)
- `action` = `'rules_accepted'`
- `description` = Contextual message indicating self or parent agreement
- `causer_type` = `App\Models\User::class`
- `causer_id` = The user who triggered the agreement (actingUser)
- `properties` = (if tracked by RecordActivity)

**Description Examples:**
- Self-agreement: `"User accepted community rules and was promoted to Stowaway."`
- Parent agreement: `"Community rules agreed on behalf of user by Parent Name (parent). User promoted to Stowaway."`

**Visibility:** Logged by `RecordActivity::run()` within `AgreeToRules::handle()`.

---

## 14. Data Flow Diagrams

### User Agrees to Rules Themselves

```text
[User views rules modal]
         ↓
[Clicks "I Have Read the Rules and Agree to Follow Them"]
         ↓
[view-rules.blade.php acceptRules() method]
         ↓
[AgreeToRules::run($user, $user)]  ← Self-agreement
         ↓
[Guard: isAtLeastLevel(Stowaway)? — PASS (is Drifter)]
         ↓
[Set rules_accepted_at = now()]
[Set rules_accepted_by_user_id = $user->id]
[Save to users table]
         ↓
[RecordActivity: 'rules_accepted', 'User accepted...']
         ↓
[PromoteUser::run($user, MembershipLevel::Stowaway)]
         ↓
[User now Stowaway, visible in staff dashboards]
```

### Parent Agrees on Behalf of Drifter Child

```text
[Parent views Parent Portal]
         ↓
[Sees child at Drifter level + Minecraft enabled]
         ↓
[Sees "Rules Agreement Required" card with rules + button]
         ↓
[Reads community rules (collapsible element)]
         ↓
[Clicks "I agree to the community rules on behalf of [child]"]
         ↓
[Confirmation dialog: "You agree to the rules on behalf of [child]"]
         ↓
[parent-portal/index.blade.php agreeToRulesOnBehalf($childId)]
         ↓
[Verify: parent owns child via ParentChildLink — PASS]
         ↓
[AgreeToRules::run($child, $parent)]  ← Parent-agreement
         ↓
[Guard: isAtLeastLevel(Stowaway)? — PASS (child is Drifter)]
         ↓
[Set child.rules_accepted_at = now()]
[Set child.rules_accepted_by_user_id = $parent->id]
[Save to users table]
         ↓
[RecordActivity: 'rules_accepted', "Community rules agreed on behalf of user by [parent name] (parent)..."]
         ↓
[PromoteUser::run($child, MembershipLevel::Stowaway)]
         ↓
[Child now Stowaway]
         ↓
[Parent portal re-renders]
         ↓
[Shows "Awaiting Staff Review" card instead of rules agreement]
         ↓
[Parent waits for staff to promote child to Traveler]
```

### Child Attempts Minecraft Link (Guard Enforcement)

**Scenario 1: Drifter child**
```text
[Parent tries generateChildMcCode for Drifter child]
         ↓
[parent-portal/index.blade.php generateChildMcCode() line 267]
         ↓
[if ($child->isLevel(MembershipLevel::Drifter))]
         ↓
Toast: "This child must agree to the community rules..."
         ↓
[Return early — no code generated]
         ↓
[Parent directed back to rules agreement flow]
```

**Scenario 2: Stowaway child (awaiting staff review)**
```text
[Parent tries generateChildMcCode for Stowaway child]
         ↓
[parent-portal/index.blade.php generateChildMcCode() line 273]
         ↓
[if ($child->isLevel(MembershipLevel::Stowaway))]
         ↓
Toast: "This child is awaiting staff review before they can link a Minecraft account."
         ↓
[Return early — no code generated]
         ↓
[Parent waits for staff promotion]
```

**Scenario 3: Traveler+ child (allowed to proceed)**
```text
[Parent tries generateChildMcCode for Traveler child]
         ↓
[parent-portal/index.blade.php generateChildMcCode() line 267, 273]
         ↓
[Both isLevel checks return false]
         ↓
[Proceed to username validation, account type selection]
         ↓
[GenerateVerificationCode::run() — external call to Minecraft API]
         ↓
[Code generated and stored in verification table]
```

---

## 15. Configuration

Not applicable for this feature. No configuration files or environment variables are introduced.

---

## 16. Test Coverage

### Action Tests

**File:** `tests/Feature/Actions/Actions/AgreeToRulesTest.php` (8 tests)

1. **Self-agreement sets timestamps and attribution**
   - `it('self-agreement sets rules_accepted_at and rules_accepted_by_user_id to the user own id')`
   - Verifies `rules_accepted_at` is not null and `rules_accepted_by_user_id` equals user's own ID

2. **Self-agreement promotes to Stowaway**
   - `it('self-agreement promotes the user to Stowaway')`
   - Verifies membership level change from Drifter to Stowaway

3. **Self-agreement logs activity**
   - `it('self-agreement logs a rules_accepted activity')`
   - Verifies `rules_accepted` entry in activity logs

4. **Parent-agreement sets attribution**
   - `it('parent-agreement sets rules_accepted_by_user_id to the parent id')`
   - Verifies `rules_accepted_by_user_id` equals parent's ID, not child's

5. **Parent-agreement promotes to Stowaway**
   - `it('parent-agreement promotes the child to Stowaway')`
   - Verifies child membership level changes

6. **Guard rejects Stowaway user**
   - `it('guard rejects agreement for an already-Stowaway user')`
   - Verifies `success: false` and no state change for Stowaway user

7. **Guard rejects Traveler+ user**
   - `it('guard rejects agreement for a user above Stowaway')`
   - Verifies `success: false` for Traveler and higher levels

### Parent Portal Livewire Tests

**File:** `tests/Feature/Livewire/ParentPortalAgreeToRulesTest.php` (8 tests)

1. **Blade rendering: Drifter rules card**
   - `it('shows the Drifter rules agreement card when child is Drifter and Minecraft is enabled')`
   - Verifies "Rules Agreement Required" card and button text render

2. **Blade rendering: Stowaway waiting card**
   - `it('shows the Stowaway waiting card when child is Stowaway and Minecraft is enabled')`
   - Verifies "Awaiting Staff Review" card displays for Stowaway child

3. **Blade rendering: Traveler Minecraft linking**
   - `it('shows the Minecraft linking UI for a Traveler child')`
   - Verifies Minecraft form displays for Traveler+ child

4. **Blade rendering: No card when Minecraft disabled**
   - `it('does not show the Drifter card when Minecraft is disabled')`
   - Verifies UI hidden when `parent_allows_minecraft` is false

5. **Method: Parent agrees on behalf of child**
   - `it('parent can agree to rules on behalf of a Drifter child')`
   - Calls `agreeToRulesOnBehalf()`, verifies child promoted to Stowaway with `rules_accepted_by_user_id = $parent->id`

6. **Method: Parent agreement attribution**
   - `it('parent agreement sets rules_accepted_by_user_id to parent id, not child id')`
   - Verifies relationship points to parent, not child

7. **Method: Cannot agree for Stowaway child**
   - `it('cannot agree on behalf of an already-Stowaway child')`
   - Verifies guard prevents re-agreement; `rules_accepted_by_user_id` unchanged

8. **Method: Cannot agree for unrelated child**
   - `it('cannot agree on behalf of an unrelated child')`
   - Verifies permission check; parent cannot agree for non-owned child

### Minecraft Code Guard Tests

**File:** `tests/Feature/Livewire/ParentPortalMcCodeGuardTest.php` (3 tests)

1. **Guard rejects Drifter child**
   - `it('generateChildMcCode rejects a Drifter child with an error toast')`
   - Calls `generateChildMcCode()` for Drifter, verifies no code generated

2. **Guard rejects Stowaway child**
   - `it('generateChildMcCode rejects a Stowaway child with an error toast')`
   - Calls `generateChildMcCode()` for Stowaway, verifies no code generated

3. **Guard allows Traveler child**
   - `it('generateChildMcCode proceeds for a Traveler child')`
   - Calls `generateChildMcCode()` for Traveler, verifies guard doesn't block (external API may fail)

### Profile Display Tests

**File:** `tests/Feature/Livewire/ProfileRulesAgreedByTest.php` (5 tests)

1. **Display: Self-agreement**
   - `it('shows Rules Agreed By Self for staff with manage-stowaway-users gate when user agreed themselves')`
   - Verifies "Rules Agreed By: Self" displays for user with `rules_accepted_by_user_id = user->id`

2. **Display: Parent agreement**
   - `it('shows parent name and email when a parent agreed on behalf of child')`
   - Verifies parent name, email, and "(parent)" label display when `rules_accepted_by_user_id` points to parent

3. **Display: Not yet agreed**
   - `it('shows Not yet agreed when rules_accepted_by_user_id is null')`
   - Verifies "Not yet agreed" message when `rules_accepted_by_user_id` is null

4. **Visibility: Gate protection**
   - `it('does not show Rules Agreed By to users without manage-stowaway-users gate')`
   - Verifies section hidden for non-staff users

5. **Visibility: All membership levels**
   - `it('shows Rules Agreed By for all membership levels including Drifter and Citizen')`
   - Verifies display for both Drifter and Citizen levels (showing feature applies to entire spectrum)

### Stowaway Widget Tests

**File:** `tests/Feature/Livewire/Dashboard/StowawayUsersWidgetTest.php` (Describe block: Rules Agreed By, 4 tests)

1. **Display: Self-agreement in modal**
   - `it('shows Self when rules_accepted_by_user_id matches the user')`
   - Opens modal, verifies "Rules Agreed By: Self" for self-agreed user

2. **Display: Parent agreement in modal**
   - `it('shows parent name, email and profile link when a parent agreed')`
   - Opens modal, verifies parent info and "(parent)" label

3. **Display: Not yet agreed in modal**
   - `it('shows Not yet agreed when rules_accepted_by_user_id is null')`
   - Opens modal, verifies "Not yet agreed" for null `rules_accepted_by_user_id`

4. **Display: Cascade null on parent deletion**
   - `it('shows Not yet agreed when parent who agreed is later deleted (nullOnDelete cascade)')`
   - Deletes parent, verifies `rules_accepted_by_user_id` cascades to null (foreign key behavior), displays "Not yet agreed"

---

## 17. File Map

### Core Implementation

- `app/Actions/AgreeToRules.php` — Main action handling rule agreement logic
- `app/Models/User.php` — User model with `rulesAcceptedBy()` relationship and membership level methods
- `app/Enums/MembershipLevel.php` — Membership level enum (Drifter, Stowaway, Traveler, etc.)
- `database/migrations/2026_03_31_000001_add_rules_accepted_by_user_id_to_users.php` — Schema migration
- `app/Providers/AuthServiceProvider.php` — Authorization gates (uses existing gates, not new ones)

### UI Components (Livewire Views)

- `resources/views/livewire/parent-portal/index.blade.php` — Parent portal with `agreeToRulesOnBehalf()` method and MC code guards
- `resources/views/livewire/dashboard/stowaway-users-widget.blade.php` — Staff dashboard widget with "Rules Agreed By" modal section
- `resources/views/livewire/users/display-basic-details.blade.php` — User profile display with "Rules Agreed By" section
- `resources/views/livewire/dashboard/view-rules.blade.php` — Rules modal with `acceptRules()` method
- `resources/views/partials/community-rules.blade.php` — Static community rules partial (included in parent portal and modal)

### Tests

- `tests/Feature/Actions/Actions/AgreeToRulesTest.php` — Unit tests for AgreeToRules action (8 tests)
- `tests/Feature/Livewire/ParentPortalAgreeToRulesTest.php` — Parent portal agreement and UI state tests (8 tests)
- `tests/Feature/Livewire/ParentPortalMcCodeGuardTest.php` — MC code generation guards for Drifter/Stowaway (3 tests)
- `tests/Feature/Livewire/ProfileRulesAgreedByTest.php` — Profile display tests for "Rules Agreed By" visibility and gate protection (5 tests)
- `tests/Feature/Livewire/Dashboard/StowawayUsersWidgetTest.php` — Stowaway widget tests, including "Rules Agreed By" describe block (4 tests in Rules Agreed By section)

### Related but Not New

- `app/Actions/RecordActivity.php` — Called by AgreeToRules to log the activity
- `app/Actions/PromoteUser.php` — Called by AgreeToRules to promote user to Stowaway
- `app/Actions/GenerateVerificationCode.php` — Called by parent portal to generate MC codes (guarded by onboarding enforcement)

---

## 18. Known Issues & Improvement Opportunities

### 1. Foreign Key Cascade Behavior Asymmetry

**Observation:** The migration uses `nullOnDelete()` for the `rules_accepted_by_user_id` foreign key (line 14):

```php
$table->foreign('rules_accepted_by_user_id')->references('id')->on('users')->nullOnDelete();
```

If a parent account is deleted, the child's `rules_accepted_by_user_id` is set to NULL, causing the child to appear as "Not yet agreed" in the UI, even though they previously agreed.

**Impact:** Minimal for typical scenarios, but could confuse staff if a parent is deleted and then the child's profile is reviewed.

**Recommendation:** Consider logging a warning or audit entry when a parent deletion cascades to child records. Alternatively, use soft deletes on users to preserve historical data.

### 2. Guard Logic Uses `isLevel()` vs `isAtLeastLevel()`

**Observation:** The `generateChildMcCode()` guards use `isLevel()` (exact match):

```php
if ($child->isLevel(\App\Enums\MembershipLevel::Drifter)) { ... }
if ($child->isLevel(\App\Enums\MembershipLevel::Stowaway)) { ... }
```

Whereas `AgreeToRules::handle()` uses `isAtLeastLevel()` for the rejection guard:

```php
if ($user->isAtLeastLevel(MembershipLevel::Stowaway)) { ... }
```

**Implication:** If a Resident or Citizen user's membership is somehow reset to Drifter (edge case), they would be able to re-agree to rules. The `isLevel()` approach in `generateChildMcCode()` is more restrictive and appropriate for blocking specific levels.

**Recommendation:** No immediate change needed, but be aware that future membership level checks should clearly specify intent: "exactly this level" vs. "this level or higher."

### 3. No Validation on `rules_accepted_at` Without `rules_accepted_by_user_id`

**Observation:** A user could theoretically have `rules_accepted_at` set but `rules_accepted_by_user_id` NULL (or vice versa).

**Mitigation:** The `AgreeToRules` action is the only entry point for setting both columns together, so this is unlikely in practice. However, direct database manipulation or data imports could introduce inconsistency.

**Recommendation:** Consider adding a check constraint or validation rule ensuring both columns are set together: `(rules_accepted_at IS NULL AND rules_accepted_by_user_id IS NULL) OR (rules_accepted_at IS NOT NULL AND rules_accepted_by_user_id IS NOT NULL)`.

### 4. Staff Can View Sensitive Parental Information via Profile

**Observation:** When a parent agrees on behalf of a child, the child's profile (with `manage-stowaway-users` gate) displays the parent's name and email. This assumes staff viewing is authorized to see parental contact information.

**Verification:** Confirm that parental email is not considered PII requiring additional gating in this context.

**Recommendation:** Review authorization for displaying parental email on staff-visible profiles; ensure it aligns with data protection policies.

### 5. No Timeline or Audit for Staff Review Step

**Observation:** A child promoted to Stowaway remains in that state until staff manually promotes to Traveler, but there's no tracking of how long they've been waiting or which staff member promotes them.

**Mitigation:** The `PromoteUser` action logs an activity, but there's no dedicated "staff review started" or "staff review completed" event.

**Recommendation:** Consider logging a separate activity entry when a user is promoted from Stowaway to Traveler, or track the promoter's ID in the PromoteUser action for full audit trail.

