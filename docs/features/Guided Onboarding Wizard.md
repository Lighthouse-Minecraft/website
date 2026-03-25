# Guided Onboarding Wizard -- Technical Documentation

> **Audience:** Project owner, developers, AI agents
> **Generated:** 2026-03-25
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

The Guided Onboarding Wizard is a multi-step setup flow shown to newly registered users at the Stowaway and Traveler membership levels. It replaces the normal dashboard Community section with a focused card-based UI that guides users through the key account setup steps: connecting Discord, waiting for staff promotion, and linking their Minecraft account.

The wizard is driven entirely by the user's current state — no separate step column is stored. Step progression is inferred from the presence of linked accounts and activity log entries. Two timestamp columns on the `users` table (`onboarding_wizard_dismissed_at` and `onboarding_wizard_completed_at`) track whether the wizard has been permanently suppressed.

The wizard targets two membership levels in sequence: **Stowaways** see the Discord-connection step followed by a waiting-room step (pending staff promotion to Traveler), while **Travelers** see the Minecraft-linking step. Both levels can dismiss the wizard early at any point; dismissal permanently hides it and does not trigger the completion modal. Completion — achieved by finishing the final Minecraft step — sets both timestamps simultaneously and presents a "Welcome to Lighthouse!" modal with feature highlights.

A **Resume Account Setup** sidebar link is shown when the wizard is active (i.e., neither dismissed nor completed), allowing users navigating away to return. Users who already had linked accounts before the feature shipped are backfilled via migration so the wizard never appears for them.

---

## 2. Database Schema

### `users` table (onboarding columns)

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `onboarding_wizard_dismissed_at` | timestamp | Yes | NULL | Set when user clicks Dismiss on any step; permanently suppresses the wizard |
| `onboarding_wizard_completed_at` | timestamp | Yes | NULL | Set when user completes the final step; set simultaneously with `dismissed_at` on completion |

**Indexes:** None added by this migration (standard Laravel nullable timestamp columns).

**Foreign Keys:** None.

**Migration:** `database/migrations/2026_03_25_000001_add_onboarding_wizard_columns_to_users.php`

**Backfill behavior:** The migration backfills `onboarding_wizard_dismissed_at = now()` for all users who already have at least one entry in `discord_accounts` or `minecraft_accounts`, ensuring existing members never see the wizard.

---

## 3. Models & Relationships

### User (`app/Models/User.php`)

The `User` model carries all onboarding wizard logic. No separate model exists for this feature.

**Relevant fillable fields:**
- `onboarding_wizard_dismissed_at`
- `onboarding_wizard_completed_at`

**Casts:**
- `onboarding_wizard_dismissed_at` => `datetime`
- `onboarding_wizard_completed_at` => `datetime`

**Key Methods:**

- `shouldShowOnboardingWizard(): bool` — Returns `true` when the user is at the Stowaway or Traveler membership level AND both `onboarding_wizard_dismissed_at` and `onboarding_wizard_completed_at` are `null`. Users at Drifter, Resident, Citizen, or above never see the wizard.

- `currentOnboardingStep(): string` — Returns one of `'discord'`, `'waiting'`, `'minecraft'`, or `'complete'`. Step is derived from current user state and activity log entries:
  - **Stowaway, Discord not processed:** returns `'discord'`. "Processed" means either an active Discord account exists OR an activity log entry with action `onboarding_discord_skipped` or `onboarding_discord_disabled` exists.
  - **Stowaway, Discord processed:** returns `'waiting'`.
  - **Traveler, Minecraft not processed:** returns `'minecraft'`. "Processed" means either an active Minecraft account exists OR an activity log entry with action `onboarding_minecraft_skipped` or `onboarding_minecraft_disabled` exists.
  - **Traveler, Minecraft processed:** returns `'complete'`.

**Relationships used by wizard (defined on User):**

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `discordAccounts()` | hasMany | DiscordAccount | Wizard checks `.active().exists()` |
| `minecraftAccounts()` | hasMany | MinecraftAccount | Wizard checks `.active().exists()` |

---

## 4. Enums Reference

### MembershipLevel (`app/Enums/MembershipLevel.php`)

| Case | Value | Label | Notes |
|------|-------|-------|-------|
| `Drifter` | 0 | Drifter | Wizard not shown |
| `Stowaway` | 1 | Stowaway | Wizard active — Discord + waiting steps |
| `Traveler` | 2 | Traveler | Wizard active — Minecraft step |
| `Resident` | 3 | Resident | Wizard not shown (past onboarding) |
| `Citizen` | 4 | Citizen | Wizard not shown (past onboarding) |

**Helper methods:** `label()`, `discordRoleId()`, `minecraftRank()`.

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

The wizard renders inside the `@can('view-community-content')` block on the dashboard. No wizard-specific gate exists; access is controlled purely by the dashboard gate and the `shouldShowOnboardingWizard()` method.

| Gate Name | Who Can Pass | Logic Summary |
|-----------|-------------|---------------|
| `view-community-content` | Any non-brig user | `! $user->in_brig` |
| `link-discord` | Stowaway+ not in brig with parental permission | `isAtLeastLevel(Stowaway) && !in_brig && parent_allows_discord` |
| `link-minecraft-account` | Traveler+ not in brig with parental permission | `isAtLeastLevel(Traveler) && !in_brig && parent_allows_minecraft` |

### Policies

No policies are involved in the onboarding wizard. The wizard component itself contains no `$this->authorize()` call.

### Permissions Matrix

| User Type | Sees Wizard | Discord Step | Waiting Step | Minecraft Step | Can Dismiss |
|-----------|-------------|--------------|--------------|----------------|-------------|
| Drifter | No | No | No | No | N/A |
| Stowaway (not brig) | Yes | Yes | After Discord | No | Yes |
| Traveler (not brig) | Yes | No | No | Yes | Yes |
| Resident+ | No | No | No | No | N/A |
| Any user in brig | No (blocked by `view-community-content`) | No | No | No | N/A |

---

## 6. Routes

The wizard has no dedicated route. It is embedded in the dashboard view and mounted as a Livewire component.

| Method | URL | Middleware | Handler | Route Name |
|--------|-----|-----------|---------|------------|
| GET | /dashboard | auth, verified, ensure-dob | `resources/views/dashboard.blade.php` (view route) | `dashboard` |

The wizard component (`livewire:onboarding.wizard`) is rendered inline within `dashboard.blade.php` when `shouldShowOnboardingWizard()` returns true.

---

## 7. User Interface Components

### Onboarding Wizard
**File:** `resources/views/livewire/onboarding/wizard.blade.php`
**Route:** Embedded in `/dashboard` (route name: `dashboard`)

**Purpose:** Multi-step onboarding card rendered as a full replacement for the normal dashboard Community widgets. Guides new users through Discord and Minecraft account setup.

**Authorization:** No explicit `$this->authorize()` call. Access is gated at the dashboard level by `@can('view-community-content')` and `$authUser->shouldShowOnboardingWizard()`.

**PHP class public properties:**
- `$step` (string): Current step name; set on `mount()` via `currentOnboardingStep()`.
- `$showWelcomeModal` (bool): Controls visibility of the completion welcome modal; defaults to `false`.

**User Actions Available:**

| Action | Method Called | Result |
|--------|--------------|--------|
| Click "Connect Discord" button | (client-side redirect) | Navigates to `settings.discord-account` route |
| Click "Skip for now" (Discord step) | `skipDiscord()` | Logs `onboarding_discord_skipped`; advances to `waiting` step |
| Click "Continue" (disabled Discord) | `continueDisabledDiscord()` | Logs `onboarding_discord_disabled`; advances to `waiting` step |
| Click "Connect Minecraft" button | (client-side redirect) | Navigates to `settings.minecraft-accounts` route |
| Click "Skip for now" (Minecraft step) | `skipMinecraft()` | Logs `onboarding_minecraft_skipped`; calls `complete()` |
| Click "Continue" (disabled Minecraft) | `continueDisabledMinecraft()` | Logs `onboarding_minecraft_disabled`; calls `complete()` |
| Click "Dismiss" | `dismiss()` | Sets `dismissed_at`; logs `onboarding_wizard_dismissed`; redirects to dashboard |
| Click "Get Started" (welcome modal) | `closeWelcomeModal()` | Redirects to dashboard |

**UI Elements:**
- Three step cards: `discord` (indigo-tinted), `waiting` (zinc-tinted), `minecraft` (indigo-tinted)
- Each active step card has a "Dismiss" button in the top-right corner
- Discord step: shows Connect Discord primary button and Skip link, OR disabled-Discord explanation with Continue button (based on `parent_allows_discord`)
- Waiting step: informational card only; no action buttons except Dismiss
- Minecraft step: shows Connect Minecraft primary button and Skip link, OR disabled-Minecraft explanation with Continue button (based on `parent_allows_minecraft`)
- Welcome modal: displayed after completion; contains feature highlights (Join Discussions, Open a Ticket, Explore Community Content) and a link to notification preferences settings

### Dashboard (takeover integration)
**File:** `resources/views/dashboard.blade.php`

When `$authUser->shouldShowOnboardingWizard()` returns true (inside the `@can('view-community-content')` block), the normal dashboard widgets (announcements, setup card, donations, community stories) are replaced entirely by `<livewire:onboarding.wizard />`.

### Sidebar Link
**File:** `resources/views/components/layouts/app/sidebar.blade.php`

Inside the `@auth` guard in the "My Hub" nav group, a "Resume Account Setup" navlist item with the `sparkles` icon pointing to the `dashboard` route is shown when `auth()->user()->shouldShowOnboardingWizard()` returns true. This link disappears once the wizard is dismissed or completed.

---

## 8. Actions (Business Logic)

The wizard does not use dedicated Action classes for its own operations. Business logic is implemented directly in the Volt component methods. The only action used is:

### RecordActivity (`app/Actions/RecordActivity.php`)

**Signature:** `handle($subject, string $action, ?string $description = null, ?User $actor = null): void`

**Step-by-step logic:**
1. Resolves the causer ID from the provided `$actor` or the currently authenticated user.
2. Collects request metadata (IP address, user agent).
3. Creates an `ActivityLog` record with `subject_type`, `subject_id`, `action`, `description`, and `meta`.

**Called by wizard component for these actions:**
- `skipDiscord()` → `RecordActivity::run($user, 'onboarding_discord_skipped', 'Skipped Discord step.')`
- `continueDisabledDiscord()` → `RecordActivity::run($user, 'onboarding_discord_disabled', 'Continued past disabled Discord step.')`
- `skipMinecraft()` → `RecordActivity::run($user, 'onboarding_minecraft_skipped', 'Skipped Minecraft step.')`
- `continueDisabledMinecraft()` → `RecordActivity::run($user, 'onboarding_minecraft_disabled', 'Continued past disabled Minecraft step.')`
- `complete()` → `RecordActivity::run($user, 'onboarding_wizard_completed', 'Completed the onboarding wizard.')`
- `dismiss()` → `RecordActivity::run($user, 'onboarding_wizard_dismissed', 'Dismissed the onboarding wizard.')`

---

## 9. Notifications

Not applicable for this feature. The onboarding wizard sends no notifications to the user or to staff.

---

## 10. Background Jobs

Not applicable for this feature. No jobs are dispatched by the onboarding wizard.

---

## 11. Console Commands & Scheduled Tasks

Not applicable for this feature. No Artisan commands or scheduled tasks are associated with the onboarding wizard.

---

## 12. Services

Not applicable for this feature. No service classes are involved in the onboarding wizard.

---

## 13. Activity Log Entries

All activity log entries are written against the `User` model as both subject and causer (the authenticated user performs the action on themselves).

| Action String | Logged By | Subject Model | Description |
|---------------|-----------|---------------|-------------|
| `onboarding_discord_skipped` | `wizard.skipDiscord()` | User | `'Skipped Discord step.'` |
| `onboarding_discord_disabled` | `wizard.continueDisabledDiscord()` | User | `'Continued past disabled Discord step.'` |
| `onboarding_wizard_dismissed` | `wizard.dismiss()` | User | `'Dismissed the onboarding wizard.'` |
| `onboarding_minecraft_skipped` | `wizard.skipMinecraft()` | User | `'Skipped Minecraft step.'` |
| `onboarding_minecraft_disabled` | `wizard.continueDisabledMinecraft()` | User | `'Continued past disabled Minecraft step.'` |
| `onboarding_wizard_completed` | `wizard.complete()` | User | `'Completed the onboarding wizard.'` |

**Important:** `currentOnboardingStep()` queries the `activity_logs` table for these action strings to determine whether a step has been processed. This means the activity log is load-bearing for step state — deleting these entries would cause the wizard to revert to an earlier step.

---

## 14. Data Flow Diagrams

### Stowaway: Skip Discord → Waiting

```
User clicks "Skip for now" on discord step card
  -> Livewire wire:click="skipDiscord" (no route, inline component)
    -> wizard.skipDiscord()
      -> RecordActivity::run($user, 'onboarding_discord_skipped', 'Skipped Discord step.')
        -> ActivityLog record created
      -> $this->step = $user->fresh()->currentOnboardingStep()
        -> currentOnboardingStep() queries ActivityLog for 'onboarding_discord_skipped'
        -> finds entry -> returns 'waiting'
      -> $this->step = 'waiting'
    -> Blade re-renders: shows waiting card
```

### Stowaway: Parent-Disabled Discord → Waiting

```
User (parent_allows_discord = false) clicks "Continue" on discord step card
  -> Livewire wire:click="continueDisabledDiscord"
    -> wizard.continueDisabledDiscord()
      -> RecordActivity::run($user, 'onboarding_discord_disabled', 'Continued past disabled Discord step.')
        -> ActivityLog record created
      -> $this->step = $user->fresh()->currentOnboardingStep() -> 'waiting'
    -> Blade re-renders: shows waiting card
```

### Traveler: Skip Minecraft → Complete → Welcome Modal

```
User clicks "Skip for now" on minecraft step card
  -> Livewire wire:click="skipMinecraft"
    -> wizard.skipMinecraft()
      -> RecordActivity::run($user, 'onboarding_minecraft_skipped', 'Skipped Minecraft step.')
        -> ActivityLog record created
      -> wizard.complete()
        -> $user->update(['onboarding_wizard_completed_at' => now(), 'onboarding_wizard_dismissed_at' => now()])
        -> RecordActivity::run($user, 'onboarding_wizard_completed', 'Completed the onboarding wizard.')
        -> $this->step = 'complete'
        -> $this->showWelcomeModal = true
    -> Blade re-renders: step is 'complete' (no card rendered), welcome modal shown
User clicks "Get Started" in modal
  -> Livewire wire:click="closeWelcomeModal"
    -> wizard.closeWelcomeModal()
      -> $this->redirect(route('dashboard'))
    -> Normal dashboard loads (shouldShowOnboardingWizard() now returns false)
```

### Dismiss from Any Step

```
User clicks "Dismiss" button on any wizard step card
  -> Livewire wire:click="dismiss"
    -> wizard.dismiss()
      -> $user->update(['onboarding_wizard_dismissed_at' => now()])
      -> RecordActivity::run($user, 'onboarding_wizard_dismissed', 'Dismissed the onboarding wizard.')
      -> $this->redirect(route('dashboard'))
    -> Dashboard reloads: shouldShowOnboardingWizard() returns false -> normal widgets shown
```

### Auto-Complete on Mount (Traveler with Minecraft Already Linked)

```
Traveler who already has active Minecraft accounts loads dashboard
  -> wizard mounts via <livewire:onboarding.wizard />
    -> wizard.mount()
      -> $this->step = Auth::user()->currentOnboardingStep()
        -> currentOnboardingStep() finds active minecraft account -> returns 'complete'
      -> $this->step === 'complete' -> wizard.complete() called automatically
        -> user->update(['onboarding_wizard_completed_at' => now(), 'onboarding_wizard_dismissed_at' => now()])
        -> RecordActivity::run($user, 'onboarding_wizard_completed', ...)
        -> $this->showWelcomeModal = true
    -> Welcome modal shown immediately on page load
```

---

## 15. Configuration

Not applicable for this feature. No environment variables or config values are specific to the onboarding wizard.

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/Models/UserOnboardingWizardTest.php` | 16 tests | User model method behavior: `shouldShowOnboardingWizard()` and `currentOnboardingStep()` for all membership levels and states; migration backfill logic |
| `tests/Feature/Onboarding/WizardDashboardTakeoverTest.php` | 20 tests | Dashboard takeover behavior, Discord step UI and interactions, parent-disabled Discord, dismiss from Discord and waiting steps |
| `tests/Feature/Onboarding/WizardWaitingStepTest.php` | 10 tests | Waiting step visibility conditions, card content, absence of action buttons, Traveler bypass of waiting step, dismiss from waiting step |
| `tests/Feature/Onboarding/WizardMinecraftStepTest.php` | 14 tests | Minecraft step visibility, Connect Minecraft link, skip Minecraft, parent-disabled Minecraft, completion (both timestamps set), activity log on completion, auto-complete on mount, welcome modal content |

### Test Case Inventory

**`UserOnboardingWizardTest.php`**
- `shouldShowOnboardingWizard returns true for a Stowaway with no accounts and no dismissed/completed`
- `shouldShowOnboardingWizard returns true for a Traveler with no dismissed/completed`
- `shouldShowOnboardingWizard returns false for a Drifter`
- `shouldShowOnboardingWizard returns false for a Resident (already past onboarding)`
- `shouldShowOnboardingWizard returns false when dismissed_at is set`
- `shouldShowOnboardingWizard returns false when completed_at is set`
- `shouldShowOnboardingWizard returns false for a user whose dismissed_at was set by backfill`
- `currentOnboardingStep returns discord for a Stowaway with no Discord accounts`
- `currentOnboardingStep returns discord for a Stowaway with parent_allows_discord false`
- `currentOnboardingStep returns waiting for a Stowaway who has linked Discord`
- `currentOnboardingStep returns waiting for a Stowaway who skipped Discord`
- `currentOnboardingStep returns waiting for a Stowaway who continued past disabled Discord`
- `currentOnboardingStep returns minecraft for a Traveler with no Minecraft accounts`
- `currentOnboardingStep returns complete for a Traveler who has linked Minecraft`
- `currentOnboardingStep returns minecraft for a Traveler with parent_allows_minecraft false`
- `currentOnboardingStep returns complete for a Traveler who skipped Minecraft`
- `currentOnwardingStep returns complete for a Traveler who continued past disabled Minecraft` *(note: typo in test name)*
- `backfill: user with linked Discord account has dismissed_at set`
- `backfill: user with no linked accounts is left with dismissed_at null`

**`WizardDashboardTakeoverTest.php`**
- `Stowaway with no Discord and no dismissed_at sees the wizard instead of normal dashboard`
- `user with dismissed_at set sees the normal dashboard`
- `wizard mounts on discord step for a Stowaway with no Discord account`
- `Discord step shows Connect Discord button and Skip for now option`
- `Connect Discord button points to the discord account settings route`
- `Skip for now records onboarding_discord_skipped in activity log`
- `Skipping Discord advances step to waiting`
- `parent-disabled Discord state shows explanation and Continue button`
- `Continue on parent-disabled Discord records onboarding_discord_disabled in activity log`
- `Continue on parent-disabled Discord advances step to waiting`
- `Dismiss button is present on the Discord step`
- `Dismissing sets onboarding_wizard_dismissed_at on the user`
- `Dismissing records onboarding_wizard_dismissed in activity log`
- `after dismissal, the normal dashboard widgets are shown`
- `Dismiss button is present on the waiting step`

**`WizardWaitingStepTest.php`**
- `Stowaway who linked Discord sees the waiting step`
- `Stowaway who skipped Discord sees the waiting step`
- `Stowaway who continued past disabled Discord sees the waiting step`
- `waiting card explains the approval process`
- `waiting card has no action button besides Dismiss`
- `Traveler with no Minecraft account does not see the waiting step`
- `Traveler does not see the waiting step even after having linked Discord`
- `Dismiss from the waiting step sets dismissed_at`

**`WizardMinecraftStepTest.php`**
- `Traveler with no Minecraft account sees the Minecraft step card`
- `Connect Minecraft button points to the minecraft account settings route`
- `Minecraft step shows Skip for now option`
- `Skip for now on Minecraft step records onboarding_minecraft_skipped`
- `Skip for now on Minecraft step triggers completion`
- `parent-disabled Minecraft state shows explanation and Continue button`
- `Continue on parent-disabled Minecraft records onboarding_minecraft_disabled`
- `Continue on parent-disabled Minecraft triggers completion`
- `completion sets both completed_at and dismissed_at`
- `completion records onboarding_wizard_completed in activity log`
- `wizard auto-completes when Traveler already has Minecraft linked`
- `welcome modal is shown after completion`
- `welcome modal contains feature highlights and notification settings link`
- `welcome modal is NOT shown on dismissal`
- `sidebar shows Resume Account Setup link when wizard is active`
- `sidebar does not show Resume Account Setup link after wizard is dismissed`
- `sidebar does not show Resume Account Setup link after wizard is completed`

### Coverage Gaps

- **No test for a Stowaway who connects Discord directly via settings** then returns to the dashboard — the wizard would need to re-check step state via `mount()`, but there is no test covering the transition from `discord` to `waiting` via actual account creation (only via the skip path).
- **No test for the `complete()` method path triggered through `continueDisabledMinecraft()`** asserting that both timestamps are set (only `skipMinecraft()` is used for that assertion).
- **No test verifying that a brig'd user never sees the wizard** (the `view-community-content` gate blocks it, but this is not explicitly tested for the wizard specifically).
- **No test for the sidebar "Resume Account Setup" link on the Stowaway/waiting step** (only tested for Stowaway with no Discord on the dashboard route, not for the waiting step state).

---

## 17. File Map

**Models:**
- `app/Models/User.php` — contains `shouldShowOnboardingWizard()`, `currentOnboardingStep()`, and the two timestamp casts

**Enums:**
- `app/Enums/MembershipLevel.php` — `Stowaway` and `Traveler` cases gate wizard visibility

**Actions:**
- `app/Actions/RecordActivity.php` — sole action used; called six times within the wizard component

**Policies:** None specific to this feature.

**Gates:** `app/Providers/AuthServiceProvider.php` — gates used: `view-community-content`, `link-discord`, `link-minecraft-account`

**Notifications:** None.

**Jobs:** None.

**Services:** None.

**Controllers:** None specific to this feature.

**Volt Components:**
- `resources/views/livewire/onboarding/wizard.blade.php`

**Routes:**
- `dashboard` (`GET /dashboard`) — the only route; wizard is embedded here

**Migrations:**
- `database/migrations/2026_03_25_000001_add_onboarding_wizard_columns_to_users.php`

**Console Commands:** None.

**Tests:**
- `tests/Feature/Models/UserOnboardingWizardTest.php`
- `tests/Feature/Onboarding/WizardDashboardTakeoverTest.php`
- `tests/Feature/Onboarding/WizardWaitingStepTest.php`
- `tests/Feature/Onboarding/WizardMinecraftStepTest.php`

**Views (integration points):**
- `resources/views/dashboard.blade.php` — mounts `livewire:onboarding.wizard` when `shouldShowOnboardingWizard()` is true
- `resources/views/components/layouts/app/sidebar.blade.php` — shows "Resume Account Setup" nav item when `shouldShowOnboardingWizard()` is true

**Config:** No feature-specific config keys.

---

## 18. Known Issues & Improvement Opportunities

- **Typo in test name:** `tests/Feature/Models/UserOnboardingWizardTest.php` line 164 reads `currentOnwardingStep` (missing "b") instead of `currentOnboardingStep`. This is cosmetic and does not affect test execution.

- **Activity log is load-bearing for step state:** `currentOnboardingStep()` queries the `activity_logs` table to determine whether the Discord or Minecraft step was processed. If activity log entries are deleted (e.g., during a data cleanup or admin action), affected users would see the wizard revert to an earlier step. This is an implicit dependency that could surprise future developers.

- **No authorization check in wizard component:** The wizard component does not call `$this->authorize()`. It relies solely on the dashboard template's `@can('view-community-content')` check and the `shouldShowOnboardingWizard()` conditional. An authenticated user who directly calls Livewire actions (e.g., via a crafted request) on the component while not meeting wizard preconditions would still be able to trigger `dismiss()`, `skipDiscord()`, etc. This is low-risk (actions only write to the user's own record) but is worth noting.

- **`shouldShowOnboardingWizard()` called multiple times per request:** The sidebar and dashboard both call this method independently on the same request. There is no memoization. For most requests this involves a simple attribute null-check plus level comparison and is negligible, but the `currentOnboardingStep()` method (called on `mount()`) does hit the database twice (discord accounts + activity log, or minecraft accounts + activity log). No caching is applied.

- **Waiting step has no auto-advance:** When a Stowaway is promoted to Traveler, the waiting step card simply disappears on next page load (because `shouldShowOnboardingWizard()` re-evaluates). There is no real-time push or polling — the user must refresh or navigate to see the Minecraft step. This is acceptable UX for the current scale but could be improved with a Livewire polling directive.

- **Backfill sets only `dismissed_at`, not `completed_at`:** Existing users who had linked accounts before the migration was run have `dismissed_at` set but `completed_at` left null. This means `shouldShowOnboardingWizard()` correctly suppresses the wizard for them (it checks both), but if any future code differentiates between "completed" and "dismissed" users, these backfilled users would appear as non-completers. A future migration could set `completed_at` for these users as well.

- **Welcome modal redirect goes to `/dashboard` without step context:** After `closeWelcomeModal()`, the user is simply redirected to the dashboard, which now shows normal widgets. There is no deep-link to a specific first action (e.g., opening a ticket or joining a discussion). The modal content suggests next steps but does not guide the user there automatically.
