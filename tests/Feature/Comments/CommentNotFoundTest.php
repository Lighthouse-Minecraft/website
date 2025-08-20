<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Comment Not Found', function () {
    it('returns 404 for non-existent comment on show', function () {
        loginAsAdmin();

        $response = $this->get(route('comments.show', ['id' => 999999]));

        $response->assertNotFound();
    })->done(assignee: 'ghostridr');

    it('returns 404 for non-existent comment on edit', function () {
        loginAsAdmin();

        $response = $this->get(route('comments.edit', ['id' => 999999]));

        $response->assertNotFound();
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
