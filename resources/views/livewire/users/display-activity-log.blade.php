<?php

use Livewire\Volt\Component;
use App\Models\User;
use App\Models\ActivityLog;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;
    
    public User $user;

    public function with(): array
    {
        return [
            'activities' => ActivityLog::relevantTo($this->user)
                ->with(['causer', 'subject'])
                ->latest()
                ->paginate(10),
        ];
    }
}; ?>

<div>
    <flux:heading size="xl">User Activity Log</flux:heading>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Date / Time</flux:table.column>
            <flux:table.column>Subject</flux:table.column>
            <flux:table.column>Action</flux:table.column>
            <flux:table.column>By User</flux:table.column>
            <flux:table.column>Details</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach($activities as $activity)
                <flux:table.row :key="$activity->id">
                    {{-- Date / Time (in viewer's stored timezone) --}}
                    @php
                        $tz = auth()->user()->timezone ?? 'UTC';
                        $localTime = $activity->created_at->setTimezone($tz);
                    @endphp
                    <flux:table.cell class="whitespace-nowrap text-sm text-zinc-500 dark:text-zinc-400"
                        title="{{ $localTime->format('Y-m-d H:i:s T') }}">
                        {{ $localTime->format('M j, Y') }}<br>
                        {{ $localTime->format('g:i A') }}
                    </flux:table.cell>

                    {{-- Subject --}}
                    <flux:table.cell>
                        @php
                            $subject = $activity->subject;
                        @endphp

                        @if ($subject instanceof User)
                            <flux:link href="{{ route('profile.show', $subject) }}">
                                {{ $subject->name }}
                            </flux:link>
                        @elseif ($subject instanceof \App\Models\Thread)
                            <flux:link href="/tickets/{{ $subject->id }}">
                                {{ $subject->subject }}
                            </flux:link>
                        @else
                            {{ class_basename($activity->subject_type) }} #{{ $activity->subject_id }}
                        @endif
                    </flux:table.cell>

                    {{-- Action (prettified) --}}
                    <flux:table.cell>
                        {{ Str::of($activity->action)->replace('_', ' ')->title() }}
                    </flux:table.cell>

                    {{-- Causer --}}
                    <flux:table.cell>
                        @if ($activity->causer)
                            <flux:link href="{{ route('profile.show', $activity->causer) }}">
                                {{ $activity->causer->name }}
                            </flux:link>
                        @else
                            <em>System</em>
                        @endif
                    </flux:table.cell>

                    {{-- Description --}}
                    <flux:table.cell>
                        {{ $activity->description }}
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <div class="mt-4">
        {{ $activities->links() }}
    </div>
</div>
