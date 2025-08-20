<?php

declare(strict_types=1);

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows admin to view comments index', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('comments.index'))
        ->assertSuccessful();
});

it('allows officer to view comments index', function () {
    $officer = User::factory()->create();
    $officer->staff_rank = StaffRank::Officer;
    $officer->staff_department = StaffDepartment::Command;
    $officer->save();

    $this->actingAs($officer)
        ->get(route('comments.index'))
        ->assertSuccessful();
});

it('forbids regular users from viewing comments index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('comments.index'))
        ->assertForbidden();
});
