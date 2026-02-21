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
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public string $appealMessage = '';

    public function submitAppeal(): void
    {
        $user = Auth::user();

        if (! $user->canAppeal()) {
            Flux::toast('You cannot submit an appeal at this time.', 'Not Available', variant: 'danger');
            return;
        }

        $this->validate([
            'appealMessage' => 'required|string|min:20',
        ]);

        // Create appeal ticket directly — brig users are exempt from the ticket create policy for appeals only
        $thread = Thread::create([
            'type' => ThreadType::Ticket,
            'subtype' => ThreadSubtype::AdminAction,
            'department' => StaffDepartment::Quartermaster,
            'subject' => 'Brig Appeal: '.$user->name,
            'status' => ThreadStatus::Open,
            'created_by_user_id' => $user->id,
            'last_message_at' => now(),
        ]);

        $thread->addParticipant($user);

        Message::create([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
            'body' => $this->appealMessage,
            'kind' => MessageKind::Message,
        ]);

        \App\Actions\RecordActivity::handle($thread, 'ticket_opened', 'Brig appeal submitted: '.$thread->subject);

        // Notify Quartermaster staff
        $notificationService = app(TicketNotificationService::class);
        $quartermaster = User::where('staff_department', StaffDepartment::Quartermaster)
            ->whereNotNull('staff_rank')
            ->get();

        foreach ($quartermaster as $member) {
            $notificationService->send($member, new NewTicketNotification($thread));
        }

        // Set a 7-day lockout to prevent appeal spam
        $user->next_appeal_available_at = now()->addDays(7);
        $user->save();

        $this->appealMessage = '';
        Flux::modal('brig-appeal-modal')->close();
        Flux::toast('Your appeal has been submitted. You may submit another in 7 days if needed.', 'Appeal Submitted', variant: 'success');
    }
}; ?>

<flux:card class="w-full max-w-lg mx-auto text-center py-8 px-6 space-y-6">
    <div class="flex flex-col items-center gap-3">
        <flux:icon name="lock-closed" class="w-12 h-12 text-red-500" />
        <flux:heading size="xl">You Are In the Brig</flux:heading>
        <flux:badge color="red" size="lg">Access Restricted</flux:badge>
    </div>

    @php $user = Auth::user(); @endphp

    @if($user->brig_reason)
        <div class="bg-zinc-800 rounded-lg p-4 text-left">
            <flux:text class="font-medium text-sm text-zinc-400 uppercase tracking-wide mb-1">Reason</flux:text>
            <flux:text>{{ $user->brig_reason }}</flux:text>
        </div>
    @endif

    @if($user->brig_expires_at && ! $user->brigTimerExpired())
        <div class="bg-zinc-800 rounded-lg p-4 text-left">
            <flux:text class="font-medium text-sm text-zinc-400 uppercase tracking-wide mb-1">Appeal Available</flux:text>
            <flux:text>You may submit an appeal after <strong>{{ $user->brig_expires_at->format('F j, Y \a\t g:i A T') }}</strong>.</flux:text>
        </div>
    @endif

    <flux:text variant="subtle" class="text-sm">
        Your Minecraft server access has been suspended. You may still view your existing support tickets.
    </flux:text>

    @if($user->canAppeal())
        <flux:modal.trigger name="brig-appeal-modal">
            <flux:button variant="primary" icon="chat-bubble-left-ellipsis">Submit Appeal</flux:button>
        </flux:modal.trigger>
    @else
        <flux:button disabled variant="ghost" icon="chat-bubble-left-ellipsis">Appeal Not Yet Available</flux:button>
    @endif

    <flux:modal name="brig-appeal-modal" class="w-full lg:w-1/2">
        <div class="space-y-6">
            <flux:heading size="lg">Submit Brig Appeal</flux:heading>
            <flux:text variant="subtle">Explain your situation to the Quartermaster. Be respectful and honest. Staff will review your appeal and respond via ticket.</flux:text>

            <flux:field>
                <flux:label>Your Appeal <span class="text-red-500">*</span></flux:label>
                <flux:textarea wire:model="appealMessage" rows="6" placeholder="Explain why you believe the Brig status should be lifted..." />
                <flux:error name="appealMessage" />
            </flux:field>

            <div class="flex gap-2 justify-end">
                <flux:button variant="ghost" x-on:click="$flux.modal('brig-appeal-modal').close()">Cancel</flux:button>
                <flux:button wire:click="submitAppeal" variant="primary">Submit Appeal</flux:button>
            </div>
        </div>
    </flux:modal>
</flux:card>
