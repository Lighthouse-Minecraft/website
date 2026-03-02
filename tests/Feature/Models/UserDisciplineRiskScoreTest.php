<?php

declare(strict_types=1);

use App\Models\DisciplineReport;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

uses()->group('discipline-reports', 'models');

it('returns zero scores when user has no reports', function () {
    $user = User::factory()->create();

    $score = $user->disciplineRiskScore();

    expect($score)->toBe([
        '7d' => 0,
        '30d' => 0,
        '90d' => 0,
        'total' => 0,
    ]);
});

it('calculates 7-day score from published reports only', function () {
    $user = User::factory()->create();

    // Published report 3 days ago (Minor = 2pts)
    DisciplineReport::factory()->forSubject($user)->minor()->publishedDaysAgo(3)->create();

    Cache::forget("user.{$user->id}.discipline_risk_score");
    $score = $user->disciplineRiskScore();

    expect($score['7d'])->toBe(2);
});

it('calculates 30-day score correctly', function () {
    $user = User::factory()->create();

    // Report 15 days ago (Major = 7pts) - in 30d but not 7d
    DisciplineReport::factory()->forSubject($user)->major()->publishedDaysAgo(15)->create();

    Cache::forget("user.{$user->id}.discipline_risk_score");
    $score = $user->disciplineRiskScore();

    expect($score['7d'])->toBe(0)
        ->and($score['30d'])->toBe(7);
});

it('calculates 90-day score correctly', function () {
    $user = User::factory()->create();

    // Report 60 days ago (Severe = 10pts) - in 90d but not 30d or 7d
    DisciplineReport::factory()->forSubject($user)->severe()->publishedDaysAgo(60)->create();

    Cache::forget("user.{$user->id}.discipline_risk_score");
    $score = $user->disciplineRiskScore();

    expect($score['7d'])->toBe(0)
        ->and($score['30d'])->toBe(0)
        ->and($score['90d'])->toBe(10);
});

it('triple counts recent reports in total (7d + 30d + 90d)', function () {
    $user = User::factory()->create();

    // Report 2 days ago (Minor = 2pts) - in all 3 windows
    DisciplineReport::factory()->forSubject($user)->minor()->publishedDaysAgo(2)->create();

    Cache::forget("user.{$user->id}.discipline_risk_score");
    $score = $user->disciplineRiskScore();

    // 2pts in 7d, 2pts in 30d, 2pts in 90d = total 6
    expect($score['7d'])->toBe(2)
        ->and($score['30d'])->toBe(2)
        ->and($score['90d'])->toBe(2)
        ->and($score['total'])->toBe(6);
});

it('excludes draft reports from risk score', function () {
    $user = User::factory()->create();

    // Draft report (not published)
    DisciplineReport::factory()->forSubject($user)->minor()->create();

    Cache::forget("user.{$user->id}.discipline_risk_score");
    $score = $user->disciplineRiskScore();

    expect($score['total'])->toBe(0);
});

it('excludes reports older than 90 days', function () {
    $user = User::factory()->create();

    // Report 100 days ago - outside all windows
    DisciplineReport::factory()->forSubject($user)->severe()->publishedDaysAgo(100)->create();

    Cache::forget("user.{$user->id}.discipline_risk_score");
    $score = $user->disciplineRiskScore();

    expect($score['total'])->toBe(0);
});

it('returns correct color for each threshold', function () {
    expect(User::riskScoreColor(0))->toBe('zinc')
        ->and(User::riskScoreColor(1))->toBe('green')
        ->and(User::riskScoreColor(10))->toBe('green')
        ->and(User::riskScoreColor(11))->toBe('yellow')
        ->and(User::riskScoreColor(25))->toBe('yellow')
        ->and(User::riskScoreColor(26))->toBe('orange')
        ->and(User::riskScoreColor(50))->toBe('orange')
        ->and(User::riskScoreColor(51))->toBe('red')
        ->and(User::riskScoreColor(100))->toBe('red');
});

it('caches risk score for 24 hours', function () {
    $user = User::factory()->create();

    // First call caches the result
    $score1 = $user->disciplineRiskScore();

    // Add a report directly to DB (bypassing cache)
    DisciplineReport::factory()->forSubject($user)->minor()->published()->create();

    // Second call should return cached (stale) value
    $score2 = $user->disciplineRiskScore();

    expect($score2['total'])->toBe($score1['total']);
});

it('clears cached risk score when clearDisciplineRiskScoreCache is called', function () {
    $user = User::factory()->create();

    // Prime the cache
    $user->disciplineRiskScore();
    expect(Cache::has("user.{$user->id}.discipline_risk_score"))->toBeTrue();

    // Clear it
    $user->clearDisciplineRiskScoreCache();
    expect(Cache::has("user.{$user->id}.discipline_risk_score"))->toBeFalse();
});
