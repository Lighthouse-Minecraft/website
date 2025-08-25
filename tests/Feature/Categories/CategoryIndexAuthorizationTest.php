<?php

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Category Index Authorization', function () {
    it('allows admins to view index', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        Category::factory()->count(2)->create();
        $res = $this->get(route('taxonomy.categories.index'));
        $res->assertOk();
    })->done(assignee: 'ghostridr');

    it('denies non-staff users', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        $res = $this->get(route('taxonomy.categories.index'));
        $res->assertForbidden();
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
