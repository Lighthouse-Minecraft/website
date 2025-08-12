<x-layouts.app>
    <div class="space-y-6">
        <flux:heading size="xl">{{  $meeting->title }} - {{  $meeting->day }}</flux:heading>

        <flux:heading variant="primary">Agenda</flux:heading>
        <livewire:note.editor :meeting="$meeting" section_key="agenda"/>

        <livewire:meeting.department-section :meeting="$meeting" departmentValue="general" :key="'department-section-general'" />
        <flux:separator />
        @foreach(\App\Enums\StaffDepartment::cases() as $department)
            <livewire:meeting.department-section :meeting="$meeting" :departmentValue="$department->value" :key="'department-section-' . $department->value" />
            <flux:separator />
        @endforeach
    </div>
 </x-layouts.app>
