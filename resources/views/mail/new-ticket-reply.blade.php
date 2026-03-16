<x-mail::message>
There is a new reply on a ticket you're following.

**Subject:** {{ $thread->subject }}

**From:** {{ $fromName }}

**Message:** {{ $messagePreview }}

@if($hasImage ?? false)
*An image was attached to this reply.*
@endif

<x-mail::button :url="$ticketUrl">
View Ticket
</x-mail::button>

Thank you for your service!
</x-mail::message>
