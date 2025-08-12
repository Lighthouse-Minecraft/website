<?php
// tests/Unit/Models/MeetingNoteTest.php

use App\Models\User;
use App\Models\Meeting;
use App\Models\MeetingNote;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('mass-assigns fillable attributes', function () {
    $creator = User::factory()->create();
    $locker  = User::factory()->create();
    $meeting = Meeting::factory()->create();

    $note = MeetingNote::create([
        'created_by'      => $creator->id,
        'locked_by'       => $locker->id,
        'meeting_id'      => $meeting->id,
        'section_key'     => 'agenda',
        'content'         => 'Initial content',
        'locked_at'       => now(),
        'lock_updated_at' => now(),
    ]);

    expect($note->exists)->toBeTrue()
        ->and($note->created_by)->toBe($creator->id)
        ->and($note->locked_by)->toBe($locker->id)
        ->and($note->meeting_id)->toBe($meeting->id)
        ->and($note->section_key)->toBe('agenda')
        ->and($note->content)->toBe('Initial content');
});

it('relationships resolve to the right models', function () {
    $creator = User::factory()->create();
    $locker  = User::factory()->create();
    $meeting = Meeting::factory()->create();

    $note = MeetingNote::factory()->create([
        'created_by' => $creator->id,
        'locked_by'  => $locker->id,
        'meeting_id' => $meeting->id,
    ]);

    expect($note->createdBy->is($creator))->toBeTrue();
    expect($note->lockedBy->is($locker))->toBeTrue();
    expect($note->meeting->is($meeting))->toBeTrue();
});

it('eager loads createdBy and lockedBy via $with', function () {
    $note = MeetingNote::factory()->create();
    $fresh = MeetingNote::findOrFail($note->id);

    expect($fresh->relationLoaded('createdBy'))->toBeTrue();
    expect($fresh->relationLoaded('lockedBy'))->toBeTrue();
});

it('casts locked_at and lock_updated_at to datetime when set', function () {
    // add in model: protected $casts = ['locked_at' => 'datetime','lock_updated_at' => 'datetime'];
    $note = MeetingNote::factory()->create([
        'locked_at'       => now(),
        'lock_updated_at' => now(),
    ]);

    expect($note->locked_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    expect($note->lock_updated_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});
