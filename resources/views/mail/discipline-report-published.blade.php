<x-mail::message>
A discipline report has been filed on your account.

**Severity:** {{ $report->severity->label() }}

**Location:** {{ $report->location->label() }}

**Description:** {{ $report->description }}

For more details about this report and any actions taken, please visit your profile on the Lighthouse website.

Please be aware that continued violations of our community guidelines may result in further disciplinary actions, including temporary or permanent suspension of your account.

<x-mail::button :url="$profileUrl">
View Profile
</x-mail::button>
</x-mail::message>
