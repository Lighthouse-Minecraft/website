<?php

use App\Models\Blog;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists blogs for a given tag', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $tag = Tag::factory()->create(['name' => 'Events']);
    $in = Blog::factory()->create();
    $out = Blog::factory()->create();
    $in->tags()->attach($tag->id);

    $res = $this->get(route('taxonomy.tags.blogs', $tag->id));
    $res->assertOk();
    $res->assertSee($tag->name);
    $res->assertSee($in->title);
    $res->assertDontSee($out->title);
})->done(assignee: 'ghostridr');
