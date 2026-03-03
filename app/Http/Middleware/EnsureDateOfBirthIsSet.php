<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureDateOfBirthIsSet
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && $request->user()->date_of_birth === null) {
            if (! $request->routeIs('birthdate.*', 'logout')) {
                return redirect()->route('birthdate.show');
            }
        }

        return $next($request);
    }
}
