<?php
/**
 * 이벤트 응모 AJAX API
 *
 * Gnuboard 회원 세션 기반 인증
 * gangnamunni_event_entries / gangnamunni_events 테이블 사용
 *
 * Endpoints:
 *   POST ?action=enter        응모 등록
 *   GET  ?action=status&event_id=N  응모 상태 확인
 *   GET  ?action=list         내 응모 목록
 *
 * @created 2026-02-27
 */

require_once __DIR__ . '/includes/init.php';
header('Content-Type: application/json; charset=utf-8');

// ---------------------------------------------------------------------------
// 인증: Gnuboard 세션
// ---------------------------------------------------------------------------
if (!is_gangnamunni_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

$member = get_gangnamunni_member();
$member_id = $member['mb_id'] ?? '';

if ($member_id === '') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '회원 정보를 가져올 수 없습니다.']);
    exit;
}

// ---------------------------------------------------------------------------
// DB 연결 확인 ($connect 는 init.php 에서 제공)
// ---------------------------------------------------------------------------
if (!isset($connect) || !$connect) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '서버 오류가 발생했습니다.']);
    exit;
}

// ---------------------------------------------------------------------------
// member_id 컬럼 존재 여부 캐시 (서버 마이그레이션 전 호환)
// ---------------------------------------------------------------------------
$has_member_id_col = false;
$col_check = mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_event_entries LIKE 'member_id'");
if ($col_check && mysqli_num_rows($col_check) > 0) {
    $has_member_id_col = true;
}

// ---------------------------------------------------------------------------
// 라우팅
// ---------------------------------------------------------------------------
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'enter':
        handle_enter($connect, $member, $has_member_id_col);
        break;

    case 'status':
        handle_status($connect, $member, $has_member_id_col);
        break;

    case 'list':
        handle_list($connect, $member, $has_member_id_col);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '알 수 없는 요청입니다.']);
        break;
}

mysqli_close($connect);
exit;

// ===========================================================================
// 핸들러 함수
// ===========================================================================

/**
 * POST ?action=enter  -- 응모 등록
 */
function handle_enter($connect, $member, $has_member_id_col) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'POST 요청만 허용됩니다.']);
        return;
    }

    // JSON body 또는 form-data 모두 지원
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $csrf = (string)($input['csrf_token'] ?? '');
    if ($csrf === '' || empty($_SESSION['event_entry_csrf']) || !hash_equals((string)$_SESSION['event_entry_csrf'], $csrf)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => '요청이 만료되었습니다. 페이지를 새로고침해주세요.']);
        return;
    }

    $event_id   = intval($input['event_id'] ?? 0);
    $user_name  = trim($input['user_name'] ?? '');
    $user_phone = trim($input['user_phone'] ?? '');
    $user_email = trim($input['user_email'] ?? '');
    $member_id  = $member['mb_id'] ?? '';

    // --- 기본 유효성 ---
    if ($event_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '이벤트 ID가 올바르지 않습니다.']);
        return;
    }
    if ($user_name === '' || $user_phone === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '이름과 전화번호를 입력해주세요.']);
        return;
    }
    if ($user_email !== '' && !filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '올바른 이메일 형식을 입력해주세요.']);
        return;
    }

    // 이벤트 행을 잠궈 정원 확인과 INSERT를 직렬화한다.
    // 동시 응모 시 max_entries 초과 및 중복 INSERT를 방지한다.
    mysqli_begin_transaction($connect);

    // --- 이벤트 존재 / 활성 / 응모 가능 확인 ---
    $stmt = mysqli_prepare($connect,
        "SELECT id, title, has_raffle, raffle_type, max_entries, raffle_end_date,
                raffle_prize, raffle_winner_count, is_active
         FROM gangnamunni_events WHERE id = ? FOR UPDATE");
    mysqli_stmt_bind_param($stmt, 'i', $event_id);
    mysqli_stmt_execute($stmt);
    $event = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    if (!$event) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => '존재하지 않는 이벤트입니다.']);
        return;
    }
    if (intval($event['is_active']) !== 1) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '현재 진행 중이지 않은 이벤트입니다.']);
        return;
    }
    if (intval($event['has_raffle']) !== 1) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '이 이벤트는 응모 기능이 없습니다.']);
        return;
    }

    // --- 응모 기한 확인 ---
    if (!empty($event['raffle_end_date'])) {
        $end = strtotime($event['raffle_end_date']);
        $today = strtotime(date('Y-m-d'));
        if ($end !== false && $today > $end) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => '응모 기간이 종료되었습니다.']);
            return;
        }
    }

    // --- 최대 응모 수 확인 ---
    $max_entries = intval($event['max_entries']);
    if ($max_entries > 0) {
        $stmt = mysqli_prepare($connect,
            "SELECT COUNT(*) AS cnt FROM gangnamunni_event_entries WHERE event_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $event_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
        mysqli_stmt_close($stmt);

        if (intval($row['cnt']) >= $max_entries) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => '응모 정원이 마감되었습니다.']);
            return;
        }
    }

    // --- 중복 응모 확인 (member_id 우선, 없으면 user_email 폴백) ---
    if ($has_member_id_col) {
        $stmt = mysqli_prepare($connect,
            "SELECT id FROM gangnamunni_event_entries WHERE event_id = ? AND member_id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'is', $event_id, $member_id);
    } else {
        $stmt = mysqli_prepare($connect,
            "SELECT id FROM gangnamunni_event_entries WHERE event_id = ? AND user_email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'is', $event_id, $user_email);
    }
    mysqli_stmt_execute($stmt);
    $dup = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    if ($dup) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => '이미 이 이벤트에 응모하셨습니다.']);
        return;
    }

    // --- INSERT ---
    if ($has_member_id_col) {
        $stmt = mysqli_prepare($connect,
            "INSERT INTO gangnamunni_event_entries
                (event_id, member_id, user_name, user_phone, user_email, status, created_at)
             VALUES (?, ?, ?, ?, ?, 'entered', NOW())");
        mysqli_stmt_bind_param($stmt, 'issss', $event_id, $member_id, $user_name, $user_phone, $user_email);
    } else {
        $stmt = mysqli_prepare($connect,
            "INSERT INTO gangnamunni_event_entries
                (event_id, user_name, user_phone, user_email, created_at)
             VALUES (?, ?, ?, ?, NOW())");
        mysqli_stmt_bind_param($stmt, 'isss', $event_id, $user_name, $user_phone, $user_email);
    }

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        mysqli_rollback($connect);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '응모 처리 중 오류가 발생했습니다.']);
        return;
    }
    $entry_id = mysqli_insert_id($connect);
    mysqli_stmt_close($stmt);
    mysqli_commit($connect);

    // --- 사용자 알림 ---
    $notif_file = __DIR__ . '/includes/user_notifications.php';
    if (file_exists($notif_file)) {
        require_once $notif_file;
        if (function_exists('create_user_notification')) {
            $event_title = $event['title'] ?? '이벤트';
            create_user_notification(
                $connect,
                $member_id,
                'raffle',
                "[{$event_title}] 응모가 완료되었습니다",
                "이벤트 응모가 정상적으로 접수되었습니다. 추첨 결과를 기다려주세요!",
                "event_detail.php?id={$event_id}"
            );
        }
    }

    // --- 관리자 알림 ---
    $admin_notif_file = __DIR__ . '/includes/admin_notifications.php';
    if (file_exists($admin_notif_file)) {
        require_once $admin_notif_file;
        if (function_exists('create_notification')) {
            $event_title = $event['title'] ?? '이벤트';
            create_notification(
                $connect,
                'consultation_new',
                "새 응모: {$event_title}",
                "{$user_name}님이 [{$event_title}] 이벤트에 응모했습니다.",
                "admin_events.php?id={$event_id}#entries"
            );
        }
    }

    http_response_code(200);
    echo json_encode([
        'success'  => true,
        'message'  => '응모가 완료되었습니다!',
        'entry_id' => $entry_id,
    ]);
}

/**
 * GET ?action=status&event_id=N  -- 응모 상태 확인
 */
function handle_status($connect, $member, $has_member_id_col) {
    $event_id  = intval($_GET['event_id'] ?? 0);
    $member_id = $member['mb_id'] ?? '';

    if ($event_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '이벤트 ID가 올바르지 않습니다.']);
        return;
    }

    // 이벤트 정보
    $stmt = mysqli_prepare($connect,
        "SELECT id, title, max_entries, raffle_end_date, is_active, has_raffle
         FROM gangnamunni_events WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $event_id);
    mysqli_stmt_execute($stmt);
    $event = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    if (!$event) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => '존재하지 않는 이벤트입니다.']);
        return;
    }

    // 총 응모 수
    $stmt = mysqli_prepare($connect,
        "SELECT COUNT(*) AS cnt FROM gangnamunni_event_entries WHERE event_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $event_id);
    mysqli_stmt_execute($stmt);
    $total = intval(mysqli_stmt_get_result($stmt)->fetch_assoc()['cnt'] ?? 0);
    mysqli_stmt_close($stmt);

    // 기한 만료 여부
    $is_expired = false;
    if (!empty($event['raffle_end_date'])) {
        $end = strtotime($event['raffle_end_date']);
        $today = strtotime(date('Y-m-d'));
        if ($end !== false && $today > $end) {
            $is_expired = true;
        }
    }

    // 현재 회원 응모 여부
    if ($has_member_id_col) {
        $stmt = mysqli_prepare($connect,
            "SELECT id, event_id, member_id, user_name, user_phone, user_email,
                    is_winner, status, created_at
             FROM gangnamunni_event_entries
             WHERE event_id = ? AND member_id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'is', $event_id, $member_id);
    } else {
        $user_email = $member['mb_email'] ?? '';
        $stmt = mysqli_prepare($connect,
            "SELECT id, event_id, user_name, user_phone, user_email,
                    is_winner, created_at
             FROM gangnamunni_event_entries
             WHERE event_id = ? AND user_email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'is', $event_id, $user_email);
    }
    mysqli_stmt_execute($stmt);
    $entry = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    $entered = ($entry !== null);

    // 민감 정보 마스킹 (전화번호 중간 자리)
    if ($entry && !empty($entry['user_phone'])) {
        $phone = $entry['user_phone'];
        if (mb_strlen($phone) >= 7) {
            $entry['user_phone'] = mb_substr($phone, 0, 3) . '-****-' . mb_substr($phone, -4);
        }
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'entered' => $entered,
        'entry'   => $entered ? $entry : null,
        'event'   => [
            'total_entries' => $total,
            'max_entries'   => intval($event['max_entries']),
            'is_expired'    => $is_expired,
        ],
    ]);
}

/**
 * GET ?action=list  -- 내 응모 목록
 */
function handle_list($connect, $member, $has_member_id_col) {
    $member_id  = $member['mb_id'] ?? '';
    $user_email = $member['mb_email'] ?? '';

    if ($has_member_id_col) {
        $stmt = mysqli_prepare($connect,
            "SELECT ee.id            AS entry_id,
                    ee.event_id,
                    ee.user_name,
                    ee.user_phone,
                    ee.user_email,
                    ee.is_winner,
                    ee.status,
                    ee.created_at    AS entered_at,
                    e.title          AS event_title,
                    e.raffle_prize,
                    e.raffle_end_date,
                    e.raffle_winner_count,
                    e.is_active      AS event_active
             FROM gangnamunni_event_entries ee
             JOIN gangnamunni_events e ON e.id = ee.event_id
             WHERE ee.member_id = ?
             ORDER BY ee.created_at DESC");
        mysqli_stmt_bind_param($stmt, 's', $member_id);
    } else {
        $stmt = mysqli_prepare($connect,
            "SELECT ee.id            AS entry_id,
                    ee.event_id,
                    ee.user_name,
                    ee.user_phone,
                    ee.user_email,
                    ee.is_winner,
                    ee.created_at    AS entered_at,
                    e.title          AS event_title,
                    e.raffle_prize,
                    e.raffle_end_date,
                    e.raffle_winner_count,
                    e.is_active      AS event_active
             FROM gangnamunni_event_entries ee
             JOIN gangnamunni_events e ON e.id = ee.event_id
             WHERE ee.user_email = ?
             ORDER BY ee.created_at DESC");
        mysqli_stmt_bind_param($stmt, 's', $user_email);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $entries = [];

    while ($row = mysqli_fetch_assoc($result)) {
        // 응모 기한 만료 여부 계산
        $row['is_expired'] = false;
        if (!empty($row['raffle_end_date'])) {
            $end = strtotime($row['raffle_end_date']);
            $today = strtotime(date('Y-m-d'));
            if ($end !== false && $today > $end) {
                $row['is_expired'] = true;
            }
        }

        // 전화번호 마스킹
        if (!empty($row['user_phone'])) {
            $phone = $row['user_phone'];
            if (mb_strlen($phone) >= 7) {
                $row['user_phone'] = mb_substr($phone, 0, 3) . '-****-' . mb_substr($phone, -4);
            }
        }

        $entries[] = $row;
    }

    mysqli_stmt_close($stmt);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'entries' => $entries,
        'total'   => count($entries),
    ]);
}
