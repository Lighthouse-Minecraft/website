<?php

declare(strict_types=1);

use App\Actions\CreateTopic;
use App\Enums\MessageKind;
use App\Enums\ThreadType;
use App\Models\DisciplineReport;
use App\Models\Thread;
use App\Models\User;
use App\Notifications\NewTopicNotification;
use Illuminate\Support\Facades\Notification;

uses()->group('topics', 'actions');

it('creates a topic thread linked to a discipline report', function () {
    $report = DisciplineReport::factory()->published()->create();
    $creator = officerCommand();

    $thread = CreateTopic::run($report, $creator, 'Discussion about this report');

    expect($thread)->toBeInstanceOf(Thread::class)
        ->and($thread->type)->toBe(ThreadType::Topic)
        ->and($thread->subject)->toBe('Discussion about this report')
        ->and($thread->topicable_type)->toBe(DisciplineReport::class)
        ->and($thread->topicable_id)->toBe($report->id)
        ->and($thread->created_by_user_id)->toBe($creator->id)
        ->and($thread->subtype)->toBeNull();
});

it('auto-adds report subject, reporter, and publisher as participants', function () {
    $subject = User::factory()->create();
    $reporter = User::factory()->create();
    $publisher = User::factory()->create();

    $report = DisciplineReport::factory()
        ->forSubject($subject)
        ->byReporter($reporter)
        ->published()
        ->create(['publisher_user_id' => $publisher->id]);

    $creator = officerCommand();

    $thread = CreateTopic::run($report, $creator, 'Test topic');

    $participantIds = $thread->participants()->pluck('user_id')->sort()->values()->toArray();

    expect($participantIds)->toContain($creator->id)
        ->toContain($subject->id)
        ->toContain($reporter->id)
        ->toContain($publisher->id);
});

it('auto-adds subject parents as participants', function () {
    $child = User::factory()->create();
    $parent = User::factory()->create();
    $parent->children()->attach($child);

    $report = DisciplineReport::factory()
        ->forSubject($child)
        ->published()
        ->create();

    $creator = officerCommand();

    $thread = CreateTopic::run($report, $creator, 'Test topic');

    $participantIds = $thread->participants()->pluck('user_id')->toArray();

    expect($participantIds)->toContain($parent->id);
});

it('deduplicates participants when roles overlap', function () {
    $staffUser = officerCommand();

    $report = DisciplineReport::factory()
        ->byReporter($staffUser)
        ->published()
        ->create(['publisher_user_id' => $staffUser->id]);

    $thread = CreateTopic::run($report, $staffUser, 'Test topic');

    // Staff user is creator, reporter, AND publisher - should only be added once
    $count = $thread->participants()->where('user_id', $staffUser->id)->count();
    expect($count)->toBe(1);
});

it('creates initial message when provided', function () {
    $report = DisciplineReport::factory()->published()->create();
    $creator = officerCommand();

    $thread = CreateTopic::run($report, $creator, 'Test topic', 'This is my initial message');

    // 1 system message (report summary) + 1 user message
    expect($thread->messages)->toHaveCount(2);

    $userMessage = $thread->messages->where('kind', MessageKind::Message)->first();
    expect($userMessage->body)->toBe('This is my initial message')
        ->and($userMessage->user_id)->toBe($creator->id);
});

it('creates system message with report summary but no user message when initial message is null', function () {
    $report = DisciplineReport::factory()->published()->create();
    $creator = officerCommand();

    $thread = CreateTopic::run($report, $creator, 'Test topic');

    // Only the system message (report summary)
    expect($thread->messages)->toHaveCount(1)
        ->and($thread->messages->first()->kind)->toBe(MessageKind::System)
        ->and($thread->messages->first()->body)->toContain('Staff Report');
});

it('records activity when topic is created', function () {
    $report = DisciplineReport::factory()->published()->create();
    $creator = officerCommand();

    $thread = CreateTopic::run($report, $creator, 'My topic');

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => Thread::class,
        'subject_id' => $thread->id,
        'action' => 'topic_created',
    ]);
});

it('notifies all auto-added participants except creator', function () {
    Notification::fake();

    $subject = User::factory()->create();
    $reporter = User::factory()->create();
    $publisher = User::factory()->create();

    $report = DisciplineReport::factory()
        ->forSubject($subject)
        ->byReporter($reporter)
        ->published()
        ->create(['publisher_user_id' => $publisher->id]);

    $creator = officerCommand();

    CreateTopic::run($report, $creator, 'Test topic');

    Notification::assertSentTo($subject, NewTopicNotification::class);
    Notification::assertSentTo($reporter, NewTopicNotification::class);
    Notification::assertSentTo($publisher, NewTopicNotification::class);
    Notification::assertNotSentTo($creator, NewTopicNotification::class);
});
