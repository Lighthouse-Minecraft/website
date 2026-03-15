<?php

namespace App\Actions;

use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class LinkParentByEmail
{
    use AsAction;

    /**
     * When a child's parent_email is set or changed, find the matching
     * parent account (if any) and create the parent-child link.
     */
    public function handle(User $child): void
    {
        if (empty($child->parent_email)) {
            return;
        }

        $parent = User::whereRaw('LOWER(email) = ?', [strtolower($child->parent_email)])
            ->where('id', '!=', $child->id)
            ->first();

        if (! $parent) {
            return;
        }

        // Don't duplicate existing links
        if ($child->parents()->where('parent_user_id', $parent->id)->exists()) {
            return;
        }

        $child->parents()->attach($parent->id);

        RecordActivity::run($child, 'parent_linked', "Parent account ({$parent->email}) automatically linked.");
    }
}
