<?php
/**
 * 공통 함수 모음
 * API 및 에디터에서 사용
 */

/**
 * JSON 응답 출력 후 종료
 *
 * @param mixed $data json_encode 가능한 응답 본문
 * @param int $statusCode HTTP 상태 코드
 * @return never
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * JSON 에러 응답
 *
 * @param string $message
 * @param int $statusCode
 * @return never
 */
function jsonError($message, $statusCode = 400) {
    jsonResponse(['success' => false, 'message' => $message], $statusCode);
}

/**
 * JSON 성공 응답
 *
 * @param array $data 응답에 병합할 추가 필드
 * @param string $message
 * @return never
 */
function jsonSuccess($data = [], $message = 'OK') {
    $response = array_merge(['success' => true, 'message' => $message], $data);
    jsonResponse($response);
}

/**
 * XSS 방지용 문자열 정리
 *
 * @param string|null $str
 * @return string
 */
function sanitizeInput($str) {
    if (is_null($str)) return '';
    $str = trim($str);
    $str = htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    return $str;
}

/**
 * HTML 에디터(Quill) 콘텐츠 정리 — DOM 기반 화이트리스트
 *
 * 정규식 치환 대신 HTML 을 파싱해서 허용된 태그·속성만 남긴다.
 *  - 허용 태그 외 요소는 내용만 남기고 태그 제거, script/style/iframe/svg 등은 내용째 제거
 *  - 속성은 태그별 화이트리스트만 유지 (on*, style 등 나머지는 모두 제거)
 *  - href/src 는 http(s)://, / (사이트 상대), href 는 # 까지만 허용
 *  - class 는 Quill 서식 클래스(ql-*)만 허용
 * DOM 확장이 없으면 HTML 을 버리고 텍스트로만 반환한다 (fail-safe).
 *
 * @param string|null $html 정리할 HTML
 * @return string
 */
function sanitizeHtml($html) {
    if (is_null($html) || trim((string) $html) === '') return '';
    $html = (string) $html;

    if (!class_exists('DOMDocument')) {
        return nl2br(htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8'));
    }

    $doc = new DOMDocument('1.0', 'UTF-8');
    $prevErrors = libxml_use_internal_errors(true);
    $doc->loadHTML(
        '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>',
        LIBXML_NONET
    );
    libxml_clear_errors();
    libxml_use_internal_errors($prevErrors);

    $body = $doc->getElementsByTagName('body')->item(0);
    if (!$body) return '';
    _sanitize_html_children($body);

    $out = '';
    foreach (iterator_to_array($body->childNodes) as $child) {
        $out .= $doc->saveHTML($child);
    }
    return $out;
}

/**
 * sanitizeHtml() 내부용: 허용 URL 여부
 *
 * @param mixed $url
 * @param bool $allowFragment true 면 '#' 앵커 허용(href 용)
 * @return string|null 허용되면 URL, 아니면 null
 */
function _sanitize_html_url($url, $allowFragment) {
    $url = trim((string) $url);
    // 스킴 판별 전에 제어문자·공백 제거 (java\tscript: 등 우회 차단)
    $probe = preg_replace('/[\x00-\x20\x7F]+/', '', $url);
    if ($probe === '') return null;
    if (preg_match('#^https?://#i', $probe)) return $url;
    if ($probe[0] === '/' ) return $url;
    if ($allowFragment && $probe[0] === '#') return $url;
    return null;
}

/**
 * 사용자 입력으로 받은 이미지 URL 정리 — XSS 유발 URL 차단
 *
 * 허용: http(s):// 절대 URL, 사이트 상대 경로(/uploads/..., uploads/...)
 * 차단: javascript:/data:/vbscript: 등 모든 기타 스킴, 프로토콜 상대(//host),
 *       경로 탈출(..), 따옴표·꺾쇠·백틱·역슬래시·공백·제어문자
 *       (인라인 이벤트 핸들러 안의 JS 문자열 탈출 방지 — htmlspecialchars 로는 막히지 않는다)
 *
 * @param mixed $url
 * @return string 안전하면 URL, 아니면 ''
 */
function sanitizeImageUrl($url) {
    if (!is_string($url)) return '';
    $url = trim($url);
    if ($url === '' || strlen($url) > 500) return '';
    // 공백·제어문자 + 속성/JS 문자열 탈출 문자
    if (preg_match('/[\s\x00-\x1F\x7F"\'<>`\\\\]/', $url)) return '';
    if (strpos($url, '..') !== false) return '';
    // 프로토콜 상대 URL(//evil.com)
    if (substr($url, 0, 2) === '//') return '';
    // http(s) 절대 URL
    if (preg_match('#^https?://[^/?\#]+#i', $url)) return $url;
    // 스킴이 남아 있으면 차단 (javascript:, data:, vbscript: ...)
    if (preg_match('#^[A-Za-z][A-Za-z0-9+.\-]*:#', $url)) return '';
    // 사이트 상대 경로
    if (preg_match('#^/?[A-Za-z0-9][A-Za-z0-9/_.\-~%?&=+]*$#', $url)) return $url;
    return '';
}

/**
 * 이미지 URL 배열 정리 — 안전하지 않은 항목은 버린다.
 *
 * @param mixed $urls
 * @return array
 */
function sanitizeImageUrlList($urls) {
    if (!is_array($urls)) return [];
    $out = [];
    foreach ($urls as $u) {
        $clean = sanitizeImageUrl($u);
        if ($clean !== '') $out[] = $clean;
    }
    return $out;
}

/** sanitizeHtml() 내부용: 노드 트리 재귀 정리 */
function _sanitize_html_children(DOMNode $parent) {
    static $allowedTags = [
        'p' => 1, 'br' => 1, 'strong' => 1, 'em' => 1, 'u' => 1, 's' => 1,
        'ul' => 1, 'ol' => 1, 'li' => 1, 'h1' => 1, 'h2' => 1, 'h3' => 1,
        'blockquote' => 1, 'a' => 1, 'img' => 1, 'span' => 1,
    ];
    static $dropWithContent = [
        'script' => 1, 'style' => 1, 'iframe' => 1, 'frame' => 1, 'frameset' => 1,
        'object' => 1, 'embed' => 1, 'applet' => 1, 'svg' => 1, 'math' => 1,
        'template' => 1, 'noscript' => 1, 'textarea' => 1, 'select' => 1,
        'title' => 1, 'head' => 1, 'meta' => 1, 'link' => 1, 'base' => 1, 'form' => 1,
    ];

    foreach (iterator_to_array($parent->childNodes) as $node) {
        if ($node->nodeType === XML_TEXT_NODE) continue;

        if ($node->nodeType !== XML_ELEMENT_NODE) {
            $parent->removeChild($node); // 주석, CDATA, PI 등
            continue;
        }

        /** @var DOMElement $node nodeType 검사로 요소 노드임이 보장된다 */
        $tag = strtolower($node->nodeName);
        if (isset($dropWithContent[$tag])) {
            $parent->removeChild($node);
            continue;
        }

        _sanitize_html_children($node);

        if (!isset($allowedTags[$tag])) {
            // 허용되지 않은 태그: 정리된 자식만 남기고 태그 제거
            while ($node->firstChild) {
                $parent->insertBefore($node->firstChild, $node);
            }
            $parent->removeChild($node);
            continue;
        }

        $keep = [];
        $class = $node->getAttribute('class');
        if ($class !== '') {
            $classes = array_filter(preg_split('/\s+/', $class), function ($c) {
                return (bool) preg_match('/^ql-[a-z0-9-]{1,40}$/', $c);
            });
            if ($classes) $keep['class'] = implode(' ', $classes);
        }
        if ($tag === 'a') {
            $href = _sanitize_html_url($node->getAttribute('href'), true);
            $keep['href'] = $href !== null ? $href : '#';
            $target = strtolower($node->getAttribute('target'));
            if ($target === '_blank' || $target === '_self') {
                $keep['target'] = $target;
                $keep['rel'] = 'noopener noreferrer';
            }
        } elseif ($tag === 'img') {
            $src = _sanitize_html_url($node->getAttribute('src'), false);
            if ($src === null) {
                $parent->removeChild($node);
                continue;
            }
            $keep['src'] = $src;
            $alt = $node->getAttribute('alt');
            if ($alt !== '') $keep['alt'] = $alt;
        } elseif ($tag === 'li') {
            $list = $node->getAttribute('data-list');
            if (in_array($list, ['bullet', 'ordered', 'checked', 'unchecked'], true)) $keep['data-list'] = $list;
        }

        foreach (iterator_to_array($node->attributes) as $attr) {
            $node->removeAttribute($attr->nodeName);
        }
        foreach ($keep as $name => $value) {
            $node->setAttribute($name, $value);
        }
    }
}

/**
 * POST JSON 바디 파싱
 */
function getJsonBody() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }
    return $data;
}

/**
 * 이미지 파일 유효성 검사
 *
 * @param array $file $_FILES 항목
 * @param int $maxSize 최대 크기(바이트)
 * @return string[] 오류 메시지 목록(비어 있으면 통과)
 */
function validateImageFile($file, $maxSize = 10485760) {
    $errors = [];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = '파일 업로드 오류가 발생했습니다.';
        return $errors;
    }

    if ($file['size'] > $maxSize) {
        $errors[] = '파일 크기가 ' . ($maxSize / 1048576) . 'MB를 초과합니다.';
    }

    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed_types)) {
        $errors[] = '허용되지 않는 파일 형식입니다. (jpg, png, gif, webp만 가능)';
    }

    // 매직 바이트 검증
    $header = file_get_contents($file['tmp_name'], false, null, 0, 8);
    $valid_headers = [
        "\xFF\xD8\xFF",           // JPEG
        "\x89\x50\x4E\x47",      // PNG
        "\x47\x49\x46\x38",      // GIF
        "RIFF",                    // WebP (RIFF header)
    ];

    $header_valid = false;
    foreach ($valid_headers as $vh) {
        if (strpos($header, $vh) === 0) {
            $header_valid = true;
            break;
        }
    }

    if (!$header_valid) {
        $errors[] = '유효하지 않은 이미지 파일입니다.';
    }

    return $errors;
}

/**
 * 유니크 파일명 생성
 *
 * @param string $extension 확장자(점 제외)
 * @return string
 */
function generateUniqueFilename($extension) {
    return date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
}

/**
 * 이미지 리사이즈 (최대 너비 기준)
 *
 * @param string $sourcePath 원본 이미지 경로
 * @param string $destPath 저장할 경로
 * @param int $maxWidth 최대 너비(px)
 * @return bool
 */
function resizeImage($sourcePath, $destPath, $maxWidth = 1920) {
    $info = getimagesize($sourcePath);
    if (!$info) return false;

    $width = $info[0];
    $height = $info[1];
    $mime = $info['mime'];

    if ($width <= $maxWidth) {
        if ($sourcePath !== $destPath) {
            copy($sourcePath, $destPath);
        }
        return true;
    }

    $ratio = $maxWidth / $width;
    $newWidth = $maxWidth;
    $newHeight = (int)($height * $ratio);

    switch ($mime) {
        case 'image/jpeg': $src = imagecreatefromjpeg($sourcePath); break;
        case 'image/png':  $src = imagecreatefrompng($sourcePath); break;
        case 'image/gif':  $src = imagecreatefromgif($sourcePath); break;
        case 'image/webp': $src = imagecreatefromwebp($sourcePath); break;
        default: return false;
    }

    $dst = imagecreatetruecolor($newWidth, $newHeight);

    // PNG/GIF 투명 배경 유지
    if ($mime === 'image/png' || $mime === 'image/gif') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    switch ($mime) {
        case 'image/jpeg': imagejpeg($dst, $destPath, 85); break;
        case 'image/png':  imagepng($dst, $destPath, 6); break;
        case 'image/gif':  imagegif($dst, $destPath); break;
        case 'image/webp': imagewebp($dst, $destPath, 85); break;
    }

    imagedestroy($src);
    imagedestroy($dst);
    return true;
}

/**
 * 썸네일 생성
 *
 * @param string $sourcePath 원본 이미지 경로
 * @param string $destPath 저장할 경로
 * @param int $thumbWidth 썸네일 너비(px)
 * @return bool
 */
function createThumbnail($sourcePath, $destPath, $thumbWidth = 300) {
    return resizeImage($sourcePath, $destPath, $thumbWidth);
}

/**
 * EXIF 회전 보정
 *
 * @param string $filePath 보정할 JPEG 경로(제자리 덮어쓰기)
 * @return void
 */
function fixExifOrientation($filePath) {
    if (!function_exists('exif_read_data')) return;

    $info = getimagesize($filePath);
    if ($info['mime'] !== 'image/jpeg') return;

    $exif = @exif_read_data($filePath);
    if (!$exif || !isset($exif['Orientation'])) return;

    $src = imagecreatefromjpeg($filePath);
    $orientation = $exif['Orientation'];

    switch ($orientation) {
        case 3: $src = imagerotate($src, 180, 0); break;
        case 6: $src = imagerotate($src, -90, 0); break;
        case 8: $src = imagerotate($src, 90, 0); break;
        default: return;
    }

    imagejpeg($src, $filePath, 90);
    imagedestroy($src);
}

/**
 * WebP 변환
 *
 * @param string $sourcePath 원본 이미지 경로
 * @param string $destPath 저장할 경로
 * @param int $quality 품질(0-100)
 * @return bool
 */
function convertToWebp($sourcePath, $destPath, $quality = 80) {
    $info = getimagesize($sourcePath);
    if (!$info) return false;

    switch ($info['mime']) {
        case 'image/jpeg': $src = imagecreatefromjpeg($sourcePath); break;
        case 'image/png':
            $src = imagecreatefrompng($sourcePath);
            imagealphablending($src, true);
            imagesavealpha($src, true);
            break;
        case 'image/gif':  $src = imagecreatefromgif($sourcePath); break;
        case 'image/webp': copy($sourcePath, $destPath); return true;
        default: return false;
    }

    $result = imagewebp($src, $destPath, $quality);
    imagedestroy($src);
    return $result;
}

/**
 * Rate Limiting (IP 기반, 파일 저장)
 *
 * @param string $identifier 제한 대상 식별자(예: IP)
 * @param int $maxRequests 허용 요청 수
 * @param int $windowSeconds 시간 창(초)
 * @return bool 허용되면 true, 초과하면 false
 */
function checkRateLimit($identifier, $maxRequests = 60, $windowSeconds = 60) {
    $rateLimitDir = dirname(__DIR__) . '/uploads/.ratelimit';
    if (!is_dir($rateLimitDir)) {
        mkdir($rateLimitDir, 0755, true);
    }

    $file = $rateLimitDir . '/' . md5($identifier) . '.json';

    $data = [];
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true) ?: [];
    }

    $now = time();
    // 만료된 항목 제거
    $data = array_filter($data, function($ts) use ($now, $windowSeconds) {
        return ($now - $ts) < $windowSeconds;
    });

    if (count($data) >= $maxRequests) {
        return false;
    }

    $data[] = $now;
    file_put_contents($file, json_encode($data), LOCK_EX);
    return true;
}

/**
 * CSRF 토큰 생성
 */
function generateCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRF 토큰 검증
 *
 * @param string $token 요청으로 받은 토큰
 * @return bool
 */
function validateCsrfToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Disconnect a Kakao account before deleting the local service account.
 * Kakao requires /v1/user/unlink (not logout) so consent is withdrawn and the
 * consent/signup screen is shown again on a later Kakao login.
 */
function unlinkKakaoAccountForWithdrawal($kakaoUserId = '') {
    $accessToken = '';

    try {
        if (!class_exists('Hybrid_Auth') && defined('G5_SOCIAL_LOGIN_PATH')) {
            require_once G5_SOCIAL_LOGIN_PATH . '/Hybrid/Auth.php';
        }
        if (class_exists('Hybrid_Auth')) {
            $adapter = Hybrid_Auth::getAdapter('Kakao');
            $tokens = $adapter->getAccessToken();
            $accessToken = trim((string)($tokens['access_token'] ?? ''));
        }
    } catch (Throwable $e) {
        error_log('[Kakao unlink] Access token unavailable: ' . $e->getMessage());
    }

    $headers = ['Content-Type: application/x-www-form-urlencoded;charset=utf-8'];
    $body = '';
    if ($accessToken !== '') {
        $headers[] = 'Authorization: Bearer ' . $accessToken;
    } else {
        $adminKey = defined('KAKAO_ADMIN_KEY') ? (string)KAKAO_ADMIN_KEY : (string)getenv('KAKAO_ADMIN_KEY');
        if ($adminKey === '' || $kakaoUserId === '' || !ctype_digit((string)$kakaoUserId)) {
            return ['ok' => false, 'message' => 'Kakao unlink credentials are unavailable.'];
        }
        $headers[] = 'Authorization: KakaoAK ' . $adminKey;
        $body = http_build_query(['target_id_type' => 'user_id', 'target_id' => (string)$kakaoUserId]);
    }

    $curl = curl_init('https://kapi.kakao.com/v1/user/unlink');
    if ($curl === false) return ['ok' => false, 'message' => 'Unable to initialize Kakao unlink.'];
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($curl);
    $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    $payload = is_string($response) ? json_decode($response, true) : null;
    $unlinkedId = is_array($payload) ? (string)($payload['id'] ?? '') : '';
    if ($httpCode !== 200 || $unlinkedId === '') {
        error_log('[Kakao unlink] HTTP ' . $httpCode . ': ' . ($curlError ?: (string)$response));
        return ['ok' => false, 'message' => 'Kakao account unlink failed.'];
    }
    if ($kakaoUserId !== '' && ctype_digit((string)$kakaoUserId) && $unlinkedId !== (string)$kakaoUserId) {
        error_log('[Kakao unlink] User id mismatch.');
        return ['ok' => false, 'message' => 'Kakao unlink identity mismatch.'];
    }

    return ['ok' => true, 'id' => $unlinkedId];
}

/**
 * 날짜 포맷 (상대 시간)
 *
 * @param string $datetime strtotime 이 해석할 수 있는 날짜 문자열
 * @return string
 */
function formatRelativeTime($datetime) {
    $now = time();
    $ts = strtotime($datetime);
    $diff = $now - $ts;

    if ($diff < 60) return '방금 전';
    if ($diff < 3600) return floor($diff / 60) . '분 전';
    if ($diff < 86400) return floor($diff / 3600) . '시간 전';
    if ($diff < 604800) return floor($diff / 86400) . '일 전';
    if ($diff < 2592000) return floor($diff / 604800) . '주 전';
    return date('Y.m.d', $ts);
}

/**
 * 포인트 적립 (gangnamunni_points 에 earn 행 추가)
 *
 * @param mysqli $connect
 * @param string|int $member_id
 * @param int $amount 적립 포인트(0 이하면 무시)
 * @param string $reason
 * @param string $reference
 * @return bool
 */
function earnPoints($connect, $member_id, $amount, $reason = '', $reference = '') {
    if (!$member_id || $amount <= 0) return false;
    $esc = mysqli_real_escape_string($connect, $member_id);
    $r = mysqli_query($connect, "SELECT COALESCE(SUM(CASE WHEN type='earn' THEN amount ELSE -amount END),0) as b FROM gangnamunni_points WHERE member_id='$esc'");
    $bal = $r ? (int)mysqli_fetch_assoc($r)['b'] : 0;
    $new = $bal + $amount;
    $st = mysqli_prepare($connect, "INSERT INTO gangnamunni_points (member_id,type,amount,balance,reason,reference) VALUES (?,'earn',?,?,?,?)");
    if (!$st) return false;
    mysqli_stmt_bind_param($st, 'siiss', $member_id, $amount, $new, $reason, $reference);
    $ok = mysqli_stmt_execute($st);
    mysqli_stmt_close($st);
    return $ok;
}
