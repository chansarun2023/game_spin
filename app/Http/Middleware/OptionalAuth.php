<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

class OptionalAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Try to authenticate the user if they have a valid token
        if ($request->bearerToken()) {
            // Use Sanctum's authentication logic
            $user = $request->user();

            if (!$user) {
                // Try to authenticate using the token
                $token = \Laravel\Sanctum\PersonalAccessToken::findToken($request->bearerToken());
                if ($token && $token->tokenable) {
                    Auth::login($token->tokenable);
                }
            }
        }

        return $next($request);
    }
}
