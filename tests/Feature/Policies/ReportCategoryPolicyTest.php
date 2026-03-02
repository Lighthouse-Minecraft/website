<?php

declare(strict_types=1);

use App\Models\ReportCategory;

uses()->group('discipline-reports', 'policies');

it('allows authorized roles to manage report categories', function (Closure $createUser, bool $canManage) {
    $user = $createUser();

    expect($user->can('viewAny', ReportCategory::class))->toBe($canManage)
        ->and($user->can('create', ReportCategory::class))->toBe($canManage);
})->with([
    'admin' => [fn () => loginAsAdmin(), true],
    'command officer' => [fn () => tap(officerCommand(), fn ($u) => loginAs($u)), true],
    'quartermaster officer' => [fn () => tap(officerQuartermaster(), fn ($u) => loginAs($u)), true],
    'crew' => [fn () => tap(crewQuartermaster(), fn ($u) => loginAs($u)), false],
]);

it('prevents deletion of report categories', function () {
    $admin = loginAsAdmin();
    $category = ReportCategory::factory()->create();

    expect($admin->can('delete', $category))->toBeFalse();
});
