<?php

use App\Models\Blog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Blog Security', function () {
    it('prevents unauthorized user from accessing a blog', function () {
        $blog = Blog::factory()->create();
        $user = User::factory()->create();
        $this->actingAs($user);
        $response = $this->get('/blogs/'.$blog->id);
        expect($response->status())->toBe(403);
    })->done(assignee: 'ghostridr');

    it('prevents unauthorized user from creating a blog', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        $response = $this->post('/blogs', ['title' => 'New Blog', 'content' => 'Blog content']);
        expect($response->status())->toBe(403);
    })->done(assignee: 'ghostridr');

    it('prevents unauthorized user from deleting a blog', function () {
        $blog = Blog::factory()->create();
        $user = User::factory()->create();
        $this->actingAs($user);
        $response = $this->delete('/blogs/'.$blog->id);
        expect($response->status())->toBe(403);
    })->done(assignee: 'ghostridr');

    it('prevents unauthorized user from updating a blog', function () {
        $blog = Blog::factory()->create();
        $user = User::factory()->create();
        $this->actingAs($user);
        $response = $this->put('/blogs/'.$blog->id, ['title' => 'Updated Title']);
        expect($response->status())->toBe(403);
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
