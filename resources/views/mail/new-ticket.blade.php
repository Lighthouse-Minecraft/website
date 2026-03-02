<x-mail::message>
    A new ticket has been created in your department.

    **Subject:** {{ $thread->subject }}

    **Department:** {{ $thread->department->label() }}

    **From:** {{ $thread->createdBy->name }}

    <x-mail::button :url="$ticketUrl">
        View Ticket
    </x-mail::button>

    Thank you for your service!
</x-mail::message>
