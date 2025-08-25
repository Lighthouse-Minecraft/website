<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('paginates indexes and listings with disjoint sets and latest order', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    // Category index
    Category::factory()->count(25)->create();
    $page1 = $this->get(route('taxonomy.categories.index'));
    $page1->assertOk();
    $firstPageIds = $page1->viewData('categories')->getCollection()->pluck('id');

    $page2 = $this->get(route('taxonomy.categories.index', ['page' => 2]));
    $page2->assertOk();
    $secondPageIds = $page2->viewData('categories')->getCollection()->pluck('id');

    expect($firstPageIds->intersect($secondPageIds)->isEmpty())->toBeTrue();
    expect($firstPageIds->first())->toBeGreaterThan($firstPageIds->last());

    // Tags index
    Tag::factory()->count(25)->create();
    $t1 = $this->get(route('taxonomy.tags.index'));
    $t2 = $this->get(route('taxonomy.tags.index', ['page' => 2]));
    $tFirst = $t1->viewData('tags')->getCollection()->pluck('id');
    $tSecond = $t2->viewData('tags')->getCollection()->pluck('id');
    expect($tFirst->intersect($tSecond)->isEmpty())->toBeTrue();
    expect($tFirst->first())->toBeGreaterThan($tFirst->last());
})->done(assignee: 'ghostridr');
