<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Blog Authorization', function () {
    it('allows admin to create a blog', function () {
        $this->withExceptionHandling();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $response = $this->post('/blogs', [
            'title' => 'New Blog',
            'content' => 'Content',
            'author_id' => $admin->id,
            'is_published' => true,
        ]);
        expect($response->status())->toBe(201);
    })->done(assignee: 'ghostridr');

    it('prevents non-admin from creating a blog', function () {
        $this->withExceptionHandling();
        $user = User::factory()->create();
        $this->actingAs($user);
        $response = $this->post('/blogs', [
            'title' => 'New Blog',
            'content' => 'Content',
            'author_id' => $user->id,
            'is_published' => true,
        ]);
        expect($response->status())->toBe(403);
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
