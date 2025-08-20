<?php

use App\Models\Blog;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists blogs for a given category', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $cat = Category::factory()->create(['name' => 'Servers']);
    $blogIn = Blog::factory()->create();
    $blogOut = Blog::factory()->create();
    $blogIn->categories()->attach($cat->id);

    $res = $this->get(route('taxonomy.categories.blogs', $cat->id));
    $res->assertOk();
    $res->assertSee($cat->name);
    $res->assertSee($blogIn->title);
    $res->assertDontSee($blogOut->title);
})->done(assignee: 'ghostridr');
