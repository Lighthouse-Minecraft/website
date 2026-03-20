<?php

use App\Http\Controllers\AdminControlPanelController;
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
    ->middleware(['auth', 'verified', 'ensure-dob'])
    ->name('dashboard');

Volt::route('/birthdate', 'auth.collect-birthdate')
    ->name('birthdate.show')
    ->middleware(['auth']);

Volt::route('/parent-portal', 'parent-portal.index')
    ->name('parent-portal.index')
    ->middleware(['auth', 'verified', 'ensure-dob']);

Volt::route('/parent-portal/{user}', 'parent-portal.index')
    ->name('parent-portal.show')
    ->middleware(['auth', 'verified', 'ensure-dob']);

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
    Volt::route('settings/notifications', 'settings.notifications')->name('settings.notifications');
    Volt::route('settings/minecraft-accounts', 'settings.minecraft-accounts')->name('settings.minecraft-accounts');
    Volt::route('settings/discord-account', 'settings.discord-account')->name('settings.discord-account');
    Volt::route('settings/staff-bio', 'settings.staff-bio')->name('settings.staff-bio');

    Route::get('auth/discord/redirect', [DiscordAuthController::class, 'redirect'])->name('auth.discord.redirect');
    Route::get('auth/discord/callback', [DiscordAuthController::class, 'callback'])->name('auth.discord.callback');
});

Route::get('/profile/{user:slug}', [UserController::class, 'show'])
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

Route::get('community-updates', [CommunityUpdatesController::class, 'index'])->name('community-updates.index');
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

// Discipline Report Routes
Volt::route('/reports/{report}', 'reports.view-report')
    ->name('reports.show')
    ->middleware(['auth']);

// Community Stories
Volt::route('/community-stories', 'community-stories.index')
    ->name('community-stories.index')
    ->middleware(['auth', 'verified', 'ensure-dob', 'can:view-community-stories']);

// Legacy redirects for old /topics URLs
Route::redirect('/topics', '/discussions', 301);
Route::get('/topics/{any}', fn (string $any) => redirect("/discussions/{$any}", 301))->where('any', '.*');

// Discussion System Routes
Route::prefix('discussions')
    ->name('discussions.')
    ->middleware(['auth'])
    ->group(function () {
        Volt::route('/', 'topics.topics-list')->name('index');
        Volt::route('/{thread}', 'topics.view-topic')->name('show');
    });

Route::prefix('meetings')
    ->name('meeting.')
    ->controller(App\Http\Controllers\MeetingController::class)
    ->middleware('auth')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{meeting}/manage', 'edit')->name('edit');
    });

Volt::route('/meetings/{meeting}/report', 'meeting.report-form')
    ->name('meeting.report')
    ->middleware('auth');

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

// Blog Public Routes (no auth)
Volt::route('/blog', 'blog.index')->name('blog.index');
Volt::route('/blog/category/{categorySlug}', 'blog.index')->name('blog.category');
Volt::route('/blog/tag/{tagSlug}', 'blog.index')->name('blog.tag');
Volt::route('/blog/author/{authorSlug}', 'blog.index')->name('blog.author');
Route::get('/blog/rss', \App\Http\Controllers\BlogRssController::class)->name('blog.rss');
Route::get('/blog/sitemap.xml', \App\Http\Controllers\BlogSitemapController::class)->name('blog.sitemap');

// Blog Management
Volt::route('/blog/manage', 'blog.manage')
    ->name('blog.manage')
    ->middleware(['auth', 'can:manage-blog']);

Volt::route('/blog/{slug}', 'blog.show')->name('blog.show');

require __DIR__.'/auth.php';

Volt::route('/staff', 'staff.page')->name('staff.index');

// Staff Applications
Route::prefix('applications')
    ->name('applications.')
    ->middleware(['auth'])
    ->group(function () {
        Volt::route('/', 'staff-applications.my-applications')->name('index');
        Volt::route('/apply/{staffPosition}', 'staff-applications.apply')->name('apply');
        Volt::route('/{staffApplication}', 'staff-applications.show')->name('show');
    });

// Staff Application Review (Command Officers + Admin)
Route::prefix('admin/applications')
    ->name('admin.applications.')
    ->middleware(['auth', 'can:review-staff-applications'])
    ->group(function () {
        Volt::route('/', 'staff-applications.review-list')->name('index');
        Volt::route('/{staffApplication}', 'staff-applications.review-detail')->name('show');
    });

// Documentation / Library routes
Route::prefix('library')->name('library.')->group(function () {
    Volt::route('/books', 'library.books-index')->name('books.index');
    Volt::route('/books/{book}', 'library.book-show')->name('books.show');
    Volt::route('/books/{book}/{part}', 'library.part-show')->name('books.part');
    Volt::route('/books/{book}/{part}/{chapter}', 'library.chapter-show')->name('books.chapter');
    Volt::route('/books/{book}/{part}/{chapter}/{page}', 'library.page-show')->name('books.page');

    Volt::route('/guides', 'library.guides-index')->name('guides.index');
    Volt::route('/guides/{guide}', 'library.guide-show')->name('guides.show');
    Volt::route('/guides/{guide}/{page}', 'library.guide-page')->name('guides.page');

    Route::prefix('editor')->name('editor.')->middleware(['auth', 'ensure-local'])->group(function () {
        Volt::route('/', 'library.editor-index')->name('index');
        Volt::route('/edit', 'library.editor-edit')->name('edit');
        Volt::route('/create', 'library.editor-create')->name('create');
    });
});

Route::get('/{slug}', [PageController::class, 'show'])->name('pages.show');
