<x-mail::message>
A ticket has been assigned to you.

**Subject:** {{ $thread->subject }}

**Department:** {{ $thread->department->label() }}

@if($assignedToName)
**Assigned to:** {{ $assignedToName }}
@endif

<x-mail::button :url="$ticketUrl">
View Ticket
</x-mail::button>

Thank you for your service!
</x-mail::message>
