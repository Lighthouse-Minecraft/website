<?php

namespace App\Actions;

use App\Enums\StaffRank;
use App\Models\Meeting;
use App\Models\MeetingPayout;
use App\Models\SiteConfig;
use App\Services\MinecraftRconService;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessMeetingPayouts
{
    use AsAction;

    public function handle(Meeting $meeting, array $excludedUserIds = []): void
    {
        $payoutAmounts = [
            StaffRank::JrCrew->value => (int) SiteConfig::getValue('meeting_payout_jr_crew', '0'),
            StaffRank::CrewMember->value => (int) SiteConfig::getValue('meeting_payout_crew_member', '0'),
            StaffRank::Officer->value => (int) SiteConfig::getValue('meeting_payout_officer', '0'),
        ];

        $submittedUserIds = $meeting->reports()
            ->whereNotNull('submitted_at')
            ->pluck('user_id')
            ->toArray();

        $attendees = $meeting->attendees()->get();

        $paidCount = 0;
        $skippedCount = 0;
        $failedCount = 0;

        foreach ($attendees as $attendee) {
            // Skip if payout record already exists (duplicate prevention)
            if (MeetingPayout::where('meeting_id', $meeting->id)->where('user_id', $attendee->id)->exists()) {
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
            if ($amount === 0) {
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

            // All eligibility checks passed — persist a placeholder record before
            // firing RCON. This makes the unique (meeting_id, user_id) constraint
            // the idempotency guard: if the process crashes after RCON succeeds but
            // before the DB write, a retry will find this record and skip re-firing
            // the command, preventing a double-payment.
            $payout = MeetingPayout::firstOrCreate(
                ['meeting_id' => $meeting->id, 'user_id' => $attendee->id],
                [
                    'minecraft_account_id' => $mcAccount->id,
                    'amount' => $amount,
                    'status' => 'failed', // pessimistic default; updated to 'paid' on success
                ]
            );

            if (! $payout->wasRecentlyCreated) {
                // Record already existed from a prior crashed run — count it and skip RCON
                $payout->status === 'paid' ? $paidCount++ : $failedCount++;

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
                $failedCount++;
                // Status is already 'failed' from firstOrCreate — no update needed
            }
        }

        RecordActivity::run(
            $meeting,
            'meeting_payouts_processed',
            "Meeting payouts: {$paidCount} paid, {$skippedCount} skipped, {$failedCount} failed."
        );
    }
}
