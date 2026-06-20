<?php
/**
 * DB 설정 (Override 지원)
 * - dbconfig.override.php 파일이 있으면 해당 설정으로 덮어씀
 * - 없으면 운영 DB(hms_saas) 사용
 */

// 기본값 (운영 DB)
$_DB_HOST = 'localhost';
$_DB_PORT = '3306';
$_DB_USER = 'root';
$_DB_PASSWORD = '<REMOVED_DB_PASSWORD>';
$_DB_NAME = 'hms_saas';

// Override 파일 확인
$_override_file = __DIR__ . '/dbconfig.override.php';
if (file_exists($_override_file)) {
    require_once $_override_file;
    // override 파일에서 $_DB_NAME 등을 재정의
}

// 상수 정의 (중복 방지)
if (!defined('G5_MYSQL_HOST')) {
    define('G5_MYSQL_HOST', $_DB_HOST);
    define('G5_MYSQL_PORT', $_DB_PORT);
    define('G5_MYSQL_USER', $_DB_USER);
    define('G5_MYSQL_PASSWORD', $_DB_PASSWORD);
    define('G5_MYSQL_DB', $_DB_NAME);
    define('G5_TABLE_PREFIX', 'g5_');
    define('INDUSTRY_PRESET', 'medical_beauty');
}
