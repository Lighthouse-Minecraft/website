<?php

declare(strict_types=1);

use App\Models\DisciplineReport;
use App\Models\User;
use App\Notifications\Channels\PushoverChannel;
use App\Notifications\DisciplineReportPublishedParentNotification;

uses()->group('discipline-reports', 'notifications');

it('uses the parent template', function () {
    $report = DisciplineReport::factory()->published()->create();
    $notification = new DisciplineReportPublishedParentNotification($report);

    $mail = $notification->toMail(new stdClass);

    expect($mail->markdown)->toBe('mail.discipline-report-published-parent');
});

it('passes child name to template', function () {
    $child = User::factory()->create(['name' => 'TestChild']);
    $report = DisciplineReport::factory()->published()->forSubject($child)->create();
    $notification = new DisciplineReportPublishedParentNotification($report);

    $mail = $notification->toMail(new stdClass);

    expect($mail->viewData['childName'])->toBe('TestChild')
        ->and($mail->viewData['report'])->toBe($report);
});

it('has parent-specific subject line', function () {
    $report = DisciplineReport::factory()->published()->create();
    $notification = new DisciplineReportPublishedParentNotification($report);

    $mail = $notification->toMail(new stdClass);

    expect($mail->subject)->toBe('Staff Report Recorded for Your Child');
});

it('pushover message references child', function () {
    $child = User::factory()->create(['name' => 'ChildPlayer']);
    $report = DisciplineReport::factory()->published()->forSubject($child)->create();
    $notification = new DisciplineReportPublishedParentNotification($report);

    $pushover = $notification->toPushover(new stdClass);

    expect($pushover['message'])->toContain('ChildPlayer')
        ->and($pushover['message'])->toContain('your child');
});

it('sends via mail channel when allowed', function () {
    $report = DisciplineReport::factory()->published()->create();
    $notification = new DisciplineReportPublishedParentNotification($report);
    $notification->setChannels(['mail']);

    expect($notification->via(new stdClass))->toBe(['mail']);
});

it('sends via pushover channel when allowed and key is set', function () {
    $report = DisciplineReport::factory()->published()->create();
    $notification = new DisciplineReportPublishedParentNotification($report);
    $notification->setChannels(['mail', 'pushover'], 'test-key');

    expect($notification->via(new stdClass))->toContain(PushoverChannel::class);
});

it('is queued for background processing', function () {
    $report = DisciplineReport::factory()->published()->create();
    $notification = new DisciplineReportPublishedParentNotification($report);

    expect($notification)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
});
