<?php

use App\Models\Announcement;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Announcement Events', function () {
    describe('Events', function () {
        it('fires created event when an announcement is made', function () {
            $called = false;
            Announcement::created(function () use (&$called) {
                $called = true;
            });
            Announcement::factory()->create();
            expect($called)->toBeTrue();
        })->done(assignee: 'ghostridr');

        it('fires updated event when an announcement is updated', function () {
            $called = false;
            Announcement::updated(function () use (&$called) {
                $called = true;
            });
            $announcement = Announcement::factory()->create();
            $announcement->update(['title' => 'Updated Title']);
            expect($called)->toBeTrue();
        })->done(assignee: 'ghostridr');

        it('fires deleted event when an announcement is deleted', function () {
            $called = false;
            Announcement::deleted(function () use (&$called) {
                $called = true;
            });
            $announcement = Announcement::factory()->create();
            $announcement->delete();
            expect($called)->toBeTrue();
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
