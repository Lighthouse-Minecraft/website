<x-mail::message>
There is a new reply on a discussion you're part of.

**Subject:** {{ $thread->subject }}

**From:** {{ $fromName }}

**Message:** {{ $messagePreview }}

<x-mail::button :url="$topicUrl">
View Discussion
</x-mail::button>

Thank you!
</x-mail::message>
