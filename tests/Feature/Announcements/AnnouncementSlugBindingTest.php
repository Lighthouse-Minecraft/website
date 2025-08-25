<?php

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Announcement Slug Route Model Binding', function () {
    it('resolves announcement by id (slug not supported)', function () {
        $announcement = Announcement::factory()->create();
        $user = User::factory()->create();
        $this->actingAs($user);
        $res = $this->get('/announcements/'.$announcement->id);
        $res->assertOk()->assertSee($announcement->title);
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
