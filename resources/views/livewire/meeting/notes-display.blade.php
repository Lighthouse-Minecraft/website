<div>
    <flux:heading class="mb-4">Meeting Notes</flux:heading>

    <div class="flex flex-col lg:flex-row gap-6">
        {{-- Small card: List of Meetings --}}
        <div class="w-full lg:w-1/4">
            <flux:card>
                <flux:heading size="lg" class="mb-4">Meetings</flux:heading>

                @if ($this->meetings->count() > 0)
                    <div class="space-y-2">
                        @foreach ($this->meetings as $meeting)
                            <div
                                wire:click="selectMeeting({{ $meeting->id }})"
                                class="p-3 rounded-lg cursor-pointer transition-colors hover:bg-gray-50 dark:hover:bg-gray-800
                                    @if($selectedMeetingId === $meeting->id)
                                        bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800
                                    @else
                                        border-gray-200 dark:border-gray-700
                                    @endif
                                    border"
                            >
                                <div class="font-medium text-sm text-gray-900 dark:text-gray-100">
                                    {{ $meeting->title }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    {{ $meeting->scheduled_time?->format('M j, Y g:i A') ?? 'No date set' }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                        <flux:icon name="calendar-days" class="mx-auto h-12 w-12 mb-3" />
                        <p>No meetings found for this department.</p>
                    </div>
                @endif
            </flux:card>
        </div>

        {{-- Large card: Meeting Note Content --}}
        <div class="w-full lg:w-3/4">
            <flux:card>
                @if ($selectedMeetingNote)
                    <flux:heading size="lg" class="mb-4">{{ $meeting->title }} - {{ $meeting->day }}</flux:heading>

                    <div class="prose dark:prose-invert max-w-none">
                        {!! nl2br(e($selectedMeetingNote->content)) !!}
                    </div>
                @elseif ($selectedMeetingId)
                    <div class="text-center py-16 text-gray-500 dark:text-gray-400">
                        <flux:icon name="document-text" class="mx-auto h-12 w-12 mb-3" />
                        <flux:heading size="lg" class="mb-2">No Notes Available</flux:heading>
                        <p>No meeting notes found for this department and selected meeting.</p>
                    </div>
                @else
                    <div class="text-center py-16 text-gray-500 dark:text-gray-400">
                        <flux:icon name="cursor-arrow-rays" class="mx-auto h-12 w-12 mb-3" />
                        <flux:heading size="lg" class="mb-2">Select a Meeting</flux:heading>
                        <p>Choose a meeting from the list to view its notes.</p>
                    </div>
                @endif
            </flux:card>
        </div>
    </div>
</div>
