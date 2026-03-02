<x-mail::message>
A new discipline report has been submitted by {{ $report->reporter->name }} and needs review.

**Subject:** {{ $report->subject->name }}

**Severity:** {{ $report->severity->label() }}

**Location:** {{ $report->location->label() }}

<x-mail::button :url="$profileUrl">
View Report
</x-mail::button>
</x-mail::message>
