<?php

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Announcement Relations', function () {
    describe('Acknowledgers', function () {
        it('relates an announcement to users who acknowledged it', function () {
            $announcement = Announcement::factory()->create();
            $user = User::factory()->create();

            $announcement->acknowledgers()->syncWithoutDetaching([$user->id]);

            expect($announcement->acknowledgers)->toHaveCount(1);
            expect($announcement->acknowledgers->first()->id)->toBe($user->id);
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
