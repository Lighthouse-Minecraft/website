<x-mail::message>
A discipline report has been filed regarding your child, **{{ $childName }}**.

**Severity:** {{ $report->severity->label() }}

**Location:** {{ $report->location->label() }}

**Description:** {{ $report->description }}

You can view more details about your child's account through your Parent Portal.

<x-mail::button :url="$portalUrl">
Go to Parent Portal
</x-mail::button>
</x-mail::message>
