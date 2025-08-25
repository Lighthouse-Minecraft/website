<?php

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Tag Slug & Optional Fields', function () {
    it('generates slug on store', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $name = 'My Fancy Tag';
        $res = $this->post(route('taxonomy.tags.store'), [
            'name' => $name,
            'description' => 'Desc',
            'color' => '#abc123',
        ]);
        $res->assertStatus(201);
        /** @var Tag $tag */
        $tag = Tag::query()->where('name', $name)->firstOrFail();
        expect($tag->slug)->toBe('my-fancy-tag');
        expect($tag->description)->toBe('Desc');
        expect($tag->color)->toBe('#abc123');
    })->done(assignee: 'ghostridr');

    it('shows optional fields on show page when present', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $tag = Tag::factory()->create([
            'name' => 'Ops',
            'description' => 'Ops Desc',
            'color' => '#ff00aa',
        ]);
        $res = $this->get(route('taxonomy.tags.show', $tag->id));
        $res->assertOk();
        $res->assertSee('Ops');
        $res->assertSee('Ops Desc');
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
