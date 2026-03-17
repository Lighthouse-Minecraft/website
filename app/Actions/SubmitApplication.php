<?php

namespace App\Actions;

use App\Enums\ApplicationStatus;
use App\Enums\ReportSeverity;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\ActivityLog;
use App\Models\DisciplineReport;
use App\Models\StaffApplication;
use App\Models\StaffPosition;
use App\Models\User;
use App\Notifications\ApplicationStatusChangedNotification;
use App\Notifications\NewStaffApplicationNotification;
use App\Services\TicketNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class SubmitApplication
{
    use AsAction;

    public function handle(User $applicant, StaffPosition $position, array $answers): StaffApplication
    {
        if (! $position->accepting_applications) {
            throw new \RuntimeException('This position is not currently accepting applications.');
        }

        $existingPending = StaffApplication::where('user_id', $applicant->id)
            ->where('staff_position_id', $position->id)
            ->pending()
            ->exists();

        if ($existingPending) {
            throw new \RuntimeException('You already have a pending application for this position.');
        }

        return DB::transaction(function () use ($applicant, $position, $answers) {
            // Snapshot applicant background info
            $snapshot = $this->captureApplicantSnapshot($applicant);

            $application = StaffApplication::create([
                'user_id' => $applicant->id,
                'staff_position_id' => $position->id,
                'status' => ApplicationStatus::Submitted,
                ...$snapshot,
            ]);

            foreach ($answers as $questionId => $answer) {
                $application->answers()->create([
                    'application_question_id' => $questionId,
                    'answer' => $answer,
                ]);
            }

            // Create staff-only review discussion
            $systemUser = User::where('email', 'system@lighthouse.local')->first();
            if (! $systemUser) {
                Log::warning('System user (system@lighthouse.local) not found — skipping review discussion creation for application #'.$application->id);
            }
            if ($systemUser) {
                $reviewUrl = route('admin.applications.show', $application);
                $profileUrl = route('profile.show', $applicant);

                $initialMessage = implode("\n", [
                    '**New staff application received**',
                    "**Applicant:** [{$applicant->name}]({$profileUrl})",
                    "**Position:** {$position->title}",
                    "**Department:** {$position->department->label()}",
                    "**Rank:** {$position->rank->label()}",
                    '',
                    "[Review Application]({$reviewUrl})",
                ]);

                $thread = CreateTopic::run(
                    $application,
                    $systemUser,
                    "[STAFF ONLY] Application Review: {$applicant->name} for {$position->title}",
                    $initialMessage,
                );

                // Add relevant staff as participants (NOT the applicant)
                $staffUsers = User::where(function ($q) use ($position) {
                    // Command Officers
                    $q->where(function ($sub) {
                        $sub->where('staff_department', StaffDepartment::Command)
                            ->where('staff_rank', '>=', StaffRank::Officer->value);
                    })
                    // Department staff (JrCrew+)
                        ->orWhere(function ($sub) use ($position) {
                            $sub->where('staff_department', $position->department)
                                ->where('staff_rank', '>=', StaffRank::JrCrew->value);
                        });
                })
                    ->where('staff_rank', '!=', StaffRank::None->value)
                    ->where('id', '!=', $applicant->id)
                    ->where('id', '!=', $systemUser->id)
                    ->get();

                // Also add admins
                $admins = User::whereHas('roles', fn ($q) => $q->where('name', 'Admin'))
                    ->where('id', '!=', $applicant->id)
                    ->where('id', '!=', $systemUser->id)
                    ->get();

                foreach ($staffUsers->merge($admins)->unique('id') as $staffUser) {
                    $thread->addParticipant($staffUser);
                }

                $application->update(['staff_review_thread_id' => $thread->id]);
            }

            RecordActivity::run($application, 'application_submitted', "{$applicant->name} applied for {$position->title}.");

            // Notify applicant
            $notificationService = app(TicketNotificationService::class);
            $notificationService->send(
                $applicant,
                new ApplicationStatusChangedNotification($application, ApplicationStatus::Submitted),
                'staff_alerts',
            );

            // Notify Command Officers + Admins
            $reviewers = User::where(function ($q) {
                $q->where(function ($sub) {
                    $sub->where('staff_department', StaffDepartment::Command)
                        ->where('staff_rank', '>=', StaffRank::Officer->value);
                })
                    ->orWhereHas('roles', fn ($r) => $r->where('name', 'Admin'));
            })
                ->where('id', '!=', $applicant->id)
                ->get();

            foreach ($reviewers as $reviewer) {
                $notificationService->send(
                    $reviewer,
                    new NewStaffApplicationNotification($application),
                    'staff_alerts',
                );
            }

            return $application;
        });
    }

    private function captureApplicantSnapshot(User $applicant): array
    {
        // Age at submission
        $age = $applicant->date_of_birth
            ? $applicant->date_of_birth->age
            : null;

        // Last promotion date from activity log
        $lastPromotion = ActivityLog::where('subject_type', User::class)
            ->where('subject_id', $applicant->id)
            ->whereIn('action', ['user_promoted', 'user_promoted_to_admin'])
            ->orderBy('created_at', 'desc')
            ->first();

        // Published report counts
        $reportCount = DisciplineReport::forSubject($applicant)
            ->published()
            ->where('severity', '!=', ReportSeverity::Commendation)
            ->count();

        $commendationCount = DisciplineReport::forSubject($applicant)
            ->published()
            ->where('severity', ReportSeverity::Commendation)
            ->count();

        return [
            'applicant_age' => $age,
            'applicant_member_since' => $applicant->created_at->toDateString(),
            'applicant_membership_level' => $applicant->membership_level->label(),
            'applicant_membership_level_since' => $lastPromotion?->created_at?->toDateString(),
            'applicant_report_count' => $reportCount,
            'applicant_commendation_count' => $commendationCount,
        ];
    }
}
