<x-mail::message>
Hi {{ $guestName }},

Thank you for reaching out to Lighthouse MC. We've received your message and a staff member will get back to you soon.

**Subject:** {{ $subject }}

You can view your conversation and any replies using the link below. Replies from our team will also be sent to your email address.

<x-mail::button :url="url('/contact/thread/'.$conversationToken)">
View Conversation
</x-mail::button>

If you did not submit this inquiry, you can safely ignore this email.

Thanks,
{{ config('app.name') }}
</x-mail::message>
