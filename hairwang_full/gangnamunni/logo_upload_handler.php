<?php
/**
 * 로고 업로드 처리
 */

session_start();

// 로그인 체크
if (!isset($_SESSION['theme_admin_logged_in']) || $_SESSION['theme_admin_logged_in'] !== true) {
    die(json_encode(['success' => false, 'message' => '로그인이 필요합니다.']));
}

require_once __DIR__ . '/../dbconfig.php';

$connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
mysqli_set_charset($connect, 'utf8mb4');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['logo_upload'])) {
    $file = $_FILES['logo_upload'];

    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        die(json_encode(['success' => false, 'message' => '파일 업로드 중 오류가 발생했습니다.']));
    }

    if ($file['size'] > 5 * 1024 * 1024) { // 5MB
        die(json_encode(['success' => false, 'message' => '파일 크기는 5MB 이하여야 합니다.']));
    }

    // 파일 검증: 클라이언트가 보낸 MIME/파일명은 신뢰하지 않고 실제 내용으로 판별한다.
    // (SVG 는 스크립트를 담을 수 있고 리사이즈도 되지 않으므로 허용하지 않는다)
    $image_info = @getimagesize($file['tmp_name']);
    $detected_type = $image_info[2] ?? null;
    $ext_by_type = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg'];
    if (!isset($ext_by_type[$detected_type])) {
        die(json_encode(['success' => false, 'message' => '지원하지 않는 파일 형식입니다. (PNG/JPG)']));
    }

    // 저장 디렉토리
    $upload_dir = __DIR__ . '/assets/images/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // 원본 저장
    $ext = $ext_by_type[$detected_type]; // 확장자는 실제 이미지 형식에서만 결정
    $original_name = 'logo_uploaded_' . time() . '.' . $ext;
    $original_path = $upload_dir . $original_name;

    if (!move_uploaded_file($file['tmp_name'], $original_path)) {
        die(json_encode(['success' => false, 'message' => '파일 저장 중 오류가 발생했습니다.']));
    }

    // 여러 크기로 리사이징
    $sizes = [
        ['name' => 'logo_512.png', 'size' => 512],
        ['name' => 'logo_256.png', 'size' => 256],
        ['name' => 'logo_128.png', 'size' => 128],
        ['name' => 'logo_64.png', 'size' => 64],
        ['name' => 'logo_32.png', 'size' => 32],
        ['name' => 'favicon.png', 'size' => 16]
    ];

    $source_image = null;
    if ($detected_type === IMAGETYPE_PNG) {
        $source_image = @imagecreatefrompng($original_path);
    } elseif ($detected_type === IMAGETYPE_JPEG) {
        $source_image = @imagecreatefromjpeg($original_path);
    }

    if ($source_image) {
        list($orig_width, $orig_height) = getimagesize($original_path);

        foreach ($sizes as $size_info) {
            $new_size = $size_info['size'];
            $new_image = imagecreatetruecolor($new_size, $new_size);

            // 투명도 유지
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
            $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
            imagefilledrectangle($new_image, 0, 0, $new_size, $new_size, $transparent);

            imagecopyresampled($new_image, $source_image, 0, 0, 0, 0, $new_size, $new_size, $orig_width, $orig_height);
            imagepng($new_image, $upload_dir . $size_info['name']);
            imagedestroy($new_image);
        }

        imagedestroy($source_image);

        // DB 업데이트
        $logo_path = 'assets/images/logo_128.png';
        mysqli_query($connect, "UPDATE gangnamunni_theme_settings
                                SET setting_value = '{$logo_path}'
                                WHERE setting_key = 'site_logo_path'");

        // 이력 저장
        $admin_user = mysqli_real_escape_string($connect, (string) ($_SESSION['theme_admin_user'] ?? 'admin'));
        $action = mysqli_real_escape_string($connect, "로고 업로드: " . $file['name']);
        $ip = mysqli_real_escape_string($connect, (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        mysqli_query($connect, "INSERT INTO gangnamunni_theme_history (admin_user, action, ip_address)
                                VALUES ('{$admin_user}', '{$action}', '{$ip}')");

        echo json_encode([
            'success' => true,
            'message' => '로고가 성공적으로 업로드되었습니다.',
            'logo_path' => $logo_path
        ]);
    } else {
        @unlink($original_path); // 디코딩 실패한 파일은 남기지 않는다
        echo json_encode(['success' => false, 'message' => '이미지 처리 중 오류가 발생했습니다.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
}

mysqli_close($connect);
?>
