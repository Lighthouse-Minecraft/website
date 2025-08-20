<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders empty state for blogs/announcements listing without errors', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = Category::factory()->create();
    $res = $this->get(route('taxonomy.categories.blogs', $category->id));
    $res->assertOk();

    $res = $this->get(route('taxonomy.categories.announcements', $category->id));
    $res->assertOk();

    $tag = Tag::factory()->create();
    $res = $this->get(route('taxonomy.tags.blogs', $tag->id));
    $res->assertOk();

    $res = $this->get(route('taxonomy.tags.announcements', $tag->id));
    $res->assertOk();
})->done(assignee: 'ghostridr');
