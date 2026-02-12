<?php

declare(strict_types=1);

use App\Enums\MessageKind;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Enums\ThreadStatus;
use App\Enums\ThreadSubtype;
use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;

describe('Create Ticket Component', function () {
    it('can render for authenticated users', function () {
        $user = User::factory()->create();

        actingAs($user);

        Volt::test('ready-room.tickets.create-ticket')
            ->assertSee('Create Support Ticket')
            ->assertSee('Department')
            ->assertSee('Subject')
            ->assertSee('Message');
    })->done();

    it('validates required fields', function () {
        $user = User::factory()->create();

        actingAs($user);

        Volt::test('ready-room.tickets.create-ticket')
            ->set('form.department', '')
            ->set('form.subject', '')
            ->set('form.message', '')
            ->call('create')
            ->assertHasErrors(['form.department', 'form.subject', 'form.message']);
    })->done();

    it('creates support ticket with correct data', function () {
        $user = User::factory()->create();

        actingAs($user);

        Volt::test('ready-room.tickets.create-ticket')
            ->set('form.department', StaffDepartment::Chaplain->value)
            ->set('form.subject', 'Need help with prayer request')
            ->set('form.message', 'I would like to submit a prayer request')
            ->call('create')
            ->assertHasNoErrors();

        $thread = Thread::where('subject', 'Need help with prayer request')->first();

        expect($thread)->not->toBeNull()
            ->and($thread->subtype)->toBe(ThreadSubtype::Support)
            ->and($thread->department)->toBe(StaffDepartment::Chaplain)
            ->and($thread->status)->toBe(ThreadStatus::Open);

        $message = Message::where('thread_id', $thread->id)->first();

        expect($message)->not->toBeNull()
            ->and($message->user_id)->toBe($user->id)
            ->and($message->body)->toBe('I would like to submit a prayer request')
            ->and($message->kind)->toBe(MessageKind::Message);
    })->done();

    it('adds creator as participant', function () {
        $user = User::factory()->create();

        actingAs($user);

        Volt::test('ready-room.tickets.create-ticket')
            ->set('form.department', StaffDepartment::Chaplain->value)
            ->set('form.subject', 'Test ticket')
            ->set('form.message', 'Test message')
            ->call('create');

        $thread = Thread::where('subject', 'Test ticket')->first();

        expect($thread->participants()->where('user_id', $user->id)->exists())->toBeTrue();
    })->done();
});

describe('Create Admin Ticket Component', function () {
    it('can render for staff with createAsStaff permission', function () {
        $staff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::Officer)
            ->create();

        actingAs($staff);

        Volt::test('ready-room.tickets.create-admin-ticket')
            ->assertSee('Create Admin Ticket')
            ->assertSee('Target User')
            ->assertSee('Department')
            ->assertSee('Subject');
    })->done();

    it('validates required fields', function () {
        $staff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::Officer)
            ->create();

        actingAs($staff);

        Volt::test('ready-room.tickets.create-admin-ticket')
            ->set('form.target_user_id', null)
            ->set('form.department', '')
            ->set('form.subject', '')
            ->set('form.message', '')
            ->call('create')
            ->assertHasErrors(['form.target_user_id', 'form.department', 'form.subject', 'form.message']);
    })->done();

    it('creates admin action ticket with correct data', function () {
        $staff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::Officer)
            ->create();

        $targetUser = User::factory()->create();

        actingAs($staff);

        Volt::test('ready-room.tickets.create-admin-ticket')
            ->set('form.target_user_id', $targetUser->id)
            ->set('form.department', StaffDepartment::Chaplain->value)
            ->set('form.subject', 'Account Review')
            ->set('form.message', 'We need to review your account')
            ->call('create')
            ->assertHasNoErrors();

        $thread = Thread::where('subject', 'Account Review')->first();

        expect($thread)->not->toBeNull()
            ->and($thread->subtype)->toBe(ThreadSubtype::AdminAction)
            ->and($thread->department)->toBe(StaffDepartment::Chaplain);
    })->done();

    it('adds both creator and target user as participants', function () {
        $staff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::Officer)
            ->create();

        $targetUser = User::factory()->create();

        actingAs($staff);

        Volt::test('ready-room.tickets.create-admin-ticket')
            ->set('form.target_user_id', $targetUser->id)
            ->set('form.department', StaffDepartment::Chaplain->value)
            ->set('form.subject', 'Test admin ticket')
            ->set('form.message', 'Test message')
            ->call('create');

        $thread = Thread::where('subject', 'Test admin ticket')->first();

        expect($thread->participants()->where('user_id', $staff->id)->exists())->toBeTrue()
            ->and($thread->participants()->where('user_id', $targetUser->id)->exists())->toBeTrue();
    })->done();
});
