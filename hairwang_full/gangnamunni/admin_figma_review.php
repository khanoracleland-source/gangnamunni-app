<?php
require_once __DIR__ . '/includes/temporarily_disabled_pages.php';
enforce_temporarily_disabled_admin_page(__FILE__);

// ============================================================================
// [Figma Review - 2026-06-05] 피그마 검수 메모 대시보드
// 원인 : figma_compare.html에서 저장된 검수 메모를 한눈에 확인/관리할 페이지 필요
// 변경 : 통계 + 필터 + 목록 + Claude 응답 표시 + 상태 변경
// 영향 범위 : 사이드바 메뉴 "피그마 디자인 & 적용 페이지" → "검수 메모 대시보드"
// ============================================================================
session_start();

$admin_page_title = '피그마 검수 메모 대시보드';
$admin_current = 'figma_review_dashboard';

require_once __DIR__ . '/dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die('DB 연결 실패: ' . mysqli_connect_error());
mysqli_set_charset($connect, 'utf8mb4');

if (function_exists('loadThemeSettings')) loadThemeSettings($connect);
if (function_exists('require_admin_login')) require_admin_login();

// 필터 파라미터
$filter_status   = $_GET['status']   ?? '';
$filter_priority = $_GET['priority'] ?? '';
$filter_group    = $_GET['group']    ?? '';
$filter_wrong    = isset($_GET['wrong']) ? 1 : 0;

// 목록 조회
$where = ['1=1'];
if ($filter_status !== '' && in_array($filter_status, ['pending','in_progress','done','rejected'])) {
    $where[] = "status = '" . mysqli_real_escape_string($connect, $filter_status) . "'";
}
if ($filter_priority !== '' && in_array($filter_priority, ['high','medium','low'])) {
    $where[] = "priority = '" . mysqli_real_escape_string($connect, $filter_priority) . "'";
}
if ($filter_group !== '') {
    $where[] = "figma_group = '" . mysqli_real_escape_string($connect, $filter_group) . "'";
}
if ($filter_wrong) {
    $where[] = "is_wrong_match = 1";
}
$where_sql = implode(' AND ', $where);

$rows = [];
$r = mysqli_query($connect, "SELECT * FROM figma_review_notes WHERE $where_sql
                              ORDER BY FIELD(priority,'high','medium','low'), updated_at DESC LIMIT 500");
while ($r && ($row = mysqli_fetch_assoc($r))) $rows[] = $row;

// 통계
$stats = ['total'=>0,'pending'=>0,'in_progress'=>0,'done'=>0,'rejected'=>0,'wrong_match'=>0,'high'=>0];
$r2 = mysqli_query($connect, "SELECT status, priority, is_wrong_match, COUNT(*) cnt FROM figma_review_notes GROUP BY status, priority, is_wrong_match");
while ($r2 && ($row = mysqli_fetch_assoc($r2))) {
    $stats['total'] += (int)$row['cnt'];
    $stats[$row['status']] = ($stats[$row['status']] ?? 0) + (int)$row['cnt'];
    if ($row['is_wrong_match']) $stats['wrong_match'] += (int)$row['cnt'];
    if ($row['priority'] === 'high') $stats['high'] += (int)$row['cnt'];
}

// 그룹 목록 (필터 드롭다운용)
$groups = [];
$r3 = mysqli_query($connect, "SELECT DISTINCT figma_group FROM figma_review_notes ORDER BY figma_group");
while ($r3 && ($row = mysqli_fetch_assoc($r3))) $groups[] = $row['figma_group'];

include __DIR__ . '/includes/admin_header.php';
?>
<style>
.stat-card { background: var(--bs-body-bg); border-radius: 10px; padding: 16px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
.stat-num { font-size: 28px; font-weight: 700; }
.stat-label { color: var(--bs-secondary-color); font-size: 13px; }
.priority-high   { background: rgba(220,53,69,0.15); color: #dc3545; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
.priority-medium { background: rgba(255,193,7,0.15); color: #b88600; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
.priority-low    { background: rgba(108,117,125,0.15); color: #6c757d; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
.status-pending     { background: rgba(255,193,7,0.15); color: #b88600; padding: 2px 8px; border-radius: 10px; font-size: 11px; }
.status-in_progress { background: rgba(13,110,253,0.15); color: #0d6efd; padding: 2px 8px; border-radius: 10px; font-size: 11px; }
.status-done        { background: rgba(25,135,84,0.15); color: #198754; padding: 2px 8px; border-radius: 10px; font-size: 11px; }
.status-rejected    { background: rgba(108,117,125,0.15); color: #6c757d; padding: 2px 8px; border-radius: 10px; font-size: 11px; }
.fix-prompt { max-height: 80px; overflow: auto; font-size: 12px; background: var(--bs-tertiary-bg); padding: 6px 8px; border-radius: 4px; }
.claude-response { background: rgba(25,135,84,0.08); border-left: 3px solid #198754; padding: 8px 12px; margin-top: 8px; font-size: 12px; border-radius: 4px; }
</style>

<div class="container-fluid py-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">🎨 피그마 검수 메모 대시보드</h2>
    <div>
      <a href="figma_compare.html" target="_blank" class="btn btn-primary">
        <i class="bi bi-columns-gap"></i> 비교 페이지 열기
      </a>
      <button onclick="exportClaudeQueue()" class="btn btn-outline-success">
        <i class="bi bi-download"></i> Claude 작업 큐 다운로드(JSON)
      </button>
    </div>
  </div>

  <!-- 통계 카드 -->
  <div class="row g-3 mb-4">
    <div class="col-md col-6"><div class="stat-card text-center"><div class="stat-num"><?= $stats['total'] ?></div><div class="stat-label">전체 메모</div></div></div>
    <div class="col-md col-6"><div class="stat-card text-center"><div class="stat-num text-warning"><?= $stats['pending'] ?? 0 ?></div><div class="stat-label">대기중</div></div></div>
    <div class="col-md col-6"><div class="stat-card text-center"><div class="stat-num text-primary"><?= $stats['in_progress'] ?? 0 ?></div><div class="stat-label">진행중</div></div></div>
    <div class="col-md col-6"><div class="stat-card text-center"><div class="stat-num text-success"><?= $stats['done'] ?? 0 ?></div><div class="stat-label">완료</div></div></div>
    <div class="col-md col-6"><div class="stat-card text-center"><div class="stat-num text-danger"><?= $stats['high'] ?></div><div class="stat-label">긴급(high)</div></div></div>
    <div class="col-md col-6"><div class="stat-card text-center"><div class="stat-num text-danger"><?= $stats['wrong_match'] ?></div><div class="stat-label">매칭 오류</div></div></div>
  </div>

  <!-- 필터 -->
  <form method="get" class="card card-body mb-3">
    <div class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label small">상태</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">전체</option>
          <option value="pending"     <?= $filter_status==='pending'?'selected':'' ?>>대기중</option>
          <option value="in_progress" <?= $filter_status==='in_progress'?'selected':'' ?>>진행중</option>
          <option value="done"        <?= $filter_status==='done'?'selected':'' ?>>완료</option>
          <option value="rejected"    <?= $filter_status==='rejected'?'selected':'' ?>>반려</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small">우선순위</label>
        <select name="priority" class="form-select form-select-sm">
          <option value="">전체</option>
          <option value="high"   <?= $filter_priority==='high'?'selected':'' ?>>긴급</option>
          <option value="medium" <?= $filter_priority==='medium'?'selected':'' ?>>중간</option>
          <option value="low"    <?= $filter_priority==='low'?'selected':'' ?>>낮음</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small">피그마 그룹</label>
        <select name="group" class="form-select form-select-sm">
          <option value="">전체</option>
          <?php foreach ($groups as $g): ?>
            <option value="<?= htmlspecialchars($g) ?>" <?= $filter_group===$g?'selected':'' ?>><?= htmlspecialchars($g) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <div class="form-check pt-3">
          <input type="checkbox" name="wrong" value="1" class="form-check-input" id="chkWrong" <?= $filter_wrong?'checked':'' ?>>
          <label for="chkWrong" class="form-check-label small">매칭 오류만</label>
        </div>
      </div>
      <div class="col-md-2 text-end">
        <button class="btn btn-primary btn-sm w-100">필터 적용</button>
      </div>
    </div>
  </form>

  <!-- 목록 -->
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <strong>검수 메모 (<?= count($rows) ?>건)</strong>
    </div>
    <div class="card-body p-0">
      <?php if (empty($rows)): ?>
        <div class="text-center text-muted py-5">
          <i class="bi bi-inbox" style="font-size:48px"></i><br>
          저장된 메모가 없습니다. <a href="figma_compare.html" target="_blank">비교 페이지</a>에서 메모를 작성해 주세요.
        </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:50px">#</th>
              <th>피그마 화면</th>
              <th style="width:80px">상태</th>
              <th style="width:80px">우선순위</th>
              <th>현재 매핑</th>
              <th>검수 메모 / 수정 프롬프트</th>
              <th style="width:140px">업데이트</th>
              <th style="width:120px">액션</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
            <tr>
              <td>#<?= $row['id'] ?></td>
              <td>
                <strong><?= htmlspecialchars($row['figma_group']) ?> #<?= $row['figma_index'] ?></strong><br>
                <small class="text-muted"><code><?= htmlspecialchars($row['figma_filename']) ?></code></small>
                <?php if ($row['is_wrong_match']): ?>
                  <span class="badge bg-danger ms-1">매칭 오류</span>
                <?php endif; ?>
              </td>
              <td><span class="status-<?= $row['status'] ?>"><?= [
                  'pending'=>'대기중','in_progress'=>'진행중','done'=>'완료','rejected'=>'반려'
              ][$row['status']] ?? $row['status'] ?></span></td>
              <td><span class="priority-<?= $row['priority'] ?>"><?= ['high'=>'긴급','medium'=>'중간','low'=>'낮음'][$row['priority']] ?? $row['priority'] ?></span></td>
              <td>
                <?php if ($row['current_impl_url']): ?>
                  <a href="<?= htmlspecialchars($row['current_impl_url']) ?>" target="_blank" class="small">
                    <?= htmlspecialchars(basename($row['current_impl_url'])) ?>
                  </a>
                <?php endif; ?>
                <?php if ($row['suggested_impl_url']): ?>
                  <br><small class="text-success">→ 제안: <a href="<?= htmlspecialchars($row['suggested_impl_url']) ?>" target="_blank"><?= htmlspecialchars(basename($row['suggested_impl_url'])) ?></a></small>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($row['fix_prompt']): ?>
                  <div class="fix-prompt"><?= nl2br(htmlspecialchars($row['fix_prompt'])) ?></div>
                <?php endif; ?>
                <?php if ($row['claude_response']): ?>
                  <div class="claude-response">
                    <strong>🤖 Claude:</strong><br>
                    <?= nl2br(htmlspecialchars($row['claude_response'])) ?>
                  </div>
                <?php endif; ?>
              </td>
              <td><small><?= date('m/d H:i', strtotime($row['updated_at'])) ?></small></td>
              <td>
                <div class="btn-group btn-group-sm">
                  <button class="btn btn-outline-primary"  onclick="setStatus(<?= $row['id'] ?>, 'in_progress')" title="진행중으로"><i class="bi bi-play"></i></button>
                  <button class="btn btn-outline-success"  onclick="setStatus(<?= $row['id'] ?>, 'done')" title="완료"><i class="bi bi-check"></i></button>
                  <button class="btn btn-outline-secondary" onclick="setStatus(<?= $row['id'] ?>, 'rejected')" title="반려"><i class="bi bi-x"></i></button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
function setStatus(id, status) {
  if (!confirm('상태를 변경하시겠습니까?')) return;
  fetch('api_figma_note_status.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id: id, status: status }),
  })
  .then(r => r.json())
  .then(d => { if (d.ok) location.reload(); else alert('실패: ' + d.error); });
}

function exportClaudeQueue() {
  // pending + in_progress 메모를 JSON으로 다운로드 → Claude가 작업할 큐
  fetch('api_figma_note_list.php?status=pending')
    .then(r => r.json())
    .then(pending => {
      fetch('api_figma_note_list.php?status=in_progress')
        .then(r => r.json())
        .then(prog => {
          const all = [...(pending.list||[]), ...(prog.list||[])];
          const blob = new Blob([JSON.stringify(all, null, 2)], {type:'application/json'});
          const a = document.createElement('a');
          a.href = URL.createObjectURL(blob);
          a.download = 'figma_claude_queue_' + new Date().toISOString().slice(0,10) + '.json';
          a.click();
        });
    });
}
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
