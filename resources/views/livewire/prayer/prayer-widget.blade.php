<?php

use App\Models\PrayerCountry;
use Livewire\Volt\Component;

new class extends Component {
    public $day;
    public $prayerCountry;

    public function mount() {
        $this->day = date('n-d');
        $this->prayerCountry = PrayerCountry::where('day', $this->day)->first();
    }
}; ?>

<div>
    <flux:card class="space-y-3">
        <flux:heading>Pray Today</flux:heading>

        @if($prayerCountry)
            <flux:separator />
            <flux:heading class="flex items-center gap-2">
                Operation World
                <flux:tooltip toggleable>
                    <flux:button icon="information-circle" size="xs" variant="ghost" />
                    <flux:tooltip.content class="max-w-[20rem] space-y-2">
                        <p>"The purpose of Operation World is to inform and inspire God’s people to prayer and action in order to change the world."</p>
                        <p>Operation World is a great way to bolster your prayer life while helping to pray for the needs of every country in the world throughout the year.</p>
                    </flux:tooltip.content>
                </flux:tooltip>
            </flux:heading>

            <flux:text class="font-medium">Prayer Topic: {{ $prayerCountry->name }}</flux:text>

            <flux:button href="{{ $prayerCountry->operation_world_url }}" size="xs" target="_blank" >Prayer Details</flux:button>

            @if($prayerCountry->prayer_cast_url)
                <flux:button href="{{ $prayerCountry->prayer_cast_url }}" size="xs" target="_blank" >PrayerCast Video</flux:button>
            @endif
        @endif
        <flux:separator />
        <flux:link href="{{ config('lighthouse.prayer_list_url') }}" class="text-sm" target="_blank" rel="noopener noreferrer">Lighthouse Prayer List</flux:link>
    </flux:card>
</div>
