<?php

use App\Enums\ThreadType;
use App\Models\Thread;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Volt\Volt;

use function Pest\Laravel\get;

describe('Contact Form', function () {
    it('is accessible without authentication', function () {
        get('/contact')->assertOk();
    });

    it('renders the contact form', function () {
        $component = Volt::test('contact.contact-form');

        $component->assertSee('Send Us a Message');
        $component->assertSee('Email');
        $component->assertSee('Category');
        $component->assertSee('Subject');
        $component->assertSee('Message');
    });

    it('shows validation errors for missing required fields', function () {
        $component = Volt::test('contact.contact-form');

        $component->call('submit');

        $component->assertHasErrors(['email', 'category', 'subject', 'message']);
    });

    it('validates email format', function () {
        $component = Volt::test('contact.contact-form');

        $component->set('email', 'not-an-email')
            ->set('category', 'General Inquiry')
            ->set('subject', 'Test subject')
            ->set('message', 'This is a valid test message.')
            ->call('submit');

        $component->assertHasErrors(['email']);
    });

    it('validates message minimum length', function () {
        $component = Volt::test('contact.contact-form');

        $component->set('email', 'test@example.com')
            ->set('category', 'General Inquiry')
            ->set('subject', 'Test subject')
            ->set('message', 'short')
            ->call('submit');

        $component->assertHasErrors(['message']);
    });

    it('rejects invalid category', function () {
        $component = Volt::test('contact.contact-form');

        $component->set('email', 'test@example.com')
            ->set('category', 'Invalid Category')
            ->set('subject', 'Test subject')
            ->set('message', 'This is a valid test message.')
            ->call('submit');

        $component->assertHasErrors(['category']);
    });

    it('silently passes on honeypot filled by bots', function () {
        Notification::fake();

        $component = Volt::test('contact.contact-form');

        $component->set('email', 'bot@example.com')
            ->set('category', 'General Inquiry')
            ->set('subject', 'Bot submission')
            ->set('message', 'This is a bot message.')
            ->set('website', 'bot-filled-this')
            ->call('submit');

        // Shows submitted state (to fool the bot) but creates no thread
        $component->assertSet('submitted', true);
        expect(Thread::where('guest_email', 'bot@example.com')->exists())->toBeFalse();
        Notification::assertNothingSent();
    });

    it('shows success message after valid submission', function () {
        Notification::fake();

        $component = Volt::test('contact.contact-form');

        $component->set('name', 'Test User')
            ->set('email', 'testuser@example.com')
            ->set('category', 'General Inquiry')
            ->set('subject', 'My question')
            ->set('message', 'This is my detailed question for the team.')
            ->call('submit');

        $component->assertSet('submitted', true)
            ->assertSee('Message Received!');
    });

    it('creates a thread on valid submission', function () {
        Notification::fake();

        $component = Volt::test('contact.contact-form');

        $component->set('name', 'Valid User')
            ->set('email', 'valid@example.com')
            ->set('category', 'Membership / Joining')
            ->set('subject', 'Joining inquiry')
            ->set('message', 'How do I join your community?')
            ->call('submit');

        expect(Thread::where('guest_email', 'valid@example.com')
            ->where('type', ThreadType::ContactInquiry)
            ->exists())->toBeTrue();
    });

    it('enforces IP rate limiting after 5 submissions', function () {
        Notification::fake();

        // Exhaust the rate limit
        RateLimiter::clear('contact-form:127.0.0.1');
        for ($i = 0; $i < 5; $i++) {
            $component = Volt::test('contact.contact-form');
            $component->set('email', "user{$i}@example.com")
                ->set('category', 'General Inquiry')
                ->set('subject', 'Rate limit test')
                ->set('message', 'Testing rate limiting on this form.')
                ->call('submit');
        }

        // 6th submission should fail
        $component = Volt::test('contact.contact-form');
        $component->set('email', 'user6@example.com')
            ->set('category', 'General Inquiry')
            ->set('subject', 'Rate limit test')
            ->set('message', 'Testing rate limiting on this form.')
            ->call('submit');

        $component->assertHasErrors(['email']);

        RateLimiter::clear('contact-form:127.0.0.1');
    });
});
