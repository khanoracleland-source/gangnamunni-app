<?php
/**
 * PHP 코드 문법 검사 — 관리 대시보드
 * 사이드바: 개발 도구 → 코드 문법 검사
 */

$admin_page_title = 'PHP 코드 문법 검사';
$admin_current    = 'code_check';

session_start();
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/hospital_filter.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die('DB 연결 실패');
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

// OOP 래퍼 (준비구문용)
$db = new mysqli(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, (int)G5_MYSQL_PORT);
$db->set_charset('utf8mb4');

require_once __DIR__ . '/includes/admin_header.php';
require_admin_login();
if (!in_array($_SESSION['ad_admin_role'] ?? '', ['super_admin', 'admin'], true)) {
    http_response_code(403);
    exit('Access denied');
}

// ── AJAX 핸들러 ──────────────────────────────────────────────────
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'run_check') {
    // 서버에서 run_check.php 직접 실행
    $runner = dirname(__DIR__) . '/code_check/run_check.php';
    $mode   = $_POST['no_ai'] ?? false ? '--no-ai' : '';
    $cmd    = PHP_BINARY . ' ' . escapeshellarg($runner) . ' --mode=db ' . $mode . ' 2>&1';
    $out    = shell_exec('cd ' . escapeshellarg(dirname($runner)) . ' && ' . $cmd);
    echo json_encode(['ok' => true, 'output' => $out]);
    $db->close(); exit;
}

if ($action === 'ai_recheck') {
    // 특정 오류 AI 재분석
    $err_id = (int)($_POST['err_id'] ?? 0);
    if (!$err_id) { echo json_encode(['ok' => false]); $db->close(); exit; }

    $row = $db->query("SELECT * FROM gangnamunni_syntax_errors WHERE id=$err_id LIMIT 1")->fetch_assoc();
    if (!$row) { echo json_encode(['ok' => false, 'msg' => '오류 없음']); $db->close(); exit; }

    // lib_ai 로드
    $lib = dirname(__DIR__) . '/code_check/';
    require_once $lib . 'config.php';
    require_once $lib . 'lib_ai.php';

    $err = ['file' => $row['file_path'], 'line_no' => $row['line_no'],
            'error_msg' => $row['error_msg'], 'context' => $row['context_code']];
    $ai = cc_ai_analyze_error($err, $db);

    if ($ai['ai_used']) {
        $stmt = $db->prepare(
            "UPDATE gangnamunni_syntax_errors
             SET ai_analysis=?, ai_fix_suggestion=?, ai_model=?, status='ai_checked', ai_checked_at=NOW()
             WHERE id=? LIMIT 1"
        );
        $model = $ai['ai_model'] ?? '';
        $stmt->bind_param('sssi', $ai['analysis'], $ai['fix_suggestion'], $model, $err_id);
        $stmt->execute(); $stmt->close();
    }
    echo json_encode(['ok' => $ai['ai_used'], 'analysis' => $ai['analysis'], 'fix' => $ai['fix_suggestion']]);
    $db->close(); exit;
}

if ($action === 'update_status') {
    $err_id = (int)($_POST['err_id'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['new','ai_checked','fixed','ignored'])
              ? $_POST['status'] : '';
    if ($err_id && $status) {
        $stmt = $db->prepare("UPDATE gangnamunni_syntax_errors SET status=? WHERE id=? LIMIT 1");
        $stmt->bind_param('si', $status, $err_id); $stmt->execute(); $stmt->close();
    }
    echo json_encode(['ok' => true]);
    $db->close(); exit;
}

// ── 데이터 로드 ──────────────────────────────────────────────────
// 최근 실행 이력 10개
$runs = [];
$r = $db->query("SELECT * FROM gangnamunni_syntax_runs ORDER BY id DESC LIMIT 10");
if ($r) while ($row = $r->fetch_assoc()) $runs[] = $row;

// 현재 오류 목록 (status != fixed, ignored)
$run_filter = (int)($_GET['run_id'] ?? 0);
$status_filter = $_GET['status'] ?? '';
$where = "WHERE 1=1";
if ($run_filter)    $where .= " AND e.run_id=$run_filter";
if ($status_filter) $where .= " AND e.status='" . $db->real_escape_string($status_filter) . "'";
else                $where .= " AND e.status != 'ignored'";

$errors = [];
$er = $db->query("SELECT e.*, r.scanned_at as run_at FROM gangnamunni_syntax_errors e
                  LEFT JOIN gangnamunni_syntax_runs r ON e.run_id=r.id
                  $where ORDER BY e.created_at DESC LIMIT 100");
if ($er) while ($row = $er->fetch_assoc()) $errors[] = $row;

// KPI
$kpi = $db->query("SELECT
    COUNT(*) AS total,
    SUM(status='new') AS cnt_new,
    SUM(status='ai_checked') AS cnt_ai,
    SUM(status='fixed') AS cnt_fixed
    FROM gangnamunni_syntax_errors WHERE status != 'ignored'")->fetch_assoc();
$last_run = $runs[0] ?? null;
?>
<div class="container-fluid py-3">

  <!-- KPI -->
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card text-center border-0 bg-body-secondary">
        <div class="card-body py-3">
          <div class="fs-1 fw-bold text-<?= ($kpi['total']??0) == 0 ? 'success' : 'danger' ?>"><?= $kpi['total']??0 ?></div>
          <div class="small text-muted">전체 오류 (미수정)</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center border-0 bg-body-secondary">
        <div class="card-body py-3">
          <div class="fs-1 fw-bold text-warning"><?= $kpi['cnt_new']??0 ?></div>
          <div class="small text-muted">신규 (AI 분석 전)</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center border-0 bg-body-secondary">
        <div class="card-body py-3">
          <div class="fs-1 fw-bold text-info"><?= $kpi['cnt_ai']??0 ?></div>
          <div class="small text-muted">AI 분석 완료</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center border-0 bg-body-secondary">
        <div class="card-body py-3">
          <div class="fs-1 fw-bold text-success"><?= $last_run ? $last_run['total_files'] : '-' ?></div>
          <div class="small text-muted">최근 검사 파일 수</div>
        </div>
      </div>
    </div>
  </div>

  <!-- 탭 -->
  <ul class="nav nav-tabs mb-3" id="ccTabs">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-errors"><i class="bi bi-bug"></i> 오류 목록</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-runs"><i class="bi bi-clock-history"></i> 실행 이력</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-run"><i class="bi bi-play-circle"></i> 검사 실행</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-guide"><i class="bi bi-book"></i> 이식 가이드</button></li>
  </ul>

  <div class="tab-content">

    <!-- ① 오류 목록 -->
    <div class="tab-pane fade show active" id="tab-errors">
      <div class="d-flex gap-2 mb-3 flex-wrap align-items-center">
        <span class="fw-bold">필터:</span>
        <a href="?status=" class="btn btn-sm <?= !$status_filter ? 'btn-primary' : 'btn-outline-secondary' ?>">전체</a>
        <a href="?status=new" class="btn btn-sm <?= $status_filter==='new' ? 'btn-warning' : 'btn-outline-warning' ?>">신규</a>
        <a href="?status=ai_checked" class="btn btn-sm <?= $status_filter==='ai_checked' ? 'btn-info' : 'btn-outline-info' ?>">AI 분석됨</a>
        <a href="?status=fixed" class="btn btn-sm <?= $status_filter==='fixed' ? 'btn-success' : 'btn-outline-success' ?>">수정완료</a>
      </div>
      <?php if (!$errors): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> 오류 없음! 🎉</div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle" style="font-size:13px">
          <thead class="table-dark">
            <tr>
              <th style="width:40px">#</th>
              <th>파일명</th>
              <th style="width:60px">줄</th>
              <th>오류 메시지</th>
              <th>AI 분석</th>
              <th style="width:100px">상태</th>
              <th style="width:120px">액션</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($errors as $i => $e): ?>
            <tr id="row<?= $e['id'] ?>">
              <td class="text-muted"><?= $i+1 ?></td>
              <td>
                <span class="badge bg-secondary" title="<?= htmlspecialchars($e['file_path']) ?>">
                  <?= htmlspecialchars($e['file_name']) ?>
                </span>
              </td>
              <td><?= $e['line_no'] ?: '-' ?></td>
              <td>
                <div style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                     title="<?= htmlspecialchars($e['error_msg']) ?>">
                  <?= htmlspecialchars($e['error_msg']) ?>
                </div>
                <?php if ($e['context_code']): ?>
                  <a href="#" class="small text-muted" onclick="showCtx(<?= $e['id'] ?>);return false">코드 보기</a>
                  <pre id="ctx<?= $e['id'] ?>" style="display:none;font-size:11px;background:#1a1a1a;color:#e0e0e0;padding:8px;border-radius:6px;margin-top:6px;white-space:pre-wrap"><?= htmlspecialchars($e['context_code']) ?></pre>
                <?php endif ?>
              </td>
              <td>
                <?php if ($e['ai_analysis']): ?>
                  <div style="max-width:260px;font-size:12px">
                    <div class="text-info"><?= nl2br(htmlspecialchars(mb_substr($e['ai_analysis'],0,120))) ?>…</div>
                    <?php if ($e['ai_fix_suggestion']): ?>
                      <div class="text-success mt-1" style="font-size:11px">💡 <?= nl2br(htmlspecialchars(mb_substr($e['ai_fix_suggestion'],0,100))) ?></div>
                    <?php endif ?>
                  </div>
                <?php else: ?>
                  <button class="btn btn-xs btn-outline-info py-0 px-2" onclick="aiRecheck(<?= $e['id'] ?>)">
                    <i class="bi bi-robot"></i> AI 분석
                  </button>
                <?php endif ?>
              </td>
              <td>
                <select class="form-select form-select-sm" onchange="updateStatus(<?= $e['id'] ?>,this.value)">
                  <option value="new" <?= $e['status']==='new'?'selected':'' ?>>신규</option>
                  <option value="ai_checked" <?= $e['status']==='ai_checked'?'selected':'' ?>>AI완료</option>
                  <option value="fixed" <?= $e['status']==='fixed'?'selected':'' ?>>수정완료</option>
                  <option value="ignored" <?= $e['status']==='ignored'?'selected':'' ?>>무시</option>
                </select>
              </td>
              <td>
                <button class="btn btn-xs btn-outline-secondary py-0 px-1" title="AI 재분석" onclick="aiRecheck(<?= $e['id'] ?>)">
                  <i class="bi bi-arrow-clockwise"></i>
                </button>
              </td>
            </tr>
          <?php endforeach ?>
          </tbody>
        </table>
      </div>
      <?php endif ?>
    </div>

    <!-- ② 실행 이력 -->
    <div class="tab-pane fade" id="tab-runs">
      <table class="table table-sm align-middle" style="font-size:13px">
        <thead class="table-dark">
          <tr><th>#</th><th>일시</th><th>전체</th><th>정상</th><th>오류</th><th>PHP</th><th>소스</th><th>브랜치</th><th>커밋</th></tr>
        </thead>
        <tbody>
        <?php foreach ($runs as $r): ?>
          <tr>
            <td><?= $r['id'] ?></td>
            <td><?= $r['scanned_at'] ?></td>
            <td><?= $r['total_files'] ?></td>
            <td class="text-success"><?= $r['ok_files'] ?></td>
            <td class="<?= $r['error_count']>0?'text-danger fw-bold':'' ?>"><?= $r['error_count'] ?></td>
            <td><?= $r['php_version'] ?></td>
            <td><span class="badge bg-secondary"><?= $r['run_source'] ?></span></td>
            <td><?= htmlspecialchars($r['git_branch']) ?></td>
            <td><code style="font-size:11px"><?= substr($r['git_commit'],0,8) ?></code></td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>

    <!-- ③ 검사 실행 -->
    <div class="tab-pane fade" id="tab-run">
      <div class="card" style="max-width:700px">
        <div class="card-body">
          <h5 class="card-title"><i class="bi bi-play-circle"></i> 서버에서 즉시 검사 실행</h5>
          <p class="text-muted small">서버의 <code>_server_files/</code> 전체 PHP 파일을 <code>php -l</code>로 검사합니다. 결과는 자동으로 DB에 저장됩니다.</p>
          <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" id="noAI">
            <label class="form-check-label" for="noAI">AI 분석 건너뜀 (빠른 검사)</label>
          </div>
          <button class="btn btn-primary" onclick="runCheck()">
            <i class="bi bi-play-fill"></i> 검사 시작
          </button>
          <div id="runOutput" class="mt-3" style="display:none">
            <pre id="runLog" style="background:#111;color:#e0e0e0;padding:14px;border-radius:8px;font-size:12px;max-height:400px;overflow-y:auto;white-space:pre-wrap"></pre>
          </div>
        </div>
      </div>

      <div class="card mt-3" style="max-width:700px">
        <div class="card-body">
          <h5><i class="bi bi-terminal"></i> 로컬/다른 서버에서 실행</h5>
          <pre style="background:#111;color:#e0e0e0;padding:12px;border-radius:8px;font-size:12px"># 이 PC (XAMPP)
"C:\xampp\php\php.exe" code_check\run_check.php

# Linux 서버
php code_check/run_check.php

# API 모드 (GitHub Actions)
php code_check/run_check.php --mode=api

# AI 없이 빠른 검사
php code_check/run_check.php --mode=stdout --no-ai

# 다른 프로젝트 경로 지정
php code_check/run_check.php --target=/var/www/html/my-project</pre>
        </div>
      </div>
    </div>

    <!-- ④ 이식 가이드 -->
    <div class="tab-pane fade" id="tab-guide">
      <div class="card" style="max-width:800px">
        <div class="card-body" style="font-size:14px;line-height:1.9">
          <h5><i class="bi bi-boxes"></i> 다른 프로젝트로 이식 방법</h5>

          <h6 class="fw-bold mt-4">1단계 — code_check/ 폴더 복사</h6>
          <pre style="background:#111;color:#e0e0e0;padding:10px;border-radius:6px;font-size:12px">cp -r code_check/ /path/to/new-project/</pre>

          <h6 class="fw-bold mt-4">2단계 — config.php 수정</h6>
          <table class="table table-sm table-bordered">
            <tr><th>설정</th><th>의미</th><th>기본값</th></tr>
            <tr><td><code>CC_SCAN_PATH</code></td><td>검사할 PHP 파일 루트 경로</td><td><code>../_server_files</code></td></tr>
            <tr><td><code>CC_DB_HOST/NAME/USER/PASS</code></td><td>DB 연결 정보</td><td>현 서버 값</td></tr>
            <tr><td><code>CC_API_URL</code></td><td>결과 전송 API URL</td><td>현 도메인</td></tr>
            <tr><td><code>CC_API_KEY</code></td><td>API 인증 키</td><td>임의 변경</td></tr>
            <tr><td><code>CC_AI_PROVIDER</code></td><td>AI 엔진 (anthropic/openai/skip)</td><td>anthropic</td></tr>
          </table>

          <h6 class="fw-bold mt-4">3단계 — DB 테이블 생성</h6>
          <pre style="background:#111;color:#e0e0e0;padding:10px;border-radius:6px;font-size:12px">mysql -u root -p new_db_name &lt; code_check/install_db.sql</pre>

          <h6 class="fw-bold mt-4">4단계 — 관리 페이지 연결</h6>
          <p><code>admin_code_check.php</code>를 새 프로젝트 관리자 폴더에 복사 후<br>
          사이드바 메뉴에 항목 추가 (<code>admin_header.php</code>의 <code>dev_tools</code> 그룹)</p>

          <div class="alert alert-info mt-3">
            <b>환경변수로도 설정 가능</b> (Docker/CI/CD 친화적)<br>
            <code>CC_SCAN_PATH=/app CC_DB_HOST=db CC_DB_NAME=mydb php run_check.php</code>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /tab-content -->
</div>

<script>
function showCtx(id) {
    const el = document.getElementById('ctx'+id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

function aiRecheck(errId) {
    const row = document.getElementById('row'+errId);
    const fd = new FormData();
    fd.append('action','ai_recheck');
    fd.append('err_id', errId);
    fetch('admin_code_check.php', {method:'POST', body:fd})
        .then(r=>r.json())
        .then(d=>{
            if (d.ok) {
                location.reload();
            } else {
                alert('AI 분석 실패: ' + (d.msg||''));
            }
        });
}

function updateStatus(errId, status) {
    const fd = new FormData();
    fd.append('action','update_status');
    fd.append('err_id', errId);
    fd.append('status', status);
    fetch('admin_code_check.php', {method:'POST', body:fd}).then(r=>r.json());
}

function runCheck() {
    const noAI = document.getElementById('noAI').checked;
    const fd = new FormData();
    fd.append('action','run_check');
    if (noAI) fd.append('no_ai','1');

    document.getElementById('runOutput').style.display='block';
    document.getElementById('runLog').textContent='실행 중...';

    const btn = event.target;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 검사 중...';

    fetch('admin_code_check.php', {method:'POST', body:fd})
        .then(r=>r.json())
        .then(d=>{
            document.getElementById('runLog').textContent = d.output || '(출력 없음)';
            btn.disabled=false; btn.innerHTML='<i class="bi bi-play-fill"></i> 검사 시작';
            setTimeout(()=>location.reload(), 2000);
        })
        .catch(()=>{
            document.getElementById('runLog').textContent='오류 발생';
            btn.disabled=false; btn.innerHTML='<i class="bi bi-play-fill"></i> 검사 시작';
        });
}
</script>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
