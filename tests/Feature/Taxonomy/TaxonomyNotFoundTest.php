<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns 404 for invalid taxonomy show/list routes', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $this->get(route('taxonomy.categories.show', 999999))->assertNotFound();
    $this->get(route('taxonomy.tags.show', 999999))->assertNotFound();
    $this->get(route('taxonomy.categories.blogs', 999999))->assertNotFound();
    $this->get(route('taxonomy.tags.blogs', 999999))->assertNotFound();
    $this->get(route('taxonomy.categories.announcements', 999999))->assertNotFound();
    $this->get(route('taxonomy.tags.announcements', 999999))->assertNotFound();
})->done(assignee: 'ghostridr');
