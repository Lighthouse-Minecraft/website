<?php

declare(strict_types=1);

use App\Enums\MembershipLevel;
use App\Models\User;

test('guest visiting a profile is redirected to login', function () {
    $user = User::factory()->create();

    $this->get(route('profile.show', $user))
        ->assertRedirect(route('login'));
});

test('guest visiting acp page create is redirected to login', function () {
    $this->get(route('admin.pages.create'))
        ->assertRedirect(route('login'));
});

test('guest visiting acp page edit is redirected to login', function () {
    $page = \App\Models\Page::create([
        'title' => 'Test Page',
        'slug' => 'test-page',
        'content' => 'Test content',
    ]);

    $this->get(route('admin.pages.edit', $page))
        ->assertRedirect(route('login'));
});

test('authenticated traveler can view another users profile', function () {
    $viewer = User::factory()->create(['membership_level' => MembershipLevel::Traveler]);
    $target = User::factory()->create();

    $this->actingAs($viewer)
        ->get(route('profile.show', $target))
        ->assertOk();
});

test('authenticated user without permission gets 403 for another users profile', function () {
    $viewer = User::factory()->create(['membership_level' => MembershipLevel::Stowaway]);
    $target = User::factory()->create();

    $this->actingAs($viewer)
        ->get(route('profile.show', $target))
        ->assertForbidden();
});
