<?php

namespace App\Actions;

use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\Facades\Prism;

class FormatMeetingNotesWithAi
{
    use AsAction;

    /**
     * Format raw meeting notes using an LLM via PrismPHP.
     *
     * @param  string  $notes  Raw compiled meeting notes
     * @param  string|null  $systemPrompt  Override the default system prompt
     * @return array{success: bool, text: string, error: ?string}
     */
    public function handle(string $notes, ?string $systemPrompt = null): array
    {
        $provider = config('lighthouse.ai.meeting_notes_provider', 'openai');
        $model = config('lighthouse.ai.meeting_notes_model', 'gpt-4o');
        $defaultPrompt = config('lighthouse.ai.meeting_notes_system_prompt', '');

        $apiKey = config("prism.providers.{$provider}.api_key", '');

        if (empty($apiKey)) {
            return [
                'success' => false,
                'text' => $notes,
                'error' => 'AI provider API key is not configured. Using raw notes.',
            ];
        }

        if (empty(trim($notes))) {
            return [
                'success' => false,
                'text' => $notes,
                'error' => 'No notes to format.',
            ];
        }

        try {
            $response = Prism::text()
                ->using($provider, $model)
                ->withSystemPrompt($systemPrompt ?? $defaultPrompt)
                ->withPrompt($notes)
                ->asText();

            $formattedText = $response->text;

            if (empty(trim($formattedText))) {
                Log::warning('AI returned empty response for meeting notes formatting', [
                    'provider' => $provider,
                    'model' => $model,
                ]);

                return [
                    'success' => false,
                    'text' => $notes,
                    'error' => 'AI returned an empty response. Using raw notes.',
                ];
            }

            Log::info('Meeting notes formatted with AI', [
                'provider' => $provider,
                'model' => $model,
                'prompt_tokens' => $response->usage->promptTokens,
                'completion_tokens' => $response->usage->completionTokens,
            ]);

            return [
                'success' => true,
                'text' => $formattedText,
                'error' => null,
            ];
        } catch (PrismException $e) {
            Log::error('AI meeting notes formatting failed', [
                'provider' => $provider,
                'model' => $model,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'text' => $notes,
                'error' => 'AI formatting failed.',
            ];
        } catch (\Throwable $e) {
            Log::error('Unexpected error during AI meeting notes formatting', [
                'provider' => $provider,
                'model' => $model,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'text' => $notes,
                'error' => 'An unexpected error occurred. Using raw notes.',
            ];
        }
    }
}
