<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Taxonomy Pagination', function () {
    it('paginates categories to 20 per page', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        Category::factory()->count(25)->create();
        $res = $this->get(route('taxonomy.categories.index'));
        $res->assertOk();
        $res->assertSee((string) Category::latest()->paginate(20)->lastPage());
    })->done(assignee: 'ghostridr');

    it('paginates tags to 20 per page', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        Tag::factory()->count(25)->create();
        $res = $this->get(route('taxonomy.tags.index'));
        $res->assertOk();
        $res->assertSee((string) Tag::latest()->paginate(20)->lastPage());
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
