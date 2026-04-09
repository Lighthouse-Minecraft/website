<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureRulesAgreed
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && ! $request->user()->hasAgreedToCurrentRules()) {
            return redirect()->route('rules.show');
        }

        return $next($request);
    }
}
