<?php

namespace App\Actions;

use App\Models\User;
use App\Notifications\BrigStatusUpdatedNotification;
use App\Services\TicketNotificationService;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateBrigStatus
{
    use AsAction;

    /**
     * Update a brigged user's status in place without releasing and re-brigging.
     *
     * Handles reason edits, timer adjustments, permanent flag set/unset, and quick
     * release. All changes are activity-logged and the user is optionally notified.
     *
     * @param  User  $target  The brigged user to update.
     * @param  User  $admin  The staff member making the change.
     * @param  string|null  $newReason  New brig reason. Null = no change.
     * @param  Carbon|false  $newExpiresAt  New expiry. False = no change; null = clear; Carbon = set.
     * @param  bool|null  $permanent  True = set permanent; false = remove; null = no change.
     * @param  bool  $notify  Whether to notify the user. Always true when removing permanent.
     * @param  string|null  $releaseReason  If provided, quick-releases the user via ReleaseUserFromBrig.
     */
    public function handle(
        User $target,
        User $admin,
        ?string $newReason = null,
        Carbon|false $newExpiresAt = false,
        ?bool $permanent = null,
        bool $notify = true,
        ?string $releaseReason = null,
    ): void {
        // Quick release: delegate entirely to the existing action
        if ($releaseReason !== null) {
            ReleaseUserFromBrig::run($target, $admin, $releaseReason);
            RecordActivity::run($target, 'brig_status_updated', "Quick release by {$admin->name}. Reason: {$releaseReason}.");

            return;
        }

        $changes = [];

        // --- Permanent flag changes (highest priority — override expiry/timer) ---
        if ($permanent === true) {
            $target->permanent_brig_at = now();
            $target->brig_expires_at = null;
            $target->next_appeal_available_at = null;
            $target->save();

            $changes[] = 'Permanent confinement set';

            RecordActivity::run($target, 'permanent_brig_set', "Permanent confinement set by {$admin->name}. Appeal timer and expiry cleared.");

            if ($notify) {
                $this->sendNotification($target, 'Your brig status has been updated to permanent confinement. You cannot submit appeals.');
            }

            return;
        }

        if ($permanent === false) {
            $target->permanent_brig_at = null;

            // Recalculate next_appeal_available_at
            $target->next_appeal_available_at = $target->brig_expires_at
                ? $target->brig_expires_at
                : now()->addHours(24);

            $target->save();

            $changes[] = 'Permanent confinement removed';

            RecordActivity::run($target, 'permanent_brig_removed', "Permanent confinement removed by {$admin->name}. Appeal timer recalculated.");

            // Always notify when removing permanent confinement
            $this->sendNotification($target, 'Your permanent confinement has been lifted. You may now submit a brig appeal.');

            return;
        }

        // --- Reason / timer updates ---
        if ($newReason !== null) {
            $target->brig_reason = $newReason;
            $changes[] = "Reason updated to: {$newReason}";
        }

        if ($newExpiresAt !== false) {
            $target->brig_expires_at = $newExpiresAt;

            if ($newExpiresAt === null) {
                $changes[] = 'Expiry cleared (indefinite)';
            } else {
                $changes[] = 'Expiry set to '.$newExpiresAt->toDateTimeString();
            }
        }

        if (empty($changes)) {
            return;
        }

        $target->save();

        $changesSummary = implode('; ', $changes);
        RecordActivity::run($target, 'brig_status_updated', "Brig status updated by {$admin->name}: {$changesSummary}.");

        if ($notify) {
            $this->sendNotification($target, $changesSummary);
        }
    }

    private function sendNotification(User $target, string $summary): void
    {
        $notificationService = app(TicketNotificationService::class);
        $notificationService->send($target, new BrigStatusUpdatedNotification($target, $summary), 'account');
    }
}
