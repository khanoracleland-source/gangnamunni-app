<?php
/**
 * 상담 신청 처리 (AJAX JSON API)
 * detail.php에서 fetch('consultation_submit.php') 호출
 * - 텍스트 필드 + 사진 첨부(최대 3장) 지원
 */

header('Content-Type: application/json');

// [1차 검수 프론트 #9 - 2026-05-28] 상담 신청 500 에러 픽스
// __DIR__/../dbconfig.php (hairwang_full/dbconfig.php) 는 HTTP 요청 시 권한/경로 문제로 500.
// gangnamunni/dbconfig.php (같은 디렉토리)를 우선 사용하도록 변경.
if (file_exists(__DIR__ . '/dbconfig.php')) {
    require_once __DIR__ . '/dbconfig.php';
} elseif (file_exists(__DIR__ . '/../dbconfig.php')) {
    require_once __DIR__ . '/../dbconfig.php';
} else {
    echo json_encode(['success' => false, 'message' => 'DB 설정 파일을 찾을 수 없습니다.']);
    exit;
}

$connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);

if (!$connect) {
    echo json_encode(['success' => false, 'message' => 'DB 연결 실패']);
    exit;
}

mysqli_set_charset($connect, 'utf8mb4');

// POST 데이터 받기
$hospital_id = isset($_POST['hospital_id']) ? intval($_POST['hospital_id']) : 0;
$hospital_name = isset($_POST['hospital_name']) ? trim($_POST['hospital_name']) : '';
$type = isset($_POST['type']) ? $_POST['type'] : 'general';
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';
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

// hospital_name이 없으면 DB에서 조회
if (empty($hospital_name)) {
    $stmt_h = mysqli_prepare($connect, "SELECT name FROM gangnamunni_hospitals WHERE id = ?");
    if ($stmt_h) {
        mysqli_stmt_bind_param($stmt_h, 'i', $hospital_id);
        mysqli_stmt_execute($stmt_h);
        $res_h = mysqli_stmt_get_result($stmt_h);
        $row_h = mysqli_fetch_assoc($res_h);
        $hospital_name = $row_h['name'] ?? '';
        mysqli_stmt_close($stmt_h);
    }
}

// 상담 신청 저장 (prepared statement)
$stmt = mysqli_prepare($connect,
    "INSERT INTO gangnamunni_consultations (hospital_id, hospital_name, consultation_type, name, phone, message, photo_urls, photo_consent, status, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())"
);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'DB 오류: ' . mysqli_error($connect)]);
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
        $ftype = $_FILES['photos']['type'][$i];

        if (empty($tmp) || !is_uploaded_file($tmp)) continue;
        if ($size > $max_size) continue;
        if (!in_array($ftype, $allowed_types)) continue;

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

echo json_encode([
    'success' => true,
    'message' => '상담 신청이 완료되었습니다.',
    'consultation_id' => $consultation_id,
    'photos' => count($uploaded_photos)
]);

mysqli_close($connect);
