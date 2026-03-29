<x-mail::message>
A ticket has gone unassigned past the expected response window.

**Subject:** {{ $thread->subject }}

**Department:** {{ $thread->department?->label() ?? 'Unknown' }}

**From:** {{ $thread->createdBy?->name ?? 'Unknown' }}

<x-mail::button :url="$ticketUrl">
View Ticket
</x-mail::button>

Thank you for your service!
</x-mail::message>
