<?php

use App\Models\Blog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

describe('Blog Policies', function () {
    it('allows acknowledge when policy says so', function () {
        $user = User::factory()->create();
        $blog = Blog::factory()->create();

        expect(Gate::forUser($user)->allows('acknowledge', $blog))
            ->toBeTrue();
    })->done(assignee: 'ghostridr');

    it('prevents non-admin from deleting via policy', function () {
        $user = User::factory()->create();
        $blog = Blog::factory()->create();

        expect(Gate::forUser($user)->denies('delete', $blog))
            ->toBeTrue();
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
