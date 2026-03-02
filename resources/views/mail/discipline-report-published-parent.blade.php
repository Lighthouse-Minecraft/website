<x-mail::message>
    A discipline report has been filed regarding your child, **{{ $childName }}**.

    **Severity:** {{ $report->severity->label() }}

    **Location:** {{ $report->location->label() }}

    **Description:** {{ $report->description }}

    You can view more details about this report and your child's account by visiting the Lighthouse MC Parent Portal.

    <x-mail::button :url="$portalUrl">
        Go to Parent Portal
    </x-mail::button>
</x-mail::message>
