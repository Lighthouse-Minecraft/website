<?php

use App\Models\Blog;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Blog Model Performance', function () {
    it('can bulk attach many categories to a blog efficiently', function () {
        $blog = Blog::factory()->create();
        $categories = Category::factory()->count(50)->create();
        $blog->categories()->attach($categories->pluck('id')->toArray());
        expect($blog->categories()->count())->toBe(50);
    })->done(assignee: 'ghostridr');

    it('can bulk detach many categories from a blog efficiently', function () {
        $blog = Blog::factory()->create();
        $categories = Category::factory()->count(50)->create();
        $blog->categories()->attach($categories->pluck('id')->toArray());
        $blog->categories()->detach($categories->pluck('id')->toArray());
        expect($blog->categories()->count())->toBe(0);
    })->done(assignee: 'ghostridr');

    it('can bulk attach many tags to a blog efficiently', function () {
        $blog = Blog::factory()->create();
        $tags = Tag::factory()->count(50)->create();
        $blog->tags()->attach($tags->pluck('id')->toArray());
        expect($blog->tags()->count())->toBe(50);
    })->done(assignee: 'ghostridr');

    it('can bulk detach many tags from a blog efficiently', function () {
        $blog = Blog::factory()->create();
        $tags = Tag::factory()->count(50)->create();
        $blog->tags()->attach($tags->pluck('id')->toArray());
        $blog->tags()->detach($tags->pluck('id')->toArray());
        expect($blog->tags()->count())->toBe(0);
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
