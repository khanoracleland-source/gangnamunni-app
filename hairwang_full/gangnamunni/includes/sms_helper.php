<?php
/**
 * ============================================================================
 * SMS 발송 헬퍼 (스캐폴딩 — 결제/공급사 준비 후 즉시 활성화)
 * ============================================================================
 * 문자 발송은 요금이 발생하므로, 공급사 키가 설정되기 전까지는 아무 것도 보내지 않고
 * ['success' => false, 'error' => 'SMS 미설정'] 을 반환한다.
 *
 * ▶ 활성화 방법 (요금 결제/발신번호 등록이 끝난 뒤):
 *   1) auth/register.php 의 $enable_sms_verify = true 로 변경
 *   2) gangnamunni/.env 에 아래 키 추가 (git 에 커밋되지 않음)
 *        SMS_PROVIDER=aligo         # 현재 aligo(알리고) 참조 구현 포함
 *        SMS_API_KEY=발급키
 *        SMS_API_USER=알리고_user_id
 *        SMS_SENDER=사전등록발신번호   # 예: 0212345678
 *   → 그러면 가입 흐름에 SMS OTP(인증문자 발송+코드확인)가 즉시 적용된다.
 *
 * ▶ 다른 공급사(CoolSMS/SOLAPI, NHN Toast, Naver SENS)는 sms_send() 의
 *   switch 에 케이스와 전용 함수를 추가하면 된다. 호출부(register.php)는 그대로 재사용.
 * ============================================================================
 */

if (!function_exists('gnu_load_env')) {
    // .env 파서 (login.php 와 동일 규칙: # / ; 주석, KEY=VALUE)
    function gnu_load_env($file) {
        $out = [];
        if (!is_readable($file)) return $out;
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || $line[0] === ';') continue;
            $pos = strpos($line, '=');
            if ($pos === false) continue;
            $out[trim(substr($line, 0, $pos))] = trim(trim(substr($line, $pos + 1)), "\"'");
        }
        return $out;
    }
}

if (!function_exists('sms_config')) {
    function sms_config() {
        static $cfg = null;
        if ($cfg !== null) return $cfg;
        $env = gnu_load_env(dirname(__DIR__) . '/.env');
        $get = function ($k) use ($env) { return $env[$k] ?? (getenv($k) ?: ''); };
        $cfg = [
            'provider' => strtolower((string) $get('SMS_PROVIDER')),
            'api_key'  => (string) $get('SMS_API_KEY'),
            'api_user' => (string) $get('SMS_API_USER'),
            'sender'   => preg_replace('/[^0-9]/', '', (string) $get('SMS_SENDER')),
        ];
        return $cfg;
    }
}

if (!function_exists('sms_is_enabled')) {
    // 공급사·키·발신번호가 모두 설정돼 실제 발송이 가능한 상태인지 여부.
    function sms_is_enabled() {
        $c = sms_config();
        return $c['provider'] !== '' && $c['api_key'] !== '' && $c['sender'] !== '';
    }
}

if (!function_exists('sms_send')) {
    /**
     * @return array{success:bool, error?:string}
     */
    function sms_send($to_phone, $message) {
        $to = preg_replace('/[^0-9]/', '', (string) $to_phone);
        if ($to === '') return ['success' => false, 'error' => '수신번호 없음'];
        if (!sms_is_enabled()) return ['success' => false, 'error' => 'SMS 미설정'];

        $c = sms_config();
        switch ($c['provider']) {
            case 'aligo':
                return sms_send_aligo($c, $to, $message);
            default:
                return ['success' => false, 'error' => '지원하지 않는 SMS 공급사: ' . $c['provider']];
        }
    }
}

if (!function_exists('sms_send_aligo')) {
    // 알리고(Aligo) 참조 구현 — https://smartsms.aligo.in/  (문서: https://apis.aligo.in/)
    function sms_send_aligo($c, $to, $message) {
        $post = http_build_query([
            'key'      => $c['api_key'],
            'user_id'  => $c['api_user'],
            'sender'   => $c['sender'],
            'receiver' => $to,
            'msg'      => $message,
            'msg_type' => 'SMS',
        ]);
        $body = false;
        if (function_exists('curl_init')) {
            $ch = curl_init('https://apis.aligo.in/send/');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $post,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 7,
            ]);
            $body = curl_exec($ch);
            curl_close($ch);
        }
        if ($body === false) {
            $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded', 'content' => $post, 'timeout' => 7]]);
            $body = @file_get_contents('https://apis.aligo.in/send/', false, $ctx);
        }
        if (!$body) return ['success' => false, 'error' => '전송 실패(네트워크)'];
        $r = json_decode($body, true);
        // 알리고: result_code === 1 이면 성공(문자열/숫자 혼용될 수 있어 문자열 비교)
        if (is_array($r) && (string) ($r['result_code'] ?? '') === '1') return ['success' => true];
        return ['success' => false, 'error' => is_array($r) ? (string) ($r['message'] ?? 'unknown') : 'invalid response'];
    }
}
