<?php

declare(strict_types=1);

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('back link points to ACP when from=acp is present', function () {
    $user = User::factory()->admin()->create();
    $announcement = Announcement::factory()->for($user, 'author')->create();

    $this->actingAs($user)
        ->get(route('announcements.show', ['id' => $announcement->id, 'from' => 'acp']))
        ->assertSee(route('acp.index'));
});

it('back link points to index by default', function () {
    $user = User::factory()->create();
    $announcement = Announcement::factory()->for($user, 'author')->create();

    $this->actingAs($user)
        ->get(route('announcements.show', $announcement->id))
        ->assertSee(route('announcements.index'));
});
