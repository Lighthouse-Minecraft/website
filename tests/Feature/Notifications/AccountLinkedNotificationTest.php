<?php

declare(strict_types=1);

use App\Models\ParentChildLink;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AccountLinkedNotification;
use Illuminate\Support\Facades\Notification;

uses()->group('notifications');

it('sends a mail notification to parent when child links a Minecraft account', function () {
    Notification::fake();

    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->create();
    ParentChildLink::create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);

    $notification = new AccountLinkedNotification($child, 'CoolKid123', 'Minecraft', 1, 0, 0, 0);
    foreach ($child->parents as $p) {
        $p->notify($notification->setChannels(['mail']));
    }

    Notification::assertSentTo($parent, AccountLinkedNotification::class);
});

it('sends a mail notification to parent when child links a Discord account', function () {
    Notification::fake();

    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->create();
    ParentChildLink::create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);

    $notification = new AccountLinkedNotification($child, 'DiscordUser#1234', 'Discord', 0, 0, 1, 0);
    foreach ($child->parents as $p) {
        $p->notify($notification->setChannels(['mail']));
    }

    Notification::assertSentTo($parent, AccountLinkedNotification::class);
});

it('sends notification to staff with User - Manager role', function () {
    Notification::fake();

    $child = User::factory()->create();
    $manager = User::factory()->withRole('User - Manager')->create();

    $notification = new AccountLinkedNotification($child, 'TestAccount', 'Minecraft', 1, 0, 0, 0);

    $userManagerRoleId = Role::where('name', 'User - Manager')->value('id');
    $managers = User::whereHas('staffPosition.roles', fn ($r) => $r->where('roles.id', $userManagerRoleId))->get();

    foreach ($managers as $m) {
        $m->notify($notification->setChannels(['mail']));
    }

    Notification::assertSentTo($manager, AccountLinkedNotification::class);
});

it('does not send parent notification when user has no parent', function () {
    Notification::fake();

    $user = User::factory()->create();

    $notification = new AccountLinkedNotification($user, 'SoloUser', 'Minecraft', 1, 0, 0, 0);
    foreach ($user->parents as $p) {
        $p->notify($notification->setChannels(['mail']));
    }

    Notification::assertNothingSent();
});

it('notification mail contains correct account details', function () {
    $user = User::factory()->create(['name' => 'TestPlayer']);
    $notification = new AccountLinkedNotification($user, 'CoolKid123', 'Minecraft', 2, 1, 1, 0);

    $mail = $notification->toMail($user);

    expect($mail->subject)->toContain('Minecraft')
        ->and($mail->subject)->toContain('TestPlayer');

    $intro = collect($mail->introLines)->join(' ');
    expect($intro)->toContain('CoolKid123')
        ->and($intro)->toContain('2 active Minecraft')
        ->and($intro)->toContain('1 disabled Minecraft')
        ->and($intro)->toContain('1 active Discord');
});
