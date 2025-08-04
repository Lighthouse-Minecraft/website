<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use App\Http\Controllers\PageController;

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

Route::middleware(['auth'])->group(function () {
    Route::get('acp', [App\Http\Controllers\AdminControlPanelController::class, 'index'])
        ->name('acp.index');
});

Route::get('/acp/pages/create', [PageController::class, 'create'])
    ->name('admin.pages.create')
    ->middleware('can:create,App\Models\Page');

Route::get('/acp/pages/{page}/edit', [PageController::class, 'edit'])
    ->name('admin.pages.edit')
    ->middleware('can:update,page');

require __DIR__.'/auth.php';
