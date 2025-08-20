<?php

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Category Admin CRUD', function () {
    it('admin can update a category', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $category = Category::factory()->create(['name' => 'Old']);
        $res = $this->put(route('taxonomy.categories.update', $category->id), ['name' => 'New']);
        $res->assertStatus(302);
        expect($category->fresh()->name)->toBe('New');
    })->done(assignee: 'ghostridr');

    it('admin can delete a category', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $category = Category::factory()->create();
        $res = $this->delete(route('taxonomy.categories.destroy', $category->id));
        $res->assertStatus(302);
        expect(Category::find($category->id))->toBeNull();
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
