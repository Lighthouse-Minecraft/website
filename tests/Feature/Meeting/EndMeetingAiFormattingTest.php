<?php

declare(strict_types=1);

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\MeetingNote;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\ValueObjects\Usage;

use function Pest\Livewire\livewire;

describe('EndMeetingConfirmed', function () {
    it('creates community note with raw compiled notes', function () {
        $meeting = Meeting::factory()->withStatus(MeetingStatus::InProgress)->create();
        $generalContent = 'Raw general notes content';
        MeetingNote::factory()->create([
            'content' => $generalContent,
            'section_key' => 'general',
            'meeting_id' => $meeting->id,
        ]);

        loginAsAdmin();

        livewire('meetings.manage-meeting', ['meeting' => $meeting])
            ->call('EndMeetingConfirmed')
            ->assertSuccessful();

        $communityNote = MeetingNote::where('meeting_id', $meeting->id)
            ->where('section_key', 'community')
            ->first();

        expect($communityNote)->not->toBeNull();
        expect($communityNote->content)->toContain($generalContent);
    });

    it('transitions meeting to finalizing without blocking on AI', function () {
        $meeting = Meeting::factory()->withStatus(MeetingStatus::InProgress)->create();
        MeetingNote::factory()->create([
            'content' => 'Some notes',
            'section_key' => 'general',
            'meeting_id' => $meeting->id,
        ]);

        loginAsAdmin();

        livewire('meetings.manage-meeting', ['meeting' => $meeting])
            ->call('EndMeetingConfirmed')
            ->assertSuccessful();

        $meeting->refresh();
        expect($meeting->status)->toBe(MeetingStatus::Finalizing);
    });
});

describe('processAiFormatting', function () {
    it('updates community note with AI-formatted content', function () {
        config()->set('prism.providers.openai.api_key', 'test-key');

        $formattedOutput = "# Community Summary\n\nKey updates from this meeting.";

        Prism::fake([
            TextResponseFake::make()
                ->withText($formattedOutput)
                ->withUsage(new Usage(100, 50)),
        ]);

        $meeting = Meeting::factory()->withStatus(MeetingStatus::Finalizing)
            ->withMinutes('Raw compiled minutes')
            ->create();

        $communityNote = MeetingNote::factory()->create([
            'content' => 'Raw compiled minutes',
            'section_key' => 'community',
            'meeting_id' => $meeting->id,
        ]);

        loginAsAdmin();

        livewire('meetings.manage-meeting', ['meeting' => $meeting])
            ->call('processAiFormatting')
            ->assertSuccessful();

        $communityNote->refresh();
        expect($communityNote->content)->toBe($formattedOutput);
        expect($communityNote->locked_by)->toBeNull();
    });

    it('keeps raw notes when AI is not configured', function () {
        config()->set('prism.providers.openai.api_key', '');

        $rawContent = 'Raw compiled minutes';
        $meeting = Meeting::factory()->withStatus(MeetingStatus::Finalizing)
            ->withMinutes($rawContent)
            ->create();

        $communityNote = MeetingNote::factory()->create([
            'content' => $rawContent,
            'section_key' => 'community',
            'meeting_id' => $meeting->id,
        ]);

        loginAsAdmin();

        livewire('meetings.manage-meeting', ['meeting' => $meeting])
            ->call('processAiFormatting')
            ->assertSuccessful();

        $communityNote->refresh();
        expect($communityNote->content)->toBe($rawContent);
    });

    it('requires update authorization', function () {
        $meeting = Meeting::factory()->withStatus(MeetingStatus::Finalizing)
            ->withMinutes('Some minutes')
            ->create();

        MeetingNote::factory()->create([
            'content' => 'Community content',
            'section_key' => 'community',
            'meeting_id' => $meeting->id,
        ]);

        $user = \App\Models\User::factory()->create();
        loginAs($user);

        livewire('meetings.manage-meeting', ['meeting' => $meeting])
            ->call('processAiFormatting')
            ->assertForbidden();
    });
});

describe('Reformat with AI', function () {
    it('shows the reformat with AI button during finalizing', function () {
        loginAsAdmin();
        $meeting = Meeting::factory()->withStatus(MeetingStatus::Finalizing)->create();

        livewire('meetings.manage-meeting', ['meeting' => $meeting])
            ->assertSee('Reformat with AI');
    });

    it('does not show the reformat button when meeting is completed', function () {
        loginAsAdmin();
        $meeting = Meeting::factory()->withStatus(MeetingStatus::Completed)->create();

        livewire('meetings.manage-meeting', ['meeting' => $meeting])
            ->assertDontSee('Reformat with AI');
    });

    it('updates community note content when reformatting', function () {
        config()->set('prism.providers.openai.api_key', 'test-key');

        $reformattedOutput = "# Reformatted Summary\n\nCleaner version of notes.";

        Prism::fake([
            TextResponseFake::make()
                ->withText($reformattedOutput)
                ->withUsage(new Usage(100, 50)),
        ]);

        $meeting = Meeting::factory()->withStatus(MeetingStatus::Finalizing)
            ->withMinutes('Raw compiled minutes')
            ->create();

        $communityNote = MeetingNote::factory()->create([
            'content' => 'Old community content',
            'section_key' => 'community',
            'meeting_id' => $meeting->id,
            'locked_by' => 1,
            'locked_at' => now(),
        ]);

        loginAsAdmin();

        livewire('meetings.manage-meeting', ['meeting' => $meeting])
            ->call('reformatWithAi')
            ->assertSuccessful();

        $communityNote->refresh();
        expect($communityNote->content)->toBe($reformattedOutput);
        expect($communityNote->locked_by)->toBeNull();
    });

    it('shows error when no community note exists', function () {
        $meeting = Meeting::factory()->withStatus(MeetingStatus::Finalizing)->create();

        loginAsAdmin();

        livewire('meetings.manage-meeting', ['meeting' => $meeting])
            ->call('reformatWithAi')
            ->assertSuccessful();
    });

    it('shows error when no meeting minutes are available', function () {
        $meeting = Meeting::factory()->withStatus(MeetingStatus::Finalizing)->create([
            'minutes' => '',
        ]);

        MeetingNote::factory()->create([
            'content' => 'Some community content',
            'section_key' => 'community',
            'meeting_id' => $meeting->id,
        ]);

        loginAsAdmin();

        livewire('meetings.manage-meeting', ['meeting' => $meeting])
            ->call('reformatWithAi')
            ->assertSuccessful();
    });

    it('uses custom prompt from the AI prompt editor', function () {
        config()->set('prism.providers.openai.api_key', 'test-key');

        $customOutput = "- Bullet 1\n- Bullet 2";

        $fake = Prism::fake([
            TextResponseFake::make()
                ->withText($customOutput)
                ->withUsage(new Usage(80, 30)),
        ]);

        $meeting = Meeting::factory()->withStatus(MeetingStatus::Finalizing)
            ->withMinutes('Raw minutes content')
            ->create();

        MeetingNote::factory()->create([
            'content' => 'Old content',
            'section_key' => 'community',
            'meeting_id' => $meeting->id,
        ]);

        loginAsAdmin();

        livewire('meetings.manage-meeting', ['meeting' => $meeting])
            ->set('aiPrompt', 'Format as bullet points only')
            ->call('reformatWithAi')
            ->assertSuccessful();

        $communityNote = MeetingNote::where('meeting_id', $meeting->id)
            ->where('section_key', 'community')
            ->first();

        expect($communityNote->content)->toBe($customOutput);
    });

    it('requires update authorization for reformatting', function () {
        $meeting = Meeting::factory()->withStatus(MeetingStatus::Finalizing)->create();

        MeetingNote::factory()->create([
            'content' => 'Community content',
            'section_key' => 'community',
            'meeting_id' => $meeting->id,
        ]);

        // Login as a non-staff user
        $user = \App\Models\User::factory()->create();
        loginAs($user);

        livewire('meetings.manage-meeting', ['meeting' => $meeting])
            ->call('reformatWithAi')
            ->assertForbidden();
    });
});
