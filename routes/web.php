<?php

use App\Http\Controllers\AdminControlPanelController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\CommunityUpdatesController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DonationController;
use App\Http\Controllers\PageController;
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

        Route::get('create', 'create')->name('create');
        Route::post('store', 'store')->name('store');
        Route::get('{id}/edit', 'edit')->name('edit');
        Route::put('{/id}/update', 'update')->name('update');
        Route::delete('{announcement}', 'destroy')->name('delete');
    });

Route::get('community-updates', [CommunityUpdatesController::class, 'index'])->name('community-updates.index')->middleware('auth');
Route::get('ready-room', [DashboardController::class, 'readyRoom'])->name('ready-room.index')->middleware('auth');

// This is for non admin announcement links
Route::prefix('announcements')
    ->name('announcements.')
    ->controller(AnnouncementController::class)
    ->middleware('auth')
    ->group(function () {

        Route::get(uri: '{id}', action: 'show')->name('show');
    });

Route::prefix('meetings')
    ->name('meeting.')
    ->controller(App\Http\Controllers\MeetingController::class)
    ->middleware('auth')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{meeting}/manage', 'edit')->name('edit');
    });

Route::get('/donate', [DonationController::class, 'index'])->name('donate');

require __DIR__.'/auth.php';

Route::get('/{slug}', [PageController::class, 'show'])->name('pages.show');
