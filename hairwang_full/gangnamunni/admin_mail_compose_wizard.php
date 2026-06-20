<?php
/**
 * 메일 작성 마법사 - 4단계 (수신자 → 템플릿 → 내용 작성 → 미리보기+발송)
 */
session_start();
if (!isset($_SESSION['ad_admin_logged_in']) || $_SESSION['ad_admin_logged_in'] !== true) {
    header('Location: dashboard.php'); exit;
}
require_once __DIR__ . '/includes/admin_auth.php';
require_permission('mail');

require_once __DIR__ . '/../dbconfig.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
mysqli_set_charset($connect, 'utf8mb4');
require_once __DIR__ . '/theme_helper.php';
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }
loadThemeSettings($connect);

$esc = function($v) use ($connect) { return mysqli_real_escape_string($connect, $v); };

// 회원 목록 (수신자 검색용)
$members = [];
$r = mysqli_query($connect, "SELECT mb_id, mb_name, mb_nick, mb_email FROM g5_member WHERE mb_email != '' ORDER BY mb_name LIMIT 500");
if ($r) while ($row = mysqli_fetch_assoc($r)) $members[] = $row;

// 회원 그룹 (등급별)
$grades = [];
$r = mysqli_query($connect, "SELECT DISTINCT mb_level FROM g5_member WHERE mb_email != '' ORDER BY mb_level");
if ($r) while ($row = mysqli_fetch_assoc($r)) $grades[] = $row['mb_level'];

// 템플릿 로드
$templates = [];
$tplFile = __DIR__ . '/data/mail_templates.json';
if (file_exists($tplFile)) {
    $templates = json_decode(file_get_contents($tplFile), true) ?: [];
}

// SMTP 발송
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_mail') {
    header('Content-Type: application/json');
    try {
        require_once __DIR__ . '/includes/mail_helper.php';
        $recipients = json_decode($_POST['recipients'] ?? '[]', true);
        $subject = trim($_POST['subject'] ?? '');
        $body = $_POST['body'] ?? '';

        if (empty($recipients)) throw new Exception('수신자를 선택해주세요.');
        if (!$subject) throw new Exception('제목을 입력해주세요.');
        if (!$body) throw new Exception('내용을 입력해주세요.');

        $sent = 0;
        $failed = 0;
        foreach ($recipients as $rcpt) {
            $email = $rcpt['email'] ?? '';
            $name = $rcpt['name'] ?? '';
            if (!$email) continue;
            $personalBody = str_replace(['{{name}}','{{email}}'], [$name, $email], $body);
            $result = send_smtp_mail($email, $subject, $personalBody);
            if ($result) $sent++; else $failed++;
        }

        echo json_encode(['success' => true, 'sent' => $sent, 'failed' => $failed, 'message' => "{$sent}명에게 발송 완료" . ($failed ? " ({$failed}명 실패)" : '')]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// 수신자 그룹 조회
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'get_group_members') {
    header('Content-Type: application/json');
    $level = intval($_POST['level'] ?? 0);
    $result = [];
    $r = mysqli_query($connect, "SELECT mb_id, mb_name, mb_nick, mb_email FROM g5_member WHERE mb_email != '' AND mb_level = {$level}");
    if ($r) while ($row = mysqli_fetch_assoc($r)) $result[] = $row;
    echo json_encode(['success' => true, 'members' => $result, 'count' => count($result)]);
    exit;
}

$admin_page_title = '메일 작성 마법사';
$admin_current = 'mail';
$admin_extra_css = '<link href="includes/wizard_base.css" rel="stylesheet">';
require_once __DIR__ . '/includes/admin_header.php';
?>

<style>
.recipient-list { max-height: 300px; overflow-y: auto; border: 1px solid var(--bs-border-color); border-radius: 10px; padding: 8px; }
.recipient-item { display: flex; align-items: center; justify-content: space-between; padding: 6px 10px; border-radius: 6px; font-size: 13px; }
.recipient-item:hover { background: var(--bs-tertiary-bg); }
.recipient-item .recipient-remove { cursor: pointer; color: var(--bs-danger); opacity: .6; }
.recipient-item .recipient-remove:hover { opacity: 1; }
.recipient-badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; background: rgba(255,107,53,.1); color: #ff6b35; border-radius: 20px; font-size: 12px; font-weight: 600; margin: 2px; }
.recipient-badge .remove { cursor: pointer; margin-left: 4px; }
.tpl-card { height: 100%; }
.tpl-card .tpl-preview { font-size: 11px; color: var(--bs-secondary-color); max-height: 60px; overflow: hidden; margin-top: 6px; line-height: 1.4; }
.mail-editor { min-height: 300px; border: 1px solid var(--bs-border-color); border-radius: 10px; padding: 16px; font-size: 14px; }
.mail-preview-frame { border: 1px solid var(--bs-border-color); border-radius: 10px; overflow: hidden; background: var(--bs-body-bg); }
.mail-preview-header { background: var(--bs-tertiary-bg); padding: 12px 16px; border-bottom: 1px solid var(--bs-border-color); }
.mail-preview-body { padding: 20px; min-height: 200px; }
.send-result { text-align: center; padding: 30px; }
.send-result .result-icon { font-size: 64px; }
</style>

<div class="wz-steps">
    <div class="wz-step active" data-step="1"><div class="wz-circle">1</div><span class="wz-label">수신자</span></div>
    <div class="wz-line"></div>
    <div class="wz-step" data-step="2"><div class="wz-circle">2</div><span class="wz-label">템플릿</span></div>
    <div class="wz-line"></div>
    <div class="wz-step" data-step="3"><div class="wz-circle">3</div><span class="wz-label">작성</span></div>
    <div class="wz-line"></div>
    <div class="wz-step" data-step="4"><div class="wz-circle">4</div><span class="wz-label">발송</span></div>
</div>

<div class="wz-body">

    <!-- Step 1: 수신자 선택 -->
    <div class="wz-panel active" id="wzPanel1">
        <h5 class="mb-3"><i class="bi bi-people"></i> 수신자 선택</h5>

        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tabIndividual">개별 선택</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabGroup">그룹 선택</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabDirect">직접 입력</a></li>
        </ul>

        <div class="tab-content mb-3">
            <div class="tab-pane active" id="tabIndividual">
                <div class="wz-search-wrap mb-2">
                    <input type="text" class="form-control" id="memberSearch" placeholder="이름 또는 이메일로 검색..." autocomplete="off">
                    <div class="wz-dropdown" id="memberDropdown">
                        <?php foreach ($members as $m): ?>
                        <div class="wz-dropdown-item" data-value="<?= htmlspecialchars($m['mb_id']) ?>" data-name="<?= htmlspecialchars($m['mb_name'] ?: $m['mb_nick']) ?>" data-email="<?= htmlspecialchars($m['mb_email']) ?>">
                            <i class="bi bi-person"></i>
                            <div style="flex:1">
                                <span class="fw-bold"><?= htmlspecialchars($m['mb_name'] ?: $m['mb_nick']) ?></span>
                                <span class="text-muted" style="font-size:11px"><?= htmlspecialchars($m['mb_email']) ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="tab-pane" id="tabGroup">
                <div class="row g-2">
                    <?php foreach ($grades as $g): ?>
                    <div class="col-4 col-md-3">
                        <div class="wz-card" style="padding:12px;text-align:center" data-value="<?= $g ?>" onclick="addGroup(<?= $g ?>)">
                            <div style="font-size:20px"><i class="bi bi-people-fill"></i></div>
                            <div class="small fw-bold">레벨 <?= $g ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div class="col-4 col-md-3">
                        <div class="wz-card" style="padding:12px;text-align:center;border-color:#ff6b35" onclick="addAllMembers()">
                            <div style="font-size:20px;color:#ff6b35"><i class="bi bi-globe"></i></div>
                            <div class="small fw-bold" style="color:#ff6b35">전체 회원</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="tab-pane" id="tabDirect">
                <div class="input-group">
                    <input type="email" class="form-control" id="directEmail" placeholder="이메일 주소 입력">
                    <input type="text" class="form-control" id="directName" placeholder="이름 (선택)" style="max-width:120px">
                    <button class="btn btn-outline-secondary" onclick="addDirect()"><i class="bi bi-plus"></i></button>
                </div>
            </div>
        </div>

        <h6 class="small fw-bold text-muted mb-2">선택된 수신자 (<span id="rcptCount">0</span>명)</h6>
        <div id="rcptTags" class="mb-2" style="min-height:30px"></div>

        <div class="wz-actions">
            <a href="admin_mail.php" class="btn-wz-prev"><i class="bi bi-arrow-left"></i> 메일함</a>
            <button class="btn-wz-next" onclick="wz.next()">템플릿 <i class="bi bi-arrow-right"></i></button>
        </div>
    </div>

    <!-- Step 2: 템플릿 선택 -->
    <div class="wz-panel" id="wzPanel2">
        <h5 class="mb-3"><i class="bi bi-file-earmark-text"></i> 템플릿 선택 <span class="text-muted small">(선택사항)</span></h5>
        <div class="row g-2" id="tplGrid">
            <div class="col-6 col-md-4">
                <div class="wz-card tpl-card selected" data-value="">
                    <div class="wz-check"><i class="bi bi-check"></i></div>
                    <div class="wz-card-icon"><i class="bi bi-file-earmark"></i></div>
                    <div class="wz-card-name">빈 메일</div>
                    <div class="tpl-preview">직접 작성합니다</div>
                </div>
            </div>
            <?php foreach ($templates as $i => $tpl): ?>
            <div class="col-6 col-md-4">
                <div class="wz-card tpl-card" data-value="<?= $i ?>" data-subject="<?= htmlspecialchars($tpl['subject'] ?? '') ?>" data-body="<?= htmlspecialchars($tpl['body'] ?? '') ?>">
                    <div class="wz-check"><i class="bi bi-check"></i></div>
                    <div class="wz-card-icon"><i class="bi bi-file-earmark-richtext"></i></div>
                    <div class="wz-card-name"><?= htmlspecialchars($tpl['name'] ?? '템플릿 '.($i+1)) ?></div>
                    <div class="tpl-preview"><?= htmlspecialchars(mb_substr(strip_tags($tpl['body'] ?? ''), 0, 60)) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="wz-actions">
            <button class="btn-wz-prev" onclick="wz.prev()"><i class="bi bi-arrow-left"></i> 이전</button>
            <button class="btn-wz-next" onclick="wz.next()">내용 작성 <i class="bi bi-arrow-right"></i></button>
        </div>
    </div>

    <!-- Step 3: 내용 작성 -->
    <div class="wz-panel" id="wzPanel3">
        <h5 class="mb-3"><i class="bi bi-pencil"></i> 메일 내용</h5>
        <div class="mb-3">
            <label class="form-label small fw-bold">제목 <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="mailSubject" placeholder="메일 제목">
        </div>
        <div class="mb-3">
            <label class="form-label small fw-bold">본문 <span class="text-danger">*</span></label>
            <div class="mb-2">
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary" onclick="insertVar('name')">{{name}}</button>
                    <button class="btn btn-outline-secondary" onclick="insertVar('email')">{{email}}</button>
                </div>
                <span class="text-muted small ms-2">변수를 삽입하면 수신자별로 자동 치환됩니다</span>
            </div>
            <textarea class="form-control mail-editor" id="mailBody" rows="12" placeholder="메일 본문을 작성하세요..."></textarea>
        </div>
        <div class="wz-actions">
            <button class="btn-wz-prev" onclick="wz.prev()"><i class="bi bi-arrow-left"></i> 이전</button>
            <button class="btn-wz-next" onclick="wz.next()">미리보기 <i class="bi bi-arrow-right"></i></button>
        </div>
    </div>

    <!-- Step 4: 미리보기 + 발송 -->
    <div class="wz-panel" id="wzPanel4">
        <h5 class="mb-3"><i class="bi bi-send"></i> 미리보기 & 발송</h5>

        <div class="wz-summary mb-3">
            <h6><i class="bi bi-people"></i> 수신자</h6>
            <div id="previewRecipients" class="small"></div>
        </div>

        <div class="mail-preview-frame mb-3">
            <div class="mail-preview-header">
                <div class="small text-muted">제목</div>
                <div class="fw-bold" id="previewSubject"></div>
            </div>
            <div class="mail-preview-body" id="previewBody"></div>
        </div>

        <div class="wz-actions">
            <button class="btn-wz-prev" onclick="wz.prev()"><i class="bi bi-arrow-left"></i> 수정</button>
            <button class="btn-wz-submit" id="btnSendMail" onclick="sendMail()"><i class="bi bi-send"></i> 발송하기 (<span id="sendCount">0</span>명)</button>
        </div>

        <!-- 발송 결과 -->
        <div id="sendResult" style="display:none"></div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
<script src="includes/wizard_base.js"></script>
<script>
const wz = new WizardBase(4, { storageKey: 'mail_compose_wz' });
const recipients = [];
const allMembers = <?= json_encode($members, JSON_UNESCAPED_UNICODE) ?>;

// 수신자 검색
initSearchDropdown(
    document.getElementById('memberSearch'),
    document.getElementById('memberDropdown'),
    { onSelect: (item) => {
        addRecipient(item.dataset.value, item.dataset.name, item.dataset.email);
        document.getElementById('memberSearch').value = '';
    }}
);

function addRecipient(id, name, email) {
    if (recipients.find(r => r.email === email)) return;
    recipients.push({ id, name, email });
    renderRecipients();
}

function removeRecipient(idx) {
    recipients.splice(idx, 1);
    renderRecipients();
}

function renderRecipients() {
    document.getElementById('rcptCount').textContent = recipients.length;
    document.getElementById('rcptTags').innerHTML = recipients.map((r, i) =>
        `<span class="recipient-badge">${wzEsc(r.name||r.email)} <span class="remove" onclick="removeRecipient(${i})">&#x2715;</span></span>`
    ).join('');
}

async function addGroup(level) {
    const fd = new FormData();
    fd.append('action', 'get_group_members');
    fd.append('level', level);
    const res = await fetch('', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
        data.members.forEach(m => addRecipient(m.mb_id, m.mb_name || m.mb_nick, m.mb_email));
    }
}

function addAllMembers() {
    allMembers.forEach(m => addRecipient(m.mb_id, m.mb_name || m.mb_nick, m.mb_email));
}

function addDirect() {
    const email = document.getElementById('directEmail').value.trim();
    const name = document.getElementById('directName').value.trim();
    if (!email || !email.includes('@')) { alert('유효한 이메일을 입력하세요.'); return; }
    addRecipient('', name || email, email);
    document.getElementById('directEmail').value = '';
    document.getElementById('directName').value = '';
}

// 템플릿 선택
const tplSelect = initCardSelect(document.getElementById('tplGrid'), {
    onChange: (vals) => {
        const v = vals[0];
        if (v === '' || v === undefined) return;
        const card = document.querySelector(`#tplGrid .wz-card[data-value="${v}"]`);
        if (card) {
            const subj = card.dataset.subject || '';
            const body = card.dataset.body || '';
            if (subj) document.getElementById('mailSubject').value = subj;
            if (body) document.getElementById('mailBody').value = body;
        }
    }
});

function insertVar(name) {
    const ta = document.getElementById('mailBody');
    const start = ta.selectionStart;
    const end = ta.selectionEnd;
    const text = ta.value;
    const insert = '{{' + name + '}}';
    ta.value = text.substring(0, start) + insert + text.substring(end);
    ta.selectionStart = ta.selectionEnd = start + insert.length;
    ta.focus();
}

// 유효성
wz.setValidator(1, () => {
    if (recipients.length === 0) { alert('수신자를 1명 이상 선택해주세요.'); return false; }
    return true;
});
wz.setValidator(3, () => {
    if (!document.getElementById('mailSubject').value.trim()) { alert('제목을 입력해주세요.'); return false; }
    if (!document.getElementById('mailBody').value.trim()) { alert('본문을 입력해주세요.'); return false; }
    return true;
});

// 미리보기
wz.onStepChange = (step) => { if (step === 4) renderMailPreview(); };

function renderMailPreview() {
    const first = recipients[0] || {};
    document.getElementById('previewRecipients').innerHTML = recipients.slice(0, 5).map(r => wzEsc(r.name||r.email)).join(', ') + (recipients.length > 5 ? ` 외 ${recipients.length-5}명` : '') + ` <strong>(총 ${recipients.length}명)</strong>`;
    document.getElementById('previewSubject').textContent = document.getElementById('mailSubject').value;
    const body = document.getElementById('mailBody').value
        .replace(/\{\{name\}\}/g, wzEsc(first.name || '홍길동'))
        .replace(/\{\{email\}\}/g, wzEsc(first.email || 'user@example.com'));
    document.getElementById('previewBody').innerHTML = '<pre style="white-space:pre-wrap;font-family:inherit;margin:0">' + body + '</pre>';
    document.getElementById('sendCount').textContent = recipients.length;
}

// 발송
async function sendMail() {
    if (!confirm(recipients.length + '명에게 메일을 발송합니다. 계속하시겠습니까?')) return;
    const btn = document.getElementById('btnSendMail');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 발송 중...';

    const fd = new FormData();
    fd.append('action', 'send_mail');
    fd.append('recipients', JSON.stringify(recipients));
    fd.append('subject', document.getElementById('mailSubject').value.trim());
    fd.append('body', document.getElementById('mailBody').value.trim());

    try {
        const res = await fetch('', { method: 'POST', body: fd });
        const data = await res.json();
        const resultDiv = document.getElementById('sendResult');
        if (data.success) {
            wz.clearStorage();
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = `<div class="send-result">
                <div class="result-icon" style="color:#28a745">&#9989;</div>
                <h5 class="mt-2">${wzEsc(data.message)}</h5>
                <p class="text-muted">발송 ${data.sent}건 / 실패 ${data.failed}건</p>
                <a href="admin_mail.php" class="btn btn-outline-primary mt-2">메일함으로 돌아가기</a>
            </div>`;
            btn.style.display = 'none';
        } else {
            alert(data.error || '발송 실패');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send"></i> 발송하기';
        }
    } catch (e) {
        alert('네트워크 오류: ' + e.message);
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send"></i> 발송하기';
    }
}
</script>
