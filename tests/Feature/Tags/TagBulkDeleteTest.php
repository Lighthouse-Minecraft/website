<?php

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Tag Bulk Delete', function () {
    it('deletes selected tags', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $tags = Tag::factory()->count(3)->create();
        $ids = $tags->take(2)->pluck('id')->toArray();
        $res = $this->post(route('acp.taxonomy.tags.bulkDelete'), ['ids' => $ids]);
        $res->assertStatus(302);
        expect(Tag::whereIn('id', $ids)->exists())->toBeFalse();
        expect(Tag::find($tags->last()->id))->not()->toBeNull();
    })->done(assignee: 'ghostridr');

    it('validates ids input', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $res = $this->post(route('acp.taxonomy.tags.bulkDelete'), ['ids' => []]);
        $res->assertStatus(302);
        $res->assertSessionHasErrors(['ids']);
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
