<x-mail::message>
Your Lighthouse account has been placed in the Brig by a staff member.

**Reason:** {{ $reason }}

@if($expiresAt)
**Appeal available after:** {{ $expiresAt->format('F j, Y \a\t g:i A T') }}

You will receive a notification when your appeal window opens.
@else
You may submit an appeal at any time via your dashboard.

<x-mail::button :url="$dashboardUrl">
Go to Dashboard
</x-mail::button>
@endif

Your Minecraft server access has been suspended during this period.
</x-mail::message>
