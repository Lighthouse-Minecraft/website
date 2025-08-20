<?php

use App\Models\Blog;
use App\Models\User;
use Database\Factories\TaxonomyFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists blogs for a given category', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $cat = TaxonomyFactory::createCategory(['name' => 'Servers'], blogs: 1);
    $cat->loadMissing('blogs');
    $blogIn = $cat->blogs->first();
    $blogOut = Blog::factory()->create();

    $res = $this->get(route('taxonomy.categories.blogs', $cat->id));
    $res->assertOk();
    $res->assertSee($cat->name);
    $res->assertSee($blogIn->title);
    $res->assertDontSee($blogOut->title);
})->done(assignee: 'ghostridr');
