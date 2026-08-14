<?php
require_once __DIR__ . '/includes/temporarily_disabled_pages.php';
enforce_temporarily_disabled_admin_page(__FILE__);

/**
 * admin_progress.php - 프로젝트 진행사항 (클라이언트 진행 보고)
 *
 * 타임라인 기반 진행사항 표시 + 클라이언트 메시지 기능
 * Bootstrap 5.3 + 다크모드 호환
 *
 * @since 2026-04-02
 */

session_start();

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("MySQL 연결 실패: " . mysqli_connect_error());
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

require_admin_login();

// 테이블 자동 생성
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS `gangnamunni_progress_messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `message` TEXT NOT NULL,
    `author` VARCHAR(100) DEFAULT 'client',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// AJAX: 메시지 저장
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json; charset=utf-8');

    if ($_POST['ajax_action'] === 'save_message') {
        $message = trim($_POST['message'] ?? '');
        $author = $_SESSION['ad_admin_username'] ?? 'client';

        if ($message === '') {
            echo json_encode(['success' => false, 'error' => '메시지를 입력해주세요.']);
            exit;
        }

        $stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_progress_messages (message, author) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, 'ss', $message, $author);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($ok) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'DB 저장 실패']);
        }
        exit;
    }

    if ($_POST['ajax_action'] === 'load_messages') {
        $rows = [];
        $res = mysqli_query($connect, "SELECT id, message, author, created_at FROM gangnamunni_progress_messages ORDER BY created_at DESC LIMIT 50");
        while ($r = mysqli_fetch_assoc($res)) {
            $rows[] = $r;
        }
        echo json_encode(['success' => true, 'messages' => $rows]);
        exit;
    }

    if ($_POST['ajax_action'] === 'delete_message') {
        $id = intval($_POST['id'] ?? 0);
        $role = $_SESSION['ad_admin_role'] ?? '';
        if ($id > 0 && in_array($role, ['super_admin', 'admin'])) {
            $stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_progress_messages WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => '권한 없음']);
        }
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

// 페이지 변수
$admin_page_title = '진행사항';
$admin_current = 'admin_progress.php';

// 기존 메시지 로드
$messages = [];
$res = mysqli_query($connect, "SELECT id, message, author, created_at FROM gangnamunni_progress_messages ORDER BY created_at DESC LIMIT 50");
if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $messages[] = $r;
    }
}

$is_admin = in_array($_SESSION['ad_admin_role'] ?? '', ['super_admin', 'admin']);

require_once __DIR__ . '/includes/admin_header.php';
?>

<style>
/* ── Hero ── */
.progress-hero {
    background: linear-gradient(135deg, #7B1FA2 0%, #E040FB 100%);
    border-radius: 1rem;
    padding: 2.5rem 2rem;
    color: #fff;
    position: relative;
    overflow: hidden;
    margin-bottom: 2rem;
}
.progress-hero::before {
    content: '';
    position: absolute;
    top: -40%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: rgba(255,255,255,.08);
    border-radius: 50%;
}
.progress-hero::after {
    content: '';
    position: absolute;
    bottom: -30%;
    left: -5%;
    width: 200px;
    height: 200px;
    background: rgba(255,255,255,.06);
    border-radius: 50%;
}
.progress-hero h1 { font-weight: 800; font-size: 2rem; position: relative; z-index: 1; }
.progress-hero p  { opacity: .9; position: relative; z-index: 1; margin-bottom: 0; }

/* ── Stat cards ── */
.stat-card {
    border: none;
    border-radius: .85rem;
    transition: transform .2s, box-shadow .2s;
}
.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(123,31,162,.15);
}
.stat-icon {
    width: 52px; height: 52px;
    border-radius: .75rem;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem;
}
.stat-icon.purple { background: rgba(123,31,162,.12); color: #7B1FA2; }
.stat-icon.green  { background: rgba(25,135,84,.12);  color: #198754; }
.stat-icon.blue   { background: rgba(13,110,253,.12);  color: #0d6efd; }
.stat-icon.amber  { background: rgba(255,193,7,.15);   color: #cc9a00; }

[data-bs-theme="dark"] .stat-icon.purple { background: rgba(224,64,251,.18); color: #E040FB; }
[data-bs-theme="dark"] .stat-icon.green  { background: rgba(25,135,84,.2);   color: #5dd39e; }
[data-bs-theme="dark"] .stat-icon.blue   { background: rgba(13,110,253,.2);  color: #6ea8fe; }
[data-bs-theme="dark"] .stat-icon.amber  { background: rgba(255,193,7,.2);   color: #ffc107; }

/* ── Timeline ── */
.timeline { position: relative; padding-left: 2.5rem; }
.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 3px;
    background: linear-gradient(180deg, #7B1FA2, #E040FB, rgba(224,64,251,.2));
    border-radius: 3px;
}
.timeline-item {
    position: relative;
    margin-bottom: 1.5rem;
    animation: fadeSlideUp .5s ease both;
}
.timeline-item:nth-child(1) { animation-delay: .1s; }
.timeline-item:nth-child(2) { animation-delay: .25s; }
.timeline-item:nth-child(3) { animation-delay: .4s; }

.timeline-dot {
    position: absolute;
    left: -2.5rem;
    top: 1.2rem;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 3px solid #7B1FA2;
    background: #fff;
    z-index: 2;
}
[data-bs-theme="dark"] .timeline-dot { background: #212529; border-color: #E040FB; }

.timeline-dot.active {
    background: #7B1FA2;
    box-shadow: 0 0 0 4px rgba(123,31,162,.25);
}
[data-bs-theme="dark"] .timeline-dot.active {
    background: #E040FB;
    box-shadow: 0 0 0 4px rgba(224,64,251,.25);
}

.timeline-card {
    border: none;
    border-radius: .85rem;
    border-left: 4px solid #7B1FA2;
    transition: transform .2s, box-shadow .2s;
}
.timeline-card:hover {
    transform: translateX(4px);
    box-shadow: 0 6px 20px rgba(123,31,162,.1);
}
[data-bs-theme="dark"] .timeline-card { border-left-color: #E040FB; }

.timeline-date {
    display: inline-block;
    background: linear-gradient(135deg, #7B1FA2, #E040FB);
    color: #fff;
    padding: .2rem .7rem;
    border-radius: .4rem;
    font-size: .8rem;
    font-weight: 600;
}

/* ── Message section ── */
.msg-bubble {
    border-radius: 1rem;
    padding: 1rem 1.25rem;
    margin-bottom: .75rem;
    position: relative;
    animation: fadeSlideUp .3s ease both;
}
.msg-bubble.client {
    background: rgba(123,31,162,.08);
    border: 1px solid rgba(123,31,162,.15);
    margin-left: 0;
    margin-right: 3rem;
}
.msg-bubble.admin-msg {
    background: rgba(13,110,253,.08);
    border: 1px solid rgba(13,110,253,.15);
    margin-left: 3rem;
    margin-right: 0;
}
[data-bs-theme="dark"] .msg-bubble.client {
    background: rgba(224,64,251,.1);
    border-color: rgba(224,64,251,.2);
}
[data-bs-theme="dark"] .msg-bubble.admin-msg {
    background: rgba(13,110,253,.12);
    border-color: rgba(13,110,253,.25);
}
.msg-author {
    font-weight: 600;
    font-size: .82rem;
}
.msg-time {
    font-size: .75rem;
    opacity: .6;
}
.msg-delete {
    position: absolute;
    top: .5rem;
    right: .75rem;
    opacity: 0;
    transition: opacity .2s;
    cursor: pointer;
    background: none;
    border: none;
    color: var(--bs-danger);
    font-size: .85rem;
}
.msg-bubble:hover .msg-delete { opacity: 1; }

/* ── Animations ── */
@keyframes fadeSlideUp {
    from { opacity: 0; transform: translateY(16px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ── Textarea focus ── */
.msg-textarea:focus {
    border-color: #7B1FA2;
    box-shadow: 0 0 0 .2rem rgba(123,31,162,.2);
}
[data-bs-theme="dark"] .msg-textarea:focus {
    border-color: #E040FB;
    box-shadow: 0 0 0 .2rem rgba(224,64,251,.2);
}

/* ── Responsive ── */
@media (max-width: 767.98px) {
    .progress-hero { padding: 1.5rem 1.25rem; }
    .progress-hero h1 { font-size: 1.5rem; }
    .msg-bubble.client  { margin-right: 1rem; }
    .msg-bubble.admin-msg { margin-left: 1rem; }
}
</style>

<!-- Hero -->
<div class="progress-hero">
    <h1><i class="bi bi-rocket-takeoff me-2"></i>프로젝트 진행사항</h1>
    <p class="fs-6 mt-2">
        <i class="bi bi-calendar3 me-1"></i><?= date('Y년 m월 d일 (D)') ?>
        <span class="ms-3"><i class="bi bi-building me-1"></i>서울미녀 (Seoul Beauty)</span>
    </p>
</div>

<!-- Summary Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon green"><i class="bi bi-apple"></i></div>
                <div>
                    <div class="fw-bold">iOS 심사</div>
                    <span class="text-success fw-semibold">제출 완료</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon green"><i class="bi bi-bug"></i></div>
                <div>
                    <div class="fw-bold">사이드바 수정</div>
                    <span class="text-success fw-semibold">완료</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon blue"><i class="bi bi-hospital"></i></div>
                <div>
                    <div class="fw-bold">병원 등록</div>
                    <span class="text-primary fw-semibold">0 / 30</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon amber"><i class="bi bi-book"></i></div>
                <div>
                    <div class="fw-bold">운영 매뉴얼</div>
                    <span class="text-warning fw-semibold">대기</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Timeline -->
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm" style="border-radius:.85rem">
            <div class="card-header bg-transparent border-0 pt-3 pb-0 px-4">
                <h5 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-purple"></i>진행 타임라인</h5>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="timeline">

                    <!-- 04/03 -->
                    <div class="timeline-item">
                        <div class="timeline-dot active"></div>
                        <div class="card timeline-card shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="timeline-date">04 / 03</span>
                                    <span class="badge bg-success-subtle text-success">완료</span>
                                </div>
                                <h6 class="fw-bold mb-2">iOS 앱 심사 제출 완료 / 관리자 대시보드 전면 개선</h6>
                                <ul class="mb-0 ps-3 small text-body-secondary">
                                    <li>Apple App Store Connect 심사 제출</li>
                                    <li>사이드바 확장/축소, 라벨 일치, active 수정</li>
                                    <li>관리자 대시보드 UI/UX 전면 개선</li>
                                    <li>다크모드 18개 파일 CSS 변수 교체</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- 04/03~04/05 -->
                    <div class="timeline-item">
                        <div class="timeline-dot active"></div>
                        <div class="card timeline-card shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="timeline-date">04 / 03 ~ 04 / 05</span>
                                    <span class="badge bg-primary-subtle text-primary">진행 중</span>
                                </div>
                                <h6 class="fw-bold mb-2">병원 10개씩 등록 운영 + 전체 기능 점검</h6>
                                <ul class="mb-0 ps-3 small text-body-secondary">
                                    <li>실제 병원 데이터 30개 등록 (10개/일)</li>
                                    <li>전체 기능 E2E 테스트 및 점검</li>
                                    <li>병원 상세 페이지 + 후기 + 예약 흐름 확인</li>
                                    <li>모바일 앱 연동 테스트</li>
                                </ul>
                                <div class="mt-2">
                                    <div class="progress" style="height:6px; border-radius:3px">
                                        <div class="progress-bar" role="progressbar"
                                             style="width:0%; background:linear-gradient(90deg,#7B1FA2,#E040FB)"
                                             aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <small class="text-body-secondary">0 / 30 병원 등록</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 04/06 -->
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="card timeline-card shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="timeline-date">04 / 06</span>
                                    <span class="badge bg-warning-subtle text-warning">예정</span>
                                </div>
                                <h6 class="fw-bold mb-2">운영 매뉴얼 작성 전달</h6>
                                <ul class="mb-0 ps-3 small text-body-secondary">
                                    <li>관리자 운영 매뉴얼 최종 작성</li>
                                    <li>병원 파트너 가이드 문서</li>
                                    <li>서비스 운영 절차 안내서 전달</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                </div><!-- /.timeline -->
            </div>
        </div>
    </div>

    <!-- Messages -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm" style="border-radius:.85rem">
            <div class="card-header bg-transparent border-0 pt-3 pb-0 px-4">
                <h5 class="fw-bold mb-0"><i class="bi bi-chat-dots me-2 text-purple"></i>메시지</h5>
            </div>
            <div class="card-body px-4 pb-4">
                <!-- Input -->
                <div class="mb-3">
                    <textarea id="msgInput" class="form-control msg-textarea" rows="3"
                              placeholder="메시지를 입력하세요..." maxlength="2000"></textarea>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <small class="text-body-secondary"><span id="charCount">0</span> / 2,000</small>
                        <button id="msgSendBtn" class="btn btn-sm px-3"
                                style="background:linear-gradient(135deg,#7B1FA2,#E040FB);color:#fff;border:none;border-radius:.5rem">
                            <i class="bi bi-send me-1"></i>보내기
                        </button>
                    </div>
                </div>

                <!-- Message list -->
                <div id="msgList" style="max-height:420px;overflow-y:auto">
                    <?php if (empty($messages)): ?>
                        <div class="text-center text-body-secondary py-4" id="msgEmpty">
                            <i class="bi bi-chat-square-text fs-1 d-block mb-2 opacity-50"></i>
                            아직 메시지가 없습니다.
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $m):
                            $isAdmin = in_array($m['author'], ['admin', 'super_admin']);
                            $bubbleCls = $isAdmin ? 'admin-msg' : 'client';
                        ?>
                        <div class="msg-bubble <?= $bubbleCls ?>" data-id="<?= (int)$m['id'] ?>">
                            <?php if ($is_admin): ?>
                                <button class="msg-delete" title="삭제" onclick="deleteMsg(<?= (int)$m['id'] ?>)">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            <?php endif; ?>
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="msg-author <?= $isAdmin ? 'text-primary' : '' ?>" style="<?= !$isAdmin ? 'color:#7B1FA2' : '' ?>">
                                    <i class="bi <?= $isAdmin ? 'bi-shield-check' : 'bi-person' ?> me-1"></i><?= htmlspecialchars($m['author']) ?>
                                </span>
                                <span class="msg-time"><?= date('m/d H:i', strtotime($m['created_at'])) ?></span>
                            </div>
                            <div class="small"><?= nl2br(htmlspecialchars($m['message'])) ?></div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const input    = document.getElementById('msgInput');
    const sendBtn  = document.getElementById('msgSendBtn');
    const charCnt  = document.getElementById('charCount');
    const msgList  = document.getElementById('msgList');
    const isAdmin  = <?= $is_admin ? 'true' : 'false' ?>;

    // 글자 수 카운터
    input.addEventListener('input', function() {
        charCnt.textContent = this.value.length;
    });

    // Ctrl+Enter 전송
    input.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'Enter') {
            e.preventDefault();
            sendBtn.click();
        }
    });

    // 메시지 전송
    sendBtn.addEventListener('click', async function() {
        const msg = input.value.trim();
        if (!msg) return;

        sendBtn.disabled = true;
        sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>전송 중...';

        try {
            const fd = new FormData();
            fd.append('ajax_action', 'save_message');
            fd.append('message', msg);

            const res = await fetch(location.href, { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                input.value = '';
                charCnt.textContent = '0';
                loadMessages();
            } else {
                alert(data.error || '저장 실패');
            }
        } catch (err) {
            alert('네트워크 오류');
        } finally {
            sendBtn.disabled = false;
            sendBtn.innerHTML = '<i class="bi bi-send me-1"></i>보내기';
        }
    });

    // 메시지 목록 로드
    async function loadMessages() {
        try {
            const fd = new FormData();
            fd.append('ajax_action', 'load_messages');
            const res = await fetch(location.href, { method: 'POST', body: fd });
            const data = await res.json();

            if (!data.success) return;

            if (data.messages.length === 0) {
                msgList.innerHTML = '<div class="text-center text-body-secondary py-4">' +
                    '<i class="bi bi-chat-square-text fs-1 d-block mb-2 opacity-50"></i>' +
                    '아직 메시지가 없습니다.</div>';
                return;
            }

            let html = '';
            data.messages.forEach(function(m) {
                const adm = ['admin','super_admin'].includes(m.author);
                const cls = adm ? 'admin-msg' : 'client';
                const icon = adm ? 'bi-shield-check' : 'bi-person';
                const color = adm ? 'class="msg-author text-primary"' : 'class="msg-author" style="color:#7B1FA2"';
                const dt = m.created_at.substring(5,16).replace('-','/').replace(' ',' ');
                const del = isAdmin
                    ? '<button class="msg-delete" title="삭제" onclick="deleteMsg(' + m.id + ')"><i class="bi bi-trash3"></i></button>'
                    : '';

                // XSS 방지
                const safe = m.message.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
                const safeAuthor = m.author.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

                html += '<div class="msg-bubble ' + cls + '" data-id="' + m.id + '">' +
                    del +
                    '<div class="d-flex justify-content-between align-items-center mb-1">' +
                    '<span ' + color + '><i class="bi ' + icon + ' me-1"></i>' + safeAuthor + '</span>' +
                    '<span class="msg-time">' + dt + '</span></div>' +
                    '<div class="small">' + safe + '</div></div>';
            });
            msgList.innerHTML = html;
        } catch (e) { /* silent */ }
    }

    // 메시지 삭제
    window.deleteMsg = async function(id) {
        if (!confirm('이 메시지를 삭제하시겠습니까?')) return;
        try {
            const fd = new FormData();
            fd.append('ajax_action', 'delete_message');
            fd.append('id', id);
            const res = await fetch(location.href, { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                const el = document.querySelector('.msg-bubble[data-id="' + id + '"]');
                if (el) el.remove();
                if (!msgList.querySelector('.msg-bubble')) {
                    msgList.innerHTML = '<div class="text-center text-body-secondary py-4">' +
                        '<i class="bi bi-chat-square-text fs-1 d-block mb-2 opacity-50"></i>' +
                        '아직 메시지가 없습니다.</div>';
                }
            } else {
                alert(data.error || '삭제 실패');
            }
        } catch (e) {
            alert('네트워크 오류');
        }
    };
})();
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
