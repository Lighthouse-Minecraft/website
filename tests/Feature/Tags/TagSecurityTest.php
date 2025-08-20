<?php

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Tag Security', function () {
    it('prevents unauthorized user from accessing a tag', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        $tag = Tag::factory()->create();
        $response = $this->get(route('taxonomy.tags.show', $tag->id));
        expect($response->status())->toBe(200);
    })->done(assignee: 'ghostridr');

    it('prevents unauthorized user from deleting a tag', function () {
        $tag = Tag::factory()->create();
        $user = User::factory()->create();
        $this->actingAs($user);
        $response = $this->delete(route('taxonomy.tags.destroy', $tag->id));
        expect($response->status())->toBe(403);
    })->done(assignee: 'ghostridr');

    it('prevents unauthorized user from updating a tag', function () {
        $tag = Tag::factory()->create();
        $user = User::factory()->create();
        $this->actingAs($user);
        $response = $this->put(route('taxonomy.tags.update', $tag->id), ['name' => 'Updated Tag']);
        expect($response->status())->toBe(403);
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
