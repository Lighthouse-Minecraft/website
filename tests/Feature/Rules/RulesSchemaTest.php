<?php

declare(strict_types=1);

use App\Enums\BrigType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses()->group('rules', 'schema');

// == Table existence and structure ==

it('rule_categories table has correct columns', function () {
    expect(Schema::hasTable('rule_categories'))->toBeTrue();
    expect(Schema::hasColumns('rule_categories', ['id', 'name', 'sort_order', 'created_at', 'updated_at']))->toBeTrue();
});

it('rules table has correct columns', function () {
    expect(Schema::hasTable('rules'))->toBeTrue();
    expect(Schema::hasColumns('rules', [
        'id', 'rule_category_id', 'title', 'description', 'status',
        'supersedes_rule_id', 'created_by_user_id', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('rule_versions table has correct columns', function () {
    expect(Schema::hasTable('rule_versions'))->toBeTrue();
    expect(Schema::hasColumns('rule_versions', [
        'id', 'version_number', 'status', 'created_by_user_id', 'approved_by_user_id',
        'rejection_note', 'published_at', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('rule_version_rules pivot table exists', function () {
    expect(Schema::hasTable('rule_version_rules'))->toBeTrue();
    expect(Schema::hasColumns('rule_version_rules', ['rule_version_id', 'rule_id']))->toBeTrue();
});

it('user_rule_agreements table has correct columns', function () {
    expect(Schema::hasTable('user_rule_agreements'))->toBeTrue();
    expect(Schema::hasColumns('user_rule_agreements', [
        'id', 'user_id', 'rule_version_id', 'agreed_at', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('discipline_report_rules pivot table exists', function () {
    expect(Schema::hasTable('discipline_report_rules'))->toBeTrue();
    expect(Schema::hasColumns('discipline_report_rules', ['discipline_report_id', 'rule_id']))->toBeTrue();
});

it('users table has rules_reminder_sent_at column', function () {
    expect(Schema::hasColumn('users', 'rules_reminder_sent_at'))->toBeTrue();
});

// == BrigType enum ==

it('BrigType has rules_non_compliance value', function () {
    expect(BrigType::RulesNonCompliance->value)->toBe('rules_non_compliance');
});

it('BrigType rules_non_compliance has correct label', function () {
    expect(BrigType::RulesNonCompliance->label())->toBe('Rules Non-Compliance');
});

// == Seeded data ==

it('seeder produces 10 rule categories', function () {
    expect(DB::table('rule_categories')->count())->toBe(10);
});

it('seeder produces 26 rules', function () {
    expect(DB::table('rules')->count())->toBe(26);
});

it('seeder produces version 1 as published', function () {
    $version = DB::table('rule_versions')->where('version_number', 1)->first();

    expect($version)->not->toBeNull()
        ->and($version->status)->toBe('published')
        ->and($version->published_at)->not->toBeNull();
});

it('version 1 contains all seeded rules', function () {
    $version = DB::table('rule_versions')->where('version_number', 1)->first();
    $ruleCount = DB::table('rules')->count();
    $linkedCount = DB::table('rule_version_rules')->where('rule_version_id', $version->id)->count();

    expect($linkedCount)->toBe($ruleCount);
});

it('seeder produces rules_header site config', function () {
    $config = DB::table('site_configs')->where('key', 'rules_header')->first();

    expect($config)->not->toBeNull()
        ->and($config->value)->not->toBeEmpty();
});

it('seeder produces rules_footer site config', function () {
    $config = DB::table('site_configs')->where('key', 'rules_footer')->first();

    expect($config)->not->toBeNull()
        ->and($config->value)->not->toBeEmpty();
});

it('all seeded rules have active status', function () {
    $inactiveOrDraft = DB::table('rules')
        ->whereIn('status', ['draft', 'inactive'])
        ->count();

    expect($inactiveOrDraft)->toBe(0);
});

// == Legacy agreement migration ==

it('users with rules_accepted_at get a user_rule_agreements record for version 1', function () {
    $user = User::factory()->create([
        'rules_accepted_at' => now()->subDays(10),
    ]);

    // Simulate what the seeder does for a new user created before the migration
    $version = DB::table('rule_versions')->where('version_number', 1)->first();

    DB::table('user_rule_agreements')->insertOrIgnore([
        'user_id' => $user->id,
        'rule_version_id' => $version->id,
        'agreed_at' => $user->rules_accepted_at,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $agreement = DB::table('user_rule_agreements')
        ->where('user_id', $user->id)
        ->where('rule_version_id', $version->id)
        ->first();

    expect($agreement)->not->toBeNull();
});

it('users without rules_accepted_at do not get an agreement record from seeder logic', function () {
    $user = User::factory()->create([
        'rules_accepted_at' => null,
    ]);

    $version = DB::table('rule_versions')->where('version_number', 1)->first();

    $agreement = DB::table('user_rule_agreements')
        ->where('user_id', $user->id)
        ->where('rule_version_id', $version->id)
        ->first();

    expect($agreement)->toBeNull();
});
