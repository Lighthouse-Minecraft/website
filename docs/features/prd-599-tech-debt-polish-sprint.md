# PRD #599: Tech Debt & Polish Sprint — Technical Reference

## Overview

PRD #599 encompasses six interconnected features focused on improving the robustness, consistency, and user experience of the Lighthouse Website. These features address long-standing technical debt, enhance authorization controls, streamline workflows, and tighten integrations between the core brig system, Discord account management, and staff tracking. Each feature is independently deployable but designed to work cohesively, with particular emphasis on the permanent brig option and Discord role synchronization during disciplinary actions. The work includes updates to modals, validation logic, configuration, and comprehensive test coverage across Livewire components, actions, and API integration points.

## Feature 1: Permanent Brig Option

### Overview
Users with the `Brig Warden` role can now place a user in the brig with or without an automatic release timer. The modal includes a "Permanent (no expiry)" checkbox that, when checked, disables the optional days input and allows placing a user in the brig with no expiration date.

### How It Works

**Component:** `resources/views/livewire/users/display-basic-details.blade.php`

**State Properties:**
- `$brigActionPermanent` (bool): Tracks whether the permanent checkbox is checked
- `$brigActionDays` (int|null): Number of days until auto-release (only used when permanent is false)
- `$brigActionReason` (string): Required reason for the brig placement (minimum 5 characters)

**Key Methods:**

- **`openPutInBrigModal()`**: Prepares the modal by resetting form state and checking authorization via `'put-in-brig'` gate.
  ```php
  public function openPutInBrigModal(): void
  {
      if (! Auth::user()->can('put-in-brig')) {
          return;
      }
      $this->brigActionReason = '';
      $this->brigActionDays = null;
      $this->brigActionPermanent = false;
      Flux::modal('profile-put-in-brig-modal')->show();
  }
  ```

- **`confirmPutInBrig()`**: Validates form input and executes the brig placement. Validation differs based on permanent status:
  - When `$brigActionPermanent === true`: `brigActionDays` becomes `nullable` (no validation requirement)
  - When `$brigActionPermanent === false`: `brigActionDays` is `nullable|integer|min:1|max:365` (only validated if a value is provided)
  
  Expiration logic:
  ```php
  $expiresAt = (! $this->brigActionPermanent && $this->brigActionDays)
      ? now()->addDays((int) $this->brigActionDays)
      : null;
  ```
  If permanent is checked or no days are provided, `$expiresAt` is `null`, creating a permanent brig entry.

### Modal UI

**Location:** `profile-put-in-brig-modal` (lines 1017–1044)

- Shows a checkbox "Permanent (no expiry)" that toggles the days input visibility
- Days input uses Alpine.js `x-show="!$wire.brigActionPermanent"` to conditionally display only when permanent is unchecked
- Days input is disabled when permanent is checked (`:disabled="$brigActionPermanent"`)

### Authorization
Gate `'put-in-brig'` requires the user to have the `Brig Warden` role:
```php
Gate::define('put-in-brig', function ($user) {
    return $user->hasRole('Brig Warden');
});
```

### Data Flow
1. Admin/Brig Warden opens the modal via `openPutInBrigModal()`
2. Fills in reason and optionally checks permanent or sets days
3. Calls `confirmPutInBrig()` which validates and calls `PutUserInBrig::run()`
4. User record is updated with `in_brig=true`, `brig_expires_at` (null if permanent), and related fields
5. Discord roles are synced (see Feature 6)
6. Minecraft accounts are banned
7. Activity log entry is recorded with expiration status

### Related Action Classes
- `PutUserInBrig::handle()` — Executes brig placement with Discord and Minecraft side effects (lines 32–119)

## Feature 2: Profile Edit Restrictions

### Overview
When editing a user profile, the editable fields depend on the role of the person editing. Non-manager users can only edit the user's name; admins and User - Manager role users can edit all fields (name, email, date of birth, parent email).

### Authorization
The component checks authorization via:
```php
$this->authorize('update', $this->user);  // In openEditUserModal() and saveEditUser()
```

### canEditAllFields() Method

**Location:** Line 412 in `display-basic-details.blade.php`

```php
public function canEditAllFields(): bool
{
    return Auth::user()->isAdmin() || Auth::user()->hasRole('User - Manager');
}
```

Returns `true` only if the current user is an admin or has the `User - Manager` role. Everyone else can only edit the name field.

### Restricted Fields

When `canEditAllFields()` returns `false`, the following fields are read-only (displayed as plain text):
- **Email** — Shows current email with message "Contact staff to update your email address."
- **Date of Birth** — Displayed as read-only text if set
- **Parent Email** — Displayed as read-only text if set

When `canEditAllFields()` returns `true`, all fields are editable inputs.

### Data Flow

**Modal:** `profile-edit-user-modal`

1. Admin/manager opens modal via `openEditUserModal()` (line 398)
2. Modal initializes `editUserData` with current user values (lines 402–407)
3. Template conditionally renders inputs or read-only text based on `$this->canEditAllFields()`
4. On save, `saveEditUser()` validates based on role:
   - Always validates `editUserData.name` as required, string, max 32 characters
   - If `canEditAllFields()` is true, adds validation for email, date_of_birth, parent_email
5. Updates only the permitted fields on the user record
6. If parent email changed and `canEditAllFields()`, runs `LinkParentByEmail::run()`
7. Records activity as `'update_profile'`

### Validation Rules

```php
$rules = [
    'editUserData.name' => ['required', 'string', 'max:32'],
];

if ($this->canEditAllFields()) {
    $rules['editUserData.email'] = ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user->id)];
    $rules['editUserData.date_of_birth'] = ['nullable', 'date', 'before:today'];
    $rules['editUserData.parent_email'] = ['nullable', 'email'];
}
```

### Related Methods
- `openEditUserModal()` (line 398) — Prepares modal data and shows it
- `saveEditUser()` (line 417) — Validates and persists changes
- `LinkParentByEmail::run()` (line 451) — Action to link/relink parent when email is updated

## Feature 3: Discord OAuth Return URL

### Overview
When a user initiates Discord account linking from the onboarding wizard, they are redirected back to the dashboard after successful linking instead of to the settings page. This is tracked via a `discord_oauth_from` session key set during the OAuth redirect.

### Session Key: discord_oauth_from

**Location:** `app/Http/Controllers/DiscordAuthController.php`

**Redirect Method (lines 13–24):**
```php
public function redirect(Request $request)
{
    Gate::authorize('link-discord');

    if ($request->get('from') === 'onboarding') {
        session(['discord_oauth_from' => 'onboarding']);
    }

    return Socialite::driver('discord')
        ->scopes(['identify', 'guilds.join'])
        ->redirect();
}
```

The session key is only set when the `from` query parameter is exactly `'onboarding'`. No other values are accepted.

**Callback Method (lines 26–60):**
```php
$from = session()->pull('discord_oauth_from');  // Retrieves and clears the key
$successRoute = $from === 'onboarding' ? 'dashboard' : 'settings.discord-account';
$errorRoute = $from === 'onboarding' ? 'dashboard' : 'settings.discord-account';
```

The `session()->pull()` method retrieves the value and removes it from the session in one operation. If `$from` is `'onboarding'`, both success and error redirects go to `route('dashboard')`; otherwise they go to `route('settings.discord-account')`.

### Onboarding Wizard Integration

**Location:** `resources/views/livewire/onboarding/wizard.blade.php` (line 114)

The Discord step button now links directly to the OAuth redirect with the `from` parameter:
```blade
<flux:button href="{{ route('auth.discord.redirect', ['from' => 'onboarding']) }}" variant="primary">
    Connect Discord
</flux:button>
```

Previously, it may have linked to a settings page; now it goes through the OAuth flow and returns to the dashboard to continue onboarding.

### Authorization
The `'link-discord'` gate is checked at the start of both methods:
```php
Gate::authorize('link-discord');
```

Gate definition (line 91–93 in AuthServiceProvider):
```php
Gate::define('link-discord', function ($user) {
    return $user->isAtLeastLevel(MembershipLevel::Stowaway) && ! $user->in_brig && $user->parent_allows_discord;
});
```

## Feature 4: Finance Report PDF Headers

### Overview
Finance reports now include a print-only header that appears when the page is printed or exported to PDF. The header displays the organization logo, report title (based on the active tab), and generation timestamp.

### Print Header Location

**File:** `resources/views/livewire/finance/reports.blade.php` (lines 547–571)

```blade
{{-- Print header (only visible when printing) --}}
<div class="hidden print:block mb-6">
    @php
        $tabLabels = [
            'activities'   => 'Statement of Activities',
            'ledger'       => 'General Ledger',
            'trial'        => 'Trial Balance',
            'balance-sheet'=> 'Balance Sheet',
            'cash-flow'    => 'Cash Flow Statement',
            'variance'     => 'Budget vs. Actual',
        ];
        $reportTitle = $tabLabels[$activeTab] ?? 'Financial Report';
    @endphp

    <div style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">
        <img src="{{ asset('img/LighthouseMC_Logo.png') }}" alt="{{ config('app.name') }}" style="width:48px; height:48px; border-radius:6px;" />
        <div>
            <div style="font-size:18px; font-weight:700; line-height:1.2;">{{ config('app.name') }}</div>
            <div style="font-size:14px; font-weight:600; color:#374151;">{{ $reportTitle }}</div>
        </div>
    </div>
    <div style="border-top:1px solid #d1d5db; padding-top:6px;">
        <span style="font-size:12px; color:#6b7280;">Generated {{ now()->format('F j, Y') }}</span>
    </div>
</div>
```

### Inline Styles Rationale

All CSS is written inline (not as classes) to ensure styles are preserved when the report is printed or exported to PDF. PDF export engines often strip out Tailwind CSS classes, making inline styles essential for consistent appearance in PDF output. Key styled elements:

| Element | Style | Rationale |
|---------|-------|-----------|
| Container | `display:flex; align-items:center; gap:12px; margin-bottom:8px;` | Horizontal layout with logo and text side-by-side |
| Logo | `width:48px; height:48px; border-radius:6px;` | Consistent square logo with subtle rounding |
| Organization Name | `font-size:18px; font-weight:700; line-height:1.2;` | Large, bold header text |
| Report Title | `font-size:14px; font-weight:600; color:#374151;` | Smaller subtitle, gray text (#374151 = gray-700) |
| Divider | `border-top:1px solid #d1d5db;` | Subtle gray line separator |
| Timestamp | `font-size:12px; color:#6b7280;` | Small, gray (#6b7280 = gray-500) timestamp |

### Tab-to-Title Mapping

The header dynamically displays the report title based on the active tab:

| Active Tab | Header Title |
|-----------|--------------|
| `activities` | Statement of Activities |
| `ledger` | General Ledger |
| `trial` | Trial Balance |
| `balance-sheet` | Balance Sheet |
| `cash-flow` | Cash Flow Statement |
| `variance` | Budget vs. Actual |
| (unknown/default) | Financial Report |

### Visibility Control

- **Screen display:** Hidden via `class="hidden"`; only the print UI is visible (tabs, filters, buttons)
- **Print display:** Shown via `class="print:block"`; only the header and report tables are visible
- **Print button:** Line 542–544 in the same file provides a "Print / PDF" button that triggers browser print dialog

## Feature 5: Discord Brig Role Sync

### Overview
When a user is placed in or released from the brig, their Discord account(s) are assigned or removed from a special "In Brig" role. This is configured via the environment variable `DISCORD_ROLE_IN_BRIG` and is applied separately from the existing role-stripping logic.

### Configuration

**File:** `config/lighthouse.php` (line 126)

```php
'discord' => [
    'roles' => [
        // ... other roles ...
        'in_brig' => env('DISCORD_ROLE_IN_BRIG'),
    ],
],
```

**Environment Variable:** `DISCORD_ROLE_IN_BRIG`

This should be set to a Discord role ID (string). If not set, the role sync is skipped gracefully (no error is raised).

### PutUserInBrig Action

**File:** `app/Actions/PutUserInBrig.php` (lines 72–101)

When a user is placed in the brig, Discord side effects occur in this order:

1. **Retrieve the brig role ID:**
   ```php
   $brigRoleId = (string) config('lighthouse.discord.roles.in_brig', '');
   ```

2. **Iterate over active Discord accounts** (status = Active or ParentDisabled):
   ```php
   foreach ($target->discordAccounts()->whereIn('status', [DiscordAccountStatus::Active, DiscordAccountStatus::ParentDisabled])->get() as $discordAccount) {
   ```

3. **Strip all managed roles** (try/catch with warning log):
   ```php
   try {
       $discordApi->removeAllManagedRoles($discordAccount->discord_user_id);
   } catch (\Exception $e) {
       \Illuminate\Support\Facades\Log::warning('Failed to strip Discord roles during brig', [...]);
   }
   ```

4. **Assign the In Brig role** (only if `$brigRoleId !== ''`):
   ```php
   if ($brigRoleId !== '') {
       try {
           $discordApi->addRole($discordAccount->discord_user_id, $brigRoleId);
       } catch (\Exception $e) {
           \Illuminate\Support\Facades\Log::warning('Failed to assign In Brig Discord role', [...]);
       }
   }
   ```

5. **Mark account as brigged:**
   ```php
   $discordAccount->status = DiscordAccountStatus::Brigged;
   $discordAccount->save();
   ```

### ReleaseUserFromBrig Action

**File:** `app/Actions/ReleaseUserFromBrig.php` (lines 68–95)

When a user is released from the brig, the In Brig role is removed:

1. **Retrieve the brig role ID:**
   ```php
   $brigRoleId = (string) config('lighthouse.discord.roles.in_brig', '');
   ```

2. **Iterate over brigged Discord accounts:**
   ```php
   foreach ($target->discordAccounts()->where('status', DiscordAccountStatus::Brigged)->get() as $discordAccount) {
   ```

3. **Remove the In Brig role** (only if `$brigRoleId !== ''`):
   ```php
   if ($brigRoleId !== '') {
       try {
           $discordApi->removeRole($discordAccount->discord_user_id, $brigRoleId);
       } catch (\Exception $e) {
           Log::warning('Failed to remove In Brig Discord role on release', [...]);
       }
   }
   ```

4. **Restore account status** based on parent toggle:
   ```php
   $discordRestoreStatus = $target->parent_allows_discord
       ? DiscordAccountStatus::Active
       : DiscordAccountStatus::ParentDisabled;
   $discordAccount->status = $discordRestoreStatus;
   $discordAccount->save();
   ```

5. **Sync Discord roles** if restoring to Active:
   ```php
   if ($discordRestoreStatus === DiscordAccountStatus::Active) {
       try {
           SyncDiscordRoles::run($target);
       } catch (\Exception $e) {
           Log::error('Failed to sync Discord roles on brig release', [...]);
       }
       // Also sync staff roles if applicable
   }
   ```

### Error Handling (Try/Catch Behavior)

Both actions use a **try/catch with logging** pattern rather than throwing exceptions. This ensures that:

- Transient Discord API failures do not fail the entire brig operation
- All errors are logged with context (user ID, Discord user ID, error message)
- The brig placement or release completes even if Discord role operations fail
- Staff can see what happened in the logs for debugging

**Log Levels:**
- `Log::warning()` for role operation failures (expected to be recoverable)
- `Log::error()` for account status/sync failures (more serious issues)

## Feature 6: Staff Activity Card

### Overview
A new Livewire component displays a summary of a staff member's activity over the last 3 months: meeting attendance, staff reports filed/missed, and assigned tickets. The component is only shown on staff member profiles and is gated by authorization.

### Component Location

**File:** `resources/views/livewire/users/staff-activity-card.blade.php`

**Component Class:** Lines 1–122 (PHP) define the Livewire Volt component.

### Authorization Gate

**Gate Name:** `'view-staff-activity'`

**Location:** `app/Providers/AuthServiceProvider.php` (lines 263–271)

```php
Gate::define('view-staff-activity', function ($user, $targetUser) {
    // The staff member themselves
    if ($user->id === $targetUser->id) {
        return true;
    }

    // Command officers and department leads (Officer rank in any department)
    return $user->isAtLeastRank(StaffRank::Officer);
});
```

Authorization allows viewing if:
1. The user is viewing their own activity card, OR
2. The user is at least an Officer rank (across any department)

### Data Displayed

The card displays four key metrics calculated from the last 3 months of data:

#### 1. Meeting Attendance
- **Query:** Completed staff meetings in the last 3 months
- **Display:** `{{ $this->attendanceCount }} / {{ $this->totalMeetings3mo }}` meetings
- **Interactive:** Clicking the count opens a modal with a table of all meetings and attendance status

#### 2. Staff Reports
- **Filed:** Count of submitted meeting reports for staff meetings in the last 3 months
- **Missed:** Calculated as `totalMeetings3mo - reportsFiled`
- **Display:** Shows "X filed" and optionally a "Y missed" badge (only if missed > 0)

#### 3. Tickets
- **Open:** Count of Open and Pending tickets assigned to the staff member
- **Closed:** Count of Resolved and Closed tickets assigned to the staff member
- **Display:** Two badges showing "X open" and "Y closed"

### Component Properties & Methods

**Locked Properties:**
- `$userId` — The staff member's user ID (locked to prevent tampering)

**Computed Properties:**
- `$user` — Resolves the User model by `$userId`
- `$meetingAttendance` — Collection of attendance records with meeting details
- `$attendanceCount` — Number of meetings attended in last 3 months
- `$totalMeetings3mo` — Total number of staff meetings in last 3 months
- `$reportsFiled` — Count of submitted reports
- `$reportsMissed` — Calculated as `totalMeetings3mo - reportsFiled` (min 0)
- `$openTickets` — Count of Open/Pending tickets
- `$closedTickets` — Count of Resolved/Closed tickets

**Methods:**
- `mount(User $user)` — Authorizes access and sets `$userId`
- `openAttendanceModal()` — Shows the attendance detail modal

### Modal Behavior

**Modal Name:** `staff-attendance-modal-{{ $this->userId }}`

The attendance modal displays:
- Meeting title
- Meeting date (M j, Y format)
- Attendance status badge:
  - "Not on Record" — User not in pivot table
  - "Attended" — Pivot exists and `attended=true`
  - "Absent" — Pivot exists and `attended=false`

### Time Window

All metrics are calculated from the last 3 months (hardcoded via `now()->subMonths(3)`). The badge on the card header reads "Last 3 months".

### Display Integration

**Location:** `resources/views/users/show.blade.php` (lines 16–22)

The card is conditionally shown only if:
1. The user has a staff position (`$user->staffPosition` is not null)
2. Authorization passes (gate `'view-staff-activity'`)

```blade
@if($user->staffPosition)
    @can('view-staff-activity', $user)
        <div class="my-6">
            <livewire:users.staff-activity-card :user="$user" />
        </div>
    @endcan
@endif
```

## Authorization & Gates Summary

| Gate Name | Definition | Purpose |
|-----------|-----------|---------|
| `put-in-brig` | User has `Brig Warden` role | Allow placing users in brig |
| `release-from-brig` | User has `Brig Warden` role | Allow releasing users from brig |
| `link-discord` | Stowaway+, not in brig, parent_allows_discord | Allow Discord account linking |
| `view-staff-activity` | Self-view OR Officer+ rank | Allow viewing staff activity metrics |

### Authorization in Components

- **display-basic-details.blade.php:**
  - `openPutInBrigModal()`: checks `'put-in-brig'`
  - `confirmPutInBrig()`: checks `'put-in-brig'`
  - `openReleaseFromBrigModal()`: checks `'release-from-brig'`
  - `confirmReleaseFromBrig()`: checks `'release-from-brig'`
  - `openEditUserModal()`: checks `'update'` policy (model policy)
  - `saveEditUser()`: checks `'update'` policy

- **staff-activity-card.blade.php:**
  - `mount()`: checks `'view-staff-activity'` gate

## Test Coverage

### PermaBrigModalTest.php
**File:** `tests/Feature/Livewire/PermaBrigModalTest.php`

Tests the permanent brig checkbox behavior:

1. **it('shows permanent checkbox in brig modal')** — Verifies the Permanent checkbox is visible in the component
2. **it('places user in permanent brig when permanent checkbox is checked')** — Checks that with `brigActionPermanent=true`, `brig_expires_at` is null
3. **it('places user in timed brig when permanent is unchecked and days are set')** — Verifies that with `brigActionPermanent=false` and `brigActionDays=7`, an expiration timestamp is set
4. **it('places user in brig with no expiry when permanent is unchecked and no days set')** — Checks that with `brigActionPermanent=false` and `brigActionDays=null`, no expiration is set

### ProfileEditRestrictionsTest.php
**File:** `tests/Feature/Livewire/ProfileEditRestrictionsTest.php`

Tests field edit restrictions based on user role:

1. **it('regular user editing own profile can only update username')** — Non-managers can only change name; email, DOB, parent_email remain unchanged
2. **it('user-manager can edit all fields')** — Users with `User - Manager` role can update all fields
3. **it('admin can edit all fields on any profile')** — Admins can update all fields
4. **it('regular user edit modal shows protected fields as read-only text')** — Verifies that non-managers see "Contact staff to update your email address." message
5. **it('user-manager edit modal shows protected fields as inputs')** — Verifies that managers do NOT see the contact-staff message and can edit

### OnboardingDiscordFlowTest.php
**File:** `tests/Feature/Discord/OnboardingDiscordFlowTest.php`

Tests Discord OAuth return flow from onboarding:

1. **it('stores onboarding origin in session when from=onboarding param is passed')** — Verifies `session('discord_oauth_from')` is set to 'onboarding'
2. **it('does not store onboarding origin when from param is absent')** — Verifies no session key is set without the param
3. **it('does not store origin for unrecognised from values')** — Verifies only 'onboarding' is accepted; 'evil' or other values are ignored
4. **it('callback redirects to dashboard on success when from=onboarding was stored')** — Verifies successful OAuth callback redirects to 'dashboard' route
5. **it('callback redirects to settings on success without onboarding session')** — Verifies callback without session key redirects to 'settings.discord-account'
6. **it('callback redirects to dashboard on OAuth error when from=onboarding')** — Verifies error during OAuth redirects to 'dashboard' when onboarding
7. **it('wizard discord step links directly to OAuth redirect with onboarding param')** — Verifies the button in wizard uses the correct href with `from=onboarding`
8. **it('wizard discord step does not link to settings page')** — Verifies the wizard button does NOT link to settings

### ReportsTest.php
**File:** `tests/Feature/Finance/ReportsTest.php` (last ~40 lines)

Tests the print header functionality:

1. **it('print header includes the org name')** — Verifies `config('app.name')` appears in the component output
2. **it('print header includes the Lighthouse logo')** — Verifies the logo path 'LighthouseMC_Logo.png' is present
3. **it('print header shows report title for active tab')** — Sets `activeTab='ledger'` and verifies 'General Ledger' appears
4. **it('print header shows Statement of Activities for default tab')** — Verifies default tab title is 'Statement of Activities'

### DiscordBrigIntegrationTest.php
**File:** `tests/Feature/Discord/DiscordBrigIntegrationTest.php`

Tests Discord role sync during brig operations:

1. **it('sets discord accounts to brigged when user is put in brig')** — Verifies Discord account status changes to `Brigged`
2. **it('calls removeAllManagedRoles when brigging')** — Verifies the API call to strip existing roles is made
3. **it('restores discord accounts to active when released from brig')** — Verifies Discord account status changes back to `Active`
4. **it('syncs discord permissions when released from brig')** — Verifies `SyncDiscordRoles::run()` is called on release
5. **it('assigns the In Brig Discord role when user is put in brig')** — Verifies `addRole()` is called with the brig role ID from config
6. **it('removes the In Brig Discord role when user is released from brig')** — Verifies `removeRole()` is called with the brig role ID
7. **it('silently skips In Brig role sync when no Discord account is linked')** — Verifies no API calls occur if user has no Discord accounts
8. **it('does not assign In Brig role when config key is not set')** — Verifies role assignment is skipped if `DISCORD_ROLE_IN_BRIG` is null or empty

### StaffActivityCardTest.php
**File:** `tests/Feature/Livewire/StaffActivityCardTest.php`

Tests authorization and data display of the staff activity card:

**Authorization Tests:**
1. **it('shows card on staff profile page to the staff member themselves')** — Staff member can view their own card
2. **it('shows card on staff profile page to an officer')** — Officer+ can view any staff member's card
3. **it('does not show card on non-staff profile page')** — Card is hidden if user has no staff position
4. **it('denies crew members from viewing another staff member activity card')** — Crew members (non-officers) are forbidden
5. **it('denies regular members from viewing the staff activity card')** — Regular members are forbidden

**Attendance Count Tests:**
6. **it('shows correct attendance count from last 3 months')** — Displays "1 / 2" for 1 attended out of 2 meetings
7. **it('does not count meetings older than 3 months in attendance')** — Excludes meetings before the 3-month window

**Reports Filed/Missed Tests:**
8. **it('shows correct reports filed and missed count')** — Displays "1 filed" and "1 missed" badge
9. **it('does not show missed badge when all reports are filed')** — Badge only shows if missed > 0

**Ticket Count Tests:**
10. **it('shows correct open and closed ticket counts')** — Displays "2 open" and "2 closed" with Open/Pending and Resolved/Closed counts
11. **it('shows zero ticket counts when no tickets assigned')** — Displays "0 open" and "0 closed"

## Configuration

### Environment Variables

| Variable | Purpose | Example Value |
|----------|---------|----------------|
| `DISCORD_ROLE_IN_BRIG` | Discord role ID to assign when brigging | `1234567890123456789` |

### Configuration Keys

| Key Path | Purpose | Source |
|----------|---------|--------|
| `lighthouse.discord.roles.in_brig` | Discord In Brig role ID | `config/lighthouse.php` line 126, reads `env('DISCORD_ROLE_IN_BRIG')` |

### No New Migration Requirements

This sprint does not require new database migrations. All brig-related fields (`in_brig`, `brig_expires_at`, `brig_reason`, `next_appeal_available_at`, `brig_timer_notified`, `brig_type`) already exist on the `users` table. Discord account status is tracked on the `discord_accounts` table (existing `status` column).
