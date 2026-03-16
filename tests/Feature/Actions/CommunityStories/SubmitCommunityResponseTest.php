<?php

declare(strict_types=1);

use App\Actions\SubmitCommunityResponse;
use App\Enums\CommunityQuestionStatus;
use App\Enums\CommunityResponseStatus;
use App\Models\CommunityQuestion;
use App\Models\CommunityResponse;

uses()->group('community-stories', 'actions');

it('allows a traveler to submit a response to the active question', function () {
    $user = loginAs(membershipTraveler());
    $question = CommunityQuestion::factory()->active()->create();

    $response = SubmitCommunityResponse::run($question, $user, 'This is my amazing story about the community and all the great things.');

    expect($response->fresh()->status)->toBe(CommunityResponseStatus::Submitted)
        ->and($response->user_id)->toBe($user->id)
        ->and($response->community_question_id)->toBe($question->id);
});

it('prevents duplicate responses to the same question', function () {
    $user = loginAs(membershipTraveler());
    $question = CommunityQuestion::factory()->active()->create();

    SubmitCommunityResponse::run($question, $user, 'First response to this wonderful community question.');

    SubmitCommunityResponse::run($question, $user, 'Second response attempt that should fail here.');
})->throws(\RuntimeException::class, 'You have already responded to this question.');

it('prevents a drifter from submitting a response', function () {
    $user = loginAs(membershipDrifter());
    $question = CommunityQuestion::factory()->active()->create();

    SubmitCommunityResponse::run($question, $user, 'This should fail because I am a drifter member.');
})->throws(\RuntimeException::class, 'You must be at least a Traveler to respond.');

it('prevents a stowaway from submitting a response', function () {
    $user = loginAs(membershipStowaway());
    $question = CommunityQuestion::factory()->active()->create();

    SubmitCommunityResponse::run($question, $user, 'This should fail because I am a stowaway member.');
})->throws(\RuntimeException::class, 'You must be at least a Traveler to respond.');

it('prevents response to a draft question', function () {
    $user = loginAs(membershipTraveler());
    $question = CommunityQuestion::factory()->create(['status' => CommunityQuestionStatus::Draft]);

    SubmitCommunityResponse::run($question, $user, 'This should fail because the question is a draft.');
})->throws(\RuntimeException::class, 'This question is not accepting responses.');

it('prevents response to a draft question with scheduled dates', function () {
    $user = loginAs(membershipTraveler());
    $question = CommunityQuestion::factory()->withSchedule()->create();

    SubmitCommunityResponse::run($question, $user, 'This should fail because the question is a draft with schedule.');
})->throws(\RuntimeException::class, 'This question is not accepting responses.');

it('allows a resident to respond to one archived question after answering the active question', function () {
    $user = loginAs(membershipResident());
    $activeQuestion = CommunityQuestion::factory()->active()->create();
    $archivedQuestion = CommunityQuestion::factory()->archived()->create();

    // Answer active question first
    SubmitCommunityResponse::run($activeQuestion, $user, 'My response to the current active community question.');

    // Now answer one archived question
    $response = SubmitCommunityResponse::run($archivedQuestion, $user, 'My response to a past question from the community archive.');

    expect($response->fresh()->status)->toBe(CommunityResponseStatus::Submitted)
        ->and($response->community_question_id)->toBe($archivedQuestion->id);
});

it('prevents a traveler from responding to an archived question', function () {
    $user = loginAs(membershipTraveler());
    $activeQuestion = CommunityQuestion::factory()->active()->create();
    $archivedQuestion = CommunityQuestion::factory()->archived()->create();

    SubmitCommunityResponse::run($activeQuestion, $user, 'My response to the current active community question.');

    SubmitCommunityResponse::run($archivedQuestion, $user, 'This should fail because travelers cannot answer archived.');
})->throws(\RuntimeException::class, 'You must be at least a Resident to respond to past questions.');

it('prevents responding to a second archived question in the same cycle', function () {
    $user = loginAs(membershipResident());
    $activeQuestion = CommunityQuestion::factory()->active()->create();
    $archivedQuestion1 = CommunityQuestion::factory()->archived()->create();
    $archivedQuestion2 = CommunityQuestion::factory()->archived()->create();

    SubmitCommunityResponse::run($activeQuestion, $user, 'My response to the current active community question here.');
    SubmitCommunityResponse::run($archivedQuestion1, $user, 'My response to the first archived community question.');

    SubmitCommunityResponse::run($archivedQuestion2, $user, 'This should fail because only one archived allowed.');
})->throws(\RuntimeException::class, 'You may only respond to one past question per cycle.');

it('requires answering the active question before an archived question', function () {
    $user = loginAs(membershipResident());
    CommunityQuestion::factory()->active()->create();
    $archivedQuestion = CommunityQuestion::factory()->archived()->create();

    SubmitCommunityResponse::run($archivedQuestion, $user, 'This should fail because active question not answered.');
})->throws(\RuntimeException::class, 'You must answer the current question before responding to a past question.');

it('records activity when response is submitted', function () {
    $user = loginAs(membershipTraveler());
    $question = CommunityQuestion::factory()->active()->create();

    $response = SubmitCommunityResponse::run($question, $user, 'My amazing story about the Lighthouse community experience.');

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => CommunityResponse::class,
        'subject_id' => $response->id,
        'action' => 'community_response_submitted',
    ]);
});
