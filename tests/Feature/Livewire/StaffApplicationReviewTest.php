<?php

declare(strict_types=1);

use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses()->group('applications', 'livewire');

it('admin can see review list', function () {
    $admin = loginAsAdmin();

    Livewire::test('staff-applications.review-list')
        ->assertStatus(200);
});

it('command officer can see review list', function () {
    $officer = officerCommand();
    actingAs($officer);

    Livewire::test('staff-applications.review-list')
        ->assertStatus(200);
});

it('non-command staff cannot access review page', function () {
    $crew = crewEngineer();
    actingAs($crew);

    Livewire::test('staff-applications.review-list')
        ->assertStatus(403);
});
