# Authentication & Registration -- Technical Documentation

> **Audience:** Project owner, developers, AI agents
> **Generated:** 2026-03-07
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

The Authentication & Registration feature handles user account creation, login/logout, password management, email verification, date-of-birth collection, and account deletion. It is the entry point for every user into the Lighthouse Website application.

The feature implements an **age-gated registration flow** with three tiers:
- **17+**: Standard registration with no restrictions.
- **13-16**: Requires a parent/guardian email address. Parent is notified but no approval is needed.
- **Under 13**: Requires a parent/guardian email. The child's account is placed in the "Brig" with `ParentalPending` status, and parental permissions (`parent_allows_site`, `parent_allows_minecraft`, `parent_allows_discord`) default to `false`. The parent must create an account and approve access via the Parent Portal.

Additionally, existing users who lack a `date_of_birth` (pre-dating the parental system) are redirected to a birthdate collection flow via the `ensure-dob` middleware. The `EnsureParentAllowsLogin` middleware (applied globally to the `web` middleware group) force-logs-out any user whose parent has disabled their login access.

All users interact with this feature. It is the foundation upon which all other features (membership, staff, Minecraft linking, Discord, etc.) depend.

---

## 2. Database Schema

### `users` table (auth-relevant columns)

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint (PK) | No | auto | Primary key |
| `name` | string | No | - | Display username (max 32 at registration) |
| `email` | string | No | - | Unique, used for login |
| `email_verified_at` | timestamp | Yes | null | Set when email is verified |
| `password` | string | No | - | Hashed via `Hash::make()` |
| `remember_token` | string | Yes | null | Laravel remember-me token |
| `date_of_birth` | date | Yes | null | Required post-registration via `ensure-dob` middleware |
| `parent_email` | string | Yes | null | Indexed. Set for users under 17. Used by `AutoLinkParentOnRegistration` |
| `in_brig` | boolean | No | false | Whether user is in the Brig |
| `brig_reason` | text | Yes | null | Human-readable reason |
| `brig_expires_at` | timestamp | Yes | null | Timed brig expiration |
| `brig_timer_notified` | boolean | No | false | Whether expiry notification was sent |
| `brig_type` | string(30) | Yes | null | Cast to `BrigType` enum |
| `parent_allows_site` | boolean | No | true | Parent toggle for site access |
| `parent_allows_login` | boolean | No | true | Parent toggle for login ability |
| `parent_allows_minecraft` | boolean | No | true | Parent toggle for MC linking |
| `parent_allows_discord` | boolean | No | true | Parent toggle for Discord linking |
| `created_at` | timestamp | Yes | null | Laravel timestamp |
| `updated_at` | timestamp | Yes | null | Laravel timestamp |

**Indexes:** `email` (unique), `parent_email` (index)

**Migrations:**
- `database/migrations/0001_01_01_000000_create_users_table.php` — Creates `users`, `password_reset_tokens`, `sessions` tables
- `database/migrations/2026_02_20_080000_add_brig_fields_to_users_table.php` — Adds `in_brig`, `brig_reason`, `brig_expires_at`, `brig_timer_notified`
- `database/migrations/2026_02_28_100000_add_parental_fields_to_users_table.php` — Adds `date_of_birth`, `parent_email`, `brig_type`, `parent_allows_site`, `parent_allows_minecraft`, `parent_allows_discord`
- `database/migrations/2026_03_04_145109_add_parent_allows_login_to_users_table.php` — Adds `parent_allows_login`

### `password_reset_tokens` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `email` | string (PK) | No | - | Primary key |
| `token` | string | No | - | Hashed reset token |
| `created_at` | timestamp | Yes | null | Token creation time |

**Migration:** `database/migrations/0001_01_01_000000_create_users_table.php`

### `sessions` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | string (PK) | No | - | Session ID |
| `user_id` | foreignId | Yes | null | Indexed. Links to users table |
| `ip_address` | string(45) | Yes | null | Client IP |
| `user_agent` | text | Yes | null | Browser user agent |
| `payload` | longText | No | - | Serialized session data |
| `last_activity` | integer | No | - | Indexed. Unix timestamp |

**Migration:** `database/migrations/0001_01_01_000000_create_users_table.php`

---

## 3. Models & Relationships

### User (`app/Models/User.php`)

The User model is central to authentication. Auth-relevant attributes:

**Fillable (auth-related):** `name`, `email`, `password`, `date_of_birth`, `parent_email`, `parent_allows_site`, `parent_allows_login`, `parent_allows_minecraft`, `parent_allows_discord`

**Casts (auth-related):**
- `email_verified_at` => `datetime`
- `password` => `hashed`
- `date_of_birth` => `date`
- `parent_allows_site` => `boolean`
- `parent_allows_login` => `boolean`
- `parent_allows_minecraft` => `boolean`
- `parent_allows_discord` => `boolean`
- `brig_type` => `BrigType`
- `in_brig` => `boolean`

**Key Relationships (auth-adjacent):**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `children()` | belongsToMany | User | Via `parent_child_links` pivot, parent side |
| `parents()` | belongsToMany | User | Via `parent_child_links` pivot, child side |

**Key Methods (auth-relevant):**
- `isInBrig(): bool` — Returns `in_brig` value
- `isAdult(): bool` — Age >= 18
- `isMinor(): bool` — Age < 18
- `hasRole(string): bool` — Checks Spatie-style role
- `isAdmin(): bool` — Checks admin role/status

**Note:** The `MustVerifyEmail` interface is commented out on the model, but email verification is still functionally implemented via `VerifyEmailController` and the `verified` middleware on routes.

---

## 4. Enums Reference

### BrigType (`app/Enums/BrigType.php`)

| Case | Value | Label | Notes |
|------|-------|-------|-------|
| `Discipline` | `discipline` | Disciplinary | Staff-imposed discipline |
| `ParentalPending` | `parental_pending` | Pending Parental Approval | Auto-set for under-13 registrations |
| `ParentalDisabled` | `parental_disabled` | Restricted by Parent | Parent explicitly disabled access |
| `AgeLock` | `age_lock` | Age Verification Required | For existing users needing DOB collection |

**Helper methods:**
- `isDisciplinary(): bool` — Returns true for `Discipline`
- `isParental(): bool` — Returns true for `ParentalPending` or `ParentalDisabled`

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

The following gates are relevant to authentication and the auth-gated experience:

| Gate Name | Who Can Pass | Logic Summary |
|-----------|-------------|---------------|
| `view-community-content` | Non-brigged users | `!$user->in_brig` — Brig blocks content access |
| `link-discord` | Stowaway+, not in brig, parent allows | `isAtLeastLevel(Stowaway) && !in_brig && parent_allows_discord` |
| `link-minecraft-account` | Stowaway+, not in brig, parent allows | `isAtLeastLevel(Stowaway) && !in_brig && parent_allows_minecraft` |
| `view-parent-portal` | Adults or users with children | `isAdult() OR children()->exists()` |

### Middleware

| Middleware | Scope | Logic |
|-----------|-------|-------|
| `EnsureParentAllowsLogin` | Global (web group) | If `parent_allows_login === false`, logout user, redirect to login with status message |
| `EnsureDateOfBirthIsSet` (`ensure-dob`) | Per-route alias | If user has no `date_of_birth`, redirect to `birthdate.show`. Exempts `birthdate.*` and `logout` routes |

### Permissions Matrix

| User Type | Register | Login | Reset Password | View Dashboard | Link MC/Discord | Delete Account |
|-----------|----------|-------|----------------|----------------|-----------------|----------------|
| Guest | Yes | Yes | Yes | No | No | No |
| Under 13 (pending) | Yes* | Yes** | Yes | Yes (brig card) | No | Yes |
| 13-16 | Yes* | Yes | Yes | Yes | Yes (if parent allows) | Yes |
| 17+ / Adult | Yes | Yes | Yes | Yes | Yes | Yes |
| Parent-disabled login | - | No*** | Yes | No | No | No |

\* Under-17 requires parent email step.
\** Under-13 is logged in but placed in brig with restricted access.
\*** `EnsureParentAllowsLogin` middleware force-logs out on every request.

---

## 6. Routes

### Guest Routes (`routes/auth.php` — middleware: `guest`)

| Method | URL | Handler | Route Name |
|--------|-----|---------|------------|
| GET | `/login` | `auth.login` (Volt) | `login` |
| GET | `/register` | `auth.register` (Volt) | `register` |
| GET | `/forgot-password` | `auth.forgot-password` (Volt) | `password.request` |
| GET | `/reset-password/{token}` | `auth.reset-password` (Volt) | `password.reset` |

### Authenticated Routes (`routes/auth.php` — middleware: `auth`)

| Method | URL | Handler | Route Name |
|--------|-----|---------|------------|
| GET | `/verify-email` | `auth.verify-email` (Volt) | `verification.notice` |
| GET | `/verify-email/{id}/{hash}` | `VerifyEmailController` | `verification.verify` |
| GET | `/confirm-password` | `auth.confirm-password` (Volt) | `password.confirm` |

### Other Auth-Related Routes (`routes/web.php`)

| Method | URL | Middleware | Handler | Route Name |
|--------|-----|-----------|---------|------------|
| POST | `/logout` | - | `App\Livewire\Actions\Logout` | `logout` |
| GET | `/birthdate` | `auth` | `auth.collect-birthdate` (Volt) | `birthdate.show` |
| GET | `/dashboard` | `auth, verified, ensure-dob` | `dashboard` (view) | `dashboard` |
| GET | `/settings/profile` | `auth` | `settings.profile` (Volt) | `settings.profile` |
| GET | `/settings/password` | `auth` | `settings.password` (Volt) | `settings.password` |

---

## 7. User Interface Components

### Login (`auth.login`)
**File:** `resources/views/livewire/auth/login.blade.php`
**Route:** `/login` (route name: `login`)

**Purpose:** Authenticates users with email + password.

**User Actions:**
- Submit email/password form -> calls `login()` -> validates credentials, rate-limited to 5 attempts per email+IP, regenerates session, redirects to dashboard
- Link to "Forgot password?" -> navigates to `password.request`
- Link to "Create account" -> navigates to `register`

**UI Elements:** Email input, password input, "Remember me" checkbox, submit button, links to register and forgot-password.

---

### Registration (`auth.register`)
**File:** `resources/views/livewire/auth/register.blade.php`
**Route:** `/register` (route name: `register`)

**Purpose:** Creates new user accounts with age-gated workflow.

**Authorization:** Guest-only (middleware: `guest`)

**Step 1 — Account Details:**
- Fields: Username (max 32), Email, Date of Birth, Password, Confirm Password
- On submit (`register()`): validates fields, calculates age
  - If 17+: creates account immediately
  - If under 17: advances to step 2

**Step 2 — Parent Email (under 17 only):**
- Fields: Parent/Guardian Email (cannot match user's email)
- On submit (`submitParentEmail()`): validates, then calls `createAccount()`

**`createAccount()` logic:**
1. Creates User with validated data
2. If under 17: sets `parent_email`
3. If under 13: sets `parent_allows_site/minecraft/discord` to `false`
4. Fires `Registered` event (triggers email verification)
5. Calls `RecordActivity::run($user, 'user_registered', ...)`
6. Calls `AutoLinkParentOnRegistration::run($user)`
7. If under 17 with parent email: sends `ParentAccountNotification` via on-demand notification
8. If under 13: calls `PutUserInBrig::run()` with `BrigType::ParentalPending`
9. Logs in user via `Auth::login()`
10. Redirects to dashboard

---

### Collect Birthdate (`auth.collect-birthdate`)
**File:** `resources/views/livewire/auth/collect-birthdate.blade.php`
**Route:** `/birthdate` (route name: `birthdate.show`)

**Purpose:** Collects date of birth from existing users who registered before the DOB requirement.

**Authorization:** Authenticated (middleware: `auth`). Reached via `ensure-dob` middleware redirect.

**Step 1 — Date of Birth:**
- On submit (`submitDateOfBirth()`):
  - If 17+: saves DOB, releases from AgeLock brig if applicable, redirects to dashboard
  - If under 17: advances to step 2

**Step 2 — Parent Email (under 17 only):**
- On submit (`submitParentEmail()`):
  - Saves DOB and parent_email
  - If under 13: sets parental permissions to false, transitions AgeLock brig to ParentalPending (or creates new ParentalPending brig)
  - If 13-16: saves without restrictions, releases from AgeLock brig if applicable
  - Sends `ParentAccountNotification`
  - Redirects to dashboard

---

### Forgot Password (`auth.forgot-password`)
**File:** `resources/views/livewire/auth/forgot-password.blade.php`
**Route:** `/forgot-password` (route name: `password.request`)

**Purpose:** Sends password reset link via email.

**User Actions:**
- Submit email -> calls `sendPasswordResetLink()` -> calls `Password::sendResetLink()` -> shows generic success message regardless of account existence (security best practice)

---

### Reset Password (`auth.reset-password`)
**File:** `resources/views/livewire/auth/reset-password.blade.php`
**Route:** `/reset-password/{token}` (route name: `password.reset`)

**Purpose:** Allows user to set a new password using a valid reset token.

**User Actions:**
- Submit email + new password + confirmation -> calls `resetPassword()` -> calls `Password::reset()` -> fires `PasswordReset` event -> redirects to login

---

### Verify Email (`auth.verify-email`)
**File:** `resources/views/livewire/auth/verify-email.blade.php`
**Route:** `/verify-email` (route name: `verification.notice`)

**Purpose:** Displays email verification notice with resend option.

**User Actions:**
- Click "Resend verification email" -> sends verification notification
- Click logout link

---

### Confirm Password (`auth.confirm-password`)
**File:** `resources/views/livewire/auth/confirm-password.blade.php`
**Route:** `/confirm-password` (route name: `password.confirm`)

**Purpose:** Re-confirms password for accessing secure areas.

**User Actions:**
- Submit password -> calls `confirmPassword()` -> validates against current user -> sets `auth.password_confirmed_at` in session -> redirects to intended URL

---

### Change Password (`settings.password`)
**File:** `resources/views/livewire/settings/password.blade.php`
**Route:** `/settings/password` (route name: `settings.password`)

**Purpose:** Change current password. Requires current password validation.

**User Actions:**
- Submit current password + new password + confirmation -> calls `updatePassword()` -> validates current password, updates, redirects

---

### Delete Account (`settings.delete-user-form`)
**File:** `resources/views/livewire/settings/delete-user-form.blade.php`
**Route:** Embedded in `/settings/profile` page

**Purpose:** Permanently delete user account with password confirmation.

**User Actions:**
- Click "Delete account" -> opens password confirmation modal -> submit password -> calls Logout action -> deletes user model -> redirects to `/`

---

## 8. Actions (Business Logic)

### AutoLinkParentOnRegistration (`app/Actions/AutoLinkParentOnRegistration.php`)

**Signature:** `handle(User $newUser): void`

**Step-by-step logic:**
1. Case-insensitive search: finds all users where `parent_email` matches `$newUser->email`
2. If no children found, returns early
3. Batch-links all matching children via `$newUser->children()->syncWithoutDetaching($childIds)`
4. For each child: `RecordActivity::run($child, 'parent_linked', "Parent account ({email}) automatically linked.")`

**Called by:** `auth.register` component (`createAccount()` method)

---

### PutUserInBrig (`app/Actions/PutUserInBrig.php`)

**Called by auth feature with:** `BrigType::ParentalPending` for under-13 registrations and DOB collection.

**Relevant parameters:** `target`, `admin`, `reason`, `brigType`, `notify: false`

---

### ReleaseUserFromBrig (`app/Actions/ReleaseUserFromBrig.php`)

**Called by auth feature:** In `collect-birthdate` to release users from `AgeLock` brig when they provide DOB showing 17+ or 13-16 age.

---

### Logout (`app/Livewire/Actions/Logout.php`)

**Signature:** Invokable `__invoke(Request $request): void`

**Step-by-step logic:**
1. `Auth::guard('web')->logout()`
2. `$request->session()->invalidate()`
3. `$request->session()->regenerateToken()`
4. Redirects to `/`

**Called by:** POST `/logout` route, `delete-user-form` component

---

## 9. Notifications

### ParentAccountNotification (`app/Notifications/ParentAccountNotification.php`)

**Triggered by:** `auth.register` component and `auth.collect-birthdate` component
**Recipient:** Parent email address (on-demand notification, no User model required)
**Channels:** `mail`
**Queued:** Yes (`ShouldQueue`)
**Mail subject:** "Your Child Has Created a Lighthouse Account"
**Content summary:**
- If `requiresApproval` (under 13): Explains parental approval is required, describes Parent Portal features
- If not (13-16): Informs parent of account creation, describes Parent Portal features
- Includes "Create Your Account" button linking to `/register`

**Mail template:** `resources/views/mail/parent-account.blade.php`

**Note:** This notification is an approved exception to the `TicketNotificationService` guideline because it is sent to an email address before a User account exists, using Laravel's `Notification::route('mail', $email)` on-demand notification.

---

## 10. Background Jobs

Not applicable for this feature. The `ParentAccountNotification` is queued via `ShouldQueue` but uses Laravel's built-in queue system rather than a dedicated Job class.

---

## 11. Console Commands & Scheduled Tasks

Not applicable for this feature.

---

## 12. Services

Not applicable for this feature directly. The feature uses Laravel's built-in authentication services (guards, password broker, session driver) configured in `config/auth.php`.

---

## 13. Activity Log Entries

| Action String | Logged By | Subject Model | Description |
|---------------|-----------|---------------|-------------|
| `user_registered` | `auth.register` component | User | "User registered for an account" |
| `parent_linked` | `AutoLinkParentOnRegistration` | User (child) | "Parent account ({email}) automatically linked." |

---

## 14. Data Flow Diagrams

### Registration (17+ User)

```
User visits /register (guest middleware)
  -> GET /register
    -> auth.register Volt component renders Step 1 form
User fills in name, email, DOB (17+), password, confirmation
  -> wire:submit="register"
    -> validate() passes
    -> age >= 17, calls createAccount()
      -> User::create([name, email, password, date_of_birth])
      -> event(new Registered($user))  // triggers email verification
      -> RecordActivity::run($user, 'user_registered', ...)
      -> AutoLinkParentOnRegistration::run($user)  // links if parent_email matches
      -> Auth::login($user)
      -> redirect to /dashboard
```

### Registration (Under 13)

```
User visits /register (guest middleware)
  -> Step 1: fill name, email, DOB (age 11), password
    -> wire:submit="register"
      -> age < 17: $this->step = 2
  -> Step 2: fill parent_email
    -> wire:submit="submitParentEmail"
      -> validate (parent_email != user email)
      -> createAccount()
        -> User::create([..., parent_email, parent_allows_site=false, parent_allows_minecraft=false, parent_allows_discord=false])
        -> event(new Registered($user))
        -> RecordActivity::run($user, 'user_registered', ...)
        -> AutoLinkParentOnRegistration::run($user)
        -> Notification::route('mail', parent_email)->notify(new ParentAccountNotification($user, requiresApproval: true))
        -> PutUserInBrig::run(target: $user, brigType: ParentalPending, notify: false)
        -> Auth::login($user)
        -> redirect to /dashboard (user sees brig restrictions)
```

### Login

```
User visits /login (guest middleware)
  -> auth.login Volt component renders form
User submits email + password
  -> wire:submit="login"
    -> RateLimiter::tooManyAttempts(email|ip, 5) check
    -> Auth::attempt(['email' => $email, 'password' => $password], $remember)
    -> If fails: increment rate limiter, show error
    -> If succeeds: clear rate limiter, regenerate session
    -> redirect to /dashboard
  -> EnsureParentAllowsLogin middleware runs on next request
    -> If parent_allows_login === false: logout, redirect to /login with status message
  -> ensure-dob middleware runs (on dashboard)
    -> If no date_of_birth: redirect to /birthdate
```

### Birthdate Collection (Existing User, Age Lock)

```
User with no date_of_birth visits /dashboard
  -> ensure-dob middleware redirects to /birthdate
  -> auth.collect-birthdate Volt component renders Step 1
User enters DOB
  -> wire:submit="submitDateOfBirth"
    -> If 17+:
      -> Save DOB, release from AgeLock brig if applicable
      -> redirect to /dashboard
    -> If under 17:
      -> $this->step = 2
  -> Step 2: enter parent email
    -> wire:submit="submitParentEmail"
      -> Save DOB + parent_email
      -> If under 13: set parent_allows_* to false, transition to ParentalPending brig
      -> If 13-16: release from AgeLock brig if applicable
      -> Send ParentAccountNotification
      -> redirect to /dashboard
```

### Password Reset

```
User visits /forgot-password (guest middleware)
  -> Submits email
    -> Password::sendResetLink(['email' => $email])
    -> Shows generic "We emailed you" message

User clicks link in email
  -> GET /reset-password/{token}
  -> auth.reset-password component renders
  -> Submits email + new password + confirmation
    -> Password::reset() validates token + updates password
    -> Fires PasswordReset event
    -> Redirects to /login
```

### Email Verification

```
After registration, Registered event fires
  -> Laravel sends verification email with signed URL

User clicks verification link
  -> GET /verify-email/{id}/{hash} (middleware: auth, signed, throttle:6,1)
    -> VerifyEmailController (invokable)
      -> Checks EmailVerificationRequest (validates hash)
      -> Marks email as verified
      -> Fires Verified event
      -> Redirects to /dashboard?verified=1
```

### Account Deletion

```
User visits /settings/profile
  -> delete-user-form component rendered at bottom
  -> User clicks "Delete account"
    -> Password confirmation modal opens
    -> User enters password
      -> Logout action invoked (invalidate session, regenerate CSRF)
      -> $user->delete()
      -> Redirect to /
```

### Parent Login Block

```
Any authenticated request (global web middleware)
  -> EnsureParentAllowsLogin::handle()
    -> If $user->parent_allows_login === false:
      -> Auth::guard('web')->logout()
      -> Session invalidate + regenerate token
      -> Redirect to /login with status: "Your account login has been disabled by your parent or guardian."
```

---

## 15. Configuration

### `config/auth.php`

| Key | Default | Purpose |
|-----|---------|---------|
| `defaults.guard` | `web` | Default auth guard |
| `defaults.passwords` | `users` | Default password broker |
| `guards.web.driver` | `session` | Session-based authentication |
| `guards.web.provider` | `users` | Eloquent user provider |
| `providers.users.driver` | `eloquent` | Uses Eloquent ORM |
| `providers.users.model` | `App\Models\User` | User model class (configurable via `AUTH_MODEL` env) |
| `passwords.users.table` | `password_reset_tokens` | Token storage table (configurable via `AUTH_PASSWORD_RESET_TOKEN_TABLE` env) |
| `passwords.users.expire` | `60` | Reset token expiry in minutes |
| `passwords.users.throttle` | `60` | Seconds between reset token requests |
| `password_timeout` | `10800` | Password confirmation timeout in seconds (3 hours, configurable via `AUTH_PASSWORD_TIMEOUT` env) |

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/Auth/AuthenticationTest.php` | 4 | Login screen rendering, successful login, invalid password, logout |
| `tests/Feature/Auth/RegistrationTest.php` | 2 | Registration screen rendering, basic adult registration |
| `tests/Feature/Auth/RegistrationWithAgeTest.php` | 5 | Age-gated registration: 17+ normal, under-17 parent step, under-13 brig, under-13 login+brig, 13-16 login |
| `tests/Feature/Auth/CollectBirthdateTest.php` | 5 | DOB redirect, no redirect with DOB, AgeLock release for 17+, under-13 ParentalPending brig, 13-16 parent email collection |
| `tests/Feature/Auth/EmailVerificationTest.php` | 3 | Verification screen rendering, successful verification, invalid hash rejection |
| `tests/Feature/Auth/PasswordConfirmationTest.php` | 3 | Confirmation screen rendering, successful confirmation, invalid password |
| `tests/Feature/Auth/PasswordResetTest.php` | 4 | Reset link screen, reset link request, reset screen rendering, successful password reset |
| `tests/Feature/Auth/GuestRedirectTest.php` | 5 | Guest redirects to login for profile, ACP create, ACP edit; authenticated traveler/stowaway can view profiles |
| `tests/Feature/Auth/AcpTabPermissionsTest.php` | 12 | ACP gate permissions for various staff positions and roles |
| `tests/Feature/Middleware/EnsureDateOfBirthIsSetTest.php` | 3 | DOB middleware: redirects without DOB, allows with DOB, no redirect loop on birthdate page |
| `tests/Feature/Actions/Actions/AutoLinkParentOnRegistrationTest.php` | 4 | Parent-child linking: email match, no duplicates, multiple children, no match |

### Test Case Inventory

**AuthenticationTest:**
- `test_login_screen_can_be_rendered`
- `test_users_can_authenticate_using_the_login_screen`
- `test_users_can_not_authenticate_with_invalid_password`
- `test_users_can_logout`

**RegistrationTest:**
- `test_registration_screen_can_be_rendered`
- `test_new_users_can_register`

**RegistrationWithAgeTest:**
- `it registers 17+ user normally`
- `it shows parent email step for under 17`
- `it puts under 13 in brig with parental_pending type`
- `it logs in under 13 user but puts them in brig`
- `it logs in 13-16 user after registration`

**CollectBirthdateTest:**
- `it redirects users without DOB to birthdate page`
- `it does not redirect users with DOB`
- `it releases age_lock for 17+`
- `it puts existing user under 13 in parental_pending brig`
- `it collects parent email for 13-16 and redirects`

**EmailVerificationTest:**
- `test_email_verification_screen_can_be_rendered`
- `test_email_can_be_verified`
- `test_email_is_not_verified_with_invalid_hash`

**PasswordConfirmationTest:**
- `test_confirm_password_screen_can_be_rendered`
- `test_password_can_be_confirmed`
- `test_password_is_not_confirmed_with_invalid_password`

**PasswordResetTest:**
- `test_reset_password_link_screen_can_be_rendered`
- `test_reset_password_link_can_be_requested`
- `test_reset_password_screen_can_be_rendered`
- `test_password_can_be_reset_with_valid_token`

**GuestRedirectTest:**
- `guest visiting a profile is redirected to login`
- `guest visiting acp page create is redirected to login`
- `guest visiting acp page edit is redirected to login`
- `authenticated traveler can view another users profile`
- `authenticated stowaway can view another users profile`

**AcpTabPermissionsTest:**
- `engineering jr crew can pass view-mc-command-log gate`
- `engineering jr crew can pass view-activity-log gate`
- `any officer can pass view-mc-command-log gate`
- `any officer can pass view-activity-log gate`
- `engineering jr crew can pass view-discord-api-log gate`
- `any officer can pass view-discord-api-log gate`
- `non-engineering non-officer is denied discord api log gate`
- `non-engineering non-officer is denied mc command log gate`
- `non-engineering non-officer is denied activity log gate`
- `any officer can viewAny minecraft accounts`
- `any officer can viewAny discord accounts`
- `crew member from non-engineering dept cannot viewAny minecraft accounts`

**EnsureDateOfBirthIsSetTest:**
- `it redirects user with no date of birth to birthdate page`
- `it allows user with date of birth to access pages`
- `it allows user without DOB to access the birthdate page`

**AutoLinkParentOnRegistrationTest:**
- `it links parent to child when emails match`
- `it does not create duplicate links`
- `it links parent to multiple children with same parent_email`
- `it does nothing when no children have matching parent_email`

### Coverage Gaps

- No test for `EnsureParentAllowsLogin` middleware (force-logout when `parent_allows_login === false`)
- `EnsureDateOfBirthIsSet` middleware has basic coverage in `EnsureDateOfBirthIsSetTest.php` (3 tests) but does not test the exemption list for specific route names
- No test for rate limiting on login (5 attempts per email+IP)
- No test for `ParentAccountNotification` being sent during registration or birthdate collection
- No test for the `parent_email` cannot equal user's own email validation
- No test for password change (`settings.password` component)
- No test for account deletion (`settings.delete-user-form` component)
- No test for registration with parent email that matches an existing user (auto-link during registration)
- No test for the transition from AgeLock to ParentalPending in `collect-birthdate` (only tests fresh ParentalPending)

---

## 17. File Map

**Models:**
- `app/Models/User.php`

**Enums:**
- `app/Enums/BrigType.php`

**Actions:**
- `app/Actions/AutoLinkParentOnRegistration.php`
- `app/Actions/PutUserInBrig.php` (called by auth feature)
- `app/Actions/ReleaseUserFromBrig.php` (called by auth feature)
- `app/Actions/RecordActivity.php` (called by auth feature)
- `app/Livewire/Actions/Logout.php`

**Policies:**
- Not applicable (auth uses middleware and gates, not model policies)

**Gates:** `app/Providers/AuthServiceProvider.php` -- gates: `view-community-content`, `link-discord`, `link-minecraft-account`, `view-parent-portal`

**Notifications:**
- `app/Notifications/ParentAccountNotification.php`

**Jobs:** None

**Services:** None

**Controllers:**
- `app/Http/Controllers/Auth/VerifyEmailController.php`

**Volt Components:**
- `resources/views/livewire/auth/login.blade.php`
- `resources/views/livewire/auth/register.blade.php`
- `resources/views/livewire/auth/forgot-password.blade.php`
- `resources/views/livewire/auth/reset-password.blade.php`
- `resources/views/livewire/auth/verify-email.blade.php`
- `resources/views/livewire/auth/confirm-password.blade.php`
- `resources/views/livewire/auth/collect-birthdate.blade.php`
- `resources/views/livewire/settings/password.blade.php`
- `resources/views/livewire/settings/delete-user-form.blade.php`
- `resources/views/livewire/settings/profile.blade.php` (contains email re-verification and delete-user embed)

**Mail Templates:**
- `resources/views/mail/parent-account.blade.php`
- `resources/views/mail/parent-account-disabled.blade.php`
- `resources/views/mail/parent-account-enabled.blade.php`

**Routes:**
- `routes/auth.php` — `login`, `register`, `password.request`, `password.reset`, `verification.notice`, `verification.verify`, `password.confirm`, `logout`
- `routes/web.php` — `birthdate.show`, `dashboard`, `settings.profile`, `settings.password`

**Migrations:**
- `database/migrations/0001_01_01_000000_create_users_table.php`
- `database/migrations/2026_02_20_080000_add_brig_fields_to_users_table.php`
- `database/migrations/2026_02_28_100000_add_parental_fields_to_users_table.php`
- `database/migrations/2026_03_04_145109_add_parent_allows_login_to_users_table.php`

**Middleware:**
- `app/Http/Middleware/EnsureParentAllowsLogin.php`
- `app/Http/Middleware/EnsureDateOfBirthIsSet.php`

**Bootstrap:**
- `bootstrap/app.php` (middleware registration)

**Layout Components:**
- `resources/views/components/layouts/auth.blade.php`
- `resources/views/components/layouts/auth/simple.blade.php`
- `resources/views/components/auth-header.blade.php`
- `resources/views/components/auth-session-status.blade.php`

**Config:**
- `config/auth.php`

**Tests:**
- `tests/Feature/Auth/AuthenticationTest.php`
- `tests/Feature/Auth/RegistrationTest.php`
- `tests/Feature/Auth/RegistrationWithAgeTest.php`
- `tests/Feature/Auth/CollectBirthdateTest.php`
- `tests/Feature/Auth/EmailVerificationTest.php`
- `tests/Feature/Auth/PasswordConfirmationTest.php`
- `tests/Feature/Auth/PasswordResetTest.php`
- `tests/Feature/Auth/GuestRedirectTest.php`
- `tests/Feature/Auth/AcpTabPermissionsTest.php`
- `tests/Feature/Middleware/EnsureDateOfBirthIsSetTest.php`
- `tests/Feature/Actions/Actions/AutoLinkParentOnRegistrationTest.php`

---

## 18. Known Issues & Improvement Opportunities

1. **Missing middleware tests:** `EnsureParentAllowsLogin` has no dedicated test. This is a critical security middleware that force-logs out users and should have explicit test coverage for both the block case and the pass-through case.

2. **No test for rate limiting on login:** The login component implements rate limiting (5 attempts per email+IP), but no test verifies this behavior. A malicious actor could potentially bypass this if the implementation has a bug.

3. **No test for ParentAccountNotification delivery:** Registration and birthdate collection both send `ParentAccountNotification`, but no test verifies the notification is actually dispatched with correct parameters.

4. **Account deletion lacks test coverage:** The `delete-user-form` component has no tests. Account deletion is a destructive, irreversible action that should be well-tested.

5. **Password change lacks test coverage:** The `settings.password` component has no tests verifying current password validation or successful update.

6. **AgeLock-to-ParentalPending transition in collect-birthdate:** The `collect-birthdate` component handles transitioning from `AgeLock` to `ParentalPending` brig type by directly modifying `brig_type` and `brig_reason` on the model (lines 68-71) rather than going through the `PutUserInBrig` action. This bypasses any activity logging or side effects that the action might perform.

7. **Parent email validation gap:** The `parent_email` field only validates that it's a valid email and not the same as the user's email. There's no validation preventing a minor from entering another minor's email as their "parent" email.

8. **Registration validation re-run on step 2:** The `submitParentEmail()` method re-validates all step 1 fields (name, email, password, DOB) even though they were already validated in step 1. This is defensive but could theoretically fail if the email uniqueness check now fails due to a race condition.

9. **`ensure-dob` middleware scope:** The `ensure-dob` middleware is only applied to specific routes (`dashboard`, `parent-portal.index`, `parent-portal.show`) rather than globally. Users without DOB can access settings pages and other authenticated routes without being forced through the birthdate flow.

10. **Additional mail templates exist but are not documented in context:** `parent-account-disabled.blade.php` and `parent-account-enabled.blade.php` exist in the mail views directory, suggesting parent permission toggle notifications exist elsewhere (likely in the Parent Portal feature).
