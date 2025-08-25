<?php

use App\Models\Blog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Blog Model Relations', function () {
    describe('Acknowledgers', function () {
        it('relates a blog to users who acknowledged it', function () {
            $blog = Blog::factory()->create();
            $user = User::factory()->create();

            $blog->acknowledgers()->syncWithoutDetaching([$user->id]);

            expect($blog->acknowledgers)->toHaveCount(1);
            expect($blog->acknowledgers->first()->id)->toBe($user->id);
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
