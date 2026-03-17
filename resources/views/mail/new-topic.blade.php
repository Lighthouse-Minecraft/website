<x-mail::message>
A new discussion topic has been created that includes you.

**Subject:** {{ $thread->subject }}

**Started by:** {{ $thread->createdBy?->name ?? 'Unknown' }}

<x-mail::button :url="$topicUrl">
View Topic
</x-mail::button>

Thank you!
</x-mail::message>
