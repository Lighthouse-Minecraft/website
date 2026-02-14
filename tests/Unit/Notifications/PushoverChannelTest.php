<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake();
    Config::set('services.pushover.token', 'test-token');
});

it('sends pushover notification with valid configuration', function () {
    $user = User::factory()->create(['pushover_key' => 'user-pushover-key']);

    $notification = new class extends Notification
    {
        public function toPushover($notifiable)
        {
            return [
                'message' => 'Test message',
                'title' => 'Test title',
                'url' => 'https://example.com',
                'priority' => 1,
            ];
        }
    };

    $channel = new PushoverChannel;
    $channel->send($user, $notification);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.pushover.net/1/messages.json'
            && $request['token'] === 'test-token'
            && $request['user'] === 'user-pushover-key'
            && $request['message'] === 'Test message'
            && $request['title'] === 'Test title'
            && $request['url'] === 'https://example.com'
            && $request['priority'] === 1;
    });
});

it('does not send if notification lacks toPushover method', function () {
    $user = User::factory()->create(['pushover_key' => 'user-pushover-key']);

    $notification = new class extends Notification
    {
        // No toPushover method
    };

    $channel = new PushoverChannel;
    $channel->send($user, $notification);

    Http::assertNothingSent();
});

it('does not send if user has no pushover_key', function () {
    $user = User::factory()->create(['pushover_key' => null]);

    $notification = new class extends Notification
    {
        public function toPushover($notifiable)
        {
            return ['message' => 'Test message'];
        }
    };

    $channel = new PushoverChannel;
    $channel->send($user, $notification);

    Http::assertNothingSent();
});

it('does not send if pushover token is not configured', function () {
    Config::set('services.pushover.token', null);

    $user = User::factory()->create(['pushover_key' => 'user-pushover-key']);

    $notification = new class extends Notification
    {
        public function toPushover($notifiable)
        {
            return ['message' => 'Test message'];
        }
    };

    $channel = new PushoverChannel;
    $channel->send($user, $notification);

    Http::assertNothingSent();
});
