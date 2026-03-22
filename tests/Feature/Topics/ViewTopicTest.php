<?php

declare(strict_types=1);

use App\Enums\MessageKind;
use App\Models\DisciplineReport;
use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;

uses()->group('topics');

describe('View Topic Component', function () {
    it('can render for participants', function () {
        $user = User::factory()->create();
        $report = DisciplineReport::factory()->published()->create();

        $thread = Thread::factory()->topic()->create([
            'topicable_type' => DisciplineReport::class,
            'topicable_id' => $report->id,
        ]);
        $thread->addParticipant($user);

        Message::factory()->forThread($thread)->byUser($user)->create(['body' => 'Hello topic']);

        actingAs($user);

        Volt::test('topics.view-topic', ['thread' => $thread])
            ->assertSee($thread->subject)
            ->assertSee('Hello topic');
    });

    it('returns 403 for non-participants', function () {
        $user = User::factory()->create();
        $thread = Thread::factory()->topic()->create();

        actingAs($user);

        Volt::test('topics.view-topic', ['thread' => $thread])
            ->assertForbidden();
    });

    it('returns 404 for non-topic threads', function () {
        $user = User::factory()->create();
        $thread = Thread::factory()->create(); // default is ticket type
        $thread->addParticipant($user);

        actingAs($user);

        Volt::test('topics.view-topic', ['thread' => $thread])
            ->assertNotFound();
    });

    it('allows participants to send replies', function () {
        $user = User::factory()->create();
        $thread = Thread::factory()->topic()->create();
        $thread->addParticipant($user);

        actingAs($user);

        Volt::test('topics.view-topic', ['thread' => $thread])
            ->set('replyMessage', 'My reply here')
            ->call('sendReply')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('messages', [
            'thread_id' => $thread->id,
            'user_id' => $user->id,
            'body' => 'My reply here',
            'kind' => MessageKind::Message,
        ]);
    });

    it('prevents reply on locked topic', function () {
        $user = User::factory()->create();
        $thread = Thread::factory()->topic()->locked()->create();
        $thread->addParticipant($user);

        actingAs($user);

        Volt::test('topics.view-topic', ['thread' => $thread])
            ->assertDontSee('Send Reply')
            ->assertSee('This topic is locked');
    });

    it('allows users with Moderator role to lock and unlock topic', function () {
        $officer = User::factory()
            ->withStaffPosition(\App\Enums\StaffDepartment::Quartermaster, \App\Enums\StaffRank::Officer)
            ->withRole('Moderator')
            ->create();
        $thread = Thread::factory()->topic()->create();
        $thread->addParticipant($officer);

        actingAs($officer);

        $component = Volt::test('topics.view-topic', ['thread' => $thread])
            ->assertSee('Lock Topic')
            ->call('toggleLock');

        $thread->refresh();
        expect($thread->is_locked)->toBeTrue();

        $this->assertDatabaseHas('messages', [
            'thread_id' => $thread->id,
            'kind' => MessageKind::System,
        ]);
    });

    it('allows staff to add participants via search', function () {
        $staff = User::factory()
            ->withStaffPosition(\App\Enums\StaffDepartment::Quartermaster, \App\Enums\StaffRank::CrewMember)
            ->withRole('Staff Access')
            ->withRole('Ticket - User')
            ->create();
        $newUser = User::factory()->create(['name' => 'Unique Test Name']);
        $thread = Thread::factory()->topic()->create();
        $thread->addParticipant($staff);

        actingAs($staff);

        Volt::test('topics.view-topic', ['thread' => $thread])
            ->set('participantSearch', 'Unique Test')
            ->call('searchUsers')
            ->assertSet('searchResults', function ($results) use ($newUser) {
                return collect($results)->pluck('id')->contains($newUser->id);
            })
            ->call('addParticipantById', $newUser->id);

        expect($thread->participants()->where('user_id', $newUser->id)->exists())->toBeTrue();
    });

    it('shows parent report info when linked to discipline report', function () {
        $user = User::factory()->create();
        $report = DisciplineReport::factory()->published()->create();

        $thread = Thread::factory()->topic()->create([
            'topicable_type' => DisciplineReport::class,
            'topicable_id' => $report->id,
        ]);
        $thread->addParticipant($user);

        actingAs($user);

        Volt::test('topics.view-topic', ['thread' => $thread])
            ->assertSee('Related Staff Report')
            ->assertSee($report->subject->name);
    });

    it('hides internal notes from non-staff users', function () {
        $user = User::factory()->create();
        $thread = Thread::factory()->topic()->create();
        $thread->addParticipant($user);

        Message::factory()->forThread($thread)->create(['body' => 'Public message', 'kind' => MessageKind::Message]);
        Message::factory()->forThread($thread)->create(['body' => 'Staff only note', 'kind' => MessageKind::InternalNote]);

        actingAs($user);

        Volt::test('topics.view-topic', ['thread' => $thread])
            ->assertSee('Public message')
            ->assertDontSee('Staff only note');
    });

    it('shows internal notes to staff participants', function () {
        $staff = User::factory()
            ->withStaffPosition(\App\Enums\StaffDepartment::Quartermaster, \App\Enums\StaffRank::CrewMember)
            ->withRole('Staff Access')
            ->withRole('Ticket - User')
            ->create();
        $thread = Thread::factory()->topic()->create();
        $thread->addParticipant($staff);

        Message::factory()->forThread($thread)->create(['body' => 'Public message', 'kind' => MessageKind::Message]);
        Message::factory()->forThread($thread)->create(['body' => 'Staff only note', 'kind' => MessageKind::InternalNote]);

        actingAs($staff);

        Volt::test('topics.view-topic', ['thread' => $thread])
            ->assertSee('Public message')
            ->assertSee('Staff only note');
    });

    it('denies non-staff from adding participants', function () {
        $user = User::factory()->create();
        $newUser = User::factory()->create();
        $thread = Thread::factory()->topic()->create();
        $thread->addParticipant($user);

        actingAs($user);

        $component = Volt::test('topics.view-topic', ['thread' => $thread]);

        // The canAddParticipant computed should be false for non-staff
        expect($user->can('addParticipant', $thread))->toBeFalse();

        // Trying to add should throw forbidden
        $component->call('addParticipantById', $newUser->id)
            ->assertForbidden();
    });
});
