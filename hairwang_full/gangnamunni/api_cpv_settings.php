<?php
/**
 * CPV 설정 CRUD API
 * GET: 전체 설정 조회 (카테고리별 그룹핑)
 * POST: 설정값 저장 (단건 or 다건)
 * PUT: 전체 ON/OFF or 기본값 복원
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

session_start();
require_once __DIR__ . '/includes/admin_session_guard.php'; sb_admin_session_guard(); // 삭제·비활성·역할변경 관리자 세션 차단
require_once __DIR__ . '/../dbconfig.php';

$connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB,
    defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
if (!$connect) { echo json_encode(['success' => false, 'message' => 'DB connection failed']); exit; }
mysqli_set_charset($connect, 'utf8mb4');

// 관리자 인증 체크
if (!isset($_SESSION['ad_admin_logged_in']) || $_SESSION['ad_admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// 테이블 자동 생성
createCpvSettingsTable($connect);

$method = $_SERVER['REQUEST_METHOD'];

// GET: 전체 설정 조회
if ($method === 'GET') {
    $action = isset($_GET['action']) ? $_GET['action'] : 'list';

    if ($action === 'export') {
        // JSON 내보내기
        $rows = [];
        $r = mysqli_query($connect, "SELECT setting_key, setting_value, setting_type, category, label FROM gangnamunni_cpv_settings ORDER BY category, sort_order");
        if ($r) while ($row = mysqli_fetch_assoc($r)) $rows[] = $row;
        echo json_encode(['success' => true, 'data' => $rows, 'exported_at' => date('Y-m-d H:i:s')]);
        exit;
    }

    // 카테고리별 그룹핑
    $settings = [];
    $r = mysqli_query($connect, "SELECT * FROM gangnamunni_cpv_settings ORDER BY category, sort_order");
    if ($r) {
        while ($row = mysqli_fetch_assoc($r)) {
            $cat = $row['category'];
            if (!isset($settings[$cat])) $settings[$cat] = [];
            $settings[$cat][] = $row;
        }
    }
    echo json_encode(['success' => true, 'data' => $settings]);
    exit;
}

// POST: 설정값 저장
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit;
    }

    $action = isset($input['action']) ? $input['action'] : 'save';

    // 단건 저장
    if ($action === 'save' && isset($input['key']) && isset($input['value'])) {
        $key = $input['key'];
        $value = $input['value'];
        $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_cpv_settings SET setting_value = ? WHERE setting_key = ?");
        mysqli_stmt_bind_param($stmt, 'ss', $value, $key);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        echo json_encode(['success' => $ok, 'key' => $key, 'value' => $value]);
        exit;
    }

    // 다건 저장 (bulk)
    if ($action === 'bulk_save' && isset($input['settings']) && is_array($input['settings'])) {
        $updated = 0;
        $errors = [];
        foreach ($input['settings'] as $key => $value) {
            $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_cpv_settings SET setting_value = ? WHERE setting_key = ?");
            if ($stmt) {
                $v = (string) $value;
                mysqli_stmt_bind_param($stmt, 'ss', $v, $key);
                if (mysqli_stmt_execute($stmt)) {
                    $updated++;
                } else {
                    $errors[] = $key;
                }
                mysqli_stmt_close($stmt);
            }
        }
        echo json_encode(['success' => true, 'updated' => $updated, 'errors' => $errors]);
        exit;
    }

    // JSON 가져오기 (import)
    if ($action === 'import' && isset($input['data']) && is_array($input['data'])) {
        $imported = 0;
        foreach ($input['data'] as $item) {
            if (!isset($item['setting_key']) || !isset($item['setting_value'])) continue;
            $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_cpv_settings SET setting_value = ? WHERE setting_key = ?");
            if ($stmt) {
                $v = (string) $item['setting_value'];
                $k = $item['setting_key'];
                mysqli_stmt_bind_param($stmt, 'ss', $v, $k);
                if (mysqli_stmt_execute($stmt)) $imported++;
                mysqli_stmt_close($stmt);
            }
        }
        echo json_encode(['success' => true, 'imported' => $imported]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

// PUT: 전체 ON/OFF or 기본값 복원
if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = isset($input['action']) ? $input['action'] : '';

    if ($action === 'all_on') {
        mysqli_query($connect, "UPDATE gangnamunni_cpv_settings SET setting_value = '1' WHERE setting_type = 'toggle'");
        echo json_encode(['success' => true, 'message' => 'All toggles ON']);
        exit;
    }

    if ($action === 'all_off') {
        mysqli_query($connect, "UPDATE gangnamunni_cpv_settings SET setting_value = '0' WHERE setting_type = 'toggle'");
        echo json_encode(['success' => true, 'message' => 'All toggles OFF']);
        exit;
    }

    if ($action === 'reset_defaults') {
        // 테이블 삭제 후 재생성
        mysqli_query($connect, "DROP TABLE IF EXISTS gangnamunni_cpv_settings");
        createCpvSettingsTable($connect);
        echo json_encode(['success' => true, 'message' => 'Defaults restored']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Method not allowed']);

// ========================================================
// 테이블 생성 + 기본값 INSERT
// ========================================================
function createCpvSettingsTable($conn) {
    // 테이블 생성
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS gangnamunni_cpv_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(80) NOT NULL UNIQUE,
        setting_value TEXT NOT NULL,
        setting_type ENUM('toggle','number','text','select','percent','currency') DEFAULT 'toggle',
        category VARCHAR(30) NOT NULL,
        sub_category VARCHAR(50) DEFAULT NULL,
        label VARCHAR(100) NOT NULL,
        description VARCHAR(200) DEFAULT NULL,
        min_value DECIMAL(12,2) DEFAULT NULL,
        max_value DECIMAL(12,2) DEFAULT NULL,
        sort_order INT DEFAULT 0,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 이미 데이터 있으면 스킵
    $r = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM gangnamunni_cpv_settings");
    $row = mysqli_fetch_assoc($r);
    if ((int)$row['cnt'] > 0) return;

    // 기본 설정값 55개 INSERT
    $defaults = getDefaultCpvSettings();
    $stmt = mysqli_prepare($conn, "INSERT INTO gangnamunni_cpv_settings (setting_key, setting_value, setting_type, category, sub_category, label, description, min_value, max_value, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $sort = 0;
    foreach ($defaults as $d) {
        $sort++;
        $key = $d[0]; $val = (string)$d[1]; $type = $d[2]; $cat = $d[3];
        $sub = isset($d[4]) ? $d[4] : null;
        $label = $d[5]; $desc = isset($d[6]) ? $d[6] : null;
        $minv = isset($d[7]) ? $d[7] : null;
        $maxv = isset($d[8]) ? $d[8] : null;
        mysqli_stmt_bind_param($stmt, 'sssssssddI', $key, $val, $type, $cat, $sub, $label, $desc, $minv, $maxv, $sort);
        // bind_param doesn't support NULL well with 'd', use query instead
    }
    mysqli_stmt_close($stmt);

    // 직접 INSERT로 처리
    foreach ($defaults as $d) {
        $sort++;
        $key = mysqli_real_escape_string($conn, $d[0]);
        $val = mysqli_real_escape_string($conn, (string)$d[1]);
        $type = mysqli_real_escape_string($conn, $d[2]);
        $cat = mysqli_real_escape_string($conn, $d[3]);
        $sub = isset($d[4]) && $d[4] !== null ? "'" . mysqli_real_escape_string($conn, $d[4]) . "'" : 'NULL';
        $label = mysqli_real_escape_string($conn, $d[5]);
        $desc = isset($d[6]) && $d[6] !== null ? "'" . mysqli_real_escape_string($conn, $d[6]) . "'" : 'NULL';
        $minv = isset($d[7]) && $d[7] !== null ? $d[7] : 'NULL';
        $maxv = isset($d[8]) && $d[8] !== null ? $d[8] : 'NULL';
        mysqli_query($conn, "INSERT IGNORE INTO gangnamunni_cpv_settings (setting_key, setting_value, setting_type, category, sub_category, label, description, min_value, max_value, sort_order)
            VALUES ('$key', '$val', '$type', '$cat', $sub, '$label', $desc, $minv, $maxv, $sort)");
    }
}

function getDefaultCpvSettings() {
    // [key, default_value, type, category, sub_category, label, description, min, max]
    return [
        // Phase 6: 시스템 전역
        ['cpv_master_switch', '0', 'toggle', 'phase6', null, 'CPV 시스템 전체', '마스터 스위치 — OFF시 모든 CPV 기능 비활성화'],
        ['cpv_test_mode', '1', 'toggle', 'phase6', null, '테스트 모드', '로그만 기록, 실제 차감 안 함'],
        ['currency_unit', 'KRW', 'select', 'phase6', null, '통화 단위', 'KRW/USD/JPY'],
        ['decimal_rounding', 'round', 'select', 'phase6', null, '소수점 처리', 'round/ceil/floor'],
        ['cron_interval_minutes', '5', 'number', 'phase6', null, '크론 실행 주기', '분 단위', 1, 60],
        ['daily_aggregate_time', '00:00', 'text', 'phase6', null, '일일 집계 시간', 'HH:MM 형식'],

        // Phase 1: 포인트 지갑
        ['wallet_enabled', '1', 'toggle', 'phase1', null, '광고주 포인트 지갑', '포인트 지갑 시스템 활성화'],
        ['pg_charge_enabled', '1', 'toggle', 'phase1', null, 'PG 결제 충전', 'PG 결제를 통한 포인트 충전'],
        ['pg_fee_rate', '3.3', 'percent', 'phase1', 'pg_charge_enabled', 'PG 수수료율', '결제 수수료 비율 (%)', 0, 10],
        ['manual_charge_enabled', '1', 'toggle', 'phase1', null, '수동 충전/조정', '관리자 수동 충전'],
        ['transaction_log_enabled', '1', 'toggle', 'phase1', null, '거래 내역 기록', '모든 거래 로그 기록'],
        ['transaction_retention_days', '365', 'number', 'phase1', 'transaction_log_enabled', '내역 보관 기간', '일 단위', 30, 3650],

        // Phase 2: CPV 과금 엔진
        ['cpv_charge_enabled', '1', 'toggle', 'phase2', null, '페이지뷰 과금', '페이지 로드 완료시 과금'],
        ['cpv_charge_delay_sec', '1', 'number', 'phase2', 'cpv_charge_enabled', '과금 딜레이', '봇 방지 — 초 단위', 0, 10],
        ['no_extra_charge', '1', 'toggle', 'phase2', null, '추가액션 비과금', '예약/상담 등 추가 과금 안 함'],
        ['anti_abuse_enabled', '1', 'toggle', 'phase2', null, '어뷰징 방지', '중복 조회 차단'],
        ['anti_abuse_minutes', '30', 'number', 'phase2', 'anti_abuse_enabled', '중복 방지 시간', '분 단위', 1, 1440],
        ['daily_view_limit', '10', 'number', 'phase2', 'anti_abuse_enabled', '일일 조회 한도', '동일 사용자 / 병원당 회', 1, 100],
        ['cpv_log_enabled', '1', 'toggle', 'phase2', null, '과금 로그 기록', '모든 과금 이벤트 로그'],
        ['cpv_log_retention_days', '90', 'number', 'phase2', 'cpv_log_enabled', '로그 보관 기간', '일 단위', 7, 365],

        // Phase 3: 단가 정책
        ['base_cpv_rate', '200', 'currency', 'phase3', null, '기본 CPV 단가', '원/뷰 기본 단가', 10, 10000],
        ['max_cpv_rate', '5000', 'currency', 'phase3', null, '최대 단가 상한', '가중치 적용 후 상한선', 100, 100000],
        ['min_cpv_rate', '50', 'currency', 'phase3', null, '최소 단가 하한', '가중치 적용 후 하한선', 1, 1000],
        ['keyword_pricing_enabled', '1', 'toggle', 'phase3', null, '키워드 카테고리별 단가', '키워드에 따른 가중치'],
        ['weight_keyword_eye', '1.5', 'number', 'phase3', 'keyword_pricing_enabled', '눈성형 가중치', '배수', 0.1, 10],
        ['weight_keyword_nose', '1.3', 'number', 'phase3', 'keyword_pricing_enabled', '코성형 가중치', '배수', 0.1, 10],
        ['weight_keyword_skin', '1.0', 'number', 'phase3', 'keyword_pricing_enabled', '피부과 가중치', '배수', 0.1, 10],
        ['weight_keyword_dental', '0.8', 'number', 'phase3', 'keyword_pricing_enabled', '치과 가중치', '배수', 0.1, 10],
        ['procedure_pricing_enabled', '1', 'toggle', 'phase3', null, '시술 종류별 단가', '시술 유형에 따른 가중치'],
        ['weight_proc_premium', '2.0', 'number', 'phase3', 'procedure_pricing_enabled', '프리미엄 시술 가중치', '배수', 0.1, 10],
        ['weight_proc_standard', '1.0', 'number', 'phase3', 'procedure_pricing_enabled', '일반 시술 가중치', '배수', 0.1, 10],
        ['weight_proc_discount', '0.7', 'number', 'phase3', 'procedure_pricing_enabled', '할인시술 가중치', '배수', 0.1, 10],
        ['event_weight_enabled', '1', 'toggle', 'phase3', null, '이벤트 가중치', '이벤트 진행 여부에 따른 가중치'],
        ['weight_event_active', '1.5', 'number', 'phase3', 'event_weight_enabled', '이벤트 진행 중', '배수', 0.1, 10],
        ['weight_event_ended', '0.5', 'number', 'phase3', 'event_weight_enabled', '이벤트 종료', '배수', 0.1, 10],
        ['rating_weight_enabled', '1', 'toggle', 'phase3', null, '평점 가중치', '병원 평점에 따른 가중치'],
        ['weight_rating_high', '1.3', 'number', 'phase3', 'rating_weight_enabled', '고평점(4.5+) 가중치', '배수', 0.1, 10],
        ['weight_rating_low', '0.6', 'number', 'phase3', 'rating_weight_enabled', '저평점(3.0 미만) 가중치', '배수', 0.1, 10],

        // Phase 4: 예산 관리
        ['daily_cap_enabled', '1', 'toggle', 'phase4', null, 'Daily Cap 설정', '일일 예산 한도 기능'],
        ['default_daily_cap', '50000', 'currency', 'phase4', 'daily_cap_enabled', '기본 일일캡', '신규 병원 기본값 (원)', 1000, 10000000],
        ['min_daily_cap', '10000', 'currency', 'phase4', 'daily_cap_enabled', '최소 일일캡', '원', 1000, 1000000],
        ['max_daily_cap', '1000000', 'currency', 'phase4', 'daily_cap_enabled', '최대 일일캡', '원', 10000, 100000000],
        ['auto_pause_zero', '1', 'toggle', 'phase4', null, '잔액 0원 자동 중단', '잔액 소진 시 광고 자동 중단'],
        ['zero_grace_hours', '0', 'number', 'phase4', 'auto_pause_zero', '유예 기간', '시간 단위', 0, 72],
        ['auto_pause_cap', '1', 'toggle', 'phase4', null, '일일캡 도달 자동 중단', '캡 도달 시 당일 중단'],
        ['cap_overflow_margin', '0', 'percent', 'phase4', 'auto_pause_cap', '초과 허용 마진', '%', 0, 50],
        ['low_balance_alert', '1', 'toggle', 'phase4', null, '잔액 부족 사전 알림', '잔액이 기준 이하일 때 알림'],
        ['alert_threshold_days', '3', 'number', 'phase4', 'low_balance_alert', '알림 기준 (일)', 'N일분 잔액 이하', 1, 30],
        ['alert_threshold_amount', '100000', 'currency', 'phase4', 'low_balance_alert', '알림 기준 (금액)', '고정 금액 이하 (원)', 10000, 10000000],

        // Phase 5: 충전 상품 & 대시보드
        ['charge_product_1_price', '50000', 'currency', 'phase5', null, '라이트 충전 금액', '원', 10000, 10000000],
        ['charge_product_1_bonus', '0', 'percent', 'phase5', null, '라이트 보너스율', '%', 0, 50],
        ['charge_product_2_price', '100000', 'currency', 'phase5', null, '베이직 충전 금액', '원', 10000, 10000000],
        ['charge_product_2_bonus', '3', 'percent', 'phase5', null, '베이직 보너스율', '%', 0, 50],
        ['charge_product_3_price', '300000', 'currency', 'phase5', null, '스탠다드 충전 금액', '원', 10000, 10000000],
        ['charge_product_3_bonus', '5', 'percent', 'phase5', null, '스탠다드 보너스율', '%', 0, 50],
        ['charge_product_4_price', '500000', 'currency', 'phase5', null, '프리미엄 충전 금액', '원', 10000, 10000000],
        ['charge_product_4_bonus', '8', 'percent', 'phase5', null, '프리미엄 보너스율', '%', 0, 50],
        ['charge_product_5_price', '1000000', 'currency', 'phase5', null, '엔터프라이즈 충전 금액', '원', 10000, 100000000],
        ['charge_product_5_bonus', '12', 'percent', 'phase5', null, '엔터프라이즈 보너스율', '%', 0, 50],
        ['vat_rate', '10', 'percent', 'phase5', null, '부가세 (VAT)', '%', 0, 30],
        ['partner_dashboard', '1', 'toggle', 'phase5', null, '파트너 대시보드', '병원용 대시보드 활성화'],
        ['admin_dashboard', '1', 'toggle', 'phase5', null, '본사 대시보드', '본사 관리자 대시보드 활성화'],
        ['settlement_report', '1', 'toggle', 'phase5', null, '정산 리포트', '정산 리포트 기능 활성화'],
        ['settlement_cycle', 'monthly', 'select', 'phase5', 'settlement_report', '정산 주기', 'monthly/biweekly/weekly'],
        ['settlement_day', '1', 'number', 'phase5', 'settlement_report', '정산 기준일', '매월 N일', 1, 28],
        ['stats_extension', '1', 'toggle', 'phase5', null, '기존 통계 확장', '기존 광고 통계에 CPV 데이터 통합'],
    ];
}
