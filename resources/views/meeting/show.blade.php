<x-layouts.app>
    <flux:heading size="xl">{{  $meeting->title }} - {{  $meeting->day }}</flux:heading>

    <livewire:meeting.department-section :meeting="$meeting" departmentValue="general" :key="'department-section-general'" />
    @foreach(\App\Enums\StaffDepartment::cases() as $department)
        <livewire:meeting.department-section :meeting="$meeting" :departmentValue="$department->value" :key="'department-section-' . $department->value" />
    @endforeach

 </x-layouts.app>
