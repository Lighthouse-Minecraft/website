<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Models\Blog;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            // Preferred aliases
            'blog' => Blog::class,
            'announcement' => Announcement::class,

            // Backwards-compatibility: handle older stored types
            // - Fully qualified class names
            'App\\Models\\Blog' => Blog::class,
            'App\\Models\\Announcement' => Announcement::class,

            // - Plain class basenames (e.g. "Blog", "Announcement")
            'Blog' => Blog::class,
            'Announcement' => Announcement::class,
        ]);
    }
}
