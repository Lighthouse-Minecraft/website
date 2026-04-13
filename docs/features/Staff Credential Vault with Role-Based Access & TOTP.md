# Staff Credential Vault with Role-Based Access & TOTP — Technical Documentation

> **Audience:** Project owner, developers, AI agents
> **Generated:** 2026-04-12
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

The Staff Credential Vault is a secure password manager built into the Lighthouse Website for storing and sharing service credentials among authorized staff. Sensitive fields are encrypted at rest using a dedicated vault key that is separate from the application key, so a database dump alone is insufficient to read stored credentials.

**Who uses it:**

- **Vault Manager** (role): Full control over the vault — can create, edit, delete credentials and manage which staff positions have access to each credential.
- **Position holders** (JrCrew rank and above): Can view and update credentials that are assigned to their staff position. Cannot delete credentials or change position access.
- **Non-position staff / members below JrCrew rank**: Cannot access the vault at all.
- **Admins**: Bypass all policy checks and have full access.
- **Logs - Viewer** (role): Can read the Credential Access Log in the Admin Control Panel (ACP).

**Key concepts:**

- **Credential**: A named record containing a username, optional email, password, optional TOTP secret, notes, and recovery codes. All sensitive fields are encrypted via Eloquent accessors/mutators using AES-256-CBC and the `VAULT_KEY` environment variable.
- **Vault Session**: Re-authentication (entering the user's Lighthouse password) is required before sensitive data can be revealed. The session remains unlocked for a configurable TTL (default 30 minutes). Password reveal requires the session to be unlocked; TOTP reveal always requires re-authentication regardless of session state.
- **Position Access Control**: A Vault Manager assigns one or more `StaffPosition` records to each credential. Only the holder of an assigned position can view that credential. Credentials with no assigned positions are visible only to Vault Managers.
- **Rotation Flags**: When a staff member is removed from a position via `UnassignStaffPosition`, `FlagCredentialsAfterPositionRemoval` automatically sets `needs_password_change = true` on every credential assigned to that position that the departing user previously accessed. This surfaces a "Needs Rotation" badge in the vault index and detail views.
- **Access Log**: Every meaningful interaction with a credential (creation, update, deletion, password reveal, TOTP reveal, position assignment) is recorded in `credential_access_logs`. These logs are viewable in the ACP by staff with the `Logs - Viewer` role.

---

## 2. Database Schema

### `credentials` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned | No | auto | Primary key |
| name | varchar(255) | No | | Human-readable label (e.g. "Apex Hosting Admin") |
| website_url | varchar(255) | Yes | null | Login URL for the service |
| username | text | No | | Encrypted via VaultEncrypter |
| email | text | Yes | null | Encrypted via VaultEncrypter |
| password | text | No | | Encrypted via VaultEncrypter |
| totp_secret | text | Yes | null | Encrypted via VaultEncrypter |
| notes | text | Yes | null | Encrypted via VaultEncrypter |
| recovery_codes | text | Yes | null | Encrypted via VaultEncrypter |
| needs_password_change | tinyint(1) | No | 0 | Set to 1 when a departing staff member accessed the credential |
| created_by | bigint unsigned | No | | FK → users.id |
| updated_by | bigint unsigned | Yes | null | FK → users.id |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

**Migration:** `database/migrations/2026_04_12_000001_create_credentials_table.php`

---

### `credential_staff_position` table (pivot)

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| credential_id | bigint unsigned | No | | FK → credentials.id (cascade delete) |
| staff_position_id | bigint unsigned | No | | FK → staff_positions.id (cascade delete) |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

**Primary key:** Composite `(credential_id, staff_position_id)`

**Migration:** `database/migrations/2026_04_12_000002_create_credential_staff_position_table.php`

---

### `credential_access_logs` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned | No | auto | Primary key |
| credential_id | bigint unsigned | No | | FK → credentials.id (cascade delete) |
| user_id | bigint unsigned | No | | FK → users.id |
| action | varchar(255) | No | | Action string (see below) |
| created_at | timestamp | No | CURRENT_TIMESTAMP | No `updated_at` column |

**Note:** This table has no `updated_at` column. The model sets `$timestamps = false` and manually fills `created_at`.

**Known action values:**

| Action | Triggered by |
|--------|-------------|
| `created` | `CreateCredential` |
| `updated` | `UpdateCredential` |
| `deleted` | `DeleteCredential` (recorded before logs are cleared) |
| `viewed_password` | Vault detail component on password reveal |
| `viewed_totp` | Vault detail component on TOTP reveal |
| `positions_assigned` | `AssignCredentialPositions` |

**Migration:** `database/migrations/2026_04_12_000003_create_credential_access_logs_table.php`

---

### `roles` table — seeded entry

The migration `database/migrations/2026_04_12_000004_seed_vault_manager_role.php` inserts a `Vault Manager` role (color: `violet`, icon: `key`) if it does not already exist. The `down()` method deletes this role.

---

## 3. Models & Relationships

### `App\Models\Credential`

All six sensitive fields (`username`, `email`, `password`, `totp_secret`, `notes`, `recovery_codes`) are encrypted on write and decrypted on read via custom Eloquent accessors and mutators that call `VaultEncrypter`. The plaintext value is **never** stored in the database.

The `needs_password_change` field is cast to `boolean`.

**Important UI note:** The index and detail Blade views use `$credential->getRawOriginal('totp_secret')` to check whether a TOTP secret exists without decrypting the value. This avoids an unnecessary decrypt operation just to determine presence.

| Relationship | Type | Description |
|---|---|---|
| `createdBy()` | BelongsTo User | Staff member who created the credential |
| `updatedBy()` | BelongsTo User | Staff member who last updated the credential |
| `staffPositions()` | BelongsToMany StaffPosition | Positions that can view this credential (via `credential_staff_position`) |
| `accessLogs()` | HasMany CredentialAccessLog | All access log entries for this credential |
| `latestAccessLog()` | HasOne CredentialAccessLog | Most recent `viewed_password` or `viewed_totp` entry, used for the "Last Accessed" column on the index |

---

### `App\Models\CredentialAccessLog`

Append-only log record. `$timestamps = false`; only `created_at` is stored.

| Relationship | Type | Description |
|---|---|---|
| `credential()` | BelongsTo Credential | |
| `user()` | BelongsTo User | |

---

### `App\Models\StaffPosition` (updated)

A `credentials()` BelongsToMany relationship was added to `StaffPosition` pointing to the `credential_staff_position` pivot table. This is used by `FlagCredentialsAfterPositionRemoval` to query credentials scoped to a given position.

---

## 4. Enums Reference

No feature-specific enums. The `StaffRank` enum (pre-existing) is used by the `view-vault` gate (`isAtLeastRank(StaffRank::JrCrew)`).

---

## 5. Authorization & Permissions

### Gates (in `AuthServiceProvider`)

| Gate | Logic | Used by |
|---|---|---|
| `view-vault` | User is at least `StaffRank::JrCrew` | Vault route middleware; index component `mount()` |
| `manage-vault` | User has `Vault Manager` role | Index `openCreate()`/`create()`; detail `openManagePositions()`, `addPosition()`, `removePosition()` |
| `view-credential-access-log` | Shared `$canViewLogs` closure (requires `Logs - Viewer` role) | ACP credential access log tab |

### `CredentialPolicy`

| Method | Who can? |
|---|---|
| `before()` | Admins bypass all checks (returns `true`) |
| `viewAny()` | Vault Manager OR at least JrCrew rank |
| `view()` | Vault Manager, OR current user's `staffPosition` is in `credential->staffPositions` |
| `update()` | Same as `view()` — position holders can update credentials they can see |
| `delete()` | Vault Manager only |
| `managePositions()` | Vault Manager only |

**Position-holder edit restriction:** The `vault.detail` component further restricts what non-Vault-Manager editors can change. Position holders cannot edit `name` or `website_url`; only Vault Managers see those fields in the edit modal.

---

## 6. Routes

All vault routes are grouped under the `vault.` name prefix, `/vault` URL prefix, and the `['auth', 'can:view-vault']` middleware stack.

```
GET  /vault            vault.index    resources/views/livewire/vault/index.blade.php
GET  /vault/{credential}  vault.detail   resources/views/livewire/vault/detail.blade.php
```

The `{credential}` segment uses implicit route–model binding on the `Credential` model.

**ACP route:** The credential access log page is embedded inside the ACP via the `admin-control-panel-tabs` component. It does not have its own route; access is gated by `view-credential-access-log` within the ACP.

---

## 7. User Interface Components

### `resources/views/livewire/vault/index.blade.php`

**Volt component.** Mounts with `authorize('view-vault')`.

**Properties:** `name`, `website_url`, `username`, `email`, `password`, `totp_secret`, `notes`, `recovery_codes` (all strings, used for the create modal form).

**Computed property `credentials()`:** Returns the list of credentials visible to the current user.
- Vault Manager / Admin: all credentials, ordered by name, with `latestAccessLog.user` eager-loaded.
- Position holder: only credentials where `staffPositions` contains the user's current position.
- No position: returns an empty collection.

**Methods:**

| Method | Gate/Policy | Description |
|---|---|---|
| `openCreate()` | `manage-vault` | Resets form fields and shows `create-credential-modal` |
| `create()` | `manage-vault` | Validates input, calls `CreateCredential::run()`, shows toast, resets form |

**Table columns:** Name (with "Needs Rotation" badge), Website, Password (always masked), TOTP (badge or dash), Last Accessed (user + diffForHumans).

**Create modal:** Available only to users who pass `@can('manage-vault')`. Fields: Name*, Website URL, Username*, Email, Password*, TOTP Secret, Notes, Recovery Codes.

---

### `resources/views/livewire/vault/detail.blade.php`

**Volt component.** Mounts with `authorize('view', $credential)`.

**Properties:**

| Property | Type | Description |
|---|---|---|
| `credential` | Credential | The bound model |
| `editName`, `editWebsiteUrl`, `editUsername`, `editEmail`, `editPassword`, `editTotpSecret`, `editNotes`, `editRecoveryCodes` | string | Edit form fields |
| `addPositionId` | ?int | Selected position ID in the manage-positions modal |
| `revealedPassword` | ?string | Plaintext password once revealed; null while hidden |
| `totpCode` | ?string | Current TOTP code once revealed |
| `totpSecondsRemaining` | int | Seconds until the current TOTP window expires |
| `reauthPassword` | string | Password input in the re-auth modal |
| `reauthError` | string | Error message shown in the re-auth modal |
| `reauthPurpose` | string | `'password'` or `'totp'` — action to take after successful re-auth |

**Computed properties:**

| Computed | Description |
|---|---|
| `isSessionUnlocked` | Delegates to `VaultSession::isUnlocked()` |
| `isVaultManager` | True if user has `Vault Manager` role or is admin |
| `assignedPositions` | StaffPositions currently assigned to this credential (with user eager-loaded) |
| `availablePositions` | All StaffPositions not yet assigned to this credential |

**Methods:**

| Method | Gate/Policy | Description |
|---|---|---|
| `revealPassword()` | `view` | Reveals password if session unlocked; otherwise opens re-auth modal with `reauthPurpose = 'password'` |
| `showTotp()` | `view` | Always opens re-auth modal with `reauthPurpose = 'totp'` (TOTP reveal never skips re-auth) |
| `refreshTotp()` | `view` | Refreshes `totpCode` and `totpSecondsRemaining` without re-auth; no-op if `totpCode` is null. Called by `wire:poll.1000ms` in the TOTP modal |
| `reauth()` | `view` | Calls `ReauthenticateVaultSession::run()`; on success unlocks vault, then either reveals password or reveals TOTP and opens the TOTP modal |
| `openEdit()` | `update` | Loads current values into edit fields; Vault Managers see name/website fields, position holders do not |
| `saveEdit()` | `update` | Validates and calls `UpdateCredential::run()`; blank password is ignored |
| `confirmDelete()` | `delete` | Shows delete confirmation modal |
| `delete()` | `delete` | Calls `DeleteCredential::run()`, redirects to vault index |
| `openManagePositions()` | `managePositions` | Opens the position management modal |
| `addPosition()` | `managePositions` | Appends a position to the current list and calls `AssignCredentialPositions::run()` |
| `removePosition(int $positionId)` | `managePositions` | Removes a position from the current list and calls `AssignCredentialPositions::run()` |

**Modals rendered in this component:**

| Modal name | Who sees it | Description |
|---|---|---|
| `edit-credential-modal` | `can('update', $credential)` | Edit form (fields vary by role) |
| `delete-confirm-modal` | `can('delete', $credential)` | Deletion confirmation |
| `reauth-modal` | All (rendered unconditionally) | Password entry for session unlock |
| `totp-modal` | All (rendered only when `$totpCode !== null`) | Displays live TOTP code with countdown |
| `manage-positions-modal` | `can('managePositions', $credential)` | Add/remove position access |

---

### `resources/views/livewire/admin-manage-credential-access-log-page.blade.php`

**Volt component.** Mounts with `authorize('view-credential-access-log')`.

Displays a paginated (25 per page) table of all `credential_access_logs` records, ordered newest-first. An action filter dropdown (populated from distinct values in the table) can be used to narrow the view.

Columns: Date/Time, User, Credential, Action (human-readable badge).

Handles deleted users (`(deleted user)`) and deleted credentials (`(deleted credential)`) gracefully via nullable relationship checks.

---

### `resources/views/livewire/admin-control-panel-tabs.blade.php` (integration)

The ACP tabs component includes a "Credential Access Log" tab under the Logs tab group, guarded by `@can('view-credential-access-log')`. The tab renders `<livewire:admin-manage-credential-access-log-page />` inside the tab panel. The `hasLogTabs()` and default tab resolution logic were updated to include this gate.

---

## 8. Actions (Business Logic)

All actions use `AsAction` and are invoked via `ClassName::run(...)`.

---

### `App\Actions\CreateCredential`

**Signature:** `handle(User $creator, array $data): Credential`

**Required `$data` keys:** `name`, `username`, `password`

**Optional `$data` keys:** `website_url`, `email`, `totp_secret`, `notes`, `recovery_codes`

Creates the credential with `needs_password_change = false` and `created_by = $creator->id`. Encrypted fields are stored encrypted automatically by model mutators. Records `credential_created` activity and a `created` access log entry.

---

### `App\Actions\UpdateCredential`

**Signature:** `handle(Credential $credential, User $updater, array $data): Credential`

Accepts a partial `$data` array — only keys present in `$data` are updated. Password update only occurs when `$data['password']` is present and non-empty; when a password is changed, `needs_password_change` is reset to `false`. Records `credential_updated` activity and an `updated` access log entry. Returns `$credential->fresh()`.

---

### `App\Actions\DeleteCredential`

**Signature:** `handle(Credential $credential, User $deletedBy): void`

Records a `deleted` access log entry **before** clearing data. Then detaches all `staffPositions()` pivot rows, deletes all `accessLogs()` entries, and hard-deletes the credential. Records `credential_deleted` activity on the **user** model (not the credential, since it no longer exists).

---

### `App\Actions\AssignCredentialPositions`

**Signature:** `handle(Credential $credential, User $assignedBy, array $positionIds): void`

Uses `sync()` to replace the full set of assigned positions with `$positionIds`. Passing an empty array removes all positions. Records `credential_positions_assigned` activity and a `positions_assigned` access log entry.

---

### `App\Actions\FlagCredentialsAfterPositionRemoval`

**Signature:** `handle(User $user, StaffPosition $position): void`

Called from `UnassignStaffPosition` within a database transaction. Queries credentials assigned to `$position` that have at least one access log entry from `$user`, then sets `needs_password_change = true` on each. Credentials the departing user never accessed are not flagged.

**Wired into:** `App\Actions\UnassignStaffPosition` — called at the start of the transaction, before the position's `user_id` is cleared.

---

### `App\Actions\GenerateTotpCode`

**Signature:** `handle(Credential $credential): array{code: string, seconds_remaining: int}`

Uses the `spomky-labs/otphp` library (`OTPHP\TOTP::createFromSecret()`) with an internal clock. Reads the decrypted `totp_secret` from the credential and returns the current 6-digit code and seconds until the window expires. The raw secret is never included in the return value.

---

### `App\Actions\ReauthenticateVaultSession`

**Signature:** `handle(User $user, string $password): bool`

Verifies `$password` against the user's Lighthouse account password using `Hash::check()`. On success, calls `VaultSession::unlock()` and returns `true`. On failure, returns `false` without touching the session.

---

### `App\Actions\RecordCredentialAccess`

**Signature:** `handle(Credential $credential, User $user, string $action): void`

Creates a `CredentialAccessLog` record directly. This is a thin wrapper to ensure consistent logging. Not intended to be called from outside the vault action layer.

---

## 9. Notifications

No notifications are sent by the vault feature.

---

## 10. Background Jobs

No queued jobs are used by the vault feature. TOTP code generation happens synchronously in the request cycle via `wire:poll`.

---

## 11. Console Commands & Scheduled Tasks

No console commands or scheduled tasks are introduced by this feature.

---

## 12. Services

### `App\Services\VaultEncrypter`

A thin wrapper around Laravel's `Illuminate\Encryption\Encrypter` using a **separate key** from `APP_KEY`.

**Constructor:** Reads `config('vault.key')` (base64-encoded 32-byte key). Throws `RuntimeException` if the key is not set, with a message instructing how to generate one.

**Methods:**

| Method | Description |
|---|---|
| `encrypt(string $value): string` | Encrypts a string using AES-256-CBC. Produces a different ciphertext for the same plaintext on each call (IV randomization). |
| `decrypt(string $payload): string` | Decrypts a previously encrypted payload. |

**Binding:** Resolved via `app(VaultEncrypter::class)` from the service container (singleton by default). Called directly from Eloquent model accessors/mutators in `Credential`.

**Key generation command (reference):**
```
php -r "echo base64_encode(random_bytes(32)).PHP_EOL;"
```

---

### `App\Services\VaultSession`

Manages the re-authentication session window.

**Session key:** `vault_unlocked_at` (stores a Unix timestamp integer).

**Methods:**

| Method | Description |
|---|---|
| `unlock(): void` | Writes `now()->timestamp` to the session under `vault_unlocked_at` |
| `isUnlocked(): bool` | Returns true if `vault_unlocked_at` exists in the session and is within the configured TTL |
| `lock(): void` | Removes `vault_unlocked_at` from the session |

**TTL check:** `now()->timestamp - $unlockedAt < $ttl * 60`, where `$ttl = config('vault.session_ttl_minutes', 30)`.

---

## 13. Activity Log Entries

Activity is recorded via `RecordActivity::run($subject, $action, $description)`.

| Action key | Subject model | Description format |
|---|---|---|
| `credential_created` | `Credential` | `"Credential \"{name}\" created by {user->name}."` |
| `credential_updated` | `Credential` | `"Credential \"{name}\" updated by {user->name}."` |
| `credential_deleted` | `User` (deleter) | `"Credential \"{name}\" deleted by {user->name}."` |
| `credential_positions_assigned` | `Credential` | `"Credential \"{name}\" position access updated by {user->name}."` |

Access log events (`created`, `updated`, `deleted`, `viewed_password`, `viewed_totp`, `positions_assigned`) go to `credential_access_logs`, not the general `activity_logs` table.

---

## 14. Data Flow Diagrams

### Password Reveal Flow

```
User clicks "Reveal" on vault.detail
    │
    ▼
revealPassword() → authorize('view', credential)
    │
    ├── VaultSession::isUnlocked() == true
    │       │
    │       └── Set $revealedPassword = credential->password (decrypted via mutator)
    │           RecordCredentialAccess: 'viewed_password'
    │
    └── VaultSession::isUnlocked() == false
            │
            └── Set reauthPurpose = 'password'
                Open reauth-modal
                    │
                User enters password → reauth()
                    │
                    ├── ReauthenticateVaultSession::run() == false
                    │       └── Set reauthError, stay in modal
                    │
                    └── ReauthenticateVaultSession::run() == true
                            │
                            └── VaultSession::unlock()
                                Set $revealedPassword = credential->password
                                RecordCredentialAccess: 'viewed_password'
                                Close reauth-modal, show toast
```

### TOTP Reveal Flow

```
User clicks "Show Code" on vault.detail
    │
    ▼
showTotp() → authorize('view', credential)
    │
    └── Always: Set reauthPurpose = 'totp', open reauth-modal
        (vault session state is irrelevant for TOTP)
            │
        User enters password → reauth()
            │
            └── ReauthenticateVaultSession::run() == true
                    │
                    └── VaultSession::unlock()
                        GenerateTotpCode::run() → {code, seconds_remaining}
                        RecordCredentialAccess: 'viewed_totp'
                        Open totp-modal
                        wire:poll.1000ms calls refreshTotp() to update countdown
```

### Staff Departure / Credential Rotation Flow

```
Admin removes a user from a staff position
    │
    ▼
UnassignStaffPosition::run($position, $actingUser)
    │
    ├── FlagCredentialsAfterPositionRemoval::run($user, $position)
    │       │
    │       └── Query: credentials assigned to $position
    │               WHERE EXISTS (access log from $user)
    │               → credential->update(['needs_password_change' => true])
    │
    └── position->update(['user_id' => null])
        (rest of unassign logic)

Vault Manager visits vault.index / vault.detail
    └── "Needs Rotation" badge shown on flagged credentials
```

### Credential Creation Flow

```
Vault Manager clicks "Add Credential" on vault.index
    │
    ▼
openCreate() → authorize('manage-vault')
    │
    └── Open create-credential-modal
            │
        User fills form → create()
            │
            ├── authorize('manage-vault')
            ├── validate()
            ├── CreateCredential::run(user, data)
            │       ├── Credential::create() — mutators encrypt sensitive fields
            │       ├── RecordActivity: 'credential_created'
            │       └── RecordCredentialAccess: 'created'
            ├── Close modal, show toast
            └── Unset $credentials computed property to force refresh
```

---

## 15. Configuration

### `config/vault.php`

| Key | Env var | Default | Description |
|---|---|---|---|
| `vault.key` | `VAULT_KEY` | `null` | Base64-encoded 32-byte AES-256-CBC encryption key. **Required.** App will throw `RuntimeException` on any vault operation if unset. |
| `vault.session_ttl_minutes` | `VAULT_SESSION_TTL_MINUTES` | `30` | How many minutes a vault session remains unlocked after re-authentication. |

**Generating a vault key:**
```bash
php -r "echo base64_encode(random_bytes(32)).PHP_EOL;"
```
Add the output to `.env` as `VAULT_KEY=<value>`.

**Important:** `VAULT_KEY` must be stored separately from `APP_KEY` and never committed to version control. A database dump + `APP_KEY` alone cannot decrypt vault data; `VAULT_KEY` is also required.

---

## 16. Test Coverage

All vault tests are in the `vault` Pest group. Run with:
```
./vendor/bin/pest --group=vault
```

### Action Tests (`tests/Feature/Actions/Vault/`)

| File | Cases covered |
|---|---|
| `CreateCredentialTest.php` | Creates record, encrypts sensitive fields, decrypts via model, sets `created_by`, defaults `needs_password_change` to false, records activity, handles nullable optional fields |
| `UpdateCredentialTest.php` | Updates plain fields, updates encrypted fields, clears `needs_password_change` on password change, does not clear flag when other fields update, does not update password on empty string, sets `updated_by`, records activity |
| `DeleteCredentialTest.php` | Deletes record, removes pivot rows, removes access logs, records activity on deleter's user model |
| `AssignCredentialPositionsTest.php` | Assigns multiple positions, removes positions via sync, clears all positions on empty array, records activity |
| `FlagCredentialsAfterPositionRemovalTest.php` | Flags credentials the departing user accessed, does not flag unaccessed credentials, does not flag credentials accessed only by others, does not flag credentials on a different position |
| `GenerateTotpCodeTest.php` | Returns a 6-digit code, returns `seconds_remaining` in 1–30 range, does not include raw secret in return value |
| `ReauthenticateVaultSessionTest.php` | Returns true and unlocks on correct password, returns false on wrong password, does not modify session on wrong password |
| `RecordCredentialAccessTest.php` | Creates a log entry, CreateCredential logs `created`, UpdateCredential logs `updated`, DeleteCredential logs `deleted` (records before clearing), AssignCredentialPositions logs `positions_assigned` |

### Policy Tests (`tests/Feature/Policies/CredentialPolicyTest.php`)

Covers: Vault Manager can view/update/delete/managePositions; position holder can view/update but not delete/managePositions; staff with no position cannot view; staff whose position is not assigned cannot view.

### Gate Tests (`tests/Feature/Gates/VaultGatesTest.php`)

Covers: `manage-vault` granted to Vault Manager, denied to JrCrew without role, denied to regular member; `view-vault` granted to JrCrew/CrewMember/Officer, denied to `StaffRank::None`.

### Service Tests

| File | Cases covered |
|---|---|
| `tests/Feature/Services/VaultEncrypterTest.php` | Round-trip encrypt/decrypt, different ciphertext per call (IV randomization), throws `RuntimeException` when `VAULT_KEY` is null |
| `tests/Feature/Services/VaultSessionTest.php` | Locked by default, unlocked after `unlock()`, locked after `lock()`, locked when timestamp is older than TTL, unlocked when timestamp is within TTL |

### Livewire Tests

| File | Cases covered |
|---|---|
| `tests/Feature/Livewire/VaultTest.php` | Vault Manager can create via index, non-Vault-Manager JrCrew cannot create; Vault Manager can delete/update credential; password reveals after successful re-auth; rejects on wrong password; reveals immediately when session already unlocked; position holder can reveal their credential's password; TOTP code shown after re-auth; TOTP always requires re-auth even with session unlocked; TOTP refresh works without re-auth |
| `tests/Feature/Livewire/CredentialAccessLogPageTest.php` | Renders for `Logs - Viewer` role, forbidden to other users, displays log entries with human-readable action text |

---

## 17. File Map

```
app/
  Actions/
    CreateCredential.php
    UpdateCredential.php
    DeleteCredential.php
    AssignCredentialPositions.php
    FlagCredentialsAfterPositionRemoval.php
    GenerateTotpCode.php
    ReauthenticateVaultSession.php
    RecordCredentialAccess.php
    UnassignStaffPosition.php          ← integrates FlagCredentialsAfterPositionRemoval
  Models/
    Credential.php
    CredentialAccessLog.php
    StaffPosition.php                  ← credentials() relationship added
  Policies/
    CredentialPolicy.php
  Providers/
    AuthServiceProvider.php            ← view-vault, manage-vault, view-credential-access-log gates
  Services/
    VaultEncrypter.php
    VaultSession.php

config/
  vault.php

database/migrations/
  2026_04_12_000001_create_credentials_table.php
  2026_04_12_000002_create_credential_staff_position_table.php
  2026_04_12_000003_create_credential_access_logs_table.php
  2026_04_12_000004_seed_vault_manager_role.php

resources/views/livewire/
  vault/
    index.blade.php
    detail.blade.php
  admin-manage-credential-access-log-page.blade.php
  admin-control-panel-tabs.blade.php  ← credential-access-log tab added

routes/
  web.php                              ← vault.index, vault.detail routes

tests/Feature/
  Actions/Vault/
    AssignCredentialPositionsTest.php
    CreateCredentialTest.php
    DeleteCredentialTest.php
    FlagCredentialsAfterPositionRemovalTest.php
    GenerateTotpCodeTest.php
    ReauthenticateVaultSessionTest.php
    RecordCredentialAccessTest.php
    UpdateCredentialTest.php
  Gates/
    VaultGatesTest.php
  Livewire/
    CredentialAccessLogPageTest.php
    VaultTest.php
  Policies/
    CredentialPolicyTest.php
  Services/
    VaultEncrypterTest.php
    VaultSessionTest.php
```

---

## 18. Known Issues & Improvement Opportunities

- **Session TTL is per-browser-session, not per-tab.** If the same user has the vault open in two tabs, unlocking in one tab unlocks both. This is inherent to session-based TTL design.
- **TOTP refresh via `wire:poll.1000ms`** polls every second for the full duration a TOTP modal is open. This is a minor performance concern but unlikely to matter in practice given typical vault usage patterns.
- **Access log cascade delete.** When a credential is deleted, all of its access logs are also deleted by `DeleteCredential` (and the `cascade` foreign key). This means post-deletion audit history for that credential is not preserved. Admins who need to audit deletions should rely on the `activity_logs` table (`credential_deleted` action on the deleter's user record) rather than `credential_access_logs`.
- **No credential factory `withTotp()` documented here.** Tests reference `Credential::factory()->withTotp()` and `Credential::factory()->needsPasswordChange()` factory states, which are expected to exist in `database/factories/CredentialFactory.php`.
- **`view-vault` gate vs `CredentialPolicy::viewAny()`** express the same JrCrew rank requirement in two places. They are consistent, but a future refactor could unify them.
- **Position holders can update but not create or delete.** There is no UI flow for a position holder to add a new credential — they can only view and update existing ones assigned to their position. This is by design but worth surfacing in user documentation.
- **No expiry / rotation reminders.** The `needs_password_change` flag requires a Vault Manager to notice the badge and manually change the password. There is no automated notification or scheduled reminder when a flag is set.
