<?php

declare(strict_types=1);

use App\Models\User;

uses()->group('user-slug', 'profile');

it('resolves profile route by user slug', function () {
    $viewer = User::factory()->create();
    $target = User::factory()->create(['name' => 'Test User']);

    $this->actingAs($viewer)
        ->get('/profile/test-user')
        ->assertOk();
});

it('returns 404 for non-existent slug', function () {
    $viewer = User::factory()->create();

    $this->actingAs($viewer)
        ->get('/profile/nonexistent-slug')
        ->assertNotFound();
});

it('generates correct profile url using route helper with user model', function () {
    $user = User::factory()->create(['name' => 'Profile Test']);

    $url = route('profile.show', $user);

    expect($url)->toContain('/profile/profile-test');
});
