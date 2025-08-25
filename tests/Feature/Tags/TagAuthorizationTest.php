<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Tag Authorization', function () {
    it('allows admin to create a tag', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $res = $this->post(route('taxonomy.tags.store'), [
            'name' => 'Survival',
        ]);
        $res->assertStatus(201);
        $this->assertDatabaseHas('tags', ['name' => 'Survival']);
    })->done(assignee: 'ghostridr');

    it('prevents non-admin from creating a tag', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $res = $this->post(route('taxonomy.tags.store'), [
            'name' => 'Events',
        ]);
        $res->assertForbidden();
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
