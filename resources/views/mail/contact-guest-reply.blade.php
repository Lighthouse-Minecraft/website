<x-mail::message>
Hi {{ $thread->guest_name ?? 'there' }},

A staff member has replied to your inquiry: **{{ preg_replace('/^\[[^\]]+\]\s*/', '', $thread->subject) }}**

---

{{ $message->body }}

---

To view the full conversation or reply, please use the link below. **Do not reply to this email** — replies sent to this email address will not be received.

<x-mail::button :url="$conversationUrl">
View Conversation
</x-mail::button>

Thanks,
{{ config('app.name') }}
</x-mail::message>
