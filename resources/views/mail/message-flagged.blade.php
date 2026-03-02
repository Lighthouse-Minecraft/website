<x-mail::message>
A message has been flagged and requires review.

**Original Ticket:** {{ $thread->subject }}

**Flagged by:** {{ $flaggedByName }}

**Reason:** {{ $reason }}

<x-mail::button :url="$reviewUrl">
Review Flag
</x-mail::button>

Thank you for your service!
</x-mail::message>
