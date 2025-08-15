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

<div class="w-full lg:w-3/4 mx-auto space-y-6 my-6">
    <flux:heading><span class="text-sky-600 dark:text-sky-400">{{  $departmentLabel }}</span></flux:heading>

    <flux:text variant="subtle">
        {{ $description }}
    </flux:text>

    <livewire:note.editor :meeting="$meeting" :section_key="$departmentValue"/>
</div>
