<?php

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

describe('Announcement Policies', function () {
    it('allows acknowledge when policy says so', function () {
        $user = User::factory()->create();
        $announcement = Announcement::factory()->create();

        expect(Gate::forUser($user)->allows('acknowledge', $announcement))
            ->toBeTrue();
    })->done(assignee: 'ghostridr');

    it('prevents non-admin from deleting via policy', function () {
        $user = User::factory()->create();
        $announcement = Announcement::factory()->create();

        expect(Gate::forUser($user)->denies('delete', $announcement))
            ->toBeTrue();
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
