<?php

declare(strict_types=1);

use App\Actions\UpdateCredential;
use App\Models\Credential;
use App\Models\User;

uses()->group('vault', 'actions');

it('updates plain fields', function () {
    $user = User::factory()->withRole('Vault Manager')->create();
    $credential = Credential::factory()->create(['created_by' => $user->id]);

    $updated = UpdateCredential::run($credential, $user, [
        'name' => 'New Name',
        'website_url' => 'https://example.com',
    ]);

    expect($updated->name)->toBe('New Name')
        ->and($updated->website_url)->toBe('https://example.com');
});

it('updates encrypted fields correctly', function () {
    $user = User::factory()->withRole('Vault Manager')->create();
    $credential = Credential::factory()->create(['created_by' => $user->id]);

    $updated = UpdateCredential::run($credential, $user, [
        'username' => 'newuser',
        'email' => 'new@example.com',
    ]);

    expect($updated->username)->toBe('newuser')
        ->and($updated->email)->toBe('new@example.com');
});

it('clears needs_password_change when password is updated', function () {
    $user = User::factory()->withRole('Vault Manager')->create();
    $credential = Credential::factory()->needsPasswordChange()->create(['created_by' => $user->id]);

    expect($credential->needs_password_change)->toBeTrue();

    $updated = UpdateCredential::run($credential, $user, [
        'password' => 'brand-new-password',
    ]);

    expect($updated->needs_password_change)->toBeFalse();
});

it('stamps password_changed_at when password is updated', function () {
    $user = User::factory()->withRole('Vault Manager')->create();
    $credential = Credential::factory()->create(['created_by' => $user->id]);

    $before = now()->subSecond();
    $updated = UpdateCredential::run($credential, $user, ['password' => 'brand-new-password']);

    expect($updated->password_changed_at)->not->toBeNull()
        ->and($updated->password_changed_at->isAfter($before))->toBeTrue();
});

it('does not update password_changed_at when only other fields are updated', function () {
    $user = User::factory()->withRole('Vault Manager')->create();
    $original = now()->subDay();
    $credential = Credential::factory()->create([
        'created_by' => $user->id,
        'password_changed_at' => $original,
    ]);

    UpdateCredential::run($credential, $user, ['name' => 'Different Name']);

    expect($credential->fresh()->password_changed_at->toDateString())->toBe($original->toDateString());
});

it('does not clear needs_password_change when only other fields are updated', function () {
    $user = User::factory()->withRole('Vault Manager')->create();
    $credential = Credential::factory()->needsPasswordChange()->create(['created_by' => $user->id]);

    $updated = UpdateCredential::run($credential, $user, [
        'username' => 'updateduser',
        'notes' => 'Updated notes only',
    ]);

    expect($updated->needs_password_change)->toBeTrue();
});

it('does not update password when password field is empty string', function () {
    $user = User::factory()->withRole('Vault Manager')->create();
    $credential = Credential::factory()->needsPasswordChange()->create(['created_by' => $user->id]);
    $originalPassword = $credential->password;

    $updated = UpdateCredential::run($credential, $user, [
        'password' => '',
        'name' => 'Some Update',
    ]);

    expect($updated->password)->toBe($originalPassword)
        ->and($updated->needs_password_change)->toBeTrue();
});

it('sets updated_by to the updater', function () {
    $creator = User::factory()->withRole('Vault Manager')->create();
    $updater = User::factory()->withRole('Vault Manager')->create();
    $credential = Credential::factory()->create(['created_by' => $creator->id]);

    $updated = UpdateCredential::run($credential, $updater, ['name' => 'Changed']);

    expect($updated->updated_by)->toBe($updater->id);
});

it('records activity on update', function () {
    $user = User::factory()->withRole('Vault Manager')->create();
    $credential = Credential::factory()->create(['created_by' => $user->id]);

    UpdateCredential::run($credential, $user, ['name' => 'Changed']);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => Credential::class,
        'subject_id' => $credential->id,
        'action' => 'credential_updated',
    ]);
});
