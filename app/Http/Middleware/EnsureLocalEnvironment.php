<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureLocalEnvironment
{
    public function handle(Request $request, Closure $next)
    {
        if (! app()->isLocal()) {
            abort(404);
        }

        return $next($request);
    }
}
