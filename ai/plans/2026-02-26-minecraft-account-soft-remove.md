# Minecraft Account Soft-Remove ("Removed" Status)

## Context

When a user or admin removes a Minecraft account today, the record is **hard-deleted** from the database, losing all history of who owned that UUID. This is a problem when an account is compromised — we need to remove it from the whitelist without erasing the audit trail. This feature replaces the hard-delete with a soft-disable: the record stays in the database with a "Removed" status, preserving history and locking the UUID.

**Key behaviors:**
- "Remove" sets status to Removed (no hard-delete)
- Removed accounts don't count toward the user's max account limit
- UUID stays locked — no one can re-add the same account while the record exists
- Users and admins can **reactivate** a Removed account (re-whitelists, re-syncs rank)
- Only admins can **permanently delete** a Removed account (releases the UUID)
- Brig system is **unchanged** (keeps its own Banned status)

---

## Step 1: Migration — Add 'removed' to status constraint

**Create** `database/migrations/2026_02_26_000000_add_removed_status_to_minecraft_accounts_table.php`

Follow the pattern from `2026_02_21_100000_add_banned_status_to_minecraft_accounts_table.php`:
- `up()`: Drop and recreate PgSQL check constraint including `'removed'`
- `down()`: Convert any `removed` rows to `cancelled`, then tighten constraint back

---

## Step 2: Enum — Add Removed case

**Modify** `app/Enums/MinecraftAccountStatus.php`

- Add `case Removed = 'removed';`
- `label()`: `'Removed'`
- `color()`: `'zinc'` (gray/neutral)

---

## Step 3: Model — Add scopes

**Modify** `app/Models/MinecraftAccount.php`

Add two scopes:
- `scopeRemoved()` — where status = Removed
- `scopeCountingTowardLimit()` — whereIn Active, Verifying, Banned (these count; Removed and Cancelled don't)

---

## Step 4: Factory — Add removed() state

**Modify** `database/factories/MinecraftAccountFactory.php`

Add `removed()` state: sets status to Removed, verified_at to a past time.

---

## Step 5: Update account limit counting

**Modify** `app/Actions/GenerateVerificationCode.php` (line 48)

Change `$user->minecraftAccounts()->count()` to `$user->minecraftAccounts()->countingTowardLimit()->count()`

**Modify** `resources/views/livewire/settings/minecraft-accounts.blade.php` (line 275)

Change `remainingSlots` calculation to filter only counting statuses (Active, Verifying, Banned) from the already-loaded collection.

**Modify** `resources/views/dashboard.blade.php` (line 38)

Change `minecraftAccounts()->count()` to `minecraftAccounts()->countingTowardLimit()->count()`

---

## Step 6: UnlinkMinecraftAccount — soft-disable instead of hard-delete

**Modify** `app/Actions/UnlinkMinecraftAccount.php`

- Line 99: Replace `$account->delete()` with setting status to `MinecraftAccountStatus::Removed` and save
- Line 103: Change activity action to `'minecraft_account_removed'`
- Update success message

All RCON commands (rank reset, staff removal, whitelist removal) stay exactly as-is.

---

## Step 7: RevokeMinecraftAccount — soft-disable instead of hard-delete

**Modify** `app/Actions/RevokeMinecraftAccount.php`

- Line 67: Replace `$account->delete()` with setting status to `MinecraftAccountStatus::Removed` and save
- Line 62: Update error message from "has not been deleted" to "has not been removed"

RCON commands and the whitelist-success guard stay as-is.

---

## Step 8: Policy — Add reactivate, update forceDelete

**Modify** `app/Policies/MinecraftAccountPolicy.php`

- Add `reactivate(User $user, MinecraftAccount $account): bool` — returns `$user->id === $account->user_id || $user->isAdmin()`
- Change `forceDelete()` from `return false` to `return $user->isAdmin()`

---

## Step 9: New action — ReactivateMinecraftAccount

**Create** `app/Actions/ReactivateMinecraftAccount.php`

Using `AsAction` trait, `MinecraftRconService` (same pattern as UnlinkMinecraftAccount):
1. Verify account status is Removed
2. Check owner isn't at max limit (`countingTowardLimit()->count()`)
3. Check owner isn't in brig
4. Add to whitelist via RCON (`whitelistAddCommand()`)
5. If whitelist fails, return error (don't change status)
6. Set status to Active, save
7. Call `SyncMinecraftRanks::run($owner)`
8. If owner has `staff_department`, call `SyncMinecraftStaff::run($owner, $owner->staff_department)`
9. Log activity: `'minecraft_account_reactivated'`

---

## Step 10: New action — ForceDeleteMinecraftAccount

**Create** `app/Actions/ForceDeleteMinecraftAccount.php`

Using `AsAction` trait:
1. Verify caller `isAdmin()`
2. Verify account status is Removed (only Removed accounts can be permanently deleted)
3. Hard-delete the record (`$account->delete()`)
4. Log activity: `'minecraft_account_permanently_deleted'`

---

## Step 11: Settings UI — Removed accounts + Reactivate button

**Modify** `resources/views/livewire/settings/minecraft-accounts.blade.php`

**PHP class:**
- Add `public ?int $accountToReactivate = null;`
- Add `confirmReactivate(int $accountId)` method (shows modal)
- Add `reactivateAccount()` method (calls `ReactivateMinecraftAccount::run()`, authorizes via policy)

**Blade template (lines 310-325):** Replace the current two-branch `@if/@else` with:
- `Active` → "Remove" button (existing)
- `Removed` → "Reactivate" button (if `$remainingSlots > 0` and not in brig)
- `Verifying` → "Remove" button triggering cancel-verification modal (existing)
- `Banned` / other → no action button

**Add** a `confirm-reactivate` modal after the existing `confirm-remove` modal.

---

## Step 12: Admin profile UI — Reactivate + Delete buttons

**Modify** `resources/views/livewire/users/display-basic-details.blade.php`

**PHP class:**
- Add `reactivateMinecraftAccount(int $accountId)` method
- Add `forceDeleteMinecraftAccount(int $accountId)` method

**Blade template (lines 496-504):** Replace the single "Revoke" button with conditional logic:
- `Removed` → "Reactivate" button + "Delete" button (with `wire:confirm` warning)
- `Active` / `Banned` / `Verifying` → "Revoke" button (existing behavior)
- `Cancelled` → no button

Add a status badge next to each account name in the admin view.

---

## Step 13: Tests

**Modify** existing tests:
- `tests/Feature/Minecraft/UnlinkMinecraftAccountTest.php` — change `assertDatabaseMissing` to assert status is Removed + `assertDatabaseHas`
- `tests/Feature/Minecraft/RevokeMinecraftAccountTest.php` — same pattern
- `tests/Feature/Policies/MinecraftAccountPolicyTest.php` — add reactivate/forceDelete test cases

**Create** new test files:
- `tests/Feature/Minecraft/ReactivateMinecraftAccountTest.php`:
  - Reactivates a removed account to active
  - Fails if account is not Removed
  - Fails if at max account limit
  - Fails if user is in brig
  - Fails if whitelist add fails
  - Records activity log
- `tests/Feature/Minecraft/ForceDeleteMinecraftAccountTest.php`:
  - Admin can permanently delete a Removed account
  - Non-admin cannot permanently delete
  - Cannot delete an Active account (only Removed)
  - Records activity log
  - Releases UUID for re-registration

---

## What's NOT changing

- **Brig system** — `PutUserInBrig` and `ReleaseUserFromBrig` stay exactly as-is. They already filter by Active/Verifying, so Removed accounts are naturally skipped.
- **SyncMinecraftRanks / SyncMinecraftStaff** — already scope to Active accounts only.
- **UUID uniqueness** — existing DB constraint + app-level check in `GenerateVerificationCode` already blocks re-adding a UUID that exists in any status.
- **MinecraftVerification** / verification flow — untouched.

---

## Verification

1. `php artisan migrate` — fresh and incremental
2. `./vendor/bin/pest` — all tests pass
3. Manual: Remove an account → verify status shows "Removed", account stays in DB, removed from whitelist
4. Manual: Reactivate a removed account → verify status returns to Active, re-whitelisted, rank synced
5. Manual: Confirm removed account doesn't count toward limit (can add a new account)
6. Manual: Confirm UUID is locked (can't add same account while removed record exists)
7. Manual: Admin permanent delete → record gone, UUID released
8. Manual: Brig a user with a Removed account → verify Removed account is untouched
