<?php

declare(strict_types=1);

use App\Models\Announcement;
use App\Models\User;
use Livewire\Livewire;

uses()->group('announcements', 'admin');

it('admin can create announcement via modal', function () {
    loginAsAdmin();

    Livewire::test('admin-manage-announcements-page')
        ->set('newTitle', 'New Announcement')
        ->set('newContent', '# Hello World')
        ->set('newIsPublished', true)
        ->call('createAnnouncement');

    $this->assertDatabaseHas('announcements', ['title' => 'New Announcement']);
});

it('admin can update announcement via modal', function () {
    loginAsAdmin();
    $announcement = Announcement::factory()->published()->create();

    Livewire::test('admin-manage-announcements-page')
        ->call('openEditModal', $announcement->id)
        ->set('editTitle', 'Updated Title')
        ->call('updateAnnouncement');

    expect($announcement->fresh()->title)->toBe('Updated Title');
});

it('admin can delete announcement', function () {
    loginAsAdmin();
    $announcement = Announcement::factory()->published()->create();

    Livewire::test('admin-manage-announcements-page')
        ->call('deleteAnnouncement', $announcement->id);

    $this->assertDatabaseMissing('announcements', ['id' => $announcement->id]);
});

it('stores expired_at when creating announcement', function () {
    loginAsAdmin();
    $expiry = now()->addWeek()->format('Y-m-d\TH:i');

    Livewire::test('admin-manage-announcements-page')
        ->set('newTitle', 'Expiring Announcement')
        ->set('newContent', 'Content')
        ->set('newIsPublished', true)
        ->set('newExpiredAt', $expiry)
        ->call('createAnnouncement');

    $announcement = Announcement::where('title', 'Expiring Announcement')->first();
    expect($announcement->expired_at)->not->toBeNull();
});

it('setting published_at without toggling publish creates a scheduled announcement', function () {
    loginAsAdmin();
    $futureDate = now()->addHours(2)->format('Y-m-d\TH:i');

    Livewire::test('admin-manage-announcements-page')
        ->set('newTitle', 'Scheduled Announcement')
        ->set('newContent', 'Content')
        ->set('newIsPublished', false)
        ->set('newPublishedAt', $futureDate)
        ->call('createAnnouncement');

    $announcement = Announcement::where('title', 'Scheduled Announcement')->first();
    expect($announcement->is_published)->toBeTrue()
        ->and($announcement->published_at)->not->toBeNull();
});

it('unauthorized user cannot create announcement', function () {
    $user = User::factory()->create();
    loginAs($user);

    Livewire::test('admin-manage-announcements-page')
        ->set('newTitle', 'Attempt')
        ->set('newContent', 'Content')
        ->call('createAnnouncement')
        ->assertForbidden();
});
