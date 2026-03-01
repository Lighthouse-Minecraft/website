<?php

namespace App\Actions;

use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class AutoLinkParentOnRegistration
{
    use AsAction;

    public function handle(User $newUser): void
    {
        // Case-insensitive match in case emails were stored with different casing
        $children = User::whereRaw('LOWER(parent_email) = ?', [strtolower($newUser->email)])->get();

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
