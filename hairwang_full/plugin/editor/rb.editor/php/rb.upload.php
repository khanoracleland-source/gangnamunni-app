<?php
include_once("./_common.php");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Nonce 관련 상수 정의 추가
if (!defined('FT_NONCE_UNIQUE_KEY'))
    define('FT_NONCE_UNIQUE_KEY', sha1($_SERVER['SERVER_SOFTWARE'] . G5_MYSQL_USER . session_id() . G5_TABLE_PREFIX));

if (!defined('FT_NONCE_SESSION_KEY'))
    define('FT_NONCE_SESSION_KEY', substr(md5(FT_NONCE_UNIQUE_KEY), 5));

if (!defined('FT_NONCE_DURATION'))
    define('FT_NONCE_DURATION', 60 * 60); // 1시간 유효

if (!defined('FT_NONCE_KEY'))
    define('FT_NONCE_KEY', '_nonce');

// 세션에서 Nonce 값을 가져옴
$session_nonce = isset($_SESSION['token_' . FT_NONCE_SESSION_KEY]) ? $_SESSION['token_' . FT_NONCE_SESSION_KEY] : '';

// 업로드 시 전달된 Nonce 값
$posted_nonce = isset($_POST['editor_nonce']) ? $_POST['editor_nonce'] : '';

// Nonce 검증 로그 추가
error_log("업로드 요청 Nonce 값: " . $posted_nonce);
error_log("서버 세션 Nonce 값: " . $session_nonce);

if (!$session_nonce || $posted_nonce !== $session_nonce) {
    error_log("Nonce 불일치: 업로드된 nonce = " . $posted_nonce . " | 세션 nonce = " . $session_nonce);
    echo json_encode(array("files" => array(array("error" => "잘못된 접근입니다. (Nonce 불일치)"))));
    exit;
}


// 업로드 디렉토리 설정
$ym = date('ym', G5_SERVER_TIME);
$data_dir = G5_DATA_PATH . '/editor/' . $ym . '/';
$data_url = G5_DATA_URL . '/editor/' . $ym . '/';

// 디렉토리가 없으면 생성
if (!is_dir($data_dir)) {
    if (!@mkdir($data_dir, G5_DIR_PERMISSION, true)) {
        error_log("업로드 디렉토리 생성 실패: " . $data_dir);
        echo json_encode(array("files" => array(array("error" => "업로드 디렉토리 생성에 실패했습니다."))));
        exit;
    }
    @chmod($data_dir, G5_DIR_PERMISSION);
}

// 파일 업로드 검증
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    error_log("파일 업로드 실패 (파일 정보 없음 또는 오류 코드 존재)");
    echo json_encode(array("files" => array(array("error" => "파일 업로드에 실패했습니다."))));
    exit;
}

// 파일 저장 처리
$tempfile = $_FILES['file']['tmp_name'];
$filename = time() . '_' . bin2hex(random_bytes(8)) . '_' . $_FILES['file']['name'];
$savefile = $data_dir . $filename;

if (!file_exists($tempfile)) {
    error_log("임시 파일이 존재하지 않음: " . $tempfile);
    echo json_encode(array("files" => array(array("error" => "임시 파일이 존재하지 않습니다."))));
    exit;
}

if (!move_uploaded_file($tempfile, $savefile)) {
    error_log("파일 저장 실패: " . $savefile);
    echo json_encode(array("files" => array(array("error" => "파일 저장에 실패했습니다."))));
    exit;
}

// 파일 권한 설정
@chmod($savefile, G5_FILE_PERMISSION);

// 업로드 성공 응답
$file_url = $data_url . $filename;
echo json_encode(array('files' => array(array('name' => $filename, 'url' => $file_url))));
?>
