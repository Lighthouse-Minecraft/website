<x-mail::message>
Your Lighthouse account has been placed in The Brig, meaning you have been temporarily suspended.

**Reason:** {{ $reason }}

@if($expiresAt)
**Appeal available after:** {{ $expiresAt->format('F j, Y \a\t g:i A T') }}

You will receive a notification when your appeal window opens.
@else
You may submit an appeal at any time via the dashboard.

<x-mail::button :url="$dashboardUrl">
    Go to Dashboard
</x-mail::button>
@endif

Your Minecraft server and Discord server accesses have been suspended during this period.
</x-mail::message>
