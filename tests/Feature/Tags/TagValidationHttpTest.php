<?php

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Tag validation (HTTP)', function () {
    it('requires a name on store', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $res = $this->post(route('taxonomy.tags.store'), []);
        $res->assertStatus(302);
        $res->assertSessionHasErrors(['name']);
    })->done(assignee: 'ghostridr');

    it('requires unique name on store', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        Tag::factory()->create(['name' => 'Dup']);

        $res = $this->post(route('taxonomy.tags.store'), ['name' => 'Dup']);
        $res->assertStatus(302);
        $res->assertSessionHasErrors(['name']);

        $json = $this->postJson(route('taxonomy.tags.store'), ['name' => 'Dup']);
        $json->assertUnprocessable();
        $json->assertJsonValidationErrors(['name']);
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
