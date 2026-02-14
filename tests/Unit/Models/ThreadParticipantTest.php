<?php

declare(strict_types=1);

use App\Models\Thread;
use App\Models\ThreadParticipant;
use App\Models\User;

describe('ThreadParticipant Model', function () {
    it('creates participant with is_viewer flag', function () {
        $thread = Thread::factory()->create();
        $user = User::factory()->create();

        $participant = ThreadParticipant::create([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
            'is_viewer' => true,
        ]);

        expect($participant->is_viewer)->toBeTrue()
            ->and($participant->thread_id)->toBe($thread->id)
            ->and($participant->user_id)->toBe($user->id);
    });

    it('defaults is_viewer to false', function () {
        $thread = Thread::factory()->create();
        $user = User::factory()->create();

        $participant = ThreadParticipant::create([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
        ]);

        expect($participant->is_viewer)->toBeFalsy();
    });

    it('has thread relationship', function () {
        $thread = Thread::factory()->create();
        $user = User::factory()->create();

        $participant = ThreadParticipant::create([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
        ]);

        expect($participant->thread)->toBeInstanceOf(Thread::class)
            ->and($participant->thread->id)->toBe($thread->id);
    });

    it('has user relationship', function () {
        $thread = Thread::factory()->create();
        $user = User::factory()->create();

        $participant = ThreadParticipant::create([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
        ]);

        expect($participant->user)->toBeInstanceOf(User::class)
            ->and($participant->user->id)->toBe($user->id);
    });

    it('casts last_read_at to datetime', function () {
        $thread = Thread::factory()->create();
        $user = User::factory()->create();

        $participant = ThreadParticipant::create([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
            'last_read_at' => now(),
        ]);

        expect($participant->last_read_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    });

    it('casts is_viewer to boolean', function () {
        $thread = Thread::factory()->create();
        $user = User::factory()->create();

        $participant = ThreadParticipant::create([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
            'is_viewer' => 1, // Integer
        ]);

        expect($participant->is_viewer)->toBeBool()
            ->and($participant->is_viewer)->toBeTrue();
    });
});
