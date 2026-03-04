<x-mail::message>
@if($report->severity === \App\Enums\ReportSeverity::Trivial || $report->severity === \App\Enums\ReportSeverity::Minor)
Our staff team had a conversation with you that we've recorded for our records.
@else
A staff report has been recorded on your account.
@endif

**Severity:** {{ $report->severity->label() }}

**Location:** {{ $report->location->label() }}

**Description:** {{ $report->description }}

For more details about this report and any actions taken, please visit your profile on the Lighthouse website.

This report is part of our record-keeping to help maintain a positive community. If you have any questions, feel free to reach out to staff.

<x-mail::button :url="$profileUrl">
View Profile
</x-mail::button>
</x-mail::message>
