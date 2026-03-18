<?php

use App\Enums\MessageKind;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Enums\ThreadStatus;
use App\Enums\ThreadType;
use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use App\Notifications\NewTopicNotification;
use App\Services\TicketNotificationService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;

new class extends Component {
    public string $appealMessage = '';

    /**
     * Submit the current user's brig appeal as a discussion.
     *
     * Creates a Discussion thread with the appeal message, auto-adds Command Officers+
     * and all Quartermasters as participants, sets a 7-day cooldown, and notifies staff.
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

        DB::transaction(function () use ($user, &$thread) {
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->firstOrFail();

            if (! $lockedUser->canAppeal()) {
                return;
            }

            $subject = $lockedUser->brig_type?->isParental()
                ? 'Staff Contact: '.$lockedUser->name
                : 'Brig Appeal: '.$lockedUser->name;

            $thread = Thread::create([
                'type' => ThreadType::Topic,
                'subject' => $subject,
                'status' => ThreadStatus::Open,
                'created_by_user_id' => $lockedUser->id,
                'last_message_at' => now(),
            ]);

            // Add the appealing user
            $thread->addParticipant($lockedUser);

            // Auto-add Command Officers+ and all Quartermasters
            $staffToAdd = User::where(function ($query) {
                $query->where(function ($q) {
                    // Command department, Officer rank or above
                    $q->where('staff_department', StaffDepartment::Command)
                      ->where('staff_rank', '>=', StaffRank::Officer->value);
                })->orWhere(function ($q) {
                    // All Quartermaster department members with a rank
                    $q->where('staff_department', StaffDepartment::Quartermaster)
                      ->whereNotNull('staff_rank');
                });
            })->where('id', '!=', $lockedUser->id)->get();

            foreach ($staffToAdd as $staff) {
                $thread->addParticipant($staff);
            }

            // System message with context
            $systemUser = User::where('email', 'system@lighthouse.local')->first();
            if ($systemUser) {
                $brigTypeLabel = $lockedUser->brig_type?->isParental() ? 'Staff Contact' : 'Brig Appeal';
                $lines = ["**{$brigTypeLabel} from {$lockedUser->name}**"];
                if ($lockedUser->brig_reason) {
                    $lines[] = "**Brig Reason:** {$lockedUser->brig_reason}";
                }
                $lines[] = "**Brig Type:** ".($lockedUser->brig_type?->label() ?? 'Discipline');

                Message::create([
                    'thread_id' => $thread->id,
                    'user_id' => $systemUser->id,
                    'body' => implode("\n", $lines),
                    'kind' => MessageKind::System,
                ]);
            }

            // User's appeal message
            Message::create([
                'thread_id' => $thread->id,
                'user_id' => $lockedUser->id,
                'body' => $this->appealMessage,
                'kind' => MessageKind::Message,
            ]);

            $activityDesc = $lockedUser->brig_type?->isParental()
                ? 'Staff contact discussion started: '.$thread->subject
                : 'Brig appeal discussion started: '.$thread->subject;
            \App\Actions\RecordActivity::run($thread, 'topic_created', $activityDesc);

            $lockedUser->next_appeal_available_at = now()->addDays(7);
            $lockedUser->save();
        });

        if ($thread === null) {
            Flux::toast('You cannot submit an appeal at this time.', 'Not Available', variant: 'danger');
            return;
        }

        // Notify all staff participants outside the transaction
        try {
            $notificationService = app(TicketNotificationService::class);
            $participants = $thread->participants()
                ->where('user_id', '!=', $user->id)
                ->with('user')
                ->get();

            foreach ($participants as $participant) {
                $notificationService->send($participant->user, new NewTopicNotification($thread));
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send brig appeal notifications', ['error' => $e->getMessage()]);
        }

        $this->appealMessage = '';
        Flux::modal('brig-appeal-modal')->close();

        $isParental = Auth::user()->brig_type?->isParental();
        Flux::toast(
            $isParental
                ? 'Your message has been sent to staff. You may send another in 7 days if needed.'
                : 'Your appeal has been submitted. You may submit another in 7 days if needed.',
            $isParental ? 'Message Sent' : 'Appeal Submitted',
            variant: 'success',
        );
    }
}; ?>

@php $user = Auth::user(); @endphp

<div>
@if($user->brig_type === \App\Enums\BrigType::ParentalPending)
    <flux:card class="w-full max-w-lg mx-auto text-center py-8 px-6 space-y-6">
        <div class="flex flex-col items-center gap-3">
            <flux:icon name="shield-check" class="w-12 h-12 text-amber-500" />
            <flux:heading size="xl">Account Pending Approval</flux:heading>
            <flux:badge color="amber" size="lg">Awaiting Parent</flux:badge>
        </div>

        <flux:text variant="subtle" class="text-sm">
            Your account requires parental approval. We've sent an email to your parent or guardian.
        </flux:text>
        <flux:text variant="subtle" class="text-sm">
            Once they create an account and approve your access, you'll be able to use the site.
        </flux:text>

        @if($user->canAppeal())
            <flux:modal.trigger name="brig-appeal-modal">
                <flux:button variant="primary" icon="chat-bubble-left-ellipsis">Contact Staff</flux:button>
            </flux:modal.trigger>
        @elseif($user->next_appeal_available_at)
            <div class="bg-zinc-50 dark:bg-zinc-800 rounded-lg p-4 text-left">
                <flux:text class="font-medium text-sm text-zinc-600 dark:text-zinc-400 uppercase tracking-wide mb-1">Next Contact Available</flux:text>
                <flux:text class="text-zinc-800 dark:text-zinc-200">You may contact staff again after <strong>{{ $user->next_appeal_available_at->setTimezone($user->timezone ?? 'UTC')->format('F j, Y \a\t g:i A T') }}</strong>.</flux:text>
            </div>
        @endif
    </flux:card>
@elseif($user->brig_type === \App\Enums\BrigType::ParentalDisabled)
    <flux:card class="w-full max-w-lg mx-auto text-center py-8 px-6 space-y-6">
        <div class="flex flex-col items-center gap-3">
            <flux:icon name="shield-check" class="w-12 h-12 text-orange-500" />
            <flux:heading size="xl">Account Restricted by Parent</flux:heading>
            <flux:badge color="orange" size="lg">Parent Restricted</flux:badge>
        </div>

        <flux:text variant="subtle" class="text-sm">
            Your parent or guardian has restricted your access to the site.
        </flux:text>
        <flux:text variant="subtle" class="text-sm">
            Please speak with your parent if you believe this is an error.
        </flux:text>

        @if($user->canAppeal())
            <flux:modal.trigger name="brig-appeal-modal">
                <flux:button variant="primary" icon="chat-bubble-left-ellipsis">Contact Staff</flux:button>
            </flux:modal.trigger>
        @elseif($user->next_appeal_available_at)
            <div class="bg-zinc-50 dark:bg-zinc-800 rounded-lg p-4 text-left">
                <flux:text class="font-medium text-sm text-zinc-600 dark:text-zinc-400 uppercase tracking-wide mb-1">Next Contact Available</flux:text>
                <flux:text class="text-zinc-800 dark:text-zinc-200">You may contact staff again after <strong>{{ $user->next_appeal_available_at->setTimezone($user->timezone ?? 'UTC')->format('F j, Y \a\t g:i A T') }}</strong>.</flux:text>
            </div>
        @endif
    </flux:card>
@elseif($user->brig_type === \App\Enums\BrigType::AgeLock)
    <flux:card class="w-full max-w-lg mx-auto text-center py-8 px-6 space-y-6">
        <div class="flex flex-col items-center gap-3">
            <flux:icon name="lock-closed" class="w-12 h-12 text-red-500" />
            <flux:heading size="xl">Account Locked</flux:heading>
            <flux:badge color="red" size="lg">Age Verification Required</flux:badge>
        </div>

        <flux:text variant="subtle" class="text-sm">
            Your account has been locked for age verification. Please update your date of birth to continue.
        </flux:text>

        <flux:button href="{{ route('birthdate.show') }}" variant="primary" icon="pencil-square">
            Update Date of Birth
        </flux:button>
    </flux:card>
@else
    {{-- Disciplinary brig (default / BrigType::Discipline / null) --}}
    <flux:card class="w-full max-w-lg mx-auto text-center py-8 px-6 space-y-6">
        <div class="flex flex-col items-center gap-3">
            <flux:icon name="lock-closed" class="w-12 h-12 text-red-500" />
            <flux:heading size="xl">You Are In the Brig</flux:heading>
            <flux:badge color="red" size="lg">Access Restricted</flux:badge>
        </div>

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

    </flux:card>
@endif

{{-- Shared contact/appeal modal — available to discipline and parental brig types --}}
@if($user->brig_type?->isDisciplinary() || $user->brig_type?->isParental() || $user->brig_type === null)
<flux:modal name="brig-appeal-modal" class="w-full lg:w-1/2">
    <div class="space-y-6">
        @if($user->brig_type?->isParental())
            <flux:heading size="lg">Contact Staff</flux:heading>
            <flux:text variant="subtle">Send a message to the staff team. Be respectful and honest. Staff will review your message and respond in the discussion.</flux:text>
        @else
            <flux:heading size="lg">Submit Brig Appeal</flux:heading>
            <flux:text variant="subtle">Explain your situation to the staff team. Be respectful and honest. Staff will review your appeal and respond in the discussion.</flux:text>
        @endif

        <flux:field>
            <flux:label>Your Message <span class="text-red-500">*</span></flux:label>
            <flux:textarea wire:model.live="appealMessage" rows="6" placeholder="{{ $user->brig_type?->isParental() ? 'Describe your situation or question for staff...' : 'Explain why you believe the Brig status should be lifted...' }}" />
            <flux:error name="appealMessage" />
        </flux:field>

        <div class="flex gap-2 justify-end">
            <flux:button variant="ghost" x-on:click="$flux.modal('brig-appeal-modal').close()">Cancel</flux:button>
            <flux:button wire:click="submitAppeal" wire:loading.attr="disabled" wire:target="submitAppeal" variant="primary">
                {{ $user->brig_type?->isParental() ? 'Send Message' : 'Submit Appeal' }}
            </flux:button>
        </div>
    </div>
</flux:modal>
@endif
</div>