<?php
/**
 * 상용구(템플릿) 관리 페이지
 * 이미지 업로드 + HTML 편집 + CRUD
 *
 * @created 2026-02-11
 */
session_start();
$admin_page_title = '상용구 관리';
$admin_current = 'mail_templates';

// DB 상수를 관리자 검사보다 먼저 올린다 — check_admin_login() 이 cred_epoch
// (비밀번호 변경 시 세션 폐기)를 조회할 수 있어야 한다. 아래쪽의
// require_once 는 그대로 두되 두 번 로드되지 않는다(require_once).
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_admin_login();
require_permission('mail_templates');
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
            echo json_encode(['error' => '허용되지 않는 파일 형식: ' . $mime]);
            exit;
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['error' => '5MB 초과']);
            exit;
        }
        $dir = __DIR__ . '/data/mail_images/';
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) ?: 'png';
        $filename = 'tmpl_' . date('Ymd_His') . '_' . mt_rand(1000,9999) . '.' . $ext;
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
                        'date' => date('Y-m-d H:i', filemtime($f)),
                    ];
                }
            }
        }
        echo json_encode(['images' => $images]);
        exit;
    }

    // 이미지 삭제
    if ($action === 'delete_image') {
        $filename = basename($_POST['filename'] ?? '');
        if (!$filename) { echo json_encode(['error' => '파일명 없음']); exit; }
        $path = __DIR__ . '/data/mail_images/' . $filename;
        if (file_exists($path)) {
            @unlink($path);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => '파일 없음']);
        }
        exit;
    }

    // 상용구 CRUD (기존 admin_mail.php에서 독립)
    if ($action === 'get_templates') {
        echo json_encode(['templates' => mail_get_templates()]);
        exit;
    }
    if ($action === 'save_template') {
        $id = intval($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $content = $_POST['content'] ?? '';
        if (!$title) { echo json_encode(['error' => '제목을 입력하세요.']); exit; }
        echo json_encode(['success' => mail_save_template($id, $title, $content)]);
        exit;
    }
    if ($action === 'delete_template') {
        $id = intval($_POST['id'] ?? 0);
        echo json_encode(['success' => mail_delete_template($id)]);
        exit;
    }

    echo json_encode(['error' => 'Unknown action']);
    exit;
}

// === HTML ===
$admin_extra_css = <<<'CSSEOF'
.tmpl-card { border: 1px solid var(--bs-border-color); border-radius: 8px; padding: 16px; margin-bottom: 12px; background: var(--bs-body-bg); transition: box-shadow 0.2s; }
.tmpl-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.tmpl-card .tmpl-title { font-weight: 600; font-size: 14px; margin-bottom: 4px; }
.tmpl-card .tmpl-preview { font-size: 13px; color: var(--bs-secondary-color); max-height: 60px; overflow: hidden; }
.tmpl-card .tmpl-actions { display: flex; gap: 4px; margin-top: 8px; }
.editor-area { min-height: 200px; border: 1px solid var(--bs-border-color); border-radius: 6px; padding: 12px; font-size: 14px; line-height: 1.6; }
.preview-area { min-height: 100px; border: 1px solid var(--bs-border-color); border-radius: 6px; padding: 12px; background: var(--bs-tertiary-bg); font-size: 14px; line-height: 1.6; }
.preview-area img { max-width: 100%; height: auto; }
.img-gallery { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
.img-gallery-item { position: relative; width: 80px; height: 80px; border: 1px solid var(--bs-border-color); border-radius: 6px; overflow: hidden; cursor: pointer; }
.img-gallery-item img { width: 100%; height: 100%; object-fit: cover; }
.img-gallery-item:hover { border-color: var(--bs-primary); }
.img-gallery-item .img-delete { position: absolute; top: 2px; right: 2px; background: rgba(220,53,69,0.8); color: #fff; border: none; border-radius: 50%; width: 20px; height: 20px; font-size: 10px; cursor: pointer; display: none; align-items: center; justify-content: center; }
.img-gallery-item:hover .img-delete { display: flex; }
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
    <h5 class="mb-0"><i class="bi bi-card-text me-2"></i>상용구 관리</h5>
    <div class="d-flex gap-2">
        <a href="admin_mail.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>메일함</a>
    </div>
</div>

<div class="row g-3">
    <!-- 왼쪽: 상용구 목록 -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold small">등록된 상용구</span>
                <button class="btn btn-sm btn-primary" onclick="newTemplate()"><i class="bi bi-plus-lg me-1"></i>추가</button>
            </div>
            <div class="card-body" id="templateListArea" style="max-height: 600px; overflow-y: auto;">
                <div class="text-muted small text-center py-4">불러오는 중...</div>
            </div>
        </div>
    </div>

    <!-- 오른쪽: 편집 영역 -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <span class="fw-semibold small" id="editorTitle">상용구 추가</span>
            </div>
            <div class="card-body">
                <input type="hidden" id="editId" value="0">
                <div class="mb-2">
                    <label class="form-label small fw-semibold">제목</label>
                    <input type="text" class="form-control form-control-sm" id="editTitle" placeholder="예: 인사말, 안내문, 할인 안내">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">내용 (HTML)</label>
                    <textarea class="form-control editor-area" id="editContent" rows="8" placeholder="HTML 내용을 입력하세요...&#10;예: <p>안녕하세요, 서울미녀입니다.</p>&#10;<img src='이미지URL' width='200'>"></textarea>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">미리보기</label>
                    <div class="preview-area" id="previewArea">-</div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary btn-sm" onclick="saveTemplate()"><i class="bi bi-check-lg me-1"></i>저장</button>
                    <button class="btn btn-secondary btn-sm" onclick="resetForm()">초기화</button>
                </div>
            </div>
        </div>

        <!-- 이미지 업로드 -->
        <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold small"><i class="bi bi-images me-1"></i>이미지 관리</span>
                <label class="btn btn-sm btn-outline-primary mb-0" for="imgUpload" style="cursor:pointer;">
                    <i class="bi bi-upload me-1"></i>업로드
                </label>
                <input type="file" id="imgUpload" accept="image/*" style="display:none;" onchange="uploadImage(this)">
            </div>
            <div class="card-body">
                <div class="small text-muted mb-2">이미지를 클릭하면 URL이 복사됩니다. "삽입" 클릭 시 편집 중인 상용구에 이미지 태그가 삽입됩니다.</div>
                <div class="img-gallery" id="imgGallery">
                    <div class="text-muted small">불러오는 중...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$admin_extra_js = <<<'JSEOF'
document.addEventListener('DOMContentLoaded', function() {
    loadTemplates();
    loadImages();
    document.getElementById('editContent').addEventListener('input', updatePreview);
});

function updatePreview() {
    document.getElementById('previewArea').innerHTML =
        document.getElementById('editContent').value || '<span class="text-muted">-</span>';
}

// === 상용구 CRUD ===
function loadTemplates() {
    fetch('admin_mail_templates.php?ajax=get_templates')
        .then(r => r.json())
        .then(data => {
            var templates = data.templates || [];
            var el = document.getElementById('templateListArea');
            if (templates.length === 0) {
                el.innerHTML = '<div class="text-muted small text-center py-4"><i class="bi bi-inbox" style="font-size:32px;display:block;margin-bottom:8px;"></i>등록된 상용구가 없습니다.<br>오른쪽에서 추가하세요.</div>';
                return;
            }
            var html = '';
            templates.forEach(function(t) {
                var preview = stripHtml(t.content).substring(0, 100);
                html += '<div class="tmpl-card" data-id="' + t.id + '">';
                html += '<div class="tmpl-title">' + escHtml(t.title) + '</div>';
                html += '<div class="tmpl-preview">' + (t.content || '') + '</div>';
                html += '<div class="tmpl-actions">';
                html += '<button class="btn btn-sm btn-outline-primary" onclick="editTemplate(' + t.id + ')"><i class="bi bi-pencil me-1"></i>수정</button>';
                html += '<button class="btn btn-sm btn-outline-danger" onclick="deleteTemplate(' + t.id + ')"><i class="bi bi-trash me-1"></i>삭제</button>';
                html += '</div></div>';
            });
            el.innerHTML = html;
        });
}

function newTemplate() {
    resetForm();
    document.getElementById('editTitle').focus();
}

function editTemplate(id) {
    fetch('admin_mail_templates.php?ajax=get_templates')
        .then(r => r.json())
        .then(data => {
            var t = (data.templates || []).find(x => x.id === id);
            if (!t) return;
            document.getElementById('editId').value = t.id;
            document.getElementById('editTitle').value = t.title;
            document.getElementById('editContent').value = t.content;
            document.getElementById('editorTitle').textContent = '상용구 수정: ' + t.title;
            updatePreview();
            document.getElementById('editTitle').focus();
            window.scrollTo({top: 0, behavior: 'smooth'});
        });
}

function saveTemplate() {
    var id = parseInt(document.getElementById('editId').value) || 0;
    var title = document.getElementById('editTitle').value.trim();
    var content = document.getElementById('editContent').value;
    if (!title) { alert('제목을 입력하세요.'); return; }

    fetch('admin_mail_templates.php?ajax=save_template', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + id + '&title=' + encodeURIComponent(title) + '&content=' + encodeURIComponent(content)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(id ? '수정 완료' : '추가 완료');
            resetForm();
            loadTemplates();
        } else {
            alert('저장 실패: ' + (data.error || ''));
        }
    });
}

function deleteTemplate(id) {
    if (!confirm('이 상용구를 삭제하시겠습니까?')) return;
    fetch('admin_mail_templates.php?ajax=delete_template', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + id
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('삭제 완료');
            loadTemplates();
            if (parseInt(document.getElementById('editId').value) === id) resetForm();
        }
    });
}

function resetForm() {
    document.getElementById('editId').value = '0';
    document.getElementById('editTitle').value = '';
    document.getElementById('editContent').value = '';
    document.getElementById('editorTitle').textContent = '상용구 추가';
    document.getElementById('previewArea').innerHTML = '<span class="text-muted">-</span>';
}

// === 이미지 관리 ===
function uploadImage(input) {
    if (!input.files[0]) return;
    var formData = new FormData();
    formData.append('image', input.files[0]);

    fetch('admin_mail_templates.php?ajax=upload_image', { method: 'POST', body: formData })
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
    fetch('admin_mail_templates.php?ajax=list_images')
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
                html += '<div class="img-gallery-item" title="' + escHtml(img.filename) + '">';
                html += '<img src="' + escHtml(img.url) + '" alt="" onclick="copyImageUrl(\'' + escHtml(img.url) + '\')">';
                html += '<button class="img-delete" onclick="event.stopPropagation();deleteImage(\'' + escHtml(img.filename) + '\')" title="삭제">&times;</button>';
                html += '<button class="img-insert-btn" onclick="event.stopPropagation();insertImage(\'' + escHtml(img.url) + '\')" title="내용에 삽입">삽입</button>';
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
    var textarea = document.getElementById('editContent');
    var pos = textarea.selectionStart || textarea.value.length;
    var tag = '<img src="' + url + '" style="max-width:100%;height:auto;" alt="">';
    textarea.value = textarea.value.substring(0, pos) + tag + textarea.value.substring(pos);
    updatePreview();
    showToast('이미지 삽입 완료');
}

function deleteImage(filename) {
    if (!confirm('이 이미지를 삭제하시겠습니까?')) return;
    fetch('admin_mail_templates.php?ajax=delete_image', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'filename=' + encodeURIComponent(filename)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('이미지 삭제 완료');
            loadImages();
        }
    });
}

// === 유틸 ===
function escHtml(s) {
    if (!s) return '';
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}
function stripHtml(html) {
    var tmp = document.createElement('div');
    tmp.innerHTML = html;
    return tmp.textContent || tmp.innerText || '';
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
