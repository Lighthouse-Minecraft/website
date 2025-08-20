<?php

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Category Security', function () {
    it('prevents unauthorized user from accessing a category', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = Category::factory()->create();
        $response = $this->get(route('taxonomy.categories.show', $category->id));
        expect($response->status())->toBe(200);
    })->done(assignee: 'ghostridr');

    it('prevents unauthorized user from deleting a category', function () {
        $category = Category::factory()->create();
        $user = User::factory()->create();
        $this->actingAs($user);
        $response = $this->delete(route('taxonomy.categories.destroy', $category->id));
        expect($response->status())->toBe(403);
    })->done(assignee: 'ghostridr');

    it('prevents unauthorized user from updating a category', function () {
        $category = Category::factory()->create();
        $user = User::factory()->create();
        $this->actingAs($user);
        $response = $this->put(route('taxonomy.categories.update', $category->id), ['name' => 'Updated Category']);
        expect($response->status())->toBe(403);
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
