<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Blog Routes Existence', function () {
    it('has expected named routes for blogs', function () {
        $routes = [
            // Public blogs routes
            'blogs.index',
            'blogs.show',
            'blogs.store',
            'blogs.update',
            'blogs.destroy',

            // ACP blogs routes
            'acp.blogs.show',
            'acp.blogs.create',
            'acp.blogs.store',
            'acp.blogs.edit',
            'acp.blogs.update',
            'acp.blogs.confirmDelete',
            'acp.blogs.delete',
            'acp.blogs.addTag',
            'acp.blogs.attachTag',
            'acp.blogs.removeTag',
            'acp.blogs.addCategory',
            'acp.blogs.attachCategory',
            'acp.blogs.removeCategory',
        ];

        foreach ($routes as $name) {
            $needsId = collect(['show', 'edit', 'update', 'delete', 'destroy', 'confirmDelete', 'attach', 'remove', 'add'])
                ->contains(fn ($needle) => str_contains($name, $needle));

            $params = [];
            if ($needsId) {
                // ACP blog routes sometimes use `{blog}` instead of `{id}`
                if (str_starts_with($name, 'acp.blogs.') && (
                    str_contains($name, 'delete') || str_contains($name, 'add') || str_contains($name, 'attach') || str_contains($name, 'remove')
                )) {
                    $params = ['blog' => 1];
                } else {
                    $params = ['id' => 1];
                }
            }

            expect(route($name, $params))->toBeString();
        }
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
