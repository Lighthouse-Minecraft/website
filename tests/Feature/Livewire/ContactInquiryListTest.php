<?php

use App\Enums\ThreadStatus;
use App\Enums\ThreadType;
use App\Models\Thread;
use App\Models\User;
use Livewire\Volt\Volt;

use function Pest\Laravel\get;

describe('Contact Inquiry List', function () {
    it('denies access to unauthenticated users', function () {
        get('/contact-inquiries')->assertRedirect('/login');
    });

    it('denies access to authenticated users without the role', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        get('/contact-inquiries')->assertForbidden();
    });

    it('allows access to users with the Contact - Receive Submissions role', function () {
        $staff = User::factory()->withRole('Contact - Receive Submissions')->create();
        $this->actingAs($staff);

        get('/contact-inquiries')->assertOk();
    });

    it('allows access to admins', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        get('/contact-inquiries')->assertOk();
    });

    it('renders the inquiry list heading', function () {
        $staff = User::factory()->withRole('Contact - Receive Submissions')->create();
        $this->actingAs($staff);

        $component = Volt::test('contact.inquiry-list');
        $component->assertSee('Contact Inquiries');
    });

    it('lists contact inquiry threads', function () {
        $staff = User::factory()->withRole('Contact - Receive Submissions')->create();
        $this->actingAs($staff);

        $thread = Thread::create([
            'type' => ThreadType::ContactInquiry,
            'subject' => '[General Inquiry] Hello world',
            'status' => ThreadStatus::Open,
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'conversation_token' => \Illuminate\Support\Str::uuid(),
            'last_message_at' => now(),
        ]);

        $component = Volt::test('contact.inquiry-list');
        $component->assertSee('Hello world')
            ->assertSee('Test Guest')
            ->assertSee('guest@example.com')
            ->assertSee('General Inquiry');
    });

    it('does not show non-contact-inquiry threads', function () {
        $staff = User::factory()->withRole('Contact - Receive Submissions')->create();
        $this->actingAs($staff);

        Thread::create([
            'type' => \App\Enums\ThreadType::Ticket,
            'subject' => 'Should not appear',
            'status' => ThreadStatus::Open,
            'created_by_user_id' => $staff->id,
            'department' => \App\Enums\StaffDepartment::Command,
            'last_message_at' => now(),
        ]);

        $component = Volt::test('contact.inquiry-list');
        $component->assertDontSee('Should not appear');
    });

    it('shows open threads by default and hides closed threads', function () {
        $staff = User::factory()->withRole('Contact - Receive Submissions')->create();
        $this->actingAs($staff);

        Thread::create([
            'type' => ThreadType::ContactInquiry,
            'subject' => '[General Inquiry] Open thread',
            'status' => ThreadStatus::Open,
            'guest_name' => 'Open Guest',
            'guest_email' => 'open@example.com',
            'conversation_token' => \Illuminate\Support\Str::uuid(),
            'last_message_at' => now(),
        ]);

        Thread::create([
            'type' => ThreadType::ContactInquiry,
            'subject' => '[General Inquiry] Closed thread',
            'status' => ThreadStatus::Closed,
            'guest_name' => 'Closed Guest',
            'guest_email' => 'closed@example.com',
            'conversation_token' => \Illuminate\Support\Str::uuid(),
            'last_message_at' => now()->subDay(),
        ]);

        $component = Volt::test('contact.inquiry-list');
        $component->assertSee('Open thread')
            ->assertDontSee('Closed thread');
    });

    it('shows closed threads when closed filter is selected', function () {
        $staff = User::factory()->withRole('Contact - Receive Submissions')->create();
        $this->actingAs($staff);

        Thread::create([
            'type' => ThreadType::ContactInquiry,
            'subject' => '[General Inquiry] Closed thread',
            'status' => ThreadStatus::Closed,
            'guest_name' => 'Closed Guest',
            'guest_email' => 'closed@example.com',
            'conversation_token' => \Illuminate\Support\Str::uuid(),
            'last_message_at' => now()->subDay(),
        ]);

        $component = Volt::test('contact.inquiry-list');
        $component->set('filter', 'closed')
            ->assertSee('Closed thread');
    });

    it('sorts threads by most recent activity descending', function () {
        $staff = User::factory()->withRole('Contact - Receive Submissions')->create();
        $this->actingAs($staff);

        Thread::create([
            'type' => ThreadType::ContactInquiry,
            'subject' => '[General Inquiry] Older thread',
            'status' => ThreadStatus::Open,
            'guest_email' => 'old@example.com',
            'conversation_token' => \Illuminate\Support\Str::uuid(),
            'last_message_at' => now()->subHours(2),
        ]);

        Thread::create([
            'type' => ThreadType::ContactInquiry,
            'subject' => '[General Inquiry] Newer thread',
            'status' => ThreadStatus::Open,
            'guest_email' => 'new@example.com',
            'conversation_token' => \Illuminate\Support\Str::uuid(),
            'last_message_at' => now(),
        ]);

        $component = Volt::test('contact.inquiry-list');
        $html = $component->html();

        // Newer thread should appear before older thread
        expect(strpos($html, 'Newer thread'))->toBeLessThan(strpos($html, 'Older thread'));
    });

    it('shows unread indicator for threads with no participant record', function () {
        $staff = User::factory()->withRole('Contact - Receive Submissions')->create();
        $this->actingAs($staff);

        Thread::create([
            'type' => ThreadType::ContactInquiry,
            'subject' => '[General Inquiry] Unread thread',
            'status' => ThreadStatus::Open,
            'guest_email' => 'unread@example.com',
            'conversation_token' => \Illuminate\Support\Str::uuid(),
            'last_message_at' => now(),
        ]);

        $component = Volt::test('contact.inquiry-list');
        $component->assertSee('New');
    });
});
