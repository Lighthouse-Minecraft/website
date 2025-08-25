<?php

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Category Bulk Delete', function () {
    it('deletes selected categories', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $cats = Category::factory()->count(3)->create();
        $ids = $cats->take(2)->pluck('id')->toArray();
        $res = $this->post(route('acp.taxonomy.categories.bulkDelete'), ['ids' => $ids]);
        $res->assertStatus(302);
        expect(Category::whereIn('id', $ids)->exists())->toBeFalse();
        expect(Category::find($cats->last()->id))->not()->toBeNull();
    })->done(assignee: 'ghostridr');

    it('validates ids input', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $res = $this->post(route('acp.taxonomy.categories.bulkDelete'), ['ids' => []]);
        $res->assertStatus(302);
        $res->assertSessionHasErrors(['ids']);
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
