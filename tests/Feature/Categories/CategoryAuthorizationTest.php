<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Category Authorization', function () {
    it('allows admin to create a category', function () {
        $this->withExceptionHandling();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $response = $this->post(route('taxonomy.categories.store'), ['name' => 'New Category']);
        expect($response->status())->toBe(201);
    })->done(assignee: 'ghostridr');

    it('prevents non-admin from creating a category', function () {
        $this->withExceptionHandling();
        $user = User::factory()->create();
        $this->actingAs($user);
        $response = $this->post(route('taxonomy.categories.store'), ['name' => 'New Category']);
        expect($response->status())->toBe(403);
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
