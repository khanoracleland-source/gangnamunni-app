<?php
/**
 * 알림 보내기 - 회원에게 알림 발송
 * Bootstrap 5.3 + 주간/야간 모드
 * @created 2026-02-27
 */

// ===== AJAX 요청은 HTML 렌더링 전에 처리 =====
$is_ajax = (
    isset($_POST['action'])
    || (isset($_GET['action']) && in_array($_GET['action'], ['history', 'token_diagnostics'], true))
);

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
    require_once __DIR__ . '/includes/member_notification_service.php';
    member_notification_ensure_tables($connect);

    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    // --- action=preview: 대상 인원 수 반환 ---
    if ($action === 'preview') {
        $csrf = (string)($_POST['admin_csrf'] ?? '');
        $session_csrf = (string)($_SESSION['admin_send_notification_csrf'] ?? '');
        if ($session_csrf === '' || !hash_equals($session_csrf, $csrf)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => '요청이 만료되었습니다. 페이지를 새로고침해주세요.']);
            exit;
        }

        $target = $_POST['target'] ?? 'all';
        $event_id = intval($_POST['event_id'] ?? 0);
        $member_id = trim($_POST['member_id'] ?? '');
        $member_ids = $_POST['member_ids'] ?? [];
        $type = $_POST['type'] ?? 'general';

        echo json_encode(member_notification_preview($connect, $target, $event_id, $member_id, $member_ids, $type));
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

        $csrf = (string)($_POST['admin_csrf'] ?? '');
        $session_csrf = (string)($_SESSION['admin_send_notification_csrf'] ?? '');
        if ($session_csrf === '' || !hash_equals($session_csrf, $csrf)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => '요청이 만료되었습니다. 페이지를 새로고침해주세요.']);
            exit;
        }

        echo json_encode(member_notification_send($connect, [
            'target' => $target,
            'event_id' => $event_id,
            'member_id' => $member_id_input,
            'member_ids' => $member_ids_input,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'link' => $link,
        ], [
            'id' => intval($_SESSION['ad_admin_id'] ?? 0),
            'username' => $_SESSION['ad_admin_user'] ?? '',
        ]));
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

    // action=token_diagnostics (GET)
    if ($action === 'token_diagnostics') {
        $one = static function ($sql) use ($connect) {
            $res = mysqli_query($connect, $sql);
            return $res ? (mysqli_fetch_assoc($res) ?: []) : [];
        };
        $all = static function ($sql) use ($connect) {
            $res = mysqli_query($connect, $sql);
            $rows = [];
            if ($res) {
                while ($row = mysqli_fetch_assoc($res)) $rows[] = $row;
            }
            return $rows;
        };
        $deviceIdFromInfo = static function ($deviceInfo) {
            $decoded = json_decode((string)$deviceInfo, true);
            if (is_array($decoded) && isset($decoded['device_id'])) {
                return trim((string)$decoded['device_id']);
            }
            if (preg_match('/"device_id"\s*:\s*"([^"]+)"/', (string)$deviceInfo, $matches)) {
                return trim((string)$matches[1]);
            }
            return '';
        };

        $summary = $one("
            SELECT
                COUNT(*) AS total_tokens,
                SUM(pt_use = 1) AS active_tokens,
                SUM(pt_use = 1 AND pt_platform = 'android') AS active_android,
                SUM(pt_use = 1 AND pt_platform = 'ios') AS active_ios,
                SUM(pt_use = 1 AND pt_platform = 'web') AS active_web,
                SUM(pt_use = 1 AND (mb_id IS NULL OR mb_id = '')) AS active_empty_member,
                SUM(pt_use = 1 AND pt_platform = 'android'
                    AND (pt_device_info LIKE '%iPhone%' OR pt_device_info LIKE '%iPad%' OR pt_device_info LIKE '%iPod%')) AS ios_marked_android
            FROM g5_push_tokens
        ");
        $eligible = $one("
            SELECT COUNT(*) AS cnt
            FROM g5_push_tokens t
            INNER JOIN g5_member m ON m.mb_id = t.mb_id
            WHERE t.pt_use = 1
              AND t.pt_token <> ''
              AND m.mb_id IS NOT NULL
              AND m.mb_id <> ''
              AND (t.pt_platform = 'ios'
                   OR t.pt_device_info LIKE '%iPhone%'
                   OR t.pt_device_info LIKE '%iPad%'
                   OR t.pt_device_info LIKE '%iPod%')
        ");
        $activeRows = $all("
            SELECT pt_id, mb_id, pt_platform, pt_device_info, pt_datetime
            FROM g5_push_tokens
            WHERE pt_use = 1
              AND mb_id IS NOT NULL
              AND mb_id <> ''
            ORDER BY pt_datetime DESC, pt_id DESC
            LIMIT 2000
        ");
        $duplicateBuckets = [];
        foreach ($activeRows as $row) {
            $deviceId = $deviceIdFromInfo($row['pt_device_info'] ?? '');
            if ($deviceId === '') continue;
            $key = $row['mb_id'] . "\n" . $row['pt_platform'] . "\n" . $deviceId;
            if (!isset($duplicateBuckets[$key])) {
                $duplicateBuckets[$key] = [
                    'mb_id' => $row['mb_id'],
                    'pt_platform' => $row['pt_platform'],
                    'device_id' => $deviceId,
                    'cnt' => 0,
                    'last_seen' => $row['pt_datetime'],
                ];
            }
            $duplicateBuckets[$key]['cnt']++;
        }
        $duplicates = array_values(array_filter($duplicateBuckets, static function ($row) {
            return (int)$row['cnt'] > 1;
        }));
        usort($duplicates, static function ($a, $b) {
            return ((int)$b['cnt'] <=> (int)$a['cnt']) ?: strcmp((string)$b['last_seen'], (string)$a['last_seen']);
        });
        $duplicates = array_slice($duplicates, 0, 10);

        $multiDevices = $all("
            SELECT mb_id, pt_platform, COUNT(*) AS cnt, MAX(pt_datetime) AS last_seen
            FROM g5_push_tokens
            WHERE pt_use = 1
              AND mb_id IS NOT NULL
              AND mb_id <> ''
            GROUP BY mb_id, pt_platform
            HAVING cnt > 1
            ORDER BY cnt DESC, last_seen DESC
            LIMIT 10
        ");
        $recent = $all("
            SELECT mb_id, pt_platform, pt_use, LEFT(pt_token, 18) AS token_start, pt_datetime, pt_device_info
            FROM g5_push_tokens
            ORDER BY pt_datetime DESC
            LIMIT 8
        ");

        echo json_encode([
            'success' => true,
            'summary' => $summary,
            'ios_eligible' => (int)($eligible['cnt'] ?? 0),
            'duplicates' => $duplicates,
            'multi_devices' => $multiDevices,
            'recent' => $recent,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
$admin_page_title = '회원 푸시 발송';
$admin_current = 'send_notification';
$admin_page_description = '회원 앱 푸시와 인앱 알림을 발송하고, Android topic/iOS direct token 상태를 확인합니다.';

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
     WHERE m.mb_id IS NOT NULL AND m.mb_id <> ''
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
                <strong>회원 푸시 / 인앱 발송</strong>
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
                            <option value="booking">예약 알림</option>
                            <option value="event">이벤트</option>
                            <option value="raffle">응모/추첨</option>
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
                        <div class="form-text">서울미녀 도메인 링크만 사용할 수 있습니다.</div>
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

        <!-- 토큰 진단 카드 -->
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-activity text-success"></i>
                <strong>Token Diagnostics</strong>
                <button class="btn btn-sm btn-outline-secondary ms-auto" id="refreshDiagnosticsBtn" title="새로고침">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
            </div>
            <div class="card-body" id="tokenDiagnosticsBody">
                <div class="text-center text-body-secondary py-3" style="font-size:13px;">
                    <i class="bi bi-hourglass-split"></i> 로딩 중...
                </div>
            </div>
        </div>

        <!-- 발송 이력 카드 -->
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-clock-history text-warning"></i>
                <strong>Delivery Log</strong>
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
                                <th style="width:95px;" class="text-end">인앱</th>
                                <th style="width:95px;" class="text-end">푸시</th>
                                <th style="width:180px;">상세</th>
                            </tr>
                        </thead>
                        <tbody id="historyBody">
                            <tr>
                                <td colspan="6" class="text-center text-body-secondary py-3">
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
    var tokenDiagnosticsBody = document.getElementById('tokenDiagnosticsBody');
    var refreshDiagnosticsBtn = document.getElementById('refreshDiagnosticsBtn');
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
        booking:   { label: '예약 알림',  icon: 'bi-calendar-check', color: '#AE1932', bg: 'bg-danger'    },
        event:     { label: '이벤트',     icon: 'bi-calendar-event', color: '#AE1932', bg: 'bg-warning'   },
        raffle:    { label: '응모/추첨',  icon: 'bi-gift',           color: '#198754', bg: 'bg-success'   },
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
    function renderPreview(data, preview) {
        var ti = typeInfo[data.type] || typeInfo.general;
        var inAppCount = preview.in_app_count || preview.count || 0;
        var resolvedCount = preview.resolved_count || inAppCount;
        var pushTokenCount = preview.push_token_count || 0;
        var pushMemberCount = preview.push_member_count || 0;
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
        html += '<div class="text-center mt-2 d-flex flex-wrap justify-content-center gap-1">';
        html += '  <span class="badge bg-info text-dark" style="font-size:12px;"><i class="bi bi-people me-1"></i>대상 ' + resolvedCount + '명</span>';
        html += '  <span class="badge bg-success" style="font-size:12px;"><i class="bi bi-bell me-1"></i>인앱 ' + inAppCount + '명</span>';
        html += '  <span class="badge bg-primary" style="font-size:12px;"><i class="bi bi-phone me-1"></i>푸시 ' + pushMemberCount + '명/' + pushTokenCount + '기기</span>';
        html += '</div>';
        previewArea.innerHTML = html;
        previewCount.textContent = '대상: ' + inAppCount + '명';
    }

    // HTML 이스케이프
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function safeInt(value) {
        var n = parseInt(value, 10);
        return isNaN(n) ? 0 : n;
    }

    function parsePushDetails(item) {
        if (!item || !item.push_details) return {};
        try {
            return JSON.parse(item.push_details);
        } catch (e) {
            return {};
        }
    }

    function renderPlatformStats(stats) {
        if (!stats || typeof stats !== 'object') return '-';
        var parts = [];
        Object.keys(stats).forEach(function(platform) {
            var row = stats[platform] || {};
            var tokens = safeInt(row.tokens);
            var sent = safeInt(row.sent);
            var failed = safeInt(row.failed);
            if (tokens > 0 || sent > 0 || failed > 0) {
                parts.push(platform + ' ' + sent + '/' + tokens + (failed > 0 ? ' 실패 ' + failed : ''));
            }
        });
        return parts.length ? parts.join(', ') : '-';
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
                renderPreview(data, res);
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
                var detail = previewRes && previewRes.success
                    ? ' (resolved ' + (previewRes.resolved_count || 0)
                        + ', push ' + (previewRes.push_member_count || 0)
                        + '명/' + (previewRes.push_token_count || 0) + '기기'
                        + ', skipped ' + (previewRes.skipped_in_app || 0) + ')'
                    : '';
                showToast((previewRes.error || '발송 대상이 없습니다.') + detail, false);
                return;
            }

            var confirmed = confirm(
                previewRes.count + '명에게 인앱 알림을 저장하고, '
                + (previewRes.push_token_count || 0) + '개 기기에 푸시를 발송하시겠습니까?\n\n제목: '
                + data.title
            );
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
                showToast(res.message, res.push_success !== false);
                // 이력/진단 새로고침
                loadHistory();
                loadDiagnostics();
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
                historyBody.innerHTML = '<tr><td colspan="6" class="text-center text-body-secondary py-3"><i class="bi bi-inbox"></i> 발송 이력이 없습니다</td></tr>';
                return;
            }

            var html = '';
            res.data.forEach(function(item) {
                var dt = item.created_at ? item.created_at.substring(0, 16).replace('T', ' ') : '-';
                var ti = typeInfo[item.notification_type] || typeInfo.general;
                var details = parsePushDetails(item);
                var direct = details.direct || {};
                var topic = details.android_topic || null;
                var detailParts = [];
                if (topic) {
                    detailParts.push('Android topic ' + safeInt(topic.sent) + '/' + (safeInt(topic.sent) + safeInt(topic.failed)));
                }
                if (direct.platform_stats) {
                    detailParts.push(renderPlatformStats(direct.platform_stats));
                }
                if (item.push_error) {
                    detailParts.push('오류: ' + item.push_error);
                }
                var detailText = detailParts.filter(Boolean).join(' · ') || '-';
                html += '<tr>';
                html += '<td class="text-body-secondary" style="font-size:12px;">' + escapeHtml(dt) + '</td>';
                html += '<td><span class="badge ' + ti.bg + ' bg-opacity-75" style="font-size:10px;">' + escapeHtml(item.target_type || '-') + '</span></td>';
                html += '<td class="text-truncate" style="max-width:200px;" title="' + escapeHtml(item.title || '') + '">' + escapeHtml(item.title || '-') + '</td>';
                html += '<td class="text-end">' + safeInt(item.in_app_count || item.target_count) + '/' + safeInt(item.resolved_count || item.target_count) + '</td>';
                html += '<td class="text-end"><span class="' + (safeInt(item.push_failed) > 0 ? 'text-danger' : 'text-success') + '">' + safeInt(item.push_sent) + '</span>';
                if (safeInt(item.push_failed) > 0) html += ' <span class="text-danger">-' + safeInt(item.push_failed) + '</span>';
                html += '</td>';
                html += '<td class="text-truncate text-body-secondary" style="max-width:180px;font-size:12px;" title="' + escapeHtml(detailText) + '">' + escapeHtml(detailText) + '</td>';
                html += '</tr>';
            });
            historyBody.innerHTML = html;
        })
        .catch(function() {
            historyBody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-3"><i class="bi bi-exclamation-triangle"></i> 이력 로드 실패</td></tr>';
        });
    }

    function diagBox(label, value, icon, color) {
        return '<div class="col-6 col-md-3">'
            + '<div class="border rounded p-2 h-100">'
            + '<div class="text-body-secondary" style="font-size:11px;"><i class="bi ' + icon + ' me-1 text-' + color + '"></i>' + label + '</div>'
            + '<div class="fw-bold" style="font-size:20px;">' + value + '</div>'
            + '</div></div>';
    }

    function issueBadge(label, value, isDanger) {
        return '<span class="badge ' + (isDanger ? 'bg-danger' : 'bg-success') + ' bg-opacity-75">'
            + escapeHtml(label) + ': ' + value + '</span>';
    }

    function renderDiagnostics(data) {
        var s = data.summary || {};
        var duplicateCount = data.duplicates ? data.duplicates.length : 0;
        var multiDeviceCount = data.multi_devices ? data.multi_devices.length : 0;
        var html = '';
        html += '<div class="row g-2">';
        html += diagBox('전체 active', safeInt(s.active_tokens), 'bi-phone', 'primary');
        html += diagBox('Android', safeInt(s.active_android), 'bi-android2', 'success');
        html += diagBox('iOS', safeInt(s.active_ios), 'bi-apple', 'dark');
        html += diagBox('iOS eligible', safeInt(data.ios_eligible), 'bi-check2-circle', safeInt(data.ios_eligible) > 0 ? 'success' : 'danger');
        html += '</div>';
        html += '<div class="d-flex flex-wrap gap-2 mt-3">';
        html += issueBadge('빈 mb_id active', safeInt(s.active_empty_member), safeInt(s.active_empty_member) > 0);
        html += issueBadge('iPhone인데 android 저장', safeInt(s.ios_marked_android), safeInt(s.ios_marked_android) > 0);
        html += issueBadge('동일 device_id 중복', duplicateCount, duplicateCount > 0);
        html += issueBadge('multi-device user/platform', multiDeviceCount, false);
        html += '</div>';
        if (duplicateCount > 0) {
            html += '<div class="mt-3 small text-body-secondary">';
            html += '<div class="fw-semibold text-body mb-1">동일 device_id 중복 active token 상위</div>';
            data.duplicates.slice(0, 4).forEach(function(row) {
                html += '<div>' + escapeHtml(row.mb_id || '-') + ' · ' + escapeHtml(row.pt_platform || '-') + ' · ' + safeInt(row.cnt) + '개</div>';
            });
            html += '</div>';
        }
        tokenDiagnosticsBody.innerHTML = html;
    }

    function loadDiagnostics() {
        fetch(endpointUrl + '?action=token_diagnostics')
        .then(parseJsonResponse)
        .then(function(res) {
            if (res.success) renderDiagnostics(res);
        })
        .catch(function() {
            tokenDiagnosticsBody.innerHTML = '<div class="text-center text-danger py-3"><i class="bi bi-exclamation-triangle"></i> 진단 로드 실패</div>';
        });
    }

    // 이력 새로고침 버튼
    refreshHistoryBtn.addEventListener('click', function() {
        var icon = this.querySelector('i');
        icon.className = 'bi bi-arrow-clockwise spinner-border spinner-border-sm';
        loadHistory();
        setTimeout(function() { icon.className = 'bi bi-arrow-clockwise'; }, 800);
    });

    refreshDiagnosticsBtn.addEventListener('click', function() {
        var icon = this.querySelector('i');
        icon.className = 'bi bi-arrow-clockwise spinner-border spinner-border-sm';
        loadDiagnostics();
        setTimeout(function() { icon.className = 'bi bi-arrow-clockwise'; }, 800);
    });

    // 페이지 로드 시 이력/진단 자동 로드
    loadHistory();
    loadDiagnostics();

})();
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
