<?php

use App\Enums\StaffDepartment;
use App\Models\Meeting;
use Livewire\Volt\Component;

new class extends Component {
    public Meeting $meeting;
    public string $departmentValue;
    public string $departmentLabel;
    public StaffDepartment $department;
    public ?string $description = null;

    public function mount(Meeting $meeting, string $departmentValue, ?string $description)
    {
        $this->meeting = $meeting;
        $this->departmentValue = $departmentValue;
        $this->description = $description;

        if ($departmentValue === 'general') {
            $this->departmentLabel = 'General';
        } elseif ($departmentValue === 'community') {
            $this->departmentLabel = 'Public Community Minutes';
        } else {
            $this->department = StaffDepartment::from($departmentValue);
            $this->departmentLabel = $this->department->label();
        }
    }

}; ?>

<div class="w-full lg:w-full mx-auto space-y-6 my-6">
    <flux:heading><span class="text-sky-600 dark:text-sky-400">{{  $departmentLabel }}</span></flux:heading>

    <flux:text variant="subtle">
        {{ $description }}
    </flux:text>

    @if ($departmentValue === 'community')
        <div class="w-full lg:w-2/3 mx-auto">
            <livewire:note.editor :meeting="$meeting" :section_key="$departmentValue"/>
        </div>
    @else
        <div class="block lg:flex w-full gap-4">
            <div class="w-full lg:w-2/3">
                <livewire:note.editor :meeting="$meeting" :section_key="$departmentValue"/>
            </div>

            <div class="w-full lg:w-1/3">
                <livewire:task.department-list :meeting="$meeting" :section_key="$departmentValue"/>
            </div>
        </div>
    @endif
</div>
