<?php

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Tag Admin CRUD', function () {
    it('admin can update a tag', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $tag = Tag::factory()->create(['name' => 'Old']);
        $res = $this->put(route('taxonomy.tags.update', $tag->id), ['name' => 'New']);
        $res->assertStatus(302);
        expect($tag->fresh()->name)->toBe('New');
    })->done(assignee: 'ghostridr');

    it('admin can delete a tag', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $tag = Tag::factory()->create();
        $res = $this->delete(route('taxonomy.tags.destroy', $tag->id));
        $res->assertStatus(302);
        expect(Tag::find($tag->id))->toBeNull();
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
