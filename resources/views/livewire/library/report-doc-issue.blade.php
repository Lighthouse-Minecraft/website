<?php

use App\Actions\RecordActivity;
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
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component {
    public string $pageTitle = '';
    public string $pageUrl = '';
    public string $details = '';

    public function mount(string $pageTitle = '', string $pageUrl = ''): void
    {
        $this->pageTitle = $pageTitle;
        $this->pageUrl = $pageUrl;
    }

    public function submit(): void
    {
        $this->validate([
            'details' => 'required|string|min:10',
        ]);

        $subject = "Documentation Issue: {$this->pageTitle}";

        $body = "**Page:** [{$this->pageTitle}]({$this->pageUrl})\n\n"
            . "**Issue Details:**\n{$this->details}";

        $thread = Thread::create([
            'type' => ThreadType::Ticket,
            'subtype' => ThreadSubtype::Support,
            'department' => StaffDepartment::Command,
            'subject' => $subject,
            'status' => ThreadStatus::Open,
            'created_by_user_id' => auth()->id(),
            'last_message_at' => now(),
        ]);

        $thread->addParticipant(auth()->user());

        Message::create([
            'thread_id' => $thread->id,
            'user_id' => auth()->id(),
            'body' => $body,
            'kind' => MessageKind::Message,
        ]);

        RecordActivity::run($thread, 'ticket_opened', "Opened ticket: {$subject}");

        $departmentStaff = User::where('staff_department', StaffDepartment::Command->value)
            ->whereNotNull('staff_rank')
            ->where('id', '!=', auth()->id())
            ->get();

        $notificationService = app(TicketNotificationService::class);
        foreach ($departmentStaff as $staff) {
            $notificationService->send($staff, new NewTicketNotification($thread));
        }

        $this->details = '';

        Flux::modal('report-doc-issue')->close();
        Flux::toast('Your documentation issue has been reported. Thank you!', variant: 'success');

        $this->redirect('/tickets/' . $thread->id, navigate: true);
    }
}; ?>

<div>
    <flux:modal name="report-doc-issue" class="md:w-96">
        <div class="space-y-4">
            <flux:heading size="lg">Report Documentation Issue</flux:heading>
            <flux:text variant="subtle">
                Let us know what's wrong with this page and we'll get it fixed.
            </flux:text>

            <flux:field>
                <flux:label>Page</flux:label>
                <flux:input :value="$pageTitle" disabled />
            </flux:field>

            <flux:field>
                <flux:label>What needs to be fixed? <span class="text-red-500">*</span></flux:label>
                <flux:textarea wire:model="details" rows="4" placeholder="Describe what's incorrect, outdated, or unclear..." />
                <flux:error name="details" />
            </flux:field>

            <div class="flex gap-2">
                <flux:button variant="primary" wire:click="submit">Submit Report</flux:button>
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
