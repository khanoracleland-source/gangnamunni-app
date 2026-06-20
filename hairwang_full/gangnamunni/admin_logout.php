<?php
/**
 * ============================================================================
 * [관리자 #로그아웃 - 2026-05-28] 관리자 로그아웃 페이지별 미작동 픽스
 * ============================================================================
 * 원인 : 기존 로그아웃 링크가 `dashboard.php?logout=1` 단일 경로 사용.
 *        다른 admin 페이지에서 로그아웃 클릭 시 dashboard.php로 이동하지만,
 *        dashboard.php의 logout 처리가 페이지 렌더링 흐름 후반에 위치하여
 *        세션 유지된 상태로 보임 → 검수자에게 "새로고침만 발생"으로 인식됨.
 * 변경 : 별도 admin_logout.php 진입점 생성. 세션 종료 후 dashboard 로 redirect.
 * 영향 범위 : 신규 파일 생성. 기존 dashboard.php logout 처리 코드 보존 (영향 없음).
 * ============================================================================
 */

session_start();

// 관리자 인증 헬퍼 (admin_logout 함수 포함)
require_once __DIR__ . '/includes/admin_auth.php';

// 세션 종료 (admin_auth.php의 admin_logout 함수 사용)
if (function_exists('admin_logout')) {
    admin_logout();
} else {
    // fallback : ad_admin_* 세션 키 명시 제거
    foreach ($_SESSION as $k => $v) {
        if (strpos($k, 'ad_admin_') === 0) {
            unset($_SESSION[$k]);
        }
    }
    session_regenerate_id(true);
}

// dashboard.php 로그인 화면으로 redirect (로그아웃 메시지 전달)
header('Location: dashboard.php?msg=' . urlencode('정상적으로 로그아웃되었습니다.'));
exit;
