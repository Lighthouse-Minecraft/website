<?php

use App\Models\PrayerCountry;
use Flux\Flux;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Component;


new class extends Component {
    public $prayerCountry;
    public $month;
    public $monthName;
    public $year;
    public $day;
    public $date;

    public $prayerName;
    public $prayerDay;
    public $prayerOperationWorldUrl;
    public $prayerPrayerCastUrl;

    public function mount() {
        $this->year = date('Y');
        $this->month = null;
        $this->monthName = null;
        $this->day = 1;
    }

    public function openMonthModal($month) {
        $this->month = $month;
        $this->monthName = $this->getMonthName($month);

        $this->date = "{$this->year}-{$this->month}-{$this->day}";
        $this->prayerDay = "{$this->month}-{$this->day}";

        $this->loadPrayerData($this->month, $this->day);

        Flux::modal('month-modal')->show();
    }

    private function getMonthName($month) {
        $months = [
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
            6 => 'June',
            7 => 'July',
            8 => 'August',
            9 => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December',
        ];

        return $months[$month] ?? 'Unknown';
    }

    public function updatedDate() {

        $dateParts = explode('-', $this->date);
        if (count($dateParts) === 3) {
            $this->year = $dateParts[0];
            $this->month = (int)$dateParts[1];
            $this->day = (int)$dateParts[2];
            $this->monthName = $this->getMonthName($this->month);

            $this->prayerDay = "{$this->month}-{$this->day}";

            // Load prayer data for the selected date
            $this->loadPrayerData($this->month, $this->day);
        }
    }

    public function savePrayerData() {
        // Check if we're creating or updating and authorize accordingly
        if ($this->prayerCountry) {
            $this->authorize('update', $this->prayerCountry);
        } else {
            $this->authorize('create', PrayerCountry::class);
        }

        // Validate input
        $this->validate([
            'prayerName' => 'required|string|max:255',
            'prayerOperationWorldUrl' => 'nullable|url|max:255',
            'prayerPrayerCastUrl' => 'nullable|url|max:255',
        ]);

        // Save or update prayer country data
        $prayerCountry = PrayerCountry::updateOrCreate(
            ['day' => $this->prayerDay],
            [
                'name' => $this->prayerName,
                'operation_world_url' => $this->prayerOperationWorldUrl,
                'prayer_cast_url' => $this->prayerPrayerCastUrl,
            ]
        );

        // Set the prayer country instance for future operations
        $this->prayerCountry = $prayerCountry;

        // Reset the cache for this day
        Cache::forget("prayer_country_{$this->month}_{$this->day}");

        // Optionally, you can reset the form or provide feedback
        Flux::toast('Prayer data saved successfully.', 'Success', variant: 'success');
    }

    public function loadPrayerData($month, $day) {
        $cacheKey = "prayer_country_{$month}_{$day}";
        $cacheTtl = config('lighthouse.prayer_cache_ttl', 60 * 60 * 24); // default to 24 hours

        $prayerCountry = Cache::flexible($cacheKey, [$cacheTtl, $cacheTtl * 7], fn() => PrayerCountry::where('day', "{$month}-{$day}")->first());

        if ($prayerCountry) {
            $this->prayerCountry = $prayerCountry;
            $this->prayerName = $prayerCountry->name;
            $this->prayerOperationWorldUrl = $prayerCountry->operation_world_url;
            $this->prayerPrayerCastUrl = $prayerCountry->prayer_cast_url;
        } else {
            $this->prayerCountry = null;
            $this->resetPrayerData();
        }
    }

    private function resetPrayerData() {
        $this->prayerName = null;
        $this->prayerOperationWorldUrl = null;
        $this->prayerPrayerCastUrl = null;
    }
}; ?>

<div>
    <flux:table>
        <flux:table.columns>
            <flux:table.cell>Month</flux:table.cell>
        </flux:table.columns>
        <flux:table.rows>
            <flux:table.row>
                <flux:table.cell>
                    <flux:modal.trigger wire:click="openMonthModal(1)">
                        <flux:link>January</flux:link>
                    </flux:modal.trigger>
                </flux:table.cell>
            </flux:table.row>
            <flux:table.row>
                <flux:table.cell>
                    <flux:modal.trigger wire:click="openMonthModal(2)">
                        <flux:link>February</flux:link>
                    </flux:modal.trigger>
                </flux:table.cell>
            </flux:table.row>
            <flux:table.row>
                <flux:table.cell>
                    <flux:modal.trigger wire:click="openMonthModal(3)">
                        <flux:link>March</flux:link>
                    </flux:modal.trigger>
                </flux:table.cell>
            </flux:table.row>
            <flux:table.row>
                <flux:table.cell>
                    <flux:modal.trigger wire:click="openMonthModal(4)">
                        <flux:link>April</flux:link>
                    </flux:modal.trigger>
                </flux:table.cell>
            </flux:table.row>
            <flux:table.row>
                <flux:table.cell>
                    <flux:modal.trigger wire:click="openMonthModal(5)">
                        <flux:link>May</flux:link>
                    </flux:modal.trigger>
                </flux:table.cell>
            </flux:table.row>
            <flux:table.row>
                <flux:table.cell>
                    <flux:modal.trigger wire:click="openMonthModal(6)">
                        <flux:link>June</flux:link>
                    </flux:modal.trigger>
                </flux:table.cell>
            </flux:table.row>
            <flux:table.row>
                <flux:table.cell>
                    <flux:modal.trigger wire:click="openMonthModal(7)">
                        <flux:link>July</flux:link>
                    </flux:modal.trigger>
                </flux:table.cell>
            </flux:table.row>
            <flux:table.row>
                <flux:table.cell>
                    <flux:modal.trigger wire:click="openMonthModal(8)">
                        <flux:link>August</flux:link>
                    </flux:modal.trigger>
                </flux:table.cell>
            </flux:table.row>
            <flux:table.row>
                <flux:table.cell>
                    <flux:modal.trigger wire:click="openMonthModal(9)">
                        <flux:link>September</flux:link>
                    </flux:modal.trigger>
                </flux:table.cell>
            </flux:table.row>
            <flux:table.row>
                <flux:table.cell>
                    <flux:modal.trigger wire:click="openMonthModal(10)">
                        <flux:link>October</flux:link>
                    </flux:modal.trigger>
                </flux:table.cell>
            </flux:table.row>
            <flux:table.row>
                <flux:table.cell>
                    <flux:modal.trigger wire:click="openMonthModal(11)">
                        <flux:link>November</flux:link>
                    </flux:modal.trigger>
                </flux:table.cell>
            </flux:table.row>
            <flux:table.row>
                <flux:table.cell>
                    <flux:modal.trigger wire:click="openMonthModal(12)">
                        <flux:link>December</flux:link>
                    </flux:modal.trigger>
                </flux:table.cell>
            </flux:table.row>
        </flux:table.rows>
    </flux:table>

    <flux:modal name="month-modal" class="w-full">
        <flux:heading size="lg">Manage {{ $monthName }}</flux:heading>

        <flux:calendar wire:model.live="date" size="xs"></flux:calendar>

        <form wire:submit.prevent="savePrayerData">
            <div class="space-y-6">
                <flux:text>Day: {{  $prayerDay }}</flux:text>
                <flux:input wire:model="prayerName" label="Country Name" />
                <flux:input wire:model="prayerOperationWorldUrl" label="Operation World URL" />
                <flux:input wire:model="prayerPrayerCastUrl" label="Prayer Cast URL" />

                <div class="w-full text-right">
                    <flux:button wire:click="savePrayerData" variant="primary">Save Prayer Data</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>
</div>
