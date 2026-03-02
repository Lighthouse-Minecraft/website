<x-mail::message>
A discipline report has been filed.

**Severity:** {{ $report->severity->label() }}

**Location:** {{ $report->location->label() }}

**Description:** {{ $report->description }}

<x-mail::button :url="$profileUrl">
View Profile
</x-mail::button>
</x-mail::message>
