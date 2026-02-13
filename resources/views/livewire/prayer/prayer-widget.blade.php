<?php

use App\Models\PrayerCountry;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Component;

new class extends Component
{
    public $day;

    public $prayerCountry;

    public $hasPrayedToday = false;

    public $user;

    public $prayerStats;

    public $userTimezone;

    public $currentDate;

    public $currentYear;

    public function mount()
    {
        $this->user = auth()->user();

        // Get current date in user's timezone (default to America/New_York if not set)
        $this->userTimezone = $this->user->timezone ?? 'America/New_York';
        $this->currentDate = now()->setTimezone($this->userTimezone);

        $this->day = $this->currentDate->format('n-j');
        $this->currentYear = $this->currentDate->format('Y');
        $this->loadPrayerData($this->currentDate->format('n'), $this->currentDate->format('j'));

        if ($this->prayerCountry) {
            $this->hasPrayedToday = $this->checkIfUserHasPrayedThisYear();
        }
    }

    private function checkIfUserHasPrayedThisYear(): bool
    {
        // Get current year in user's timezone
        $userTimezone = $this->user->timezone ?? 'America/New_York';
        $currentYear = now()->setTimezone($userTimezone)->format('Y');

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

    public function markAsPrayedToday()
    {
        if (! $this->prayerCountry) {
            Flux::toast('No prayer country found for today.', 'Error', variant: 'danger');

            return;
        }
        if ($this->hasPrayedToday) {
            Flux::toast('You have already marked as prayed today!', 'Info', variant: 'warning');

            return;
        }
        // Check if user has already prayed for this country this year
        $hasAlreadyPrayed = $this->user
            ->prayerCountries()
            ->wherePivot('prayer_country_id', $this->prayerCountry->id)
            ->wherePivot('year', $this->currentYear)
            ->exists();

        if ($hasAlreadyPrayed) {
            Flux::toast('You have already prayed for this country this year!', 'Info', variant: 'warning');

            return;
        }

        // Save the prayer record
        $this->user->prayerCountries()->attach($this->prayerCountry->id, [
            'year' => $this->currentYear,
        ]);

        // Check if the user prayed yesterday in their timezone
        $lastPrayedInUserTz = $this->user->last_prayed_at?->setTimezone($this->userTimezone);
        $todayInUserTz = $this->currentDate->copy()->startOfDay();
        $yesterdayInUserTz = $todayInUserTz->copy()->subDays(1);
        
        if ($lastPrayedInUserTz && $lastPrayedInUserTz->isSameDay($yesterdayInUserTz)) {
            $this->user->prayer_streak++;
        } elseif (! $lastPrayedInUserTz || ! $lastPrayedInUserTz->isSameDay($todayInUserTz)) {
            $this->user->prayer_streak = 1; // reset streak if not consecutive
        }

        $this->user->last_prayed_at = $this->currentDate;
        $this->user->save();

        $this->prayerStats->count++;
        $this->prayerStats->save();

        // Clear the cache for this user/country/year combination
        $cacheKey = "user_prayer_{$this->user->id}_{$this->prayerCountry->id}_{$this->currentYear}";
        Cache::forget($cacheKey);

        // Update the state
        $this->hasPrayedToday = true;

        Flux::toast('Thank you for praying today!', 'Success', variant: 'success');
    }

    public function loadPrayerData($month, $day)
    {
        $cacheKey = "prayer_country_{$month}_{$day}";
        $cacheTtl = config('lighthouse.prayer_cache_ttl', 60 * 60 * 24); // default to 24 hours

        $prayerCountry = Cache::flexible($cacheKey, [$cacheTtl, $cacheTtl * 7], fn () => PrayerCountry::where('day', "{$month}-{$day}")->with('stats')->first());

        if ($prayerCountry) {
            $this->prayerCountry = $prayerCountry;

            // Get current year in user's timezone
            $userTimezone = $this->user->timezone ?? 'America/New_York';
            $year = now()->setTimezone($userTimezone)->year;

            $this->prayerStats = $prayerCountry->stats()->firstOrCreate(
                ['year' => $year],
                ['count' => 0],
            );
        }
    }
}; ?>

<div>
    <flux:card class="space-y-3">
        <div class="flex">
            <flux:heading>Pray Today</flux:heading>
            <flux:spacer />
            <div class="flex gap-3">
                <flux:tooltip content="Your Prayer Streak" class="flex">
                    <flux:icon.bolt variant="solid" class="text-yellow-300 size-4 mx-1" />
                    <flux:text variant="pill" class="">
                        {{  $user->prayer_streak }}
                    </flux:text>
                </flux:tooltip>
                @if ($prayerStats)
                    <flux:tooltip content="How Many Lighthouse Members Prayed Today" class="flex">
                        <flux:icon.user-group variant="solid" class="text-purple-500 size-4 mx-1" />
                        <flux:text>{{ $prayerStats->count }}</flux:text>
                    </flux:tooltip>
                @endif
            </div>
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
        <div class="w-full flex mt-6">

            <flux:spacer />
            @if($hasPrayedToday)
                <flux:button variant="ghost" size="xs" disabled>
                    <flux:icon name="check" class="w-4 h-4 mr-1" />
                    Thank you for Praying
                </flux:button>
            @else
                <flux:button wire:click="markAsPrayedToday" variant="primary">I Prayed Today</flux:button>
            @endif
        </div>
    </flux:card>
</div>
