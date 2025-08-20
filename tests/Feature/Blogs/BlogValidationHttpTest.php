<?php

use App\Models\Blog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Blog validation (HTTP)', function () {
    it('requires a title on store', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $res = $this->post('/blogs', ['title' => '', 'content' => 'x']);
        $res->assertStatus(302)->assertSessionHasErrors(['title']);
    })->done(assignee: 'ghostridr');

    it('requires unique title on store', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        Blog::factory()->create(['title' => 'Unique Blog']);
        $res = $this->post('/blogs', ['title' => 'Unique Blog', 'content' => 'x']);
        $res->assertStatus(302)->assertSessionHasErrors(['title']);
        $res = $this->postJson('/blogs', ['title' => 'Unique Blog', 'content' => 'x']);
        $res->assertStatus(422)->assertJsonValidationErrors(['title']);
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
