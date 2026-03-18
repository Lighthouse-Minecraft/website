<?php

use App\Actions\AutoLinkParentOnRegistration;
use App\Actions\LinkParentByEmail;
use App\Actions\PutUserInBrig;
use App\Actions\RecordActivity;
use App\Enums\BrigType;
use App\Models\SiteConfig;
use App\Models\User;
use App\Notifications\ParentAccountNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
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
    public string $registration_answer = '';

    /**
     * Handle step 1: validate account details + DOB, then proceed.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:32'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            'date_of_birth' => ['required', 'date', 'before:today'],
        ]);

        $age = \Carbon\Carbon::parse($this->date_of_birth)->age;
        $hasQuestion = ! empty(SiteConfig::getValue('registration_question'));

        if ($age >= 17 && ! $hasQuestion) {
            // Adult with no registration question — create immediately
            $this->createAccount();
            return;
        }

        // Under 17 (needs parent email) or has registration question — go to step 2
        $this->step = 2;
    }

    /**
     * Handle step 2: collect parent email and/or registration answer, then create account.
     */
    public function submitStep2(): void
    {
        $age = \Carbon\Carbon::parse($this->date_of_birth)->age;
        $hasQuestion = ! empty(SiteConfig::getValue('registration_question'));

        $rules = [
            'name' => ['required', 'string', 'max:32'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            'date_of_birth' => ['required', 'date', 'before:today'],
        ];

        if ($age < 17) {
            $rules['parent_email'] = ['required', 'email', Rule::notIn([$this->email])];
        }

        if ($hasQuestion) {
            $rules['registration_answer'] = ['required', 'string', 'min:3'];
        }

        $this->validate($rules);

        $this->createAccount();
    }

    /**
     * Create the user account with appropriate age handling.
     *
     * Under-13 users are not stored per COPPA — only the parent is notified.
     */
    private function createAccount(): void
    {
        $age = \Carbon\Carbon::parse($this->date_of_birth)->age;

        // COPPA: do not create an account for users under 13
        if ($age < 13) {
            Notification::route('mail', $this->parent_email)
                ->notify(new ParentAccountNotification($this->name, requiresApproval: true));

            $this->step = 3;

            return;
        }

        $questionText = SiteConfig::getValue('registration_question');

        $userData = [
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'date_of_birth' => $this->date_of_birth,
        ];

        if ($age < 17) {
            $userData['parent_email'] = $this->parent_email;
        }

        if (! empty($questionText) && ! empty($this->registration_answer)) {
            $userData['registration_question_text'] = $questionText;
            $userData['registration_answer'] = $this->registration_answer;
        }

        $user = User::create($userData);

        event(new Registered($user));
        RecordActivity::run($user, 'user_registered', 'User registered for an account');

        // Auto-link: check if this user's email matches any child's parent_email
        AutoLinkParentOnRegistration::run($user);

        // Auto-link: check if this child's parent_email matches an existing parent account
        LinkParentByEmail::run($user);

        // Send parent notification for minors (13-16)
        if ($age < 17 && ! empty($this->parent_email)) {
            Notification::route('mail', $this->parent_email)
                ->notify(new ParentAccountNotification($user, requiresApproval: false));
        }

        // Log in and redirect to dashboard
        Auth::login($user);
        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    @if($step === 3)
        <x-auth-header title="Almost There!" description="" />

        <flux:card class="text-center space-y-4">
            <flux:icon name="envelope" class="w-12 h-12 text-green-500 mx-auto" />
            <flux:heading size="lg">We've Emailed Your Parent</flux:heading>
            <flux:text>
                We've sent an email to your parent or guardian with instructions on how to create your account.
                Please ask them to check their email and follow the link to set up your account through the Parent Portal.
            </flux:text>
            <flux:text variant="subtle" class="text-sm">
                Once your parent creates their account, they will be able to set up your account for you.
            </flux:text>
        </flux:card>

        <div class="space-x-1 text-center text-sm text-zinc-600 dark:text-zinc-400">
            <x-text-link href="{{ route('login') }}">Return to Login</x-text-link>
        </div>
    @elseif($step === 1)
        <x-auth-header title="Create an account" description="Enter your details below to create your account" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form wire:submit="register" class="flex flex-col gap-6">
            <!-- Username -->
            <div class="grid gap-2">
                <flux:field>
                    <flux:label>Username</flux:label>
                    <flux:description>This will be your public display name on the site.</flux:description>
                    <flux:input wire:model="name" id="name" type="text" name="name" required autofocus autocomplete="name" placeholder="Online Nickname" maxlength="32" />
                    <flux:error name="name" />
                </flux:field>
            </div>

            <!-- Email Address -->
            <div class="grid gap-2">
                <flux:input wire:model="email" id="email" label="{{ __('Email address') }}" type="email" name="email" required autocomplete="email" placeholder="email@example.com" />
            </div>

            <!-- Date of Birth -->
            <div class="grid gap-2">
                <flux:field>
                    <flux:label>Date of Birth</flux:label>
                    <flux:description>We use your date of birth to ensure age-appropriate safety settings for our community.</flux:description>
                    <flux:input wire:model="date_of_birth" id="date_of_birth" type="date" name="date_of_birth" required />
                    <flux:error name="date_of_birth" />
                </flux:field>
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
    @else
        <x-auth-header title="Almost Done!" description="Just a couple more things before we create your account." />

        <form wire:submit="submitStep2" class="flex flex-col gap-6">
            @php
                $age = \Carbon\Carbon::parse($date_of_birth)->age;
                $questionText = \App\Models\SiteConfig::getValue('registration_question');
            @endphp

            @if($age < 17)
                <flux:field>
                    <flux:label>Parent/Guardian Email</flux:label>
                    <flux:input wire:model="parent_email" type="email" required placeholder="parent@example.com" />
                    <flux:error name="parent_email" />
                    <flux:description>We'll send your parent information about your account and how to manage it.</flux:description>
                </flux:field>
            @endif

            @if(! empty($questionText))
                <flux:field>
                    <flux:label>{{ $questionText }}</flux:label>
                    <flux:textarea wire:model="registration_answer" rows="3" required />
                    <flux:error name="registration_answer" />
                </flux:field>
            @endif

            <flux:button type="submit" variant="primary" class="w-full">
                Create account
            </flux:button>
        </form>

        <div class="space-x-1 text-center text-sm text-zinc-600 dark:text-zinc-400">
            Already have an account?
            <x-text-link href="{{ route('login') }}">Log in</x-text-link>
        </div>
    @endif
</div>
