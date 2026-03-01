<?php

namespace App\Policies;

use App\Models\User;

class ParentChildLinkPolicy
{
    public function manage(User $parent, User $child): bool
    {
        return $parent->children()->where('child_user_id', $child->id)->exists();
    }
}
