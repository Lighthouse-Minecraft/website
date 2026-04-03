<?php

declare(strict_types=1);

use App\Actions\LinkContactThreadsOnRegistration;
use App\Enums\ThreadStatus;
use App\Enums\ThreadType;
use App\Models\Thread;
use App\Models\ThreadParticipant;
use App\Models\User;
use Illuminate\Support\Str;

uses()->group('contact', 'actions');

function makeContactInquiryThread(string $guestEmail, array $attrs = []): Thread
{
    return Thread::create(array_merge([
        'type' => ThreadType::ContactInquiry,
        'subject' => '[General Inquiry] Test',
        'status' => ThreadStatus::Open,
        'guest_name' => 'Test Guest',
        'guest_email' => $guestEmail,
        'conversation_token' => (string) Str::uuid(),
        'last_message_at' => now(),
    ], $attrs));
}

it('adds user as participant on threads with matching guest email', function () {
    $thread = makeContactInquiryThread('alice@example.com');
    $user = User::factory()->create(['email' => 'alice@example.com']);

    LinkContactThreadsOnRegistration::run($user);

    expect(ThreadParticipant::where('thread_id', $thread->id)->where('user_id', $user->id)->exists())->toBeTrue();
});

it('links multiple threads with the same guest email', function () {
    $thread1 = makeContactInquiryThread('alice@example.com');
    $thread2 = makeContactInquiryThread('alice@example.com');
    $user = User::factory()->create(['email' => 'alice@example.com']);

    LinkContactThreadsOnRegistration::run($user);

    expect(ThreadParticipant::where('user_id', $user->id)->count())->toBe(2);
});

it('does not create duplicate participant records', function () {
    $thread = makeContactInquiryThread('alice@example.com');
    $user = User::factory()->create(['email' => 'alice@example.com']);

    LinkContactThreadsOnRegistration::run($user);
    LinkContactThreadsOnRegistration::run($user);

    expect(ThreadParticipant::where('thread_id', $thread->id)->where('user_id', $user->id)->count())->toBe(1);
});

it('does nothing when no threads match the user email', function () {
    makeContactInquiryThread('other@example.com');
    $user = User::factory()->create(['email' => 'alice@example.com']);

    LinkContactThreadsOnRegistration::run($user);

    expect(ThreadParticipant::where('user_id', $user->id)->count())->toBe(0);
});

it('does not link non-contact-inquiry threads', function () {
    $user = User::factory()->create(['email' => 'alice@example.com']);
    $ticket = Thread::create([
        'type' => ThreadType::Ticket,
        'subject' => 'Regular ticket',
        'status' => ThreadStatus::Open,
        'created_by_user_id' => $user->id,
        'department' => \App\Enums\StaffDepartment::Command,
        'last_message_at' => now(),
    ]);

    LinkContactThreadsOnRegistration::run($user);

    expect(ThreadParticipant::where('thread_id', $ticket->id)->where('user_id', $user->id)->count())->toBe(0);
});

it('matches guest email case-insensitively', function () {
    $thread = makeContactInquiryThread('Alice@Example.COM');
    $user = User::factory()->create(['email' => 'alice@example.com']);

    LinkContactThreadsOnRegistration::run($user);

    expect(ThreadParticipant::where('thread_id', $thread->id)->where('user_id', $user->id)->exists())->toBeTrue();
});
