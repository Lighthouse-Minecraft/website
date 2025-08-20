<?php

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Announcement Security', function () {
    it('allows authenticated users to access an announcement (policy view returns true)', function () {
        $announcement = Announcement::factory()->create();
        $user = User::factory()->create();
        $this->actingAs($user);
        $response = $this->get('/announcements/'.$announcement->id);
        $response->assertOk();
    })->done(assignee: 'ghostridr');

    it('prevents unauthorized user from creating an announcement', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        $response = $this->post('/announcements', ['title' => 'New Announcement', 'content' => 'Announcement content']);
        expect($response->status())->toBe(403);
    })->done(assignee: 'ghostridr');

    it('prevents unauthorized user from deleting an announcement', function () {
        $announcement = Announcement::factory()->create();
        $user = User::factory()->create();
        $this->actingAs($user);
        $response = $this->delete('/announcements/'.$announcement->id);
        expect($response->status())->toBe(403);
    })->done(assignee: 'ghostridr');

    it('prevents unauthorized user from updating an announcement', function () {
        $announcement = Announcement::factory()->create();
        $user = User::factory()->create();
        $this->actingAs($user);
        $response = $this->put('/announcements/'.$announcement->id, ['title' => 'Updated Title']);
        expect($response->status())->toBe(403);
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
