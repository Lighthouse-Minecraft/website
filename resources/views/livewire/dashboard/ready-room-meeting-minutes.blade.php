<div>
    <flux:card>
        <flux:heading class="mb-4">Staff Meeting Minutes</flux:heading>

        @if($this->meetings->count() > 0)
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Meeting</flux:table.column>
                    <flux:table.column>Date</flux:table.column>
                    <flux:table.column>Officers</flux:table.column>
                    <flux:table.column>Attendance</flux:table.column>
                    <flux:table.column>Minutes</flux:table.column>
                    <flux:table.column>Archived Tasks</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach($this->meetings as $meeting)
                        @php
                            $attendees = $meeting->attendees;
                            $presentCount = $attendees->where('pivot.attended', true)->count();
                            $totalCount = $attendees->count();
                            $officerAttendees = $attendees->where('staff_rank', \App\Enums\StaffRank::Officer);
                            $officersPresent = $officerAttendees->where('pivot.attended', true)->count();
                            $officersTotal = $officerAttendees->count();
                        @endphp
                        <flux:table.row wire:key="meeting-row-{{ $meeting->id }}">
                            <flux:table.cell>{{ $meeting->title }}</flux:table.cell>
                            <flux:table.cell>{{ \Carbon\Carbon::parse($meeting->day)->format('M j, Y') }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$officersPresent === $officersTotal ? 'green' : 'amber'">
                                    {{ $officersPresent }}/{{ $officersTotal }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:button size="xs" variant="ghost" wire:click="showAttendance({{ $meeting->id }})">
                                    {{ $presentCount }}/{{ $totalCount }} present
                                </flux:button>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if($meeting->minutes)
                                    <flux:button size="xs" variant="ghost" icon="document-text" wire:click="showMinutes({{ $meeting->id }})">
                                        View
                                    </flux:button>
                                @else
                                    <flux:text variant="subtle" class="text-xs">None</flux:text>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @if($meeting->archived_tasks_count > 0)
                                    <flux:button size="xs" variant="ghost" icon="archive-box" wire:click="showArchivedTasks({{ $meeting->id }})">
                                        {{ $meeting->archived_tasks_count }}
                                    </flux:button>
                                @else
                                    <flux:text variant="subtle" class="text-xs">0</flux:text>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            @if($this->meetings->hasPages())
                <div class="mt-4">
                    {{ $this->meetings->links() }}
                </div>
            @endif
        @else
            <flux:text variant="subtle" class="text-sm">No completed staff meetings found.</flux:text>
        @endif
    </flux:card>

    {{-- Attendance Modal --}}
    <flux:modal name="meeting-attendance-modal" class="min-w-[36rem] !text-left">
        @if($this->viewingMeeting)
            @php $vm = $this->viewingMeeting; @endphp
            <div class="space-y-4">
                <flux:heading size="lg">Attendance &mdash; {{ $vm->title }}</flux:heading>
                <flux:text variant="subtle" class="text-sm">{{ \Carbon\Carbon::parse($vm->day)->format('M j, Y') }}</flux:text>

                @php
                    $grouped = $vm->attendees->groupBy('staff_department');
                @endphp

                @foreach(\App\Enums\StaffDepartment::cases() as $dept)
                    @if($grouped->has($dept->value))
                        @php $deptMembers = $grouped[$dept->value]->sortByDesc('staff_rank'); @endphp
                        <div>
                            <flux:text class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">{{ $dept->label() }}</flux:text>
                            <div class="space-y-1">
                                @foreach($deptMembers as $member)
                                    <div class="flex items-center gap-2 py-1">
                                        @if($member->pivot->attended)
                                            <flux:icon name="check" variant="micro" class="w-4 h-4 text-green-500 shrink-0" />
                                        @else
                                            <flux:icon name="x-mark" variant="micro" class="w-4 h-4 text-red-400 shrink-0" />
                                        @endif
                                        <flux:avatar size="xs" :src="$member->avatarUrl()" />
                                        <flux:text class="text-sm">{{ $member->name }}</flux:text>
                                        @if($member->staff_rank === \App\Enums\StaffRank::Officer)
                                            <flux:badge size="sm" color="emerald">Officer</flux:badge>
                                        @endif
                                        @if(! $member->pivot->attended)
                                            <flux:badge size="sm" color="red">Absent</flux:badge>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach

                <div class="text-right">
                    <flux:modal.close>
                        <flux:button variant="ghost">Close</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- Minutes Modal --}}
    <flux:modal name="meeting-minutes-modal" class="min-w-[64rem] max-w-full !text-left">
        @if($this->viewingMeeting)
            @php $vm = $this->viewingMeeting; @endphp
            <div class="space-y-4">
                <flux:heading size="lg">Meeting Minutes &mdash; {{ $vm->title }}</flux:heading>
                <flux:text variant="subtle" class="text-sm">{{ \Carbon\Carbon::parse($vm->day)->format('M j, Y') }}</flux:text>

                @if($vm->minutes)
                    <div class="prose dark:prose-invert max-w-none">
                        {!! nl2br(e($vm->minutes)) !!}
                    </div>
                @else
                    <flux:text variant="subtle" class="text-sm">No meeting minutes available.</flux:text>
                @endif

                <div class="text-right">
                    <flux:modal.close>
                        <flux:button variant="ghost">Close</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- Archived Tasks Modal --}}
    <flux:modal name="meeting-archived-tasks-modal" class="min-w-[36rem] !text-left">
        @if($this->viewingMeeting)
            @php
                $vm = $this->viewingMeeting;
                $tasksByDept = $vm->archivedTasks->groupBy('section_key');
            @endphp
            <div class="space-y-4">
                <flux:heading size="lg">Archived Tasks &mdash; {{ $vm->title }}</flux:heading>
                <flux:text variant="subtle" class="text-sm">{{ \Carbon\Carbon::parse($vm->day)->format('M j, Y') }}</flux:text>

                @foreach(\App\Enums\StaffDepartment::cases() as $dept)
                    @if($tasksByDept->has($dept->value))
                        <div>
                            <flux:text class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">{{ $dept->label() }}</flux:text>
                            <div class="space-y-1">
                                @foreach($tasksByDept[$dept->value] as $task)
                                    <div wire:key="archived-task-{{ $task->id }}" class="flex items-center gap-2 py-1 px-2 rounded border border-zinc-200 dark:border-zinc-700">
                                        <flux:icon name="archive-box" variant="micro" class="w-4 h-4 text-zinc-400 shrink-0" />
                                        <flux:text class="text-sm">{{ $task->name }}</flux:text>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach

                @if($tasksByDept->isEmpty())
                    <flux:text variant="subtle" class="text-sm">No tasks were archived during this meeting.</flux:text>
                @endif

                <div class="text-right">
                    <flux:modal.close>
                        <flux:button variant="ghost">Close</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
