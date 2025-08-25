<?php

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Category Show', function () {
    it('shows a category detail page', function () {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);
        $category = Category::factory()->create(['name' => 'Servers']);
        $res = $this->get(route('taxonomy.categories.show', $category->id));
        $res->assertOk()->assertSee('Servers');
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
