<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('generates slug on store and keeps slug stable on update', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    // Category
    $this->post(route('taxonomy.categories.store'), ['name' => 'My Category Name'])->assertCreated();
    $cat = Category::firstOrFail();
    expect($cat->slug)->toBe(Str::slug('My Category Name'));

    $oldSlug = $cat->slug;
    $this->put(route('taxonomy.categories.update', $cat->id), ['name' => 'New Name'])->assertRedirect();
    $cat->refresh();
    expect($cat->slug)->toBe($oldSlug);

    // Tag
    $this->post(route('taxonomy.tags.store'), ['name' => 'My Tag Name'])->assertCreated();
    $tag = Tag::where('name', 'My Tag Name')->firstOrFail();
    expect($tag->slug)->toBe(Str::slug('My Tag Name'));

    $oldTagSlug = $tag->slug;
    $this->put(route('taxonomy.tags.update', $tag->id), ['name' => 'New Tag Name'])->assertRedirect();
    $tag->refresh();
    expect($tag->slug)->toBe($oldTagSlug);
})->done(assignee: 'ghostridr');
