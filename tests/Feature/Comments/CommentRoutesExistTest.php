<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Comment Routes Existence', function () {
    it('has expected named routes for comments', function () {
        $routes = [
            // Public comments routes
            'comments.index',
            'comments.show',
            'comments.edit',
            'comments.store',
            'comments.update',
            'comments.destroy',

            // ACP comments routes
            'acp.comments.create',
            'acp.comments.store',
            'acp.comments.edit',
            'acp.comments.update',
            'acp.comments.confirmDelete',
            'acp.comments.delete',
            'acp.comments.approve',
            'acp.comments.reject',
        ];

        foreach ($routes as $name) {
            $needsId = collect(['show', 'edit', 'update', 'delete', 'destroy', 'confirmDelete', 'approve', 'reject'])
                ->contains(fn ($needle) => str_contains($name, $needle));
            $params = $needsId ? ['id' => 1] : [];
            expect(route($name, $params))->toBeString();
        }
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
