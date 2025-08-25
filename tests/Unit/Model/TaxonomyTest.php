<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Tag;
use App\Models\Taxonomy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Taxonomy Model', function () {
    it('returns builders for categories and tags', function () {
        expect(Taxonomy::categories())->toBeInstanceOf(Builder::class);
        expect(Taxonomy::tags())->toBeInstanceOf(Builder::class);
    })->done(assignee: 'ghostridr');

    it('retrieves all categories and tags via collections', function () {
        Category::factory()->state(['parent_id' => null])->count(2)->create();
        Tag::factory()->count(3)->create();

        $cats = Taxonomy::allCategories();
        $tags = Taxonomy::allTags();

        expect($cats->count())->toBe(2);
        expect($tags->count())->toBe(3);
    })->done(assignee: 'ghostridr');

    it('finds category and tag by id', function () {
        $c = Category::factory()->create();
        $t = Tag::factory()->create();

        expect(Taxonomy::findCategory($c->id)?->id)->toBe($c->id);
        expect(Taxonomy::findTag($t->id)?->id)->toBe($t->id);
        expect(Taxonomy::findCategory(999999))->toBeNull();
        expect(Taxonomy::findTag(999999))->toBeNull();
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
