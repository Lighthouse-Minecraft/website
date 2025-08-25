<?php

declare(strict_types=1);

use App\Models\Announcement;
use App\Models\Blog;
use App\Models\User;

it('renders notifications component without error and shows items', function () {
    $user = User::factory()->create();

    // Create a published announcement and blog the user has not acknowledged yet
    $announcement = Announcement::factory()->published()->create([
        'author_id' => $user->id,
        'title' => 'Announcement Visible In Notifications',
    ]);

    $blog = Blog::factory()->published()->create([
        'author_id' => $user->id,
        'title' => 'Blog Visible In Notifications',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response->assertSuccessful()
        ->assertSeeLivewire('dashboard.notifications')
        ->assertSee($announcement->title)
        ->assertSee($blog->title);
});
