<?php
/**
 * 상담 신청 처리 (AJAX JSON API)
 * detail.php에서 fetch('consultation_submit.php') 호출
 * - 텍스트 필드 + 사진 첨부(최대 3장) 지원
 */

header('Content-Type: application/json');

require_once __DIR__ . '/includes/init.php';

if (!$connect) {
    echo json_encode(['success' => false, 'message' => 'DB 연결 실패']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '허용되지 않은 요청입니다.']);
    exit;
}

$post_string = static function (string $key, string $default = ''): string {
    $value = $_POST[$key] ?? $default;
    return is_string($value) || is_numeric($value) ? trim(stripslashes((string)$value)) : $default;
};
$posted_csrf = $post_string('csrf_token');
if ($posted_csrf === '' || !hash_equals((string)($_SESSION['consultation_csrf_token'] ?? ''), $posted_csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '잘못된 요청입니다. 페이지를 새로고침해주세요.']);
    exit;
}

// Prevent accidental double-submit and basic automated flooding per session.
$now = time();
$attempts = array_values(array_filter((array)($_SESSION['consultation_attempts'] ?? []),
    static fn ($timestamp) => is_int($timestamp) && $timestamp > $now - 600));
if (count($attempts) >= 5) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => '요청이 너무 많습니다. 잠시 후 다시 시도해주세요.']);
    exit;
}
$attempts[] = $now;
$_SESSION['consultation_attempts'] = $attempts;

// POST 데이터 받기
$hospital_id = isset($_POST['hospital_id']) ? intval($_POST['hospital_id']) : 0;
$hospital_name = '';
$type = $post_string('type', 'general');
$name = $post_string('name');
$phone = $post_string('phone');
$message = $post_string('message');
$photo_consent = isset($_POST['photo_consent']) ? intval($_POST['photo_consent']) : 0;

// 유효성 검사
if ($hospital_id <= 0) {
    echo json_encode(['success' => false, 'message' => '병원 정보가 없습니다.']);
    exit;
}

if (empty($name) || empty($phone)) {
    echo json_encode(['success' => false, 'message' => '이름과 연락처를 입력해주세요.']);
    exit;
}

if (!in_array($type, ['general', 'doctor', 'kakao'], true)
    || mb_strlen($name) > 100 || mb_strlen($phone) > 30 || mb_strlen($message) > 3000
    || !preg_match('/^[0-9+()\-\s]{7,30}$/', $phone)) {
    echo json_encode(['success' => false, 'message' => '입력한 내용을 확인해주세요.']);
    exit;
}

// hospital_name이 없으면 DB에서 조회
if (empty($hospital_name)) {
    $stmt_h = mysqli_prepare($connect, "SELECT name FROM gangnamunni_hospitals WHERE id = ? LIMIT 1");
    if ($stmt_h) {
        mysqli_stmt_bind_param($stmt_h, 'i', $hospital_id);
        mysqli_stmt_execute($stmt_h);
        $res_h = mysqli_stmt_get_result($stmt_h);
        $row_h = mysqli_fetch_assoc($res_h);
        $hospital_name = $row_h['name'] ?? '';
        mysqli_stmt_close($stmt_h);
    }
}
if ($hospital_name === '') {
    echo json_encode(['success' => false, 'message' => '병원 정보를 확인할 수 없습니다.']);
    exit;
}

$has_photos = !empty($_FILES['photos']) && !empty($_FILES['photos']['name'][0]);
if ($has_photos && $photo_consent !== 1) {
    echo json_encode(['success' => false, 'message' => '사진 첨부 동의가 필요합니다.']);
    exit;
}

// 상담 신청 저장 (prepared statement)
$stmt = mysqli_prepare($connect,
    "INSERT INTO gangnamunni_consultations (hospital_id, hospital_name, consultation_type, name, phone, message, photo_urls, photo_consent, status, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())"
);

if (!$stmt) {
    error_log('[consultation] prepare failed: ' . mysqli_error($connect));
    echo json_encode(['success' => false, 'message' => '상담 신청을 처리할 수 없습니다.']);
    exit;
}

// 사진은 INSERT 후 consultation_id 확보 뒤 업로드 → 나중에 UPDATE
$photo_urls_json = null;

mysqli_stmt_bind_param($stmt, 'issssssi', $hospital_id, $hospital_name, $type, $name, $phone, $message, $photo_urls_json, $photo_consent);

if (!mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => false, 'message' => '상담 신청 중 오류가 발생했습니다.']);
    mysqli_stmt_close($stmt);
    exit;
}

$consultation_id = mysqli_insert_id($connect);
mysqli_stmt_close($stmt);

// === 사진 업로드 처리 ===
$uploaded_photos = [];
if (!empty($_FILES['photos']) && !empty($_FILES['photos']['name'][0])) {
    $max_files = 3;
    $max_size = 5 * 1024 * 1024; // 5MB
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    $upload_dir = __DIR__ . '/uploads/consultations/' . $consultation_id . '/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0775, true);
    }

    $count = min(count($_FILES['photos']['name']), $max_files);
    for ($i = 0; $i < $count; $i++) {
        $tmp = $_FILES['photos']['tmp_name'][$i];
        $size = $_FILES['photos']['size'][$i];
        $upload_error = (int)($_FILES['photos']['error'][$i] ?? UPLOAD_ERR_NO_FILE);

        if ($upload_error !== UPLOAD_ERR_OK || empty($tmp) || !is_uploaded_file($tmp)) continue;
        if ($size <= 0 || $size > $max_size) continue;
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $ftype = $finfo ? finfo_file($finfo, $tmp) : '';
        if ($finfo) finfo_close($finfo);
        if (!in_array($ftype, $allowed_types, true) || @getimagesize($tmp) === false) continue;

        // 파일명 안전 생성
        $ext = 'jpg';
        if ($ftype === 'image/png') $ext = 'png';
        elseif ($ftype === 'image/gif') $ext = 'gif';
        elseif ($ftype === 'image/webp') $ext = 'webp';
        $filename = time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
        $dest = $upload_dir . $filename;

        if (move_uploaded_file($tmp, $dest)) {
            chmod($dest, 0644);
            $uploaded_photos[] = 'uploads/consultations/' . $consultation_id . '/' . $filename;
        }
    }

    // DB에 사진 경로 업데이트
    if (!empty($uploaded_photos)) {
        $photo_json = json_encode($uploaded_photos, JSON_UNESCAPED_SLASHES);
        $stmt_u = mysqli_prepare($connect, "UPDATE gangnamunni_consultations SET photo_urls = ? WHERE id = ?");
        if ($stmt_u) {
            mysqli_stmt_bind_param($stmt_u, 'si', $photo_json, $consultation_id);
            mysqli_stmt_execute($stmt_u);
            mysqli_stmt_close($stmt_u);
        }
    }
}

// Rotate after a successful state-changing request.
$_SESSION['consultation_csrf_token'] = bin2hex(random_bytes(32));

echo json_encode([
    'success' => true,
    'message' => '상담 신청이 완료되었습니다.',
    'consultation_id' => $consultation_id,
    'photos' => count($uploaded_photos),
    'csrf_token' => $_SESSION['consultation_csrf_token'],
]);

mysqli_close($connect);
