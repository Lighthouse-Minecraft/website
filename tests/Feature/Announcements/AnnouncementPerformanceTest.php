<?php

use App\Models\Announcement;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Announcement Performance', function () {
    describe('Performance', function () {
        it('can bulk attach many categories to an announcement efficiently', function () {
            $announcement = Announcement::factory()->create();
            $categories = Category::factory()->count(50)->create();
            $announcement->categories()->attach($categories->pluck('id')->toArray());
            expect($announcement->categories()->count())->toBe(50);
        })->done(assignee: 'ghostridr');

        it('can bulk detach many categories from an announcement efficiently', function () {
            $announcement = Announcement::factory()->create();
            $categories = Category::factory()->count(50)->create();
            $announcement->categories()->attach($categories->pluck('id')->toArray());
            $announcement->categories()->detach($categories->pluck('id')->toArray());
            expect($announcement->categories()->count())->toBe(0);
        })->done(assignee: 'ghostridr');

        it('can bulk attach many tags to an announcement efficiently', function () {
            $announcement = Announcement::factory()->create();
            $tags = Tag::factory()->count(50)->create();
            $announcement->tags()->attach($tags->pluck('id')->toArray());
            expect($announcement->tags()->count())->toBe(50);
        })->done(assignee: 'ghostridr');

        it('can bulk detach many tags from an announcement efficiently', function () {
            $announcement = Announcement::factory()->create();
            $tags = Tag::factory()->count(50)->create();
            $announcement->tags()->attach($tags->pluck('id')->toArray());
            $announcement->tags()->detach($tags->pluck('id')->toArray());
            expect($announcement->tags()->count())->toBe(0);
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
