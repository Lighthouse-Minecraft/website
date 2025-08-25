<?php

namespace App\Actions;

use App\Models\Blog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class AcknowledgeBlog
{
    use AsAction;

    /**
     * Idempotently mark a blog as acknowledged by the given (or current) user.
     */
    public function handle(Blog $blog, ?User $user = null): void
    {
        // Resolve user if not provided
        if (! $user) {
            $user = Auth::user();
            if (! $user instanceof User) {
                throw ValidationException::withMessages([
                    'user' => 'User must be authenticated to acknowledge a blog.',
                ]);
            }
        }

        if ($user->acknowledgedBlogs()->whereKey($blog->id)->exists()) {
            return;
        }

        $user->acknowledgedBlogs()->syncWithoutDetaching([$blog->id]);
    }

    // Only allow logged-in users to acknowledge announcements
    public function authorize(ActionRequest $request): bool
    {
        return Auth::check();
    }
}
