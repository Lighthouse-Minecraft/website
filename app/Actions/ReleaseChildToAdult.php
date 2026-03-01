<?php

namespace App\Actions;

use App\Models\ParentChildLink;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class ReleaseChildToAdult
{
    use AsAction;

    public function handle(User $child, ?User $releasedBy = null): void
    {
        // Dissolve all parent-child links
        ParentChildLink::where('child_user_id', $child->id)->delete();

        // Reset parental toggles to defaults
        $child->parent_allows_site = true;
        $child->parent_allows_minecraft = true;
        $child->parent_allows_discord = true;
        $child->parent_email = null;
        $child->save();

        // If in parental brig, release
        if ($child->isInBrig() && $child->brig_type?->isParental()) {
            ReleaseUserFromBrig::run($child, $releasedBy ?? $child, 'Released to adult account.');
        }

        $desc = $releasedBy
            ? "Released to adult account by {$releasedBy->name}."
            : 'Automatically released to adult account (age 19+).';
        RecordActivity::run($child, 'child_released_to_adult', $desc);
    }
}
