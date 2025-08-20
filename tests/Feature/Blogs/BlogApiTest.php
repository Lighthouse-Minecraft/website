<?php

use App\Models\Blog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Blog API', function () {
    it('can list blogs via web page', function () {
        Blog::factory()->count(3)->create();
        $user = User::factory()->create();
        $this->actingAs($user);
        $res = $this->get(route('blogs.index'));
        $res->assertOk();
        $first = Blog::first();
        $res->assertSee(e($first->title));
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
