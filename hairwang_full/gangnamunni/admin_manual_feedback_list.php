<?php
/**
 * 매뉴얼 수정요청 관리 목록
 * @created 2026-04-08
 */
session_start();
$admin_page_title   = '매뉴얼 수정요청 관리';
$admin_current      = 'manual_feedback';

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/theme_helper.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_admin_login();

// ─── 필터 ───
$f_status   = trim($_GET['status']   ?? '');
$f_priority = trim($_GET['priority'] ?? '');
$f_type     = trim($_GET['type']     ?? '');
$f_role     = trim($_GET['role']     ?? '');
$f_keyword  = trim($_GET['keyword']  ?? '');
$page       = max(1, intval($_GET['p'] ?? 1));
$per_page   = 20;
$offset     = ($page - 1) * $per_page;

$allowed_status   = ['requested','prompt_ready','in_progress','done','hold','rejected'];
$allowed_priority = ['low','normal','high','urgent'];
$allowed_types    = ['bug_fix','feature_add','content_edit','update','other'];
$allowed_roles    = ['client','developer','manager','other'];

$where  = [];
$params = [];
$types  = '';

if ($f_status   && in_array($f_status, $allowed_status))   { $where[] = "status=?";            $types .= 's'; $params[] = $f_status; }
if ($f_priority && in_array($f_priority, $allowed_priority)){ $where[] = "priority=?";          $types .= 's'; $params[] = $f_priority; }
if ($f_type     && in_array($f_type, $allowed_types))       { $where[] = "request_type=?";      $types .= 's'; $params[] = $f_type; }
if ($f_role     && in_array($f_role, $allowed_roles))       { $where[] = "created_by_role=?";   $types .= 's'; $params[] = $f_role; }
if ($f_keyword) { $like = '%' . $f_keyword . '%'; $where[] = "(request_title LIKE ? OR request_text LIKE ?)"; $types .= 'ss'; $params[] = $like; $params[] = $like; }

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$count_sql  = "SELECT COUNT(*) FROM manual_feedback_items $where_sql";
$stmt_count = mysqli_prepare($connect, $count_sql);
if ($types) mysqli_stmt_bind_param($stmt_count, $types, ...$params);
mysqli_stmt_execute($stmt_count);
$total = mysqli_stmt_get_result($stmt_count)->fetch_row()[0] ?? 0;
mysqli_stmt_close($stmt_count);

$list_sql  = "SELECT * FROM manual_feedback_items $where_sql ORDER BY created_at DESC LIMIT ? OFFSET ?";
$types_p   = $types . 'ii';
$params_p  = array_merge($params, [$per_page, $offset]);
$stmt_list = mysqli_prepare($connect, $list_sql);
mysqli_stmt_bind_param($stmt_list, $types_p, ...$params_p);
mysqli_stmt_execute($stmt_list);
$items = mysqli_stmt_get_result($stmt_list)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt_list);

$total_pages = max(1, ceil($total / $per_page));

// ─── 라벨 ───
$status_labels = ['requested'=>'접수','prompt_ready'=>'프롬프트 준비','in_progress'=>'진행중','done'=>'완료','hold'=>'보류','rejected'=>'거절'];
$status_colors = ['requested'=>'primary','prompt_ready'=>'info','in_progress'=>'warning','done'=>'success','hold'=>'secondary','rejected'=>'danger'];
$priority_labels = ['low'=>'낮음','normal'=>'보통','high'=>'높음','urgent'=>'긴급'];
$priority_colors = ['low'=>'secondary','normal'=>'info','high'=>'warning','urgent'=>'danger'];
$type_labels = ['bug_fix'=>'오류수정','feature_add'=>'기능추가','content_edit'=>'내용수정','update'=>'업데이트','other'=>'기타'];
$role_labels = ['client'=>'클라이언트','developer'=>'개발자','manager'=>'매니저','other'=>'기타'];

require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="container-fluid px-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-chat-left-text me-2"></i>매뉴얼 수정요청 관리</h4>
    <div class="d-flex gap-2">
      <span class="badge bg-secondary"><?= number_format($total) ?> 건</span>
      <a href="https://hms.seoulbeauty.app/hairwang/gangnamunni/manual/index.html" target="_blank" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-box-arrow-up-right me-1"></i>매뉴얼 페이지
      </a>
      <button class="btn btn-sm btn-success" onclick="mfBuildReportDone()">
        <i class="bi bi-file-earmark-check me-1"></i>완료 리포트 생성
      </button>
    </div>
  </div>

  <!-- 필터 -->
  <form method="get" class="card card-body mb-3 p-3">
    <div class="row g-2 align-items-end">
      <div class="col-sm-2">
        <label class="form-label form-label-sm mb-1">상태</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">전체</option>
          <?php foreach ($status_labels as $k=>$v): ?>
            <option value="<?= $k ?>" <?= $f_status===$k?'selected':'' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-2">
        <label class="form-label form-label-sm mb-1">중요도</label>
        <select name="priority" class="form-select form-select-sm">
          <option value="">전체</option>
          <?php foreach ($priority_labels as $k=>$v): ?>
            <option value="<?= $k ?>" <?= $f_priority===$k?'selected':'' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-2">
        <label class="form-label form-label-sm mb-1">요청유형</label>
        <select name="type" class="form-select form-select-sm">
          <option value="">전체</option>
          <?php foreach ($type_labels as $k=>$v): ?>
            <option value="<?= $k ?>" <?= $f_type===$k?'selected':'' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-2">
        <label class="form-label form-label-sm mb-1">작성자구분</label>
        <select name="role" class="form-select form-select-sm">
          <option value="">전체</option>
          <?php foreach ($role_labels as $k=>$v): ?>
            <option value="<?= $k ?>" <?= $f_role===$k?'selected':'' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-3">
        <label class="form-label form-label-sm mb-1">키워드</label>
        <input type="text" name="keyword" class="form-control form-control-sm" value="<?= htmlspecialchars($f_keyword) ?>" placeholder="제목/내용 검색">
      </div>
      <div class="col-sm-1 d-flex gap-1">
        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
        <a href="?" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x"></i></a>
      </div>
    </div>
  </form>

  <!-- 일괄 작업 툴바 -->
  <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
    <button id="btn-prompt-selected" class="btn btn-sm btn-primary d-none" onclick="mfPromptSelected()">
      <i class="bi bi-robot me-1"></i>프롬프트 생성
    </button>
    <button id="btn-delete-selected" class="btn btn-sm btn-danger d-none" onclick="mfDeleteSelected()">
      <i class="bi bi-trash me-1"></i>선택 삭제
    </button>
    <button id="btn-report-selected" class="btn btn-sm btn-success d-none" onclick="mfBuildReport()">
      <i class="bi bi-file-earmark-check me-1"></i>클라이언트 리포트
    </button>
    <span id="chk-count-label" class="text-muted small d-none"></span>
  </div>

  <!-- 목록 -->
  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0" style="font-size:0.875rem;">
        <thead class="table-light">
          <tr>
            <th style="width:40px"><input type="checkbox" id="chk-all" title="전체선택"></th>
            <th style="width:60px">#</th>
            <th style="width:130px">등록일시</th>
            <th style="width:90px">작성자</th>
            <th style="width:80px">구분</th>
            <th style="width:85px">유형</th>
            <th style="width:75px">중요도</th>
            <th>요청 제목</th>
            <th style="width:120px">페이지</th>
            <th style="width:90px">상태</th>
            <th style="width:60px">상세</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($items)): ?>
          <tr><td colspan="11" class="text-center py-4 text-muted">등록된 요청이 없습니다.</td></tr>
        <?php else: ?>
          <?php $prev_date = ''; ?>
          <?php foreach ($items as $item): ?>
            <?php
              $cur_date = date('Y-m-d', strtotime($item['created_at']));
              if ($cur_date !== $prev_date) {
                $prev_date = $cur_date;
                echo "<tr class='table-secondary'><td colspan='11' class='py-1 px-3' style='font-size:0.8rem;font-weight:600;'><i class='bi bi-calendar3 me-1'></i>{$cur_date}</td></tr>";
              }
            ?>
            <tr class="mf-row" data-id="<?= $item['id'] ?>" style="cursor:pointer;" onclick="if(event.target.tagName!=='INPUT'&&!event.target.closest('a'))window.open('admin_manual_feedback_detail.php?id=<?= $item['id'] ?>','_blank')">
              <td onclick="event.stopPropagation()"><input type="checkbox" class="chk-item" value="<?= $item['id'] ?>"></td>
              <td class="text-muted"><?= $item['id'] ?></td>
              <td class="text-muted" style="font-size:0.78rem;"><?= date('H:i', strtotime($item['created_at'])) ?></td>
              <td><?= htmlspecialchars($item['created_by_name']) ?></td>
              <td><span class="badge bg-light text-dark border"><?= $role_labels[$item['created_by_role']] ?? $item['created_by_role'] ?></span></td>
              <td><span class="badge bg-light text-dark border"><?= $type_labels[$item['request_type']] ?? $item['request_type'] ?></span></td>
              <td>
                <span class="badge bg-<?= $priority_colors[$item['priority']] ?? 'secondary' ?>">
                  <?= $priority_labels[$item['priority']] ?? $item['priority'] ?>
                </span>
              </td>
              <td>
                <div style="max-width:320px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;" title="<?= htmlspecialchars($item['request_title']) ?>">
                  <?= htmlspecialchars($item['request_title']) ?>
                  <?php if (!empty($item['image_path'])): ?>
                  <?php $ic=is_array(json_decode($item['image_path'],true))?count(json_decode($item['image_path'],true)):1; ?>
                  <span class="badge bg-secondary ms-1" title="이미지 <?= $ic ?>장 첨부"><i class="bi bi-image"></i> <?= $ic ?></span>
                  <?php endif; ?>
                </div>
                <?php if ($item['progress_note']): ?>
                  <div class="text-muted" style="font-size:0.75rem;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;max-width:320px;">
                    <i class="bi bi-chat-right-text me-1"></i><?= htmlspecialchars(mb_substr($item['progress_note'],0,60)) ?>
                  </div>
                <?php endif; ?>
              </td>
              <td class="text-muted" style="font-size:0.75rem;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;max-width:120px;"
                  title="<?= htmlspecialchars($item['page_url']) ?>">
                <?= htmlspecialchars($item['page_path'] ?: $item['page_title'] ?: '-') ?>
              </td>
              <td>
                <span class="badge bg-<?= $status_colors[$item['status']] ?? 'secondary' ?>">
                  <?= $status_labels[$item['status']] ?? $item['status'] ?>
                </span>
              </td>
              <td>
                <a href="admin_manual_feedback_detail.php?id=<?= $item['id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2" onclick="event.stopPropagation()">
                  <i class="bi bi-arrow-right"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- 페이징 -->
  <?php if ($total_pages > 1): ?>
  <nav class="mt-3 d-flex justify-content-center">
    <ul class="pagination pagination-sm mb-0">
      <?php for ($i = max(1,$page-4); $i <= min($total_pages,$page+4); $i++): ?>
        <li class="page-item <?= $i===$page?'active':'' ?>">
          <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['p'=>$i])) ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
    </ul>
  </nav>
  <?php endif; ?>
</div>

<script>
// ── 전체선택 ──
document.getElementById('chk-all').addEventListener('change', function() {
  document.querySelectorAll('.chk-item').forEach(c => c.checked = this.checked);
  updateDeleteBar();
});
document.addEventListener('change', function(e) {
  if (e.target.classList.contains('chk-item')) updateDeleteBar();
});
function updateDeleteBar() {
  const checked = document.querySelectorAll('.chk-item:checked');
  const btnDel  = document.getElementById('btn-delete-selected');
  const btnPrm  = document.getElementById('btn-prompt-selected');
  const lbl     = document.getElementById('chk-count-label');
  const btnRpt  = document.getElementById('btn-report-selected');
  if (checked.length > 0) {
    btnDel.classList.remove('d-none');
    btnPrm.classList.remove('d-none');
    if (btnRpt) btnRpt.classList.remove('d-none');
    lbl.classList.remove('d-none');
    lbl.textContent = checked.length + '건 선택됨';
  } else {
    btnDel.classList.add('d-none');
    btnPrm.classList.add('d-none');
    if (btnRpt) btnRpt.classList.add('d-none');
    lbl.classList.add('d-none');
  }
}

// ── 선택 항목 프롬프트 생성 ──
async function mfPromptSelected() {
  const ids = Array.from(document.querySelectorAll('.chk-item:checked')).map(c => c.value);
  if (!ids.length) return;
  btnPrm = document.getElementById('btn-prompt-selected');
  btnPrm.disabled = true;
  btnPrm.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>생성 중...';

  const results = [];
  for (const id of ids) {
    try {
      const fd = new FormData(); fd.append('id', id);
      const r = await fetch('api_manual_feedback_generate_prompt.php', { method: 'POST', body: fd });
      const d = await r.json();
      if (d.success) results.push({ id, prompt: d.prompt });
      else results.push({ id, error: d.error || '실패' });
    } catch(e) { results.push({ id, error: '네트워크 오류' }); }
  }

  btnPrm.disabled = false;
  btnPrm.innerHTML = '<i class="bi bi-robot me-1"></i>프롬프트 생성';

  // 결과 모달 표시
  const ok = results.filter(r => r.prompt);
  const fail = results.filter(r => r.error);
  let combined = ok.map(r => '# 요청 #' + r.id + '\n\n' + r.prompt).join('\n\n' + '='.repeat(60) + '\n\n');
  if (!combined) { alert('프롬프트 생성 실패: ' + fail.map(r => '#'+r.id+' '+r.error).join(', ')); return; }

  showPromptModal(combined, ok.length, fail.length);
}

function showPromptModal(text, okCnt, failCnt) {
  let m = document.getElementById('mf-prompt-modal');
  if (!m) {
    m = document.createElement('div');
    m.id = 'mf-prompt-modal';
    m.className = 'modal fade';
    m.tabIndex = -1;
    m.innerHTML = `
      <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header" style="background:#111;color:#fff;">
            <h5 class="modal-title fw-bold"><i class="bi bi-robot me-2"></i>생성된 Claude CLI 프롬프트</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body p-0">
            <div class="px-3 pt-3 pb-1" id="mf-prompt-info"></div>
            <textarea id="mf-prompt-text" class="form-control border-0 rounded-0"
              style="min-height:500px;font-family:monospace;font-size:0.8rem;resize:none;" readonly></textarea>
          </div>
          <div class="modal-footer">
            <button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">닫기</button>
            <button class="btn btn-sm btn-primary" onclick="navigator.clipboard.writeText(document.getElementById('mf-prompt-text').value).then(()=>alert('복사됨'))">
              <i class="bi bi-clipboard me-1"></i>전체 복사
            </button>
          </div>
        </div>
      </div>`;
    document.body.appendChild(m);
  }
  document.getElementById('mf-prompt-info').innerHTML =
    '<span class="badge bg-success me-1">' + okCnt + '건 생성</span>' +
    (failCnt ? '<span class="badge bg-danger">' + failCnt + '건 실패</span>' : '');
  document.getElementById('mf-prompt-text').value = text;
  new bootstrap.Modal(m).show();
}

// ── 선택 삭제 ──
function mfDeleteSelected() {
  const ids = Array.from(document.querySelectorAll('.chk-item:checked')).map(c => c.value);
  if (!ids.length) return;
  if (!confirm(ids.length + '건을 삭제하시겠습니까?\n삭제된 데이터는 복구할 수 없습니다.')) return;
  fetch('api_manual_feedback_delete.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ ids: ids })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      alert(data.deleted + '건이 삭제되었습니다.');
      location.reload();
    } else {
      alert('삭제 실패: ' + (data.error || '알 수 없는 오류'));
    }
  })
  .catch(() => alert('오류가 발생했습니다.'));
}


// ── 선택 항목 클라이언트 리포트 생성 ──
async function mfBuildReport() {
  const ids = Array.from(document.querySelectorAll('.chk-item:checked')).map(c => c.value);
  if (!ids.length) return;
  const btn = document.getElementById('btn-report-selected');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>생성 중...';
  try {
    const fd = new FormData();
    ids.forEach(id => fd.append('ids[]', id));
    const r = await fetch('api_manual_feedback_build_report.php', { method: 'POST', body: fd });
    const d = await r.json();
    if (d.success) showReportModal(d.url, d.count, d.filename);
    else alert('리포트 생성 실패: ' + (d.error || '알 수 없는 오류'));
  } catch(e) { alert('네트워크 오류'); }
  btn.disabled = false;
  btn.innerHTML = '<i class="bi bi-file-earmark-check me-1"></i>클라이언트 리포트';
}

// ── 완료(done) 전체 리포트 생성 ──
async function mfBuildReportDone() {
  const btn = document.querySelector('[onclick="mfBuildReportDone()"]');
  if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>생성 중...'; }
  try {
    const fd = new FormData();
    fd.append('all_done', '1');
    const r = await fetch('api_manual_feedback_build_report.php', { method: 'POST', body: fd });
    const d = await r.json();
    if (d.success) showReportModal(d.url, d.count, d.filename);
    else alert('리포트 생성 실패: ' + (d.error || '알 수 없는 오류'));
  } catch(e) { alert('네트워크 오류'); }
  if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-file-earmark-check me-1"></i>완료 리포트 생성'; }
}

// ── 리포트 URL 결과 모달 ──
function showReportModal(url, count, filename) {
  let m = document.getElementById('mf-report-modal');
  if (!m) {
    m = document.createElement('div');
    m.id = 'mf-report-modal';
    m.className = 'modal fade';
    m.tabIndex = -1;
    m.innerHTML = `
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header" style="background:#0f5132;color:#fff;">
            <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-check me-2"></i>클라이언트 리포트 생성 완료</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-success d-flex align-items-center gap-2 mb-3">
              <i class="bi bi-check-circle-fill fs-5"></i>
              <span id="mf-report-count"></span>건의 리포트가 생성됐습니다.</div>
            <label class="form-label fw-bold">클라이언트 공유 URL</label>
            <div class="input-group">
              <input type="text" id="mf-report-url" class="form-control font-monospace" readonly>
              <button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('mf-report-url').value).then(()=>showToastLocal('URL 복사됨!'))">
                <i class="bi bi-clipboard"></i>
              </button>
            </div>
            <div class="mt-3">
              <a id="mf-report-link" href="#" target="_blank" class="btn btn-primary">
                <i class="bi bi-box-arrow-up-right me-1"></i>리포트 새 탭에서 열기
              </a>
            </div>
            <p class="text-muted small mt-3 mb-0">파일명: <span id="mf-report-filename" class="font-monospace"></span></p>
          </div>
          <div class="modal-footer">
            <button class="btn btn-outline-secondary" data-bs-dismiss="modal">닫기</button>
          </div>
        </div>
      </div>`;
    document.body.appendChild(m);
  }
  document.getElementById('mf-report-count').textContent = count;
  document.getElementById('mf-report-url').value = url;
  document.getElementById('mf-report-link').href = url;
  document.getElementById('mf-report-filename').textContent = filename;
  new bootstrap.Modal(m).show();
}

function showToastLocal(msg) {
  const t = document.createElement('div');
  t.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:9999;';
  t.innerHTML = '<div class="toast show align-items-center text-white bg-success border-0"><div class="d-flex"><div class="toast-body">' + msg + '</div></div></div>';
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 2000);
}

</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
