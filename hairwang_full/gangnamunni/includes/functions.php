<?php
/**
 * 공통 함수 모음
 * API 및 에디터에서 사용
 */

/**
 * JSON 응답 출력 후 종료
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * JSON 에러 응답
 */
function jsonError($message, $statusCode = 400) {
    jsonResponse(['success' => false, 'message' => $message], $statusCode);
}

/**
 * JSON 성공 응답
 */
function jsonSuccess($data = [], $message = 'OK') {
    $response = array_merge(['success' => true, 'message' => $message], $data);
    jsonResponse($response);
}

/**
 * XSS 방지용 문자열 정리
 */
function sanitizeInput($str) {
    if (is_null($str)) return '';
    $str = trim($str);
    $str = htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    return $str;
}

/**
 * HTML 에디터 콘텐츠 정리 (허용된 태그 + 속성 화이트리스트)
 */
function sanitizeHtml($html) {
    if (is_null($html) || trim($html) === '') return '';

    // 허용 태그
    $allowed = '<p><br><strong><em><u><s><ul><ol><li><h1><h2><h3><blockquote><a><img><span>';
    $html = strip_tags($html, $allowed);

    // 위험한 속성 제거 (on* 이벤트 핸들러 - 멀티라인 대응)
    $html = preg_replace('/\bon\w+\s*=\s*["\'][^"\']*["\']/si', '', $html);
    $html = preg_replace('/\bon\w+\s*=\s*[^\s>]+/si', '', $html);

    // style 속성 완전 제거 (CSS expression/url() XSS 방지)
    $html = preg_replace('/\bstyle\s*=\s*["\'][^"\']*["\']/si', '', $html);
    $html = preg_replace('/\bstyle\s*=\s*[^\s>]+/si', '', $html);

    // 위험한 프로토콜 차단 (javascript:, data:, vbscript:)
    $html = preg_replace('/\bhref\s*=\s*["\']?\s*(javascript|data|vbscript)\s*:/si', 'href="#', $html);
    $html = preg_replace('/\bsrc\s*=\s*["\']?\s*(javascript|data|vbscript)\s*:/si', 'src="', $html);

    // 허용된 속성만 남기기 (href, src, alt, class, target)
    // <a> 태그: href, target, class만 허용
    $html = preg_replace_callback('/<a\b([^>]*)>/si', function($m) {
        $attrs = '';
        if (preg_match('/\bhref\s*=\s*"([^"]*)"/i', $m[1], $h)) {
            $href = $h[1];
            // http/https/#만 허용
            if (preg_match('/^(https?:\/\/|#|\/)/i', $href)) {
                $attrs .= ' href="' . $href . '"';
            } else {
                $attrs .= ' href="#"';
            }
        }
        if (preg_match('/\btarget\s*=\s*"(_blank|_self)"/i', $m[1], $t)) {
            $attrs .= ' target="' . $t[1] . '" rel="noopener noreferrer"';
        }
        return '<a' . $attrs . '>';
    }, $html);

    // <img> 태그: src, alt, class만 허용
    $html = preg_replace_callback('/<img\b([^>]*)>/si', function($m) {
        $attrs = '';
        if (preg_match('/\bsrc\s*=\s*"([^"]*)"/i', $m[1], $s)) {
            $src = $s[1];
            if (preg_match('/^(https?:\/\/|\/)/i', $src)) {
                $attrs .= ' src="' . $src . '"';
            }
        }
        if (preg_match('/\balt\s*=\s*"([^"]*)"/i', $m[1], $a)) {
            $attrs .= ' alt="' . htmlspecialchars($a[1], ENT_QUOTES, 'UTF-8') . '"';
        }
        return '<img' . $attrs . '>';
    }, $html);

    return $html;
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
 */
function generateUniqueFilename($extension) {
    return date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
}

/**
 * 이미지 리사이즈 (최대 너비 기준)
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
 */
function createThumbnail($sourcePath, $destPath, $thumbWidth = 300) {
    return resizeImage($sourcePath, $destPath, $thumbWidth);
}

/**
 * EXIF 회전 보정
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
 */
function validateCsrfToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * 날짜 포맷 (상대 시간)
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
