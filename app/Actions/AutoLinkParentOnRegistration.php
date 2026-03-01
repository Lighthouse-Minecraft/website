<?php

namespace App\Actions;

use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class AutoLinkParentOnRegistration
{
    use AsAction;

    public function handle(User $newUser): void
    {
        // Check if any child users have this user's email as their parent_email
        $children = User::where('parent_email', $newUser->email)->get();

        if ($children->isEmpty()) {
            return;
        }

        $childIds = $children->pluck('id')->all();

        // Batch-link all children, skipping duplicates
        $newUser->children()->syncWithoutDetaching($childIds);

        foreach ($children as $child) {
            RecordActivity::run($child, 'parent_linked', "Parent account ({$newUser->email}) automatically linked.");
        }
    }
}
