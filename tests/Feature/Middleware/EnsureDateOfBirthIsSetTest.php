<?php

declare(strict_types=1);

use App\Models\User;

use function Pest\Laravel\actingAs;

uses()->group('parent-portal', 'middleware');

it('redirects user with no date of birth to birthdate page', function () {
    $user = User::factory()->create(['date_of_birth' => null]);
    actingAs($user);

    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('birthdate.show'));
});

it('allows user with date of birth to access pages', function () {
    $user = User::factory()->adult()->create();
    actingAs($user);

    $response = $this->get(route('dashboard'));

    $response->assertOk();
});

it('allows user without DOB to access the birthdate page', function () {
    $user = User::factory()->create(['date_of_birth' => null]);
    actingAs($user);

    $response = $this->get(route('birthdate.show'));

    $response->assertOk();
});
