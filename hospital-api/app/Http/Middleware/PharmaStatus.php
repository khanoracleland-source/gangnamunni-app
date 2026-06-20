<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PharmaStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $pluginStatus = checkPlugin('pharma');

        $user = auth()->user();

        if ( $pluginStatus !== 'active' || !$user || !$user->hasAnyRole(['pharma', 'admin', 'demo_admin','vendor', 'receptionist'])) {
            abort(403, 'Unauthorized access');
        }

        return $next($request);
    }
}
