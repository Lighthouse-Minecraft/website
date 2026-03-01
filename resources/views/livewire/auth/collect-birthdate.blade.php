<?php

use App\Actions\PutUserInBrig;
use App\Actions\ReleaseUserFromBrig;
use App\Enums\BrigType;
use App\Notifications\ParentAccountNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public int $step = 1;
    public string $date_of_birth = '';
    public string $parent_email = '';

    public function submitDateOfBirth(): void
    {
        $this->validate([
            'date_of_birth' => ['required', 'date', 'before:today'],
        ]);

        $user = Auth::user();
        $age = \Carbon\Carbon::parse($this->date_of_birth)->age;

        if ($age >= 17) {
            $user->date_of_birth = $this->date_of_birth;
            $user->save();

            // If user was in age_lock brig, release them
            if ($user->isInBrig() && $user->brig_type === BrigType::AgeLock) {
                ReleaseUserFromBrig::run($user, $user, 'Date of birth verified (17+).');
            }

            $this->redirect(route('dashboard', absolute: false), navigate: true);
            return;
        }

        // Under 17 — need parent email
        $this->step = 2;
    }

    public function submitParentEmail(): void
    {
        $this->validate([
            'parent_email' => ['required', 'email', 'different:' . Auth::user()->email],
        ]);

        $user = Auth::user();
        $age = \Carbon\Carbon::parse($this->date_of_birth)->age;

        $user->date_of_birth = $this->date_of_birth;
        $user->parent_email = $this->parent_email;

        if ($age < 13) {
            $user->parent_allows_site = false;
            $user->parent_allows_minecraft = false;
            $user->parent_allows_discord = false;
            $user->save();

            if ($user->isInBrig() && $user->brig_type === BrigType::AgeLock) {
                // Transition from age_lock to parental_pending
                $user->brig_type = BrigType::ParentalPending;
                $user->brig_reason = 'Account pending parental approval (under 13).';
                $user->save();
            } elseif (! $user->isInBrig()) {
                PutUserInBrig::run(
                    target: $user,
                    admin: $user,
                    reason: 'Account pending parental approval (under 13).',
                    brigType: BrigType::ParentalPending,
                    notify: false,
                );
            }
        } else {
            // 13-16: parent toggles stay at defaults (all true)
            $user->save();

            if ($user->isInBrig() && $user->brig_type === BrigType::AgeLock) {
                ReleaseUserFromBrig::run($user, $user, 'Date of birth verified (13-16).');
            }
        }

        // Send parent notification
        $requiresApproval = $age < 13;
        Notification::route('mail', $this->parent_email)
            ->notify(new ParentAccountNotification($user, $requiresApproval));

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    @if($step === 1)
        <x-auth-header title="Date of Birth" description="Please enter your date of birth to continue." />

        <form wire:submit="submitDateOfBirth" class="flex flex-col gap-6">
            <flux:field>
                <flux:label>Date of Birth</flux:label>
                <flux:input wire:model="date_of_birth" type="date" required />
                <flux:error name="date_of_birth" />
            </flux:field>

            <flux:button type="submit" variant="primary" class="w-full">
                Continue
            </flux:button>
        </form>
    @else
        <x-auth-header title="Parent or Guardian Email" description="A parent or guardian email is required for users under 17." />

        <form wire:submit="submitParentEmail" class="flex flex-col gap-6">
            <flux:field>
                <flux:label>Parent/Guardian Email</flux:label>
                <flux:input wire:model="parent_email" type="email" required placeholder="parent@example.com" />
                <flux:error name="parent_email" />
                <flux:description>We'll send your parent information about your account and how to manage it.</flux:description>
            </flux:field>

            <flux:button type="submit" variant="primary" class="w-full">
                Submit
            </flux:button>
        </form>
    @endif
</div>
