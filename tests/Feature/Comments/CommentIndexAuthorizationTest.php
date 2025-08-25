<?php

declare(strict_types=1);

use App\Models\User;

use function Pest\Laravel\get;

describe('Comment Index Authorization', function () {
    it('allows admins/officers to view index', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $res = get(route('comments.index'));
        $res->assertOk();
    })->done(assignee: 'ghostridr');

    it('forbids regular users from viewing index', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $res = get(route('comments.index'));
        $res->assertForbidden();
    })->done(assignee: 'ghostridr');

    it('forbids guests from viewing index', function () {
        $res = get(route('comments.index'));
        $res->assertForbidden();
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
