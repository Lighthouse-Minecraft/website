<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Taxonomy JSON Validation', function () {
    it('returns JSON 422 for missing and duplicate names on store', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $this->postJson(route('taxonomy.categories.store'), [])->assertUnprocessable()->assertJsonValidationErrors(['name']);
        Category::factory()->create(['name' => 'Dupe']);
        $this->postJson(route('taxonomy.categories.store'), ['name' => 'Dupe'])->assertUnprocessable()->assertJsonValidationErrors(['name']);

        $this->postJson(route('taxonomy.tags.store'), [])->assertUnprocessable()->assertJsonValidationErrors(['name']);
        Tag::factory()->create(['name' => 'Dupe']);
        $this->postJson(route('taxonomy.tags.store'), ['name' => 'Dupe'])->assertUnprocessable()->assertJsonValidationErrors(['name']);
    })->done(assignee: 'ghostridr');

    it('returns JSON 422 for duplicate names on update', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $a = Category::factory()->create(['name' => 'A']);
        Category::factory()->create(['name' => 'B']);
        $this->putJson(route('taxonomy.categories.update', $a->id), ['name' => 'B'])->assertUnprocessable()->assertJsonValidationErrors(['name']);

        $t = Tag::factory()->create(['name' => 'A']);
        Tag::factory()->create(['name' => 'B']);
        $this->putJson(route('taxonomy.tags.update', $t->id), ['name' => 'B'])->assertUnprocessable()->assertJsonValidationErrors(['name']);
    })->done(assignee: 'ghostridr');
});
