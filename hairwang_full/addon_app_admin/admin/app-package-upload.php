<?php
/**
 * 앱 패키지 업로드 처리
 * @package AddonAppAdmin
 * @version 1.0.0
 */

include_once('./_common.php');

header('Content-Type: application/json; charset=utf-8');

// 권한 체크 제거됨
// if (!addon_app_admin_check_permission()) {
    echo json_encode(array(
        'success' => false,
        'message' => '접근 권한이 없습니다.'
    ));
    exit;
}

// POST 요청만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array(
        'success' => false,
        'message' => '잘못된 요청입니다.'
    ));
    exit;
}

// 토큰 검증
check_admin_token();

// 파일 업로드 확인
if (!isset($_FILES['app_file']) || $_FILES['app_file']['error'] !== UPLOAD_ERR_OK) {
    $error_msg = '파일 업로드 오류';

    if (isset($_FILES['app_file']['error'])) {
        switch ($_FILES['app_file']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error_msg = '파일 크기가 너무 큽니다.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $error_msg = '파일이 부분적으로만 업로드되었습니다.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $error_msg = '파일이 업로드되지 않았습니다.';
                break;
            default:
                $error_msg = '알 수 없는 오류가 발생했습니다.';
        }
    }

    echo json_encode(array(
        'success' => false,
        'message' => $error_msg
    ));
    exit;
}

$upload_file = $_FILES['app_file'];
$platform = isset($_POST['platform']) ? $_POST['platform'] : '';
$app_name = isset($_POST['app_name']) ? trim($_POST['app_name']) : '';
$version = isset($_POST['version']) ? trim($_POST['version']) : '1.0.0';

// 플랫폼 검증
if (!in_array($platform, array('Android', 'iOS'))) {
    echo json_encode(array(
        'success' => false,
        'message' => '플랫폼을 선택해주세요.'
    ));
    exit;
}

// 앱 이름 검증
if (empty($app_name)) {
    echo json_encode(array(
        'success' => false,
        'message' => '앱 이름을 입력해주세요.'
    ));
    exit;
}

// 파일 확장자 검증
$file_ext = strtolower(pathinfo($upload_file['name'], PATHINFO_EXTENSION));
if ($file_ext !== 'zip') {
    echo json_encode(array(
        'success' => false,
        'message' => 'ZIP 파일만 업로드 가능합니다.'
    ));
    exit;
}

// 파일 크기 검증 (100MB)
$max_size = 100 * 1024 * 1024;
if ($upload_file['size'] > $max_size) {
    echo json_encode(array(
        'success' => false,
        'message' => '파일 크기는 100MB를 초과할 수 없습니다.'
    ));
    exit;
}

// 앱 키 생성
$app_key = 'app_' . time() . '_' . substr(md5($app_name . $platform), 0, 8);

// 업로드 디렉토리 생성
$upload_dir = ADDON_APP_ADMIN_DATA_PATH . '/uploads/apps/' . $app_key;
if (!is_dir($upload_dir)) {
    @mkdir($upload_dir, 0707, true);
    @chmod($upload_dir, 0707);
}

// 압축 해제 디렉토리
$extract_dir = ADDON_APP_ADMIN_DATA_PATH . '/apps/' . $app_key;
if (!is_dir($extract_dir)) {
    @mkdir($extract_dir, 0707, true);
    @chmod($extract_dir, 0707);
}

// 파일명 생성
$file_name = $app_key . '.zip';
$file_path = $upload_dir . '/' . $file_name;

// 파일 이동
if (!move_uploaded_file($upload_file['tmp_name'], $file_path)) {
    echo json_encode(array(
        'success' => false,
        'message' => '파일 저장 중 오류가 발생했습니다.'
    ));
    exit;
}

// ZIP 압축 해제
$zip = new ZipArchive;
if ($zip->open($file_path) === TRUE) {
    $zip->extractTo($extract_dir);
    $zip->close();
} else {
    // 압축 해제 실패 시 업로드 파일 삭제
    @unlink($file_path);

    echo json_encode(array(
        'success' => false,
        'message' => 'ZIP 파일 압축 해제에 실패했습니다.'
    ));
    exit;
}

// 데이터베이스에 앱 소스 정보 저장
$sql = " INSERT INTO {$addon_app_admin_table_app_sources}
         SET app_key = '{$app_key}',
             platform = '{$platform}',
             app_name = '" . sql_real_escape_string($app_name) . "',
             version = '" . sql_real_escape_string($version) . "',
             file_name = '{$file_name}',
             file_path = '" . sql_real_escape_string($file_path) . "',
             file_size = {$upload_file['size']},
             extract_path = '" . sql_real_escape_string($extract_dir) . "',
             status = 'processing',
             uploaded_by = '{$member['mb_id']}',
             uploaded_at = NOW() ";

if (!sql_query($sql)) {
    // DB 저장 실패 시 파일 삭제
    @unlink($file_path);
    addon_app_admin_rrmdir($extract_dir);

    echo json_encode(array(
        'success' => false,
        'message' => '데이터베이스 저장 중 오류가 발생했습니다.'
    ));
    exit;
}

// 이미지 스캔
$image_extensions = array('png', 'jpg', 'jpeg', 'gif', 'webp');
$images_found = 0;

// 재귀적으로 이미지 찾기
function scan_images($dir, $base_dir, $app_key, $image_extensions) {
    global $addon_app_admin_table_app_images, $images_found;

    if (!is_dir($dir)) return;

    $files = scandir($dir);

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;

        $full_path = $dir . '/' . $file;

        if (is_dir($full_path)) {
            scan_images($full_path, $base_dir, $app_key, $image_extensions);
        } else {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            if (in_array($ext, $image_extensions)) {
                // 이미지 정보 가져오기
                $image_info = @getimagesize($full_path);

                if ($image_info) {
                    list($width, $height) = $image_info;

                    // 상대 경로 계산
                    $relative_path = str_replace($base_dir . '/', '', $full_path);
                    $relative_path = str_replace('\\', '/', $relative_path);

                    // 웹 경로
                    $web_path = ADDON_APP_ADMIN_DATA_URL . '/apps/' . $app_key . '/' . $relative_path;

                    // 이미지 타입 추측 (경로 기반)
                    $image_type = 'other';
                    if (stripos($relative_path, 'icon') !== false) {
                        $image_type = 'icon';
                    } elseif (stripos($relative_path, 'splash') !== false || stripos($relative_path, 'launch') !== false) {
                        $image_type = 'splash';
                    } elseif (stripos($relative_path, 'logo') !== false) {
                        $image_type = 'logo';
                    } elseif (stripos($relative_path, 'drawable') !== false || stripos($relative_path, 'mipmap') !== false) {
                        $image_type = 'drawable';
                    } elseif (stripos($relative_path, 'assets') !== false || stripos($relative_path, 'Assets.xcassets') !== false) {
                        $image_type = 'assets';
                    }

                    // 데이터베이스에 저장
                    $sql = " INSERT INTO {$addon_app_admin_table_app_images}
                             SET app_key = '{$app_key}',
                                 image_type = '{$image_type}',
                                 image_path = '" . sql_real_escape_string($relative_path) . "',
                                 full_path = '" . sql_real_escape_string($full_path) . "',
                                 web_path = '" . sql_real_escape_string($web_path) . "',
                                 width = {$width},
                                 height = {$height},
                                 file_size = " . filesize($full_path) . ",
                                 created_at = NOW() ";

                    if (sql_query($sql)) {
                        $images_found++;
                    }
                }
            }
        }
    }
}

// 이미지 스캔 실행
scan_images($extract_dir, $extract_dir, $app_key, $image_extensions);

// 앱 소스 상태 업데이트
$sql = " UPDATE {$addon_app_admin_table_app_sources}
         SET status = 'completed',
             images_count = {$images_found},
             processed_at = NOW()
         WHERE app_key = '{$app_key}' ";
sql_query($sql);

// 활동 로그
addon_app_admin_log('app_upload', $app_key, "앱 업로드: {$app_name} ({$platform})");

// 성공 응답
echo json_encode(array(
    'success' => true,
    'message' => '앱이 성공적으로 업로드되었습니다.',
    'data' => array(
        'app_key' => $app_key,
        'app_name' => $app_name,
        'platform' => $platform,
        'version' => $version,
        'file_size' => $upload_file['size'],
        'images_count' => $images_found,
        'edit_url' => './app-image-editor.php?app_key=' . $app_key
    )
));
?>
