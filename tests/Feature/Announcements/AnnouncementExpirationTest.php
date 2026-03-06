<?php

declare(strict_types=1);

use App\Models\Announcement;

uses()->group('announcements');

it('scopePublished excludes expired announcements', function () {
    Announcement::factory()->published()->create(['expired_at' => now()->subDay()]);
    Announcement::factory()->published()->create(['expired_at' => null]);

    expect(Announcement::published()->count())->toBe(1);
});

it('scopePublished excludes future-scheduled announcements', function () {
    Announcement::factory()->create([
        'is_published' => true,
        'published_at' => now()->addDay(),
    ]);
    Announcement::factory()->published()->create();

    expect(Announcement::published()->count())->toBe(1);
});

it('scopePublished includes announcements with future expiry', function () {
    Announcement::factory()->published()->create(['expired_at' => now()->addWeek()]);

    expect(Announcement::published()->count())->toBe(1);
});

it('isExpired returns true for past expired_at', function () {
    $announcement = Announcement::factory()->published()->create(['expired_at' => now()->subHour()]);

    expect($announcement->isExpired())->toBeTrue();
});

it('isExpired returns false when expired_at is null', function () {
    $announcement = Announcement::factory()->published()->create(['expired_at' => null]);

    expect($announcement->isExpired())->toBeFalse();
});
