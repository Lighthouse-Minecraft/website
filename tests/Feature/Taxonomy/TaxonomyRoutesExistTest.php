<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('has expected named routes for taxonomy', function () {
    $routes = [
        'taxonomy.categories.index',
        'taxonomy.categories.show',
        'taxonomy.categories.store',
        'taxonomy.categories.update',
        'taxonomy.categories.destroy',
        'taxonomy.categories.blogs',
        'taxonomy.categories.announcements',
        'taxonomy.tags.index',
        'taxonomy.tags.show',
        'taxonomy.tags.store',
        'taxonomy.tags.update',
        'taxonomy.tags.destroy',
        'taxonomy.tags.blogs',
        'taxonomy.tags.announcements',
        'acp.taxonomy.categories.store',
        'acp.taxonomy.categories.update',
        'acp.taxonomy.categories.delete',
        'acp.taxonomy.categories.bulkDelete',
        'acp.taxonomy.tags.store',
        'acp.taxonomy.tags.update',
        'acp.taxonomy.tags.delete',
        'acp.taxonomy.tags.bulkDelete',
    ];

    foreach ($routes as $name) {
        expect(route($name, $name === 'taxonomy.categories.index' || $name === 'taxonomy.tags.index' ? [] : ['id' => 1]))->toBeString();
    }
})->done(assignee: 'ghostridr');
