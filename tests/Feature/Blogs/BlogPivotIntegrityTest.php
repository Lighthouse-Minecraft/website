<?php

use App\Models\Blog;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

describe('Blog Pivot Integrity', function () {
    it('enforces unique (author_id, blog_id) on acknowledged_blogs', function () {
        $u = User::factory()->create();
        $b = Blog::factory()->create();

        DB::table('acknowledged_blogs')->insert([
            'author_id' => $u->id,
            'blog_id' => $b->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('acknowledged_blogs')->insert([
            'author_id' => $u->id,
            'blog_id' => $b->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
