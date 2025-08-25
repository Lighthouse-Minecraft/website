<?php

use App\Models\Announcement;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Announcements index', function () {
    it('renders and searches via controller', function () {
        Announcement::factory()->create(['title' => 'Laravel Tips']);
        Announcement::factory()->create(['title' => 'Minecraft Tricks']);

        // Initial load shows both
        $this->actingAs(\App\Models\User::factory()->create());
        $this->get(route('announcements.index'))
            ->assertSee('Laravel Tips')
            ->assertSee('Minecraft Tricks');

        // Search filters results
        $this->get(route('announcements.index', ['search' => 'Laravel']))
            ->assertSee('Laravel Tips')
            ->assertDontSee('Minecraft Tricks');
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
