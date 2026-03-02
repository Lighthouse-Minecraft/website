<x-mail::message>
    Here's a summary of ticket activity:

    @foreach($displayedTickets as $ticket)
        - **{{ $ticket['subject'] }}** ({{ $ticket['count'] }} {{ $ticket['count'] === 1 ? 'update' : 'updates' }})
    @endforeach

    @if($remainingCount > 0)
        ...and {{ $remainingCount }} more {{ $remainingCount === 1 ? 'ticket' : 'tickets' }}
    @endif

    <x-mail::button :url="$ticketsUrl">
        View All Tickets
    </x-mail::button>

    Thank you for your service!
</x-mail::message>
