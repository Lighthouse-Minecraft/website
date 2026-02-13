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
            ->assertSee('Create New Ticket')
            ->assertSee('Department')
            ->assertSee('Subject')
            ->assertSee('Message');
    })->done();

    it('validates required fields', function () {
        $user = User::factory()->create();

        actingAs($user);

        Volt::test('ready-room.tickets.create-ticket')
            ->set('department', '')
            ->set('subject', '')
            ->set('message', '')
            ->call('createTicket')
            ->assertHasErrors(['department', 'subject', 'message']);
    })->done();

    it('creates support ticket with correct data', function () {
        $user = User::factory()->create();

        actingAs($user);

        Volt::test('ready-room.tickets.create-ticket')
            ->set('department', StaffDepartment::Chaplain->value)
            ->set('subject', 'Need help with prayer request')
            ->set('message', 'I would like to submit a prayer request')
            ->call('createTicket')
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
            ->set('department', StaffDepartment::Chaplain->value)
            ->set('subject', 'Test ticket')
            ->set('message', 'Test message')
            ->call('createTicket');

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
            ->set('target_user_id', '')
            ->set('department', '')
            ->set('subject', '')
            ->set('message', '')
            ->call('createAdminTicket')
            ->assertHasErrors(['target_user_id', 'department', 'subject', 'message']);
    })->done();

    it('creates admin action ticket with correct data', function () {
        $staff = User::factory()
            ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::Officer)
            ->create();

        $targetUser = User::factory()->create();

        actingAs($staff);

        Volt::test('ready-room.tickets.create-admin-ticket')
            ->set('target_user_id', $targetUser->id)
            ->set('department', StaffDepartment::Chaplain->value)
            ->set('subject', 'Account Review')
            ->set('message', 'We need to review your account')
            ->call('createAdminTicket')
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
            ->set('target_user_id', $targetUser->id)
            ->set('department', StaffDepartment::Chaplain->value)
            ->set('subject', 'Test admin ticket')
            ->set('message', 'Test message')
            ->call('createAdminTicket');

        $thread = Thread::where('subject', 'Test admin ticket')->first();

        expect($thread->participants()->where('user_id', $staff->id)->exists())->toBeTrue()
            ->and($thread->participants()->where('user_id', $targetUser->id)->exists())->toBeTrue();
    })->done();
});
