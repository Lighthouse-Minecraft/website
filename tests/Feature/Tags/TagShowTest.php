<?php

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows a tag detail page', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);
    $tag = Tag::factory()->create(['name' => 'Gameplay', 'description' => 'Desc']);
    $res = $this->get(route('taxonomy.tags.show', $tag->id));
    $res->assertOk()->assertSee('Gameplay')->assertSee('Desc');
})->done(assignee: 'ghostridr');
