<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Taxonomy Bulk Delete', function () {
    it('bulk deletes categories for admin', function () {
        $admin = User::factory()->admin()->create();
        $cats = Category::factory()->state(['parent_id' => null])->count(3)->create();

        $this->actingAs($admin)
            ->post(route('acp.taxonomy.categories.bulkDelete'), [
                'ids' => $cats->pluck('id')->all(),
            ])
            ->assertRedirect();

        expect(Category::count())->toBe(0);
    })->done(assignee: 'ghostridr');

    it('bulk deletes tags for admin', function () {
        $admin = User::factory()->admin()->create();
        $tags = Tag::factory()->count(3)->create();

        $this->actingAs($admin)
            ->post(route('acp.taxonomy.tags.bulkDelete'), [
                'ids' => $tags->pluck('id')->all(),
            ])
            ->assertRedirect();

        expect(Tag::count())->toBe(0);
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
