<?php

use App\Enums\StaffDepartment;
use App\Enums\ThreadStatus;
use App\Models\Message;
use App\Models\SiteConfig;
use App\Models\Thread;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(config('filesystems.public_disk'));
});

it('purges images from closed tickets older than configured days', function () {
    SiteConfig::setValue('message_image_purge_days', '30');

    $thread = Thread::factory()
        ->withDepartment(StaffDepartment::Command)
        ->withStatus(ThreadStatus::Closed)
        ->create(['closed_at' => now()->subDays(31)]);

    Storage::disk(config('filesystems.public_disk'))->put('message-images/old-ticket.jpg', 'dummy');

    $message = Message::factory()->forThread($thread)->create([
        'image_path' => 'message-images/old-ticket.jpg',
        'image_was_purged' => false,
    ]);

    $this->artisan('messages:purge-images')
        ->expectsOutputToContain('Purged 1 message image(s)')
        ->assertSuccessful();

    $message->refresh();
    expect($message->image_path)->toBeNull();
    expect($message->image_was_purged)->toBeTrue();
    Storage::disk(config('filesystems.public_disk'))->assertMissing('message-images/old-ticket.jpg');
});

it('does not purge images from open tickets', function () {
    SiteConfig::setValue('message_image_purge_days', '30');

    $thread = Thread::factory()
        ->withDepartment(StaffDepartment::Command)
        ->withStatus(ThreadStatus::Open)
        ->create(['closed_at' => null]);

    Storage::disk(config('filesystems.public_disk'))->put('message-images/open-ticket.jpg', 'dummy');

    $message = Message::factory()->forThread($thread)->create([
        'image_path' => 'message-images/open-ticket.jpg',
        'image_was_purged' => false,
    ]);

    $this->artisan('messages:purge-images')->assertSuccessful();

    $message->refresh();
    expect($message->image_path)->toBe('message-images/open-ticket.jpg');
    expect($message->image_was_purged)->toBeFalse();
});

it('does not purge images from recently closed tickets', function () {
    SiteConfig::setValue('message_image_purge_days', '30');

    $thread = Thread::factory()
        ->withDepartment(StaffDepartment::Command)
        ->withStatus(ThreadStatus::Closed)
        ->create(['closed_at' => now()->subDays(10)]);

    Storage::disk(config('filesystems.public_disk'))->put('message-images/recent-ticket.jpg', 'dummy');

    $message = Message::factory()->forThread($thread)->create([
        'image_path' => 'message-images/recent-ticket.jpg',
        'image_was_purged' => false,
    ]);

    $this->artisan('messages:purge-images')->assertSuccessful();

    $message->refresh();
    expect($message->image_path)->toBe('message-images/recent-ticket.jpg');
    expect($message->image_was_purged)->toBeFalse();
});

it('purges images from locked topics older than configured days', function () {
    SiteConfig::setValue('message_image_purge_days', '30');

    $thread = Thread::factory()->topic()->locked()->create([
        'locked_at' => now()->subDays(31),
    ]);

    Storage::disk(config('filesystems.public_disk'))->put('message-images/old-topic.jpg', 'dummy');

    $message = Message::factory()->forThread($thread)->create([
        'image_path' => 'message-images/old-topic.jpg',
        'image_was_purged' => false,
    ]);

    $this->artisan('messages:purge-images')
        ->expectsOutputToContain('Purged 1 message image(s)')
        ->assertSuccessful();

    $message->refresh();
    expect($message->image_path)->toBeNull();
    expect($message->image_was_purged)->toBeTrue();
    Storage::disk(config('filesystems.public_disk'))->assertMissing('message-images/old-topic.jpg');
});

it('does not purge images from unlocked topics', function () {
    SiteConfig::setValue('message_image_purge_days', '30');

    $thread = Thread::factory()->topic()->create([
        'is_locked' => false,
        'locked_at' => null,
    ]);

    Storage::disk(config('filesystems.public_disk'))->put('message-images/unlocked-topic.jpg', 'dummy');

    $message = Message::factory()->forThread($thread)->create([
        'image_path' => 'message-images/unlocked-topic.jpg',
        'image_was_purged' => false,
    ]);

    $this->artisan('messages:purge-images')->assertSuccessful();

    $message->refresh();
    expect($message->image_path)->toBe('message-images/unlocked-topic.jpg');
    expect($message->image_was_purged)->toBeFalse();
});

it('sets image_was_purged flag and nulls image_path', function () {
    SiteConfig::setValue('message_image_purge_days', '0');

    $thread = Thread::factory()
        ->withDepartment(StaffDepartment::Command)
        ->withStatus(ThreadStatus::Closed)
        ->create(['closed_at' => now()->subDay()]);

    $message = Message::factory()->forThread($thread)->create([
        'image_path' => 'message-images/to-purge.jpg',
        'image_was_purged' => false,
    ]);

    $this->artisan('messages:purge-images')->assertSuccessful();

    $message->refresh();
    expect($message->image_path)->toBeNull();
    expect($message->image_was_purged)->toBeTrue();
});

it('deletes file from storage', function () {
    SiteConfig::setValue('message_image_purge_days', '0');

    $thread = Thread::factory()
        ->withDepartment(StaffDepartment::Command)
        ->withStatus(ThreadStatus::Closed)
        ->create(['closed_at' => now()->subDay()]);

    Storage::disk(config('filesystems.public_disk'))->put('message-images/delete-me.jpg', 'dummy content');
    Storage::disk(config('filesystems.public_disk'))->assertExists('message-images/delete-me.jpg');

    Message::factory()->forThread($thread)->create([
        'image_path' => 'message-images/delete-me.jpg',
        'image_was_purged' => false,
    ]);

    $this->artisan('messages:purge-images')->assertSuccessful();

    Storage::disk(config('filesystems.public_disk'))->assertMissing('message-images/delete-me.jpg');
});
