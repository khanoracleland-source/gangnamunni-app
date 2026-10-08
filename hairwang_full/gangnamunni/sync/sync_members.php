<?php
// CLI 전용 스크립트 — 웹 요청은 404
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * 회원 양방향 자동 동기화 크론 스크립트
 *
 * Gnuboard g5_member ↔ HMS users/patients 양방향 자동 동기화
 * 5분마다 cron_sync.sh에서 실행
 *
 * @created 2026-02-11
 */

// DB 설정 로드
require_once __DIR__ . '/db_sync_config.php';

// 회원 동기화 함수 로드
require_once __DIR__ . '/../includes/member_sync.php';

echo "\n=== 회원 양방향 동기화 시작: " . date('Y-m-d H:i:s') . " ===\n";

// hms_saas DB 연결 (모든 테이블이 여기에 통합됨)
$connect = mysqli_connect('localhost', 'root', '<REMOVED_DB_PASSWORD>', 'hms_saas');
if (!$connect) {
    logSync('회원 동기화 DB 연결 실패: ' . mysqli_connect_error(), 'ERROR');
    echo "  [ERROR] DB 연결 실패\n";
    exit(1);
}
mysqli_set_charset($connect, 'utf8mb4');

// === 1단계: Gnuboard → HMS (새 회원 자동 동기화) ===
echo "\n[1] Gnuboard → HMS 회원 동기화\n";

$sql_g2h = "SELECT m.mb_no, m.mb_id, m.mb_name
    FROM g5_member m
    LEFT JOIN member_sync s ON m.mb_no = s.mb_no
    WHERE m.mb_leave_date = ''
    AND (s.id IS NULL OR s.sync_status NOT IN ('synced'))
    ORDER BY m.mb_no ASC
    LIMIT 50";
$result_g2h = mysqli_query($connect, $sql_g2h);
$g2h_count = mysqli_num_rows($result_g2h);

if ($g2h_count > 0) {
    $success = 0;
    $fail = 0;
    while ($row = mysqli_fetch_assoc($result_g2h)) {
        $r = sync_member_to_hms($row['mb_no'], $connect);
        if ($r['success']) {
            $success++;
            logSync("Gnuboard→HMS 동기화: {$row['mb_id']} ({$row['mb_name']}) → HMS User #{$r['hms_user_id']}");
        } else {
            $fail++;
            logSync("Gnuboard→HMS 동기화 실패: {$row['mb_id']} - {$r['message']}", 'ERROR');
        }
    }
    echo "  Gnuboard→HMS: 성공 {$success}, 실패 {$fail}\n";
} else {
    echo "  새 Gnuboard 회원 없음\n";
}

// === 2단계: HMS → Gnuboard (새 환자 자동 동기화) ===
echo "\n[2] HMS → Gnuboard 회원 동기화\n";

$sql_h2g = "SELECT u.id, u.first_name, u.email
    FROM users u
    LEFT JOIN member_sync s ON u.id = s.hms_user_id
    WHERE u.owner_type = 'App\\\\Models\\\\Patient'
    AND u.status = 1
    AND (s.id IS NULL OR s.sync_status NOT IN ('synced'))
    ORDER BY u.id ASC
    LIMIT 50";
$result_h2g = mysqli_query($connect, $sql_h2g);
$h2g_count = mysqli_num_rows($result_h2g);

if ($h2g_count > 0) {
    $success = 0;
    $fail = 0;
    while ($row = mysqli_fetch_assoc($result_h2g)) {
        $r = sync_patient_to_gnuboard($row['id'], $connect);
        if ($r['success']) {
            $success++;
            logSync("HMS→Gnuboard 동기화: HMS User #{$row['id']} ({$row['first_name']}) → Gnuboard");
        } else {
            $fail++;
            logSync("HMS→Gnuboard 동기화 실패: User #{$row['id']} - {$r['message']}", 'ERROR');
        }
    }
    echo "  HMS→Gnuboard: 성공 {$success}, 실패 {$fail}\n";
} else {
    echo "  새 HMS 환자 없음\n";
}

// === 3단계: 동기화 통계 출력 ===
$stats = get_sync_stats($connect);
echo "\n  동기화 현황: g5_member={$stats['total_members']}, synced={$stats['synced']}, HMS환자={$stats['hms_patients']}\n";

mysqli_close($connect);
echo "=== 회원 동기화 완료 ===\n";
