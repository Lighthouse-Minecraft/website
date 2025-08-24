<?php

use App\Models\PrayerCountry;
use App\Models\User;
use Livewire\Volt\Component;
use Flux\Flux;
use Illuminate\Support\Facades\Cache;

new class extends Component {
    public $day;
    public $prayerCountry;
    public $hasPrayedToday = false;
    public $user;

    public function mount() {
        $this->day = date('n-d');
        $this->loadPrayerData(date('n'), date('j'));
        $this->user = auth()->user();

        if ($this->prayerCountry) {
            $this->hasPrayedToday = $this->checkIfUserHasPrayedThisYear();
        }
    }

    private function checkIfUserHasPrayedThisYear(): bool
    {
        $currentYear = now()->format('Y');
        $userId = auth()->id();
        $prayerCountryId = $this->prayerCountry->id;

        $cacheKey = "user_prayer_{$userId}_{$prayerCountryId}_{$currentYear}";
        $cacheTtl = config('lighthouse.prayer_cache_ttl', 60 * 60 * 24); // default to 24 hours

        return Cache::flexible($cacheKey, [$cacheTtl, $cacheTtl * 7], function () use ($currentYear, $prayerCountryId) {
            return auth()->user()
                ->prayerCountries()
                ->wherePivot('prayer_country_id', $prayerCountryId)
                ->wherePivot('year', $currentYear)
                ->exists();
        });
    }

    public function markAsPrayedToday() {
        $currentYear = now()->format('Y');

        // Check if user has already prayed for this country this year
        $hasAlreadyPrayed = $this->user
            ->prayerCountries()
            ->wherePivot('prayer_country_id', $this->prayerCountry->id)
            ->wherePivot('year', $currentYear)
            ->exists();

        if ($hasAlreadyPrayed) {
            Flux::toast('You have already prayed for this country this year!', 'Info', variant: 'warning');
            return;
        }

        // Save the prayer record
        $this->user->prayerCountries()->attach($this->prayerCountry->id, [
            'year' => $currentYear,
        ]);

        if ($this->user->last_prayed_at && $this->user->last_prayed_at->isYesterday()) {
            $this->user->prayer_streak ++;
        } else if (!$this->user->last_prayed_at || !$this->user->last_prayed_at->isToday()) {
            $this->user->prayer_streak = 1; // reset streak if not consecutive
        }

        $this->user->last_prayed_at = now();
        $this->user->save();

        // Clear the cache for this user/country/year combination
        $cacheKey = "user_prayer_{$this->user->id}_{$this->prayerCountry->id}_{$currentYear}";
        Cache::forget($cacheKey);

        // Update the state
        $this->hasPrayedToday = true;

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
        <div class="flex">
            <flux:heading>Pray Today</flux:heading>
            <flux:spacer />
            <flux:text class="flex"><flux:icon.bolt variant="solid" class="text-yellow-300 size-4 mx-1" /> Prayer Streak: {{  $user->prayer_streak }}</flux:text>
        </div>

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
        <flux:separator class="my-5"/>
        <flux:link href="{{ config('lighthouse.prayer_list_url') }}" class="text-sm" target="_blank" rel="noopener noreferrer">Lighthouse Prayer List</flux:link>
        <flux:separator class="my-5"/>
        <div class="w-full text-right mt-6">
            @if($hasPrayedToday)
                <flux:button variant="ghost" disabled>
                    <flux:icon name="check" class="w-4 h-4 mr-1" />
                    Thank you for Praying
                </flux:button>
            @else
                <flux:button wire:click="markAsPrayedToday" variant="primary">I Prayed Today</flux:button>
            @endif
        </div>
    </flux:card>
</div>
