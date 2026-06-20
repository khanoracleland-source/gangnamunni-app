<?php
/**
 * 앱 관리자 공통 파일
 * @package AddonAppAdmin
 * @version 1.0.0
 * @date 2026-01-19
 */

// 그누보드 경로 설정 (중복 정의 방지)
if (!defined('G5_PATH')) {
    $g5_path = realpath(dirname(__FILE__) . '/../../');
    define('G5_PATH', $g5_path);
    error_log('[ADDON_APP_ADMIN] G5_PATH 설정: ' . G5_PATH);
} else {
    // 이미 정의되어 있으면 기존 값 사용
    $g5_path = G5_PATH;
    error_log('[ADDON_APP_ADMIN] G5_PATH 이미 정의됨: ' . G5_PATH);
}

// 세션은 common.php에서 자동 시작 (직접 시작하면 설정 충돌 발생)
// if (session_status() === PHP_SESSION_NONE) {
//     @session_start();
//     error_log('[ADDON_APP_ADMIN] 세션 시작됨');
// }

// 그누보드 공통 파일 로드
$common_file = G5_PATH . '/common.php';
error_log('[ADDON_APP_ADMIN] common.php 경로: ' . $common_file);
error_log('[ADDON_APP_ADMIN] common.php 존재 여부: ' . (file_exists($common_file) ? 'YES' : 'NO'));

if (file_exists($common_file)) {
    error_log('[ADDON_APP_ADMIN] common.php 로드 시작');
    include_once($common_file);
    error_log('[ADDON_APP_ADMIN] common.php 로드 완료');

    // 로그인 정보 재확인
    if (isset($member) && is_array($member)) {
        error_log('[ADDON_APP_ADMIN] common.php 로드 후 mb_id: ' . (isset($member['mb_id']) ? $member['mb_id'] : 'EMPTY'));
        error_log('[ADDON_APP_ADMIN] common.php 로드 후 mb_name: ' . (isset($member['mb_name']) ? $member['mb_name'] : 'EMPTY'));
    }

    // DB 연결 확인
    if (isset($connect_db)) {
        error_log('[ADDON_APP_ADMIN] DB 연결 객체 존재: YES');
    } else {
        error_log('[ADDON_APP_ADMIN] DB 연결 객체 존재: NO');
    }

    // G5 상수 확인
    error_log('[ADDON_APP_ADMIN] G5_MYSQL_HOST 정의됨: ' . (defined('G5_MYSQL_HOST') ? 'YES' : 'NO'));
    error_log('[ADDON_APP_ADMIN] G5_MYSQL_USER 정의됨: ' . (defined('G5_MYSQL_USER') ? 'YES' : 'NO'));
    error_log('[ADDON_APP_ADMIN] G5_MYSQL_DB 정의됨: ' . (defined('G5_MYSQL_DB') ? 'YES' : 'NO'));
} else {
    error_log('[ADDON_APP_ADMIN] common.php 파일을 찾을 수 없습니다!');
    die('그누보드 common.php 파일을 찾을 수 없습니다. 경로: ' . $common_file);
}

// 앱 관리자 설정 파일 로드
$config_file = G5_PATH . '/addon_app_admin/includes/config.php';
error_log('[ADDON_APP_ADMIN] config.php 경로: ' . $config_file);

if (!defined('ADDON_APP_ADMIN_PATH')) {
    if (file_exists($config_file)) {
        error_log('[ADDON_APP_ADMIN] config.php 로드 시작');
        include_once($config_file);
        error_log('[ADDON_APP_ADMIN] config.php 로드 완료');
    } else {
        error_log('[ADDON_APP_ADMIN] config.php 파일을 찾을 수 없습니다!');
    }
}

$functions_file = G5_PATH . '/addon_app_admin/includes/functions.php';
error_log('[ADDON_APP_ADMIN] functions.php 경로: ' . $functions_file);

if (file_exists($functions_file)) {
    error_log('[ADDON_APP_ADMIN] functions.php 로드 시작');
    include_once($functions_file);
    error_log('[ADDON_APP_ADMIN] functions.php 로드 완료');
} else {
    error_log('[ADDON_APP_ADMIN] functions.php 파일을 찾을 수 없습니다!');
    die('functions.php 파일을 찾을 수 없습니다. 경로: ' . $functions_file);
}

// 세션 정보 디버깅
error_log('[ADDON_APP_ADMIN] 세션 디버깅 시작');
error_log('[ADDON_APP_ADMIN] session_id: ' . session_id());
error_log('[ADDON_APP_ADMIN] $_SESSION 존재: ' . (isset($_SESSION) ? 'YES' : 'NO'));

if (isset($_SESSION)) {
    error_log('[ADDON_APP_ADMIN] $_SESSION[ss_mb_id]: ' . (isset($_SESSION['ss_mb_id']) ? $_SESSION['ss_mb_id'] : 'NOT SET'));
    error_log('[ADDON_APP_ADMIN] $_SESSION 키들: ' . implode(', ', array_keys($_SESSION)));
}

// $member 배열이 비어있으면 세션에서 직접 로드 시도
if (isset($member) && is_array($member) && empty($member['mb_id'])) {
    error_log('[ADDON_APP_ADMIN] $member 배열이 비어있음 - 세션에서 직접 로드 시도');

    // 세션에서 회원 ID 가져오기
    if (isset($_SESSION['ss_mb_id']) && $_SESSION['ss_mb_id']) {
        error_log('[ADDON_APP_ADMIN] 세션에서 mb_id 발견: ' . $_SESSION['ss_mb_id']);

        // get_member() 함수 사용 (common.php와 동일한 방식)
        if (function_exists('get_member')) {
            $member = get_member($_SESSION['ss_mb_id']);

            if ($member && !empty($member['mb_id'])) {
                error_log('[ADDON_APP_ADMIN] get_member()로 회원 정보 로드 성공: ' . $member['mb_name'] . ' (' . $member['mb_id'] . ')');
            } else {
                error_log('[ADDON_APP_ADMIN] get_member() 실행했지만 회원 정보 없음');
            }
        } else {
            error_log('[ADDON_APP_ADMIN] get_member() 함수가 없음 - 직접 조회');

            // get_member() 함수가 없으면 직접 DB 조회
            if (isset($g5['member_table'])) {
                $mb_id_escaped = sql_real_escape_string($_SESSION['ss_mb_id']);
                $sql = " SELECT * FROM {$g5['member_table']} WHERE mb_id = '{$mb_id_escaped}' ";
                $member = sql_fetch($sql);

                if ($member && !empty($member['mb_id'])) {
                    error_log('[ADDON_APP_ADMIN] DB 직접 조회로 회원 정보 로드 성공: ' . $member['mb_name']);
                } else {
                    error_log('[ADDON_APP_ADMIN] DB 직접 조회 실패');
                }
            }
        }
    } else {
        error_log('[ADDON_APP_ADMIN] 세션에 ss_mb_id가 없음');
    }
} else if (isset($member) && is_array($member) && !empty($member['mb_id'])) {
    error_log('[ADDON_APP_ADMIN] $member 배열에 이미 데이터 있음: ' . $member['mb_id']);
}

// 관리자 체크 완전 제거 - 테스트 중
error_log('[ADDON_APP_ADMIN] $is_admin 값: ' . (isset($is_admin) ? $is_admin : 'NOT SET'));
error_log('[ADDON_APP_ADMIN] $member 배열 존재: ' . (isset($member) ? 'YES' : 'NO'));
error_log('[ADDON_APP_ADMIN] _common.php 로드 완료 - 관리자 체크 생략');

// 콘솔 디버그 정보 준비
$GLOBALS['addon_debug'] = array(
    'common_loaded' => true,
    'g5_path' => G5_PATH,
    'is_admin' => isset($is_admin) ? $is_admin : 'NOT SET',
    'member_exists' => isset($member),
    'member_is_array' => isset($member) && is_array($member),
    'member_count' => isset($member) && is_array($member) ? count($member) : 0,
    'member_keys' => isset($member) && is_array($member) ? array_keys($member) : array(),
    'member_data' => array(),
    'session_info' => array(
        'session_started' => session_status() === PHP_SESSION_ACTIVE,
        'session_id' => session_id(),
        'cookie_isset' => isset($_COOKIE)
    )
);

// $member 배열의 모든 키를 출력 (디버깅용)
if (isset($member) && is_array($member)) {
    foreach ($member as $key => $value) {
        if (!is_array($value) && !is_object($value)) {
            $GLOBALS['addon_debug']['member_data'][$key] = $value;
        }
    }

    // 로그에도 기록
    error_log('[ADDON_APP_ADMIN] $member 배열 키: ' . implode(', ', array_keys($member)));
    error_log('[ADDON_APP_ADMIN] mb_id: ' . (isset($member['mb_id']) ? $member['mb_id'] : 'NOT SET'));
    error_log('[ADDON_APP_ADMIN] mb_name: ' . (isset($member['mb_name']) ? $member['mb_name'] : 'NOT SET'));
}

// G5 설정 정보
$GLOBALS['addon_debug']['g5_config'] = array(
    'board_table' => isset($g5['board_table']) ? $g5['board_table'] : 'NOT SET',
    'url' => isset($g5['url']) ? $g5['url'] : 'NOT SET',
    'bbs_url' => isset($g5['bbs_url']) ? $g5['bbs_url'] : 'NOT SET'
);

// 애드온 테이블 정보
$GLOBALS['addon_debug']['addon_tables'] = array(
    'boards' => isset($addon_app_admin_table_boards) ? $addon_app_admin_table_boards : 'NOT SET',
    'media' => isset($addon_app_admin_table_media) ? $addon_app_admin_table_media : 'NOT SET',
    'app_sources' => isset($addon_app_admin_table_app_sources) ? $addon_app_admin_table_app_sources : 'NOT SET',
    'logs' => isset($addon_app_admin_table_logs) ? $addon_app_admin_table_logs : 'NOT SET'
);
