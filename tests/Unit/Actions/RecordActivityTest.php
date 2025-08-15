<?php

namespace Tests\Unit\Actions;

use App\Actions\RecordActivity;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

describe('RecordActivity Action', function () {
    it('records activity with authenticated user as causer', function () {
        $causer = User::factory()->create();
        $subject = User::factory()->create();

        Auth::login($causer);

        // Mock the request with proper IP and user agent
        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.1',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 Test Browser',
        ]);
        app()->instance('request', $request);

        RecordActivity::handle($subject, 'test_action', 'Test description');

        expect(ActivityLog::count())->toBe(1);

        $activityLog = ActivityLog::first();
        expect($activityLog->causer_id)->toBe($causer->id);
        expect($activityLog->subject_type)->toBe(User::class);
        expect($activityLog->subject_id)->toBe($subject->id);
        expect($activityLog->action)->toBe('test_action');
        expect($activityLog->description)->toBe('Test description');
        expect($activityLog->meta)->toBeArray();
        expect($activityLog->meta['ip'])->toBe('192.168.1.1');
        expect($activityLog->meta['user_agent'])->toBe('Mozilla/5.0 Test Browser');
    });

    it('records activity with null causer when not authenticated', function () {
        $subject = User::factory()->create();

        // Ensure no user is authenticated
        Auth::logout();

        // Mock the request with proper IP and user agent
        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_USER_AGENT' => 'Test Agent',
        ]);
        app()->instance('request', $request);

        RecordActivity::handle($subject, 'anonymous_action', 'Anonymous action');

        expect(ActivityLog::count())->toBe(1);

        $activityLog = ActivityLog::first();
        expect($activityLog->causer_id)->toBeNull();
        expect($activityLog->subject_type)->toBe(User::class);
        expect($activityLog->subject_id)->toBe($subject->id);
        expect($activityLog->action)->toBe('anonymous_action');
        expect($activityLog->description)->toBe('Anonymous action');
        expect($activityLog->meta)->toBeArray();
        expect($activityLog->meta['ip'])->toBe('10.0.0.1');
        expect($activityLog->meta['user_agent'])->toBe('Test Agent');
    });

    it('records activity with null description when not provided', function () {
        $subject = User::factory()->create();

        RecordActivity::handle($subject, 'no_description_action');

        expect(ActivityLog::count())->toBe(1);

        $activityLog = ActivityLog::first();
        expect($activityLog->action)->toBe('no_description_action');
        expect($activityLog->description)->toBeNull();
    });

    it('captures correct subject class for different model types', function () {
        $user = User::factory()->create();

        RecordActivity::handle($user, 'user_action', 'User related action');

        expect(ActivityLog::count())->toBe(1);

        $activityLog = ActivityLog::first();
        expect($activityLog->subject_type)->toBe(User::class);
        expect($activityLog->subject_id)->toBe($user->getKey());
    });

    it('stores meta information correctly', function () {
        $subject = User::factory()->create();

        // Mock specific request data
        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '203.0.113.1',
            'HTTP_USER_AGENT' => 'Custom/1.0 (Test Suite)',
        ]);
        app()->instance('request', $request);

        RecordActivity::handle($subject, 'meta_test', 'Testing meta storage');

        $activityLog = ActivityLog::first();
        expect($activityLog->meta)->toHaveKey('ip');
        expect($activityLog->meta)->toHaveKey('user_agent');
        expect($activityLog->meta['ip'])->toBe('203.0.113.1');
        expect($activityLog->meta['user_agent'])->toBe('Custom/1.0 (Test Suite)');
    });

    it('handles empty or null user agent gracefully', function () {
        $subject = User::factory()->create();

        // Mock request with null user agent
        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '203.0.113.1',
            'HTTP_USER_AGENT' => null, // Simulating a null user agent
        ]);
        app()->instance('request', $request);

        RecordActivity::handle($subject, 'null_agent_test');

        $activityLog = ActivityLog::first();
        expect($activityLog->meta['user_agent'])->toBeNull();
    });

    it('creates multiple activity logs for multiple actions', function () {
        $subject1 = User::factory()->create();
        $subject2 = User::factory()->create();

        RecordActivity::handle($subject1, 'first_action', 'First action');
        RecordActivity::handle($subject2, 'second_action', 'Second action');

        expect(ActivityLog::count())->toBe(2);

        $logs = ActivityLog::all();
        expect($logs->pluck('action'))->toContain('first_action');
        expect($logs->pluck('action'))->toContain('second_action');
        expect($logs->pluck('subject_id'))->toContain($subject1->id);
        expect($logs->pluck('subject_id'))->toContain($subject2->id);
    });

    it('maintains activity log relationships correctly', function () {
        $causer = User::factory()->create();
        $subject = User::factory()->create();

        Auth::login($causer);

        RecordActivity::handle($subject, 'relationship_test', 'Testing relationships');

        $activityLog = ActivityLog::first();

        // Test causer relationship
        expect($activityLog->causer)->not->toBeNull();
        expect($activityLog->causer->id)->toBe($causer->id);

        // Test subject relationship
        expect($activityLog->subject)->not->toBeNull();
        expect($activityLog->subject->id)->toBe($subject->id);
    });

    it('works with different authenticated users', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $subject = User::factory()->create();

        // First action with user1
        Auth::login($user1);
        RecordActivity::handle($subject, 'user1_action', 'Action by user 1');

        // Second action with user2
        Auth::login($user2);
        RecordActivity::handle($subject, 'user2_action', 'Action by user 2');

        expect(ActivityLog::count())->toBe(2);

        $logs = ActivityLog::orderBy('id')->get();
        expect($logs[0]->causer_id)->toBe($user1->id);
        expect($logs[1]->causer_id)->toBe($user2->id);
        expect($logs[0]->action)->toBe('user1_action');
        expect($logs[1]->action)->toBe('user2_action');
    });

    it('stores correct timestamps when activities are created', function () {
        $subject = User::factory()->create();

        $beforeTime = now()->subSecond(); // Give a bit more buffer
        RecordActivity::handle($subject, 'timestamp_test', 'Testing timestamps');
        $afterTime = now()->addSecond(); // Give a bit more buffer

        $activityLog = ActivityLog::first();
        expect($activityLog->created_at->timestamp)->toBeGreaterThanOrEqual($beforeTime->timestamp);
        expect($activityLog->created_at->timestamp)->toBeLessThanOrEqual($afterTime->timestamp);
        expect($activityLog->updated_at->timestamp)->toBeGreaterThanOrEqual($beforeTime->timestamp);
        expect($activityLog->updated_at->timestamp)->toBeLessThanOrEqual($afterTime->timestamp);
    });

    it('handles special characters in action and description', function () {
        $subject = User::factory()->create();

        $specialAction = 'special_action_with_unicode_éñ中文';
        $specialDescription = 'Description with special chars: @#$%^&*()_+ éñ中文';

        RecordActivity::handle($subject, $specialAction, $specialDescription);

        $activityLog = ActivityLog::first();
        expect($activityLog->action)->toBe($specialAction);
        expect($activityLog->description)->toBe($specialDescription);
    });

    it('works when called statically', function () {
        $subject = User::factory()->create();

        // Test static call (which is how it's used in the PromoteUser action)
        RecordActivity::handle($subject, 'static_call_test', 'Testing static call');

        expect(ActivityLog::count())->toBe(1);

        $activityLog = ActivityLog::first();
        expect($activityLog->action)->toBe('static_call_test');
        expect($activityLog->description)->toBe('Testing static call');
    });

    it('preserves all required fields for activity tracking', function () {
        $causer = User::factory()->create();
        $subject = User::factory()->create();

        Auth::login($causer);

        RecordActivity::handle($subject, 'complete_test', 'Complete activity test');

        $activityLog = ActivityLog::first();

        // Verify all required fields are present
        expect($activityLog->causer_id)->not->toBeNull();
        expect($activityLog->subject_type)->not->toBeNull();
        expect($activityLog->subject_id)->not->toBeNull();
        expect($activityLog->action)->not->toBeNull();
        expect($activityLog->description)->not->toBeNull();
        expect($activityLog->meta)->not->toBeNull();
        expect($activityLog->created_at)->not->toBeNull();
        expect($activityLog->updated_at)->not->toBeNull();
    });
});
