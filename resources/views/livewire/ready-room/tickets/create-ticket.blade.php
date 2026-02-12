<?php

use App\Enums\MessageKind;
use App\Enums\StaffDepartment;
use App\Enums\ThreadStatus;
use App\Enums\ThreadSubtype;
use App\Enums\ThreadType;
use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use App\Notifications\NewTicketNotification;
use App\Services\TicketNotificationService;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new class extends Component {
    #[Validate('required|string|max:255')]
    public string $subject = '';

    #[Validate('required|string')]
    public string $department = '';

    #[Validate('required|string|min:10')]
    public string $message = '';

    public function mount(): void
    {
        // Default to Command department
        $this->department = StaffDepartment::Command->value;
    }

    public function createTicket(): void
    {
        $this->validate();

        $thread = Thread::create([
            'type' => ThreadType::Ticket,
            'subtype' => ThreadSubtype::Support,
            'department' => StaffDepartment::from($this->department),
            'subject' => $this->subject,
            'status' => ThreadStatus::Open,
            'created_by_user_id' => auth()->id(),
            'last_message_at' => now(),
        ]);

        // Add creator as participant
        $thread->addParticipant(auth()->user());

        // Create first message
        Message::create([
            'thread_id' => $thread->id,
            'user_id' => auth()->id(),
            'body' => $this->message,
            'kind' => MessageKind::Message,
        ]);

        // Record activity
        \App\Actions\RecordActivity::run($thread, 'ticket_created', "Ticket created: {$this->subject}");

        // Notify department staff
        $departmentStaff = User::where('staff_department', $this->department)
            ->whereNotNull('staff_rank')
            ->where('id', '!=', auth()->id())
            ->get();

        $notificationService = app(TicketNotificationService::class);
        foreach ($departmentStaff as $staff) {
            $notificationService->send($staff, new NewTicketNotification($thread));
        }

        Flux::toast('Your ticket has been created successfully!', variant: 'success');

        $this->redirect('/ready-room/tickets/'.$thread->id, navigate: true);
    }
}; ?>

<div>
    <flux:heading size="xl" class="mb-6">Create New Ticket</flux:heading>

    <form wire:submit="createTicket">
        <div class="space-y-6">
            <flux:field>
                <flux:label>Department <span class="text-red-500">*</span></flux:label>
                <flux:description>Which department should handle this ticket?</flux:description>
                <flux:select wire:model="department" variant="listbox">
                    <flux:option value="{{ StaffDepartment::Command->value }}">{{ StaffDepartment::Command->label() }}</flux:option>
                    <flux:option value="{{ StaffDepartment::Chaplain->value }}">{{ StaffDepartment::Chaplain->label() }}</flux:option>
                    <flux:option value="{{ StaffDepartment::Engineer->value }}">{{ StaffDepartment::Engineer->label() }}</flux:option>
                    <flux:option value="{{ StaffDepartment::Quartermaster->value }}">{{ StaffDepartment::Quartermaster->label() }}</flux:option>
                    <flux:option value="{{ StaffDepartment::Steward->value }}">{{ StaffDepartment::Steward->label() }}</flux:option>
                </flux:select>
                <flux:error name="department" />
            </flux:field>

            <flux:field>
                <flux:label>Subject <span class="text-red-500">*</span></flux:label>
                <flux:description>Brief description of your issue or request</flux:description>
                <flux:input wire:model="subject" placeholder="e.g., Account access issue" />
                <flux:error name="subject" />
            </flux:field>

            <flux:field>
                <flux:label>Message <span class="text-red-500">*</span></flux:label>
                <flux:description>Provide detailed information about your ticket</flux:description>
                <flux:textarea wire:model="message" rows="6" placeholder="Please describe your issue in detail..." />
                <flux:error name="message" />
            </flux:field>

            <div class="flex items-center gap-4">
                <flux:button type="submit" variant="primary">Create Ticket</flux:button>
                <flux:button href="/ready-room/tickets" variant="ghost">Cancel</flux:button>
            </div>
        </div>
    </form>
</div>
