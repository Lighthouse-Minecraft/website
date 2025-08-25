<?php

use App\Models\Blog;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Blog Filters & Pagination', function () {
    it('filters by category and tag', function () {
        $cat = Category::factory()->create();
        $tag = Tag::factory()->create();
        $b1 = Blog::factory()->create();
        $b2 = Blog::factory()->create();

        $b1->categories()->sync([$cat->id]);
        $b1->tags()->sync([$tag->id]);

        $user = User::factory()->create();
        $this->actingAs($user);
        $this->get("/blogs?category={$cat->id}")
            ->assertStatus(200)
            ->assertSee($b1->title)
            ->assertDontSee($b2->title);

        $this->actingAs($user);
        $this->get("/blogs?tag={$tag->id}")
            ->assertStatus(200)
            ->assertSee($b1->title)
            ->assertDontSee($b2->title);
    })->done(assignee: 'ghostridr');

    it('filters by search term', function () {
        Blog::factory()->create(['title' => 'Laravel Tips']);
        Blog::factory()->create(['title' => 'Minecraft Tricks']);
        $user = User::factory()->create();
        $this->actingAs($user);
        $res = $this->get('/blogs?search=Laravel');
        $res->assertStatus(200);
        $res->assertSee('Laravel Tips');
        $res->assertDontSee('Minecraft Tricks');
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
