<?php

use App\Http\Controllers\{AdminControlPanelController, AnnouncementController, PageController, UserController};
use App\Models\{Announcement, Page};
use Illuminate\Support\Facades\{Route};
use Livewire\Volt\{Volt};

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

Route::middleware(['auth'])->prefix('admin/announcements')->group(function () {
    Route::get('/announcements/{announcement}', function (Announcement $announcement) {
        return view('livewire.announcements.show', compact('announcement'));
    })
        ->name('announcement.show');
    Route::get('create', [AnnouncementController::class, 'create'])
        ->name('admin.announcements.create');
    Route::post('/', [AnnouncementController::class, 'store'])
        ->name('admin.announcements.store');
    Route::get('{id}', [AnnouncementController::class, 'show'])
        ->name('admin.announcements.show');
    Route::get('{id}/edit', [AnnouncementController::class, 'edit'])
        ->name('admin.announcements.edit');
    Route::put('{id}', [AnnouncementController::class, 'update'])
        ->name('admin.announcements.update');
    Route::delete('{announcement}', [AnnouncementController::class, 'destroy'])
        ->name('admin.announcements.delete');
});

require __DIR__.'/auth.php';
