<?php

namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if (!$request->expectsJson()) {

            return route('login-page');
        }
    }

    public function handle($request, Closure $next, ...$guards)
    {
        // Perform the usual authentication check
        $this->authenticate($request, $guards);
        // Check if the user is a vendor and multivendor is off using the helper
        if (Auth::check() && Auth::user()->user_type === 'vendor' && multiVendor() == 0) {
            Auth::logout(); // Log out vendor
            return redirect()->route('login-page')->with('error', 'Multivendor is disabled. You have been logged out.');
        }

        return $next($request);
    }
}
