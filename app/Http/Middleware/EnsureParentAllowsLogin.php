<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureParentAllowsLogin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->parent_allows_login === false) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('status', 'Your account login has been disabled by your parent or guardian.');
        }

        return $next($request);
    }
}
