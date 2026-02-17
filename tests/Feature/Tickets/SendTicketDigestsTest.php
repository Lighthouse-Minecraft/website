<?php

declare(strict_types=1);

use App\Console\Commands\SendTicketDigests;
use App\Enums\EmailDigestFrequency;
use App\Models\User;

it('runs successfully with daily frequency', function () {
    User::factory()->create([
        'email_digest_frequency' => EmailDigestFrequency::Daily,
    ]);

    $this->artisan(SendTicketDigests::class, ['frequency' => 'daily'])
        ->assertSuccessful();
});

it('runs successfully with weekly frequency', function () {
    User::factory()->create([
        'email_digest_frequency' => EmailDigestFrequency::Weekly,
    ]);

    $this->artisan(SendTicketDigests::class, ['frequency' => 'weekly'])
        ->assertSuccessful();
});

it('fails with invalid frequency', function () {
    $this->artisan(SendTicketDigests::class, ['frequency' => 'monthly'])
        ->assertFailed();
});
