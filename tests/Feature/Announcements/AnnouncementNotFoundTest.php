<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Announcement Not Found', function () {
    it('returns 404 for non-existent announcement', function () {
        loginAsAdmin();

        $response = $this->get(route('announcements.show', ['id' => 999999]));

        $response->assertNotFound();
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
