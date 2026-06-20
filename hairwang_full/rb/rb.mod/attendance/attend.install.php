<?php
if (!defined('_GNUBOARD_')) exit;

// 테이블 존재 검사 함수
function rb_attend_table_exists($table_name){
    $row = sql_fetch("show tables like '".sql_real_escape_string($table_name)."' ");
    return $row ? true : false;
}

// 관리자 진입시 한번만 호출해서 부족한 테이블을 생성
function rb_attend_install_if_needed(){
    global $is_admin;

    // 관리자만 수행
    if ($is_admin !== 'super') return;

    // config 테이블
    if (!rb_attend_table_exists('rb_attend_config')) {
        sql_query("
        CREATE TABLE rb_attend_config (
          cf_id INT UNSIGNED NOT NULL DEFAULT 1,
          week_streak_point INT NOT NULL DEFAULT 0,
          month_streak_point INT NOT NULL DEFAULT 0,
          year_streak_point INT NOT NULL DEFAULT 0,
          bonus_rank1 INT NOT NULL DEFAULT 0,
          bonus_rank2 INT NOT NULL DEFAULT 0,
          bonus_rank3 INT NOT NULL DEFAULT 0,
          week_streak_len INT NOT NULL DEFAULT 3,
          month_streak_len INT NOT NULL DEFAULT 7,
          year_streak_len INT NOT NULL DEFAULT 15,
          PRIMARY KEY (cf_id)
        )
        ");
        // 시드
        sql_query("INSERT INTO rb_attend_config (cf_id) VALUES (1)");
    }

    // attendance 테이블
    if (!rb_attend_table_exists('rb_attendance')) {
        sql_query("
        CREATE TABLE rb_attendance (
          at_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          mb_id VARCHAR(50) NOT NULL,
          ymd CHAR(8) NOT NULL,
          at_datetime DATETIME NOT NULL,
          at_ip VARCHAR(45) NOT NULL,
          at_content TEXT NULL,
          at_rank TINYINT NULL,
          PRIMARY KEY (at_id),
          KEY idx_mb_day (mb_id, ymd),
          KEY idx_day_rank (ymd, at_rank)
        )
        ");
    }

    // streak 스냅샷 테이블
    if (!rb_attend_table_exists('rb_attend_streak')) {
        sql_query("
        CREATE TABLE rb_attend_streak (
          mb_id VARCHAR(50) NOT NULL,
          last_ymd CHAR(8) NOT NULL,
          cont_days INT NOT NULL DEFAULT 0,
          PRIMARY KEY (mb_id)
        )
        ");
    }
}
