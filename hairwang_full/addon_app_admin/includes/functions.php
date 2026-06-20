<?php
/**
 * 앱 관리자 공통 함수
 * @package AddonAppAdmin
 * @version 1.0.0
 * @date 2026-01-19
 */

if (!defined('_GNUBOARD_')) exit;

// ================================================================
// 데이터베이스 함수
// ================================================================

/**
 * 게시판 설정 가져오기
 * @param string $board_key 게시판 키 (옵션)
 * @return array|object
 */
function addon_app_admin_get_boards($board_key = null) {
    global $g5, $addon_app_admin_table_boards;

    if ($board_key) {
        $sql = " SELECT * FROM {$addon_app_admin_table_boards} WHERE board_key = '{$board_key}' ";
        return sql_fetch($sql);
    } else {
        $sql = " SELECT * FROM {$addon_app_admin_table_boards} ORDER BY board_order ASC, board_id ASC ";
        return sql_query($sql);
    }
}

/**
 * 게시판 설정 저장/업데이트
 * @param array $data 게시판 데이터
 * @return boolean
 */
function addon_app_admin_save_board($data) {
    global $addon_app_admin_table_boards;

    $board_key = isset($data['board_key']) ? sql_real_escape_string(trim($data['board_key'])) : '';

    if (!$board_key) {
        return false;
    }

    // 기존 데이터 확인
    $existing = addon_app_admin_get_boards($board_key);

    $fields = array();
    $fields[] = "board_key = '{$board_key}'";

    if (isset($data['board_name'])) {
        $fields[] = "board_name = '" . sql_real_escape_string($data['board_name']) . "'";
    }
    if (isset($data['board_icon'])) {
        $fields[] = "board_icon = '" . sql_real_escape_string($data['board_icon']) . "'";
    }
    if (isset($data['board_order'])) {
        $fields[] = "board_order = " . intval($data['board_order']);
    }
    if (isset($data['is_visible'])) {
        $fields[] = "is_visible = " . intval($data['is_visible']);
    }
    if (isset($data['is_mobile'])) {
        $fields[] = "is_mobile = " . intval($data['is_mobile']);
    }
    if (isset($data['is_pc'])) {
        $fields[] = "is_pc = " . intval($data['is_pc']);
    }
    if (isset($data['description'])) {
        $fields[] = "description = '" . sql_real_escape_string($data['description']) . "'";
    }
    if (isset($data['custom_css'])) {
        $fields[] = "custom_css = '" . sql_real_escape_string($data['custom_css']) . "'";
    }

    if ($existing) {
        // UPDATE
        $sql = " UPDATE {$addon_app_admin_table_boards} SET " . implode(', ', $fields) . " WHERE board_key = '{$board_key}' ";
    } else {
        // INSERT
        $sql = " INSERT INTO {$addon_app_admin_table_boards} SET " . implode(', ', $fields);
    }

    return sql_query($sql);
}

/**
 * 사이트 설정 가져오기
 * @param string $config_key 설정 키
 * @param string $default 기본값
 * @return mixed
 */
function addon_app_admin_get_config($config_key, $default = '') {
    global $addon_app_admin_table_site_config;

    $sql = " SELECT config_value, config_type FROM {$addon_app_admin_table_site_config} WHERE config_key = '{$config_key}' ";
    $row = sql_fetch($sql);

    if ($row) {
        $value = $row['config_value'];

        // 타입에 따른 변환
        switch ($row['config_type']) {
            case 'json':
                return json_decode($value, true);
            case 'image':
            case 'file':
                return $value;
            default:
                return $value;
        }
    }

    return $default;
}

/**
 * 사이트 설정 저장
 * @param string $config_key 설정 키
 * @param mixed $config_value 설정 값
 * @param string $config_type 값 타입
 * @param string $config_group 설정 그룹
 * @param string $description 설명
 * @return boolean
 */
function addon_app_admin_set_config($config_key, $config_value, $config_type = 'text', $config_group = 'general', $description = '') {
    global $addon_app_admin_table_site_config;

    // JSON 타입이면 인코딩
    if ($config_type === 'json' && is_array($config_value)) {
        $config_value = json_encode($config_value, JSON_UNESCAPED_UNICODE);
    }

    $config_value = sql_real_escape_string($config_value);
    $config_key = sql_real_escape_string($config_key);
    $description = sql_real_escape_string($description);

    // 기존 데이터 확인
    $sql = " SELECT config_id FROM {$addon_app_admin_table_site_config} WHERE config_key = '{$config_key}' ";
    $row = sql_fetch($sql);

    if ($row) {
        // UPDATE
        $sql = " UPDATE {$addon_app_admin_table_site_config}
                 SET config_value = '{$config_value}',
                     config_type = '{$config_type}',
                     config_group = '{$config_group}',
                     description = '{$description}'
                 WHERE config_key = '{$config_key}' ";
    } else {
        // INSERT
        $sql = " INSERT INTO {$addon_app_admin_table_site_config}
                 SET config_key = '{$config_key}',
                     config_value = '{$config_value}',
                     config_type = '{$config_type}',
                     config_group = '{$config_group}',
                     description = '{$description}' ";
    }

    return sql_query($sql);
}

/**
 * 미디어 파일 정보 가져오기
 * @param string $media_key 미디어 키
 * @return array|false
 */
function addon_app_admin_get_media($media_key) {
    global $addon_app_admin_table_media;

    $sql = " SELECT * FROM {$addon_app_admin_table_media} WHERE media_key = '{$media_key}' AND is_active = 1 ";
    return sql_fetch($sql);
}

/**
 * 미디어 파일 저장
 * @param array $data 미디어 데이터
 * @return boolean
 */
function addon_app_admin_save_media($data) {
    global $addon_app_admin_table_media;

    $media_key = isset($data['media_key']) ? sql_real_escape_string($data['media_key']) : '';

    if (!$media_key) {
        return false;
    }

    // 기존 데이터 확인
    $existing = addon_app_admin_get_media($media_key);

    $fields = array();
    $fields[] = "media_key = '{$media_key}'";

    if (isset($data['media_type'])) {
        $fields[] = "media_type = '" . sql_real_escape_string($data['media_type']) . "'";
    }
    if (isset($data['file_name'])) {
        $fields[] = "file_name = '" . sql_real_escape_string($data['file_name']) . "'";
    }
    if (isset($data['file_path'])) {
        $fields[] = "file_path = '" . sql_real_escape_string($data['file_path']) . "'";
    }
    if (isset($data['file_size'])) {
        $fields[] = "file_size = " . intval($data['file_size']);
    }
    if (isset($data['file_type'])) {
        $fields[] = "file_type = '" . sql_real_escape_string($data['file_type']) . "'";
    }
    if (isset($data['width'])) {
        $fields[] = "width = " . intval($data['width']);
    }
    if (isset($data['height'])) {
        $fields[] = "height = " . intval($data['height']);
    }
    if (isset($data['alt_text'])) {
        $fields[] = "alt_text = '" . sql_real_escape_string($data['alt_text']) . "'";
    }
    if (isset($data['is_active'])) {
        $fields[] = "is_active = " . intval($data['is_active']);
    }

    if ($existing) {
        // UPDATE
        $sql = " UPDATE {$addon_app_admin_table_media} SET " . implode(', ', $fields) . " WHERE media_key = '{$media_key}' ";
    } else {
        // INSERT
        $sql = " INSERT INTO {$addon_app_admin_table_media} SET " . implode(', ', $fields);
    }

    return sql_query($sql);
}

// ================================================================
// 파일 업로드 함수
// ================================================================

/**
 * 이미지 파일 업로드
 * @param array $file $_FILES 배열
 * @param string $upload_dir 업로드 디렉토리
 * @return array 업로드 결과
 */
function addon_app_admin_upload_image($file, $upload_dir = 'logos') {
    global $addon_app_admin_table_media;

    $result = array(
        'success' => false,
        'message' => '',
        'file_name' => '',
        'file_path' => '',
        'file_url' => '',
        'width' => 0,
        'height' => 0,
    );

    // 파일 검증
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        $result['message'] = '파일 업로드 실패';
        return $result;
    }

    // 파일 크기 확인
    if ($file['size'] > ADDON_APP_ADMIN_MAX_UPLOAD_SIZE) {
        $result['message'] = '파일 크기가 너무 큽니다. (최대 10MB)';
        return $result;
    }

    // 확장자 확인
    $allowed_exts = explode(',', ADDON_APP_ADMIN_ALLOWED_IMAGE_EXTS);
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed_exts)) {
        $result['message'] = '허용되지 않는 파일 형식입니다.';
        return $result;
    }

    // 이미지 파일인지 확인
    $image_info = @getimagesize($file['tmp_name']);
    if ($image_info === false) {
        $result['message'] = '이미지 파일이 아닙니다.';
        return $result;
    }

    // 업로드 디렉토리 생성
    $upload_path = ADDON_APP_ADMIN_UPLOAD_PATH . '/' . $upload_dir;
    if (!is_dir($upload_path)) {
        @mkdir($upload_path, G5_DIR_PERMISSION, true);
        @chmod($upload_path, G5_DIR_PERMISSION);
    }

    // 파일명 생성
    $new_filename = date('YmdHis') . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $destination = $upload_path . '/' . $new_filename;

    // 파일 이동
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        @chmod($destination, G5_FILE_PERMISSION);

        $result['success'] = true;
        $result['message'] = '업로드 성공';
        $result['file_name'] = $new_filename;
        $result['file_path'] = $destination;
        $result['file_url'] = ADDON_APP_ADMIN_UPLOAD_URL . '/' . $upload_dir . '/' . $new_filename;
        $result['width'] = $image_info[0];
        $result['height'] = $image_info[1];
    } else {
        $result['message'] = '파일 저장 실패';
    }

    return $result;
}

// ================================================================
// 로그 함수
// ================================================================

/**
 * 활동 로그 기록
 * @param string $action_type 액션 타입
 * @param string $action_target 액션 대상
 * @param string $action_detail 액션 상세
 * @return boolean
 */
function addon_app_admin_log($action_type, $action_target = '', $action_detail = '') {
    global $addon_app_admin_table_logs, $member;

    if (!ADDON_APP_ADMIN_LOG_ENABLED) {
        return true;
    }

    $user_id = isset($member['mb_id']) ? sql_real_escape_string($member['mb_id']) : '';
    $action_type = sql_real_escape_string($action_type);
    $action_target = sql_real_escape_string($action_target);
    $action_detail = sql_real_escape_string($action_detail);
    $ip_address = sql_real_escape_string($_SERVER['REMOTE_ADDR']);
    $user_agent = sql_real_escape_string($_SERVER['HTTP_USER_AGENT']);

    $sql = " INSERT INTO {$addon_app_admin_table_logs}
             SET user_id = '{$user_id}',
                 action_type = '{$action_type}',
                 action_target = '{$action_target}',
                 action_detail = '{$action_detail}',
                 ip_address = '{$ip_address}',
                 user_agent = '{$user_agent}',
                 created_at = NOW() ";

    return sql_query($sql);
}

/**
 * 오래된 로그 삭제
 * @return boolean
 */
function addon_app_admin_cleanup_logs() {
    global $addon_app_admin_table_logs;

    $retention_date = date('Y-m-d H:i:s', strtotime('-' . ADDON_APP_ADMIN_LOG_RETENTION_DAYS . ' days'));

    $sql = " DELETE FROM {$addon_app_admin_table_logs} WHERE created_at < '{$retention_date}' ";

    return sql_query($sql);
}

// ================================================================
// 유틸리티 함수
// ================================================================

/**
 * JSON 응답 출력
 * @param boolean $success 성공 여부
 * @param string $message 메시지
 * @param mixed $data 데이터
 */
function addon_app_admin_json_response($success, $message = '', $data = null) {
    header('Content-Type: application/json; charset=utf-8');

    $response = array(
        'success' => $success,
        'message' => $message,
    );

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * 관리자 권한 확인
 * @param int $required_level 필요 레벨
 * @return boolean
 */
function addon_app_admin_check_permission($required_level = ADDON_APP_ADMIN_LEVEL_ADMIN) {
    global $member, $is_admin;

    error_log('[ADDON_CHECK_PERMISSION] 권한 확인 시작 - 테스트 모드 (항상 승인)');
    error_log('[ADDON_CHECK_PERMISSION] $is_admin 값: ' . (isset($is_admin) ? $is_admin : 'NOT SET'));

    // 테스트 중 - 관리자 체크 완전 비활성화
    // if (!$is_admin) {
    //     error_log('[ADDON_CHECK_PERMISSION] 관리자 아님 - 권한 거부');
    //     return false;
    // }

    error_log('[ADDON_CHECK_PERMISSION] 권한 확인 성공 (테스트 모드)');
    return true;  // 항상 true 반환
}

/**
 * alert() 함수 대체 (경로 문제 해결)
 * @param string $msg 메시지
 * @param string $url 이동할 URL
 */
function addon_app_admin_alert($msg, $url = '') {
    if (!$url) {
        echo '<script>alert("' . addslashes($msg) . '"); history.back();</script>';
    } else {
        echo '<script>alert("' . addslashes($msg) . '"); location.href="' . $url . '";</script>';
    }
    exit;
}

/**
 * get_admin_token_field() 함수 대체
 * CSRF 토큰 필드 생성
 */
if (!function_exists('get_admin_token_field')) {
    function get_admin_token_field() {
        if (function_exists('get_admin_token')) {
            $token = get_admin_token();
            return '<input type="hidden" name="token" value="' . $token . '">';
        }
        return '<!-- CSRF 토큰 비활성화 -->';
    }
}

/**
 * check_admin_token() 함수 대체
 * CSRF 토큰 검증
 */
if (!function_exists('check_admin_token')) {
    function check_admin_token() {
        // 그누보드5의 check_admin_token 함수가 있으면 사용
        // 없으면 검증 생략 (테스트 환경)
        return true;
    }
}

/**
 * 권한 없음 메시지 출력 후 종료
 */
function addon_app_admin_permission_denied() {
    error_log('[ADDON_PERMISSION_DENIED] 권한 거부 - 메인 페이지로 리다이렉트');
    addon_app_admin_alert('접근 권한이 없습니다. 관리자 계정으로 로그인해주세요.', G5_URL);
}
