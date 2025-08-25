<?php

use App\Models\Blog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

if (class_exists(Livewire::class)) {
    describe('Livewire Blogs list', function () {
        it('renders and searches', function () {
            Blog::factory()->create(['title' => 'Laravel Tips']);
            Blog::factory()->create(['title' => 'Minecraft Tricks']);

            // Component alias may differ; keep this aligned with app
            Livewire::test('blogs.index')
                ->assertSee('Laravel Tips')
                ->assertSee('Minecraft Tricks')
                ->set('search', 'Laravel')
                ->assertSee('Laravel Tips')
                ->assertDontSee('Minecraft Tricks');
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');
}
