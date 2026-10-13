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
        if (!$user || !$user->status) {
            return $next($request);
        }

        // HMS 에서 직접 로그인해 발급된 토큰(user_type=hms)만 그 사용자 그대로 복원한다.
        // 그누보드(사이트) 쪽에서 발급된 토큰은 member_sync 로 정상 연결된 "환자 전용" 계정만 허용한다.
        // (이메일 충돌로 잘못 연결된 매핑을 통해 최고관리자·직원 계정에 로그인되는 것을 막는다.)
        if ($sso->user_type !== 'hms') {
            $linked = !empty($sso->g5_mb_id) && DB::table('member_sync')
                ->where('mb_id', $sso->g5_mb_id)
                ->where('hms_user_id', $user->id)
                ->where('sync_status', 'synced')
                ->exists();
            $patientOnly = $user->owner_type === \App\Models\Patient::class
                && !$user->roles()->where('name', '!=', 'Patient')->exists();
            if (!$linked || !$patientOnly) {
                return $next($request);
            }
        }

        Auth::login($user);

        return $next($request);
    }
}
