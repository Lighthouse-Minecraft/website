<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Taxonomy Validation', function () {
    it('validates category store name required and unique', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $this->post(route('taxonomy.categories.store'), [])
            ->assertSessionHasErrors(['name']);

        Category::factory()->create(['name' => 'Dupe']);
        $this->post(route('taxonomy.categories.store'), ['name' => 'Dupe'])
            ->assertSessionHasErrors(['name']);
    })->done(assignee: 'ghostridr');

    it('validates category update unique on name change', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $a = Category::factory()->create(['name' => 'A']);
        Category::factory()->create(['name' => 'B']);

        $this->put(route('taxonomy.categories.update', $a->id), ['name' => 'B'])
            ->assertSessionHasErrors(['name']);
    })->done(assignee: 'ghostridr');

    it('validates tag store name required and unique', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $this->post(route('taxonomy.tags.store'), [])
            ->assertSessionHasErrors(['name']);

        Tag::factory()->create(['name' => 'Dupe']);
        $this->post(route('taxonomy.tags.store'), ['name' => 'Dupe'])
            ->assertSessionHasErrors(['name']);
    })->done(assignee: 'ghostridr');

    it('validates tag update unique on name change', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $a = Tag::factory()->create(['name' => 'A']);
        Tag::factory()->create(['name' => 'B']);

        $this->put(route('taxonomy.tags.update', $a->id), ['name' => 'B'])
            ->assertSessionHasErrors(['name']);
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
