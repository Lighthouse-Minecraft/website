<?php

declare(strict_types=1);

use App\Actions\AcknowledgeFlag;
use App\Actions\FlagMessage;
use App\Enums\MessageFlagStatus;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Enums\ThreadSubtype;
use App\Models\Message;
use App\Models\MessageFlag;
use App\Models\Thread;
use App\Models\User;
use App\Notifications\MessageFlaggedNotification;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;

describe('Message Flagging Workflow', function () {
    beforeEach(function () {
        // Ensure system user exists
        User::factory()->create([
            'email' => 'system@lighthouse.local',
            'name' => 'System',
        ]);
    });

    it('allows users to flag messages they did not create', function () {
        $author = User::factory()->create();
        $flagger = User::factory()->create();

        $thread = Thread::factory()->create();
        $thread->addParticipant($flagger); // Make flagger a participant
        $message = Message::factory()->forThread($thread)->byUser($author)->create();

        actingAs($flagger);

        expect($flagger->can('flag', $message))->toBeTrue();
    })->done();

    it('prevents users from flagging their own messages', function () {
        $user = User::factory()->create();

        $thread = Thread::factory()->create();
        $message = Message::factory()->forThread($thread)->byUser($user)->create();

        actingAs($user);

        expect($user->can('flag', $message))->toBeFalse();
    })->done();

    it('prevents users from flagging system messages', function () {
        $user = User::factory()->create();
        $systemUser = User::where('email', 'system@lighthouse.local')->first();

        $thread = Thread::factory()->create();
        $message = Message::factory()->forThread($thread)->byUser($systemUser)->system()->create();

        actingAs($user);

        expect($user->can('flag', $message))->toBeFalse();
    })->done();

    it('creates flag record when message is flagged', function () {
        $author = User::factory()->create();
        $flagger = User::factory()->create();

        $thread = Thread::factory()->withDepartment(StaffDepartment::Chaplain)->create();
        $message = Message::factory()->forThread($thread)->byUser($author)->create();

        actingAs($flagger);

        FlagMessage::run($message, $flagger, 'This message is inappropriate');

        $flag = MessageFlag::where('message_id', $message->id)->first();

        expect($flag)->not->toBeNull()
            ->and($flag->flagged_by_user_id)->toBe($flagger->id)
            ->and($flag->note)->toBe('This message is inappropriate')
            ->and($flag->status)->toBe(MessageFlagStatus::New);
    })->done();

    it('updates thread flagging status when message is flagged', function () {
        $author = User::factory()->create();
        $flagger = User::factory()->create();

        $thread = Thread::factory()->withDepartment(StaffDepartment::Chaplain)->create();
        $message = Message::factory()->forThread($thread)->byUser($author)->create();

        expect($thread->is_flagged)->toBeFalse()
            ->and($thread->has_open_flags)->toBeFalse();

        actingAs($flagger);

        FlagMessage::run($message, $flagger, 'This message is inappropriate');

        $thread->refresh();

        expect($thread->is_flagged)->toBeTrue()
            ->and($thread->has_open_flags)->toBeTrue();
    })->done();

    it('creates moderation ticket for Quartermaster when message is flagged', function () {
        $author = User::factory()->create();
        $flagger = User::factory()->create();

        $thread = Thread::factory()->withDepartment(StaffDepartment::Chaplain)->create();
        $message = Message::factory()->forThread($thread)->byUser($author)->create();

        actingAs($flagger);

        FlagMessage::run($message, $flagger, 'This message is inappropriate');

        $flag = MessageFlag::where('message_id', $message->id)->first();

        expect($flag->flag_review_ticket_id)->not->toBeNull();

        $reviewTicket = Thread::find($flag->flag_review_ticket_id);

        expect($reviewTicket)->not->toBeNull()
            ->and($reviewTicket->subtype)->toBe(ThreadSubtype::ModerationFlag)
            ->and($reviewTicket->department)->toBe(StaffDepartment::Quartermaster);
    })->done();

    it('sends notification to Quartermaster staff when message is flagged', function () {
        Notification::fake();

        $author = User::factory()->create();
        $flagger = User::factory()->create();
        $quartermaster = User::factory()
            ->withStaffPosition(StaffDepartment::Quartermaster, StaffRank::CrewMember)
            ->create();

        $thread = Thread::factory()->withDepartment(StaffDepartment::Chaplain)->create();
        $message = Message::factory()->forThread($thread)->byUser($author)->create();

        actingAs($flagger);

        FlagMessage::run($message, $flagger, 'This message is inappropriate');

        Notification::assertSentTo(
            [$quartermaster],
            MessageFlaggedNotification::class
        );
    })->done();

    it('allows Quartermaster to acknowledge flags with staff notes', function () {
        $author = User::factory()->create();
        $flagger = User::factory()->create();
        $quartermaster = User::factory()
            ->withStaffPosition(StaffDepartment::Quartermaster, StaffRank::Officer)
            ->create();

        $thread = Thread::factory()->withDepartment(StaffDepartment::Chaplain)->create();
        $message = Message::factory()->forThread($thread)->byUser($author)->create();

        actingAs($flagger);
        FlagMessage::run($message, $flagger, 'This message is inappropriate');

        $flag = MessageFlag::where('message_id', $message->id)->first();

        actingAs($quartermaster);
        AcknowledgeFlag::run($flag, $quartermaster, 'Reviewed and took appropriate action');

        $flag->refresh();

        expect($flag->status)->toBe(MessageFlagStatus::Acknowledged)
            ->and($flag->reviewed_by_user_id)->toBe($quartermaster->id)
            ->and($flag->staff_notes)->toBe('Reviewed and took appropriate action')
            ->and($flag->reviewed_at)->not->toBeNull();
    })->done();

    it('clears has_open_flags when all flags are acknowledged', function () {
        $author = User::factory()->create();
        $flagger = User::factory()->create();
        $quartermaster = User::factory()
            ->withStaffPosition(StaffDepartment::Quartermaster, StaffRank::Officer)
            ->create();

        $thread = Thread::factory()->withDepartment(StaffDepartment::Chaplain)->create();
        $message = Message::factory()->forThread($thread)->byUser($author)->create();

        actingAs($flagger);
        FlagMessage::run($message, $flagger, 'This message is inappropriate');

        $thread->refresh();
        expect($thread->has_open_flags)->toBeTrue();

        $flag = MessageFlag::where('message_id', $message->id)->first();

        actingAs($quartermaster);
        AcknowledgeFlag::run($flag, $quartermaster, 'Reviewed');

        $thread->refresh();
        expect($thread->has_open_flags)->toBeFalse();
    })->done();

    it('keeps has_open_flags true if some flags remain unacknowledged', function () {
        $author = User::factory()->create();
        $flagger = User::factory()->create();
        $quartermaster = User::factory()
            ->withStaffPosition(StaffDepartment::Quartermaster, StaffRank::Officer)
            ->create();

        $thread = Thread::factory()->withDepartment(StaffDepartment::Chaplain)->create();
        $message1 = Message::factory()->forThread($thread)->byUser($author)->create();
        $message2 = Message::factory()->forThread($thread)->byUser($author)->create();

        actingAs($flagger);
        FlagMessage::run($message1, $flagger, 'Flag 1');
        FlagMessage::run($message2, $flagger, 'Flag 2');

        $thread->refresh();
        expect($thread->has_open_flags)->toBeTrue();

        $flag1 = MessageFlag::where('message_id', $message1->id)->first();

        actingAs($quartermaster);
        AcknowledgeFlag::run($flag1, $quartermaster, 'Reviewed flag 1');

        $thread->refresh();
        expect($thread->has_open_flags)->toBeTrue(); // Still has flag 2
    })->done();
});
