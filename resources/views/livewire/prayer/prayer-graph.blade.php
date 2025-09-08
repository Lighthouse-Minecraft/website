<?php

use App\Models\PrayerCountryStat;
use Livewire\Volt\Component;

new class extends Component {
    public $data = [];

    public function mount()
    {
        $startDate = now()->subDays(7);

        $data = [];
        for ($i = 7; $i >= 0; $i--) {
            $useDate = now()->subDays($i);
            $data[$useDate->format('M j')] = [
                'date' => $useDate->format('M j'),
                'prayers' => 0,
            ];
        }

        $statData = PrayerCountryStat::where('created_at', '>=', $startDate)->get();
        foreach ($statData as $stat) {
            $data[$stat->created_at->format('M j')]['prayers'] = $stat->count;
        }

        $this->data = array_values($data);
    }

};
?>

<div>
    <flux:card>
        <flux:heading>Community Prayer Participation</flux:heading>
        <flux:chart wire:model="data" class="aspect-3/1">
            <flux:chart.svg>
                <flux:chart.line field="prayers" class="text-sky-500 dark:text-sky-400" curve="none" />
                <flux:chart.area field="prayers" class="fill-sky-500/10 dark:fill-sky-400/10" curve="none" />

                <flux:chart.axis axis="y" position="left" :format="[
                    'notation' => 'compact',
                    'compactDisplay' => 'short',
                    'maximumFractionDigits' => 1,
                ]">
                    <flux:chart.axis.grid />
                    <flux:chart.axis.tick />
                </flux:chart.axis>

                <flux:chart.axis axis="x" field="date">
                    <flux:chart.axis.tick />
                    <flux:chart.axis.line />
                </flux:chart.axis>
            </flux:chart.svg>
        </flux:chart>
    </flux:card>
</div>
