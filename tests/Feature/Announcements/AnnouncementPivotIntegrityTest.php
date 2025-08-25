<?php

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

describe('Announcement Pivot Integrity', function () {
    it('does not create duplicate rows when acknowledging the same announcement twice', function () {
        $user = User::factory()->create();
        $announcement = Announcement::factory()->create();

        // Use the configured pivot (announcement_user) via the relation layer
        $announcement->acknowledgers()->syncWithoutDetaching([$user->id]);
        $announcement->acknowledgers()->syncWithoutDetaching([$user->id]);

        $count = DB::table('announcement_user')
            ->where('user_id', $user->id)
            ->where('announcement_id', $announcement->id)
            ->count();

        expect($count)->toBe(1);
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
