<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Announcement Routes Existence', function () {
    it('has expected named routes for announcements', function () {
        $routes = [
            // Public announcements routes
            'announcements.index',
            'announcements.show',
            'announcements.store',
            'announcements.update',
            'announcements.destroy',

            // ACP announcements routes
            'acp.announcements.show',
            'acp.announcements.create',
            'acp.announcements.store',
            'acp.announcements.edit',
            'acp.announcements.update',
            'acp.announcements.confirmDelete',
            'acp.announcements.delete',
            'acp.announcements.addTag',
            'acp.announcements.attachTag',
            'acp.announcements.removeTag',
            'acp.announcements.addCategory',
            'acp.announcements.attachCategory',
            'acp.announcements.removeCategory',
        ];

        foreach ($routes as $name) {
            // Some routes require an id to resolve
            $needsId = collect(['show', 'edit', 'update', 'delete', 'destroy', 'confirmDelete', 'attach', 'remove', 'add'])
                ->contains(fn ($needle) => str_contains($name, $needle));

            $params = [];
            if ($needsId) {
                // ACP announcement routes sometimes use `{announcement}` instead of `{id}`
                if (str_starts_with($name, 'acp.announcements.') && (
                    str_contains($name, 'delete') || str_contains($name, 'add') || str_contains($name, 'attach') || str_contains($name, 'remove')
                )) {
                    $params = ['announcement' => 1];
                } else {
                    $params = ['id' => 1];
                }
            }

            expect(route($name, $params))->toBeString();
        }
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
