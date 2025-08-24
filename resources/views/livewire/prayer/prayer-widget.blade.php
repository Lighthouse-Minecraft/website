<?php

use App\Models\PrayerCountry;
use Livewire\Volt\Component;
use Flux\Flux;

new class extends Component {
    public $day;
    public $prayerCountry;

    public function mount() {
        $this->day = date('n-d');
        $this->loadPrayerData(date('n'), date('j'));
    }

    public function markAsPrayedToday() {
        // Save the prayer record
        auth()->user()->prayerCountries()->attach($this->prayerCountry->id, [
            'year' => now()->format('Y'),
        ]);

        Flux::toast('Thank you for praying today!', 'Success', variant: 'success');
    }

    public function loadPrayerData($month, $day) {
        $cacheKey = "prayer_country_{$month}_{$day}";
        $cacheTtl = config('lighthouse.prayer_cache_ttl', 60 * 60 * 24); // default to 24 hours

        $prayerCountry = Cache::flexible($cacheKey, [$cacheTtl, $cacheTtl * 7], fn() => PrayerCountry::where('day', "{$month}-{$day}")->first());

        if ($prayerCountry) {
            $this->prayerCountry = $prayerCountry;
        }
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

        <div class="w-full text-right mt-6">
            <flux:button wire:click="markAsPrayedToday" variant="primary">I Prayed Today</flux:button>
        </div>
    </flux:card>
</div>
