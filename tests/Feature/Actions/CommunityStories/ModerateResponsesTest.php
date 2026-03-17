<?php

declare(strict_types=1);

use App\Actions\ModerateResponses;
use App\Enums\CommunityResponseStatus;
use App\Models\CommunityQuestion;
use App\Models\CommunityResponse;

uses()->group('community-stories', 'actions');

it('approves a single response', function () {
    $staff = loginAsAdmin();
    $response = CommunityResponse::factory()->create();

    $count = ModerateResponses::run(collect([$response]), $staff, CommunityResponseStatus::Approved);

    expect($count)->toBe(1)
        ->and($response->fresh()->status)->toBe(CommunityResponseStatus::Approved)
        ->and($response->fresh()->approved_at)->not->toBeNull();
});

it('bulk approves multiple responses', function () {
    $staff = loginAsAdmin();
    $question = CommunityQuestion::factory()->active()->create();
    $responses = CommunityResponse::factory()->count(3)->create(['community_question_id' => $question->id]);

    $count = ModerateResponses::run($responses, $staff, CommunityResponseStatus::Approved);

    expect($count)->toBe(3);
    foreach ($responses as $response) {
        expect($response->fresh()->status)->toBe(CommunityResponseStatus::Approved);
    }
});

it('sets reviewed_by and reviewed_at on approval', function () {
    $staff = loginAsAdmin();
    $response = CommunityResponse::factory()->create();

    ModerateResponses::run(collect([$response]), $staff, CommunityResponseStatus::Approved);

    expect($response->fresh()->reviewed_by)->toBe($staff->id)
        ->and($response->fresh()->reviewed_at)->not->toBeNull();
});

it('sets approved_at on approval', function () {
    $staff = loginAsAdmin();
    $response = CommunityResponse::factory()->create();

    ModerateResponses::run(collect([$response]), $staff, CommunityResponseStatus::Approved);

    expect($response->fresh()->approved_at)->not->toBeNull();
});

it('rejects a single response', function () {
    $staff = loginAsAdmin();
    $response = CommunityResponse::factory()->create();

    $count = ModerateResponses::run(collect([$response]), $staff, CommunityResponseStatus::Rejected);

    expect($count)->toBe(1)
        ->and($response->fresh()->status)->toBe(CommunityResponseStatus::Rejected)
        ->and($response->fresh()->approved_at)->toBeNull();
});

it('bulk rejects multiple responses', function () {
    $staff = loginAsAdmin();
    $question = CommunityQuestion::factory()->active()->create();
    $responses = CommunityResponse::factory()->count(3)->create(['community_question_id' => $question->id]);

    $count = ModerateResponses::run($responses, $staff, CommunityResponseStatus::Rejected);

    expect($count)->toBe(3);
    foreach ($responses as $response) {
        expect($response->fresh()->status)->toBe(CommunityResponseStatus::Rejected);
    }
});

it('records activity for each moderated response', function () {
    $staff = loginAsAdmin();
    $question = CommunityQuestion::factory()->active()->create();
    $responses = CommunityResponse::factory()->count(2)->create(['community_question_id' => $question->id]);

    ModerateResponses::run($responses, $staff, CommunityResponseStatus::Approved);

    foreach ($responses as $response) {
        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => CommunityResponse::class,
            'subject_id' => $response->id,
            'action' => 'community_response_approved',
        ]);
    }
});

it('skips already approved responses', function () {
    $staff = loginAsAdmin();
    $response = CommunityResponse::factory()->approved()->create();

    $count = ModerateResponses::run(collect([$response]), $staff, CommunityResponseStatus::Approved);

    expect($count)->toBe(0);
});
