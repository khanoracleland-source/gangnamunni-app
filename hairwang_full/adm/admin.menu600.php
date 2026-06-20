<?php
/**
 * 관리자 메뉴 파일 (API 관리)
 * API 관련 관리자 메뉴 정의
 * 작성일: 2026-01-19
 * 버전: 1.0
 */

if (!defined('G5_ADMIN_URL')) {
    exit;
}

// API 관리 메뉴
$menu['menu600'] = array(
    array('600100', 'API 관리 홈', G5_ADMIN_URL.'/api_admin/', 'api_admin'),
    array('600110', 'API 키 관리', G5_ADMIN_URL.'/api_admin/keylist.php', 'api_key'),
    array('600120', 'IP 화이트리스트', G5_ADMIN_URL.'/api_admin/iplist.php', 'api_ip'),
    array('600130', 'API 로그', G5_ADMIN_URL.'/api_admin/loglist.php', 'api_log'),
    array('600140', '필드 관리', G5_ADMIN_URL.'/api_admin/fieldlist.php', 'api_field'),
    array('', 'SNS 로그인 API', '', 'api_sns'),
    array('600210', 'SNS 로그인 로그', G5_ADMIN_URL.'/api_admin/sns/loginlog.php', 'api_sns_log'),
    array('600220', 'SNS 오류 로그', G5_ADMIN_URL.'/api_admin/sns/errorlog.php', 'api_sns_error'),
    array('600230', 'SNS 통계', G5_ADMIN_URL.'/api_admin/sns/statistics.php', 'api_sns_stats'),
    array('600240', 'SNS 테스트', G5_ADMIN_URL.'/api_admin/sns_test.php', 'api_sns_test'),
    array('', 'PG 결제 API', '', 'api_pg'),
    array('600310', 'PG 주문 목록', G5_ADMIN_URL.'/api_admin/pg/orderlist.php', 'api_pg_order'),
    array('600320', 'PG 결제 로그', G5_ADMIN_URL.'/api_admin/pg/paymentlog.php', 'api_pg_log'),
    array('600330', 'PG 통계', G5_ADMIN_URL.'/api_admin/pg/statistics.php', 'api_pg_stats'),
    array('600340', 'PG 테스트', G5_ADMIN_URL.'/api_admin/pg_test.php', 'api_pg_test'),
    array('600350', '필드 값 조회', G5_ADMIN_URL.'/api_admin/fieldvaluelist.php', 'api_field_value'),
);
