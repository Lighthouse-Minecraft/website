<?php

declare(strict_types=1);

use App\Models\DisciplineReport;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

uses()->group('topics', 'policies');

describe('createTopic policy', function () {
    it('allows report subject to create topic on published report', function () {
        $subject = User::factory()->create();
        $report = DisciplineReport::factory()->forSubject($subject)->published()->create();

        $this->actingAs($subject);

        expect($subject->can('createTopic', [Thread::class, $report]))->toBeTrue();
    });

    it('allows parent of subject to create topic on published report', function () {
        $child = User::factory()->create();
        $parent = User::factory()->create();
        $parent->children()->attach($child);

        $report = DisciplineReport::factory()->forSubject($child)->published()->create();

        $this->actingAs($parent);

        expect($parent->can('createTopic', [Thread::class, $report]))->toBeTrue();
    });

    it('allows staff with Ticket - User role to create topic on published report', function () {
        $staff = User::factory()
            ->withStaffPosition(\App\Enums\StaffDepartment::Quartermaster, \App\Enums\StaffRank::JrCrew)
            ->withRole('Staff Access')
            ->withRole('Ticket - User')
            ->create();
        $report = DisciplineReport::factory()->published()->create();

        $this->actingAs($staff);

        expect($staff->can('createTopic', [Thread::class, $report]))->toBeTrue();
    });

    it('denies non-staff non-subject non-parent from creating topic', function () {
        $randomUser = User::factory()->create();
        $report = DisciplineReport::factory()->published()->create();

        $this->actingAs($randomUser);

        expect($randomUser->can('createTopic', [Thread::class, $report]))->toBeFalse();
    });

    it('denies topic creation on draft reports', function () {
        $subject = User::factory()->create();
        $report = DisciplineReport::factory()->forSubject($subject)->create(); // draft

        $this->actingAs($subject);

        expect($subject->can('createTopic', [Thread::class, $report]))->toBeFalse();
    });
});

describe('topic visibility', function () {
    it('allows participants to view topic', function () {
        $user = User::factory()->create();
        $thread = Thread::factory()->topic()->create();
        $thread->addParticipant($user);

        expect($thread->isVisibleTo($user))->toBeTrue();
    });

    it('denies non-participants from viewing topic', function () {
        $user = User::factory()->create();
        $thread = Thread::factory()->topic()->create();

        expect($thread->isVisibleTo($user))->toBeFalse();
    });

    it('allows admin to view any topic', function () {
        $admin = User::factory()->admin()->create();
        $thread = Thread::factory()->topic()->create();

        expect($thread->isVisibleTo($admin))->toBeTrue();
    });
});

describe('reply policy with locking', function () {
    it('allows reply to unlocked topic for participants', function () {
        $user = User::factory()->create();
        $thread = Thread::factory()->topic()->create();
        $thread->addParticipant($user);

        $this->actingAs($user);

        expect($user->can('reply', $thread))->toBeTrue();
    });

    it('denies reply to locked topic', function () {
        $user = User::factory()->create();
        $thread = Thread::factory()->topic()->locked()->create();
        $thread->addParticipant($user);

        $this->actingAs($user);

        expect($user->can('reply', $thread))->toBeFalse();
    });
});

describe('lock-topic gate', function () {
    it('allows user with Moderator role to lock topics', function () {
        $user = User::factory()->withRole('Moderator')->create();

        $this->actingAs($user);

        expect(Gate::allows('lock-topic'))->toBeTrue();
    });

    it('allows admin to lock topics', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        expect(Gate::allows('lock-topic'))->toBeTrue();
    });

    it('denies user without Moderator role from locking topics', function () {
        $crew = crewQuartermaster();

        $this->actingAs($crew);

        expect(Gate::allows('lock-topic'))->toBeFalse();
    });

    it('denies regular users from locking topics', function () {
        $user = User::factory()->create();

        $this->actingAs($user);

        expect(Gate::allows('lock-topic'))->toBeFalse();
    });
});

describe('addParticipant policy', function () {
    it('allows staff who can view the thread to add participants', function () {
        $staff = User::factory()
            ->withStaffPosition(\App\Enums\StaffDepartment::Quartermaster, \App\Enums\StaffRank::CrewMember)
            ->withRole('Staff Access')
            ->withRole('Ticket - User')
            ->create();
        $thread = Thread::factory()->topic()->create();
        $thread->addParticipant($staff);

        $this->actingAs($staff);

        expect($staff->can('addParticipant', $thread))->toBeTrue();
    });

    it('denies non-staff from adding participants', function () {
        $user = User::factory()->create();
        $thread = Thread::factory()->topic()->create();
        $thread->addParticipant($user);

        $this->actingAs($user);

        expect($user->can('addParticipant', $thread))->toBeFalse();
    });
});
