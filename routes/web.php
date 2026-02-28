<?php

use App\Http\Controllers\AdminControlPanelController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\CommunityUpdatesController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscordAuthController;
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
    Volt::route('settings/notifications', 'settings.notifications')->name('settings.notifications');
    Volt::route('settings/minecraft-accounts', 'settings.minecraft-accounts')->name('settings.minecraft-accounts');
    Volt::route('settings/discord-account', 'settings.discord-account')->name('settings.discord-account');

    Route::get('auth/discord/redirect', [DiscordAuthController::class, 'redirect'])->name('auth.discord.redirect');
    Route::get('auth/discord/callback', [DiscordAuthController::class, 'callback'])->name('auth.discord.callback');
});

Route::get('/profile/{user}', [UserController::class, 'show'])
    ->name('profile.show')
    ->middleware(['auth', 'can:view,user']);

Route::middleware(['auth'])->group(function () {
    Route::get('acp', [AdminControlPanelController::class, 'index'])
        ->name('acp.index');
});

Route::get('/acp/pages/create', [PageController::class, 'create'])
    ->name('admin.pages.create')
    ->middleware(['auth', 'can:create,App\Models\Page']);

Route::get('/acp/pages/{page}/edit', [PageController::class, 'edit'])
    ->name('admin.pages.edit')
    ->middleware(['auth', 'can:update,page']);

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

// Ticket System Routes
Route::prefix('tickets')
    ->name('tickets.')
    ->middleware(['auth', 'track-notification-read'])
    ->group(function () {
        Volt::route('/', 'ready-room.tickets.tickets-list')->name('index');
        Volt::route('/create', 'ready-room.tickets.create-ticket')->name('create');
        Volt::route('/create-admin', 'ready-room.tickets.create-admin-ticket')
            ->name('create-admin')
            ->middleware('can:createAsStaff,App\Models\Thread');
        Volt::route('/{thread}', 'ready-room.tickets.view-ticket')->name('show');
    });

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

// Minecraft verification webhook - throttled to 30 requests per minute
Route::post('/api/minecraft/verify', function (\Illuminate\Http\Request $request) {
    // Verify server token first
    if ($request->server_token !== config('services.minecraft.verification_token')) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid server token.',
        ], 401);
    }

    $request->validate([
        'code' => 'required|string|size:6',
        'minecraft_username' => 'required|string',
        'minecraft_uuid' => 'required|string',
        'is_bedrock' => 'sometimes|boolean',
        'bedrock_username' => 'sometimes|string',
        'bedrock_xuid' => 'sometimes|string',
    ]);

    $result = \App\Actions\CompleteVerification::run(
        $request->code,
        $request->minecraft_username,
        $request->minecraft_uuid,
        bedrockUsername: $request->input('bedrock_username'),
        bedrockXuid: $request->input('bedrock_xuid'),
    );

    return response()->json($result);
})->middleware('throttle:30,1');

require __DIR__.'/auth.php';

Route::get('/{slug}', [PageController::class, 'show'])->name('pages.show');
