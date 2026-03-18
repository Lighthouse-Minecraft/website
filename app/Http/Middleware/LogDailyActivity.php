<?php

namespace App\Http\Middleware;

use App\Models\UserLoginLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogDailyActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            UserLoginLog::insertOrIgnore([
                'user_id' => $request->user()->id,
                'platform' => 'website',
                'logged_at' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $next($request);
    }
}
