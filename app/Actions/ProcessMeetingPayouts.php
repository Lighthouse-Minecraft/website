<?php

namespace App\Actions;

use App\Enums\StaffRank;
use App\Models\Meeting;
use App\Models\MeetingPayout;
use App\Models\SiteConfig;
use App\Models\User;
use App\Services\MinecraftRconService;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessMeetingPayouts
{
    use AsAction;

    public function handle(Meeting $meeting, array $excludedUserIds = []): void
    {
        $payoutAmounts = [
            StaffRank::JrCrew->value => max(0, (int) SiteConfig::getValue('meeting_payout_jr_crew', '0')),
            StaffRank::CrewMember->value => max(0, (int) SiteConfig::getValue('meeting_payout_crew_member', '0')),
            StaffRank::Officer->value => max(0, (int) SiteConfig::getValue('meeting_payout_officer', '0')),
        ];

        $submittedUserIds = $meeting->reports()
            ->whereNotNull('submitted_at')
            ->pluck('user_id')
            ->toArray();

        $attendees = $meeting->attendees()->get();

        // Jr Crew don't attend meetings but are still eligible for payouts if
        // they submitted their Staff Update Report. Include them alongside attendees.
        $attendeeIds = $attendees->pluck('id');
        $jrCrewSubmitters = User::where('staff_rank', StaffRank::JrCrew->value)
            ->whereNotIn('id', $attendeeIds)
            ->whereIn('id', $submittedUserIds)
            ->get();

        $allParticipants = $attendees->concat($jrCrewSubmitters);

        $paidCount = 0;
        $skippedCount = 0;
        $failedCount = 0;
        $pendingCount = 0;

        foreach ($allParticipants as $attendee) {
            // Skip if payout record already exists (duplicate prevention).
            // Count pending records so the activity log surfaces interrupted payouts.
            $existingPayout = MeetingPayout::where('meeting_id', $meeting->id)->where('user_id', $attendee->id)->first();
            if ($existingPayout !== null) {
                if ($existingPayout->status === 'pending') {
                    $pendingCount++;
                }

                continue;
            }

            $rank = $attendee->staff_rank;

            // Unknown/no rank — skip
            if ($rank === null || $rank === StaffRank::None) {
                MeetingPayout::create([
                    'meeting_id' => $meeting->id,
                    'user_id' => $attendee->id,
                    'amount' => 0,
                    'status' => 'skipped',
                    'skip_reason' => 'No staff rank',
                ]);
                $skippedCount++;

                continue;
            }

            $amount = $payoutAmounts[$rank->value] ?? 0;

            // Rank payout disabled
            if ($amount <= 0) {
                MeetingPayout::create([
                    'meeting_id' => $meeting->id,
                    'user_id' => $attendee->id,
                    'amount' => 0,
                    'status' => 'skipped',
                    'skip_reason' => 'Rank payout disabled',
                ]);
                $skippedCount++;

                continue;
            }

            // Excluded by manager
            if (in_array($attendee->id, $excludedUserIds)) {
                MeetingPayout::create([
                    'meeting_id' => $meeting->id,
                    'user_id' => $attendee->id,
                    'amount' => $amount,
                    'status' => 'skipped',
                    'skip_reason' => 'Excluded by manager',
                ]);
                $skippedCount++;

                continue;
            }

            // Form not submitted
            if (! in_array($attendee->id, $submittedUserIds)) {
                MeetingPayout::create([
                    'meeting_id' => $meeting->id,
                    'user_id' => $attendee->id,
                    'amount' => $amount,
                    'status' => 'skipped',
                    'skip_reason' => 'Form not submitted',
                ]);
                $skippedCount++;

                continue;
            }

            // Officers must also have attended
            if ($rank === StaffRank::Officer && ! $attendee->pivot->attended) {
                MeetingPayout::create([
                    'meeting_id' => $meeting->id,
                    'user_id' => $attendee->id,
                    'amount' => $amount,
                    'status' => 'skipped',
                    'skip_reason' => 'Did not attend',
                ]);
                $skippedCount++;

                continue;
            }

            // No primary Minecraft account
            $mcAccount = $attendee->primaryMinecraftAccount();
            if ($mcAccount === null) {
                MeetingPayout::create([
                    'meeting_id' => $meeting->id,
                    'user_id' => $attendee->id,
                    'amount' => $amount,
                    'status' => 'skipped',
                    'skip_reason' => 'No Minecraft account',
                ]);
                $skippedCount++;

                continue;
            }

            // All eligibility checks passed — persist a 'pending' placeholder before
            // firing RCON. Using 'pending' (not 'failed') clearly signals the payout
            // was never attempted, so admins know to re-process rather than investigate
            // a real RCON failure. The unique (meeting_id, user_id) constraint acts as
            // the idempotency guard: a retry finds the existing record and skips RCON,
            // preventing double-payment.
            $payout = MeetingPayout::firstOrCreate(
                ['meeting_id' => $meeting->id, 'user_id' => $attendee->id],
                [
                    'minecraft_account_id' => $mcAccount->id,
                    'amount' => $amount,
                    'status' => 'pending',
                ]
            );

            if (! $payout->wasRecentlyCreated) {
                // Record already existed from a prior crashed run — count and skip RCON
                if ($payout->status === 'paid') {
                    $paidCount++;
                } elseif ($payout->status === 'pending') {
                    // RCON was never attempted — surface as pending, not failed
                    $pendingCount++;
                } else {
                    $failedCount++;
                }

                continue;
            }

            $rconService = app(MinecraftRconService::class);
            $result = $rconService->executeCommand(
                "money give {$mcAccount->username} {$amount}",
                'meeting_payout',
                $mcAccount->username,
                $attendee,
                ['meeting_id' => $meeting->id]
            );

            if ($result['success']) {
                $payout->update(['status' => 'paid']);
                $paidCount++;
            } else {
                $payout->update(['status' => 'failed']);
                $failedCount++;
            }
        }

        $summary = "Meeting payouts: {$paidCount} paid, {$skippedCount} skipped, {$failedCount} failed.";
        if ($pendingCount > 0) {
            $summary = "Meeting payouts: {$paidCount} paid, {$skippedCount} skipped, {$failedCount} failed, {$pendingCount} pending (interrupted).";
        }

        RecordActivity::run($meeting, 'meeting_payouts_processed', $summary);
    }
}
