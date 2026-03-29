<?php

declare(strict_types=1);

use App\Enums\MessageKind;
use App\Enums\ThreadStatus;
use App\Enums\ThreadType;
use App\Jobs\EscalateUnassignedTickets;
use App\Models\Message;
use App\Models\SiteConfig;
use App\Models\Thread;
use App\Models\User;
use App\Notifications\TicketEscalationNotification;
use App\Services\TicketNotificationService;
use Illuminate\Support\Facades\Notification;

describe('EscalateUnassignedTickets Job', function () {
    it('sends escalation notification to users with Ticket Escalation - Receiver role', function () {
        Notification::fake();

        $recipient = User::factory()
            ->withRole('Ticket Escalation - Receiver')
            ->create(['notification_preferences' => ['staff_alerts' => ['email' => true]]]);

        $ticket = Thread::factory()->create([
            'type' => ThreadType::Ticket,
            'status' => ThreadStatus::Open,
            'assigned_to_user_id' => null,
            'escalated_at' => null,
            'created_at' => now()->subMinutes(35),
        ]);

        (new EscalateUnassignedTickets)->handle(app(TicketNotificationService::class));

        Notification::assertSentTo($recipient, TicketEscalationNotification::class);
    });

    it('sets escalated_at after sending notification', function () {
        Notification::fake();

        User::factory()
            ->withRole('Ticket Escalation - Receiver')
            ->create(['notification_preferences' => ['staff_alerts' => ['email' => true]]]);

        $ticket = Thread::factory()->create([
            'type' => ThreadType::Ticket,
            'status' => ThreadStatus::Open,
            'assigned_to_user_id' => null,
            'escalated_at' => null,
            'created_at' => now()->subMinutes(35),
        ]);

        (new EscalateUnassignedTickets)->handle(app(TicketNotificationService::class));

        $ticket->refresh();
        expect($ticket->escalated_at)->not->toBeNull();
    });

    it('does not escalate already-assigned tickets', function () {
        Notification::fake();

        $assignee = User::factory()->create();
        User::factory()
            ->withRole('Ticket Escalation - Receiver')
            ->create(['notification_preferences' => ['staff_alerts' => ['email' => true]]]);

        Thread::factory()->create([
            'type' => ThreadType::Ticket,
            'status' => ThreadStatus::Open,
            'assigned_to_user_id' => $assignee->id,
            'escalated_at' => null,
            'created_at' => now()->subMinutes(35),
        ]);

        (new EscalateUnassignedTickets)->handle(app(TicketNotificationService::class));

        Notification::assertNothingSent();
    });

    it('does not escalate closed tickets', function () {
        Notification::fake();

        User::factory()
            ->withRole('Ticket Escalation - Receiver')
            ->create(['notification_preferences' => ['staff_alerts' => ['email' => true]]]);

        Thread::factory()->create([
            'type' => ThreadType::Ticket,
            'status' => ThreadStatus::Closed,
            'assigned_to_user_id' => null,
            'escalated_at' => null,
            'created_at' => now()->subMinutes(35),
        ]);

        (new EscalateUnassignedTickets)->handle(app(TicketNotificationService::class));

        Notification::assertNothingSent();
    });

    it('does not escalate resolved tickets', function () {
        Notification::fake();

        User::factory()
            ->withRole('Ticket Escalation - Receiver')
            ->create(['notification_preferences' => ['staff_alerts' => ['email' => true]]]);

        Thread::factory()->create([
            'type' => ThreadType::Ticket,
            'status' => ThreadStatus::Resolved,
            'assigned_to_user_id' => null,
            'escalated_at' => null,
            'created_at' => now()->subMinutes(35),
        ]);

        (new EscalateUnassignedTickets)->handle(app(TicketNotificationService::class));

        Notification::assertNothingSent();
    });

    it('does not re-escalate already-escalated tickets', function () {
        Notification::fake();

        User::factory()
            ->withRole('Ticket Escalation - Receiver')
            ->create(['notification_preferences' => ['staff_alerts' => ['email' => true]]]);

        Thread::factory()->create([
            'type' => ThreadType::Ticket,
            'status' => ThreadStatus::Open,
            'assigned_to_user_id' => null,
            'escalated_at' => now()->subMinutes(10),
            'created_at' => now()->subMinutes(45),
        ]);

        (new EscalateUnassignedTickets)->handle(app(TicketNotificationService::class));

        Notification::assertNothingSent();
    });

    it('does not escalate tickets created within the threshold window', function () {
        Notification::fake();

        User::factory()
            ->withRole('Ticket Escalation - Receiver')
            ->create(['notification_preferences' => ['staff_alerts' => ['email' => true]]]);

        Thread::factory()->create([
            'type' => ThreadType::Ticket,
            'status' => ThreadStatus::Open,
            'assigned_to_user_id' => null,
            'escalated_at' => null,
            'created_at' => now()->subMinutes(10), // within 30-minute threshold
        ]);

        (new EscalateUnassignedTickets)->handle(app(TicketNotificationService::class));

        Notification::assertNothingSent();
    });

    it('respects the ticket_escalation_threshold_minutes site config', function () {
        Notification::fake();

        SiteConfig::setValue('ticket_escalation_threshold_minutes', '60');

        User::factory()
            ->withRole('Ticket Escalation - Receiver')
            ->create(['notification_preferences' => ['staff_alerts' => ['email' => true]]]);

        // Created 45 minutes ago — past default 30 but within custom 60
        Thread::factory()->create([
            'type' => ThreadType::Ticket,
            'status' => ThreadStatus::Open,
            'assigned_to_user_id' => null,
            'escalated_at' => null,
            'created_at' => now()->subMinutes(45),
        ]);

        (new EscalateUnassignedTickets)->handle(app(TicketNotificationService::class));

        Notification::assertNothingSent();
    });

    it('sends to multiple recipients when multiple users have the role', function () {
        Notification::fake();

        $recipient1 = User::factory()
            ->withRole('Ticket Escalation - Receiver')
            ->create(['notification_preferences' => ['staff_alerts' => ['email' => true]]]);

        $recipient2 = User::factory()
            ->withRole('Ticket Escalation - Receiver')
            ->create(['notification_preferences' => ['staff_alerts' => ['email' => true]]]);

        Thread::factory()->create([
            'type' => ThreadType::Ticket,
            'status' => ThreadStatus::Open,
            'assigned_to_user_id' => null,
            'escalated_at' => null,
            'created_at' => now()->subMinutes(35),
        ]);

        (new EscalateUnassignedTickets)->handle(app(TicketNotificationService::class));

        Notification::assertSentTo($recipient1, TicketEscalationNotification::class);
        Notification::assertSentTo($recipient2, TicketEscalationNotification::class);
    });

    it('posts a system message in the thread when escalating', function () {
        Notification::fake();

        User::factory()
            ->withRole('Ticket Escalation - Receiver')
            ->create(['notification_preferences' => ['staff_alerts' => ['email' => true]]]);

        $ticket = Thread::factory()->create([
            'type' => ThreadType::Ticket,
            'status' => ThreadStatus::Open,
            'assigned_to_user_id' => null,
            'escalated_at' => null,
            'created_at' => now()->subMinutes(35),
        ]);

        (new EscalateUnassignedTickets)->handle(app(TicketNotificationService::class));

        $systemMessage = Message::where('thread_id', $ticket->id)
            ->where('kind', MessageKind::System)
            ->first();

        expect($systemMessage)->not->toBeNull()
            ->and($systemMessage->body)->toContain('escalated');
    });

    it('is idempotent — running twice does not double-escalate', function () {
        Notification::fake();

        $recipient = User::factory()
            ->withRole('Ticket Escalation - Receiver')
            ->create(['notification_preferences' => ['staff_alerts' => ['email' => true]]]);

        Thread::factory()->create([
            'type' => ThreadType::Ticket,
            'status' => ThreadStatus::Open,
            'assigned_to_user_id' => null,
            'escalated_at' => null,
            'created_at' => now()->subMinutes(35),
        ]);

        $job = new EscalateUnassignedTickets;
        $service = app(TicketNotificationService::class);

        $job->handle($service);
        $job->handle($service);

        Notification::assertSentToTimes($recipient, TicketEscalationNotification::class, 1);
    });

    it('does not escalate non-ticket thread types', function () {
        Notification::fake();

        User::factory()
            ->withRole('Ticket Escalation - Receiver')
            ->create(['notification_preferences' => ['staff_alerts' => ['email' => true]]]);

        Thread::factory()->topic()->create([
            'status' => ThreadStatus::Open,
            'assigned_to_user_id' => null,
            'escalated_at' => null,
            'created_at' => now()->subMinutes(35),
        ]);

        (new EscalateUnassignedTickets)->handle(app(TicketNotificationService::class));

        Notification::assertNothingSent();
    });
});
