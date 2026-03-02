<x-mail::message>
    {{ $newStowawayName }} has agreed to the rules and is awaiting review to be promoted to Traveler.

    Please review their profile and promote or manage their account as appropriate.

    <x-mail::button :url="$profileUrl">
        View Profile
    </x-mail::button>

    Thank you for your service!
</x-mail::message>
