<?php

use App\Http\Controllers\AdminControlPanelController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\TaxonomyController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/pages/{slug}', [PageController::class, 'show'])->name('pages.show');

Route::get('/', function () {
    return redirect()->route('pages.show', ['slug' => 'home']);
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

Route::get('/profile/{user}', [UserController::class, 'show'])
    ->name('profile.show')
    ->middleware('can:view,user');

Route::middleware(['auth'])->group(function () {
    Route::get('acp', [AdminControlPanelController::class, 'index'])
        ->name('acp.index');
});

Route::get('/acp/pages/create', [PageController::class, 'create'])
    ->name('admin.pages.create')
    ->middleware('can:create,App\Models\Page');

Route::get('/acp/pages/{page}/edit', [PageController::class, 'edit'])
    ->name('admin.pages.edit')
    ->middleware('can:update,page');

// This is for admin announcement links
Route::prefix('acp/announcements')
    ->name('acp.announcements.')
    ->controller(AnnouncementController::class)
    ->middleware('auth')
    ->group(function () {
        Route::get('show', 'show')->name('show');
        Route::get('create', 'create')->name('create');
        Route::post('store', 'store')->name('store');
        Route::get('{id}/edit', 'edit')->name('edit');
        Route::put('{/id}/update', 'update')->name('update');
        Route::delete('{announcement}', 'destroy')->name('delete');
    });

// This is for non admin announcement links
Route::prefix('announcements')
    ->name('announcements.')
    ->controller(AnnouncementController::class)
    ->middleware('auth')
    ->group(function () {

        Route::get(uri: '{id}', action: 'show')->name('show');
    });

// This is for admin blog links
Route::prefix('acp/blogs')
    ->name('acp.blogs.')
    ->controller(BlogController::class)
    ->middleware('auth')
    ->group(function () {
        Route::get('show', 'show')->name('show');
        Route::get('create', 'create')->name('create');
        Route::post('store', 'store')->name('store');
        Route::get('{id}/edit', 'edit')->name('edit');
        Route::put('{/id}/update', 'update')->name('update');
        Route::delete('{blog}', 'destroy')->name('delete');
    });

// This is for non admin blog links
Route::prefix('blogs')
    ->name('blogs.')
    ->controller(BlogController::class)
    ->middleware('auth')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('{id}', 'show')->name('show');
        Route::post('/', 'store')->name('store');
        Route::put('{id}', 'update')->name('update');
        Route::delete('{id}', 'destroy')->name('destroy');
    });

// Comments management (announcements & blogs)
Route::prefix('comments')
    ->name('comments.')
    ->controller(CommentController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('{id}', 'show')->name('show');
        Route::post('/', 'store')->name('store');
        Route::put('{id}', 'update')->name('update');
        Route::delete('{id}', 'destroy')->name('destroy');
    });

Route::group(['Taxonomy'], function () {
    // Category management
    Route::prefix('categories')
        ->name('categories.')
        ->group(function () {
            Route::get('/', [TaxonomyController::class, 'categoriesIndex'])->name('index');
            Route::get('{id}', [TaxonomyController::class, 'categoriesShow'])->name('show');
            Route::post('/', [TaxonomyController::class, 'categoriesStore'])->name('store');
            Route::put('{id}', [TaxonomyController::class, 'categoriesUpdate'])->name('update');
            Route::delete('{id}', [TaxonomyController::class, 'categoriesDestroy'])->name('destroy');
        });

    // Tag management
    Route::prefix('tags')
        ->name('tags.')
        ->group(function () {
            Route::get('/', [TaxonomyController::class, 'tagsIndex'])->name('index');
            Route::get('/{id}', [TaxonomyController::class, 'tagsShow'])->name('show');
            Route::post('/', [TaxonomyController::class, 'tagsStore'])->name('store');
            Route::put('/{id}', [TaxonomyController::class, 'tagsUpdate'])->name('update');
            Route::delete('/{id}', [TaxonomyController::class, 'tagsDestroy'])->name('destroy');
        });

    // Endpoints
    Route::get('categories/{id}/blogs', [TaxonomyController::class, 'blogsByCategory'])->name('categories.blogs');
    Route::get('tags/{id}/blogs', [TaxonomyController::class, 'blogsByTag'])->name('tags.blogs');
});

Route::prefix('meetings')
    ->name('meeting.')
    ->controller(MeetingController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{meeting}', 'show')->name('show')->middleware('can:view,meeting');
    });

require __DIR__.'/auth.php';
