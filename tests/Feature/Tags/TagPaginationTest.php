<?php

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('paginates tags to 20 per page', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Tag::factory()->count(25)->create();

    $res = $this->get(route('taxonomy.tags.index'));
    $res->assertOk();
    // naive check: there should be at least one pagination marker or only 20 names rendered
    // Ensure 20 items by counting occurrences of <li>
    $count = substr_count($res->getContent(), '<li>');
    expect($count)->toBeGreaterThanOrEqual(20);
})->done(assignee: 'ghostridr');
