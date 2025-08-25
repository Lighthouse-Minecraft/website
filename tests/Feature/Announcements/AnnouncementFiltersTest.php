<?php

use App\Models\Announcement;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Announcement Filters & Pagination', function () {
    it('filters by category and tag', function () {
        $cat = Category::factory()->create();
        $tag = Tag::factory()->create();
        $a1 = Announcement::factory()->create();
        $a2 = Announcement::factory()->create();
        $a1->categories()->sync([$cat->id]);
        $a1->tags()->sync([$tag->id]);
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->get("/announcements?category={$cat->id}")
            ->assertStatus(200)
            ->assertSee($a1->title)
            ->assertDontSee($a2->title);
        $this->actingAs($user);
        $this->get("/announcements?tag={$tag->id}")
            ->assertStatus(200)
            ->assertSee($a1->title)
            ->assertDontSee($a2->title);
    })->done(assignee: 'ghostridr');

    it('filters by search term', function () {
        Announcement::factory()->create(['title' => 'Laravel Tips']);
        Announcement::factory()->create(['title' => 'Minecraft Tricks']);
        $user = User::factory()->create();
        $this->actingAs($user);
        $res = $this->get('/announcements?search=Laravel');
        $res->assertStatus(200);
        $res->assertSee('Laravel Tips');
        $res->assertDontSee('Minecraft Tricks');
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
