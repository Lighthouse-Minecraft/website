<?php

namespace App\Actions;

use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Models\Meeting;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class GetIterationBoundaries
{
    use AsAction;

    public function handle(): array
    {
        return Cache::remember('command_dashboard.iteration_boundaries', now()->addHours(24), function () {
            $completedMeetings = Meeting::where('type', MeetingType::StaffMeeting)
                ->where('status', MeetingStatus::Completed)
                ->whereNotNull('end_time')
                ->orderByDesc('end_time')
                ->limit(7)
                ->get();

            $now = now();

            if ($completedMeetings->isEmpty()) {
                return [
                    'current_start' => $now->copy()->subDays(30),
                    'current_end' => $now,
                    'current_meeting' => null,
                    'previous_start' => null,
                    'previous_end' => null,
                    'previous_meeting' => null,
                    'has_previous' => false,
                    'iterations_3mo' => [],
                ];
            }

            $lastMeeting = $completedMeetings->first();
            $currentStart = $lastMeeting->end_time;
            $currentEnd = $now;

            $previousStart = null;
            $previousEnd = null;
            $previousMeeting = null;
            $hasPrevious = false;

            if ($completedMeetings->count() >= 2) {
                $secondLastMeeting = $completedMeetings->get(1);
                $previousStart = $secondLastMeeting->end_time;
                $previousEnd = $lastMeeting->end_time;
                $previousMeeting = $lastMeeting;
                $hasPrevious = true;
            }

            $threeMonthsAgo = $now->copy()->subMonths(3);
            $iterations3mo = [];

            for ($i = 0; $i < $completedMeetings->count() - 1; $i++) {
                $iterEnd = $completedMeetings->get($i)->end_time;
                $iterStart = $completedMeetings->get($i + 1)->end_time;

                if ($iterStart->lt($threeMonthsAgo)) {
                    break;
                }

                $iterations3mo[] = [
                    'start' => $iterStart,
                    'end' => $iterEnd,
                    'meeting' => $completedMeetings->get($i),
                ];
            }

            return [
                'current_start' => $currentStart,
                'current_end' => $currentEnd,
                'current_meeting' => null,
                'previous_start' => $previousStart,
                'previous_end' => $previousEnd,
                'previous_meeting' => $previousMeeting,
                'has_previous' => $hasPrevious,
                'iterations_3mo' => $iterations3mo,
            ];
        });
    }
}
