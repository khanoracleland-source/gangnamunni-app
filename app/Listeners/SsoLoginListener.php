<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;

class SsoLoginListener
{
    public function handle(Login $event): void
    {
        $user = $event->user;
        if (!$user) return;

        // member_sync에서 g5_mb_id 조회
        $sync = DB::table('member_sync')
            ->where('hms_user_id', $user->id)
            ->first();
        $mb_id = $sync ? $sync->mb_id : null;

        // 기존 토큰 삭제
        DB::table('sso_tokens')->where('hms_user_id', $user->id)->delete();

        // 새 토큰 생성
        $token = bin2hex(random_bytes(32));
        DB::table('sso_tokens')->insert([
            'token' => $token,
            'user_type' => 'hms',
            'g5_mb_id' => $mb_id,
            'hms_user_id' => $user->id,
            'ip_address' => request()->ip(),
            'user_agent' => substr(request()->userAgent() ?? '', 0, 255),
            'expires_at' => now()->addDay(),
            'created_at' => now(),
        ]);

        // 쿠키 설정 (EncryptCookies 에서 제외된 쿠키)
        Cookie::queue(cookie('sb_sso_token', $token, 1440, '/', null, true, true, false, 'Lax'));
    }
}
