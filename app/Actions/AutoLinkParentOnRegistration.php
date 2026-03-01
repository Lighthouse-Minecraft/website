<?php

namespace App\Actions;

use App\Models\ParentChildLink;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class AutoLinkParentOnRegistration
{
    use AsAction;

    public function handle(User $newUser): void
    {
        // Check if any child users have this user's email as their parent_email
        $children = User::where('parent_email', $newUser->email)->get();

        foreach ($children as $child) {
            if (ParentChildLink::where('parent_user_id', $newUser->id)
                ->where('child_user_id', $child->id)->exists()) {
                continue;
            }

            ParentChildLink::create([
                'parent_user_id' => $newUser->id,
                'child_user_id' => $child->id,
            ]);

            RecordActivity::run($child, 'parent_linked', "Parent account ({$newUser->email}) automatically linked.");
        }
    }
}
