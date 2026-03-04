<x-mail::message>
@if($report->severity === \App\Enums\ReportSeverity::Trivial || $report->severity === \App\Enums\ReportSeverity::Minor)
Our staff team had a conversation with your child, **{{ $childName }}**, that we've recorded for our records.
@else
A staff report has been recorded regarding your child, **{{ $childName }}**.
@endif

**Severity:** {{ $report->severity->label() }}

**Location:** {{ $report->location->label() }}

**Description:** {{ $report->description }}

You can view more details about this report and your child's account by visiting the Lighthouse MC Parent Portal.

<x-mail::button :url="$portalUrl">
Go to Parent Portal
</x-mail::button>
</x-mail::message>
