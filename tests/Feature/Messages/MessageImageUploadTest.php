<?php

use App\Enums\MessageKind;
use App\Enums\StaffDepartment;
use App\Enums\ThreadStatus;
use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

beforeEach(function () {
    Storage::fake(config('filesystems.public_disk'));
    User::firstOrCreate(['email' => 'system@lighthouse.local'], User::factory()->raw());
});

it('allows uploading an image with a ticket reply', function () {
    $user = membershipCitizen();
    $thread = Thread::factory()
        ->withDepartment(StaffDepartment::Command)
        ->withStatus(ThreadStatus::Open)
        ->create();
    $thread->addParticipant($user);
    Message::factory()->forThread($thread)->byUser($user)->create();

    $image = UploadedFile::fake()->image('screenshot.jpg', 200, 200);

    Volt::actingAs($user)
        ->test('ready-room.tickets.view-ticket', ['thread' => $thread])
        ->set('replyMessage', 'Here is a screenshot')
        ->set('replyImage', $image)
        ->call('sendReply')
        ->assertHasNoErrors();

    $reply = Message::where('thread_id', $thread->id)
        ->where('user_id', $user->id)
        ->where('body', 'Here is a screenshot')
        ->first();

    expect($reply)->not->toBeNull();
    expect($reply->image_path)->not->toBeNull();
    expect($reply->image_path)->toStartWith('message-images/');
    Storage::disk(config('filesystems.public_disk'))->assertExists($reply->image_path);
});

it('allows uploading an image with a discussion reply', function () {
    $user = membershipCitizen();
    $thread = Thread::factory()->topic()->create(['created_by_user_id' => $user->id]);
    $thread->addParticipant($user);
    Message::factory()->forThread($thread)->byUser($user)->create();

    $image = UploadedFile::fake()->image('photo.png', 200, 200);

    Volt::actingAs($user)
        ->test('topics.view-topic', ['thread' => $thread])
        ->set('replyMessage', 'Check this out')
        ->set('replyImage', $image)
        ->call('sendReply')
        ->assertHasNoErrors();

    $reply = Message::where('thread_id', $thread->id)
        ->where('user_id', $user->id)
        ->where('body', 'Check this out')
        ->first();

    expect($reply)->not->toBeNull();
    expect($reply->image_path)->not->toBeNull();
    Storage::disk(config('filesystems.public_disk'))->assertExists($reply->image_path);
});

it('allows image-only ticket reply with no text', function () {
    $user = membershipCitizen();
    $thread = Thread::factory()
        ->withDepartment(StaffDepartment::Command)
        ->withStatus(ThreadStatus::Open)
        ->create();
    $thread->addParticipant($user);
    Message::factory()->forThread($thread)->byUser($user)->create();

    $image = UploadedFile::fake()->image('screenshot.jpg', 200, 200);

    Volt::actingAs($user)
        ->test('ready-room.tickets.view-ticket', ['thread' => $thread])
        ->set('replyMessage', '')
        ->set('replyImage', $image)
        ->call('sendReply')
        ->assertHasNoErrors();

    $reply = Message::where('thread_id', $thread->id)
        ->where('user_id', $user->id)
        ->where('kind', MessageKind::Message)
        ->latest('id')
        ->first();

    expect($reply)->not->toBeNull();
    expect($reply->image_path)->not->toBeNull();
});

it('allows image-only discussion reply with no text', function () {
    $user = membershipCitizen();
    $thread = Thread::factory()->topic()->create(['created_by_user_id' => $user->id]);
    $thread->addParticipant($user);
    Message::factory()->forThread($thread)->byUser($user)->create();

    $image = UploadedFile::fake()->image('photo.png', 200, 200);

    Volt::actingAs($user)
        ->test('topics.view-topic', ['thread' => $thread])
        ->set('replyMessage', '')
        ->set('replyImage', $image)
        ->call('sendReply')
        ->assertHasNoErrors();

    $reply = Message::where('thread_id', $thread->id)
        ->where('user_id', $user->id)
        ->where('kind', MessageKind::Message)
        ->latest('id')
        ->first();

    expect($reply)->not->toBeNull();
    expect($reply->image_path)->not->toBeNull();
});

it('validates image file size on ticket reply', function () {
    $user = membershipCitizen();
    $thread = Thread::factory()
        ->withDepartment(StaffDepartment::Command)
        ->withStatus(ThreadStatus::Open)
        ->create();
    $thread->addParticipant($user);
    Message::factory()->forThread($thread)->byUser($user)->create();

    // Create an image that exceeds the max size (default 2048 KB)
    $file = UploadedFile::fake()->image('huge.jpg')->size(3000);

    Volt::actingAs($user)
        ->test('ready-room.tickets.view-ticket', ['thread' => $thread])
        ->set('replyMessage', 'Here is a file')
        ->set('replyImage', $file)
        ->call('sendReply')
        ->assertHasErrors(['replyImage']);
});

it('validates image file size on discussion reply', function () {
    $user = membershipCitizen();
    $thread = Thread::factory()->topic()->create(['created_by_user_id' => $user->id]);
    $thread->addParticipant($user);
    Message::factory()->forThread($thread)->byUser($user)->create();

    // Create an image that exceeds the max size (default 2048 KB)
    $file = UploadedFile::fake()->image('huge.jpg')->size(3000);

    Volt::actingAs($user)
        ->test('topics.view-topic', ['thread' => $thread])
        ->set('replyMessage', 'Here is a file')
        ->set('replyImage', $file)
        ->call('sendReply')
        ->assertHasErrors(['replyImage']);
});

it('allows image upload on ticket creation', function () {
    $user = membershipCitizen();
    $image = UploadedFile::fake()->image('screenshot.jpg', 200, 200);

    Volt::actingAs($user)
        ->test('ready-room.tickets.create-ticket')
        ->set('department', StaffDepartment::Command->value)
        ->set('subject', 'Bug report with screenshot')
        ->set('message', 'Here is the bug I found with a screenshot attached')
        ->set('ticketImage', $image)
        ->call('createTicket')
        ->assertHasNoErrors();

    $thread = Thread::where('subject', 'Bug report with screenshot')->first();
    expect($thread)->not->toBeNull();

    $firstMessage = Message::where('thread_id', $thread->id)->first();
    expect($firstMessage->image_path)->not->toBeNull();
    Storage::disk(config('filesystems.public_disk'))->assertExists($firstMessage->image_path);
});

it('displays image URL via imageUrl method', function () {
    $message = Message::factory()->withImage()->create();

    expect($message->imageUrl())->not->toBeNull();
    expect($message->imageUrl())->toBeString();
});

it('returns null imageUrl when no image', function () {
    $message = Message::factory()->create();

    expect($message->imageUrl())->toBeNull();
});

it('shows purged image flag correctly', function () {
    $message = Message::factory()->purgedImage()->create();

    expect($message->image_path)->toBeNull();
    expect($message->image_was_purged)->toBeTrue();
    expect($message->imageUrl())->toBeNull();
});
