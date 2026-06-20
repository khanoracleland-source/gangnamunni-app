<?php
if (!defined('_GNUBOARD_')) exit;

// ------------------------------
// 신고 테이블/설정 테이블 보장
// ------------------------------
$report_table  = $g5['board_table'].'_report';
$report_config = $g5['board_table'].'_report_config';

// 1) 설정 테이블 
if (!sql_query("DESCRIBE `{$report_config}`", false)) {
    sql_query("
        CREATE TABLE `{$report_config}` (
          `id` TINYINT UNSIGNED NOT NULL DEFAULT 1 PRIMARY KEY,
          `enabled` TINYINT(1) NOT NULL DEFAULT 1,
          `hide_limit_post` INT UNSIGNED NOT NULL DEFAULT 5,
          `hide_limit_comment` INT UNSIGNED NOT NULL DEFAULT 5,
          `lock_threshold` INT UNSIGNED NOT NULL DEFAULT 5,
          `author_protect_level` TINYINT UNSIGNED NOT NULL DEFAULT 9,
          `disallow_self` TINYINT(1) NOT NULL DEFAULT 1,
          `icon_mode` VARCHAR(10) NOT NULL DEFAULT 'emoji',
          `reason_custom_enabled` TINYINT(1) NOT NULL DEFAULT 0,
          `reason_custom_list` TEXT,
          `note` TEXT,
          `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ", false);
}
sql_query("
    INSERT INTO `{$report_config}` (id)
    SELECT 1 FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM `{$report_config}` WHERE id=1)
", false);

// 2) 신고 내역 테이블
if (!sql_query("DESCRIBE `{$report_table}`", false)) {
    sql_query("
        CREATE TABLE `{$report_table}` (
          `rp_id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '신고 고유번호',
          `bo_table` VARCHAR(50) NOT NULL COMMENT '게시판 TABLE ID',
          `wr_id` INT(11) NOT NULL COMMENT '게시글 ID',
          `comment_id` INT(11) NOT NULL DEFAULT 0 COMMENT '댓글 ID(0=게시글)',
          `mb_id` VARCHAR(20) DEFAULT NULL COMMENT '신고자 아이디',
          `rp_reason` TEXT NOT NULL COMMENT '신고 사유',
          `rp_memo` TEXT,
          `rp_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '신고 일시',
          `rp_ip` VARCHAR(45) DEFAULT NULL COMMENT '신고자 IP',
          `rp_status` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '처리상태',
          PRIMARY KEY (`rp_id`),
          KEY `idx_bo_wr` (`bo_table`,`wr_id`,`comment_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='게시글/댓글 신고 내역'
    ", false);

    // 중복 방지용 유니크 키 추가
    sql_query("
        ALTER TABLE `{$report_table}`
        ADD UNIQUE KEY `uniq_report_target` (`bo_table`,`wr_id`,`comment_id`,`mb_id`)
    ", false);
} else {
    // 유니크 키 존재 여부 확인 후 없으면 추가
    $idx = sql_fetch("SHOW INDEX FROM `{$report_table}` WHERE Key_name='uniq_report_target'");
    if (!$idx) {
        sql_query("
            ALTER TABLE `{$report_table}`
            ADD UNIQUE KEY `uniq_report_target` (`bo_table`,`wr_id`,`comment_id`,`mb_id`)
        ", false);
    }
}

// 3) 성능 인덱스 (상태+날짜)
sql_query("
    ALTER TABLE `{$report_table}`
    ADD KEY `idx_status_datetime` (`rp_status`,`rp_datetime`)
", false);
// ------------------------------
// /테이블 보장 끝
// ------------------------------


if (!function_exists('g5_report_conf')) {
    function g5_report_conf($force_refresh = false) {
        global $g5;
        static $cached = null;
        if (!$force_refresh && $cached !== null) return $cached;

        $tbl = $g5['board_table'].'_report_config';

        // 기본값 (테이블 없거나 컬럼 누락 시 사용)
        $def = [
            'enabled'               => 1,
            'hide_limit_post'       => 5,
            'hide_limit_comment'    => 5,
            'lock_threshold'        => 5,
            'author_protect_level'  => 9,
            'disallow_self'         => 1,
            'icon_mode'             => 'emoji', // emoji | fa4 | fa5
            'reason_custom_enabled' => 0,
            'reason_custom_list'    => "스팸\n욕설\n음란물\n허위정보\n기타",
            'note'                  => '* 허위신고일 경우, 신고자의 서비스 활동이 제한될 수 있으니 유의하시어 신중하게 신고해주세요.',
        ];

        // 테이블이 없으면 바로 기본값
        if (!sql_query("DESCRIBE `{$tbl}`", false)) {
            return $cached = $def;
        }

        // 1행 로드(id=1), 누락 시 기본값 머지
        $row = sql_fetch("SELECT * FROM `{$tbl}` WHERE id = 1");
        $conf = array_merge($def, array_intersect_key((array)$row, $def));

        // 형변환(정수/불리언성 필드)
        $conf['enabled']               = (int)$conf['enabled'];
        $conf['hide_limit_post']       = (int)$conf['hide_limit_post'];
        $conf['hide_limit_comment']    = (int)$conf['hide_limit_comment'];
        $conf['lock_threshold']        = (int)$conf['lock_threshold'];
        $conf['author_protect_level']  = (int)$conf['author_protect_level'];
        $conf['disallow_self']         = (int)$conf['disallow_self'];
        $conf['reason_custom_enabled'] = (int)$conf['reason_custom_enabled'];
        // icon_mode / reason_custom_list / note 는 문자열 그대로

        return $cached = $conf;
    }
}

// 캐시 무시하고 즉시 재로드가 필요할 때 사용
if (!function_exists('g5_report_conf_reload')) {
    function g5_report_conf_reload() {
        return g5_report_conf(true);
    }
}

// ----------------------
// 헬퍼: SQL escape (순정 호환)
// ----------------------
if (!function_exists('g5_sql_esc')) {
    function g5_sql_esc($s) {
        if (function_exists('sql_real_escape_string')) {
            return sql_real_escape_string($s);
        }
        // 안전 폴백
        return addslashes($s);
    }
}

// ----------------------
// 헬퍼: 관리자 토큰 필드 (호환)
// ----------------------
if (!function_exists('get_admin_token_field_safe')) {
    function get_admin_token_field_safe() {
        if (function_exists('get_admin_token_field')) {
            return get_admin_token_field();
        }
        // get_admin_token_field 없을 때 최소한의 입력필드라도 출력
        $token = function_exists('get_admin_token') ? get_admin_token() : '';
        return '<input type="hidden" name="token" value="'.get_text($token).'">';
    }
}