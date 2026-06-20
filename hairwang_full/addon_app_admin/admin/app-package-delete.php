<?php
/**
 * 앱 패키지 삭제 처리
 * @package AddonAppAdmin
 * @version 1.0.0
 */

include_once('./_common.php');

header('Content-Type: application/json; charset=utf-8');

// 권한 체크 제거됨
// if (!addon_app_admin_check_permission()) {
//     echo json_encode(array(
//         'success' => false,
//         'message' => '접근 권한이 없습니다.'
//     ));
//     exit;
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

// 앱 키 확인
$app_key = isset($_POST['app_key']) ? trim($_POST['app_key']) : '';

if (empty($app_key)) {
    echo json_encode(array(
        'success' => false,
        'message' => '앱 키가 제공되지 않았습니다.'
    ));
    exit;
}

// 앱 소스 정보 가져오기
$sql = " SELECT * FROM {$addon_app_admin_table_app_sources} WHERE app_key = '{$app_key}' ";
$app_source = sql_fetch($sql);

if (!$app_source) {
    echo json_encode(array(
        'success' => false,
        'message' => '앱을 찾을 수 없습니다.'
    ));
    exit;
}

// 파일 삭제 함수 (재귀적으로 디렉토리 삭제)
function delete_directory($dir) {
    if (!is_dir($dir)) {
        return false;
    }

    $items = scandir($dir);

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir . '/' . $item;

        if (is_dir($path)) {
            delete_directory($path);
        } else {
            @unlink($path);
        }
    }

    return @rmdir($dir);
}

// 1. ZIP 파일 삭제
if (file_exists($app_source['file_path'])) {
    @unlink($app_source['file_path']);
}

// 2. 압축 해제 디렉토리 삭제
if (is_dir($app_source['extract_path'])) {
    delete_directory($app_source['extract_path']);
}

// 3. 업로드 디렉토리 삭제
$upload_dir = dirname($app_source['file_path']);
if (is_dir($upload_dir)) {
    delete_directory($upload_dir);
}

// 4. 데이터베이스에서 앱 이미지 삭제
$sql = " DELETE FROM {$addon_app_admin_table_app_images} WHERE app_key = '{$app_key}' ";
sql_query($sql);

// 5. 데이터베이스에서 앱 소스 삭제
$sql = " DELETE FROM {$addon_app_admin_table_app_sources} WHERE app_key = '{$app_key}' ";
sql_query($sql);

// 활동 로그
addon_app_admin_log('app_delete', $app_key, "앱 삭제: {$app_source['app_name']} ({$app_source['platform']})");

// 성공 응답
echo json_encode(array(
    'success' => true,
    'message' => '앱이 성공적으로 삭제되었습니다.',
    'data' => array(
        'app_key' => $app_key,
        'app_name' => $app_source['app_name'],
        'platform' => $app_source['platform']
    )
));
?>
