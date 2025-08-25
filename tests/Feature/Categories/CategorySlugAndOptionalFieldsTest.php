<?php

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Category Slug & Optional Fields', function () {
    it('generates slug on store', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $name = 'My Fancy Category';
        $res = $this->post(route('taxonomy.categories.store'), [
            'name' => $name,
            'description' => 'Desc',
            'color' => '#abc123',
        ]);
        $res->assertStatus(201);
        /** @var Category $category */
        $category = Category::query()->where('name', $name)->firstOrFail();
        expect($category->slug)->toBe('my-fancy-category');
        expect($category->description)->toBe('Desc');
        expect($category->color)->toBe('#abc123');
    })->done(assignee: 'ghostridr');

    it('shows optional fields on show page when present', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $category = Category::factory()->create([
            'name' => 'Ops',
            'description' => 'Ops Desc',
            'color' => '#ff00aa',
        ]);
        $res = $this->get(route('taxonomy.categories.show', $category->id));
        $res->assertOk();
        $res->assertSee('Ops');
        $res->assertSee('Ops Desc');
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
