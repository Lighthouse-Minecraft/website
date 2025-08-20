<?php

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Category Update Validation', function () {
    it('allows updating without changing name uniqueness', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $cat = Category::factory()->create(['name' => 'Alpha']);
        $res = $this->put(route('taxonomy.categories.update', $cat->id), ['name' => 'Alpha']);
        $res->assertStatus(302);
        expect($cat->fresh()->name)->toBe('Alpha');
    })->done(assignee: 'ghostridr');

    it('rejects changing to an existing name', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $a = Category::factory()->create(['name' => 'A']);
        $b = Category::factory()->create(['name' => 'B']);
        $res = $this->from(route('taxonomy.categories.show', $b->id))
            ->put(route('taxonomy.categories.update', $b->id), ['name' => 'A']);
        $res->assertStatus(302);
        $res->assertSessionHasErrors(['name']);
        expect($b->fresh()->name)->toBe('B');
    })->done(assignee: 'ghostridr');

    it('rejects changing to an existing name via JSON', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $a = Category::factory()->create(['name' => 'AA']);
        $b = Category::factory()->create(['name' => 'BB']);
        $res = $this->putJson(route('taxonomy.categories.update', $b->id), ['name' => 'AA']);
        $res->assertUnprocessable();
        $res->assertJsonValidationErrors(['name']);
        expect($b->fresh()->name)->toBe('BB');
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
