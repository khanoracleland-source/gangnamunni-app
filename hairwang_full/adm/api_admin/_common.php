<?php
/**
 * API 관리자 공통 파일
 * API 관리자 페이지 공통 기능 제공
 * 작성일: 2026-01-19
 * 버전: 1.0
 */

// 그누보드 관리자 공통 파일 포함
include_once dirname(__FILE__).'/../_common.php';

// API 관리자 접근 권한 확인
if ($is_admin != 'super') {
    alert('접근 권한이 없습니다.', G5_URL);
}

// API 관련 상수 정의
if (!defined('G5_API_ADMIN_URL')) {
    define('G5_API_ADMIN_URL', G5_ADMIN_URL . '/api_admin');
}

// API 데이터베이스 테이블 정의 (자동 생성)
if (!isset($g5['api_key_table'])) {
    $table_prefix = defined('G5_TABLE_PREFIX') ? G5_TABLE_PREFIX : 'g5_';
    sql_query("
        CREATE TABLE IF NOT EXISTS `{$table_prefix}api_keys` (
          `ak_id` int(11) NOT NULL AUTO_INCREMENT,
          `ak_name` varchar(100) NOT NULL,
          `ak_key` varchar(64) NOT NULL,
          `ak_secret` varchar(255) NOT NULL,
          `ak_type` enum('sns','pg','all') NOT NULL DEFAULT 'all',
          `ak_ip_whitelist` text,
          `ak_status` tinyint(4) NOT NULL DEFAULT '1',
          `ak_created_at` datetime NOT NULL,
          `ak_expired_at` datetime,
          `ak_last_used` datetime,
          PRIMARY KEY (`ak_id`),
          UNIQUE KEY `ak_key` (`ak_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ", true);
    $g5['api_key_table'] = $table_prefix.'api_keys';
}

if (!isset($g5['api_ip_table'])) {
    $table_prefix = defined('G5_TABLE_PREFIX') ? G5_TABLE_PREFIX : 'g5_';
    sql_query("
        CREATE TABLE IF NOT EXISTS `{$table_prefix}api_ips` (
          `ai_id` int(11) NOT NULL AUTO_INCREMENT,
          `ak_id` int(11) NOT NULL,
          `ai_ip` varchar(45) NOT NULL,
          `ai_description` varchar(255),
          `ai_status` tinyint(4) NOT NULL DEFAULT '1',
          `ai_created_at` datetime NOT NULL,
          PRIMARY KEY (`ai_id`),
          KEY `ak_id` (`ak_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ", true);
    $g5['api_ip_table'] = $table_prefix.'api_ips';
}

if (!isset($g5['api_log_table'])) {
    $table_prefix = defined('G5_TABLE_PREFIX') ? G5_TABLE_PREFIX : 'g5_';
    sql_query("
        CREATE TABLE IF NOT EXISTS `{$table_prefix}api_logs` (
          `al_id` int(11) NOT NULL AUTO_INCREMENT,
          `ak_id` int(11),
          `al_endpoint` varchar(255) NOT NULL,
          `al_method` varchar(10) NOT NULL,
          `al_request_ip` varchar(45) NOT NULL,
          `al_request_data` text,
          `al_response_code` int(11),
          `al_response_data` text,
          `al_error` text,
          `al_execution_time` float,
          `al_created_at` datetime NOT NULL,
          PRIMARY KEY (`al_id`),
          KEY `ak_id` (`ak_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ", true);
    $g5['api_log_table'] = $table_prefix.'api_logs';
}

if (!isset($g5['sns_api_log_table'])) {
    $table_prefix = defined('G5_TABLE_PREFIX') ? G5_TABLE_PREFIX : 'g5_';
    sql_query("
        CREATE TABLE IF NOT EXISTS `{$table_prefix}sns_api_logs` (
          `sl_id` int(11) NOT NULL AUTO_INCREMENT,
          `ak_id` int(11),
          `sl_provider` varchar(50) NOT NULL,
          `sl_action` enum('login','register','error') NOT NULL,
          `sl_mb_id` varchar(20),
          `sl_identifier` varchar(255),
          `sl_success` tinyint(4) NOT NULL,
          `sl_error_code` varchar(50),
          `sl_error_message` text,
          `sl_custom_fields` text,
          `sl_created_at` datetime NOT NULL,
          PRIMARY KEY (`sl_id`),
          KEY `ak_id` (`ak_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ", true);
    $g5['sns_api_log_table'] = $table_prefix.'sns_api_logs';
}

if (!isset($g5['pg_api_orders_table'])) {
    $table_prefix = defined('G5_TABLE_PREFIX') ? G5_TABLE_PREFIX : 'g5_';
    sql_query("
        CREATE TABLE IF NOT EXISTS `{$table_prefix}pg_api_orders` (
          `po_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
          `ak_id` int(11) NOT NULL,
          `po_external_order_id` varchar(100) NOT NULL,
          `po_g5_order_id` bigint(20),
          `po_item_name` varchar(255),
          `po_amount` int(11) NOT NULL,
          `po_status` enum('pending','paid','cancelled','failed','refunded') NOT NULL DEFAULT 'pending',
          `po_pg_code` varchar(50),
          `po_pg_tid` varchar(100),
          `po_pg_response` text,
          `po_callback_url` varchar(255),
          `po_custom_fields` text,
          `po_created_at` datetime NOT NULL,
          `po_updated_at` datetime NOT NULL,
          PRIMARY KEY (`po_id`),
          UNIQUE KEY `po_external_order_id` (`po_external_order_id`),
          KEY `ak_id` (`ak_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ", true);
    $g5['pg_api_orders_table'] = $table_prefix.'pg_api_orders';
}

if (!isset($g5['api_fields_table'])) {
    $table_prefix = defined('G5_TABLE_PREFIX') ? G5_TABLE_PREFIX : 'g5_';
    sql_query("
        CREATE TABLE IF NOT EXISTS `{$table_prefix}api_fields` (
          `af_id` int(11) NOT NULL AUTO_INCREMENT,
          `af_name` varchar(50) NOT NULL,
          `af_code` varchar(50) NOT NULL,
          `af_type` enum('text','number','select','checkbox','textarea') NOT NULL,
          `af_category` enum('sns','pg','common') NOT NULL,
          `af_default_value` text,
          `af_options` text,
          `af_required` tinyint(4) NOT NULL DEFAULT '0',
          `af_status` tinyint(4) NOT NULL DEFAULT '1',
          `af_order` int(11) NOT NULL DEFAULT '0',
          `af_created_at` datetime NOT NULL,
          PRIMARY KEY (`af_id`),
          UNIQUE KEY `af_code` (`af_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ", true);
    $g5['api_fields_table'] = $table_prefix.'api_fields';
}

if (!isset($g5['api_field_values_table'])) {
    $table_prefix = defined('G5_TABLE_PREFIX') ? G5_TABLE_PREFIX : 'g5_';
    sql_query("
        CREATE TABLE IF NOT EXISTS `{$table_prefix}api_field_values` (
          `fv_id` int(11) NOT NULL AUTO_INCREMENT,
          `af_id` int(11) NOT NULL,
          `al_id` int(11),
          `sl_id` int(11),
          `po_id` bigint(20),
          `fv_value` text,
          `fv_created_at` datetime NOT NULL,
          PRIMARY KEY (`fv_id`),
          KEY `af_id` (`af_id`),
          KEY `al_id` (`al_id`),
          KEY `sl_id` (`sl_id`),
          KEY `po_id` (`po_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ", true);
    $g5['api_field_values_table'] = $table_prefix.'api_field_values';
}

// 관리자 타이틀
$g5['title'] = 'API 관리';

// 관리자 메뉴 설정
$amenu = array(
    array('600000', 'API 관리', G5_API_ADMIN_URL),
    array('600100', 'API 키 관리', G5_API_ADMIN_URL.'/keylist.php'),
    array('600200', 'IP 화이트리스트', G5_API_ADMIN_URL.'/iplist.php'),
    array('600300', 'API 로그', G5_API_ADMIN_URL.'/loglist.php'),
    array('600400', 'SNS 로그', G5_API_ADMIN_URL.'/sns/loglist.php'),
    array('600500', 'PG 주문 관리', G5_API_ADMIN_URL.'/pg/orderlist.php'),
    array('600600', '커스텀 필드', G5_API_ADMIN_URL.'/fieldlist.php')
);

// API 키 발급 함수
if (!function_exists('generate_api_key')) {
    function generate_api_key() {
        return bin2hex(random_bytes(32));
    }
}

// API 시크릿 발급 함수
if (!function_exists('generate_api_secret')) {
    function generate_api_secret() {
        return bin2hex(random_bytes(32));
    }
}
