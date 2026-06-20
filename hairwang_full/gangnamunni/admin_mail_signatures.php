<?php
/**
 * 메일 서명 관리 페이지
 * 계정별 서명 편집 + 이미지 업로드 + 실시간 미리보기
 *
 * @created 2026-02-11
 */
session_start();
$admin_page_title = '서명 관리';
$admin_current = 'mail_signatures';

require_once __DIR__ . '/includes/admin_auth.php';
require_admin_login();
require_permission('mail_signatures');
require_once __DIR__ . '/includes/mail_helper.php';

// === AJAX 처리 ===
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['ajax'];

    // 이미지 업로드
    if ($action === 'upload_image') {
        if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['error' => '파일 업로드 실패']);
            exit;
        }
        $file = $_FILES['image'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $allowed)) {
            echo json_encode(['error' => '허용되지 않는 파일 형식']);
            exit;
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['error' => '5MB 초과']);
            exit;
        }
        $dir = __DIR__ . '/data/mail_images/';
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) ?: 'png';
        $filename = 'sig_' . date('Ymd_His') . '_' . mt_rand(1000,9999) . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
            $url = 'data/mail_images/' . $filename;
            echo json_encode(['success' => true, 'url' => $url, 'filename' => $filename]);
        } else {
            echo json_encode(['error' => '파일 저장 실패']);
        }
        exit;
    }

    // 업로드된 이미지 목록
    if ($action === 'list_images') {
        $dir = __DIR__ . '/data/mail_images/';
        $images = [];
        if (is_dir($dir)) {
            $files = glob($dir . '*.{jpg,jpeg,png,gif,webp,svg}', GLOB_BRACE);
            if ($files) {
                usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
                foreach ($files as $f) {
                    $images[] = [
                        'filename' => basename($f),
                        'url' => 'data/mail_images/' . basename($f),
                        'size' => filesize($f),
                    ];
                }
            }
        }
        echo json_encode(['images' => $images]);
        exit;
    }

    // 서명 조회
    if ($action === 'get_signature') {
        $account = intval($_GET['account'] ?? 0);
        echo json_encode(['signature' => mail_get_signature($account)]);
        exit;
    }

    // 서명 저장
    if ($action === 'save_signature') {
        $account = intval($_POST['account'] ?? 0);
        $html = $_POST['signature'] ?? '';
        mail_save_signature($account, $html);
        echo json_encode(['success' => true]);
        exit;
    }

    // 계정 목록
    if ($action === 'get_accounts') {
        $accounts = [];
        foreach (MAIL_ACCOUNTS as $i => $acc) {
            $accounts[] = ['index' => $i, 'email' => $acc['email'], 'label' => $acc['label'], 'color' => $acc['color']];
        }
        echo json_encode(['accounts' => $accounts]);
        exit;
    }

    echo json_encode(['error' => 'Unknown action']);
    exit;
}

// === HTML ===
$admin_extra_css = <<<'CSSEOF'
.sig-editor { min-height: 200px; border: 1px solid var(--bs-border-color); border-radius: 6px; padding: 12px; font-size: 14px; line-height: 1.6; font-family: monospace; }
.sig-preview { min-height: 120px; border: 1px solid var(--bs-border-color); border-radius: 6px; padding: 16px; background: var(--bs-tertiary-bg); font-size: 14px; line-height: 1.6; }
.sig-preview img { max-width: 100%; height: auto; }
.account-tab { padding: 8px 16px; cursor: pointer; border-radius: 8px 8px 0 0; font-size: 13px; border: 1px solid transparent; border-bottom: none; }
.account-tab:hover { background: var(--bs-tertiary-bg); }
.account-tab.active { background: var(--bs-body-bg); border-color: var(--bs-border-color); font-weight: 600; position: relative; }
.account-tab.active::after { content: ''; position: absolute; bottom: -1px; left: 0; right: 0; height: 2px; background: var(--bs-body-bg); }
.account-badge { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 6px; }
.img-gallery { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
.img-gallery-item { position: relative; width: 80px; height: 80px; border: 1px solid var(--bs-border-color); border-radius: 6px; overflow: hidden; cursor: pointer; }
.img-gallery-item img { width: 100%; height: 100%; object-fit: cover; }
.img-gallery-item:hover { border-color: var(--bs-primary); }
.img-insert-btn { position: absolute; bottom: 2px; left: 2px; background: rgba(13,110,253,0.8); color: #fff; border: none; border-radius: 4px; padding: 1px 6px; font-size: 9px; cursor: pointer; display: none; }
.img-gallery-item:hover .img-insert-btn { display: block; }
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
    <h5 class="mb-0"><i class="bi bi-pen me-2"></i>메일 서명 관리</h5>
    <a href="admin_mail.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>메일함</a>
</div>

<!-- 계정 탭 -->
<div class="d-flex border-bottom mb-0" id="accountTabs"></div>

<div class="card border-top-0" style="border-radius: 0 0 8px 8px;">
    <div class="card-body">
        <div class="row g-3">
            <!-- 편집 -->
            <div class="col-lg-6">
                <label class="form-label small fw-semibold">서명 HTML 편집</label>
                <textarea class="form-control sig-editor" id="sigContent" rows="10" placeholder="서명 HTML을 입력하세요...&#10;&#10;예:&#10;<p style='color:#888;'>--<br>서울미녀 관리팀<br>admin@seoulbeauty.app</p>&#10;<img src='data/mail_images/logo.png' width='150'>"></textarea>
                <div class="d-flex gap-2 mt-2">
                    <button class="btn btn-primary btn-sm" onclick="saveSignature()"><i class="bi bi-check-lg me-1"></i>저장</button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="loadSignature()"><i class="bi bi-arrow-clockwise me-1"></i>되돌리기</button>
                </div>
            </div>

            <!-- 미리보기 -->
            <div class="col-lg-6">
                <label class="form-label small fw-semibold">미리보기</label>
                <div class="sig-preview" id="sigPreview"><span class="text-muted">서명이 비어있습니다.</span></div>
                <div class="mt-2 small text-muted">
                    <i class="bi bi-info-circle me-1"></i>메일 작성 시 "서명 포함" 체크하면 자동으로 본문 하단에 추가됩니다.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 이미지 관리 -->
<div class="card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold small"><i class="bi bi-images me-1"></i>이미지 라이브러리</span>
        <label class="btn btn-sm btn-outline-primary mb-0" for="imgUpload" style="cursor:pointer;">
            <i class="bi bi-upload me-1"></i>이미지 업로드
        </label>
        <input type="file" id="imgUpload" accept="image/*" style="display:none;" onchange="uploadImage(this)">
    </div>
    <div class="card-body">
        <div class="small text-muted mb-2">클릭: URL 복사 / "삽입": 서명에 img 태그 삽입</div>
        <div class="img-gallery" id="imgGallery">
            <div class="text-muted small">불러오는 중...</div>
        </div>
    </div>
</div>

<?php
$admin_extra_js = <<<'JSEOF'
var currentSigAccount = 0;

document.addEventListener('DOMContentLoaded', function() {
    loadAccountTabs();
    loadImages();
    document.getElementById('sigContent').addEventListener('input', updatePreview);
});

function updatePreview() {
    var content = document.getElementById('sigContent').value;
    document.getElementById('sigPreview').innerHTML = content || '<span class="text-muted">서명이 비어있습니다.</span>';
}

// === 계정 탭 ===
function loadAccountTabs() {
    fetch('admin_mail_signatures.php?ajax=get_accounts')
        .then(r => r.json())
        .then(data => {
            var accounts = data.accounts || [];
            var html = '';
            accounts.forEach(function(acc) {
                var isActive = acc.index === currentSigAccount;
                html += '<div class="account-tab' + (isActive ? ' active' : '') + '" onclick="switchAccount(' + acc.index + ')">';
                html += '<span class="account-badge" style="background:' + acc.color + ';"></span>';
                html += acc.label + ' (' + acc.email + ')';
                html += '</div>';
            });
            document.getElementById('accountTabs').innerHTML = html;
            loadSignature();
        });
}

function switchAccount(index) {
    currentSigAccount = index;
    document.querySelectorAll('.account-tab').forEach(function(tab, i) {
        tab.classList.toggle('active', i === index);
    });
    loadSignature();
}

// === 서명 CRUD ===
function loadSignature() {
    fetch('admin_mail_signatures.php?ajax=get_signature&account=' + currentSigAccount)
        .then(r => r.json())
        .then(data => {
            document.getElementById('sigContent').value = data.signature || '';
            updatePreview();
        });
}

function saveSignature() {
    var html = document.getElementById('sigContent').value;
    fetch('admin_mail_signatures.php?ajax=save_signature', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'account=' + currentSigAccount + '&signature=' + encodeURIComponent(html)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('서명이 저장되었습니다.');
        }
    });
}

// === 이미지 관리 ===
function uploadImage(input) {
    if (!input.files[0]) return;
    var formData = new FormData();
    formData.append('image', input.files[0]);

    fetch('admin_mail_signatures.php?ajax=upload_image', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('이미지 업로드 완료');
            loadImages();
        } else {
            alert('업로드 실패: ' + (data.error || ''));
        }
    });
    input.value = '';
}

function loadImages() {
    fetch('admin_mail_signatures.php?ajax=list_images')
        .then(r => r.json())
        .then(data => {
            var images = data.images || [];
            var el = document.getElementById('imgGallery');
            if (images.length === 0) {
                el.innerHTML = '<div class="text-muted small">업로드된 이미지가 없습니다.</div>';
                return;
            }
            var html = '';
            images.forEach(function(img) {
                html += '<div class="img-gallery-item">';
                html += '<img src="' + escHtml(img.url) + '" alt="" onclick="copyImageUrl(\'' + escHtml(img.url) + '\')">';
                html += '<button class="img-insert-btn" onclick="event.stopPropagation();insertImage(\'' + escHtml(img.url) + '\')">삽입</button>';
                html += '</div>';
            });
            el.innerHTML = html;
        });
}

function copyImageUrl(url) {
    navigator.clipboard.writeText(url).then(function() {
        showToast('URL 복사됨: ' + url);
    }).catch(function() {
        prompt('이미지 URL:', url);
    });
}

function insertImage(url) {
    var textarea = document.getElementById('sigContent');
    var pos = textarea.selectionStart || textarea.value.length;
    var tag = '<img src="' + url + '" style="max-width:200px;height:auto;" alt="">';
    textarea.value = textarea.value.substring(0, pos) + tag + textarea.value.substring(pos);
    updatePreview();
    showToast('이미지 삽입 완료');
}

// === 유틸 ===
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
