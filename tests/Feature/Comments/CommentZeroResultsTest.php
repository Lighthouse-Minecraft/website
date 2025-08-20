<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Comment Index Authorization', function () {
    it('comments index returns 200 with zero comments', function () {
        $admin = User::factory()->admin()->create();
        loginAs($admin);

        $response = $this->get(route('comments.index'));

        $response->assertOk();
        $response->assertSee('No announcement comments yet.');
        $response->assertSee('No blog comments yet.');
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
