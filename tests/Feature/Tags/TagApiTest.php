<?php

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can list tags via web page', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $tags = Tag::factory()->count(2)->create();

    $res = $this->get(route('taxonomy.tags.index'));
    $res->assertOk();
    foreach ($tags as $tag) {
        $res->assertSee($tag->name);
    }
})->done(assignee: 'ghostridr');
