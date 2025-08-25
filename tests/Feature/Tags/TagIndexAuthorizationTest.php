<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Tag Index Authorization', function () {
    it('allows admins to view index', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $res = $this->get(route('taxonomy.tags.index'));
        $res->assertOk();
    })->done(assignee: 'ghostridr');

    it('allows non-admins to view index per policy', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        $res = $this->get(route('taxonomy.tags.index'));
        $res->assertOk();
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
