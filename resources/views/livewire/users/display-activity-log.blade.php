<?php

use Livewire\Volt\Component;
use App\Models\User;
use App\Models\ActivityLog;

new class extends Component {
    public User $user;
    public $activities;

    public function mount(User $user) {
        $this->user = $user;

        $this->activities = ActivityLog::relevantTo($user)
            ->with(['causer', 'subject'])
            ->latest()
            ->get();
    }
}; ?>

<div>
    <flux:heading size="xl">User Activity Log</flux:heading>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Subject</flux:table.column>
            <flux:table.column>Action</flux:table.column>
            <flux:table.column>By User</flux:table.column>
            <flux:table.column>Details</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach($activities as $activity)
                <flux:table.row :key="$activity->id">
                    {{-- Subject --}}
                    <flux:table.cell>
                        @php
                            $subject = $activity->subject;
                        @endphp

                        @if ($subject instanceof \App\Models\User)
                            <flux:link href="{{ route('profile.show', $subject) }}">
                                {{ $subject->name }}
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
</div>
