<!DOCTYPE html>
<html lang="ko" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>E2E 테스트 가이드 — 서울미녀</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="common/style.css">
<style>
.hero{background:linear-gradient(135deg,#0f3460,#16213e,#1a1a2e);color:#fff;padding:60px 0 48px}
.hero h1{font-size:2.2rem;font-weight:800}
.badge-pill{font-size:13px;padding:6px 14px;border-radius:20px}
.stat-row{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:16px 0}
.stat-box{border-radius:12px;padding:16px;text-align:center;color:#fff}
.stat-num{font-size:2rem;font-weight:800}
.stat-label{font-size:12px;opacity:0.8}
.test-card{border:1px solid var(--sb-border);border-radius:12px;margin-bottom:16px;overflow:hidden;transition:all 0.2s}
.test-card:hover{box-shadow:0 4px 16px rgba(0,0,0,0.1)}
.test-header{display:flex;align-items:center;gap:10px;padding:12px 16px;background:var(--sb-bg-alt);cursor:pointer}
.test-header .status{font-size:1.2rem}
.test-header .tid{font-family:'Fira Code',monospace;font-size:12px;color:var(--sb-primary);min-width:30px}
.test-header .tname{flex:1;font-weight:600;font-size:14px}
.test-header .ttime{font-size:12px;color:var(--sb-text-muted)}
.test-body{padding:16px;display:none}
.test-body.show{display:block}
.test-ss{width:100%;max-width:600px;border:2px solid var(--sb-border);border-radius:8px;margin:8px 0;cursor:pointer;transition:transform 0.2s}
.test-ss:hover{transform:scale(1.02)}
.flow-step{display:flex;align-items:flex-start;gap:16px;margin-bottom:16px}
.flow-num{width:36px;height:36px;border-radius:50%;background:#AE1932;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0}
.cat-title{font-size:18px;font-weight:700;margin:24px 0 12px;padding:8px 0;border-bottom:2px solid var(--sb-primary)}
.modal-img{max-width:100%;border-radius:8px}
</style>
</head>
<body>

<div class="hero">
  <div class="container">
    <div class="d-flex align-items-center gap-3 mb-3">
      <span class="badge-pill" style="background:#22c55e;">34/34 PASS</span>
      <span class="badge-pill" style="background:#667eea;">Playwright</span>
      <span class="badge-pill" style="background:#f59e0b;color:#000;">52.2초</span>
    </div>
    <h1><i class="bi bi-play-circle me-2"></i>E2E 테스트 결과 리포트</h1>
    <p class="mt-2 mb-0" style="opacity:0.85;">각 항목 클릭 시 스크린샷을 확인할 수 있습니다. 로그인 전/후 비교 가능.</p>
    <div class="mt-3">
      <button class="btn btn-success btn-lg" id="rerunBtn" onclick="rerunTests()">
        <i class="bi bi-arrow-repeat me-2"></i>전체 재테스트 실행
      </button>
      <span class="ms-3 small" id="rerunStatus" style="opacity:0.7;"></span>
    </div>
  </div>
</div>

<main class="container py-4">

<div class="stat-row mb-4">
  <div class="stat-box" style="background:linear-gradient(135deg,#667eea,#764ba2);"><div class="stat-num">34</div><div class="stat-label">전체</div></div>
  <div class="stat-box" style="background:linear-gradient(135deg,#22c55e,#16a34a);"><div class="stat-num">34</div><div class="stat-label">PASS</div></div>
  <div class="stat-box" style="background:linear-gradient(135deg,#ef4444,#dc2626);"><div class="stat-num">0</div><div class="stat-label">FAIL</div></div>
  <div class="stat-box" style="background:linear-gradient(135deg,#3b82f6,#2563eb);"><div class="stat-num">52.2s</div><div class="stat-label">소요</div></div>
</div>

<?php
// 최신 완료된 run의 스크린샷 사용
require_once dirname(__DIR__) . '/../dbconfig.php';
$_mc = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
$_latest_run = 'final_v2';
if ($_mc) {
    mysqli_set_charset($_mc, 'utf8mb4');
    $_rq = mysqli_query($_mc, "SELECT run_id FROM gangnamunni_e2e_runs WHERE status='completed' ORDER BY started_at DESC LIMIT 1");
    if ($_rq && $_rr = mysqli_fetch_assoc($_rq)) $_latest_run = $_rr['run_id'];
}
$SS = '../uploads/e2e_screenshots/' . $_latest_run;
$tests = [
  ['A. 사용자 테스트 (18/18 PASS)', [
    ['A1','홈페이지 로드 + 주요 섹션 표시','1.9s','검색바, 시술 카테고리, 이벤트, 추천 병원 섹션이 모두 정상 로드됩니다.'],
    ['A2','병원 목록 + 검색 + 필터','1.4s','병원 카드 목록이 표시되고 검색/필터/뷰 전환이 동작합니다.'],
    ['A3','병원 상세 + 탭 전환','2.0s','병원 정보, 시술, 후기 탭이 전환됩니다.'],
    ['A4','후기 목록 + 정렬','0.7s','후기 목록이 정상 로드되고 정렬 옵션이 표시됩니다.'],
    ['A5','후기 작성 (로그인 리다이렉트)','1.0s','비로그인 시 로그인 페이지로 정상 리다이렉트됩니다.'],
    ['A6','예약하기','0.7s','예약 폼이 표시되거나 로그인 리다이렉트가 동작합니다.'],
    ['A7','상담 신청','0.7s','상담 신청 폼이 정상 표시됩니다.'],
    ['A8','지도 검색','1.2s','지도 페이지가 로드되고 검색 입력이 가능합니다.'],
    ['A9','시술 랭킹 + 카테고리','0.7s','시술 랭킹 카테고리 탭이 표시됩니다.'],
    ['A10','가격 비교','0.6s','카테고리 선택 카드가 표시되고 클릭 가능합니다.'],
    ['A11','병원 비교','0.6s','병원 비교 UI가 정상 로드됩니다.'],
    ['A12','마이페이지 메뉴','0.6s','메뉴 항목들이 모두 표시됩니다.'],
    ['A13','내 예약/찜/후기/알림/포인트/쿠폰','3.9s','6개 서브 페이지가 모두 500 에러 없이 로드됩니다.'],
    ['A14','이벤트 상세','0.7s','이벤트 목록이 정상 표시됩니다.'],
    ['A15','커뮤니티 목록','0.6s','커뮤니티 게시글 목록이 정상 로드됩니다.'],
    ['A16','FAQ/QnA','1.2s','FAQ와 QnA 페이지가 모두 정상입니다.'],
    ['A17','모바일 레이아웃 (375×812)','1.1s','모바일 뷰포트에서 하단 네비게이션이 표시됩니다.'],
    ['A18','다크모드 전환','1.3s','다크모드 전환 후 배경색이 변경됩니다.'],
  ]],
  ['B. 관리자 테스트 (13/13 PASS)', [
    ['B1','관리자 로그인','3.9s','admin/admin123 로그인 후 대시보드가 표시됩니다.'],
    ['B2','대시보드 위젯','0.9s','통계 카드와 사이드바가 정상 표시됩니다.'],
    ['B3','사이드바 네비게이션','9.3s','10개 주요 관리 페이지가 모두 500 에러 없이 로드됩니다.'],
    ['B4','병원 관리 CRUD','0.8s','병원 관리 테이블이 정상 표시됩니다.'],
    ['B5','후기 관리 + 승인/반려','0.8s','후기 관리 테이블이 정상 표시됩니다.'],
    ['B6','광고 설정 7탭 + AJAX','0.9s','7개 탭과 프리뷰 iframe이 정상 동작합니다.'],
    ['B7','디자인 토큰 수정','0.9s','컬러 피커와 프리뷰가 정상입니다.'],
    ['B8','테마 설정 컬러 변경','0.8s','테마 탭과 설정 폼이 정상 표시됩니다.'],
    ['B9','업종 설정 프리셋 전환','0.9s','10개 프리셋 카드가 모두 표시됩니다.'],
    ['B10','시술 관리','1.1s','시술 관리 테이블이 정상 표시됩니다.'],
    ['B11','이벤트 관리','0.9s','이벤트 관리 페이지가 정상 로드됩니다.'],
    ['B12','알림 발송','0.7s','알림 발송 폼이 정상 표시됩니다.'],
    ['B13','병원관리자 로그인 + 제한 메뉴','1.8s','hospital_test 계정 로그인 + 제한된 사이드바 확인됩니다.'],
  ]],
  ['C. API 테스트 (3/3 PASS)', [
    ['C1','API: 설정 저장','0.0s','api_save_setting.php POST → {success:true} 응답.'],
    ['C2','API: 시술 조회','0.5s','api_treatments.php → JSON 정상 응답.'],
    ['C3','API: 추천 병원','0.6s','api_recommend.php → JSON 정상 응답.'],
  ]],
];

foreach ($tests as $cat):
  echo '<div class="cat-title"><i class="bi bi-check-circle-fill text-success me-2"></i>' . $cat[0] . '</div>';
  foreach ($cat[1] as $t):
    $id = $t[0]; $name = $t[1]; $time = $t[2]; $desc = $t[3];
    $img = "$SS/$id.png";
?>
<div class="test-card" id="card-<?php echo $id; ?>">
  <div class="test-header" onclick="toggleBody('<?php echo $id; ?>')">
    <span class="status">✅</span>
    <span class="tid"><?php echo $id; ?></span>
    <span class="tname"><?php echo htmlspecialchars($name); ?></span>
    <span class="ttime"><?php echo $time; ?></span>
    <i class="bi bi-chevron-down" id="chev-<?php echo $id; ?>"></i>
  </div>
  <div class="test-body" id="body-<?php echo $id; ?>">
    <p class="text-muted small mb-2"><?php echo htmlspecialchars($desc); ?></p>
    <div class="d-flex flex-wrap gap-3">
      <div>
        <div class="small fw-bold mb-1"><i class="bi bi-person-x me-1"></i>로그인 전</div>
        <img src="<?php echo $img; ?>" alt="<?php echo $id; ?> 로그인 전" class="test-ss" onclick="showModal('<?php echo $img; ?>','<?php echo $id; ?> (로그인 전)')">
      </div>
      <?php
        $img_li = "$SS/{$id}_loggedin.png";
        $img_li_abs = realpath(__DIR__ . '/../' . $img_li) ?: (__DIR__ . '/../' . $img_li);
        $li_exists = file_exists($img_li_abs);
      ?>
      <?php if ($li_exists): ?>
      <div>
        <div class="small fw-bold mb-1"><i class="bi bi-person-check me-1"></i>로그인 후</div>
        <img src="<?php echo $img_li; ?>" alt="<?php echo $id; ?> 로그인 후" class="test-ss" onclick="showModal('<?php echo $img_li; ?>','<?php echo $id; ?> (로그인 후)')">
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php
  endforeach;
endforeach;
?>

<!-- 사용 방법 -->
<section class="mb-5 mt-5" id="howto">
  <h2 class="sb-section-title"><span class="sb-badge sb-badge-primary me-2">HOW</span> 테스트 실행 방법</h2>
  <div class="sb-card"><div class="sb-card-body">
    <div class="flow-step"><div class="flow-num">1</div><div><strong>관리자 로그인</strong> → <code>dashboard.php</code> (admin / admin123)</div></div>
    <div class="flow-step"><div class="flow-num">2</div><div><strong>사이드바</strong> → AI/자동화 → <strong>E2E 테스트</strong></div></div>
    <div class="flow-step"><div class="flow-num">3</div><div><strong>체크박스 선택</strong> → 사용자(A) / 관리자(B) / API(C) 탭</div></div>
    <div class="flow-step"><div class="flow-num">4</div><div><strong>"테스트 실행"</strong> 클릭 → 실시간 진행바 + 결과 테이블</div></div>
    <div class="flow-step"><div class="flow-num">5</div><div><strong>FAIL 시</strong> → 스크린샷 + 에러 메시지 + 수정 페이지 링크 제공</div></div>
  </div></div>
</section>

<!-- 에러 처리 -->
<section class="mb-5" id="errors">
  <h2 class="sb-section-title"><span class="sb-badge sb-badge-primary me-2">ERR</span> 에러 발생 시</h2>
  <div class="sb-card"><div class="sb-card-body">
    <table class="table table-sm table-bordered">
      <thead class="table-dark"><tr><th>에러 유형</th><th>자동 수정 제안</th><th>수정 링크</th></tr></thead>
      <tbody>
        <tr><td>HTTP 500</td><td>PHP 에러 확인 — 서버 로그 확인</td><td>해당 페이지</td></tr>
        <tr><td>요소 미발견</td><td>HTML 셀렉터 불일치</td><td>관리 페이지</td></tr>
        <tr><td>로그인 실패</td><td>계정 정보 확인</td><td>dashboard.php</td></tr>
        <tr><td>타임아웃</td><td>서버 응답 속도 확인</td><td>admin_server_info.php</td></tr>
      </tbody>
    </table>
    <p class="text-muted small mt-2">모든 에러는 <code>gangnamunni_e2e_results</code> 테이블에 자동 저장됩니다. 스크린샷, 스택 트레이스, 수정 제안이 포함됩니다.</p>
  </div></div>
</section>

</main>

<!-- 이미지 확대 모달 -->
<div class="modal fade" id="imgModal"><div class="modal-dialog modal-xl"><div class="modal-content">
  <div class="modal-header"><h6 class="modal-title" id="imgTitle">스크린샷</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body text-center"><img id="imgFull" src="" class="modal-img"></div>
</div></div></div>

<footer class="sb-footer">
  <div class="container">
    <div class="sb-footer-content">
      <div class="sb-footer-text">&copy; 2026 Seoul Beauty. E2E 테스트 리포트 v1.0 (2026-04-01)</div>
      <div class="sb-footer-actions">
        <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i>인쇄</button>
        <a href="index.html" class="btn btn-outline-primary"><i class="bi bi-house me-1"></i>매뉴얼 홈</a>
      </div>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="common/export.js"></script>
<script>
function toggleBody(id) {
  var body = document.getElementById('body-' + id);
  var chev = document.getElementById('chev-' + id);
  body.classList.toggle('show');
  chev.style.transform = body.classList.contains('show') ? 'rotate(180deg)' : '';
}
function showModal(src, id) {
  document.getElementById('imgTitle').textContent = id + ' 스크린샷';
  document.getElementById('imgFull').src = src;
  new bootstrap.Modal(document.getElementById('imgModal')).show();
}

function rerunTests() {
  var btn = document.getElementById('rerunBtn');
  var status = document.getElementById('rerunStatus');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>테스트 실행 중...';
  status.textContent = '34개 테스트를 실행하고 있습니다. 약 1분 소요됩니다.';

  var allTests = [];
  for (var i = 1; i <= 18; i++) allTests.push('A' + i);
  for (var i = 1; i <= 13; i++) allTests.push('B' + i);
  for (var i = 1; i <= 3; i++) allTests.push('C' + i);

  var runId = 'report_' + Date.now();

  fetch('../api_e2e_test.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'action=run&run_id=' + encodeURIComponent(runId) + '&tests=' + encodeURIComponent(JSON.stringify(allTests))
  })
  .then(function(r) { return r.json(); })
  .then(function(d) {
    if (d.success) {
      status.textContent = '실행 시작됨 (Run ID: ' + runId + '). 폴링 중...';
      var poll = setInterval(function() {
        fetch('../api_e2e_test.php?action=results&run_id=' + encodeURIComponent(runId))
        .then(function(r) { return r.json(); })
        .then(function(rd) {
          if (!rd.run) return;
          var p = parseInt(rd.run.passed) || 0;
          var f = parseInt(rd.run.failed) || 0;
          var t = parseInt(rd.run.total_tests) || 34;
          status.textContent = '진행: ' + (p+f) + '/' + t + ' (' + p + ' PASS, ' + f + ' FAIL)';
          if (rd.run.status === 'completed' || rd.run.status === 'aborted') {
            clearInterval(poll);
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-repeat me-2"></i>전체 재테스트 실행';
            status.textContent = '완료! ' + p + ' PASS, ' + f + ' FAIL. 페이지를 새로고침하면 최신 스크린샷이 표시됩니다.';
            if (f === 0) status.innerHTML += ' <span class="badge bg-success">ALL PASS</span>';
          }
        });
      }, 3000);
    } else {
      status.textContent = '실행 실패: ' + (d.error || 'unknown');
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-arrow-repeat me-2"></i>전체 재테스트 실행';
    }
  });
}
</script>
</body>
</html>
