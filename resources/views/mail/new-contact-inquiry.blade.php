<x-mail::message>
A new contact inquiry has been submitted.

**Subject:** {{ $thread->subject }}

**From:** {{ $thread->guest_name ?? 'Name not provided' }} ({{ $thread->guest_email }})

Thank you for your service!
</x-mail::message>
