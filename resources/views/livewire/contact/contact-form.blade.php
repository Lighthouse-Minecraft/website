<?php

use App\Actions\CreateContactInquiry;
use Flux\Flux;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app.sidebar')] class extends Component {
    public string $name = '';
    public string $email = '';
    public string $category = '';
    public string $subject = '';
    public string $message = '';
    public string $honeypot = '';
    public string $hcaptchaToken = '';

    public bool $submitted = false;

    public function categories(): array
    {
        return [
            'General Inquiry',
            'Membership / Joining',
            'Parent / Guardian Question',
            'Report a Concern',
            'Donation / Support',
            'Technical Issue',
        ];
    }

    public function hcaptchaSiteKey(): ?string
    {
        return config('services.hcaptcha.site_key') ?: null;
    }

    public function submit(): void
    {
        // Honeypot check — bots fill hidden fields
        if ($this->honeypot !== '') {
            $this->submitted = true;
            return;
        }

        // IP rate limiting
        $key = 'contact-form:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('email', 'Too many submissions. Please try again later.');
            return;
        }

        $this->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'category' => ['required', 'string', 'in:'.implode(',', $this->categories())],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'min:10'],
        ]);

        // hCaptcha verification (only when keys are configured)
        $siteKey = config('services.hcaptcha.site_key');
        $secretKey = config('services.hcaptcha.secret_key');

        if ($siteKey && $secretKey) {
            if (empty($this->hcaptchaToken)) {
                $this->addError('hcaptchaToken', 'Please complete the captcha.');
                return;
            }

            $response = \Illuminate\Support\Facades\Http::asForm()->post('https://hcaptcha.com/siteverify', [
                'secret' => $secretKey,
                'response' => $this->hcaptchaToken,
            ]);

            if (! ($response->json('success') ?? false)) {
                $this->addError('hcaptchaToken', 'Captcha verification failed. Please try again.');
                return;
            }
        }

        RateLimiter::hit($key, 3600);

        CreateContactInquiry::run(
            name: $this->name,
            email: $this->email,
            category: $this->category,
            subject: $this->subject,
            body: $this->message,
        );

        $this->submitted = true;
    }
}; ?>

<x-slot name="header">
    <flux:heading>Contact Us</flux:heading>
</x-slot>

<div class="max-w-2xl mx-auto py-8 px-4">
    @if($submitted)
        <flux:card>
            <flux:heading size="lg">Message Received!</flux:heading>
            <flux:subheading class="mt-2">
                Thank you for reaching out. We've sent a confirmation to your email address with a link to track your conversation. A staff member will respond soon.
            </flux:subheading>
        </flux:card>
    @else
        <flux:card>
            <flux:heading size="lg">Send Us a Message</flux:heading>
            <flux:subheading class="mt-1 mb-6">
                Have a question or concern? Fill out the form below and our team will get back to you.
            </flux:subheading>

            <form wire:submit="submit">
                {{-- Honeypot --}}
                <div style="display:none" aria-hidden="true">
                    <input type="text" wire:model="honeypot" name="website" tabindex="-1" autocomplete="off">
                </div>

                <div class="space-y-4">
                    <flux:input
                        wire:model="name"
                        label="Name (optional)"
                        placeholder="Your name"
                    />

                    <flux:input
                        wire:model="email"
                        label="Email"
                        type="email"
                        placeholder="your@email.com"
                        required
                    />

                    <flux:select wire:model="category" label="Category" required>
                        <flux:select.option value="" disabled selected>Select a category...</flux:select.option>
                        @foreach($this->categories() as $cat)
                            <flux:select.option :value="$cat">{{ $cat }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @error('category') <flux:error>{{ $message }}</flux:error> @enderror

                    <flux:input
                        wire:model="subject"
                        label="Subject"
                        placeholder="Brief description of your inquiry"
                        required
                    />

                    <flux:textarea
                        wire:model="message"
                        label="Message"
                        placeholder="Please describe your question or concern in detail..."
                        rows="6"
                        required
                    />

                    @if($this->hcaptchaSiteKey())
                        <div>
                            <div
                                class="h-captcha"
                                data-sitekey="{{ $this->hcaptchaSiteKey() }}"
                                data-callback="onHcaptchaSuccess"
                            ></div>
                            @error('hcaptchaToken') <flux:error>{{ $message }}</flux:error> @enderror
                            <script>
                                function onHcaptchaSuccess(token) {
                                    @this.set('hcaptchaToken', token);
                                }
                            </script>
                            <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
                        </div>
                    @endif

                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                        <span wire:loading.remove>Send Message</span>
                        <span wire:loading>Sending...</span>
                    </flux:button>
                </div>
            </form>
        </flux:card>
    @endif
</div>
