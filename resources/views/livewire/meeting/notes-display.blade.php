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

                        @if($meeting->isStaffMeeting() && $meeting->reports->isNotEmpty())
                            <flux:card>
                                <flux:heading size="sm">Staff Reports</flux:heading>
                                @foreach($meeting->reports as $report)
                                    <div class="mt-3 border-t border-zinc-200 dark:border-zinc-700 pt-3 first:border-t-0 first:pt-0">
                                        <strong class="text-sm">{{ $report->user->name }}</strong>
                                        @foreach($report->answers->sortBy(fn ($a) => $a->question->sort_order) as $answer)
                                            <div class="mt-1 ml-4">
                                                <em class="text-xs text-zinc-500 dark:text-zinc-400">{{ $answer->question->question_text }}</em>
                                                <p class="text-sm">{{ $answer->answer ?: 'No response' }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </flux:card>
                        @endif

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
