<x-mail::message>
@if($requiresApproval)
{{ $childName }} has requested an account on Lighthouse Minecraft, a Christian Minecraft community for youth.

Your approval is required before they can access the community. Because they are under 13, their account is currently on hold.

Create your own account to review and manage their permissions through the Parent Portal.
@else
{{ $childName }} has created an account on Lighthouse Minecraft, a Christian Minecraft community for youth.

As their parent or guardian, you can create your own account to manage their permissions, view their linked accounts, and monitor their activity through the Parent Portal.
@endif

<x-mail::button :url="$registerUrl">
Create Your Account
</x-mail::button>
</x-mail::message>
