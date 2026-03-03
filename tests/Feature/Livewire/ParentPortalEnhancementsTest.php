<?php

declare(strict_types=1);

use App\Actions\CreateChildAccount;
use App\Models\MinecraftVerification;
use App\Models\ParentChildLink;
use App\Models\User;
use Illuminate\Support\Facades\Password;

use function Pest\Laravel\actingAs;

uses()->group('parent-portal');

it('rejects child creation when age is 17+', function () {
    $parent = User::factory()->adult()->create();
    actingAs($parent);

    Password::shouldReceive('sendResetLink')->never();

    Livewire\Volt\Volt::test('parent-portal.index')
        ->set('newChildName', 'Old Teen')
        ->set('newChildEmail', 'old-teen@example.com')
        ->set('newChildDob', now()->subYears(18)->format('Y-m-d'))
        ->call('createChildAccount')
        ->assertHasErrors(['newChildDob']);

    expect(User::where('email', 'old-teen@example.com')->exists())->toBeFalse();
});

it('allows child creation when age is 16', function () {
    $parent = User::factory()->adult()->create();
    actingAs($parent);

    Password::shouldReceive('sendResetLink')->once()->andReturn(Password::RESET_LINK_SENT);

    Livewire\Volt\Volt::test('parent-portal.index')
        ->set('newChildName', 'Young Teen')
        ->set('newChildEmail', 'young-teen@example.com')
        ->set('newChildDob', now()->subYears(16)->format('Y-m-d'))
        ->call('createChildAccount')
        ->assertHasNoErrors(['newChildDob']);

    expect(User::where('email', 'young-teen@example.com')->exists())->toBeTrue();
});

it('sets MC/Discord to false for under-13 child', function () {
    $parent = User::factory()->adult()->create();

    Password::shouldReceive('sendResetLink')->once()->andReturn(Password::RESET_LINK_SENT);

    $child = CreateChildAccount::run(
        $parent,
        'Young Child',
        'young-child@example.com',
        now()->subYears(10)->format('Y-m-d')
    );

    expect($child->parent_allows_site)->toBeTrue()
        ->and($child->parent_allows_minecraft)->toBeFalse()
        ->and($child->parent_allows_discord)->toBeFalse();
});

it('sets MC/Discord to true for 13+ child', function () {
    $parent = User::factory()->adult()->create();

    Password::shouldReceive('sendResetLink')->once()->andReturn(Password::RESET_LINK_SENT);

    $child = CreateChildAccount::run(
        $parent,
        'Teen',
        'teen-child@example.com',
        now()->subYears(14)->format('Y-m-d')
    );

    expect($child->parent_allows_site)->toBeTrue()
        ->and($child->parent_allows_minecraft)->toBeTrue()
        ->and($child->parent_allows_discord)->toBeTrue();
});

it('rejects MC code generation when parent_allows_minecraft is false', function () {
    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->create(['parent_allows_minecraft' => false]);
    ParentChildLink::create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);
    actingAs($parent);

    Livewire\Volt\Volt::test('parent-portal.index')
        ->set('childMcUsernames.'.$child->id, 'TestPlayer')
        ->call('generateChildMcCode', $child->id);

    expect(MinecraftVerification::where('user_id', $child->id)->exists())->toBeFalse();
});

it('rejects MC code generation for non-child user', function () {
    $parent = User::factory()->adult()->create();
    $stranger = User::factory()->create();
    actingAs($parent);

    Livewire\Volt\Volt::test('parent-portal.index')
        ->set('childMcUsernames.'.$stranger->id, 'TestPlayer')
        ->call('generateChildMcCode', $stranger->id);

    expect(MinecraftVerification::where('user_id', $stranger->id)->exists())->toBeFalse();
});
