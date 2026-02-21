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
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;

new class extends Component {
    public string $appealMessage = '';

    /**
     * Submit the current user's brig appeal.
     *
     * Validates the appeal message, creates a Quartermaster ticket containing the appeal,
     * records ticket activity, sets a 7-day next-appeal cooldown for the user, attempts
     * to notify Quartermaster staff, and updates the UI (closes modal, clears input, shows toast).
     *
     * If the user is not eligible to appeal or ticket creation fails, a danger toast is shown and the method exits.
     */
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

        $thread = null;

        // Create appeal ticket directly — brig users are exempt from the ticket create policy for appeals only
        DB::transaction(function () use ($user, &$thread) {
            // Re-check with a row lock to prevent duplicate appeals from concurrent requests
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->firstOrFail();

            if (! $lockedUser->canAppeal()) {
                return;
            }

            $thread = Thread::create([
                'type' => ThreadType::Ticket,
                'subtype' => ThreadSubtype::AdminAction,
                'department' => StaffDepartment::Quartermaster,
                'subject' => 'Brig Appeal: '.$lockedUser->name,
                'status' => ThreadStatus::Open,
                'created_by_user_id' => $lockedUser->id,
                'last_message_at' => now(),
            ]);

            $thread->addParticipant($lockedUser);

            Message::create([
                'thread_id' => $thread->id,
                'user_id' => $lockedUser->id,
                'body' => $this->appealMessage,
                'kind' => MessageKind::Message,
            ]);

            \App\Actions\RecordActivity::handle($thread, 'ticket_opened', 'Brig appeal submitted: '.$thread->subject);

            // Set a 7-day lockout to prevent appeal spam
            $lockedUser->next_appeal_available_at = now()->addDays(7);
            $lockedUser->save();
        });

        if ($thread === null) {
            Flux::toast('You cannot submit an appeal at this time.', 'Not Available', variant: 'danger');
            return;
        }

        // Fetch quartermasters outside the transaction — read doesn't need to extend the row lock
        $quartermasters = User::where('staff_department', StaffDepartment::Quartermaster)
            ->whereNotNull('staff_rank')
            ->get();

        // Notify Quartermaster staff outside the transaction so failures don't roll back DB changes
        try {
            $notificationService = app(TicketNotificationService::class);
            $notificationService->sendToMany($quartermasters, new NewTicketNotification($thread));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send brig appeal notifications', ['error' => $e->getMessage()]);
        }

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
        <div class="bg-zinc-50 dark:bg-zinc-800 rounded-lg p-4 text-left">
            <flux:text class="font-medium text-sm text-zinc-600 dark:text-zinc-400 uppercase tracking-wide mb-1">Reason</flux:text>
            <flux:text class="text-zinc-800 dark:text-zinc-200">{{ $user->brig_reason }}</flux:text>
        </div>
    @endif

    @if($user->next_appeal_available_at && ! $user->canAppeal())
        <div class="bg-zinc-50 dark:bg-zinc-800 rounded-lg p-4 text-left">
            <flux:text class="font-medium text-sm text-zinc-600 dark:text-zinc-400 uppercase tracking-wide mb-1">Appeal Available</flux:text>
            <flux:text class="text-zinc-800 dark:text-zinc-200">You may submit an appeal after <strong>{{ $user->next_appeal_available_at->setTimezone($user->timezone ?? 'UTC')->format('F j, Y \a\t g:i A T') }}</strong>.</flux:text>
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
                <flux:textarea wire:model.live="appealMessage" rows="6" placeholder="Explain why you believe the Brig status should be lifted..." />
                <flux:error name="appealMessage" />
            </flux:field>

            <div class="flex gap-2 justify-end">
                <flux:button variant="ghost" x-on:click="$flux.modal('brig-appeal-modal').close()">Cancel</flux:button>
                <flux:button wire:click="submitAppeal" wire:loading.attr="disabled" wire:target="submitAppeal" variant="primary">Submit Appeal</flux:button>
            </div>
        </div>
    </flux:modal>
</flux:card>