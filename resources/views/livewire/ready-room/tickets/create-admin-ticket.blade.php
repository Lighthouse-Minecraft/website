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
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new class extends Component {
    #[Validate('required|exists:users,id')]
    public string $target_user_id = '';

    #[Validate('required|string')]
    public string $department = '';

    #[Validate('required|string|max:255')]
    public string $subject = '';

    #[Validate('required|string|min:10')]
    public string $message = '';

    public function mount(): void
    {
        $this->authorize('createAsStaff', Thread::class);
        $this->department = StaffDepartment::Command->value;
        
        // Pre-fill user if provided in query string
        if (request()->has('user_id')) {
            $userId = request()->get('user_id');
            if (User::where('id', $userId)->exists()) {
                $this->target_user_id = $userId;
            }
        }
    }

    #[Computed]
    public function users()
    {
        return User::orderBy('name')->get();
    }

    public function createAdminTicket(): void
    {
        $this->authorize('createAsStaff', Thread::class);
        $this->validate();

        $targetUser = User::findOrFail($this->target_user_id);

        $thread = Thread::create([
            'type' => ThreadType::Ticket,
            'subtype' => ThreadSubtype::AdminAction,
            'department' => StaffDepartment::from($this->department),
            'subject' => $this->subject,
            'status' => ThreadStatus::Open,
            'created_by_user_id' => auth()->id(),
            'last_message_at' => now(),
        ]);

        // Add target user as participant
        $thread->addParticipant($targetUser);

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
        \App\Actions\RecordActivity::run(
            $thread,
            'admin_ticket_created',
            "Admin action ticket created for {$targetUser->name}: {$this->subject}"
        );

        // Notify target user
        $notificationService = app(TicketNotificationService::class);
        $notificationService->send($targetUser, new NewTicketNotification($thread));

        // Notify department staff
        $departmentStaff = User::where('staff_department', $this->department)
            ->whereNotNull('staff_rank')
            ->where('id', '!=', auth()->id())
            ->get();

        foreach ($departmentStaff as $staff) {
            $notificationService->send($staff, new NewTicketNotification($thread));
        }

        Flux::toast('Admin action ticket created successfully!', variant: 'success');

        $this->redirect('/tickets/'.$thread->id, navigate: true);
    }
}; ?>

<div>
    <flux:heading size="xl" class="mb-6">Create Admin Action Ticket</flux:heading>

    <form wire:submit="createAdminTicket">
        <div class="space-y-6">
            <flux:field>
                <flux:label>Target User <span class="text-red-500">*</span></flux:label>
                <flux:description>Which user is this ticket for?</flux:description>
                <flux:select wire:model="target_user_id" variant="listbox" searchable>
                    <flux:select.option value="">Select a user...</flux:select.option>
                    @foreach($this->users as $user)
                        <flux:select.option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="target_user_id" />
            </flux:field>

            <flux:field>
                <flux:label>Department <span class="text-red-500">*</span></flux:label>
                <flux:description>Which department should handle this ticket?</flux:description>
                <flux:select wire:model="department" variant="listbox">
                    <flux:select.option value="{{ StaffDepartment::Command->value }}">{{ StaffDepartment::Command->label() }}</flux:select.option>
                    <flux:select.option value="{{ StaffDepartment::Chaplain->value }}">{{ StaffDepartment::Chaplain->label() }}</flux:select.option>
                    <flux:select.option value="{{ StaffDepartment::Engineer->value }}">{{ StaffDepartment::Engineer->label() }}</flux:select.option>
                    <flux:select.option value="{{ StaffDepartment::Quartermaster->value }}">{{ StaffDepartment::Quartermaster->label() }}</flux:select.option>
                    <flux:select.option value="{{ StaffDepartment::Steward->value }}">{{ StaffDepartment::Steward->label() }}</flux:select.option>
                </flux:select>
                <flux:error name="department" />
            </flux:field>

            <flux:field>
                <flux:label>Subject <span class="text-red-500">*</span></flux:label>
                <flux:description>Brief description of the administrative action</flux:description>
                <flux:input wire:model="subject" placeholder="e.g., Account verification required" />
                <flux:error name="subject" />
            </flux:field>

            <flux:field>
                <flux:label>Message <span class="text-red-500">*</span></flux:label>
                <flux:description>Provide detailed information about this administrative action</flux:description>
                <flux:textarea wire:model="message" rows="6" placeholder="Please describe the administrative action..." />
                <flux:error name="message" />
            </flux:field>

            <div class="flex items-center gap-4">
                <flux:button type="submit" variant="primary">Create Admin Ticket</flux:button>
                <flux:button href="/tickets" variant="ghost">Cancel</flux:button>
            </div>
        </div>
    </form>
</div>
