<?php

use App\Actions\AcknowledgeBlog;
use App\Models\Blog;

use function Pest\Laravel\get;

describe('Dashboard Display', function () {
    it('displays existing blogs', function () {
        $blog = Blog::factory()->create([
            'title' => 'Test Blog',
            'content' => 'This is a test blog.',
            'is_published' => true,
        ]);
        loginAsAdmin();

        get(route('dashboard'))
            ->assertSeeLivewire('dashboard.notifications')
            ->assertSee($blog->title)
            ->assertSee($blog->content);
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');

describe('Dashboard Display - Acknowledge Blogs', function () {
    it('does not display acknowledged blogs', function () {
        $blog = Blog::factory()->create([
            'title' => 'Acknowledged Blog',
            'content' => 'This blog has been acknowledged.',
            'is_published' => true,
        ]);
        $user = loginAsAdmin();
        app(AcknowledgeBlog::class)->run($blog, $user);

        get(route('dashboard'))
            ->assertSeeLivewire('dashboard.notifications')
            // We can't check for blog title because it will still show up in the widget
            ->assertDontSee($blog->content);
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');

describe('Dashboard Display - Blogs List', function () {

    it('loads the blogs list component', function () {
        loginAsAdmin();

        get(route('dashboard'))
            ->assertSeeLivewire('dashboard.blogs-widget')
            ->assertSee('START OF BLOGS WIDGET')
            ->assertSee('END OF BLOGS WIDGET');
    })->done(assignee: 'ghostridr');

    it('displays a list of blogs in a widget', function () {
        $blog1 = Blog::factory()->create([
            'title' => 'First Blog',
            'content' => 'Content of the first blog.',
            'is_published' => true,
        ]);
        $blog2 = Blog::factory()->create([
            'title' => 'Second Blog',
            'content' => 'Content of the second blog.',
            'is_published' => true,
        ]);
        loginAsAdmin();

        get(route('dashboard'))
            ->assertSeeLivewire('dashboard.blogs-widget')
            ->assertSeeInOrder(['START OF BLOGS WIDGET', $blog1->title, 'END OF BLOGS WIDGET'])
            ->assertSeeInOrder(['START OF BLOGS WIDGET', $blog2->title, 'END OF BLOGS WIDGET']);
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
