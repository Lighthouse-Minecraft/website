<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Announcement Authorization', function () {
    it('allows admin to create an announcement', function () {
        $this->withExceptionHandling();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $response = $this->post('/announcements', [
            'title' => 'New Announcement',
            'content' => 'Content',
            'author_id' => $admin->id,
            'is_published' => true,
        ]);
        expect($response->status())->toBe(201);
    })->done(assignee: 'ghostridr');

    it('prevents non-admin from creating an announcement', function () {
        $this->withExceptionHandling();
        $user = User::factory()->create();
        $this->actingAs($user);
        $response = $this->post('/announcements', [
            'title' => 'New Announcement',
            'content' => 'Content',
            'author_id' => $user->id,
            'is_published' => true,
        ]);
        expect($response->status())->toBe(403);
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
