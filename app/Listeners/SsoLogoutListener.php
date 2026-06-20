<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;

class SsoLogoutListener
{
    public function handle(Logout $event): void
    {
        $token = request()->cookie('sb_sso_token');
        if (!empty($token)) {
            DB::table('sso_tokens')->where('token', $token)->delete();
        }

        Cookie::queue(Cookie::forget('sb_sso_token'));
    }
}
