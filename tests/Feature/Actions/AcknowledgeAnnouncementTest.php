<?php

use App\Actions\AcknowledgeAnnouncement;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class)->group('announcements', 'actions');

it('acknowledges a published announcement for a user', function () {
    $user = User::factory()->create();
    $announcement = Announcement::factory()->published()->create();

    app(AcknowledgeAnnouncement::class)->run($announcement, $user);

    // Pivot row exists
    $this->assertDatabaseHas('announcement_user', [
        'user_id' => $user->id,
        'announcement_id' => $announcement->id,
    ]);
})->done(issue: 61, assignee: 'jonzenor');

it('is idempotent when acknowledging the same announcement twice', function () {
    $user = User::factory()->create();
    $announcement = Announcement::factory()->published()->create();

    $action = app(AcknowledgeAnnouncement::class);

    $action->run($announcement, $user);
    $action->run($announcement, $user);

    // Still only one pivot row matching both IDs
    $count = DB::table('announcement_user')
        ->where('user_id', $user->id)
        ->where('announcement_id', $announcement->id)
        ->count();

    expect($count)->toBe(1);
})->done(issue: 61, assignee: 'jonzenor');

it('acknowledges an announcement for the authenticated user if no user is passed', function () {
    $announcement = Announcement::factory()->published()->create();
    $user = User::factory()->create();
    loginAs($user);

    app(AcknowledgeAnnouncement::class)->run($announcement, null);

    // Pivot row exists
    $this->assertDatabaseHas('announcement_user', [
        'user_id' => $user->id,
        'announcement_id' => $announcement->id,
    ]);
})->done(issue: 61, assignee: 'jonzenor');

it('throws an exception if the user is not authenticated', function () {
    $announcement = Announcement::factory()->published()->create();

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('User must be authenticated to acknowledge an announcement.');

    app(AcknowledgeAnnouncement::class)->run($announcement, null);
})->done(issue: 61, assignee: 'jonzenor');
