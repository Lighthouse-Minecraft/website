<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Taxonomy Security', function () {
    it('prevents non-admin from creating category', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->post(route('taxonomy.categories.store'), ['name' => 'X'])
            ->assertForbidden();
    })->done(assignee: 'ghostridr');

    it('prevents non-admin from updating category', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        $cat = Category::factory()->create();
        $this->put(route('taxonomy.categories.update', $cat->id), ['name' => 'New'])
            ->assertForbidden();
    })->done(assignee: 'ghostridr');

    it('prevents non-admin from deleting category', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        $cat = Category::factory()->create();
        $this->delete(route('taxonomy.categories.destroy', $cat->id))
            ->assertForbidden();
    })->done(assignee: 'ghostridr');

    it('prevents non-admin from creating tag', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->post(route('taxonomy.tags.store'), ['name' => 'X'])
            ->assertForbidden();
    })->done(assignee: 'ghostridr');

    it('prevents non-admin from updating tag', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        $tag = Tag::factory()->create();
        $this->put(route('taxonomy.tags.update', $tag->id), ['name' => 'New'])
            ->assertForbidden();
    })->done(assignee: 'ghostridr');

    it('prevents non-admin from deleting tag', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        $tag = Tag::factory()->create();
        $this->delete(route('taxonomy.tags.destroy', $tag->id))
            ->assertForbidden();
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
