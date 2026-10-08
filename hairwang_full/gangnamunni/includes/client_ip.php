<?php
/**
 * 신뢰할 수 있는 클라이언트 IP 판별
 *
 * CF-Connecting-IP / X-Forwarded-For 는 누구나 임의로 보낼 수 있는 헤더다.
 * 따라서 요청이 실제로 프록시에서 왔을 때만 그 헤더를 믿는다.
 *  - CF-Connecting-IP : REMOTE_ADDR 가 Cloudflare 대역일 때만 사용
 *  - X-Forwarded-For  : REMOTE_ADDR 가 TRUSTED_PROXIES(env, 콤마 구분 CIDR)일 때만 사용,
 *                       오른쪽부터 신뢰 프록시를 건너뛴 첫 IP 를 사용
 *  - 그 외에는 REMOTE_ADDR
 *
 * mod_remoteip 등으로 REMOTE_ADDR 가 이미 실제 클라이언트로 바뀌어 있으면 그대로 REMOTE_ADDR 를 쓴다.
 * Cloudflare 대역 출처: https://www.cloudflare.com/ips/ (변경 시 CF_IP_RANGES env 로 덮어쓸 수 있음)
 */

if (!function_exists('seoulbeauty_ip_in_cidr')) {
    function seoulbeauty_ip_in_cidr($ip, $cidr) {
        $parts = explode('/', trim($cidr), 2);
        $net = @inet_pton($parts[0]);
        $addr = @inet_pton($ip);
        if ($net === false || $addr === false || strlen($net) !== strlen($addr)) return false;
        $bits = isset($parts[1]) ? (int) $parts[1] : strlen($net) * 8;
        $bytes = intdiv($bits, 8);
        if ($bytes > 0 && substr($addr, 0, $bytes) !== substr($net, 0, $bytes)) return false;
        $rem = $bits % 8;
        if ($rem === 0) return true;
        $mask = chr((0xFF << (8 - $rem)) & 0xFF);
        return ((ord($addr[$bytes]) & ord($mask)) === (ord($net[$bytes]) & ord($mask)));
    }
}

if (!function_exists('seoulbeauty_ip_in_list')) {
    function seoulbeauty_ip_in_list($ip, array $cidrs) {
        foreach ($cidrs as $cidr) {
            if ($cidr !== '' && seoulbeauty_ip_in_cidr($ip, $cidr)) return true;
        }
        return false;
    }
}

if (!function_exists('seoulbeauty_cidr_env')) {
    function seoulbeauty_cidr_env($name, array $default) {
        $v = getenv($name);
        if ($v === false || trim($v) === '') return $default;
        return array_values(array_filter(array_map('trim', explode(',', $v))));
    }
}

if (!function_exists('seoulbeauty_client_ip')) {
    function seoulbeauty_client_ip() {
        static $ip = null;
        if ($ip !== null) return $ip;

        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if (filter_var($remote, FILTER_VALIDATE_IP) === false) {
            return $ip = '0.0.0.0';
        }

        $cloudflare = seoulbeauty_cidr_env('CF_IP_RANGES', [
            '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
            '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
            '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
            '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
            '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
            '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
        ]);
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP']) && seoulbeauty_ip_in_list($remote, $cloudflare)) {
            $cf = trim((string) $_SERVER['HTTP_CF_CONNECTING_IP']);
            if (filter_var($cf, FILTER_VALIDATE_IP) !== false) return $ip = $cf;
        }

        $trusted = seoulbeauty_cidr_env('TRUSTED_PROXIES', ['127.0.0.1/32', '::1/128']);
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) && seoulbeauty_ip_in_list($remote, $trusted)) {
            $hops = array_reverse(array_map('trim', explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])));
            foreach ($hops as $hop) {
                if (filter_var($hop, FILTER_VALIDATE_IP) === false) break;
                if (!seoulbeauty_ip_in_list($hop, $trusted)) return $ip = $hop;
            }
        }

        return $ip = $remote;
    }
}
