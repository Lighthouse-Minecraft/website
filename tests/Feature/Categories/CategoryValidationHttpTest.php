<?php

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Category validation (HTTP)', function () {
    it('requires a name on store', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $res = $this->post(route('taxonomy.categories.store'), ['name' => '']);
        $res->assertStatus(302)->assertSessionHasErrors(['name']);
    })->done(assignee: 'ghostridr');

    it('requires unique name on store', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        Category::factory()->create(['name' => 'Unique Category']);
        $res = $this->post(route('taxonomy.categories.store'), ['name' => 'Unique Category']);
        $res->assertStatus(302)->assertSessionHasErrors(['name']);

        $res = $this->postJson(route('taxonomy.categories.store'), ['name' => 'Unique Category']);
        $res->assertStatus(422)->assertJsonValidationErrors(['name']);
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
