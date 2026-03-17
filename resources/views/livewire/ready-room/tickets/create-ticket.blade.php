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
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    #[Validate('required|string|max:255')]
    public string $subject = '';

    #[Validate('required|string')]
    public string $department = '';

    public string $message = '';

    public $ticketImage = null;

    public function mount(): void
    {
        // Default to Command department
        $this->department = StaffDepartment::Command->value;
    }

    public function removeTicketImage(): void
    {
        $this->ticketImage = null;
    }

    public function createTicket(): void
    {
        $maxKb = \App\Models\SiteConfig::getValue('max_image_size_kb', '2048');

        $this->validate([
            'subject' => 'required|string|max:255',
            'department' => ['required', 'string', Rule::enum(StaffDepartment::class)],
            'message' => 'required_without:ticketImage|nullable|string|min:10',
            'ticketImage' => 'nullable|mimes:jpg,jpeg,png,gif,webp,heic,heif|max:' . $maxKb,
        ]);

        $thread = \Illuminate\Support\Facades\DB::transaction(function () {
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
            $firstMessage = Message::create([
                'thread_id' => $thread->id,
                'user_id' => auth()->id(),
                'body' => $this->message ?? '',
                'kind' => MessageKind::Message,
            ]);

            if ($this->ticketImage) {
                $imagePath = $this->ticketImage->store('message-images', config('filesystems.public_disk'));
                if ($imagePath === false) {
                    throw new \RuntimeException('Failed to store uploaded image.');
                }
                $firstMessage->update(['image_path' => $imagePath]);
            }

            // Record activity
            \App\Actions\RecordActivity::run($thread, 'ticket_opened', "Opened ticket: {$this->subject}");

            return $thread;
        });

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

        $this->redirect('/tickets/'.$thread->id, navigate: true);
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

            @php
                $maxKb = (int) \App\Models\SiteConfig::getValue('max_image_size_kb', '2048');
                $maxImageSizeLabel = $maxKb >= 1024 ? round($maxKb / 1024) . 'MB' : $maxKb . 'KB';
            @endphp
            <div>
                <flux:file-upload wire:model="ticketImage" label="Image (optional)">
                    <flux:file-upload.dropzone
                        heading="Drop an image here or click to browse"
                        :text="'JPG, PNG, GIF, WEBP, HEIC up to ' . $maxImageSizeLabel"
                    />
                </flux:file-upload>
                @if($ticketImage)
                    <flux:file-item
                        :heading="$ticketImage->getClientOriginalName()"
                        :image="$ticketImage->temporaryUrl()"
                        :size="$ticketImage->getSize()"
                    >
                        <x-slot name="actions">
                            <flux:file-item.remove wire:click="removeTicketImage" />
                        </x-slot>
                    </flux:file-item>
                @endif
                <flux:error name="ticketImage" />
            </div>

            <div class="flex items-center gap-4">
                <flux:button type="submit" variant="primary">Create Ticket</flux:button>
                <flux:button href="/tickets" variant="ghost">Cancel</flux:button>
            </div>
        </div>
    </form>
</div>
