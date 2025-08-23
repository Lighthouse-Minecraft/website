<?php

use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component {
    public $month;
    public $monthName;
    public $year;
    public $day;
    public $date;

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

        <flux:calendar wire:model="date" size="xs"></flux:calendar>
    </flux:modal>
</div>
