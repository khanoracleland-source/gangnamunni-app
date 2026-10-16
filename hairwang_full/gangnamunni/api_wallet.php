<?php
/**
 * CPV 지갑 REST API
 * - GET: 지갑 목록/단건 조회, 거래내역 조회
 * - POST: charge/deduct/refund/adjust/create 액션
 *
 * 모든 금액 변경은 DB 트랜잭션으로 원자적 처리
 * @created 2026-03-11
 */
session_start();
require_once __DIR__ . '/includes/admin_session_guard.php'; sb_admin_session_guard(); // 삭제·비활성·역할변경 관리자 세션 차단
header('Content-Type: application/json; charset=utf-8');

// 인증 체크
if (empty($_SESSION['ad_admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => '로그인이 필요합니다.']);
    exit;
}

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/cpv_helper.php';
// Wallet changes are platform-finance operations, not a privilege of any logged-in admin.
if (!check_permission('cpv_wallet') || !in_array($_SESSION['ad_admin_role'] ?? '', ['super_admin', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['error'=>'지갑 관리 권한이 없습니다.']);
    exit;
}


// DB 연결
$connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
if (!$connect) {
    http_response_code(500);
    echo json_encode(['error' => 'DB 연결 실패']);
    exit;
}
mysqli_set_charset($connect, 'utf8mb4');

$method = $_SERVER['REQUEST_METHOD'];

// ========================================
// GET 요청 처리
// ========================================
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    // 거래내역 조회
    if ($action === 'transactions') {
        $hospital_id = intval($_GET['hospital_id'] ?? 0);
        $page = max(1, intval($_GET['page'] ?? 1));
        $per_page = min(100, max(10, intval($_GET['per_page'] ?? 20)));
        $offset = ($page - 1) * $per_page;
        $type_filter = $_GET['type'] ?? '';

        $where = "hospital_id = ?";
        $bind_types = 'i';
        $bind_values = [$hospital_id];

        if ($type_filter && in_array($type_filter, ['charge', 'deduct', 'refund', 'adjust', 'bonus'])) {
            $where .= " AND type = ?";
            $bind_types .= 's';
            $bind_values[] = $type_filter;
        }

        // 총 건수
        $count_sql = "SELECT COUNT(*) as cnt FROM gangnamunni_cpv_transactions WHERE $where";
        $stmt = mysqli_prepare($connect, $count_sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, $bind_types, ...$bind_values);
            mysqli_stmt_execute($stmt);
            $total = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'] ?? 0;
            mysqli_stmt_close($stmt);
        } else {
            $total = 0;
        }

        // 거래내역
        $data_sql = "SELECT t.*, a.name as admin_name
                     FROM gangnamunni_cpv_transactions t
                     LEFT JOIN gangnamunni_admins a ON t.admin_id = a.id
                     WHERE t.$where
                     ORDER BY t.created_at DESC
                     LIMIT ? OFFSET ?";
        $stmt = mysqli_prepare($connect, $data_sql);
        $all_types = $bind_types . 'ii';
        $all_values = array_merge($bind_values, [$per_page, $offset]);
        mysqli_stmt_bind_param($stmt, $all_types, ...$all_values);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $transactions = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $row['amount_formatted'] = formatCpvCurrency($row['amount']);
            $row['balance_after_formatted'] = formatCpvCurrency($row['balance_after']);
            $transactions[] = $row;
        }
        mysqli_stmt_close($stmt);

        echo json_encode([
            'success' => true,
            'total' => (int)$total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => max(1, ceil($total / $per_page)),
            'transactions' => $transactions
        ]);
        exit;
    }

    // 단건 지갑 조회
    if (!empty($_GET['hospital_id'])) {
        $hospital_id = intval($_GET['hospital_id']);
        $stmt = mysqli_prepare($connect, "SELECT w.*, h.name as hospital_name
                                          FROM gangnamunni_cpv_wallets w
                                          LEFT JOIN gangnamunni_hospitals h ON w.hospital_id = h.id
                                          WHERE w.hospital_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
        mysqli_stmt_execute($stmt);
        $wallet = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$wallet) {
            http_response_code(404);
            echo json_encode(['error' => '지갑을 찾을 수 없습니다.', 'hospital_id' => $hospital_id]);
            exit;
        }

        $wallet['balance_formatted'] = formatCpvCurrency($wallet['balance']);
        $wallet['total_charged_formatted'] = formatCpvCurrency($wallet['total_charged']);
        $wallet['total_spent_formatted'] = formatCpvCurrency($wallet['total_spent']);
        $wallet['total_refunded_formatted'] = formatCpvCurrency($wallet['total_refunded']);

        echo json_encode(['success' => true, 'wallet' => $wallet]);
        exit;
    }

    // 전체 지갑 목록
    $page = max(1, intval($_GET['page'] ?? 1));
    $per_page = min(100, max(10, intval($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $per_page;
    $search = $_GET['q'] ?? '';

    $where = "1=1";
    $bind_types = '';
    $bind_values = [];

    if ($search) {
        $where .= " AND h.name LIKE ?";
        $bind_types .= 's';
        $bind_values[] = '%' . $search . '%';
    }

    // 총 건수
    $count_sql = "SELECT COUNT(*) as cnt FROM gangnamunni_cpv_wallets w LEFT JOIN gangnamunni_hospitals h ON w.hospital_id = h.id WHERE $where";
    $stmt = mysqli_prepare($connect, $count_sql);
    if ($bind_types && $stmt) {
        mysqli_stmt_bind_param($stmt, $bind_types, ...$bind_values);
    }
    if ($stmt) {
        mysqli_stmt_execute($stmt);
        $total = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'] ?? 0;
        mysqli_stmt_close($stmt);
    } else {
        $total = 0;
    }

    // 목록
    $list_sql = "SELECT w.*, h.name as hospital_name
                 FROM gangnamunni_cpv_wallets w
                 LEFT JOIN gangnamunni_hospitals h ON w.hospital_id = h.id
                 WHERE $where
                 ORDER BY w.updated_at DESC
                 LIMIT ? OFFSET ?";
    $stmt = mysqli_prepare($connect, $list_sql);
    $all_types = $bind_types . 'ii';
    $all_values = array_merge($bind_values, [$per_page, $offset]);
    if ($all_types) {
        mysqli_stmt_bind_param($stmt, $all_types, ...$all_values);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $wallets = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $row['balance_formatted'] = formatCpvCurrency($row['balance']);
        $row['total_charged_formatted'] = formatCpvCurrency($row['total_charged']);
        $row['total_spent_formatted'] = formatCpvCurrency($row['total_spent']);
        $wallets[] = $row;
    }
    mysqli_stmt_close($stmt);

    echo json_encode([
        'success' => true,
        'total' => (int)$total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => max(1, ceil($total / $per_page)),
        'wallets' => $wallets
    ]);
    exit;
}

// ========================================
// POST 요청 처리
// ========================================
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        // fallback: form data
        $input = $_POST;
    }

    $csrf = (string)($_SESSION['wallet_csrf_token'] ?? '');
    if ($csrf === '' || !hash_equals($csrf, (string)($input['csrf_token'] ?? ''))) {
        http_response_code(403);
        echo json_encode(['error'=>'페이지를 새로고침해주세요.']);
        exit;
    }
    $action = $input['action'] ?? '';
    $hospital_id = intval($input['hospital_id'] ?? 0);
    $amount = intval($input['amount'] ?? 0);
    $description = trim($input['description'] ?? '');
    $pg_order_id = trim($input['pg_order_id'] ?? '');
    $admin_id = intval($_SESSION['ad_admin_id'] ?? 0);

    // 지갑 생성
    if ($action === 'create') {
        if ($hospital_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'hospital_id가 필요합니다.']);
            exit;
        }

        // 중복 체크
        $stmt = mysqli_prepare($connect, "SELECT id FROM gangnamunni_cpv_wallets WHERE hospital_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
        mysqli_stmt_execute($stmt);
        $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($existing) {
            http_response_code(409);
            echo json_encode(['error' => '이미 지갑이 존재합니다.', 'wallet_id' => $existing['id']]);
            exit;
        }

        $daily_cap = intval($input['daily_cap'] ?? 0);
        $stmt = mysqli_prepare($connect,
            "INSERT INTO gangnamunni_cpv_wallets (hospital_id, balance, total_charged, total_spent, total_refunded, daily_cap, is_active, is_paused, created_at, updated_at)
             VALUES (?, 0, 0, 0, 0, ?, 1, 0, NOW(), NOW())");
        mysqli_stmt_bind_param($stmt, 'ii', $hospital_id, $daily_cap);

        if (mysqli_stmt_execute($stmt)) {
            $wallet_id = mysqli_insert_id($connect);
            mysqli_stmt_close($stmt);
            echo json_encode(['success' => true, 'message' => '지갑이 생성되었습니다.', 'wallet_id' => $wallet_id]);
        } else {
            mysqli_stmt_close($stmt);
            http_response_code(500);
            echo json_encode(['error' => '지갑 생성 실패: ' . mysqli_error($connect)]);
        }
        exit;
    }

    // 금액 변경 액션들 (charge / deduct / refund / adjust)
    $allowed_actions = ['charge', 'deduct', 'refund', 'adjust'];
    if (!in_array($action, $allowed_actions)) {
        http_response_code(400);
        echo json_encode(['error' => '유효하지 않은 액션입니다: ' . $action]);
        exit;
    }

    if ($hospital_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'hospital_id가 필요합니다.']);
        exit;
    }
    if ($amount <= 0 && $action !== 'adjust') {
        http_response_code(400);
        echo json_encode(['error' => '금액은 0보다 커야 합니다.']);
        exit;
    }
    // adjust는 음수도 가능
    if ($action === 'adjust' && $amount == 0) {
        http_response_code(400);
        echo json_encode(['error' => '조정 금액은 0이 아니어야 합니다.']);
        exit;
    }

    // 트랜잭션 시작 — 원자적 처리
    mysqli_begin_transaction($connect);

    try {
        // FOR UPDATE로 행 잠금하여 동시성 제어
        $stmt = mysqli_prepare($connect, "SELECT * FROM gangnamunni_cpv_wallets WHERE hospital_id = ? FOR UPDATE");
        mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
        mysqli_stmt_execute($stmt);
        $wallet = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$wallet) {
            throw new Exception('지갑을 찾을 수 없습니다.');
        }
        if (!$wallet['is_active']) {
            throw new Exception('비활성화된 지갑입니다.');
        }

        $current_balance = (int)$wallet['balance'];
        $new_balance = $current_balance;
        $tx_type = $action;
        $update_field = '';

        switch ($action) {
            case 'charge':
                $new_balance = $current_balance + $amount;
                $update_field = 'total_charged';
                if (!$description) $description = '포인트 충전';
                break;

            case 'deduct':
                if ($current_balance < $amount) {
                    throw new Exception('잔액이 부족합니다. 현재 잔액: ' . formatCpvCurrency($current_balance));
                }
                // 일일 한도 체크
                if ($wallet['daily_cap'] > 0) {
                    $stmt = mysqli_prepare($connect,
                        "SELECT COALESCE(SUM(amount), 0) as daily_spent
                         FROM gangnamunni_cpv_transactions
                         WHERE hospital_id = ? AND type = 'deduct' AND DATE(created_at) = CURDATE()");
                    mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
                    mysqli_stmt_execute($stmt);
                    $daily = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                    mysqli_stmt_close($stmt);

                    $daily_spent = (int)($daily['daily_spent'] ?? 0);
                    if (($daily_spent + $amount) > $wallet['daily_cap']) {
                        throw new Exception('일일 소진 한도를 초과합니다. 한도: ' . formatCpvCurrency($wallet['daily_cap']) . ', 오늘 사용: ' . formatCpvCurrency($daily_spent));
                    }
                }
                // 일시정지 체크
                if ($wallet['is_paused']) {
                    throw new Exception('지갑이 일시정지 상태입니다. 사유: ' . ($wallet['pause_reason'] ?: '없음'));
                }
                $new_balance = $current_balance - $amount;
                $update_field = 'total_spent';
                if (!$description) $description = 'CPV 과금 차감';
                break;

            case 'refund':
                $new_balance = $current_balance + $amount;
                $update_field = 'total_refunded';
                if (!$description) $description = '포인트 환불';
                break;

            case 'adjust':
                // adjust는 양수(증가) 또는 음수(감소) 가능
                $new_balance = $current_balance + $amount;
                if ($new_balance < 0) {
                    throw new Exception('조정 후 잔액이 음수가 됩니다. 현재: ' . formatCpvCurrency($current_balance));
                }
                if (!$description) $description = '관리자 수동 조정';
                break;
        }

        // 지갑 잔액 업데이트
        if ($update_field) {
            $update_sql = "UPDATE gangnamunni_cpv_wallets SET balance = ?, $update_field = $update_field + ?, updated_at = NOW() WHERE hospital_id = ?";
            $stmt = mysqli_prepare($connect, $update_sql);
            $abs_amount = abs($amount);
            mysqli_stmt_bind_param($stmt, 'iii', $new_balance, $abs_amount, $hospital_id);
        } else {
            // adjust — update_field 없이 잔액만 변경
            $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_cpv_wallets SET balance = ?, updated_at = NOW() WHERE hospital_id = ?");
            mysqli_stmt_bind_param($stmt, 'ii', $new_balance, $hospital_id);
        }
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('지갑 업데이트 실패: ' . mysqli_error($connect));
        }
        mysqli_stmt_close($stmt);

        // 거래내역 기록
        $stmt = mysqli_prepare($connect,
            "INSERT INTO gangnamunni_cpv_transactions
             (hospital_id, type, amount, balance_after, description, reference_type, reference_id, pg_order_id, admin_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $ref_type = $input['reference_type'] ?? '';
        // reference_id는 int(11) 컬럼 — 빈 값이면 NULL, 아니면 정수로 (strict mode에서 '' 삽입 오류 방지)
        $ref_id = (isset($input['reference_id']) && $input['reference_id'] !== '') ? intval($input['reference_id']) : null;
        $abs_amount = abs($amount);
        // 타입 문자열을 컬럼 순서(hospital_id, type, amount, balance_after, description, reference_type, reference_id, pg_order_id, admin_id)에 정확히 정렬
        mysqli_stmt_bind_param($stmt, 'isiissisi',
            $hospital_id, $tx_type, $abs_amount, $new_balance,
            $description, $ref_type, $ref_id, $pg_order_id, $admin_id
        );
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('거래내역 기록 실패: ' . mysqli_error($connect));
        }
        $tx_id = mysqli_insert_id($connect);
        mysqli_stmt_close($stmt);

        // 보너스 처리 (charge 시 보너스 비율이 있으면 자동 보너스 적립)
        $bonus_tx_id = null;
        if ($action === 'charge' && !empty($input['bonus_amount']) && intval($input['bonus_amount']) > 0) {
            $bonus = intval($input['bonus_amount']);
            $new_balance += $bonus;

            $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_cpv_wallets SET balance = ?, total_charged = total_charged + ?, updated_at = NOW() WHERE hospital_id = ?");
            mysqli_stmt_bind_param($stmt, 'iii', $new_balance, $bonus, $hospital_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $bonus_desc = '충전 보너스 (' . $description . ')';
            $stmt = mysqli_prepare($connect,
                "INSERT INTO gangnamunni_cpv_transactions
                 (hospital_id, type, amount, balance_after, description, reference_type, reference_id, pg_order_id, admin_id, created_at)
                 VALUES (?, 'bonus', ?, ?, ?, 'charge_bonus', ?, ?, ?, NOW())");
            $ref_charge_id = (int)$tx_id;
            mysqli_stmt_bind_param($stmt, 'iiisisi',
                $hospital_id, $bonus, $new_balance,
                $bonus_desc, $ref_charge_id, $pg_order_id, $admin_id
            );
            mysqli_stmt_execute($stmt);
            $bonus_tx_id = mysqli_insert_id($connect);
            mysqli_stmt_close($stmt);
        }

        mysqli_commit($connect);

        echo json_encode([
            'success' => true,
            'message' => '처리 완료',
            'action' => $action,
            'amount' => abs($amount),
            'amount_formatted' => formatCpvCurrency(abs($amount)),
            'new_balance' => $new_balance,
            'new_balance_formatted' => formatCpvCurrency($new_balance),
            'transaction_id' => $tx_id,
            'bonus_transaction_id' => $bonus_tx_id
        ]);

    } catch (Exception $e) {
        mysqli_rollback($connect);
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// 지원하지 않는 메서드
http_response_code(405);
echo json_encode(['error' => '허용되지 않는 메서드입니다.']);
