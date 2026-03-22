<?php

declare(strict_types=1);

use App\Actions\AcknowledgeFlag;
use App\Actions\FlagMessage;
use App\Enums\MessageFlagStatus;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Enums\ThreadType;
use App\Models\Message;
use App\Models\MessageFlag;
use App\Models\Thread;
use App\Models\User;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;

uses()->group('topics', 'flagging');

describe('Discussion Flagging', function () {
    it('allows participants to flag messages from other users', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $thread = Thread::factory()->topic()->create();
        $thread->addParticipant($user);
        $thread->addParticipant($other);
        $message = Message::factory()->forThread($thread)->byUser($other)->create();

        actingAs($user);

        FlagMessage::run($message, $user, 'Inappropriate content in discussion');

        expect($thread->fresh()->is_flagged)->toBeTrue()
            ->and($thread->fresh()->has_open_flags)->toBeTrue();

        $flag = MessageFlag::where('message_id', $message->id)->first();
        expect($flag)->not->toBeNull()
            ->and($flag->flagged_by_user_id)->toBe($user->id)
            ->and($flag->note)->toBe('Inappropriate content in discussion')
            ->and($flag->status)->toBe(MessageFlagStatus::New);
    });

    it('prevents users from flagging their own messages', function () {
        $user = User::factory()->create();
        $thread = Thread::factory()->topic()->create();
        $thread->addParticipant($user);
        $message = Message::factory()->forThread($thread)->byUser($user)->create();

        actingAs($user);

        expect($user->can('flag', $message))->toBeFalse();
    });

    it('prevents non-participants from flagging messages', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $thread = Thread::factory()->topic()->create();
        // user is NOT a participant
        $message = Message::factory()->forThread($thread)->byUser($other)->create();

        actingAs($user);

        expect($user->can('flag', $message))->toBeFalse();
    });

    it('prevents flagging system messages', function () {
        $user = User::factory()->create();
        $thread = Thread::factory()->topic()->create();
        $thread->addParticipant($user);
        $systemUser = User::where('email', 'system@lighthouse.local')->first();
        $message = Message::factory()->forThread($thread)->byUser($systemUser)->system()->create();

        actingAs($user);

        expect($user->can('flag', $message))->toBeFalse();
    });

    it('creates moderation review ticket when message is flagged', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $thread = Thread::factory()->topic()->create();
        $thread->addParticipant($user);
        $thread->addParticipant($other);
        $message = Message::factory()->forThread($thread)->byUser($other)->create();

        actingAs($user);

        FlagMessage::run($message, $user, 'Needs staff review');

        $flag = MessageFlag::where('message_id', $message->id)->first();
        expect($flag->flag_review_ticket_id)->not->toBeNull();

        $reviewTicket = Thread::find($flag->flag_review_ticket_id);
        expect($reviewTicket)->not->toBeNull()
            ->and($reviewTicket->type)->toBe(ThreadType::Topic);
    });

    it('allows Quartermaster staff to view flagged discussion', function () {
        $staff = User::factory()
            ->withRole('Ticket - Manager')
            ->create([
                'staff_department' => StaffDepartment::Quartermaster,
                'staff_rank' => StaffRank::CrewMember,
            ]);
        $thread = Thread::factory()->topic()->create([
            'is_flagged' => true,
            'has_open_flags' => true,
        ]);

        // Staff is NOT a participant
        expect($thread->isVisibleTo($staff))->toBeTrue();
    });

    it('denies non-Quartermaster from viewing flagged discussion', function () {
        $user = User::factory()->create([
            'staff_department' => StaffDepartment::Chaplain,
            'staff_rank' => StaffRank::CrewMember,
        ]);
        $thread = Thread::factory()->topic()->create([
            'is_flagged' => true,
            'has_open_flags' => true,
        ]);

        expect($thread->isVisibleTo($user))->toBeFalse();
    });

    it('revokes staff access after all flags are acknowledged', function () {
        $staff = User::factory()->create([
            'staff_department' => StaffDepartment::Quartermaster,
            'staff_rank' => StaffRank::CrewMember,
        ]);
        $thread = Thread::factory()->topic()->create([
            'is_flagged' => true,
            'has_open_flags' => false,
        ]);

        // Staff is NOT a participant — flags all acknowledged
        expect($thread->isVisibleTo($staff))->toBeFalse();
    });

    it('allows Quartermaster staff to acknowledge a flag', function () {
        $staff = User::factory()
            ->withRole('Ticket - Manager')
            ->create([
                'staff_department' => StaffDepartment::Quartermaster,
                'staff_rank' => StaffRank::CrewMember,
            ]);
        $user = User::factory()->create();
        $other = User::factory()->create();
        $thread = Thread::factory()->topic()->create();
        $thread->addParticipant($user);
        $thread->addParticipant($other);
        $message = Message::factory()->forThread($thread)->byUser($other)->create();

        actingAs($user);
        FlagMessage::run($message, $user, 'Needs review');

        $flag = MessageFlag::where('message_id', $message->id)->first();

        actingAs($staff);
        AcknowledgeFlag::run($flag, $staff, 'Reviewed and resolved');

        $flag->refresh();
        expect($flag->status)->toBe(MessageFlagStatus::Acknowledged)
            ->and($flag->reviewed_by_user_id)->toBe($staff->id)
            ->and($flag->staff_notes)->toBe('Reviewed and resolved')
            ->and($thread->fresh()->has_open_flags)->toBeFalse();
    });

    it('shows flagged filter to Quartermaster staff on discussions list', function () {
        $staff = User::factory()
            ->withRole('Ticket - Manager')
            ->create([
                'staff_department' => StaffDepartment::Quartermaster,
                'staff_rank' => StaffRank::CrewMember,
            ]);

        actingAs($staff);

        Volt::test('topics.topics-list')
            ->assertSee('Flagged');
    });

    it('hides flagged filter from non-Quartermaster users', function () {
        $user = User::factory()->create();

        actingAs($user);

        Volt::test('topics.topics-list')
            ->assertDontSee('Flagged');
    });

    it('shows flag button on discussion messages for participants', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $thread = Thread::factory()->topic()->create();
        $thread->addParticipant($user);
        $thread->addParticipant($other);
        Message::factory()->forThread($thread)->byUser($other)->create(['body' => 'Test message']);

        actingAs($user);

        Volt::test('topics.view-topic', ['thread' => $thread])
            ->assertSee('Test message')
            ->assertSeeHtml('aria-label="Flag message"');
    });

    it('submits a flag via the component', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $thread = Thread::factory()->topic()->create();
        $thread->addParticipant($user);
        $thread->addParticipant($other);
        $message = Message::factory()->forThread($thread)->byUser($other)->create();

        actingAs($user);

        Volt::test('topics.view-topic', ['thread' => $thread])
            ->call('openFlagModal', $message->id)
            ->set('flagReason', 'This is inappropriate content')
            ->call('submitFlag')
            ->assertHasNoErrors();

        expect(MessageFlag::where('message_id', $message->id)->exists())->toBeTrue();
        expect($thread->fresh()->has_open_flags)->toBeTrue();
    });

    it('adds the flagging user as a participant on the review ticket', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $thread = Thread::factory()->topic()->create();
        $thread->addParticipant($user);
        $thread->addParticipant($other);
        $message = Message::factory()->forThread($thread)->byUser($other)->create();

        actingAs($user);

        $flag = FlagMessage::run($message, $user, 'Needs review');

        $reviewTicket = Thread::find($flag->flag_review_ticket_id);
        expect($reviewTicket->participants()->where('user_id', $user->id)->exists())->toBeTrue();
    });

    it('shows flag status to the user who flagged the message', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $thread = Thread::factory()->topic()->create();
        $thread->addParticipant($user);
        $thread->addParticipant($other);
        $message = Message::factory()->forThread($thread)->byUser($other)->create();

        actingAs($user);

        FlagMessage::run($message, $user, 'Inappropriate content');

        Volt::test('topics.view-topic', ['thread' => $thread])
            ->assertSee('Flagged by')
            ->assertSee('Inappropriate content');
    });

    it('hides flag status from users who did not flag the message', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $viewer = User::factory()->create();
        $thread = Thread::factory()->topic()->create();
        $thread->addParticipant($user);
        $thread->addParticipant($other);
        $thread->addParticipant($viewer);
        $message = Message::factory()->forThread($thread)->byUser($other)->create();

        actingAs($user);
        FlagMessage::run($message, $user, 'Inappropriate content');

        // Viewer did not flag — should not see the flag box
        actingAs($viewer);

        Volt::test('topics.view-topic', ['thread' => $thread])
            ->assertDontSee('Flagged by');
    });

    it('shows flags on multiple messages to staff', function () {
        $staff = User::factory()
            ->withRole('Ticket - Manager')
            ->create([
                'staff_department' => StaffDepartment::Quartermaster,
                'staff_rank' => StaffRank::CrewMember,
            ]);
        $user = User::factory()->create();
        $other = User::factory()->create();
        $thread = Thread::factory()->topic()->create();
        $thread->addParticipant($user);
        $thread->addParticipant($other);
        $message1 = Message::factory()->forThread($thread)->byUser($other)->create(['body' => 'First bad message']);
        $message2 = Message::factory()->forThread($thread)->byUser($other)->create(['body' => 'Second bad message']);

        actingAs($user);
        FlagMessage::run($message1, $user, 'First flag reason');
        FlagMessage::run($message2, $user, 'Second flag reason');

        $thread->refresh();
        actingAs($staff);

        Volt::test('topics.view-topic', ['thread' => $thread])
            ->assertSee('First flag reason')
            ->assertSee('Second flag reason');
    });

    it('does not show acknowledge button to the flagger', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $thread = Thread::factory()->topic()->create();
        $thread->addParticipant($user);
        $thread->addParticipant($other);
        $message = Message::factory()->forThread($thread)->byUser($other)->create();

        actingAs($user);
        FlagMessage::run($message, $user, 'Needs review for content');

        Volt::test('topics.view-topic', ['thread' => $thread])
            ->assertSee('Flagged by')
            ->assertDontSee('Acknowledge Flag');
    });
});
