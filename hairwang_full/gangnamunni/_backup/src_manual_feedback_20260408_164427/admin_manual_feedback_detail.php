<?php
/**
 * 매뉴얼 수정요청 상세 + 상태/진행관리 + Claude 프롬프트 생성
 * @created 2026-04-08
 */
session_start();
$admin_page_title = '수정요청 상세';
$admin_current    = 'manual_feedback';

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/theme_helper.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_admin_login();

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: admin_manual_feedback_list.php'); exit; }

$stmt = mysqli_prepare($connect, "SELECT * FROM manual_feedback_items WHERE id=?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$item = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$item) { echo '항목을 찾을 수 없습니다.'; exit; }

// 로그 조회
$ls = mysqli_prepare($connect, "SELECT * FROM manual_feedback_logs WHERE feedback_item_id=? ORDER BY created_at DESC");
mysqli_stmt_bind_param($ls, 'i', $id);
mysqli_stmt_execute($ls);
$logs = mysqli_stmt_get_result($ls)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($ls);

$status_labels   = ['requested'=>'접수','prompt_ready'=>'프롬프트 준비','in_progress'=>'진행중','done'=>'완료','hold'=>'보류','rejected'=>'거절'];
$status_colors   = ['requested'=>'primary','prompt_ready'=>'info','in_progress'=>'warning','done'=>'success','hold'=>'secondary','rejected'=>'danger'];
$priority_labels = ['low'=>'낮음','normal'=>'보통','high'=>'높음','urgent'=>'긴급'];
$priority_colors = ['low'=>'secondary','normal'=>'info','high'=>'warning','urgent'=>'danger'];
$type_labels     = ['bug_fix'=>'오류수정','feature_add'=>'기능추가','content_edit'=>'내용수정','update'=>'업데이트','other'=>'기타'];
$role_labels     = ['client'=>'클라이언트','developer'=>'개발자','manager'=>'매니저','other'=>'기타'];

require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="container-fluid px-4" id="mf-detail-app">

  <!-- 헤더 -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center gap-2">
      <a href="admin_manual_feedback_list.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
      <h4 class="mb-0">수정요청 #<?= $id ?></h4>
      <span class="badge bg-<?= $status_colors[$item['status']] ?? 'secondary' ?>" id="badge-status">
        <?= $status_labels[$item['status']] ?? $item['status'] ?>
      </span>
      <span class="badge bg-<?= $priority_colors[$item['priority']] ?? 'secondary' ?>">
        <?= $priority_labels[$item['priority']] ?? $item['priority'] ?>
      </span>
    </div>
    <span class="text-muted small"><?= $item['created_at'] ?></span>
  </div>

  <div class="row g-3">

    <!-- 왼쪽: 요청 정보 -->
    <div class="col-lg-7">

      <!-- 1. 요청 내용 -->
      <div class="card mb-3">
        <div class="card-header fw-bold"><i class="bi bi-chat-left-text me-2"></i>1. 요청 내용</div>
        <div class="card-body">
          <div class="row g-2 mb-2">
            <div class="col-sm-4"><span class="text-muted small">작성자</span><div class="fw-bold"><?= htmlspecialchars($item['created_by_name']) ?></div></div>
            <div class="col-sm-4"><span class="text-muted small">구분</span><div><?= $role_labels[$item['created_by_role']] ?? $item['created_by_role'] ?></div></div>
            <div class="col-sm-4"><span class="text-muted small">유형</span><div><?= $type_labels[$item['request_type']] ?? $item['request_type'] ?></div></div>
          </div>
          <div class="mb-2">
            <span class="text-muted small">요청 제목</span>
            <div class="fw-bold fs-6 mt-1"><?= htmlspecialchars($item['request_title']) ?></div>
          </div>
          <div>
            <span class="text-muted small">요청 내용</span>
            <div class="mt-1 p-2 bg-light rounded" style="white-space:pre-wrap;font-size:0.9rem;"><?= htmlspecialchars($item['request_text']) ?></div>
          </div>
        </div>
      </div>

      <!-- 2. 소스 위치 -->
      <div class="card mb-3">
        <div class="card-header fw-bold"><i class="bi bi-geo-alt me-2"></i>2. 소스 위치</div>
        <div class="card-body" style="font-size:0.875rem;">
          <table class="table table-sm mb-0">
            <tr><th style="width:110px" class="text-muted">페이지 URL</th><td><?= $item['page_url'] ? '<a href="'.htmlspecialchars($item['page_url']).'" target="_blank">'.htmlspecialchars($item['page_url']).'</a>' : '-' ?></td></tr>
            <tr><th class="text-muted">페이지 제목</th><td><?= htmlspecialchars($item['page_title']) ?: '-' ?></td></tr>
            <tr><th class="text-muted">경로</th><td><?= htmlspecialchars($item['page_path']) ?: '-' ?></td></tr>
            <tr><th class="text-muted">섹션</th><td><?= htmlspecialchars($item['section_title']) ?: '-' ?></td></tr>
            <tr><th class="text-muted">메뉴</th><td><?= htmlspecialchars($item['menu_label']) ?: '-' ?></td></tr>
            <?php if ($item['source_location_desc']): ?>
            <tr><th class="text-muted">위치 설명</th><td><?= nl2br(htmlspecialchars($item['source_location_desc'])) ?></td></tr>
            <?php endif; ?>
          </table>
        </div>
      </div>

      <!-- 3. 진행사항 -->
      <div class="card mb-3">
        <div class="card-header fw-bold"><i class="bi bi-pencil-square me-2"></i>3. 진행사항</div>
        <div class="card-body">
          <div class="mb-2">
            <label class="form-label small fw-bold">진행 메모</label>
            <textarea id="progress-note" class="form-control" rows="4" placeholder="진행 내용을 입력하세요..."><?= htmlspecialchars($item['progress_note'] ?? '') ?></textarea>
            <button class="btn btn-sm btn-primary mt-2" onclick="saveField('progress')">
              <i class="bi bi-save me-1"></i>메모 저장
            </button>
          </div>
          <hr class="my-2">
          <div>
            <label class="form-label small fw-bold">상태 변경</label>
            <div class="d-flex gap-2 flex-wrap">
              <?php foreach ($status_labels as $k => $v): ?>
                <button class="btn btn-sm <?= $item['status']===$k ? 'btn-'.($status_colors[$k]??'secondary') : 'btn-outline-'.($status_colors[$k]??'secondary') ?>"
                  onclick="changeStatus('<?= $k ?>')"
                  id="btn-status-<?= $k ?>">
                  <?= $v ?>
                </button>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- 로그 -->
      <?php if ($logs): ?>
      <div class="card mb-3">
        <div class="card-header fw-bold"><i class="bi bi-clock-history me-2"></i>변경 이력</div>
        <div class="card-body p-0">
          <table class="table table-sm mb-0" style="font-size:0.8rem;">
            <thead><tr><th>일시</th><th>내용</th><th>처리자</th></tr></thead>
            <tbody>
              <?php foreach ($logs as $log): ?>
              <tr>
                <td class="text-muted"><?= $log['created_at'] ?></td>
                <td><?= htmlspecialchars($log['log_text']) ?></td>
                <td><?= htmlspecialchars($log['created_by']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

    </div><!-- /col-lg-7 -->

    <!-- 오른쪽: Claude 프롬프트 -->
    <div class="col-lg-5">
      <div class="card" style="position:sticky;top:1rem;">
        <div class="card-header fw-bold d-flex justify-content-between align-items-center">
          <span><i class="bi bi-robot me-2"></i>4. Claude CLI 프롬프트 생성</span>
          <?php if ($item['prompt_generated_at']): ?>
            <small class="text-muted"><?= $item['prompt_generated_at'] ?></small>
          <?php endif; ?>
        </div>
        <div class="card-body">
          <button class="btn btn-warning w-100 mb-3" onclick="generatePrompt()" id="btn-generate">
            <i class="bi bi-lightning-charge me-1"></i>Claude 프롬프트 생성
          </button>

          <div id="prompt-area" style="<?= $item['prompt_text'] ? '' : 'display:none' ?>">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <label class="form-label small fw-bold mb-0">생성된 프롬프트</label>
              <button class="btn btn-sm btn-outline-secondary" onclick="copyPrompt()">
                <i class="bi bi-clipboard me-1"></i>복사
              </button>
            </div>
            <textarea id="prompt-text" class="form-control font-monospace" rows="28"
              style="font-size:0.75rem;resize:vertical;"
              placeholder="프롬프트 생성 버튼을 클릭하세요."><?= htmlspecialchars($item['prompt_text'] ?? '') ?></textarea>
            <button class="btn btn-sm btn-info mt-2" onclick="savePromptStatus()">
              <i class="bi bi-check-circle me-1"></i>상태를 'prompt_ready'로 변경
            </button>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /row -->
</div>

<script>
const ITEM_ID = <?= $id ?>;
const BASE = location.href.replace(/admin_manual_feedback_detail\.php.*/, '');

async function apiPost(url, data) {
  const r = await fetch(url, {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify(data)
  });
  return r.json();
}

async function saveField(field) {
  const note = document.getElementById('progress-note').value;
  const res  = await apiPost(BASE + 'api_manual_feedback_update.php', {id: ITEM_ID, progress_note: note});
  if (res.success) showToast('저장됐습니다.', 'success');
  else showToast('저장 실패: ' + (res.error||''), 'danger');
}

async function changeStatus(newStatus) {
  const note = document.getElementById('progress-note').value;
  const res  = await apiPost(BASE + 'api_manual_feedback_update.php', {id: ITEM_ID, status: newStatus, progress_note: note});
  if (res.success) {
    // 버튼 상태 업데이트
    document.querySelectorAll('[id^=btn-status-]').forEach(b => {
      b.className = b.className.replace(/btn-(?!sm|outline)[\w-]+/, 'btn-outline-' + b.id.replace('btn-status-',''));
    });
    // 현재 버튼 활성화
    const btn = document.getElementById('btn-status-' + newStatus);
    if (btn) btn.className = btn.className.replace('btn-outline-', 'btn-');
    showToast('상태가 변경됐습니다.', 'success');
    setTimeout(() => location.reload(), 800);
  } else {
    showToast('오류: ' + (res.error||''), 'danger');
  }
}

async function generatePrompt() {
  const btn = document.getElementById('btn-generate');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>생성 중...';

  const formData = new FormData();
  formData.append('id', ITEM_ID);
  const r = await fetch(BASE + 'api_manual_feedback_generate_prompt.php', {method:'POST', body: formData});
  const res = await r.json();

  btn.disabled = false;
  btn.innerHTML = '<i class="bi bi-lightning-charge me-1"></i>Claude 프롬프트 재생성';

  if (res.success) {
    document.getElementById('prompt-text').value = res.prompt;
    document.getElementById('prompt-area').style.display = '';
    showToast('프롬프트 생성 완료!', 'success');
  } else {
    showToast('오류: ' + (res.error||''), 'danger');
  }
}

function copyPrompt() {
  const ta = document.getElementById('prompt-text');
  ta.select();
  document.execCommand('copy');
  showToast('클립보드에 복사됐습니다!', 'info');
}

async function savePromptStatus() {
  const res = await apiPost(BASE + 'api_manual_feedback_update.php', {id: ITEM_ID, status: 'prompt_ready'});
  if (res.success) {
    showToast('상태가 prompt_ready로 변경됐습니다.', 'success');
    setTimeout(() => location.reload(), 800);
  }
}

function showToast(msg, type = 'success') {
  let cont = document.getElementById('toast-container');
  if (!cont) {
    cont = document.createElement('div');
    cont.id = 'toast-container';
    cont.style.cssText = 'position:fixed;bottom:1rem;right:1rem;z-index:9999;display:flex;flex-direction:column;gap:0.5rem;';
    document.body.appendChild(cont);
  }
  const el = document.createElement('div');
  el.className = `alert alert-${type} py-2 px-3 mb-0 shadow-sm`;
  el.style.cssText = 'min-width:200px;font-size:0.875rem;';
  el.textContent = msg;
  cont.appendChild(el);
  setTimeout(() => el.remove(), 3000);
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
