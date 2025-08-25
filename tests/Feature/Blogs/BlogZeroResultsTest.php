<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Blog Zero Results', function () {
    it('renders blogs index with zero results', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('blogs.index'))
            ->assertOk();
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
