<?php

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Tag Update Validation', function () {
    it('allows updating without changing name uniqueness', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $tag = Tag::factory()->create(['name' => 'Alpha']);
        $res = $this->put(route('taxonomy.tags.update', $tag->id), ['name' => 'Alpha']);
        $res->assertStatus(302);
        expect($tag->fresh()->name)->toBe('Alpha');
    })->done(assignee: 'ghostridr');

    it('rejects changing to an existing name', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $a = Tag::factory()->create(['name' => 'A']);
        $b = Tag::factory()->create(['name' => 'B']);
        $res = $this->from(route('taxonomy.tags.show', $b->id))
            ->put(route('taxonomy.tags.update', $b->id), ['name' => 'A']);
        $res->assertStatus(302);
        $res->assertSessionHasErrors(['name']);
        expect($b->fresh()->name)->toBe('B');
    })->done(assignee: 'ghostridr');

    it('rejects changing to an existing name via JSON', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $a = Tag::factory()->create(['name' => 'AA']);
        $b = Tag::factory()->create(['name' => 'BB']);
        $res = $this->putJson(route('taxonomy.tags.update', $b->id), ['name' => 'AA']);
        $res->assertUnprocessable();
        $res->assertJsonValidationErrors(['name']);
        expect($b->fresh()->name)->toBe('BB');
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
