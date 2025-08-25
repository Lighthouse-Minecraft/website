<?php

use App\Models\Blog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Blog Slug Route Model Binding', function () {
    it('resolves blog by slug', function () {
        $blog = Blog::factory()->create(['slug' => 'my-post', 'is_public' => true]);
        $user = User::factory()->create();
        $this->actingAs($user);
        $res = $this->get('/blogs/my-post');
        $res->assertOk()->assertSee($blog->title);
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
