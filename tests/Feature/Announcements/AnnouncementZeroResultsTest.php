<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Announcement Zero Results', function () {
    it('renders announcements index with zero results', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('announcements.index'))
            ->assertOk();
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
