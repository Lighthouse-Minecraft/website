<?php

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Announcement API', function () {
    it('can list announcements via web page', function () {
        Announcement::factory()->count(3)->create();
        $user = User::factory()->create();
        $this->actingAs($user);
        $res = $this->get(route('announcements.index'));
        $res->assertOk();
        $first = Announcement::first();
        $res->assertSee(e($first->title));
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
