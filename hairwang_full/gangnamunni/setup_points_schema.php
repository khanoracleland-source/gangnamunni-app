<?php
/**
 * ============================================================
 * 포인트 원장(#포인트제도) 스키마 설치 — 웹 1회 실행용.
 * ------------------------------------------------------------
 * sql/points_ledger.sql 과 동일한 결과를 만든다. 단, DELIMITER/프로시저는
 * PHP 로직으로 대체하여 mysqli_query 로 안전하게 실행한다(멱등, 재실행 안전).
 *
 * 접근: super_admin('points' 권한) 로그인 필요.
 * 실행 후: admin_points.php 정상 동작. 설치가 끝나면 이 파일은 지워도 된다.
 *
 * @file    gangnamunni/setup_points_schema.php
 * @created 2026-07-24
 * ============================================================
 */
session_start();
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
if (!$connect) die('DB 연결 실패: ' . mysqli_connect_error());
mysqli_set_charset($connect, 'utf8mb4');

require_admin_login();
require_permission('points');

header('Content-Type: text/plain; charset=utf-8');

function step($connect, $label, $sql) {
    if (mysqli_query($connect, $sql)) {
        echo "  [OK] {$label}\n";
    } else {
        echo "  [FAIL] {$label} → " . mysqli_error($connect) . "\n";
    }
}

function index_exists($connect, $table, $index) {
    $r = mysqli_query($connect, "SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE() AND table_name = '{$table}' AND index_name = '{$index}' LIMIT 1");
    return $r && mysqli_num_rows($r) > 0;
}

echo "=== 포인트 원장 스키마 설치 ===\n\n";

// 1) 원장 테이블
step($connect, '원장 테이블(gangnamunni_points)', "CREATE TABLE IF NOT EXISTS gangnamunni_points (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL COMMENT 'g5_member.mb_no',
  type ENUM('earn','use','refund','expire','admin_add','admin_sub','earn_cancel','admin') NOT NULL,
  amount INT NOT NULL,
  balance INT NOT NULL DEFAULT 0,
  reason VARCHAR(200) DEFAULT NULL,
  reference_type VARCHAR(30) DEFAULT NULL,
  reference_id INT DEFAULT NULL,
  expires_at DATE DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_member (member_id, id),
  KEY idx_member_id2 (member_id, id),
  KEY idx_expires (expires_at),
  UNIQUE KEY uniq_ref (reference_type, reference_id, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 2) 설정 테이블
step($connect, '설정 테이블(gangnamunni_point_settings)', "CREATE TABLE IF NOT EXISTS gangnamunni_point_settings (
  skey VARCHAR(50) PRIMARY KEY,
  sval VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 3) 회원 등급 테이블
step($connect, '등급 테이블(gangnamunni_membership_levels)', "CREATE TABLE IF NOT EXISTS gangnamunni_membership_levels (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL,
  min_points INT DEFAULT 0,
  icon VARCHAR(50) DEFAULT '',
  color VARCHAR(20) DEFAULT '#888',
  benefits TEXT,
  discount_rate DECIMAL(4,1) DEFAULT 0,
  sort_order INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 4) 구 테이블 승격(멱등) — DELIMITER 프로시저를 PHP 로 대체
echo "\n-- 기존 테이블 보강(멱등) --\n";
step($connect, 'type ENUM 확장', "ALTER TABLE gangnamunni_points
  MODIFY COLUMN type ENUM('earn','use','refund','expire','admin_add','admin_sub','earn_cancel','admin') NOT NULL");
if (!index_exists($connect, 'gangnamunni_points', 'uniq_ref')) {
    step($connect, 'uniq_ref 유니크 인덱스 추가', "ALTER TABLE gangnamunni_points ADD UNIQUE KEY uniq_ref (reference_type, reference_id, type)");
} else {
    echo "  [SKIP] uniq_ref 이미 존재\n";
}
if (!index_exists($connect, 'gangnamunni_points', 'idx_member_id2')) {
    step($connect, 'idx_member_id2 인덱스 추가', "ALTER TABLE gangnamunni_points ADD KEY idx_member_id2 (member_id, id)");
} else {
    echo "  [SKIP] idx_member_id2 이미 존재\n";
}

// 5) 기본 설정값 + schema_version=2
echo "\n-- 기본 설정값 --\n";
step($connect, '설정 시드 + schema_version=3', "INSERT INTO gangnamunni_point_settings (skey, sval) VALUES
  ('enabled','1'),
  ('signup_enabled','1'),
  ('review_enabled','1'),
  ('booking_enabled','0'),
  ('signup_point','1000'),
  ('booking_percent','1'),
  ('review_default_point','1000'),
  ('expire_days','365'),
  ('schema_version','3')
ON DUPLICATE KEY UPDATE
  sval = IF(skey = 'schema_version', VALUES(sval), sval)");

// 6) 등급 시드(비어 있을 때만)
$cnt_r = mysqli_query($connect, "SELECT COUNT(*) AS c FROM gangnamunni_membership_levels");
$cnt = $cnt_r ? (int)(mysqli_fetch_assoc($cnt_r)['c'] ?? 0) : 0;
if ($cnt === 0) {
    step($connect, '등급 시드(브론즈~다이아몬드)', "INSERT INTO gangnamunni_membership_levels (name,min_points,icon,color,benefits,discount_rate,sort_order) VALUES
      ('브론즈',0,'bi-shield','#cd7f32','기본 혜택, 후기 작성 포인트 적립',0,0),
      ('실버',1000,'bi-shield-fill','#c0c0c0','5% 할인 쿠폰 매월 지급, 우선 예약',5,1),
      ('골드',5000,'bi-trophy','#ffd700','10% 할인, 무료 상담, VIP 이벤트 초대',10,2),
      ('다이아몬드',20000,'bi-gem','#AE1932','15% 할인, 전용 상담사, 무료 시술 쿠폰',15,3)");
} else {
    echo "  [SKIP] 등급 시드: 이미 {$cnt}건 존재\n";
}

// 검증
echo "\n=== 검증 ===\n";
$ver_r = mysqli_query($connect, "SELECT sval FROM gangnamunni_point_settings WHERE skey='schema_version' LIMIT 1");
$ver = $ver_r ? (mysqli_fetch_assoc($ver_r)['sval'] ?? '?') : '?';
echo "  schema_version = {$ver} (목표: 3)\n";
echo "\n설치 완료. admin_points.php 로 이동해 확인하세요.\n";
echo "보안을 위해 확인 후 이 파일(setup_points_schema.php)은 삭제하는 것을 권장합니다.\n";
