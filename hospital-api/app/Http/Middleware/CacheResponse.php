<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CacheResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  int  $minutes  Cache duration in minutes
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $minutes = 5)
    {
        // Only cache GET requests
        if ($request->method() !== 'GET') {
            return $next($request);
        }

        // Generate cache key based on URL and query parameters
        $cacheKey = $this->getCacheKey($request);

        // Try to get from cache
        if (Cache::has($cacheKey)) {
            $cachedResponse = Cache::get($cacheKey);

            return response()->json($cachedResponse)
                ->header('X-Cache', 'HIT')
                ->header('X-Cache-Key', $cacheKey);
        }

        // Process request
        $response = $next($request);

        // Cache successful responses
        if ($response->isSuccessful() && $response instanceof \Illuminate\Http\JsonResponse) {
            $data = $response->getData(true);

            Cache::put($cacheKey, $data, now()->addMinutes($minutes));

            return response()->json($data)
                ->header('X-Cache', 'MISS')
                ->header('X-Cache-Key', $cacheKey);
        }

        return $response;
    }

    /**
     * Generate cache key from request
     */
    private function getCacheKey(Request $request)
    {
        $url = $request->url();
        $queryParams = $request->query();

        ksort($queryParams);

        $queryString = http_build_query($queryParams);

        return 'api_cache:' . md5($url . $queryString);
    }
}
