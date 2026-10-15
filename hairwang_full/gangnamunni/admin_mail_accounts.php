<?php
/**
 * 메일 계정 설정 페이지
 * 발신자 이름, 라벨, 색상 변경
 *
 * @created 2026-02-12
 */
session_start();
$admin_page_title = '계정 설정';
$admin_current = 'mail_accounts';

// DB 상수를 관리자 검사보다 먼저 올린다 — check_admin_login() 이 cred_epoch
// (비밀번호 변경 시 세션 폐기)를 조회할 수 있어야 한다. 아래쪽의
// require_once 는 그대로 두되 두 번 로드되지 않는다(require_once).
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_admin_login();
require_permission('mail_accounts');
require_once __DIR__ . '/includes/mail_helper.php';

$data_file = __DIR__ . '/data/mail_accounts.json';

// === AJAX 처리 ===
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['ajax'];

    // 계정 정보 조회
    if ($action === 'get_accounts') {
        $accounts = [];
        $overrides = [];
        if (file_exists($data_file)) {
            $json = @file_get_contents($data_file);
            if ($json) $overrides = @json_decode($json, true) ?: [];
        }
        foreach (MAIL_ACCOUNTS as $i => $acc) {
            $accounts[] = [
                'index' => $i,
                'email' => $acc['email'],
                'name' => $acc['name'],
                'label' => $acc['label'],
                'color' => $acc['color'],
                'is_custom' => isset($overrides[(string)$i]),
            ];
        }
        echo json_encode(['accounts' => $accounts]);
        exit;
    }

    // 계정 정보 저장
    if ($action === 'save_account') {
        $index = intval($_POST['index'] ?? -1);
        $name = trim($_POST['name'] ?? '');
        $label = trim($_POST['label'] ?? '');
        $color = trim($_POST['color'] ?? '');

        if ($index < 0 || $index >= count(MAIL_ACCOUNTS)) {
            echo json_encode(['error' => '잘못된 계정 인덱스']);
            exit;
        }
        if (!$name) {
            echo json_encode(['error' => '발신자 이름을 입력하세요.']);
            exit;
        }

        $overrides = [];
        if (file_exists($data_file)) {
            $json = @file_get_contents($data_file);
            if ($json) $overrides = @json_decode($json, true) ?: [];
        }

        $overrides[(string)$index] = [
            'name' => $name,
            'label' => $label,
            'color' => $color,
        ];

        $dir = dirname($data_file);
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $ok = @file_put_contents($data_file, json_encode($overrides, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        if ($ok !== false) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => '저장 실패 (data/ 권한 확인)']);
        }
        exit;
    }

    // 기본값으로 리셋
    if ($action === 'reset_account') {
        $index = intval($_POST['index'] ?? -1);
        $overrides = [];
        if (file_exists($data_file)) {
            $json = @file_get_contents($data_file);
            if ($json) $overrides = @json_decode($json, true) ?: [];
        }
        unset($overrides[(string)$index]);
        @file_put_contents($data_file, json_encode($overrides, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
        exit;
    }

    // 테스트 발송
    if ($action === 'test_send') {
        $index = intval($_POST['index'] ?? 0);
        $acc = MAIL_ACCOUNTS[$index] ?? null;
        if (!$acc) {
            echo json_encode(['error' => '계정 없음']);
            exit;
        }
        $result = mail_send($index, $acc['email'], '발신자 이름 테스트', '<p>발신자 이름: <b>' . htmlspecialchars($acc['name']) . '</b></p><p>이 메일은 테스트입니다.</p>');
        echo json_encode($result);
        exit;
    }

    echo json_encode(['error' => 'Unknown action']);
    exit;
}

// === HTML ===
$admin_extra_css = <<<'CSSEOF'
.acc-card { border: 1px solid var(--bs-border-color); border-radius: 10px; padding: 20px; margin-bottom: 16px; background: var(--bs-body-bg); }
.acc-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
.acc-email { font-size: 14px; color: var(--bs-secondary-color); margin-bottom: 12px; }
.acc-badge { display: inline-block; width: 14px; height: 14px; border-radius: 50%; margin-right: 8px; vertical-align: middle; }
.from-preview { font-size: 13px; background: var(--bs-tertiary-bg); border-radius: 6px; padding: 8px 12px; margin-top: 8px; }
.from-preview b { color: var(--bs-body-color); }
CSSEOF;

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
if (!isset($connect) || !$connect) {
    $connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    if ($connect) mysqli_set_charset($connect, 'utf8mb4');
}
if (isset($connect) && $connect) loadThemeSettings($connect);
include __DIR__ . '/includes/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-person-gear me-2"></i>메일 계정 설정</h5>
    <a href="admin_mail.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>메일함</a>
</div>

<div class="alert alert-info small mb-3">
    <i class="bi bi-info-circle me-1"></i>
    메일 발송 시 "보내는 사람"에 표시되는 이름과 라벨을 변경할 수 있습니다.
    변경 후 메일을 보내면 수신자에게 새 이름으로 표시됩니다.
</div>

<div id="accountsArea">
    <div class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm"></div> 불러오는 중...</div>
</div>

<?php
$admin_extra_js = <<<'JSEOF'
document.addEventListener('DOMContentLoaded', loadAccounts);

function loadAccounts() {
    fetch('admin_mail_accounts.php?ajax=get_accounts')
        .then(r => r.json())
        .then(data => {
            var accounts = data.accounts || [];
            var html = '';
            accounts.forEach(function(acc) {
                html += '<div class="acc-card" id="accCard' + acc.index + '">';
                html += '<div class="d-flex justify-content-between align-items-start">';
                html += '<div>';
                html += '<h6 class="mb-1"><span class="acc-badge" style="background:' + acc.color + ';"></span>' + escHtml(acc.email) + '</h6>';
                if (acc.is_custom) {
                    html += '<span class="badge bg-primary-subtle text-primary small">커스텀</span> ';
                }
                html += '</div>';
                html += '<div class="d-flex gap-1">';
                html += '<button class="btn btn-sm btn-outline-primary" onclick="testSend(' + acc.index + ')" title="테스트 메일 발송"><i class="bi bi-send me-1"></i>테스트</button>';
                if (acc.is_custom) {
                    html += '<button class="btn btn-sm btn-outline-warning" onclick="resetAccount(' + acc.index + ')" title="기본값 복원"><i class="bi bi-arrow-counterclockwise"></i></button>';
                }
                html += '</div></div>';

                html += '<div class="row g-2 mt-2">';

                // 발신자 이름
                html += '<div class="col-md-5">';
                html += '<label class="form-label small fw-semibold">발신자 이름 <span class="text-danger">*</span></label>';
                html += '<input type="text" class="form-control form-control-sm" id="accName' + acc.index + '" value="' + escHtml(acc.name) + '" placeholder="예: 서울미녀 관리자">';
                html += '<div class="small text-muted mt-1">수신자에게 보이는 이름입니다.</div>';
                html += '</div>';

                // 라벨
                html += '<div class="col-md-3">';
                html += '<label class="form-label small fw-semibold">라벨</label>';
                html += '<input type="text" class="form-control form-control-sm" id="accLabel' + acc.index + '" value="' + escHtml(acc.label) + '" placeholder="예: 관리자">';
                html += '</div>';

                // 색상
                html += '<div class="col-md-2">';
                html += '<label class="form-label small fw-semibold">색상</label>';
                html += '<input type="color" class="form-control form-control-sm form-control-color" id="accColor' + acc.index + '" value="' + acc.color + '" style="height:31px;">';
                html += '</div>';

                // 저장 버튼
                html += '<div class="col-md-2 d-flex align-items-end">';
                html += '<button class="btn btn-primary btn-sm w-100" onclick="saveAccount(' + acc.index + ')"><i class="bi bi-check-lg me-1"></i>저장</button>';
                html += '</div>';

                html += '</div>';

                // 미리보기
                html += '<div class="from-preview" id="accPreview' + acc.index + '">';
                html += '<b>From:</b> ' + escHtml(acc.name) + ' &lt;' + escHtml(acc.email) + '&gt;';
                html += '</div>';

                html += '</div>';
            });
            document.getElementById('accountsArea').innerHTML = html;

            // 이름 변경 시 실시간 미리보기
            accounts.forEach(function(acc) {
                var nameInput = document.getElementById('accName' + acc.index);
                if (nameInput) {
                    nameInput.addEventListener('input', function() {
                        var preview = document.getElementById('accPreview' + acc.index);
                        preview.innerHTML = '<b>From:</b> ' + escHtml(this.value || '(이름 없음)') + ' &lt;' + escHtml(acc.email) + '&gt;';
                    });
                }
            });
        });
}

function saveAccount(index) {
    var name = document.getElementById('accName' + index).value.trim();
    var label = document.getElementById('accLabel' + index).value.trim();
    var color = document.getElementById('accColor' + index).value;

    if (!name) { alert('발신자 이름을 입력하세요.'); return; }

    fetch('admin_mail_accounts.php?ajax=save_account', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'index=' + index + '&name=' + encodeURIComponent(name) + '&label=' + encodeURIComponent(label) + '&color=' + encodeURIComponent(color)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('저장되었습니다. 다음 발송부터 적용됩니다.');
            loadAccounts();
        } else {
            alert('저장 실패: ' + (data.error || ''));
        }
    });
}

function resetAccount(index) {
    if (!confirm('이 계정의 설정을 기본값으로 복원하시겠습니까?')) return;
    fetch('admin_mail_accounts.php?ajax=reset_account', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'index=' + index
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('기본값으로 복원되었습니다.');
            loadAccounts();
        }
    });
}

function testSend(index) {
    if (!confirm('이 계정으로 자기 자신에게 테스트 메일을 보냅니다. 진행?')) return;
    fetch('admin_mail_accounts.php?ajax=test_send', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'index=' + index
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('테스트 메일 발송 완료! 받은편지함을 확인하세요.');
        } else {
            alert('발송 실패: ' + (data.error || ''));
        }
    });
}

function escHtml(s) {
    if (!s) return '';
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}
function showToast(msg) {
    var toast = document.createElement('div');
    toast.className = 'position-fixed bottom-0 start-50 translate-middle-x mb-3 px-4 py-2 bg-dark text-white rounded-pill shadow';
    toast.style.zIndex = '9999';
    toast.style.fontSize = '13px';
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(function() { toast.remove(); }, 3000);
}
JSEOF;

include __DIR__ . '/includes/admin_footer.php';
?>
