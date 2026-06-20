<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class SsoBridge
{
    public function handle(Request $request, Closure $next)
    {
        // 이미 로그인된 경우 스킵
        if (Auth::check()) {
            return $next($request);
        }

        // SSO 쿠키 확인
        $token = $request->cookie('sb_sso_token');
        if (empty($token) || strlen($token) !== 64) {
            return $next($request);
        }

        // 토큰 검증
        $sso = DB::table('sso_tokens')
            ->where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if (!$sso || !$sso->hms_user_id) {
            return $next($request);
        }

        // HMS 사용자 조회 및 자동 로그인
        $user = User::find($sso->hms_user_id);
        if ($user && $user->status) {
            Auth::login($user);
        }

        return $next($request);
    }
}
