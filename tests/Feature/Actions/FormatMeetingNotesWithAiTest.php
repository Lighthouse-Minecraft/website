<?php

declare(strict_types=1);

use App\Actions\FormatMeetingNotesWithAi;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\ValueObjects\Usage;

it('returns raw notes when no API key is configured', function () {
    config()->set('prism.providers.openai.api_key', '');

    $notes = "## General Notes\nSome meeting content";
    $result = FormatMeetingNotesWithAi::run($notes);

    expect($result['success'])->toBeFalse();
    expect($result['text'])->toBe($notes);
    expect($result['error'])->toContain('API key is not configured');
});

it('returns formatted text on successful AI call', function () {
    config()->set('prism.providers.openai.api_key', 'test-key');

    $formattedOutput = "# Meeting Summary\n\nHere are the key updates from our staff meeting.";

    Prism::fake([
        TextResponseFake::make()
            ->withText($formattedOutput)
            ->withUsage(new Usage(100, 50)),
    ]);

    $notes = "## General Notes\nRaw meeting content here";
    $result = FormatMeetingNotesWithAi::run($notes);

    expect($result['success'])->toBeTrue();
    expect($result['text'])->toBe($formattedOutput);
    expect($result['error'])->toBeNull();
});

it('falls back to raw notes on empty AI response', function () {
    config()->set('prism.providers.openai.api_key', 'test-key');

    Prism::fake([
        TextResponseFake::make()->withText(''),
    ]);

    $notes = "## General Notes\nSome content";
    $result = FormatMeetingNotesWithAi::run($notes);

    expect($result['success'])->toBeFalse();
    expect($result['text'])->toBe($notes);
    expect($result['error'])->toContain('empty response');
});

it('falls back to raw notes on empty input', function () {
    config()->set('prism.providers.openai.api_key', 'test-key');

    $result = FormatMeetingNotesWithAi::run('   ');

    expect($result['success'])->toBeFalse();
    expect($result['text'])->toBe('   ');
    expect($result['error'])->toContain('No notes to format');
});

it('uses custom system prompt when provided', function () {
    config()->set('prism.providers.openai.api_key', 'test-key');

    $customPrompt = 'Format these notes in bullet points only.';
    $formattedOutput = "- Point 1\n- Point 2";

    $fake = Prism::fake([
        TextResponseFake::make()
            ->withText($formattedOutput)
            ->withUsage(new Usage(80, 30)),
    ]);

    $notes = "## General Notes\nSome content";
    $result = FormatMeetingNotesWithAi::run($notes, $customPrompt);

    expect($result['success'])->toBeTrue();
    expect($result['text'])->toBe($formattedOutput);

    $fake->assertPrompt($notes);
});

it('reads provider and model from config', function () {
    config()->set('prism.providers.anthropic.api_key', 'test-anthropic-key');
    config()->set('lighthouse.ai.meeting_notes_provider', 'anthropic');
    config()->set('lighthouse.ai.meeting_notes_model', 'claude-3-haiku');

    Prism::fake([
        TextResponseFake::make()
            ->withText('Formatted notes')
            ->withUsage(new Usage(50, 25)),
    ]);

    $result = FormatMeetingNotesWithAi::run('Some notes');

    expect($result['success'])->toBeTrue();
});
