<?php
/**
 * 알림 보내기 - 회원에게 알림 발송
 * Bootstrap 5.3 + 주간/야간 모드
 * @created 2026-02-27
 */

// ===== AJAX 요청은 HTML 렌더링 전에 처리 =====
$is_ajax = (isset($_POST['action']) || (isset($_GET['action']) && $_GET['action'] === 'history'));

if ($is_ajax) {
    // AJAX: 세션 + DB만 로드 (HTML 렌더링 안함)
    session_start();
    if (empty($_SESSION['ad_admin_logged_in'])) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '로그인이 필요합니다.']);
        exit;
    }
    if (!in_array($_SESSION['ad_admin_role'] ?? 'viewer', ['super_admin', 'admin'], true)) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => '권한이 없습니다.']);
        exit;
    }
    // DB 연결 (일반 페이지와 동일한 운영 설정 사용)
    require_once __DIR__ . '/../dbconfig.php';
    $connect = @mysqli_connect(
        G5_MYSQL_HOST,
        G5_MYSQL_USER,
        G5_MYSQL_PASSWORD,
        G5_MYSQL_DB,
        defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306
    );
    if (!$connect) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'DB 연결에 실패했습니다.']);
        exit;
    }
    mysqli_set_charset($connect, 'utf8mb4');
    require_once __DIR__ . '/theme_helper.php';
    if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }
    loadThemeSettings($connect);
    require_once __DIR__ . '/includes/user_notifications.php';
    require_once __DIR__ . '/includes/admin_fcm.php';

    // 발송 이력 테이블 자동 생성
    mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_notification_sends (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        admin_username VARCHAR(50),
        target_type VARCHAR(50),
        target_count INT DEFAULT 0,
        notification_type VARCHAR(50),
        title VARCHAR(255),
        message TEXT,
        link VARCHAR(500),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    // --- 대상 회원 목록 조회 함수 ---
    function get_target_members($connect, $target, $event_id = 0, $member_id = '', $member_ids = []) {
        $members = [];

        switch ($target) {
            case 'all':
                $result = mysqli_query($connect, "SELECT mb_id FROM g5_member WHERE mb_level > 1");
                while ($row = mysqli_fetch_assoc($result)) {
                    $members[] = $row['mb_id'];
                }
                break;

            case 'recent_7days':
                $result = mysqli_query($connect, "SELECT mb_id FROM g5_member WHERE mb_level > 1 AND mb_datetime > DATE_SUB(NOW(), INTERVAL 7 DAY)");
                while ($row = mysqli_fetch_assoc($result)) {
                    $members[] = $row['mb_id'];
                }
                break;

            case 'event_entrants':
                // member_id 컬럼 존재 여부 체크
                $col_check = mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_event_entries LIKE 'member_id'");
                if ($col_check && mysqli_num_rows($col_check) > 0) {
                    $result = mysqli_query($connect, "SELECT DISTINCT member_id FROM gangnamunni_event_entries WHERE member_id IS NOT NULL AND member_id != ''");
                    while ($row = mysqli_fetch_assoc($result)) {
                        $members[] = $row['member_id'];
                    }
                } else {
                    // fallback: user_email -> g5_member mb_email 매핑
                    $result = mysqli_query($connect, "SELECT DISTINCT e.user_email, m.mb_id FROM gangnamunni_event_entries e INNER JOIN g5_member m ON e.user_email = m.mb_email WHERE e.user_email IS NOT NULL AND e.user_email != ''");
                    while ($row = mysqli_fetch_assoc($result)) {
                        $members[] = $row['mb_id'];
                    }
                }
                break;

            case 'specific_event':
                if ($event_id > 0) {
                    $col_check = mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_event_entries LIKE 'member_id'");
                    if ($col_check && mysqli_num_rows($col_check) > 0) {
                        $stmt = mysqli_prepare($connect, "SELECT DISTINCT member_id FROM gangnamunni_event_entries WHERE event_id = ? AND member_id IS NOT NULL AND member_id != ''");
                        mysqli_stmt_bind_param($stmt, 'i', $event_id);
                        mysqli_stmt_execute($stmt);
                        $result = mysqli_stmt_get_result($stmt);
                        while ($row = mysqli_fetch_assoc($result)) {
                            $members[] = $row['member_id'];
                        }
                        mysqli_stmt_close($stmt);
                    } else {
                        $stmt = mysqli_prepare($connect, "SELECT DISTINCT e.user_email, m.mb_id FROM gangnamunni_event_entries e INNER JOIN g5_member m ON e.user_email = m.mb_email WHERE e.event_id = ? AND e.user_email IS NOT NULL AND e.user_email != ''");
                        mysqli_stmt_bind_param($stmt, 'i', $event_id);
                        mysqli_stmt_execute($stmt);
                        $result = mysqli_stmt_get_result($stmt);
                        while ($row = mysqli_fetch_assoc($result)) {
                            $members[] = $row['mb_id'];
                        }
                        mysqli_stmt_close($stmt);
                    }
                }
                break;

            case 'individual':
                if (!empty($member_id)) {
                    // 회원 존재 확인
                    $stmt = mysqli_prepare($connect, "SELECT mb_id FROM g5_member WHERE mb_id = ?");
                    mysqli_stmt_bind_param($stmt, 's', $member_id);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    if ($row = mysqli_fetch_assoc($result)) {
                        $members[] = $row['mb_id'];
                    }
                    mysqli_stmt_close($stmt);
                }
                break;

            case 'selected':
                if (!is_array($member_ids)) {
                    $member_ids = [$member_ids];
                }
                $member_ids = array_values(array_unique(array_filter(array_map('trim', $member_ids))));
                if (!empty($member_ids)) {
                    $escaped = array_map(
                        static fn ($id) => "'" . mysqli_real_escape_string($connect, $id) . "'",
                        $member_ids
                    );
                    $result = mysqli_query(
                        $connect,
                        "SELECT mb_id FROM g5_member WHERE mb_level > 1 AND mb_id IN (" . implode(',', $escaped) . ")"
                    );
                    while ($result && $row = mysqli_fetch_assoc($result)) {
                        $members[] = $row['mb_id'];
                    }
                }
                break;
        }

        return array_unique($members);
    }

    // --- action=preview: 대상 인원 수 반환 ---
    if ($action === 'preview') {
        $target = $_POST['target'] ?? 'all';
        $event_id = intval($_POST['event_id'] ?? 0);
        $member_id = trim($_POST['member_id'] ?? '');
        $member_ids = $_POST['member_ids'] ?? [];

        $members = get_target_members($connect, $target, $event_id, $member_id, $member_ids);
        echo json_encode(['success' => true, 'count' => count($members)]);
        exit;
    }

    // --- action=send: 알림 발송 ---
    if ($action === 'send') {
        $target = $_POST['target'] ?? 'all';
        $event_id = intval($_POST['event_id'] ?? 0);
        $member_id_input = trim($_POST['member_id'] ?? '');
        $member_ids_input = $_POST['member_ids'] ?? [];
        $type = $_POST['type'] ?? 'general';
        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $link = trim($_POST['link'] ?? '');

        // 유효성 검사
        if (empty($title)) {
            echo json_encode(['success' => false, 'error' => '제목을 입력해 주세요.']);
            exit;
        }
        if (mb_strlen($title) > 200 || mb_strlen($message) > 1000) {
            echo json_encode(['success' => false, 'error' => '제목 또는 내용이 너무 깁니다.']);
            exit;
        }
        if ($link !== '') {
            $scheme = strtolower((string)parse_url($link, PHP_URL_SCHEME));
            if (!in_array($scheme, ['http', 'https'], true)) {
                echo json_encode(['success' => false, 'error' => '링크는 http/https 주소만 사용할 수 있습니다.']);
                exit;
            }
        }

        // 허용된 알림 유형만
        $allowed_types = ['general', 'event', 'promotion', 'system'];
        if (!in_array($type, $allowed_types)) {
            $type = 'general';
        }

        $csrf = (string)($_POST['admin_csrf'] ?? '');
        $session_csrf = (string)($_SESSION['admin_send_notification_csrf'] ?? '');
        if ($session_csrf === '' || !hash_equals($session_csrf, $csrf)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => '요청이 만료되었습니다. 페이지를 새로고침해주세요.']);
            exit;
        }

        $members = get_target_members($connect, $target, $event_id, $member_id_input, $member_ids_input);

        if (empty($members)) {
            echo json_encode(['success' => false, 'error' => '발송 대상이 없습니다.']);
            exit;
        }

        // 일괄 알림 생성
        $sent_count = create_user_notifications_batch(
            $connect,
            $members,
            $type,
            $title,
            !empty($message) ? $message : null,
            !empty($link) ? $link : null
        );

        // Native Android push: members without an active token still receive
        // the in-site notification created above.
        if ($target === 'all') {
            $push_result = send_push_to_android_broadcast(
                $connect,
                $title,
                !empty($message) ? $message : $title,
                !empty($link) ? $link : null
            );
        } else {
            $push_result = send_push_to_members(
                $connect,
                $members,
                $title,
                !empty($message) ? $message : $title,
                !empty($link) ? $link : null
            );
        }

        // 발송 이력 기록
        $admin_id = intval($_SESSION['ad_admin_id'] ?? 0);
        $admin_username = $_SESSION['ad_admin_user'] ?? '';
        $target_label_map = [
            'all' => '전체 회원',
            'recent_7days' => '최근 7일 가입자',
            'event_entrants' => '이벤트 응모자(전체)',
            'specific_event' => '특정 이벤트(#' . $event_id . ')',
            'individual' => '개별(' . $member_id_input . ')',
            'selected' => '선택 회원(' . count($members) . '명)',
        ];
        $target_label = $target_label_map[$target] ?? $target;

        $stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_notification_sends (admin_id, admin_username, target_type, target_count, notification_type, title, message, link) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'ississss', $admin_id, $admin_username, $target_label, $sent_count, $type, $title, $message, $link);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        echo json_encode([
            'success' => true,
            'sent' => $sent_count,
            'push_sent' => (int)($push_result['sent'] ?? 0),
            'push_failed' => (int)($push_result['failed'] ?? 0),
            'message' => $sent_count . '명에게 알림 저장, 푸시 '
                . (int)($push_result['sent'] ?? 0) . '건 성공, '
                . (int)($push_result['failed'] ?? 0) . '건 실패'
        ]);
        exit;
    }

    // --- action=history: 발송 이력 ---
    if ($action === 'history') {
        // GET으로도 처리 (아래에서 GET 핸들러로 이동)
    }

    // action=history (GET)
    if ($action === 'history') {
        $result = mysqli_query($connect, "SELECT * FROM gangnamunni_notification_sends ORDER BY created_at DESC LIMIT 20");
        $history = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $history[] = $row;
            }
        }
        echo json_encode(['success' => true, 'data' => $history]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => '알 수 없는 요청입니다.']);
    exit;
}

// ===== 일반 페이지 렌더링 =====
session_start();
if (empty($_SESSION['admin_send_notification_csrf'])) {
    $_SESSION['admin_send_notification_csrf'] = bin2hex(random_bytes(32));
}
$admin_send_notification_csrf = $_SESSION['admin_send_notification_csrf'];
$admin_page_title = '알림 보내기';
$admin_current = 'send_notification';
$admin_page_description = '회원에게 알림을 발송합니다. 전체/그룹/개별 대상을 선택하여 알림을 보낼 수 있습니다.';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }
$connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
if ($connect) { mysqli_set_charset($connect, 'utf8mb4'); loadThemeSettings($connect); }
require_admin_login();
require_once __DIR__ . '/includes/admin_header.php';

// 역할 기반 접근 제한
$current_role = $_SESSION['ad_admin_role'] ?? 'viewer';
if (!in_array($current_role, ['super_admin', 'admin'])) {
    http_response_code(403);
    echo '<div class="text-center py-5"><h4>접근 권한이 없습니다</h4>';
    echo '<p class="text-body-secondary">이 페이지는 관리자 이상만 접근할 수 있습니다.</p>';
    echo '<a href="dashboard.php" class="btn btn-primary">대시보드로 돌아가기</a></div>';
    require_once __DIR__ . '/includes/admin_footer.php';
    exit;
}

// ===== 이벤트 목록 조회 (has_raffle=1) =====
$events = [];
$ev_result = mysqli_query($connect, "SELECT id, title FROM gangnamunni_events WHERE has_raffle = 1 ORDER BY id DESC");
if ($ev_result) {
    while ($row = mysqli_fetch_assoc($ev_result)) {
        $events[] = $row;
    }
}

// Push-enabled members for explicit multi-selection.
$push_members = [];
$push_member_result = mysqli_query(
    $connect,
    "SELECT m.mb_id,
            COALESCE(NULLIF(m.mb_nick, ''), NULLIF(m.mb_name, ''), m.mb_id) AS display_name,
            COUNT(DISTINCT t.pt_id) AS token_count
     FROM g5_member m
     INNER JOIN g5_push_tokens t ON t.mb_id = m.mb_id AND t.pt_use = 1
     WHERE m.mb_level > 1
     GROUP BY m.mb_id, m.mb_nick, m.mb_name
     ORDER BY MAX(t.pt_datetime) DESC
     LIMIT 500"
);
while ($push_member_result && $row = mysqli_fetch_assoc($push_member_result)) {
    $push_members[] = $row;
}
?>

<!-- 페이지 콘텐츠 -->
<div class="row g-4">
    <!-- 좌측: 발송 폼 -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-send text-primary"></i>
                <strong>알림 발송</strong>
            </div>
            <div class="card-body">
                <form id="notifForm" autocomplete="off">
                    <!-- 대상 선택 -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><i class="bi bi-people me-1"></i>대상 선택</label>
                        <div class="d-flex flex-column gap-2">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="target" id="target_all" value="all" checked>
                                <label class="form-check-label" for="target_all">
                                    <i class="bi bi-globe2 me-1 text-primary"></i>전체 회원
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="target" id="target_recent" value="recent_7days">
                                <label class="form-check-label" for="target_recent">
                                    <i class="bi bi-clock-history me-1 text-info"></i>최근 7일 가입자
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="target" id="target_event_all" value="event_entrants">
                                <label class="form-check-label" for="target_event_all">
                                    <i class="bi bi-gift me-1 text-success"></i>이벤트 응모자 (전체)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="target" id="target_event_specific" value="specific_event">
                                <label class="form-check-label" for="target_event_specific">
                                    <i class="bi bi-calendar-event me-1 text-warning"></i>특정 이벤트 응모자
                                </label>
                            </div>
                            <!-- 이벤트 선택 드롭다운 (숨김 기본) -->
                            <div id="eventSelectWrap" class="ms-4" style="display:none;">
                                <select class="form-select form-select-sm" id="eventSelect" name="event_id">
                                    <option value="">-- 이벤트 선택 --</option>
                                    <?php foreach ($events as $ev): ?>
                                    <option value="<?php echo intval($ev['id']); ?>"><?php echo htmlspecialchars($ev['title']); ?> (#<?php echo intval($ev['id']); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($events)): ?>
                                <small class="text-body-secondary">추첨 이벤트가 없습니다.</small>
                                <?php endif; ?>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="target" id="target_individual" value="individual">
                                <label class="form-check-label" for="target_individual">
                                    <i class="bi bi-person me-1 text-secondary"></i>개별 회원
                                </label>
                            </div>
                            <!-- 개별 회원 ID 입력 (숨김 기본) -->
                            <div id="memberIdWrap" class="ms-4" style="display:none;">
                                <input type="text" class="form-control form-control-sm" id="memberIdInput" name="member_id" placeholder="회원 ID (mb_id)">
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="target" id="target_selected" value="selected">
                                <label class="form-check-label" for="target_selected">
                                    <i class="bi bi-person-check me-1 text-success"></i>푸시 회원 여러 명 선택
                                </label>
                            </div>
                            <div id="memberSelectWrap" class="ms-4" style="display:none;">
                                <input type="search" class="form-control form-control-sm mb-2" id="memberSearchInput" placeholder="이름 또는 회원 ID 검색">
                                <select class="form-select form-select-sm" id="memberSelect" name="member_ids[]" multiple size="8">
                                    <?php foreach ($push_members as $push_member): ?>
                                    <option value="<?php echo htmlspecialchars($push_member['mb_id'], ENT_QUOTES); ?>">
                                        <?php echo htmlspecialchars($push_member['display_name']); ?>
                                        (<?php echo htmlspecialchars($push_member['mb_id']); ?>)
                                        · <?php echo intval($push_member['token_count']); ?> device
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-body-secondary">Ctrl/Cmd 또는 Shift bilan bir nechta user tanlang.</small>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <!-- 알림 유형 -->
                    <div class="mb-3">
                        <label for="notifType" class="form-label fw-semibold"><i class="bi bi-tag me-1"></i>알림 유형</label>
                        <select class="form-select" id="notifType" name="type">
                            <option value="general">일반 알림</option>
                            <option value="event">이벤트</option>
                            <option value="promotion">프로모션</option>
                            <option value="system">시스템</option>
                        </select>
                    </div>

                    <!-- 제목 -->
                    <div class="mb-3">
                        <label for="notifTitle" class="form-label fw-semibold"><i class="bi bi-type me-1"></i>제목 <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="notifTitle" name="title" required placeholder="알림 제목을 입력하세요" maxlength="200">
                    </div>

                    <!-- 내용 -->
                    <div class="mb-3">
                        <label for="notifMessage" class="form-label fw-semibold"><i class="bi bi-chat-text me-1"></i>내용</label>
                        <textarea class="form-control" id="notifMessage" name="message" rows="4" placeholder="알림 내용 (선택사항)" maxlength="1000"></textarea>
                        <div class="form-text"><span id="msgCharCount">0</span>/1000</div>
                    </div>

                    <!-- 링크 -->
                    <div class="mb-3">
                        <label for="notifLink" class="form-label fw-semibold"><i class="bi bi-link-45deg me-1"></i>링크</label>
                        <input type="url" class="form-control" id="notifLink" name="link" placeholder="알림 클릭 시 이동할 URL (선택사항)">
                    </div>

                    <!-- 버튼 -->
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary flex-fill" id="previewBtn">
                            <i class="bi bi-eye me-1"></i>미리보기
                        </button>
                        <button type="button" class="btn btn-primary flex-fill" id="sendBtn" style="background:#AE1932;border-color:#AE1932;">
                            <i class="bi bi-send me-1"></i>발송하기
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 우측: 미리보기 + 발송 이력 -->
    <div class="col-lg-6">
        <!-- 미리보기 카드 -->
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-eye text-info"></i>
                <strong>알림 미리보기</strong>
                <span class="badge bg-secondary ms-auto" id="previewCount">대상: -명</span>
            </div>
            <div class="card-body">
                <div id="previewArea">
                    <div class="text-center text-body-secondary py-3" style="font-size:13px;">
                        <i class="bi bi-bell-slash d-block mb-2" style="font-size:32px;"></i>
                        미리보기 버튼을 눌러 확인하세요
                    </div>
                </div>
            </div>
        </div>

        <!-- 발송 이력 카드 -->
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-clock-history text-warning"></i>
                <strong>발송 이력</strong>
                <button class="btn btn-sm btn-outline-secondary ms-auto" id="refreshHistoryBtn" title="새로고침">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0" style="font-size:13px;">
                        <thead class="table-light">
                            <tr>
                                <th style="width:130px;">날짜</th>
                                <th>대상</th>
                                <th>제목</th>
                                <th style="width:70px;" class="text-end">발송 수</th>
                            </tr>
                        </thead>
                        <tbody id="historyBody">
                            <tr>
                                <td colspan="4" class="text-center text-body-secondary py-3">
                                    <i class="bi bi-hourglass-split"></i> 로딩 중...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 토스트 알림 -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div class="toast align-items-center border-0" id="resultToast" role="alert" aria-live="assertive">
        <div class="d-flex">
            <div class="toast-body" id="toastBody"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    // DOM 요소
    var form = document.getElementById('notifForm');
    var previewBtn = document.getElementById('previewBtn');
    var sendBtn = document.getElementById('sendBtn');
    var previewArea = document.getElementById('previewArea');
    var previewCount = document.getElementById('previewCount');
    var historyBody = document.getElementById('historyBody');
    var refreshHistoryBtn = document.getElementById('refreshHistoryBtn');
    var eventSelectWrap = document.getElementById('eventSelectWrap');
    var memberIdWrap = document.getElementById('memberIdWrap');
    var memberSelectWrap = document.getElementById('memberSelectWrap');
    var memberSelect = document.getElementById('memberSelect');
    var memberSearchInput = document.getElementById('memberSearchInput');
    var msgCharCount = document.getElementById('msgCharCount');
    // Some Android WebView/Safari versions reject location.pathname directly
    // in fetch() with "The string did not match the expected pattern".
    var endpointUrl = window.location.protocol + '//' + window.location.host + window.location.pathname;

    // 알림 유형 정보
    var typeInfo = {
        general:   { label: '일반 알림',  icon: 'bi-bell',           color: '#6c757d', bg: 'bg-secondary' },
        event:     { label: '이벤트',     icon: 'bi-calendar-event', color: '#AE1932', bg: 'bg-warning'   },
        promotion: { label: '프로모션',   icon: 'bi-megaphone',      color: '#dc3545', bg: 'bg-danger'    },
        system:    { label: '시스템',     icon: 'bi-gear',           color: '#0d6efd', bg: 'bg-primary'   }
    };

    // 대상 선택 변경 시 하위 입력 표시/숨김
    var targetRadios = document.querySelectorAll('input[name="target"]');
    targetRadios.forEach(function(radio) {
        radio.addEventListener('change', function() {
            eventSelectWrap.style.display = this.value === 'specific_event' ? 'block' : 'none';
            memberIdWrap.style.display = this.value === 'individual' ? 'block' : 'none';
            memberSelectWrap.style.display = this.value === 'selected' ? 'block' : 'none';
        });
    });

    memberSearchInput.addEventListener('input', function() {
        var query = this.value.trim().toLowerCase();
        Array.prototype.forEach.call(memberSelect.options, function(option) {
            option.hidden = query !== '' && option.text.toLowerCase().indexOf(query) === -1;
        });
    });

    // 글자 수 카운터
    var msgTextarea = document.getElementById('notifMessage');
    if (msgTextarea) {
        msgTextarea.addEventListener('input', function() {
            msgCharCount.textContent = this.value.length;
        });
    }

    // 폼 데이터 수집
    function getFormData() {
        var target = document.querySelector('input[name="target"]:checked').value;
        var data = {
            target: target,
            event_id: document.getElementById('eventSelect').value || '0',
            member_id: document.getElementById('memberIdInput').value || '',
            member_ids: Array.prototype.map.call(memberSelect.selectedOptions, function(option) {
                return option.value;
            }),
            admin_csrf: <?php echo json_encode($admin_send_notification_csrf); ?>,
            type: document.getElementById('notifType').value,
            title: document.getElementById('notifTitle').value.trim(),
            message: document.getElementById('notifMessage').value.trim(),
            link: document.getElementById('notifLink').value.trim()
        };
        return data;
    }

    // URLSearchParams 빌드
    function buildParams(data, action) {
        var params = new URLSearchParams();
        params.append('action', action);
        for (var key in data) {
            if (Array.isArray(data[key])) {
                data[key].forEach(function(value) {
                    params.append(key + '[]', value);
                });
            } else {
                params.append(key, data[key]);
            }
        }
        return params;
    }

    // 토스트 표시
    function showToast(msg, isSuccess) {
        var toastEl = document.getElementById('resultToast');
        var toastBody = document.getElementById('toastBody');
        toastEl.className = 'toast align-items-center border-0 text-white ' + (isSuccess ? 'bg-success' : 'bg-danger');
        toastBody.textContent = msg;
        var toast = bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 4000 });
        toast.show();
    }

    function parseJsonResponse(response) {
        return response.text().then(function(text) {
            var payload;
            try {
                payload = JSON.parse(text);
            } catch (e) {
                throw new Error('HTTP ' + response.status + ': server returned a non-JSON response');
            }
            if (!response.ok && !payload.error) {
                throw new Error('HTTP ' + response.status);
            }
            return payload;
        });
    }

    // 미리보기 렌더링
    function renderPreview(data, count) {
        var ti = typeInfo[data.type] || typeInfo.general;
        var html = '';
        html += '<div class="border rounded p-3" style="background:var(--bs-tertiary-bg);">';
        html += '  <div class="d-flex align-items-start gap-2">';
        html += '    <span class="badge ' + ti.bg + '" style="min-width:28px;padding:6px;"><i class="bi ' + ti.icon + '"></i></span>';
        html += '    <div style="flex:1;min-width:0;">';
        html += '      <div class="fw-semibold" style="font-size:14px;">' + escapeHtml(data.title || '(제목 없음)') + '</div>';
        if (data.message) {
            html += '      <div class="text-body-secondary mt-1" style="font-size:13px;white-space:pre-wrap;">' + escapeHtml(data.message) + '</div>';
        }
        if (data.link) {
            html += '      <div class="mt-1"><a href="' + escapeHtml(data.link) + '" class="text-primary" style="font-size:12px;" target="_blank"><i class="bi bi-link-45deg"></i>' + escapeHtml(data.link) + '</a></div>';
        }
        html += '      <div class="text-body-secondary mt-1" style="font-size:11px;">';
        html += '        <span class="badge ' + ti.bg + ' bg-opacity-25 text-body" style="font-size:10px;">' + ti.label + '</span>';
        html += '        <span class="ms-1">방금</span>';
        html += '      </div>';
        html += '    </div>';
        html += '  </div>';
        html += '</div>';
        html += '<div class="text-center mt-2">';
        html += '  <span class="badge bg-info text-dark" style="font-size:12px;"><i class="bi bi-people me-1"></i>' + count + '명에게 발송됩니다</span>';
        html += '</div>';
        previewArea.innerHTML = html;
        previewCount.textContent = '대상: ' + count + '명';
    }

    // HTML 이스케이프
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // 미리보기 버튼
    previewBtn.addEventListener('click', function() {
        var data = getFormData();

        // 유효성 검사
        if (data.target === 'specific_event' && !data.event_id) {
            showToast('이벤트를 선택해 주세요.', false);
            return;
        }
        if (data.target === 'individual' && !data.member_id) {
            showToast('회원 ID를 입력해 주세요.', false);
            return;
        }
        if (data.target === 'selected' && data.member_ids.length === 0) {
            showToast('회원을 한 명 이상 선택해 주세요.', false);
            return;
        }

        previewBtn.disabled = true;
        previewBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>조회 중...';

        fetch(endpointUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: buildParams(data, 'preview').toString()
        })
        .then(parseJsonResponse)
        .then(function(res) {
            previewBtn.disabled = false;
            previewBtn.innerHTML = '<i class="bi bi-eye me-1"></i>미리보기';

            if (res.success) {
                renderPreview(data, res.count);
            } else {
                showToast(res.error || '조회 실패', false);
            }
        })
        .catch(function(err) {
            previewBtn.disabled = false;
            previewBtn.innerHTML = '<i class="bi bi-eye me-1"></i>미리보기';
            showToast('서버 통신 오류: ' + err.message, false);
        });
    });

    // 발송 버튼
    sendBtn.addEventListener('click', function() {
        var data = getFormData();

        // 유효성 검사
        if (!data.title) {
            showToast('제목을 입력해 주세요.', false);
            document.getElementById('notifTitle').focus();
            return;
        }
        if (data.target === 'specific_event' && !data.event_id) {
            showToast('이벤트를 선택해 주세요.', false);
            return;
        }
        if (data.target === 'individual' && !data.member_id) {
            showToast('회원 ID를 입력해 주세요.', false);
            return;
        }
        if (data.target === 'selected' && data.member_ids.length === 0) {
            showToast('회원을 한 명 이상 선택해 주세요.', false);
            return;
        }

        // 먼저 대상 인원수 확인 후 confirm
        sendBtn.disabled = true;
        sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>확인 중...';

        fetch(endpointUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: buildParams(data, 'preview').toString()
        })
        .then(parseJsonResponse)
        .then(function(previewRes) {
            if (!previewRes.success || previewRes.count === 0) {
                sendBtn.disabled = false;
                sendBtn.innerHTML = '<i class="bi bi-send me-1"></i>발송하기';
                showToast('발송 대상이 없습니다.', false);
                return;
            }

            var confirmed = confirm(previewRes.count + '명에게 알림을 발송하시겠습니까?\n\n제목: ' + data.title);
            if (!confirmed) {
                sendBtn.disabled = false;
                sendBtn.innerHTML = '<i class="bi bi-send me-1"></i>발송하기';
                return;
            }

            sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>발송 중...';

            return fetch(endpointUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: buildParams(data, 'send').toString()
            });
        })
        .then(function(r) {
            if (!r) return; // confirm 취소
            return parseJsonResponse(r);
        })
        .then(function(res) {
            sendBtn.disabled = false;
            sendBtn.innerHTML = '<i class="bi bi-send me-1"></i>발송하기';

            if (!res) return;

            if (res.success) {
                showToast(res.message, true);
                // 이력 새로고침
                loadHistory();
                // 미리보기 업데이트
                previewCount.textContent = '대상: -명';
                previewArea.innerHTML = '<div class="text-center py-3"><i class="bi bi-check-circle text-success d-block mb-2" style="font-size:32px;"></i><span class="text-success fw-semibold">' + res.sent + '명 발송 완료</span></div>';
            } else {
                showToast(res.error || '발송 실패', false);
            }
        })
        .catch(function(err) {
            sendBtn.disabled = false;
            sendBtn.innerHTML = '<i class="bi bi-send me-1"></i>발송하기';
            showToast('서버 통신 오류: ' + err.message, false);
        });
    });

    // 발송 이력 로드
    function loadHistory() {
        fetch(endpointUrl + '?action=history')
        .then(parseJsonResponse)
        .then(function(res) {
            if (!res.success || !res.data || res.data.length === 0) {
                historyBody.innerHTML = '<tr><td colspan="4" class="text-center text-body-secondary py-3"><i class="bi bi-inbox"></i> 발송 이력이 없습니다</td></tr>';
                return;
            }

            var html = '';
            res.data.forEach(function(item) {
                var dt = item.created_at ? item.created_at.substring(0, 16).replace('T', ' ') : '-';
                var ti = typeInfo[item.notification_type] || typeInfo.general;
                html += '<tr>';
                html += '<td class="text-body-secondary" style="font-size:12px;">' + escapeHtml(dt) + '</td>';
                html += '<td><span class="badge ' + ti.bg + ' bg-opacity-75" style="font-size:10px;">' + escapeHtml(item.target_type || '-') + '</span></td>';
                html += '<td class="text-truncate" style="max-width:200px;" title="' + escapeHtml(item.title || '') + '">' + escapeHtml(item.title || '-') + '</td>';
                html += '<td class="text-end fw-semibold">' + (item.target_count || 0) + '</td>';
                html += '</tr>';
            });
            historyBody.innerHTML = html;
        })
        .catch(function() {
            historyBody.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-3"><i class="bi bi-exclamation-triangle"></i> 이력 로드 실패</td></tr>';
        });
    }

    // 이력 새로고침 버튼
    refreshHistoryBtn.addEventListener('click', function() {
        var icon = this.querySelector('i');
        icon.className = 'bi bi-arrow-clockwise spinner-border spinner-border-sm';
        loadHistory();
        setTimeout(function() { icon.className = 'bi bi-arrow-clockwise'; }, 800);
    });

    // 페이지 로드 시 이력 자동 로드
    loadHistory();

})();
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
