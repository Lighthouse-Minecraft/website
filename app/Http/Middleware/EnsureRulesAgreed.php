<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureRulesAgreed
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if (! $user->hasAgreedToCurrentRules()) {
            return redirect()->route('rules.show');
        }

        if ($user->unagreedChildren()->isNotEmpty()) {
            return redirect()->route('rules.show');
        }

        return $next($request);
    }
}
