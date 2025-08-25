<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('ACPs for Taxonomy', function () {
    it('allows admin to create/update/delete categories and tags via ACP routes', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        // Categories
        $this->post(route('acp.taxonomy.categories.store'), ['name' => 'Cat A'])->assertRedirect();
        $catId = Category::where('name', 'Cat A')->value('id');
        $this->put(route('acp.taxonomy.categories.update', $catId), ['name' => 'Cat B'])->assertRedirect();
        $this->delete(route('acp.taxonomy.categories.delete', $catId))->assertRedirect();

        // Tags
        $this->post(route('acp.taxonomy.tags.store'), ['name' => 'Tag A'])->assertRedirect();
        $tagId = Tag::where('name', 'Tag A')->value('id');
        $this->put(route('acp.taxonomy.tags.update', $tagId), ['name' => 'Tag B'])->assertRedirect();
        $this->delete(route('acp.taxonomy.tags.delete', $tagId))->assertRedirect();
    })->done(assignee: 'ghostridr');

    it('forbids non-admin to create/update/delete via ACP routes', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post(route('acp.taxonomy.categories.store'), ['name' => 'X'])->assertForbidden();
        $cat = Category::factory()->create();
        $this->put(route('acp.taxonomy.categories.update', $cat->id), ['name' => 'Y'])->assertForbidden();
        $this->delete(route('acp.taxonomy.categories.delete', $cat->id))->assertForbidden();

        $this->post(route('acp.taxonomy.tags.store'), ['name' => 'X'])->assertForbidden();
        $tag = Tag::factory()->create();
        $this->put(route('acp.taxonomy.tags.update', $tag->id), ['name' => 'Y'])->assertForbidden();
        $this->delete(route('acp.taxonomy.tags.delete', $tag->id))->assertForbidden();
    })->done(assignee: 'ghostridr');
});
