<?php
include_once('../../common.php');
include_once('./_config.php');

// 관리자만
if (!$is_admin) {
    alert('관리자만 접근 가능합니다.');
}

// 테이블 생성
$sql = "
CREATE TABLE IF NOT EXISTS `".G5_ATTENDANCE_TABLE."` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mb_id` VARCHAR(50) NOT NULL DEFAULT '',
  `mood` VARCHAR(16) NOT NULL DEFAULT '',
  `message` VARCHAR(255) NOT NULL DEFAULT '',
  `att_date` DATE NOT NULL,
  `att_time` TIME NOT NULL,
  `reg_datetime` DATETIME NOT NULL,
  `ip` VARBINARY(16) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_mb_date` (`mb_id`,`att_date`),
  KEY `idx_date` (`att_date`),
  KEY `idx_mb` (`mb_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
sql_query($sql, true);

// 설정 테이블
$sql = "
CREATE TABLE IF NOT EXISTS `".G5_ATTENDANCE_SETTINGS_TABLE."` (
  `id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `every_days_n` INT NOT NULL DEFAULT 0,
  `every_days_points` INT NOT NULL DEFAULT 0,
  `daily_points` INT NOT NULL DEFAULT 0,
  `streak_days` INT NOT NULL DEFAULT 7,
  `streak_points` INT NOT NULL DEFAULT 0,
  `updated_by` VARCHAR(50) DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
sql_query($sql, true);

// 기본 설정 1행 보장 (daily_points 포함)
$row = sql_fetch("SELECT COUNT(*) AS cnt FROM `".G5_ATTENDANCE_SETTINGS_TABLE."` WHERE id=1");
if (!(int)$row['cnt']) {
    sql_query("INSERT INTO `".G5_ATTENDANCE_SETTINGS_TABLE."`
      (id, every_days_n, every_days_points, daily_points, streak_days, streak_points, updated_at)
      VALUES (1, 0, 0, 0, 7, 0, NOW())", true);
}

// 적립 로그 테이블
$sql = "
CREATE TABLE IF NOT EXISTS `".G5_ATTENDANCE_REWARD_LOG_TABLE."` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mb_id` VARCHAR(50) NOT NULL,
  `rule` VARCHAR(16) NOT NULL,          -- 'every' | 'streak'
  `uniq_key` VARCHAR(64) NOT NULL,      -- 중복 적립 방지 키
  `points` INT NOT NULL,
  `att_date` DATE NOT NULL,             -- 트리거된 출석일
  `content` VARCHAR(255) NOT NULL,
  `reg_datetime` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_rule_key` (`mb_id`,`rule`,`uniq_key`),
  KEY `idx_mb` (`mb_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
sql_query($sql, true);

alert('출석부 테이블이 설치되었습니다.', G5_PLUGIN_URL."/attendance/attendance.php");