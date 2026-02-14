<?php

declare(strict_types=1);

use App\Models\Thread;
use App\Models\User;

describe('Thread Model', function () {
    it('marks thread as unread when user has never read it', function () {
        $user = User::factory()->create();
        $thread = Thread::factory()->create(['last_message_at' => now()]);
        $thread->addParticipant($user);

        expect($thread->isUnreadFor($user))->toBeTrue();
    });

    it('marks thread as read when user has read it after last message', function () {
        $user = User::factory()->create();
        $thread = Thread::factory()->create(['last_message_at' => now()->subHour()]);
        $thread->addParticipant($user);

        $participant = $thread->participants()->where('user_id', $user->id)->first();
        $participant->update(['last_read_at' => now()]);

        expect($thread->isUnreadFor($user))->toBeFalse();
    });

    it('marks thread as unread when new message arrives after user read it', function () {
        $user = User::factory()->create();
        $thread = Thread::factory()->create(['last_message_at' => now()->subMinutes(30)]);
        $thread->addParticipant($user);

        $participant = $thread->participants()->where('user_id', $user->id)->first();
        $participant->update(['last_read_at' => now()->subHour()]);

        expect($thread->isUnreadFor($user))->toBeTrue();
    });

    it('correctly handles unread status for different users', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $thread = Thread::factory()->create(['last_message_at' => now()]);

        $thread->addParticipant($user1);
        $thread->addParticipant($user2);

        // User 1 has read it
        $participant1 = $thread->participants()->where('user_id', $user1->id)->first();
        $participant1->update(['last_read_at' => now()->addMinute()]);

        // User 2 has not read it
        $participant2 = $thread->participants()->where('user_id', $user2->id)->first();
        $participant2->update(['last_read_at' => null]);

        expect($thread->isUnreadFor($user1))->toBeFalse()
            ->and($thread->isUnreadFor($user2))->toBeTrue();
    });
});
