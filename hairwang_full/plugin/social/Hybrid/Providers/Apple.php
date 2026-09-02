<?php
/* !
 * HybridAuth
 * http://hybridauth.sourceforge.net | http://github.com/hybridauth/hybridauth
 * (c) 2009-2012, HybridAuth authors | http://hybridauth.sourceforge.net/licenses.html
 */
/**
 * Hybrid_Providers_Apple provider adapter based on OAuth2 protocol
 * Copyright (c) 2020 HanbitGaram - webmaster@hanb.jp
 * https://hanb.jp / https://hanbitgaram.com
 * reference  : thisgun <Hybrid_Providers_Payco>4
 */

// vendor/firebase/php-jwt was dropped into place by hand (not via `composer
// require`), so it was never added to vendor/composer/autoload_*.php — the
// autoloader has no idea this package exists. Load it explicitly instead of
// relying on `use` + autoload, otherwise every one of these classes is a
// fatal "Class not found" the moment it's referenced.
if (!class_exists('Firebase\\JWT\\JWT', false)) {
    $__jwt_lib_dir = __DIR__ . '/../../vendor/firebase/php-jwt/src';
    require_once $__jwt_lib_dir . '/BeforeValidException.php';
    require_once $__jwt_lib_dir . '/ExpiredException.php';
    require_once $__jwt_lib_dir . '/SignatureInvalidException.php';
    require_once $__jwt_lib_dir . '/JWT.php';
}

use \Firebase\JWT\JWT;

class Hybrid_Providers_Apple extends Hybrid_Provider_Model_OAuth2 {

    public $scope;
    public $response_mode;
    public $auth_user_dir;
    private $client_info;
    private $api_response;
    private $post_user_name;

    function initialize()
    {
        parent::initialize();
        $this->scope = 'name email';
        $this->api->api_base_url = 'https://appleid.apple.com/auth/';
        $this->api->authorize_url = 'https://appleid.apple.com/auth/authorize';
        $this->api->token_url = 'https://appleid.apple.com/auth/token';
        $this->response_mode = 'form_post';
        $this->auth_user_dir = 'https://appleid.apple.com';
        $this->client_info = $this->api->client_secret;
        $this->restoreCallbackHandoff();
    }

    function loginBegin() {
        if (self::loginBeginRateLimited()) {
            error_log('[Apple flow] loginBegin rate-limited for this IP');
            $return_url = defined('G5_URL') ? G5_URL . '/gangnamunni/auth/login.php' : '/gangnamunni/auth/login.php';
            Hybrid_Auth::redirect($return_url);
        }

        $state = bin2hex(random_bytes(32));
        $nonce = bin2hex(random_bytes(16));
        $return_to = Hybrid_Auth::storage()->get("hauth_session.{$this->providerId}.hauth_return_to");
        if (!self::saveOneTimeAuthValue($state, $nonce, $return_to)) {
            error_log('[Apple flow] failed to persist oauth state (disk/permission issue?)');
            exit("Apple 로그인을 시작할 수 없습니다. 잠시 후 다시 시도해주세요.");
        }

        $parameters = array(
            "response_type" => "code",
            "response_mode" => $this->response_mode,
            "client_id" => $this->api->client_id,
            "redirect_uri" => $this->api->redirect_uri,
            "state" => $state,
            "nonce" => $nonce,
            "scope" => $this->scope,
        );

        Hybrid_Auth::redirect($this->api->authorizeUrl($parameters, "JS"));
    }

    function generateClientSecret(){
        $key = $this->client_info['key_content'];
        $payload = array(
            'iat'=>time(),
            'exp'=>time() + (86400*180),
            'iss'=> $this->client_info['team_id'],
            'aud'=>$this->auth_user_dir,
            'sub'=>$this->api->client_id
        );

        return JWT::encode(
            $payload,
            $key,
            'ES256',
            $this->client_info['key_id']
        );
        
    }

    function parseRequestResult( $result ){
        if( json_decode( $result ) ) return json_decode( $result );

        parse_str( $result, $output );

        $result = new StdClass();

        foreach( $output as $k => $v )
            $result->$k = $v;

        return $result;
    }

    function authenticate( $code )
    {
        $params = array(
            "grant_type"    => "authorization_code",
            "code"          => $code,
            "redirect_uri"  => $this->api->redirect_uri,
            "client_id"     => $this->api->client_id,
            "client_secret" => $this->api->client_secret,
            "scope"         => $this->scope
        );
        $response = $this->api->post($this->api->token_url,$params, false);
        $response = $this->parseRequestResult( $response );

        if( ! $response || ! isset( $response->access_token ) ){
            error_log('[Apple flow] token endpoint error: ' . (isset($response->error) ? $response->error : 'empty response'));
            exit( "Apple 로그인에 실패했습니다. 잠시 후 다시 시도해주세요." );
        }

        if( isset( $response->access_token  ) )  $this->api->access_token           = $response->access_token;
        if( isset( $response->refresh_token ) ) $this->api->refresh_token           = $response->refresh_token;
        if( isset( $response->expires_in    ) ) $this->api->access_token_expires_in = $response->expires_in;

        if( isset($response->expires_in)) {
            $this->api->access_token_expires_at = time() + $response->expires_in;
        }

        return $response;
    }

    function loginFinish() {
        if (!empty($_REQUEST['error'])) {
            error_log('[Apple flow] user declined/cancelled: ' . $_REQUEST['error']);
            $return_url = defined('G5_URL') ? G5_URL . '/gangnamunni/auth/login.php' : '/gangnamunni/auth/login.php';
            Hybrid_Auth::redirect($return_url);
        }

        $posted_state = isset($_POST['state']) ? (string)$_POST['state'] : '';
        $auth_data = $posted_state !== '' ? self::consumeOneTimeAuthValue($posted_state) : null;
        if ($posted_state === '' || $auth_data === null) {
            error_log('[Apple flow] state mismatch or expired');
            exit("해당 서비스에 접근할수 있는 권한이 없습니다.");
        }
        $expected_nonce = $auth_data['nonce'];

        $code = (array_key_exists('code', $_REQUEST)) ? $_REQUEST['code'] : "";
        $this->api->client_secret = $this->generateClientSecret();

        try{
            $this->api_response = $this->authenticate( $code );
        }
        catch( Exception $e ){
            error_log('[Apple flow] token exchange threw: ' . $e->getMessage());
            exit( "Apple 로그인에 실패했습니다. 잠시 후 다시 시도해주세요." );
        }

        if ( ! $this->api->authenticated() ){
            error_log('[Apple flow] not authenticated after token exchange');
            exit( "Apple 로그인에 실패했습니다. 잠시 후 다시 시도해주세요." );
        }

        if(isset($_REQUEST['user'])){
            $postData = stripslashes($_REQUEST['user']);
            if($postUserData = json_decode($postData, true)){
                if(isset($postUserData['name'])){
                    $this->post_user_name = '';
                    if(isset($postUserData['name']['lastName']))
                        $this->post_user_name .= $postUserData['name']['lastName'];
                    if(isset($postUserData['name']['firstName']))
                        $this->post_user_name .= $postUserData['name']['firstName'];
                }
            }
        }

        Hybrid_Auth::storage()->set('apple_auth_nonce', $expected_nonce);
        Hybrid_Auth::storage()->set('apple_user_name', $this->post_user_name);
        Hybrid_Auth::storage()->set('apple_auth_user', $this->api_response);

        $this->token("access_token",  $this->api->access_token);
        $this->token("refresh_token", $this->api->refresh_token);
        $this->token("expires_in",    $this->api->access_token_expires_in);
        $this->token("expires_at",    $this->api->access_token_expires_at);

        $this->setUserConnected();

        $handoff = bin2hex(random_bytes(32));
        $handoff_data = array(
            'nonce' => $expected_nonce,
            'user_name' => $this->post_user_name,
            'api_response' => $this->api_response,
            'access_token' => $this->api->access_token,
            'refresh_token' => $this->api->refresh_token,
            'expires_in' => $this->api->access_token_expires_in,
            'expires_at' => $this->api->access_token_expires_at,
        );
        if (!self::saveCallbackHandoff($handoff, $handoff_data)) {
            error_log('[Apple flow] failed to persist callback handoff');
            exit("Apple 로그인에 실패했습니다. 잠시 후 다시 시도해주세요.");
        }

        $return_to = !empty($auth_data['return_to'])
            ? $auth_data['return_to']
            : (defined('G5_URL') ? G5_URL . '/gangnamunni/auth/login.php?provider=Apple&redirect_to_idp=1' : '/gangnamunni/auth/login.php?provider=Apple&redirect_to_idp=1');
        $separator = strpos($return_to, '?') === false ? '?' : '&';
        $return_to .= $separator . 'apple_handoff=' . rawurlencode($handoff);
        Hybrid_Auth::storage()->set("hauth_session.{$this->providerId}.hauth_return_to", $return_to);
    }

    function getUserProfile() {
        $data = Hybrid_Auth::storage()->get('apple_auth_user');
        $username = Hybrid_Auth::storage()->get('apple_user_name');

        if (!is_object($data) || empty($data->id_token)) {
            error_log('[Apple flow] missing id_token');
            exit( "Apple 로그인에 실패했습니다. 잠시 후 다시 시도해주세요." );
        }

        $apple_keys = self::fetchApplePublicKeys();
        if ($apple_keys) {
            $kid = self::peekJwtKid($data->id_token);
            if ($kid !== null && !isset($apple_keys[$kid])) {
                $refreshed = self::fetchApplePublicKeys(true);
                if ($refreshed) $apple_keys = $refreshed;
            }
        }
        if (!$apple_keys) {
            error_log('[Apple flow] could not obtain Apple JWKS; rejecting login');
            exit( "Apple 로그인 확인에 실패했습니다. 잠시 후 다시 시도해주세요." );
        }

        try {
            $claims = JWT::decode($data->id_token, $apple_keys, array('RS256'));
        } catch (Exception $e) {
            error_log('[Apple flow] id_token verification failed: ' . $e->getMessage());
            exit( "Apple 로그인 확인에 실패했습니다. 잠시 후 다시 시도해주세요." );
        }

        if (($claims->iss ?? '') !== 'https://appleid.apple.com') {
            error_log('[Apple flow] unexpected iss claim');
            exit( "Apple 로그인 확인에 실패했습니다." );
        }
        if (($claims->aud ?? '') !== $this->api->client_id) {
            error_log('[Apple flow] unexpected aud claim');
            exit( "Apple 로그인 확인에 실패했습니다." );
        }
        $expected_nonce = Hybrid_Auth::storage()->get('apple_auth_nonce');
        $token_nonce = isset($claims->nonce) ? (string)$claims->nonce : '';
        if (empty($expected_nonce) || $token_nonce === '' || !hash_equals((string)$expected_nonce, $token_nonce)) {
            error_log('[Apple flow] nonce missing or mismatched');
            exit( "Apple 로그인 확인에 실패했습니다." );
        }

        $sub = isset($claims->sub) ? (string)$claims->sub : '';
        if ($sub === '') {
            error_log('[Apple flow] id_token missing sub claim');
            exit( "Apple 로그인 확인에 실패했습니다." );
        }

        $this->user->profile->identifier = $sub;
        $this->user->profile->sid = get_social_convert_id( $this->user->profile->identifier, $this->providerId );

        $safe_name = self::sanitizeAppleName(!empty($username) ? $username : (string)$this->post_user_name);
        $this->user->profile->username = $safe_name !== '' ? $safe_name : $this->user->profile->sid;
        $this->user->profile->displayName = $this->user->profile->username;

        $email = isset($claims->email) ? (string)$claims->email : '';
        $email_verified = isset($claims->email_verified)
            && ($claims->email_verified === true || $claims->email_verified === 'true' || $claims->email_verified === 1 || $claims->email_verified === '1');
        $this->user->profile->email = $email;
        $this->user->profile->emailVerified = $email_verified ? $email : '';

        Hybrid_Auth::storage()->delete('apple_auth_nonce');
        Hybrid_Auth::storage()->delete('apple_auth_user');
        Hybrid_Auth::storage()->delete('apple_user_name');

        return $this->user->profile;
    }

    private static function fetchApplePublicKeys($force = false) {
        $cache_dir = (defined('G5_DATA_PATH') ? G5_DATA_PATH : sys_get_temp_dir()) . '/cache';
        $cache_file = $cache_dir . '/apple_jwks.json';
        $ttl = 3600;

        if (!$force && is_file($cache_file) && (time() - filemtime($cache_file)) < $ttl) {
            $cached = json_decode((string)@file_get_contents($cache_file), true);
            if (is_array($cached) && !empty($cached)) return $cached;
        }

        $raw = false;
        if (function_exists('curl_init')) {
            $ch = curl_init('https://appleid.apple.com/auth/keys');
            curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5));
            $raw = curl_exec($ch);
            curl_close($ch);
        }
        if ($raw === false) {
            $ctx = stream_context_create(array('http' => array('timeout' => 5)));
            $raw = @file_get_contents('https://appleid.apple.com/auth/keys', false, $ctx);
        }
        if (!$raw) return null;

        $parsed = json_decode($raw, true);
        $keys = array();
        if (!empty($parsed['keys']) && is_array($parsed['keys'])) {
            foreach ($parsed['keys'] as $jwk) {
                if (($jwk['kty'] ?? '') !== 'RSA' || empty($jwk['kid']) || empty($jwk['n']) || empty($jwk['e'])) continue;
                $pem = self::jwkToPem($jwk['n'], $jwk['e']);
                if ($pem) $keys[$jwk['kid']] = $pem;
            }
        }
        if (empty($keys)) return null;

        if (!is_dir($cache_dir)) @mkdir($cache_dir, 0755, true);
        $tmp_file = $cache_file . '.' . getmypid() . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp_file, json_encode($keys)) !== false) {
            @rename($tmp_file, $cache_file);
        }

        return $keys;
    }

    private static function peekJwtKid($jwt) {
        $parts = explode('.', (string)$jwt);
        if (count($parts) !== 3) return null;
        $header = json_decode((string)self::base64UrlDecode($parts[0]), true);
        return (is_array($header) && !empty($header['kid'])) ? (string)$header['kid'] : null;
    }

    private static function oneTimeAuthDir() {
        $dir = (defined('G5_DATA_PATH') ? G5_DATA_PATH : sys_get_temp_dir()) . '/cache/apple_oauth';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $htaccess = $dir . '/.htaccess';
        if (!is_file($htaccess)) {
            @file_put_contents($htaccess, "Deny from all\n");
        }
        return $dir;
    }

    private static function loginBeginRateLimited() {
        $ip = preg_replace('/[^0-9a-fA-F:.]/', '', (string)($_SERVER['REMOTE_ADDR'] ?? ''));
        $dir = self::oneTimeAuthDir() . '/ratelimit';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $limit = 10;
        $window = 900;
        $path = $dir . '/' . hash('sha256', $ip !== '' ? $ip : 'unknown') . '.json';
        $fp = @fopen($path, 'c+');
        if (!$fp || !flock($fp, LOCK_EX)) { if ($fp) fclose($fp); return false; }
        $raw = stream_get_contents($fp);
        $data = json_decode($raw ?: '', true);
        $now = time();
        $hits = is_array($data['hits'] ?? null) ? $data['hits'] : array();
        $hits = array_values(array_filter($hits, function ($ts) use ($now, $window) { return (int)$ts > $now - $window; }));
        $limited = count($hits) >= $limit;
        if (!$limited) $hits[] = $now;
        ftruncate($fp, 0); rewind($fp);
        fwrite($fp, json_encode(array('hits' => $hits))); fflush($fp);
        flock($fp, LOCK_UN); fclose($fp);

        if (random_int(1, 100) === 1) {
            foreach (glob($dir . '/*.json') ?: array() as $old) {
                if (@filemtime($old) < $now - 86400 && ($old_fp = @fopen($old, 'c+'))) {
                    if (@flock($old_fp, LOCK_EX | LOCK_NB)) {
                        if (@filemtime($old) < $now - 86400) @unlink($old);
                        @flock($old_fp, LOCK_UN);
                    }
                    @fclose($old_fp);
                }
            }
        }

        return $limited;
    }

    private static function saveOneTimeAuthValue($state, $nonce, $return_to = null) {
        $file = self::oneTimeAuthDir() . '/' . hash('sha256', $state) . '.json';
        $tmp_file = $file . '.' . getmypid() . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $payload = json_encode(array('nonce' => $nonce, 'return_to' => $return_to, 'created' => time()));
        $ok = @file_put_contents($tmp_file, $payload) !== false && @rename($tmp_file, $file);
        if (!$ok) @unlink($tmp_file);

        if (random_int(1, 50) === 1) {
            foreach (glob(self::oneTimeAuthDir() . '/*.json') ?: array() as $old) {
                if (@filemtime($old) < time() - 600) @unlink($old);
            }
        }

        return $ok;
    }

    private function restoreCallbackHandoff() {
        $handoff = isset($_GET['apple_handoff']) ? (string)$_GET['apple_handoff'] : '';
        if (!preg_match('/^[a-f0-9]{64}$/', $handoff)) return;

        $data = self::consumeCallbackHandoff($handoff);
        if ($data === null) {
            error_log('[Apple flow] callback handoff missing or expired');
            return;
        }

        $response = isset($data['api_response']) && is_array($data['api_response'])
            ? (object)$data['api_response']
            : null;
        Hybrid_Auth::storage()->set('apple_auth_nonce', isset($data['nonce']) ? $data['nonce'] : '');
        Hybrid_Auth::storage()->set('apple_user_name', isset($data['user_name']) ? $data['user_name'] : '');
        Hybrid_Auth::storage()->set('apple_auth_user', $response);

        $this->token('access_token', isset($data['access_token']) ? $data['access_token'] : '');
        $this->token('refresh_token', isset($data['refresh_token']) ? $data['refresh_token'] : '');
        $this->token('expires_in', isset($data['expires_in']) ? $data['expires_in'] : 0);
        $this->token('expires_at', isset($data['expires_at']) ? $data['expires_at'] : 0);
        $this->setUserConnected();
    }

    private static function saveCallbackHandoff($handoff, $data) {
        $file = self::oneTimeAuthDir() . '/handoff_' . hash('sha256', $handoff) . '.json';
        $tmp_file = $file . '.' . getmypid() . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $data['created'] = time();
        $payload = json_encode($data);
        if ($payload === false) return false;

        $ok = @file_put_contents($tmp_file, $payload, LOCK_EX) !== false
            && @chmod($tmp_file, 0600)
            && @rename($tmp_file, $file);
        if (!$ok) @unlink($tmp_file);
        return $ok;
    }

    private static function consumeCallbackHandoff($handoff) {
        $file = self::oneTimeAuthDir() . '/handoff_' . hash('sha256', $handoff) . '.json';
        if (!is_file($file)) return null;

        $fp = @fopen($file, 'r');
        if (!$fp || !@flock($fp, LOCK_EX)) {
            if ($fp) @fclose($fp);
            return null;
        }
        $raw = stream_get_contents($fp);
        @unlink($file);
        @flock($fp, LOCK_UN);
        @fclose($fp);

        $data = json_decode((string)$raw, true);
        if (!is_array($data) || empty($data['created']) || time() - (int)$data['created'] > 600) return null;
        return $data;
    }

    private static function consumeOneTimeAuthValue($state) {
        $file = self::oneTimeAuthDir() . '/' . hash('sha256', $state) . '.json';
        if (!is_file($file)) return null;
        $raw = @file_get_contents($file);
        @unlink($file);
        $data = json_decode((string)$raw, true);
        if (!is_array($data) || !isset($data['nonce'], $data['created'])) return null;
        if (time() - (int)$data['created'] > 600) return null;
        return array('nonce' => $data['nonce'], 'return_to' => $data['return_to'] ?? null);
    }

    private static function jwkToPem($n_b64url, $e_b64url) {
        $modulus = self::base64UrlDecode($n_b64url);
        $exponent = self::base64UrlDecode($e_b64url);
        if ($modulus === false || $exponent === false || $modulus === '' || $exponent === '') return null;

        if (ord($modulus[0]) > 0x7f) $modulus = "\x00" . $modulus;
        if (ord($exponent[0]) > 0x7f) $exponent = "\x00" . $exponent;

        $modulusEncoded = pack('Ca*a*', 0x02, self::derLength(strlen($modulus)), $modulus);
        $exponentEncoded = pack('Ca*a*', 0x02, self::derLength(strlen($exponent)), $exponent);
        $sequence = $modulusEncoded . $exponentEncoded;
        $rsaPublicKey = pack('Ca*a*', 0x30, self::derLength(strlen($sequence)), $sequence);

        $rsaOID = pack('H*', '300d06092a864886f70d0101010500');
        $bitString = "\x00" . $rsaPublicKey;
        $bitStringEncoded = pack('Ca*a*', 0x03, self::derLength(strlen($bitString)), $bitString);
        $spki = $rsaOID . $bitStringEncoded;
        $spkiEncoded = pack('Ca*a*', 0x30, self::derLength(strlen($spki)), $spki);

        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spkiEncoded), 64) . "-----END PUBLIC KEY-----\n";
    }

    private static function derLength($len) {
        if ($len <= 0x7f) return chr($len);
        $bytes = ltrim(pack('N', $len), "\x00");
        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    private static function base64UrlDecode($input) {
        $remainder = strlen($input) % 4;
        if ($remainder) $input .= str_repeat('=', 4 - $remainder);
        return base64_decode(strtr($input, '-_', '+/'));
    }

    private static function sanitizeAppleName($name) {
        $name = preg_replace('/[\r\n\x00-\x1F\x7F]/', '', (string)$name);
        $name = trim($name);
        return function_exists('mb_substr') ? mb_substr($name, 0, 100) : substr($name, 0, 100);
    }

}
