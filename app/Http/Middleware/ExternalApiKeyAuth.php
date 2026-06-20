<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * External API Key 인증 미들웨어
 *
 * 강남언니 등 외부 시스템에서 HMS API 호출 시
 * X-API-Key 헤더를 통한 인증 처리
 *
 * @file    app/Http/Middleware/ExternalApiKeyAuth.php
 * @created 2026-02-10
 * @phase   Phase 1-1: HMS External API
 */
class ExternalApiKeyAuth
{
    /**
     * 요청 처리
     *
     * @param Request $request HTTP 요청
     * @param Closure $next 다음 미들웨어
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-Key');

        // config() 사용 (config:cache 호환)
        $validKey = config('app.external_api_key', env('EXTERNAL_API_KEY', ''));

        if (empty($apiKey) || $apiKey !== $validKey) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid or missing API key',
            ], 401);
        }

        $response = $next($request);

        // ThrottleRequests 호환: null 응답 방지
        if ($response === null) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error: null response',
            ], 500);
        }

        return $response;
    }
}

