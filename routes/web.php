<?php

use App\Http\Controllers\AdminControlPanelController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\CommunityUpdatesController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\TaxonomyController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

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
        Route::put('{id}/update', 'update')->name('update');
        Route::get('{id}/destroy', 'confirmDestroy')->name('confirmDelete');
        Route::get('{id}', function ($id) {
            return redirect()->route('acp.announcements.confirmDelete', ['id' => $id]);
        })->whereNumber('id');
        Route::delete('{announcement}', 'destroy')->name('delete');

        // Tag management
        Route::post('{announcement}/addTag', 'addTag')->name('addTag');
        Route::post('{announcement}/attachTag', 'attachTag')->name('attachTag');
        Route::post('{announcement}/removeTag', 'removeTag')->name('removeTag');

        // Category management
        Route::post('{announcement}/addCategory', 'addCategory')->name('addCategory');
        Route::post('{announcement}/attachCategory', 'attachCategory')->name('attachCategory');
        Route::post('{announcement}/removeCategory', 'removeCategory')->name('removeCategory');
    });

Route::get('community-updates', [CommunityUpdatesController::class, 'index'])->name('community-updates.index')->middleware('auth');
Route::get('ready-room', [DashboardController::class, 'readyRoom'])->name('ready-room.index')->middleware('auth');

// This is for non admin announcement links
Route::prefix('announcements')
    ->name('announcements.')
    ->controller(AnnouncementController::class)
    ->middleware('auth')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('{id}', 'show')->name('show');
        Route::post('/', 'store')->name('store');
        Route::put('{id}', 'update')->name('update');
        Route::delete('{id}', 'destroy')->name('destroy');
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
        Route::put('{id}/update', 'update')->name('update');
        Route::get('{id}/destroy', 'confirmDestroy')->name('confirmDelete');
        Route::get('{id}', function ($id) {
            return redirect()->route('acp.blogs.confirmDelete', ['id' => $id]);
        })->whereNumber('id');
        Route::delete('{blog}', 'destroy')->name('delete');

        // Tag management
        Route::post('{blog}/addTag', 'addTag')->name('addTag');
        Route::post('{blog}/attachTag', 'attachTag')->name('attachTag');
        Route::post('{blog}/removeTag', 'removeTag')->name('removeTag');

        // Category management
        Route::post('{blog}/addCategory', 'addCategory')->name('addCategory');
        Route::post('{blog}/attachCategory', 'attachCategory')->name('attachCategory');
        Route::post('{blog}/removeCategory', 'removeCategory')->name('removeCategory');
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

// Admin comment management (announcements & blogs)
Route::prefix('acp/comments')
    ->name('acp.comments.')
    ->controller(CommentController::class)
    ->middleware('auth')
    ->group(function () {
        Route::get('create', 'create')->name('create');
        Route::get('{id}/edit', 'edit')->name('edit');
        Route::post('store', 'store')->name('store');
        Route::put('{id}/update', 'update')->name('update');
        Route::get('{id}/destroy', 'confirmDestroy')->name('confirmDelete');
        Route::get('{id}', function ($id) {
            return redirect()->route('acp.comments.confirmDelete', ['id' => $id]);
        })->whereNumber('id');
        Route::delete('{id}', 'destroy')->name('delete');
        Route::post('{id}/approve', 'approve')->name('approve');
        Route::post('{id}/reject', 'reject')->name('reject');
    });

// Comments (announcements & blogs)
Route::prefix('comments')
    ->name('comments.')
    ->controller(CommentController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('{id}', 'show')->name('show');
        Route::get('{id}/edit', 'edit')->name('edit');
        Route::post('/', 'store')->name('store');
        Route::put('{id}', 'update')->name('update');
        Route::delete('{id}', 'destroy')->name('destroy');
    });

// Admin taxonomy management (categories & tags)
Route::prefix('acp/taxonomy')
    ->name('acp.taxonomy.')
    ->controller(TaxonomyController::class)
    ->middleware('auth')
    ->group(function () {
        Route::prefix('categories')
            ->name('categories.')
            ->group(function () {
                // Categories
                Route::get('show', 'categoriesShow')->name('show');
                Route::get('create', 'categoriesCreate')->name('create');
                Route::post('store', 'categoriesStore')->name('store');
                Route::get('{id}/edit', 'categoriesEdit')->name('edit');
                Route::put('{id}/update', 'categoriesUpdate')->name('update');
                Route::delete('{id}', 'categoriesDestroy')->name('delete');
                Route::post('bulk-delete', 'categoriesBulkDestroy')->name('bulkDelete');
            });

        // Tags
        Route::prefix('tags')
            ->name('tags.')
            ->group(function () {
                Route::get('show', 'tagsShow')->name('show');
                Route::get('create', 'tagsCreate')->name('create');
                Route::post('store', 'tagsStore')->name('store');
                Route::get('{id}/edit', 'tagsEdit')->name('edit');
                Route::put('{id}/update', 'tagsUpdate')->name('update');
                Route::delete('{id}', 'tagsDestroy')->name('delete');
                Route::post('bulk-delete', 'tagsBulkDestroy')->name('bulkDelete');
            });
    });

// Taxonomy
Route::prefix('taxonomy')
    ->name('taxonomy.')
    ->controller(TaxonomyController::class)
    ->group(function () {
        // Categories
        Route::prefix('categories')
            ->name('categories.')
            ->group(function () {
                Route::get('/', [TaxonomyController::class, 'categoriesIndex'])->name('index');
                Route::get('{id}', [TaxonomyController::class, 'categoriesShow'])->name('show');
                Route::post('/', [TaxonomyController::class, 'categoriesStore'])->name('store');
                Route::put('{id}', [TaxonomyController::class, 'categoriesUpdate'])->name('update');
                Route::delete('{id}', [TaxonomyController::class, 'categoriesDestroy'])->name('destroy');
            });
        Route::get('categories/{id}/announcements', [TaxonomyController::class, 'announcementsByCategory'])->name('categories.announcements');
        Route::get('categories/{id}/blogs', [TaxonomyController::class, 'blogsByCategory'])->name('categories.blogs');

        // Tags
        Route::prefix('tags')
            ->name('tags.')
            ->group(function () {
                Route::get('/', [TaxonomyController::class, 'tagsIndex'])->name('index');
                Route::get('/{id}', [TaxonomyController::class, 'tagsShow'])->name('show');
                Route::post('/', [TaxonomyController::class, 'tagsStore'])->name('store');
                Route::put('/{id}', [TaxonomyController::class, 'tagsUpdate'])->name('update');
                Route::delete('/{id}', [TaxonomyController::class, 'tagsDestroy'])->name('destroy');
            });
        Route::get('tags/{id}/announcements', [TaxonomyController::class, 'announcementsByTag'])->name('tags.announcements');
        Route::get('tags/{id}/blogs', [TaxonomyController::class, 'blogsByTag'])->name('tags.blogs');
    });

Route::prefix('meetings')->name('meeting.')->controller(App\Http\Controllers\MeetingController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/{meeting}/manage', 'edit')->name('edit')->middleware('can:update,meeting');
});


require __DIR__.'/auth.php';

Route::get('/{slug}', [PageController::class, 'show'])->name('pages.show');
