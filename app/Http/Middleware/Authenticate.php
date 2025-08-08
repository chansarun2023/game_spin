<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // For API routes, always return null to get 401 JSON response
        if ($request->is('api/*') || $request->expectsJson()) {
            return null;
        }

        // For web routes, redirect to login (if login route exists)
        return route('login');
    }
}
