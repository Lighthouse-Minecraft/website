<?php

use App\Enums\StaffDepartment;
use App\Models\Meeting;
use Livewire\Volt\Component;

new class extends Component {
    public Meeting $meeting;
    public string $departmentValue;
    public string $departmentLabel;
    public StaffDepartment $department;

    public function mount(Meeting $meeting, string $departmentValue)
    {
        $this->meeting = $meeting;
        $this->departmentValue = $departmentValue;

        if ($departmentValue === 'general') {
            $this->departmentLabel = 'General';
        } else {
            $this->department = StaffDepartment::from($departmentValue);
            $this->departmentLabel = $this->department->label();
        }
    }

}; ?>

<div class="w-full space-y-6 my-6">
    <flux:heading><span class="text-sky-600 dark:text-sky-400">{{  $departmentLabel }}</span></flux:heading>

    <div class="flex w-full">
        <div class="w-2/4">
            <livewire:note.editor :meeting="$meeting" :section_key="$departmentValue"/>
        </div>
    </div>
</div>
