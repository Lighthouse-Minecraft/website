<?php

namespace App\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckDocumentVisibility
{
    use AsAction;

    private const GATE_MAP = [
        'users' => 'view-docs-users',
        'resident' => 'view-docs-resident',
        'citizen' => 'view-docs-citizen',
        'staff' => 'view-docs-staff',
        'officer' => 'view-docs-officer',
    ];

    public function handle(string $visibility): bool
    {
        if ($visibility === 'public') {
            return true;
        }

        $user = Auth::user();
        $gate = self::GATE_MAP[$visibility] ?? null;

        if (! $gate) {
            abort(404);
        }

        if (! $user) {
            abort(403, 'login_required');
        }

        if (! Gate::allows($gate)) {
            $message = in_array($visibility, ['staff', 'officer']) ? 'staff_only' : 'restricted';
            abort(403, $message);
        }

        return true;
    }
}
