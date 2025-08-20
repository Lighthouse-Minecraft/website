<?php

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Category API', function () {
    it('can list categories via web page', function () {
        Category::factory()->count(3)->create();
        $user = User::factory()->admin()->create();
        $this->actingAs($user);
        $res = $this->get(route('taxonomy.categories.index'));
        $res->assertOk();

        $first = Category::first();
        $res->assertSee(e($first->name));
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
