<?php

use App\Actions\AutoLinkParentOnRegistration;
use App\Actions\PutUserInBrig;
use App\Actions\RecordActivity;
use App\Enums\BrigType;
use App\Models\User;
use App\Notifications\ParentAccountNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public int $step = 1;
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $date_of_birth = '';
    public string $parent_email = '';

    /**
     * Handle step 1: validate account details + DOB, then proceed.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            'date_of_birth' => ['required', 'date', 'before:today'],
        ]);

        $age = \Carbon\Carbon::parse($this->date_of_birth)->age;

        if ($age >= 17) {
            $this->createAccount();
            return;
        }

        // Under 17 — need parent email
        $this->step = 2;
    }

    /**
     * Handle step 2: collect parent email and create account.
     */
    public function submitParentEmail(): void
    {
        $this->validate([
            'parent_email' => ['required', 'email', 'different:email'],
        ]);

        $this->createAccount();
    }

    /**
     * Create the user account with appropriate age handling.
     */
    private function createAccount(): void
    {
        $age = \Carbon\Carbon::parse($this->date_of_birth)->age;

        $userData = [
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'date_of_birth' => $this->date_of_birth,
        ];

        if ($age < 17) {
            $userData['parent_email'] = $this->parent_email;
        }

        if ($age < 13) {
            $userData['parent_allows_site'] = false;
            $userData['parent_allows_minecraft'] = false;
            $userData['parent_allows_discord'] = false;
        }

        $user = User::create($userData);

        event(new Registered($user));
        RecordActivity::run($user, 'user_registered', 'User registered for an account');

        // Auto-link parent if they already have an account
        AutoLinkParentOnRegistration::run($user);

        // Send parent notification for minors
        if ($age < 17 && ! empty($this->parent_email)) {
            $requiresApproval = $age < 13;
            Notification::route('mail', $this->parent_email)
                ->notify(new ParentAccountNotification($user, $requiresApproval));
        }

        // Under 13: put in brig, show confirmation, do NOT log in
        if ($age < 13) {
            PutUserInBrig::run(
                target: $user,
                admin: $user,
                reason: 'Account pending parental approval (under 13).',
                brigType: BrigType::ParentalPending,
                notify: false,
            );

            $this->step = 3;
            return;
        }

        // 13+ : log in and redirect
        Auth::login($user);
        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    @if($step === 1)
        <x-auth-header title="Create an account" description="Enter your details below to create your account" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form wire:submit="register" class="flex flex-col gap-6">
            <!-- Name -->
            <div class="grid gap-2">
                <flux:input wire:model="name" id="name" label="{{ __('Name') }}" type="text" name="name" required autofocus autocomplete="name" placeholder="Online Nickname" />
            </div>

            <!-- Email Address -->
            <div class="grid gap-2">
                <flux:input wire:model="email" id="email" label="{{ __('Email address') }}" type="email" name="email" required autocomplete="email" placeholder="email@example.com" />
            </div>

            <!-- Date of Birth -->
            <div class="grid gap-2">
                <flux:input wire:model="date_of_birth" id="date_of_birth" label="Date of Birth" type="date" name="date_of_birth" required />
            </div>

            <!-- Password -->
            <div class="grid gap-2">
                <flux:input
                    wire:model="password"
                    id="password"
                    label="{{ __('Password') }}"
                    type="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    placeholder="Password"
                />
            </div>

            <!-- Confirm Password -->
            <div class="grid gap-2">
                <flux:input
                    wire:model="password_confirmation"
                    id="password_confirmation"
                    label="{{ __('Confirm password') }}"
                    type="password"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="Confirm password"
                />
            </div>

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full">
                    {{ __('Create account') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 text-center text-sm text-zinc-600 dark:text-zinc-400">
            Already have an account?
            <x-text-link href="{{ route('login') }}">Log in</x-text-link>
        </div>
    @elseif($step === 2)
        <x-auth-header title="Parent or Guardian Email" description="A parent or guardian email is required for users under 17." />

        <form wire:submit="submitParentEmail" class="flex flex-col gap-6">
            <flux:field>
                <flux:label>Parent/Guardian Email</flux:label>
                <flux:input wire:model="parent_email" type="email" required placeholder="parent@example.com" />
                <flux:error name="parent_email" />
                <flux:description>We'll send your parent information about your account and how to manage it.</flux:description>
            </flux:field>

            <flux:button type="submit" variant="primary" class="w-full">
                Create account
            </flux:button>
        </form>

        <div class="space-x-1 text-center text-sm text-zinc-600 dark:text-zinc-400">
            Already have an account?
            <x-text-link href="{{ route('login') }}">Log in</x-text-link>
        </div>
    @else
        {{-- Step 3: Under-13 confirmation --}}
        <x-auth-header title="Account Created" description="We've sent an email to your parent or guardian." />

        <flux:card class="text-center py-6 space-y-4">
            <flux:icon name="shield-check" class="w-12 h-12 text-amber-500 mx-auto" />
            <flux:text>Your account has been created, but it requires parental approval before you can use the site.</flux:text>
            <flux:text variant="subtle" class="text-sm">Once your parent creates an account and approves your access, you'll be able to log in.</flux:text>
        </flux:card>

        <div class="text-center">
            <x-text-link href="{{ route('login') }}">Back to login</x-text-link>
        </div>
    @endif
</div>
