<?php

use App\Actions\AgreeToRulesVersion;
use App\Actions\GetRulesAgreementStatus;
use App\Models\SiteConfig;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component {
    public array $checked = [];

    public function getAgreementStatus(): array
    {
        return GetRulesAgreementStatus::run(auth()->user());
    }

    public function allChecked(): bool
    {
        $status = $this->getAgreementStatus();
        $totalRules = $status['categories']->sum(fn ($cat) => $cat->rules->count());

        if ($totalRules === 0) {
            return false;
        }

        return count(array_filter($this->checked)) >= $totalRules;
    }

    public function agreeToRules(): void
    {
        $status = $this->getAgreementStatus();

        if ($status['has_agreed']) {
            return;
        }

        if (! $this->allChecked()) {
            Flux::toast('Please check all rules before submitting.', 'Not Ready', variant: 'danger');

            return;
        }

        AgreeToRulesVersion::run(auth()->user(), auth()->user());

        Flux::toast('Rules accepted successfully!', 'Success', variant: 'success');

        $this->redirect(route('dashboard'), navigate: true);
    }
}; ?>

<x-layouts.app>
    @php
        $status = $this->getAgreementStatus();
        $header = \App\Models\SiteConfig::getValue('rules_header', '');
        $footer = \App\Models\SiteConfig::getValue('rules_footer', '');
    @endphp

    <div class="max-w-3xl mx-auto py-8 px-4 space-y-8">

        @if (!$status['has_agreed'] && $status['current_version'])
            <div class="bg-amber-950/30 border border-amber-500/40 rounded-lg px-6 py-4">
                <flux:heading size="lg" class="text-amber-300">Rules Agreement Required</flux:heading>
                <flux:text class="mt-1 text-amber-200/80">
                    Please read and check each rule below, then click the agree button to continue.
                </flux:text>
            </div>
        @endif

        @if ($header)
            <div class="prose prose-invert max-w-none">
                {!! Str::markdown($header, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
            </div>
        @endif

        @foreach ($status['categories'] as $category)
            <div class="space-y-3">
                <flux:heading size="lg">{{ $category->name }}</flux:heading>

                @foreach ($category->rules as $rule)
                    <div class="bg-zinc-800/60 border border-zinc-700/50 rounded-lg p-4 space-y-2">
                        <div class="flex items-start gap-3">
                            @if (!$status['has_agreed'])
                                <div class="pt-0.5">
                                    <input
                                        type="checkbox"
                                        wire:model="checked.{{ $rule->id }}"
                                        id="rule-{{ $rule->id }}"
                                        class="w-4 h-4 rounded border-zinc-600 bg-zinc-700 text-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>
                            @endif

                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2 mb-1">
                                    <label for="rule-{{ $rule->id }}" class="font-semibold text-zinc-100 cursor-pointer">
                                        {{ $rule->title }}
                                    </label>

                                    @if ($rule->agreement_status === 'new')
                                        <flux:badge color="green" size="sm">NEW</flux:badge>
                                    @elseif ($rule->agreement_status === 'updated')
                                        <flux:badge color="amber" size="sm">UPDATED</flux:badge>
                                    @endif
                                </div>

                                <div class="prose prose-sm prose-invert max-w-none text-zinc-300">
                                    {!! Str::markdown($rule->description, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                                </div>

                                @if ($rule->agreement_status === 'updated' && $rule->previous_rule)
                                    <details class="mt-3">
                                        <summary class="text-xs text-zinc-400 cursor-pointer hover:text-zinc-200 select-none">
                                            View previous version of this rule
                                        </summary>
                                        <div class="mt-2 pl-3 border-l-2 border-zinc-600 space-y-1">
                                            <flux:text variant="subtle" class="text-xs font-medium uppercase tracking-wide">Previous</flux:text>
                                            <div class="prose prose-sm prose-invert max-w-none text-zinc-400">
                                                {!! Str::markdown($rule->previous_rule->description, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                                            </div>
                                        </div>
                                    </details>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach

        @if ($footer)
            <div class="prose prose-invert max-w-none text-zinc-400">
                {!! Str::markdown($footer, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
            </div>
        @endif

        @if (!$status['has_agreed'] && $status['current_version'])
            <div class="sticky bottom-4 bg-zinc-900/95 backdrop-blur border border-zinc-700 rounded-lg px-6 py-4 flex items-center justify-between shadow-lg">
                <flux:text variant="subtle" class="text-sm">
                    Check all rules above to enable the agree button.
                </flux:text>
                <flux:button
                    wire:click="agreeToRules"
                    variant="primary"
                    color="amber"
                    :disabled="!$this->allChecked()"
                >
                    I Have Read and Agree to All Rules
                </flux:button>
            </div>
        @elseif ($status['has_agreed'])
            <div class="text-center py-4">
                <flux:text variant="subtle">You have agreed to the current version of the rules.</flux:text>
                <div class="mt-3">
                    <flux:button href="{{ route('dashboard') }}" variant="ghost">Back to Dashboard</flux:button>
                </div>
            </div>
        @endif

    </div>
</x-layouts.app>
