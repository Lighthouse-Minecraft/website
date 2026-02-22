<div>
    <flux:heading class="mb-4">Meeting Notes</flux:heading>

    @if ($this->meetings->count() > 0)
        <flux:accordion exclusive>
            @foreach ($this->meetings as $meeting)
                @php
                    $departmentNote = $meeting->notes->first();
                @endphp

                <flux:accordion.item
                    wire:key="meeting-notes-{{ $meeting->id }}"
                    :heading="$meeting->title . ' - ' . $meeting->day"
                    :expanded="$loop->first"
                    transition
                >
                    <div class="space-y-4">
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $meeting->scheduled_time?->setTimezone('America/New_York')->format('M j, Y g:i A') ?? 'No date set' }}
                        </flux:text>

                        <flux:card>
                            <flux:heading size="sm">Department Notes</flux:heading>
                            @if($departmentNote)
                                <div class="mt-2 prose dark:prose-invert max-w-none">
                                    {!! nl2br(e($departmentNote->content)) !!}
                                </div>
                            @else
                                <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                                    No {{ ucfirst($sectionKey) }} notes were recorded for this meeting.
                                </flux:text>
                            @endif
                        </flux:card>

                        <flux:card>
                            <flux:heading size="sm">Full Meeting Minutes</flux:heading>
                            @if($meeting->minutes)
                                <div class="mt-2 prose dark:prose-invert max-w-none">
                                    {!! nl2br(e($meeting->minutes)) !!}
                                </div>
                            @else
                                <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                                    No full meeting minutes are available.
                                </flux:text>
                            @endif
                        </flux:card>
                    </div>
                </flux:accordion.item>
            @endforeach
        </flux:accordion>

        @if($this->meetings->hasPages())
            <div class="mt-6">
                {{ $this->meetings->links() }}
            </div>
        @endif
    @else
        <flux:card>
            <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                <flux:icon name="calendar-days" class="mx-auto h-12 w-12 mb-3" />
                <p>No meetings found.</p>
            </div>
        </flux:card>
    @endif
</div>
