<?php

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Announcement validation (HTTP)', function () {
    it('requires a title on store', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $res = $this->post('/announcements', ['title' => '', 'content' => 'x']);
        $res->assertStatus(302)->assertSessionHasErrors(['title']);
    })->done(assignee: 'ghostridr');

    it('requires unique title on store', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        Announcement::factory()->create(['title' => 'Unique Announcement']);
        $res = $this->post('/announcements', ['title' => 'Unique Announcement', 'content' => 'x']);
        $res->assertStatus(302)->assertSessionHasErrors(['title']);
        $res = $this->postJson('/announcements', ['title' => 'Unique Announcement', 'content' => 'x']);
        $res->assertStatus(422)->assertJsonValidationErrors(['title']);
    });
})->done(assignee: 'ghostridr');
