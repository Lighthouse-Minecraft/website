<?php

use App\Models\Blog;
use App\Models\User;
use Database\Factories\TaxonomyFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists blogs for a given tag', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $tag = TaxonomyFactory::createTag(['name' => 'Events'], blogs: 1);
    $tag->loadMissing('blogs');
    $in = $tag->blogs->first();
    $out = Blog::factory()->create();

    $res = $this->get(route('taxonomy.tags.blogs', $tag->id));
    $res->assertOk();
    $res->assertSee($tag->name);
    $res->assertSee($in->title);
    $res->assertDontSee($out->title);
})->done(assignee: 'ghostridr');
