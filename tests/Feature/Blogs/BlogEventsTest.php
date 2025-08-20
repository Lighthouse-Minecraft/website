<?php

use App\Models\Blog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Blog Model Events', function () {
    describe('Events', function () {
        it('fires created event when blog is made', function () {
            $called = false;
            Blog::created(function () use (&$called) {
                $called = true;
            });
            Blog::factory()->create();
            expect($called)->toBeTrue();
        })->done(assignee: 'ghostridr');

        it('fires updated event when blog is updated', function () {
            $called = false;
            Blog::updated(function () use (&$called) {
                $called = true;
            });
            $blog = Blog::factory()->create();
            $blog->update(['title' => 'Updated Title']);
            expect($called)->toBeTrue();
        })->done(assignee: 'ghostridr');

        it('fires deleted event when blog is deleted', function () {
            $called = false;
            Blog::deleted(function () use (&$called) {
                $called = true;
            });
            $blog = Blog::factory()->create();
            $blog->delete();
            expect($called)->toBeTrue();
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');
});
