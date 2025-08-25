<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Blog Not Found', function () {
    it('returns 404 for non-existent blog', function () {
        loginAsAdmin();

        $response = $this->get(route('blogs.show', ['id' => 999999]));

        $response->assertNotFound();
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
