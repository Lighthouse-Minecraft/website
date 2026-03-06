<?php

use App\Actions\GetIterationBoundaries;
use App\Enums\MinecraftAccountStatus;
use App\Models\DiscordAccount;
use App\Models\MinecraftAccount;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component {
    #[Locked]
    public string $activeDetailMetric = '';

    public function getMetricsProperty(): array
    {
        $boundaries = GetIterationBoundaries::run();

        $cs = $boundaries['current_start'];
        $ce = $boundaries['current_end'];
        $ps = $boundaries['previous_start'];
        $pe = $boundaries['previous_end'];
        $hasPrevious = $boundaries['has_previous'];

        $currentMetrics = Cache::flexible('command_dashboard.community.current', [3600, 43200], function () use ($cs, $ce) {
            return [
                'new_users' => User::whereBetween('created_at', [$cs, $ce])->count(),
                'new_mc_accounts' => MinecraftAccount::whereBetween('created_at', [$cs, $ce])->count(),
                'new_discord_accounts' => DiscordAccount::whereBetween('created_at', [$cs, $ce])->count(),
                'active_users' => User::whereBetween('last_login_at', [$cs, $ce])->count(),
                'pending_mc_verification' => MinecraftAccount::where('status', MinecraftAccountStatus::Verifying)->count(),
            ];
        });

        $previousMetrics = null;
        if ($hasPrevious) {
            $cacheKey = 'command_dashboard.community.previous.' . $ps->timestamp . '.' . $pe->timestamp;
            $previousMetrics = Cache::remember($cacheKey, now()->addHours(24), function () use ($ps, $pe) {
                return [
                    'new_users' => User::whereBetween('created_at', [$ps, $pe])->count(),
                    'new_mc_accounts' => MinecraftAccount::whereBetween('created_at', [$ps, $pe])->count(),
                    'new_discord_accounts' => DiscordAccount::whereBetween('created_at', [$ps, $pe])->count(),
                    'active_users' => User::whereBetween('last_login_at', [$ps, $pe])->count(),
                ];
            });
        }

        return [
            'current' => $currentMetrics,
            'previous' => $previousMetrics,
            'has_previous' => $hasPrevious,
            'current_start' => $cs,
            'current_end' => $ce,
        ];
    }

    public function getTimelineDataProperty(): array
    {
        if (! $this->activeDetailMetric) {
            return [];
        }

        $boundaries = GetIterationBoundaries::run();
        $iterations = $boundaries['iterations_3mo'];
        $data = [];

        foreach ($iterations as $iter) {
            $start = $iter['start'];
            $end = $iter['end'];

            $count = match ($this->activeDetailMetric) {
                'new_users' => User::whereBetween('created_at', [$start, $end])->count(),
                'new_mc_accounts' => MinecraftAccount::whereBetween('created_at', [$start, $end])->count(),
                'new_discord_accounts' => DiscordAccount::whereBetween('created_at', [$start, $end])->count(),
                'active_users' => User::whereBetween('last_login_at', [$start, $end])->count(),
                default => 0,
            };

            $data[] = [
                'label' => $start->format('M j') . ' - ' . $end->format('M j'),
                'count' => $count,
            ];
        }

        return $data;
    }

    public function showDetail(string $metric): void
    {
        $this->authorize('view-command-dashboard');
        $this->activeDetailMetric = $metric;
        Flux::modal('community-detail-modal')->show();
    }
}; ?>

<div>
<flux:card class="w-full">
    <flux:heading size="md" class="mb-4">Community Engagement</flux:heading>
    <flux:separator variant="subtle" class="mb-4" />

    @php $metrics = $this->metrics; @endphp

    <div class="grid grid-cols-2 gap-3">
        {{-- New Users --}}
        <button wire:click="showDetail('new_users')" class="text-left p-3 rounded-lg bg-zinc-800 hover:bg-zinc-700 transition">
            <flux:text variant="subtle" class="text-xs">New Users</flux:text>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-2xl font-bold text-white">{{ $metrics['current']['new_users'] }}</span>
                @if($metrics['has_previous'])
                    @php $delta = $metrics['current']['new_users'] - $metrics['previous']['new_users']; @endphp
                    <flux:badge size="sm" color="{{ $delta >= 0 ? 'green' : 'red' }}">
                        {{ $delta >= 0 ? '+' : '' }}{{ $delta }}
                    </flux:badge>
                @endif
            </div>
            @if($metrics['has_previous'])
                <flux:text variant="subtle" class="text-xs mt-1">prev: {{ $metrics['previous']['new_users'] }}</flux:text>
            @endif
        </button>

        {{-- New MC Accounts --}}
        <button wire:click="showDetail('new_mc_accounts')" class="text-left p-3 rounded-lg bg-zinc-800 hover:bg-zinc-700 transition">
            <flux:text variant="subtle" class="text-xs">New MC Accounts</flux:text>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-2xl font-bold text-white">{{ $metrics['current']['new_mc_accounts'] }}</span>
                @if($metrics['has_previous'])
                    @php $delta = $metrics['current']['new_mc_accounts'] - $metrics['previous']['new_mc_accounts']; @endphp
                    <flux:badge size="sm" color="{{ $delta >= 0 ? 'green' : 'red' }}">
                        {{ $delta >= 0 ? '+' : '' }}{{ $delta }}
                    </flux:badge>
                @endif
            </div>
            @if($metrics['has_previous'])
                <flux:text variant="subtle" class="text-xs mt-1">prev: {{ $metrics['previous']['new_mc_accounts'] }}</flux:text>
            @endif
        </button>

        {{-- Pending MC Verification --}}
        <div class="p-3 rounded-lg bg-zinc-800">
            <flux:text variant="subtle" class="text-xs">Pending MC Verification</flux:text>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-2xl font-bold text-white">{{ $metrics['current']['pending_mc_verification'] }}</span>
                @if($metrics['current']['pending_mc_verification'] > 0)
                    <flux:badge size="sm" color="amber">needs attention</flux:badge>
                @endif
            </div>
        </div>

        {{-- New Discord Accounts --}}
        <button wire:click="showDetail('new_discord_accounts')" class="text-left p-3 rounded-lg bg-zinc-800 hover:bg-zinc-700 transition">
            <flux:text variant="subtle" class="text-xs">New Discord Accounts</flux:text>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-2xl font-bold text-white">{{ $metrics['current']['new_discord_accounts'] }}</span>
                @if($metrics['has_previous'])
                    @php $delta = $metrics['current']['new_discord_accounts'] - $metrics['previous']['new_discord_accounts']; @endphp
                    <flux:badge size="sm" color="{{ $delta >= 0 ? 'green' : 'red' }}">
                        {{ $delta >= 0 ? '+' : '' }}{{ $delta }}
                    </flux:badge>
                @endif
            </div>
            @if($metrics['has_previous'])
                <flux:text variant="subtle" class="text-xs mt-1">prev: {{ $metrics['previous']['new_discord_accounts'] }}</flux:text>
            @endif
        </button>

        {{-- Active Users --}}
        <button wire:click="showDetail('active_users')" class="text-left p-3 rounded-lg bg-zinc-800 hover:bg-zinc-700 transition col-span-2">
            <flux:text variant="subtle" class="text-xs">Active Users (Logged In)</flux:text>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-2xl font-bold text-white">{{ $metrics['current']['active_users'] }}</span>
                @if($metrics['has_previous'])
                    @php $delta = $metrics['current']['active_users'] - $metrics['previous']['active_users']; @endphp
                    <flux:badge size="sm" color="{{ $delta >= 0 ? 'green' : 'red' }}">
                        {{ $delta >= 0 ? '+' : '' }}{{ $delta }}
                    </flux:badge>
                @endif
            </div>
            @if($metrics['has_previous'])
                <flux:text variant="subtle" class="text-xs mt-1">prev: {{ $metrics['previous']['active_users'] }}</flux:text>
            @endif
        </button>
    </div>

    <flux:text variant="subtle" class="text-xs mt-3">
        Current iteration: {{ $metrics['current_start']->format('M j') }} - {{ $metrics['current_end']->format('M j') }}
    </flux:text>
</flux:card>

{{-- Detail Modal --}}
<flux:modal name="community-detail-modal" class="w-full md:w-2/3 lg:w-1/2">
    <div class="space-y-4">
        <flux:heading size="lg">
            {{ match($this->activeDetailMetric) {
                'new_users' => 'New Users',
                'new_mc_accounts' => 'New MC Accounts',
                'new_discord_accounts' => 'New Discord Accounts',
                'active_users' => 'Active Users',
                default => 'Details',
            } }} — 3 Month Timeline
        </flux:heading>

        @if(count($this->timelineData) > 0)
            <div wire:key="community-chart-{{ $this->activeDetailMetric }}">
                <flux:chart :value="$this->timelineData" class="aspect-[5/2]">
                    <flux:chart.svg gutter="8 8 28 8">
                        <flux:chart.axis axis="y" field="count" tick-start="0">
                            <flux:chart.axis.grid class="text-zinc-700" />
                            <flux:chart.axis.tick class="text-zinc-400 text-xs" />
                        </flux:chart.axis>
                        <flux:chart.axis axis="x" field="label">
                            <flux:chart.axis.tick class="text-zinc-400 text-xs" />
                            <flux:chart.axis.line class="text-zinc-600" />
                        </flux:chart.axis>
                        <flux:chart.area field="count" class="text-blue-500/10" />
                        <flux:chart.line field="count" class="text-blue-500" />
                        <flux:chart.point field="count" class="text-blue-400" />
                        <flux:chart.cursor />
                    </flux:chart.svg>
                    <flux:chart.tooltip>
                        <flux:chart.tooltip.heading field="label" />
                        <flux:chart.tooltip.value field="count" label="Count" />
                    </flux:chart.tooltip>
                </flux:chart>
            </div>
        @else
            <flux:text variant="subtle">No historical iteration data available.</flux:text>
        @endif
    </div>
</flux:modal>
</div>
