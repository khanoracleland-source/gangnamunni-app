<?php
// ============================================================================
// [인수인계 대시보드 - 2026-06-12] 클라이언트 인수인계용 통합 대시보드
// 원인 : 6/12 긴급 인계 — 인수자가 로그인 후 인계 자료를 한 화면에서 확인 필요
// 구성 : 좌측 섹션 메뉴 + 우측 내용 + 상단 주간/야간 토글 + 로그아웃
// 접근 : 관리자 로그인 필수 (require_admin_login)
// ============================================================================
session_start();

$admin_page_title = '인수인계 대시보드';
$admin_current = 'handover_dashboard';

require_once __DIR__ . '/dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die('DB 연결 실패: ' . mysqli_connect_error());
mysqli_set_charset($connect, 'utf8mb4');

if (function_exists('loadThemeSettings')) loadThemeSettings($connect);
if (function_exists('require_admin_login')) require_admin_login();

$admin_name = $_SESSION['ad_admin_name'] ?? $_SESSION['ad_admin_id'] ?? '관리자';
mysqli_close($connect);
?>
<!DOCTYPE html>
<html lang="ko" data-bs-theme="light">
<head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>인수인계 대시보드 — 서울미녀</title>
<script>
// 다크모드 anti-flash
(function(){
  var t = localStorage.getItem('handover_theme') || 'light';
  document.documentElement.setAttribute('data-bs-theme', t);
})();
</script>
<style>
:root {
  --primary: #0891b2;
  --bg: #f5f6f8; --card: #ffffff; --text: #222; --muted: #777;
  --border: #e4e5ea; --side-bg: #15323b; --side-text: #c4d8de; --side-active: #0891b2;
  --th-bg: #fafafa; --code-bg: #f3f3f5; --note-bg: #effdf3; --warn-bg: #fffaef;
}
[data-bs-theme="dark"] {
  --bg: #14171c; --card: #1d2127; --text: #e3e6eb; --muted: #9aa3af;
  --border: #2e333b; --side-bg: #11141a; --side-text: #9aa3af;
  --th-bg: #23272f; --code-bg: #2a2e36; --note-bg: #15291c; --warn-bg: #2b2415;
}
/* 다크모드 방어: 인라인 하드코딩 라이트 배경을 자동 보정 (흰배경+밝은글자 깨짐 방지) */
[data-bs-theme="dark"] [style*="background:#f"],
[data-bs-theme="dark"] [style*="background: #f"],
[data-bs-theme="dark"] [style*="background:#e"],
[data-bs-theme="dark"] [style*="background: #e"],
[data-bs-theme="dark"] [style*="background:#d"] { background-color: rgba(255,255,255,.055) !important; }
/* 인라인 어두운 텍스트(짙은 남색/회색/청록)가 어두운 카드 위에서 안 보이는 경우만 보정 */
[data-bs-theme="dark"] [style*="color:#15"],
[data-bs-theme="dark"] [style*="color:#0f"],
[data-bs-theme="dark"] [style*="color:#22"],
[data-bs-theme="dark"] [style*="color:#33"],
[data-bs-theme="dark"] [style*="color:#2c"],
[data-bs-theme="dark"] [style*="color: #15"] { color: var(--text) !important; }
* { box-sizing: border-box; }
body { margin:0; font-family:'Pretendard','Noto Sans KR',-apple-system,sans-serif; background:var(--bg); color:var(--text); }
.topbar {
  position:fixed; top:0; left:0; right:0; height:56px; z-index:100;
  background:var(--primary); color:#fff; display:flex; align-items:center;
  padding:0 16px; gap:12px;
}
.topbar .brand { font-weight:700; font-size:16px; flex:1; }
.topbar .brand small { opacity:.8; font-weight:400; margin-left:8px; }
.topbar button, .topbar a.btn {
  background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.3); color:#fff;
  border-radius:8px; padding:6px 14px; font-size:13px; cursor:pointer; text-decoration:none;
}
.topbar button:hover, .topbar a.btn:hover { background:rgba(255,255,255,.28); }
.layout { display:flex; padding-top:56px; min-height:100vh; }
.sidebar {
  width:230px; flex-shrink:0; background:var(--side-bg); color:var(--side-text);
  position:fixed; top:56px; bottom:0; overflow-y:auto; padding:14px 0;
}
.sidebar a {
  display:block; padding:11px 20px; color:var(--side-text); text-decoration:none;
  font-size:14px; border-left:3px solid transparent;
}
.sidebar a:hover { background:rgba(255,255,255,.06); color:#fff; }
.sidebar a.active { background:rgba(174,25,50,.25); color:#fff; border-left-color:var(--side-active); font-weight:600; }
.content { margin-left:230px; flex:1; padding:24px clamp(20px, 3vw, 48px) 80px; max-width:1600px; width:100%; }
@media (min-width:1900px){ .content{ max-width:none; padding-right:clamp(48px, 6vw, 140px); } }
.section { display:none; }
.section.active { display:block; }
    .badge-new{display:inline-block;background:#1f9d55;color:#fff;font-size:11px;font-weight:700;padding:2px 9px;border-radius:6px;margin-bottom:8px}
    .warn-box{background:var(--warn-bg);border:1px solid #d9a441;border-left:4px solid #e6a817;border-radius:8px;padding:12px 16px;margin:14px 0;font-size:13.5px;line-height:1.8;color:var(--text)}
.card {
  background:var(--card); border-radius:14px; padding:24px 26px;
  border:1px solid var(--border); margin-bottom:16px;
}
h1 { color:var(--primary); font-size:22px; margin:0 0 4px; }
h2 { font-size:17px; color:var(--primary); border-left:4px solid var(--primary); padding-left:10px; margin:0 0 14px; }
h3 { font-size:14.5px; margin:18px 0 8px; }
.meta { color:var(--muted); font-size:13px; margin-bottom:16px; }
table { width:100%; border-collapse:collapse; margin:10px 0; font-size:13.5px; }
th, td { border:1px solid var(--border); padding:8px 11px; text-align:left; vertical-align:top; }
th { background:var(--th-bg); font-weight:600; white-space:nowrap; }
code { background:var(--code-bg); padding:2px 7px; border-radius:4px; font-family:Consolas,monospace; font-size:12.5px; color:#c7254e; }
[data-bs-theme="dark"] code { color:#ff8da3; }
.badge { display:inline-block; padding:2px 10px; border-radius:12px; font-size:11px; font-weight:600; color:#fff; white-space:nowrap; }
.b-done{background:#1f9c4a;} .b-wait{background:#f5a524;} .b-spec{background:#2a73d0;} .b-no{background:#d9342b;} .b-gray{background:#6c757d;}
.note { padding:10px 14px; background:var(--note-bg); border-left:3px solid #1f9c4a; border-radius:6px; font-size:13.5px; margin:10px 0; }
.warn { padding:10px 14px; background:var(--warn-bg); border-left:3px solid #f5a524; border-radius:6px; font-size:13.5px; margin:10px 0; }
ul.compact { margin:6px 0; padding-left:22px; } ul.compact li { margin:4px 0; font-size:13.5px; }
.stat-row { display:flex; gap:14px; flex-wrap:wrap; margin:14px 0; }
.stat { background:var(--card); border:1px solid var(--border); border-radius:12px; padding:14px 20px; text-align:center; min-width:130px; }
.stat .n { font-size:24px; font-weight:700; color:var(--primary); display:block; }
.stat .l { font-size:12px; color:var(--muted); }
.check-item { display:flex; align-items:flex-start; gap:10px; padding:9px 12px; border:1px solid var(--border); border-radius:10px; margin:6px 0; font-size:13.5px; }
.check-item input { margin-top:3px; transform:scale(1.2); }
/* 그룹 사이드바 */
.nav-group { margin-bottom:4px; }
.nav-group > .gtitle {
  display:flex; align-items:center; justify-content:space-between; cursor:pointer;
  padding:11px 18px; color:#fff; font-size:13px; font-weight:700; user-select:none;
  background:rgba(255,255,255,.04);
}
.nav-group > .gtitle:hover { background:rgba(255,255,255,.09); }
.nav-group > .gtitle .arrow { font-size:10px; transition:transform .2s; opacity:.7; }
.nav-group.collapsed > .gtitle .arrow { transform:rotate(-90deg); }
.nav-group .gitems { overflow:hidden; }
.nav-group.collapsed .gitems { display:none; }
.nav-group .gitems a { padding-left:34px; font-size:13px; }
/* 열기 버튼 + URL 행 */
.openbtn { display:inline-flex; align-items:center; gap:4px; background:var(--primary); color:#fff; text-decoration:none; border-radius:7px; padding:4px 12px; font-size:12px; font-weight:600; white-space:nowrap; }
.openbtn:hover { filter:brightness(1.1); }
.urlrow { display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; }
.urlrow code { word-break:break-all; }
@media (max-width:900px){ .sidebar{display:none;} .content{margin-left:0;} }
</style>
</head>
<body>

<div class="topbar">
  <div class="brand">📦 서울미녀 인수인계 대시보드 <small>(이전본) · 2026-06-12 · sj system → 서울미녀</small></div>
  <span style="font-size:13px; opacity:.9;"><?php echo htmlspecialchars($admin_name); ?>님</span>
  <button id="themeToggle" onclick="toggleTheme()">🌙 야간</button>
  <a class="btn" href="admin_handover_print.php" style="display:none;"></a>
  <a class="btn" href="dashboard.php">관리자 홈</a>
  <a class="btn" href="admin_logout.php">로그아웃</a>
</div>

<div class="layout">
  <nav class="sidebar" id="sideNav">
    <div class="nav-group">
      <div class="gtitle">📦 개요 <span class="arrow">▼</span></div>
      <div class="gitems">
        <a href="#overview" class="active" data-sec="overview">인계 패키지 개요</a>
      </div>
    </div>
    <div class="nav-group">
      <div class="gtitle">🔧 개발 <span class="arrow">▼</span></div>
      <div class="gitems">
        <a href="#status" data-sec="status">개발 현황</a>
        <a href="#remaining" data-sec="remaining">잔여 작업 (CPV·Figma)</a>
      </div>
    </div>
    <div class="nav-group">
      <div class="gtitle">🏗 기술 <span class="arrow">▼</span></div>
      <div class="gitems">
        <a href="#arch" data-sec="arch">시스템 구조</a>
        <a href="#serverdir" data-sec="serverdir">📂 서버 폴더 구조</a>
        <a href="#db" data-sec="db">DB &amp; ERD</a>
        <a href="#apis" data-sec="apis">API / 크론 / 서비스</a>
        <a href="#adminmenu" data-sec="adminmenu">관리자 메뉴 가이드</a>
      </div>
    </div>
    <div class="nav-group">
      <div class="gtitle">📱 모바일 앱 <span class="arrow">▼</span></div>
      <div class="gitems">
        <a href="#apps-android" data-sec="apps-android">🤖 Android</a>
        <a href="#apps-ios" data-sec="apps-ios">🍎 iOS</a>
        <a href="#store" data-sec="store">스토어 현황 / 양도</a>
      </div>
    </div>
    <div class="nav-group">
      <div class="gtitle">🔑 접속 / 인수 <span class="arrow">▼</span></div>
      <div class="gitems">
        <a href="#accounts" data-sec="accounts">계정 / 접속</a>
        <a href="#checklist" data-sec="checklist">인수 확인 체크리스트</a>
        <a href="#responsive" data-sec="responsive">📱 반응형 디자인 (신규)</a>
        <a href="_pagetest/verify_report.html" target="_blank" class="ext" style="color:#5fd38d">📊 검증 리포트 (캡처) ↗</a>
      </div>
    </div>
  </nav>

  <main class="content">

    <!-- ━━━ 개요 ━━━ -->
    <section class="section active" id="sec-overview">
      <div style="background:#dc2626;color:#fff;padding:14px 18px;font-size:15px;font-weight:700;border-radius:10px;margin-bottom:16px;border:2px solid #991b1b;line-height:1.6;">
        🚫 이 페이지는 <u>이전 버전(백업본)</u>입니다 — 패키지 위치·앱 종류·저장소명 등 일부 정보가 <b>옛 내용</b>입니다.<br>
        <span style="font-weight:500;font-size:14px;">최신 정확한 내용은 → </span>
        <a href="admin_handover.php" style="color:#fff;background:#7f1d1d;padding:3px 12px;border-radius:6px;text-decoration:none;font-size:14px;">admin_handover.php 열기 ↗</a>
      </div>
      <div class="card">
        <h1>인수인계 패키지 개요</h1>
        <div class="meta">인계일: 2026-06-12 · 인계자: sj system · 인수자: 서울미녀 · <b>최종 갱신: 2026-06-18</b> (앱 크래시·검은화면 해결 반영) 내부 개발팀</div>
        <div class="stat-row">
          <div class="stat"><span class="n">4GB+</span><span class="l">인계 패키지</span></div>
          <div class="stat"><span class="n">440</span><span class="l">DB 테이블</span></div>
          <div class="stat"><span class="n">258</span><span class="l">서비스 PHP 파일</span></div>
          <div class="stat"><span class="n">2종</span><span class="l">운영 모바일 앱</span></div>
          <div class="stat"><span class="n">34/34</span><span class="l">자동검증 PASS</span></div>
        </div>
        <div class="note" style="border-left-color:#0891b2;background:rgba(8,145,178,.10);display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
          <span>🔍 <b>플랫폼 동작 검증 리포트 (2026-06-12)</b> — 웹 14페이지 · Android 실기기(SM-N981N) · iOS 실기기(iPhone 18.4.1) 검증 · 스크린샷·동영상 포함</span>
          <a class="openbtn" style="background:#0891b2;" href="https://hms.seoulbeauty.app/handover_test_20260612/report.html" target="_blank">리포트 열기 ↗</a>
        </div>
        <div class="note" style="border-left-color:#7c3aed;background:rgba(124,58,237,.12);display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
          <span>🎨 <b>Figma ↔ 구현 화면 전체 비교 (12화면)</b> — 디자인/구현 양쪽 + 각 화면 라이브 이동 버튼</span>
          <a class="openbtn" style="background:#7c3aed;" href="https://hms.seoulbeauty.app/handover_test_20260612/compare.html" target="_blank">Figma 비교 열기 ↗</a>
        </div>
        <div class="note" style="border-left-color:#16a34a;background:rgba(22,163,74,.10);display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
          <span>✅ <b>앱 안정화 (2026-06-18)</b> — iOS 실행 즉시 종료 + 첫 실행 검은화면/<code>MissingPluginException</code> 해결, 최신 소스 반영. 상세는 <b>iOS 앱</b> 메뉴 참조</span>
        </div>
        <h2>패키지 위치</h2>
        <table>
          <tr><th>서버</th><td><code>5.104.82.35</code> → <code>/root/handover_backup_20260612/</code></td></tr>
        </table>
        <table>
          <thead><tr><th>폴더</th><th>내용</th></tr></thead>
          <tbody>
            <tr><td><code>01_web_source/</code></td><td>서울미녀 웹 전체 tar (3.5GB) + HMS Laravel tar (471MB, vendor 제외)</td></tr>
            <tr><td><code>02_app_source/</code></td><td>모바일 앱 소스 4종 zip (환자+파트너 Flutter / 병원 Flutter / 관리자 WebView / 네이티브 통합)</td></tr>
            <tr><td><code>03_database/</code></td><td>DB 전체 덤프 + 스키마 전용 덤프 (.sql.gz)</td></tr>
            <tr><td><code>04_docs/</code></td><td>HANDOVER_README.md (기술 가이드) + ERD_20260612.html</td></tr>
            <tr><td><code>05_config/</code></td><td>.env 사본, 크론탭, 서비스 목록, 버전 정보</td></tr>
            <tr><td><code>06_pc_dev_snapshot/</code></td><td>개발 PC 작업본 스냅샷 (site_get zip)</td></tr>
          </tbody>
        </table>
        <div class="note">✅ <b>GitHub 저장소 (최신 — 2026-06-15 인계본)</b><br>
<code>https://github.com/appdosa1004-cyber/seoul_beauty</code> (Private, 브랜치 <code>main</code>)<br>
서울미녀 프론트/관리자 소스 1004파일. 민감정보(dbconfig·.env·키)는 제외, 비번은 <code>__DB_PASSWORD__</code> 로 마스킹됨.<br>
<span style="color:#888">인수자 GitHub 아이디 받으면: Settings → Collaborators → Add people (Write 또는 Admin 권한)</span><br>
<small>※ 기존 <code>site_get</code> 레포는 옛날 사본 — <code>seoul_beauty</code> 사용</small></div>

<div class="note" style="border-left-color:#16a34a;background:rgba(22,163,74,.10)">
📦 <b>2026-06-15 전체 인계 패키지 위치 (서버)</b><br>
<code>5.104.82.35 : /root/handover_20260615/</code><br>
&nbsp;&nbsp;├ <code>01_web_source/</code> — HMS+서울미녀 코드 tar (1.1G, vendor/uploads 제외)<br>
&nbsp;&nbsp;├ <code>03_database/</code> — <b>hms_saas 전체 DB 덤프</b> + 스키마 덤프 (.sql.gz)<br>
&nbsp;&nbsp;└ 받는 법: <code>scp root@5.104.82.35:/root/handover_20260615/03_database/*.sql.gz ./</code><br>
<b>DB 복원:</b> <code>gunzip -c hms_saas_full_*.sql.gz | mysql -uroot -p hms_saas</code><br>
<small>웹 다운로드용 사본: <code>gangnamunni/_handover_files/</code> (.htaccess 차단 — scp 권장)</small></div>
      </div>
    </section>

    <!-- ━━━ 개발 현황 ━━━ -->
    <section class="section" id="sec-status">
      <div class="card">
        <h2>전체 개발 현황 (정직 보고)</h2>
        <div class="meta">1차 검수 100건 중 95건 완료 (95%) · Playwright 자동검증 34/34 PASS · 2026-06-12 기준</div>
        <h3>✅ 완료·운영 중인 핵심 기능</h3>
        <ul class="compact">
          <li>병원/의사/시술/후기/이벤트/커뮤니티/예약/상담 전체 사용자 기능</li>
          <li>관리자 75+ 메뉴 (RBAC 9역할, 다크모드, 마법사 15종, 엑셀 9종)</li>
          <li>광고 시스템 + CPV 과금 + 지갑 + 쿠폰/포인트 + 회원등급</li>
          <li>채팅, 알림(FCM), 메일 시스템, AI 자동화(멀티 프로바이더), SSO 통합로그인</li>
          <li>HMS(Laravel) 연동 + 5분 크론 동기화, 결제(이니시스 테스트 MID)</li>
          <li>자동 매뉴얼 15종(247p), QA 자동화(Playwright), Figma 검수 시스템</li>
          <li>커뮤니티 좋아요 — 2026-06-12 버그 수정 배포 완료 (likes 테이블 컬럼 통일)</li>
        </ul>
        <h3>⏳ 미완 항목 (인수 시 참고 — 사양/키 대기)</h3>
        <table>
          <thead><tr><th>항목</th><th>상태</th><th>사유</th></tr></thead>
          <tbody>
            <tr><td>📱 모바일 반응형 (폰트 스케일 + 카테고리표)</td><td>완료 (6/16)</td><td>전 페이지 글자 폰 화면폭 비례 축소(--fs-scale, 43페이지) + 메인 카테고리표(--cat-scale). 관리: 사이트설정→모바일스케일. PC 무영향. 상세는 좌측 [반응형 디자인] 메뉴</td></tr>
            <tr><td>카카오 간편 로그인 / 카카오톡 공유</td><td><span class="badge b-spec">사양 대기</span></td><td>카카오 JS 키 + 사업자 등록 필요 (발주사 발급)</td></tr>
            <tr><td>카카오/네이버 지도 API</td><td><span class="badge b-spec">사양 대기</span></td><td>API 키 발급 + 카카오 vs 네이버 선택 필요</td></tr>
            <tr><td>파트너 지갑 충전 결제</td><td><span class="badge b-done">완료 (6/12)</span></td><td>영카트 주문 연동 충전 구현 — 현재 PG 테스트 모드. 실 PG 계약 시 결제창만 연결</td></tr>
            <tr><td>병원 입점 신청 공개 폼</td><td><span class="badge b-done">동작 중</span></td><td>hospital_apply_public.php 운영 중 (서류 업로드 + 승인 대기열 + 메일/알림)</td></tr>
            <tr><td>커뮤니티 데모 글 폴백</td><td><span class="badge b-done">제거 완료 (6/12)</span></td><td>미존재 글 → 목록 리다이렉트 / 빈 목록 → 빈 상태 UI</td></tr>
            <tr><td>예약 알림 발송(푸시+이메일)</td><td><span class="badge b-gray">Phase 4 계획분</span></td><td>마일스톤 Phase 2~5 (75건) 향후 개발 목록 참고</td></tr>
          </tbody>
        </table>
        <div class="warn">⚠️ 실결제(PG)는 <strong>이니시스 테스트 MID(INIpayTest)</strong>만 설정되어 있습니다. 실 PG 계약(이니시스/KCP/토스 등)은 발주사 명의로 진행해야 합니다.</div>
      </div>
    </section>

    <!-- ━━━ 시스템 구조 ━━━ -->
    <section class="section" id="sec-arch">
      <div class="card">
        <h2>시스템 구조 및 서버 정보</h2>
        <div style="background:var(--th-bg); border:1px solid var(--border); border-radius:12px; padding:14px 16px; margin-bottom:14px;">
          <div class="urlrow" style="margin-bottom:8px;">
            <strong style="font-size:15px; color:var(--primary);">🖥 운영 서버 (Contabo) — 5.104.82.35</strong>
            <a class="openbtn" href="https://my.contabo.com/" target="_blank">콘타보 패널 ↗</a>
          </div>
          <table style="margin:0;">
            <tbody>
              <tr><th style="width:130px;">SSH / SFTP</th><td>호스트 <code>5.104.82.35</code> · 포트 <code>22</code> · 계정 <code>root</code> / <code><REMOVED_SSH_PASSWORD></code><br><small><code>ssh root@5.104.82.35</code> · FileZilla: SFTP/root/<REMOVED_SSH_PASSWORD></small></td></tr>
              <tr><th>콘타보 패널</th><td><code>my.contabo.com</code> · <code>oraclenter@gmail.com</code> / <code>32gB6ge95cEiE63zkB</code> (인수자 계정)</td></tr>
              <tr><th>사양 / OS</th><td>VPS · Rocky Linux 9.7 · 8 core / 23GB RAM / 800GB · Apache 2.4 · PHP 8.2 · MariaDB 10.11</td></tr>
              <tr><th>DB</th><td>localhost · <code>root</code> / <code><REMOVED_DB_PASSWORD></code> · DB <code>hms_saas</code></td></tr>
            </tbody>
          </table>
        </div>
        <h3>3계층 구조</h3>
        <table>
          <thead><tr><th>계층</th><th>기술</th><th>위치</th><th>역할</th></tr></thead>
          <tbody>
            <tr><td>서울미녀 서비스</td><td><strong>순수 PHP 8.2</strong> (프레임워크 없음)</td><td><code>hairwang_full/gangnamunni/</code></td><td>사용자/관리자 웹 전체</td></tr>
            <tr><td>HMS 병원관리</td><td><strong>Laravel 10</strong> (InfyHMS)</td><td><code>hms-saas/</code> 루트</td><td>병원/환자/예약 백오피스 + REST API</td></tr>
            <tr><td>게시판/쇼핑</td><td><strong>Gnuboard5 + 영카트</strong></td><td><code>hairwang_full/</code></td><td>회원(g5_member) · PG 결제 모듈</td></tr>
          </tbody>
        </table>
        <h3>서버 / 도메인</h3>
        <table>
          <tbody>
            <tr><th>서버</th><td>5.104.82.35 (Contabo · Rocky Linux 9.7 · 8core/23GB/800GB)</td></tr>
            <tr><th>웹 루트</th><td><code>/var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas/</code></td></tr>
            <tr><th>도메인</th><td>https://hms.seoulbeauty.app (Cloudflare proxy) · 메인 https://seoulbeauty.app → /</td></tr>
            <tr><th>vhost</th><td><code>/etc/httpd/conf.d/hms.seoulbeauty.app.conf</code> (Alias /hairwang → hairwang_full/)</td></tr>
            <tr><th>메일서버</th><td>Mailcow (Docker 18컨테이너) · mail.seoulbeauty.app · <code>/opt/mailcow-dockerized/</code></td></tr>
            <tr><th>배포 방식</th><td>SFTP 직접 배포 + <code>php -l</code> 구문 검증 (CI/CD 없음)</td></tr>
          </tbody>
        </table>
        <h3>프론트엔드</h3>
        <ul class="compact">
          <li>Bootstrap 5.3.3 + vanilla JS (jQuery 미사용) · Quill.js 1.3.7 (에디터) · Fabric.js 5.3.0 (이미지)</li>
          <li>다크모드: <code>data-bs-theme</code> + localStorage · 테마: 라이브 세팅 패널(57설정) + Figma 디자인 토큰</li>
        </ul>
        <h3>⚠️ 개발 시 필수 주의사항</h3>
        <ul class="compact">
          <li>Gnuboard <code>init.php</code> 직접 include 금지 (관리자 세션 충돌) — 관리자 세션 접두사 <code>ad_admin_</code></li>
          <li><code>gangnamunni/</code> 하위 서브디렉토리 PHP는 웹 접근 불가 (rewrite 충돌) → 루트에 <code>admin_*.php</code>로 배치</li>
          <li>KiviCare <code>/kv-api/</code> 폐기됨 — 앱은 HMS <code>/api/*</code>만 사용</li>
          <li>SQL은 Prepared Statement 필수 · HMS doctors 부서 컬럼은 <code>doctor_department_id</code></li>
          <li>AI 키는 DB에 AES-256-CBC 암호화 저장 — <code>includes/ai_proxy.php</code> 함수만 사용</li>
        </ul>
      </div>
    </section>

    <!-- ━━━ 서버 폴더 구조 ━━━ -->
    <section class="section" id="sec-serverdir">
      <div class="sd-tabs" style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;">
        <button class="sd-tab active" onclick="sdTab(this,'sd-structure')" style="padding:9px 18px;border:1px solid var(--border);background:var(--side-active,#ae1932);color:#fff;border-radius:8px;cursor:pointer;font-weight:600;">📂 폴더 구조</button>
        <button class="sd-tab" onclick="sdTab(this,'sd-backups')" style="padding:9px 18px;border:1px solid var(--border);background:transparent;color:inherit;border-radius:8px;cursor:pointer;font-weight:600;">💾 백업 폴더 / 파일</button>
      </div>
      <div class="sd-pane" id="sd-structure">
      <div class="card">
        <h2>📂 서버 폴더 구조 — HMS-SaaS 백엔드</h2>
        <p class="note">서울미녀/서울뷰티 전체 백엔드 루트 폴더의 상세 구조입니다. 인수인계 시 이 구조를 이해하면 전체 시스템을 파악할 수 있습니다.</p>
        <table>
          <tr><th style="width:34%">루트 경로</th><td><code>/var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas</code></td></tr>
          <tr><th>기술 스택</th><td>Laravel 10.19 · PHP 8.1 · MariaDB · Gnuboard5(서울미녀 웹) · Bootstrap 5.3 · Flutter(앱)</td></tr>
        </table>
        <table>
          <tr><th style="width:24%">폴더</th><th style="width:12%">용량</th><th>설명</th></tr>
          <tr><td><code>app/</code></td><td>5.2M</td><td><b>Laravel 핵심 로직</b> — Models·Http·Livewire·Repositories·helpers.php (비즈니스 로직 본체)</td></tr>
          <tr><td><code>routes/</code></td><td>252K</td><td>URL 라우팅 — api.php(앱API)·web.php·tenant.php(병원별)·landing.php</td></tr>
          <tr><td><code>config/</code></td><td>236K</td><td>설정 파일 (DB·앱·메일·캐시). 실제 값은 루트 <code>.env</code></td></tr>
          <tr><td><code>database/</code></td><td>2.4M</td><td>마이그레이션(스키마)·시더(초기데이터)</td></tr>
          <tr><td><code>hospital-api/</code></td><td>1.1G</td><td><b>HMS REST API</b> — 모바일 앱이 <code>/api/*</code>로 호출하는 백엔드</td></tr>
          <tr><td><code>hairwang_full/</code></td><td>4.4G</td><td><b>★ 서울미녀 웹서비스 전체</b> (Gnuboard5 + 커스텀). 아래 상세 <span class="badge b-done">구 앱소스 정리완료</span></td></tr>
          <tr><td style="padding-left:24px"><code>└ gangnamunni/</code></td><td>3.1G</td><td><b>★★ 실제 환자/관리자 페이지</b> (PHP 260+). 이번 인계 핵심. <code>/hairwang/gangnamunni/</code></td></tr>
          <tr><td style="padding-left:24px"><code>└ data/</code></td><td>217M</td><td>Gnuboard 업로드(이미지 등) — 사용자 콘텐츠, 백업 필수</td></tr>
          <tr><td style="padding-left:24px"><code>└ adm/ bbs/ lib/ plugin/</code></td><td>~96M</td><td>Gnuboard5 코어 — 프레임워크, 수정 주의</td></tr>
          <tr><td style="padding-left:24px"><code>└ shop/</code></td><td>3.1M</td><td>영카트(결제/상품) — PG 연동</td></tr>
          <tr><td style="padding-left:24px"><code>└ *_backup_* save_database/</code></td><td>~450M</td><td>백업본 — 인계 시 제외 가능</td></tr>
          <tr><td><code>resources/</code></td><td>38M</td><td>Blade 뷰·JS/SCSS 원본 (빌드 전 소스)</td></tr>
          <tr><td><code>public/</code></td><td>58M</td><td>웹 공개 루트 — index.php·빌드된 assets (외부 접근 가능 유일 폴더)</td></tr>
          <tr><td><code>vendor/</code></td><td>328M</td><td>Composer 패키지 — <b>자동생성</b>, <code>composer install</code>로 재생성, 이관 시 제외 가능</td></tr>
          <tr><td><code>storage/</code></td><td>35M</td><td>로그·캐시·세션·업로드 (logs는 정리 대상)</td></tr>
          <tr><td><code>lang/</code></td><td>1.9M</td><td>다국어 (ko 기본 + 10개 언어)</td></tr>
          <tr><td><code>docs/ sql_dumps/</code></td><td>34M</td><td>문서 / DB 덤프 백업 (참고용)</td></tr>
        </table>
        <div class="note" style="border-left-color:#d97706;background:#fff7ed;">
          📦 <b>이관 시 제외 가능 (현재 크기 기준)</b> — 소스가 아니라 명령어로 재생성되는 부산물·백업:
          <table style="margin:8px 0 0">
            <tr><th style="width:30%">제외 대상</th><th style="width:18%">현재 크기</th><th>복원</th></tr>
            <tr><td><code>vendor/</code></td><td>215M</td><td><code>composer install</code></td></tr>
            <tr><td><code>node_modules/</code></td><td>113M</td><td><code>npm install</code></td></tr>
            <tr><td><code>*.log</code> (전체)</td><td>288M</td><td>자동 생성</td></tr>
            <tr><td><code>storage</code> 캐시·로그</td><td>34M</td><td>자동 재생성</td></tr>
            <tr><td><code>*_backup_*</code> 폴더</td><td>2.0G</td><td>불필요</td></tr>
            <tr><td><code>save_database/</code></td><td>400M</td><td>불필요</td></tr>
            <tr style="font-weight:700"><td>제외 합계</td><td>≈ 3.0G</td><td></td></tr>
          </table>
          ✅ <b>현재 6.9G → 제외 후 약 3.8G (56%)</b>. 받는 쪽에서 <code>composer install</code> + <code>npm install</code>로 동일 복원.
        </div>
        <div class="warn" style="border-left-color:#dc2626;">
          ⚠️ <b>절대 제외 금지</b>(자동 복원 불가): <code>data/</code>(사용자 업로드 217M) · <code>.env</code>(DB비번 등) · <b>DB <code>hms_saas</code></b>(데이터 본체 — SQL 덤프 별도 백업 필수)
        </div>
        <div class="note" style="border-left-color:#16a34a;background:#f0fdf4;">
          📌 <b>인계 핵심 4가지</b>:
          <br>① 서울미녀 웹 = <code>.../hms-saas/hairwang_full/gangnamunni/</code>
          <br>② HMS 백엔드 = <code>.../hms-saas/</code>
          <br>③ 모바일 앱 소스 = <code>/var/www/html/seoulbeauty_app_source/</code>
          <br>④ DB = <code>hms_saas</code> (MariaDB)
        </div>
        <div class="note" style="border-left-color:#16a34a;background:#f0fdf4;">
          ✅ <b>구 앱소스 정리 완료 (2026-06-15)</b> — <code>hairwang_full/hairwang_app_source/</code>(구 hairwang3 앱, 814M, 운영무관·260룰 주범)을 <b>PC 백업 후 서버에서 삭제</b>했습니다.
          <br>　• PC 백업: <code>H:\carebaby_project\_handover_from_server\hairwang_app_source_20260615.tar.gz</code> (249M)
          <br>　• 서버 압축본: <code>/root/_archive/hairwang_app_source_20260615.tar.gz</code>
          <br>　• 효과: 서버 최장 경로 <b>377자 → 285자</b> (Windows 260룰 개선), <code>hairwang_full</code> 5.2G → 4.4G
        </div>
        <p style="margin-top:14px">
          <a class="openbtn" href="admin_server_structure.php" target="_blank">🔍 풀 화면 상세 페이지 열기 (트리·색상 범례)</a>
          &nbsp;
          <a class="openbtn" href="handover_work_guide.html" target="_blank" style="background:#0891b2">📦 인계 마무리 작업 가이드 (3종 패키지·260룰)</a>
        </p>
      </div>
      </div><!-- /sd-structure -->

      <div class="sd-pane" id="sd-backups" style="display:none;">
      <div class="card">
        <h2>💾 백업 폴더 / 파일 위치 (서버)</h2>
        <p class="note">인수인계·복구 시 참고할 서버 내 모든 백업 위치입니다. (실측 2026-06-15)</p>

        <h3>① 핵심 인계 백업본 <span class="badge b-done">우선</span></h3>
        <table>
          <tr><th style="width:42%">경로</th><th style="width:12%">용량</th><th>내용</th></tr>
          <tr><td><code>/root/handover_backup_20260612/</code></td><td>5.9G</td><td><b>★ 인계 통합 백업</b> — 01_web_source · 02_app_source · 03_database · 04_docs · 05_config · 06_pc_dev_snapshot</td></tr>
          <tr><td><code>/root/_archive/</code></td><td>2.5G</td><td>정리 아카이브 — <code>hms-saas-flutter</code>(구매원본) + <code>hms-saas_backup_20260212.tar.gz</code>(1.9G)</td></tr>
        </table>

        <h3>② /root 백업 파일 (날짜별 스냅샷)</h3>
        <table>
          <tr><th>파일 (패턴)</th><th>크기</th><th>설명</th></tr>
          <tr><td><code>backup_gangnamunni_src_2026MMDD*.tar.gz</code></td><td>~2.2G ×9</td><td>강남언니 웹 소스 날짜별 (03-29~04-03)</td></tr>
          <tr><td><code>backup_gangnamunni_2026MMDD*.tar.gz</code></td><td>0.6~2.2G</td><td>강남언니 전체 백업 (03-06·03-11·03-17·03-26)</td></tr>
          <tr><td><code>backup_gangnamunni_2026MMDD*.sql.gz</code></td><td>~240K ×다수</td><td>강남언니 DB 덤프 날짜별 (03-29~04-03)</td></tr>
          <tr><td><code>backup_hms_saas_2026MMDD*.sql / .sql.gz</code></td><td>0.3~7.2M</td><td>HMS DB 덤프 (02-10~04-06)</td></tr>
          <tr><td><code>handover_backup_20260612</code> 외 폴더</td><td>—</td><td><code>backups/</code> · <code>db_backup/</code> · <code>pre_migration_backup_20260612/</code> · <code>backup_treatment_20260325/</code> 등</td></tr>
        </table>
        <div class="note">📌 <code>/root</code>에 날짜별 백업이 누적되어 있습니다. <b>가장 최신·완전한 것은 <code>handover_backup_20260612/</code></b>이며, 나머지 날짜별은 과거 시점 스냅샷입니다.</div>

        <h3>③ hms-saas 내부 백업 / DB 덤프</h3>
        <table>
          <tr><th style="width:50%">경로</th><th style="width:10%">용량</th><th>설명</th></tr>
          <tr><td><code>.../hms-saas/sql_dumps/</code></td><td>24M</td><td>DB 덤프 모음 (gangnamunni·hms_saas·hairwang 등 10개)</td></tr>
          <tr><td><code>.../hairwang_full/save_database/</code></td><td>401M</td><td>구 DB 백업 <span class="badge b-no">인계 제외 가능</span></td></tr>
          <tr><td><code>.../hairwang_full/db/</code></td><td>70M</td><td>Gnuboard DB 백업 (253파일)</td></tr>
          <tr><td><code>DELETED</code></td><td>49M</td><td>강남언니 2월 백업 <span class="badge b-no">제외 가능</span></td></tr>
          <tr><td><code>.../hms-saas/database/hms_saas.sql</code></td><td>—</td><td>초기 스키마 SQL</td></tr>
        </table>

        <h3>④ 보안 격리 <span class="badge b-no">참고</span></h3>
        <table>
          <tr><td style="width:52%"><code>/root/_malware_quarantine_20260612/</code></td><td>88K / 21개</td><td>웹셸 백도어 격리본 (2026-06-12 발견·격리). 복원 금지, 보안 증거용.</td></tr>
        </table>

        <div class="warn" style="border-left-color:#dc2626;">
          ⚠️ <b>DB는 파일 폴더에 없습니다.</b> 실제 운영 데이터는 MariaDB <code>hms_saas</code>(441테이블)·<code>hairwang</code>(190테이블)에 있으며, 인계 시 <code>mysqldump</code>로 별도 추출 필수. (위 .sql.gz들은 과거 시점 백업)
        </div>
        <div class="note" style="border-left-color:#16a34a;background:#f0fdf4;">
          ✅ <b>마무리 인계 시</b>: <code>/root/handover_backup_20260612/</code>(통합본) 또는 새로 생성할 <code>/root/_handover_final/</code> 1곳만 받으면 됩니다. 날짜별 누적 백업은 디스크 정리 대상.
        </div>
      </div>
      </div><!-- /sd-backups -->
    </section>

    <!-- ━━━ DB ━━━ -->
    <section class="section" id="sec-db">
      <div class="card">
        <h2>데이터베이스 & ERD</h2>
        <div class="stat-row">
          <div class="stat"><span class="n">440</span><span class="l">전체 테이블</span></div>
          <div class="stat"><span class="n">242</span><span class="l">HMS Laravel</span></div>
          <div class="stat"><span class="n">99+8</span><span class="l">서울미녀+CPV</span></div>
          <div class="stat"><span class="n">83</span><span class="l">Gnuboard/영카트</span></div>
          <div class="stat"><span class="n">26,002</span><span class="l">총 데이터 행</span></div>
        </div>
        <table>
          <tbody>
            <tr><th>DB</th><td><code>hms_saas</code> (MariaDB 10.11, localhost) — 메인 통합 DB</td></tr>
            <tr><th>보조 DB</th><td><code>hairwang</code> (190테이블) — 레거시 연동분, 유지 필요</td></tr>
            <tr><th>테이블 접두사</th><td><code>gangnamunni_*</code> 서울미녀 / <code>g5_*</code> Gnuboard·영카트 / 접두사 없음 = HMS</td></tr>
            <tr><th>접속 설정</th><td>서울미녀: <code>gangnamunni/dbconfig.php</code> · HMS: <code>.env</code></td></tr>
          </tbody>
        </table>
        <div class="note">
          <div class="urlrow">
            <span>📊 <strong>ERD 다이어그램(HTML)</strong> — 핵심 60테이블 관계도 9개 + 440테이블 검색 카탈로그 (SVG·PDF 저장 가능)</span>
            <a class="openbtn" href="https://hms.seoulbeauty.app/handover_erd_20260612.html" target="_blank">ERD 열기 ↗</a>
          </div>
          <div class="urlrow" style="margin-top:8px;">
            <span>🗂 <strong>ERD 프로그램 import용 (DBML)</strong> — dbdiagram.io에 붙여넣기/import (440테이블·407관계)</span>
            <a class="openbtn" href="https://hms.seoulbeauty.app/hms_saas.dbml" target="_blank" download>DBML 다운로드 ↓</a>
          </div>
          <div class="urlrow" style="margin-top:8px;">
            <span>🛢 <strong>스키마 SQL</strong> — MySQL Workbench/DBeaver 역공학(Reverse Engineer)으로 ERD 생성</span>
            <code style="font-size:11px;">패키지 03_database/hms_saas_schema_20260612.sql.gz</code>
          </div>
        </div>
        <h3>핵심 도메인 테이블</h3>
        <table>
          <thead><tr><th>도메인</th><th>주요 테이블</th></tr></thead>
          <tbody>
            <tr><td>병원/의사</td><td><code>gangnamunni_hospitals</code> (153행, id 8093+) · <code>gangnamunni_doctors</code> · <code>hospital_*</code> 연동 5종</td></tr>
            <tr><td>시술</td><td><code>gangnamunni_treatment_master</code> (신규 체계) — 구 <code>gangnamunni_treatments</code>와 공존 주의</td></tr>
            <tr><td>후기/커뮤니티</td><td><code>hospital_reviews</code> · <code>gangnamunni_community_posts/comments/likes</code> (likes 회원 컬럼은 <code>user_id</code>)</td></tr>
            <tr><td>예약</td><td><code>gangnamunni_bookings</code> + HMS <code>appointments</code> 동기화</td></tr>
            <tr><td>광고/CPV</td><td><code>gangnamunni_cpv_*</code> 8종 (settings 73키, phase1~7) · 지갑/거래/로그</td></tr>
            <tr><td>회원</td><td><code>g5_member</code> (Gnuboard) + HMS <code>users</code> + SSO <code>sso_tokens</code></td></tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- ━━━ 관리자 메뉴 ━━━ -->
    <section class="section" id="sec-adminmenu">
      <div class="card">
        <h2>관리자 메뉴 가이드 (사이드바 15+그룹)</h2>
        <div class="urlrow" style="margin:6px 0 10px; padding:10px 14px; background:var(--th-bg); border-radius:10px;">
          <span style="font-size:13px;">관리자 대시보드 접속: <code>https://hms.seoulbeauty.app/dashboard.php</code> → 로그인 → 좌측 사이드바 (그룹은 권한별 필터링)</span>
          <a class="openbtn" href="https://hms.seoulbeauty.app/dashboard.php" target="_blank">열기 ↗</a>
        </div>
        <table>
          <thead><tr><th>그룹</th><th>주요 메뉴</th></tr></thead>
          <tbody>
            <tr><td>피그마 디자인</td><td>피그마 vs 구현 비교(figma_compare.php) · 검수 메모 대시보드 · 진행 보고서 링크</td></tr>
            <tr><td>대시보드</td><td>dashboard.php · SNS/앱스토어 심사 관리 · 메뉴 안내/관리 · 그누보드 관리자(외부)</td></tr>
            <tr><td>병원 등록</td><td>입점 안내 · 등록 신청/현황 · 신청 목록 · 등록 프로세스 안내 · 가이드</td></tr>
            <tr><td>콘텐츠 관리</td><td>병원/의사/인력 · 후기 관리/검수 · 병원 이미지 · 광고 · 카테고리 · 꿀팁 · 이벤트 · 칼럼 · 응모</td></tr>
            <tr><td>광고 관리</td><td>광고 단가 · 광고 통계 · 광고주 · 광고·디스플레이 설정(7탭 ON/OFF) · 프로모션</td></tr>
            <tr><td>신청/등록 관리</td><td>병원·광고·의사·간호사·약사·환자·직원·의약품·예약 마법사 9종</td></tr>
            <tr><td>진료 관리 (HMS)</td><td>환자 · 외래 · 처방전 · 입원 · 병상 · 청구/결제</td></tr>
            <tr><td>시술 관리</td><td>시술 마스터 · 병원별 시술 배정</td></tr>
            <tr><td>엑셀 관리</td><td>대량 등록 · 데이터 검색/다운로드</td></tr>
            <tr><td>신고/차단 + 고객서비스</td><td>신고/차단 · 약관/가이드라인 · FAQ · Q&A · 쿠폰 · 포인트 · 회원등급 · 랭킹 · 병원소식</td></tr>
            <tr><td>메일 관리</td><td>메일함 · 상용구 · 서명 · 계정 설정 · 메일서버(Mailcow 외부)</td></tr>
            <tr><td>AI / 자동화</td><td>AI 자동화 설정(11그룹) · 자동 테스트 v1/v2 · E2E 테스트</td></tr>
            <tr><td>사이트 설정</td><td>통합 설정 · 테마/고급 테마 · 네비게이션 · 에디터 · 점검 모드</td></tr>
            <tr><td>관리자/알림</td><td>관리자 관리(RBAC) · 등급별 기능 · 알림 센터 · 알림 보내기</td></tr>
            <tr><td>CPV 과금 + 결제</td><td>CPV 단가/지갑/대시보드/정산 · 결제 관리(4탭: 주문/상품/매출/PG설정)</td></tr>
            <tr><td>QA & 리포트</td><td>QA 마스터/통합 대시보드 · 각종 검증 리포트 · 자동 매뉴얼 15종</td></tr>
          </tbody>
        </table>
        <div class="note">📖 각 메뉴의 사용법은 자동 매뉴얼(사이드바 도움말 그룹 → 자동 매뉴얼, 총 247페이지)에서 화면 캡처와 함께 확인할 수 있습니다.</div>
      </div>
    </section>

    <!-- ━━━ Android 앱 ━━━ -->
    <section class="section" id="sec-responsive">
      <span class="badge-new">2026-06-16 신규</span>
      <h1>📱 모바일 반응형 디자인 (폰트 스케일 + 카테고리표)</h1>
      <p class="lead">사이트 전체 글자와 메인 카테고리 표를 폰 화면폭에 맞춰 비례 축소하는 기능. 피그마 디자인 일치 + PC 무영향.</p>

      <h2>🎯 무엇을 해결했나</h2>
      <table>
        <tr><th style="width:200px">문제</th><td>사이트 글자가 고정 px(2,179곳)이라 좁은 폰에서 너무 크게 보이고, 메인 카테고리 표가 2칸만 노출 (피그마는 서브 3개)</td></tr>
        <tr><th>해결</th><td>① 전체 글자: <code>font-size:NNpx → calc(NNpx × var(--fs-scale))</code> (43개 페이지)<br>② 카테고리표: <code>--cat-scale</code> 4변수로 비례 축소</td></tr>
        <tr><th>방식</th><td>CSS 변수 + <code>clamp()</code> — 화면폭 비례, <b>깜빡임 없음</b>, PC는 1 고정으로 <b>안 깨짐</b></td></tr>
      </table>

      <h2>🛠 관리 위치</h2>
      <table>
        <tr><th style="width:200px">관리 페이지</th><td><b>관리자 → 사이트 설정 → 모바일 스케일</b> · <code>admin_mobile_scale.php</code></td></tr>
        <tr><th>탭 구성</th><td><b>[설정]</b> 슬라이더+미리보기 · <b>[동작원리]</b> 상세 설명 · <b>[관련 DB 정보]</b> 테이블/키</td></tr>
        <tr><th>조작</th><td>슬라이더 이동 → 실시간 미리보기(복사본 iframe) → <b>[저장]</b> 으로 사이트 반영. <b>원복</b> 버튼 제공</td></tr>
        <tr><th>적용 범위</th><td>폰트 스케일 = 전 페이지 / 카테고리표 = 메인(index.php) 전용 / PC = 영향 없음</td></tr>
      </table>

      <h2>🗄 관련 DB (2개 테이블 — 주의)</h2>
      <p><b>① <code>gangnamunni_theme_settings</code> — 폰트 스케일(전체 글자)</b><br>
      읽기 <code>getTheme()</code> · 저장 <code>api_save_font_scale.php</code> (업서트)</p>
      <table>
        <tr><th>setting_key</th><th>기본</th><th>의미</th></tr>
        <tr><td><code>fs_mobile_min</code></td><td>0.82</td><td>모바일 글자 최소 배율(하한). 가장 자주 조정</td></tr>
        <tr><td><code>fs_os_scale</code></td><td>1</td><td>전체 글자 추가 배율</td></tr>
        <tr><td><code>fs_page_scale</code></td><td>1</td><td>페이지별 추가 배율</td></tr>
        <tr><td><code>fs_mobile_slope</code> / <code>fs_mobile_base</code></td><td>0.092 / 0.62</td><td>화면폭 비례 계수(고급, 보통 고정)</td></tr>
      </table>
      <p style="margin-top:14px"><b>② <code>gangnamunni_settings</code> (group=mobile_scale) — 카테고리 표</b><br>
      읽기 <code>getSettingValue()</code> · 저장 <code>api_save_setting.php</code> · 키 <code>cat_scale_{pc|android|ios}_{enabled|factor|adjust|font|pad|chip}</code> (18키)</p>

      <div class="warn-box">
        <b>⚠️ 인계 핵심 주의:</b> 폰트(theme_settings)와 카테고리(settings)는 <b>서로 다른 테이블</b>입니다.
        <code>getTheme()</code>=theme_settings, <code>getSettingValue()</code>=settings. 새 키 추가 시 <b>읽기·저장 API·화이트리스트 3곳</b>을 모두 맞춰야 반영됩니다.
        <code>updateSetting()</code>은 UPDATE 전용이라 행이 없으면 저장 실패 → 폰트는 전용 API(업서트)로 회피함.
      </div>

      <h2>📂 적용 파일</h2>
      <table>
        <tr><th style="width:280px">파일</th><th>역할</th></tr>
        <tr><td><code>theme_helper.php</code></td><td>:root에 --fs-scale / 토큰 출력 (generateThemeCSS, generateFontScaleCSS)</td></tr>
        <tr><td><code>admin_mobile_scale.php</code></td><td>관리 대시보드(설정·동작원리·DB 3탭)</td></tr>
        <tr><td><code>api_save_font_scale.php</code></td><td>폰트 스케일 저장 API (theme_settings 업서트)</td></tr>
        <tr><td><code>index_preview.php</code></td><td>미리보기 복사본 (postMessage 수신)</td></tr>
        <tr><td>43개 프론트 PHP</td><td>font-size → calc() 변환 적용</td></tr>
      </table>

      <h2>✅ 검증 리포트 (캡처 포함)</h2>
      <p>Playwright로 <b>iPhone(390px) 에뮬레이션</b> — 8개 핵심 페이지 전부 <code>--fs-scale</code> 적용 + <b>가로 넘침 0건</b>, PC(1280px)는 <code>scale=1</code>로 영향 없음 확인.</p>
      <table>
        <tr><th style="width:200px">검증 리포트 (웹)</th><td><a href="_pagetest/verify_report.html" target="_blank" class="openbtn">verify_report.html 열기 &#8599;</a> &nbsp; 모바일 8페이지 + PC + 관리화면 캡처</td></tr>
        <tr><th>인계 패키지 사본</th><td><code>/root/handover_20260615/06_reports/verify_report.html</code></td></tr>
        <tr><th>받는 법</th><td><code>scp root@5.104.82.35:/root/handover_20260615/06_reports/verify_report.html ./</code></td></tr>
      </table>

      <h2>🧭 인계자용 빠른 시작 (How-To)</h2>
      <div class="note">
        <b>글자 크기를 조정하고 싶다면:</b><br>
        1. 관리자 로그인 &rarr; 좌측 사이드바 <b>사이트 설정 &rarr; 모바일 스케일</b> 클릭<br>
        2. <b>[설정]</b> 탭에서 상단 파란 카드 <b>"폰트 스케일 — 전체 글자"</b> 의 <b>모바일 최소 배율(하한)</b> 슬라이더를 움직임<br>
        &nbsp;&nbsp;&nbsp;→ 오른쪽 미리보기에 <b>실시간 반영</b> (값이 작을수록 글자 작아짐)<br>
        3. 마음에 들면 <b>[저장]</b> 클릭 &rarr; 방문자가 새로고침하면 적용됨<br>
        4. 되돌리려면 각 항목의 <b>&#8635; 기본값</b> 또는 <b>전체 원복</b> 클릭<br>
        <b>※ PC 화면은 절대 바뀌지 않습니다</b> (모바일 전용). 카테고리표만 조정하려면 같은 탭 아래쪽 기기별 카드 사용.
      </div>

      <h2>🔧 인계자가 알아야 할 함정 (Troubleshooting)</h2>
      <table>
        <tr><th style="width:230px">증상</th><th>원인 / 해결</th></tr>
        <tr><td>저장했는데 글자가 안 바뀜</td><td>폰트는 <code>theme_settings</code>, 카테고리는 <code>settings</code> &mdash; <b>다른 테이블</b>. 새 키 추가 시 읽기/저장API/화이트리스트 <b>3곳</b> 모두 수정 필요</td></tr>
        <tr><td>"저장 성공" 토스트는 뜨는데 미반영</td><td>구 <code>updateSetting()</code>은 UPDATE 전용 &rarr; 행 없으면 무반영. 폰트는 <code>api_save_font_scale.php</code>(INSERT...ON DUPLICATE 업서트)로 회피함</td></tr>
        <tr><td>새 페이지 만들었는데 글자 안 줄어듦</td><td>그 페이지가 <code>--fs-scale</code> 정의를 못 받음. header.php 쓰면 자동, 아니면 <code>&lt;head&gt;</code>에 <code>&lt;?php echo generateFontScaleCSS(); ?&gt;</code> 추가</td></tr>
        <tr><td>또 <code>font-size:18px</code>처럼 고정 입력함</td><td>신규 작성 시 <code>font-size:calc(18px * var(--fs-scale,1) * var(--fs-page,1))</code> 또는 토큰 <code>var(--fs-xl)</code> 사용 권장</td></tr>
      </table>
    </section>

    <section class="section" id="sec-apps-android">
      <div class="card">
        <h2>🤖 Android 앱 — 서울미녀 (Google Play)</h2>
        <div class="note" style="border-left-color:#16a34a;background:#f0fdf4;font-size:14px;">
          ✅ <b>현재 Google Play 출시·승인 완료</b> — 서울미녀 환자앱 (<code>com.celebhospital.patient</code>). 이 앱만 운영·심사 통과 상태입니다.
        </div>
        <h3>앱 기본 정보</h3>
        <table>
          <tr><th style="width:32%">앱 이름</th><td>서울미녀 (환자용)</td></tr>
          <tr><th>패키지명 (번들 ID)</th><td><code>com.celebhospital.patient</code> — <b>업데이트할 때 절대 바꾸면 안 됩니다</b></td></tr>
          <tr><th>스토어</th><td>Google Play — <b>출시·승인 완료</b></td></tr>
          <tr><th>빌드 도구</th><td><b>Flutter 3.35.4</b> (이 버전이어야 함. 최신 버전은 이 앱 빌드가 깨집니다)</td></tr>
          <tr><th>서명 키</th><td><code>patient-release-key.jks</code> · SHA1 <code>B2:CF:33:67…</code><br>비밀번호 <code>patient2026!</code> · alias <code>patient</code><br><b>업데이트 시 반드시 같은 키</b> — 다르면 업로드 거부됩니다</td></tr>
        </table>
        <h3>📂 서버에서의 정확한 위치</h3>
        <div class="note" style="font-size:13.5px;">모든 소스는 서버 <code>/var/www/html/seoulbeauty_app_source/</code> 아래에 <b>압축이 풀린 채</b> 그대로 올려져 있습니다. 폴더를 바로 열어 쓰면 되고, 압축본(다운로드용)은 <code>_archives/</code>에 따로 보관돼 있습니다.</div>
        <table>
          <tr><th style="width:40%">① 전체 소스 (Android+iOS, 압축 푼 폴더)</th><td><code>/var/www/html/seoulbeauty_app_source/patient_app_source/patient_app/</code><br>→ 이 폴더 안에 <code>android/</code> · <code>ios/</code> · <code>pubspec.yaml</code> 가 모두 있습니다 (바로 빌드 가능)</td></tr>
          <tr><th>② 빌드 필수 패치 플러그인 (압축 푼 폴더)</th><td><code>/var/www/html/seoulbeauty_app_source/patched_plugins/</code></td></tr>
          <tr><th>③ 빌드된 AAB (업로드 수락본)</th><td><code>/var/www/html/seoulbeauty_app_source/_archives/seoulbeauty_patient_Flutter_v1.9.0_vc27_TESTED.aab</code></td></tr>
          <tr><th>④ (참고) 압축본 — 다운로드/백업용</th><td><code>/var/www/html/seoulbeauty_app_source/_archives/</code><br><code>seoulbeauty_patient_Flutter_v1.9.0_vc27_SOURCE.tar.gz</code> · <code>patched_plugins_namespace_fix.tar.gz</code></td></tr>
        </table>
        <h3>📘 참조용 — Android 새 버전 빌드·업로드 가이드 (순서대로)</h3>
        <ol style="padding-left:22px;line-height:1.9;font-size:14px;margin:8px 0">
          <li><b>소스 가져오기</b> — 위 <b>①</b> 폴더(<code>patient_app_source/patient_app/</code>)를 빌드할 PC/Mac으로 복사합니다. (서버에 이미 압축이 풀려 있으니 그대로 내려받으면 됩니다)</li>
          <li><b>Flutter 설치</b> — <b>Flutter 3.35.4</b>를 설치합니다. (최신 버전 쓰면 빌드 실패하니 꼭 3.35.4)</li>
          <li><b>패치 적용</b> — 위 <b>②</b> 폴더(<code>patched_plugins/</code>) 안의 플러그인들을 <code>~/.pub-cache/hosted/pub.dev/</code> 에 덮어씁니다. <b>(이걸 안 하면 빌드가 실패합니다)</b></li>
          <li><b>버전 올리기</b> — 프로젝트의 <code>pubspec.yaml</code>에서 <code>version: 1.9.0+25</code> 의 <b>+뒤 숫자를 더 큰 값</b>으로 (예: <code>1.9.0+28</code>).</li>
          <li><b>빌드</b> — 터미널에서 프로젝트 폴더로 이동 후 <code>flutter build appbundle --release</code> 실행 → <code>build/outputs/bundle/release/app-release.aab</code> 가 만들어집니다.</li>
          <li><b>업로드</b> — Play Console → 서울미녀 앱 → <b>프로덕션</b>(또는 내부 테스트) → <b>새 버전 만들기</b> → 그 <code>app-release.aab</code> 올리기 → 검토 → 출시.</li>
        </ol>
        <div class="warn" style="border-left-color:#f5a524;font-size:14px;">⚠️ <b>3가지는 무조건 지키기</b> — ① 패키지명 그대로 ② 서명키(<code>patient-release-key.jks</code>) 그대로 ③ 버전(+숫자)은 항상 더 크게. 하나라도 어기면 업로드가 거부됩니다.</div>
      </div>
    </section>

    <!-- ━━━ iOS 앱 ━━━ -->
    <section class="section" id="sec-apps-ios">
      <div class="card">
        <h2>🍎 iOS 앱 — 서울미녀 (App Store)</h2>
        <div class="note" style="border-left-color:#0891b2;background:#f0f9ff;font-size:14px;">
          🍎 App Store Connect에 등록됨 — <code>com.celebhospital.patient</code> · Apple ID <code>6760112408</code> (Oracle Land 계정). 현재 심사 상태는 App Store Connect에서 확인하세요.
        </div>
        <h3>앱 기본 정보</h3>
        <table>
          <tr><th style="width:32%">앱 이름</th><td>서울미녀</td></tr>
          <tr><th>번들 ID</th><td><code>com.celebhospital.patient</code> (Android와 동일)</td></tr>
          <tr><th>App Store Apple ID</th><td><code>6760112408</code></td></tr>
          <tr><th>Apple 개발자 계정</th><td>Oracle Land Co.,Ltd. (<code>oracleclinicdev@gmail.com</code>)</td></tr>
          <tr><th>빌드 환경</th><td><b>Mac 필수</b> — Xcode 26.3 + Flutter 3.35.4 (Android와 <b>같은 프로젝트</b>의 <code>ios/</code> 폴더)</td></tr>
          <tr><th>서명</th><td>Apple Distribution 인증서 (Mac 키체인) — iOS는 Android keystore와 달리 Apple이 서명 관리</td></tr>
        </table>
        <h3>📂 서버에서의 정확한 위치</h3>
        <table>
          <tr><th style="width:40%">전체 소스 (Android+iOS 한 프로젝트, 압축 푼 폴더)</th><td><code>/var/www/html/seoulbeauty_app_source/patient_app_source/patient_app/</code><br>→ 이 폴더 안 <code>ios/Runner.xcworkspace</code> 가 iOS 프로젝트입니다 (Xcode로 엽니다)</td></tr>
          <tr><th>(참고) 압축본 — 다운로드/백업용</th><td><b>최신(2026-06-18, 크래시·검은화면 해결 반영):</b> <code>/var/www/html/seoulbeauty_app_source/_archives/seoulbeauty_patient_Flutter_v1.9.0_vc27_SOURCE_20260618.tar.gz</code><br>(이전본: <code>…_vc27_SOURCE.tar.gz</code>)</td></tr>
        </table>
        <h3>📘 참조용 — iOS 새 버전 빌드·업로드 가이드 (Mac에서, 순서대로)</h3>
        <ol style="padding-left:22px;line-height:1.9;font-size:14px;margin:8px 0">
          <li><b>소스 가져오기</b> — 위 폴더(<code>patient_app_source/patient_app/</code>)를 <b>Mac</b>으로 복사합니다. (서버에 이미 압축이 풀려 있습니다)</li>
          <li><b>준비</b> — 터미널에서 프로젝트 폴더로 이동 후 <code>flutter pub get</code> 실행.</li>
          <li><b>Xcode 열기</b> — <code>ios/Runner.xcworkspace</code> 를 더블클릭 (또는 터미널 <code>open ios/Runner.xcworkspace</code>).</li>
          <li><b>타깃 선택</b> — Xcode 상단에서 기기 선택을 <b>Any iOS Device (arm64)</b> 로.</li>
          <li><b>빌드번호 올리기</b> — 빌드번호(CFBundleVersion)를 이전보다 큰 값으로 (pubspec의 +숫자에서 자동, 또는 Xcode에서 직접).</li>
          <li><b>아카이브</b> — 메뉴 <b>Product → Archive</b>.</li>
          <li><b>업로드</b> — Organizer 창에서 <b>Distribute App → App Store Connect → Upload</b>.</li>
          <li><b>제출</b> — App Store Connect → 서울미녀 앱 → 새 버전에 그 빌드 선택 → <b>심사 제출</b>.</li>
        </ol>
        <div class="warn" style="border-left-color:#f5a524;font-size:14px;">⚠️ <b>주의</b> — ① 번들 ID 그대로 ② 빌드번호 항상 증가 ③ 처음 업로드 시 <b>암호화 사용 여부</b>(<code>ITSAppUsesNonExemptEncryption</code>) 물으면 보통 <b>"아니요"</b> ④ Apple Distribution 인증서(Oracle Land 계정)로 서명.</div>

        <h3 style="margin-top:22px">✅ 앱 안정화 — 실행 크래시 / 첫 화면 검은화면 해결 (2026-06-18)</h3>
        <div class="note" style="border-left-color:#16a34a;background:rgba(22,163,74,.10);font-size:14px;line-height:1.8;">
          iOS·Android 양쪽에서 보고됐던 <b>① 아이콘 실행 즉시 종료(iOS)</b>와 <b>② 첫 실행 검은 화면 + <code>MissingPluginException</code></b> 문제를 해결해 최신 소스에 반영했습니다.
          상세 분석/원복 방법은 소스 폴더 안의 다음 문서 참조:
          <code>patient_app/ios앱크래시로죽는문제해결.md</code> · <code>patient_app/초기검은화면_MissingPluginException_해결.md</code>
        </div>
        <table>
          <tr><th style="width:32%">① iOS 실행 즉시 종료</th><td><b>원인</b>: <code>flutter_custom_tabs_ios</code> 플러그인의 generated Swift 메시지 타입 문제(크래시 최상단 프레임 <code>CustomTabsPlugin.register</code>).<br><b>해결</b>: <code>flutter_custom_tabs 2.4.1</code> 고정 + <code>third_party/flutter_custom_tabs_ios</code> 로 vendoring 후 패치. Run scheme을 Release로.</td></tr>
          <tr><th>② 첫 화면 검은화면 / MissingPluginException</th><td><b>원인</b>: 시작 WebView 로딩 구간에서 <code>connectivity_plus</code>의 <code>dev.fluttercommunity.plus/connectivity</code> 채널 미등록 → 예외가 초기 흐름을 막음. Android 다크모드 첫 프레임 검은 배경도 동반.<br><b>해결</b>: <code>connectivity_plus</code> vendoring + 예외 방어(<code>network_utils.dart</code>), 시작 WebView/스플래시 흰색 고정, Android <code>styles.xml</code> Light 테마 고정.</td></tr>
          <tr><th>검증</th><td>iOS Release 빌드·iPhone XS 설치·standalone 실행·프로세스 생존 / 새 크래시 리포트 없음. Android SM-N981N 설치·실행·<code>MissingPluginException</code> 없음·<code>FATAL EXCEPTION</code> 없음.</td></tr>
          <tr><th>필수 유지 항목</th><td>빌드 전 <code>third_party/connectivity_plus</code> · <code>third_party/flutter_custom_tabs_ios</code> 폴더를 <b>그대로 유지</b>해야 합니다(삭제 시 동일 문제 재발). 원복은 <code>patient_app/.codex_backups/restore_*.sh</code>.</td></tr>
        </table>
        <div class="warn" style="border-left-color:#0891b2;font-size:13px;">
          📌 <b>SSH 빌드 시 주의</b> — CocoaPods/Flutter가 비대화 셸 PATH에 없을 수 있음.
          <code>cd ios &amp;&amp; LANG=en_US.UTF-8 LC_ALL=en_US.UTF-8 /opt/homebrew/bin/pod install</code> ·
          iOS 실행은 <code>PATH=/opt/homebrew/bin:$PATH … flutter run --release …</code>.
          Xcode GUI와 command-line <code>xcodebuild</code> <b>동시 실행 금지</b>(PIF 충돌).
        </div>
      </div>
    </section>

    <!-- ━━━ 스토어 현황 / 양도 절차 ━━━ -->
    <section class="section" id="sec-store">
      <div class="card">
        <h2>스토어 현황 / 양도 절차</h2>

        <h3>📊 현재 스토어 상태</h3>
        <table>
          <thead><tr><th>플랫폼</th><th>계정 소유</th><th>상태</th><th>상세</th></tr></thead>
          <tbody>
            <tr><td><strong>Google Play (Android)</strong></td><td><span class="badge b-wait">개발자(sj) 계정</span></td><td><span class="badge b-done">출시·승인</span></td><td><code>com.celebhospital.patient</code> — 2026-03-10 승인, 정상 게시 중. <strong>인수자 계정으로 양도 필요</strong> (아래)</td></tr>
            <tr><td><strong>App Store (iOS)</strong></td><td><span class="badge b-done">클라이언트 계정</span></td><td><span class="badge b-done">심사 승인</span> · <span class="badge b-wait">출시 대기</span></td><td>v2.0.1 <strong>"개발자 출시 대기 중"</strong> — 클라이언트 Apple 계정(<code>oracleclinicdev@gmail.com</code>, Oracle Land Co.,Ltd.)에 이미 등록. <strong>양도 불필요</strong>, 출시 버튼만</td></tr>
          </tbody>
        </table>
        <div class="note">✅ <strong>iOS는 이미 인수자(Oracle Land Co.,Ltd.) Apple 계정에 등록</strong>되어 있어 양도가 필요 없습니다. App Store Connect → 버전 → <strong>"이 버전 출시"</strong> 클릭 시 즉시 게시 (심사는 이미 통과).</div>

        <h3>⚠️ 앱 양도(이관) — <span style="color:#d9342b;">Android만 미완</span></h3>
        <div class="warn">
          <strong>Android만 양도가 남았습니다.</strong> 사유: 인수자(셀럽몰/오라클랜드)의 <strong>구글 개발자 계정(ORACLE ENTER)이 '개인' 유형으로 잘못 생성되어 신원확인이 불가</strong>(프로필/앱 삭제 상태). 그래서 부득이 <strong>개발자(sj) 계정으로 먼저 출시</strong>했고, 인수자가 <strong>DUNS 번호로 새 '조직(Organization)' 구글 계정을 생성 중</strong>입니다. <strong>새 계정 ID를 전달받으면 즉시 양도 가능</strong>합니다. (iOS는 위와 같이 양도 불필요)
        </div>

        <h4 style="margin:14px 0 6px; font-size:14px; color:#155e75;">🤖 Google Play 앱 양도 절차 (Android)</h4>
        <table>
          <thead><tr><th>#</th><th>단계</th><th>주체</th><th>상태</th></tr></thead>
          <tbody>
            <tr><td>1</td><td><strong>클라이언트 조직(Organization) 구글 개발자 계정 준비</strong> — DUNS 번호로 생성·신원확인 (기존 ORACLE ENTER 개인계정은 폐기)</td><td>인수자(셀럽몰)</td><td><span class="badge b-wait">진행 중</span></td></tr>
            <tr><td>2</td><td>완성된 계정의 <strong>개발자 ID(거래 ID)</strong> + 결제 프로필 정보 전달</td><td>인수자 → 개발자</td><td><span class="badge b-wait">대기</span></td></tr>
            <tr><td>3</td><td>Play Console → 설정 → <strong>개발자 계정 → 앱 이전(App transfers)</strong> → 대상 개발자 ID 입력 → 이전 요청</td><td>개발자(sj)</td><td>—</td></tr>
            <tr><td>4</td><td>양수 계정에서 이전 <strong>수락</strong> → 리뷰·평점·설치수 유지된 채 이관 완료</td><td>인수자</td><td>—</td></tr>
          </tbody>
        </table>
        <div class="warn">📌 <strong>인수자에게 필요한 것 (Android 양도용)</strong>: ① <strong>조직 구글 개발자 계정</strong>(DUNS 필수, 1~4주 소요) 완성 ② 그 계정의 <strong>개발자 ID(거래 ID)</strong> 전달. 이 정보가 오면 양도는 콘솔에서 수 분 내 요청 가능합니다.</div>

        <h3>🖥 서버·도메인·인프라 소유 현황 (양도 불필요 — 이미 인수자 소유)</h3>
        <table>
          <thead><tr><th>자산</th><th>소유</th><th>비고</th></tr></thead>
          <tbody>
            <tr><td>운영 서버 (Contabo)</td><td><span class="badge b-done">인수자 계정</span></td><td>5.104.82.35 — 인수자(oraclenter@gmail.com)가 직접 결제·계약. <strong>양도 불필요, 접속정보 인계만</strong></td></tr>
            <tr><td>도메인 (seoulbeauty.app)</td><td><span class="badge b-done">인수자 계정</span></td><td>가비아 — 인수자(oracleland20) 구매. 계정 인계만</td></tr>
            <tr><td>DNS / HTTPS (Cloudflare)</td><td><span class="badge b-done">인수자 계정</span></td><td>인수자(oraclenter@gmail.com) 가입. 계정 인계만</td></tr>
            <tr><td>Firebase (FCM)</td><td><span class="badge b-wait">권한 이전 필요</span></td><td>프로젝트 hospital-8425b — 콘솔 소유자 권한을 인수자 구글계정으로 초대 필요</td></tr>
          </tbody>
        </table>
        <div class="note">✅ <strong>서버·도메인·Cloudflare는 처음부터 인수자 명의</strong>라 앱처럼 "양도"가 필요 없고, <strong>계정/SSH 접속 정보 인계</strong>만 하면 됩니다 (계정 섹션 참조). Firebase만 콘솔 권한 이전이 필요합니다.</div>

        <h3>📦 빌드/제출 메모</h3>
        <ul class="compact">
          <li>Android: Play 게시 상태 → <strong>업데이트 빌드</strong>(versionCode 30 > 게시본). Android Studio(E:\0maskapp\seoulbeauty) 또는 Mac seoulbeauty_apps에서 AAB 빌드</li>
          <li>iOS: 출시 버튼 클릭으로 게시. 차기 빌드는 Flutter 소스(<code>~/s_app/patient_app/ios/Runner.xcworkspace</code>) → Xcode Archive → App Store Connect 업로드</li>
          <li>서명키: Android <code>celebhospital_patient.jks</code> 보유 / iOS Apple Distribution 인증서 Mac 보유</li>
        </ul>
      </div>
    </section>

    <!-- ━━━ 계정 ━━━ -->
    <section class="section" id="sec-accounts">
      <div class="card">
        <h2>계정 / 접속 정보</h2>
        <h3>🌐 서비스 계정</h3>
        <table>
          <thead><tr><th>구분</th><th>주소</th><th>계정</th><th>비고</th></tr></thead>
          <tbody>
            <tr><td>서울미녀 관리자</td><td><code>/hairwang/app/dashboard.php</code></td><td><code>admin</code> / <code>admin123</code></td><td>super_admin (전체 권한)</td></tr>
            <tr><td>병원 테스트 계정</td><td>동일</td><td><code>hospital_test</code> / <code>hospital123</code></td><td>hospital_admin · 병원 id 8093</td></tr>
            <tr><td>HMS 관리자</td><td>https://hms.seoulbeauty.app</td><td><code>admin@hms.com</code> / <code>123456</code></td><td>Laravel 백오피스</td></tr>
            <tr><td>그누보드 관리자</td><td><code>/hairwang/adm/</code></td><td colspan="2">SSO 통합 — 서울미녀 관리자 로그인으로 접근</td></tr>
            <tr><td>메일 관리자</td><td>https://mail.seoulbeauty.app</td><td><code>admin@seoulbeauty.app</code> / <code>SeoulB3auty!2026</code></td><td>Mailcow 관리 콘솔</td></tr>
            <tr><td>GitHub</td><td>github.com/appdosa1004-cyber/site_get</td><td colspan="2">아이디 회신 시 Collaborator 초대</td></tr>
          </tbody>
        </table>

        <h3>🖥 메인 서버 (contabo1) — 운영/개발</h3>
        <table>
          <thead><tr><th>구분</th><th>접속 정보</th></tr></thead>
          <tbody>
            <tr><td><strong>SSH</strong></td><td>호스트 <code>5.104.82.35</code> · 포트 <code>22</code> · 계정 <code>root</code> / <code><REMOVED_SSH_PASSWORD></code><br><small><code>ssh root@5.104.82.35</code></small></td></tr>
            <tr><td><strong>MariaDB</strong></td><td>호스트 <code>localhost</code> · 계정 <code>root</code> / <code><REMOVED_DB_PASSWORD></code> · DB <code>hms_saas</code><br><small><code>mysql -uroot -p hms_saas</code></small></td></tr>
            <tr><td>phpMyAdmin</td><td>서버 내 설치 <code>/var/www/html/phpMyAdmin-5.2.2</code> (vhost 미연결 — Alias 추가 시 사용)</td></tr>
            <tr><td>OS / 스택</td><td>Rocky Linux 9.7 · 8core/23GB/800GB · Apache 2.4 · PHP 8.2 · MariaDB 10.11 · Cloudflare proxy</td></tr>
            <tr><td>웹 루트</td><td><code style="font-size:11px;">/var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas/</code></td></tr>
          </tbody>
        </table>

        <h3>🍎 iOS 빌드 (Mac Mini)</h3>
        <table>
          <tbody>
            <tr><th>접속</th><td>호스트 <code>192.168.0.75</code> (내부망) · 계정 <code>cr1004</code> (SSH 키 인증) · macOS 15.x · Xcode 26.3</td></tr>
            <tr><th>프로젝트 (iOS)</th><td><code>~/s_app/patient_app/ios/Runner.xcworkspace</code> (Flutter 앱 iOS 빌드 — Android와 동일 소스)<br><small style="color:#888">※ MarsApp(Swift WebView)은 deprecated → 외장하드 <code>mac_backup/deprecated_swift_webview_NOT_USE/</code> 이동</small></td></tr>
          </tbody>
        </table>

        <h3>🗄 데이터베이스 상세 (contabo1 내부, MariaDB)</h3>
        <table>
          <thead><tr><th>DB명</th><th>용도</th><th>비고</th></tr></thead>
          <tbody>
            <tr><td><code>hms_saas</code></td><td><strong>메인 통합 DB</strong> (서울미녀+HMS+Gnuboard 440테이블)</td><td>운영</td></tr>
            <tr><td><code>hairwang</code></td><td>레거시 연동 (190테이블)</td><td>유지</td></tr>
            <tr><td><code>hms_saas_dev</code> / <code>hms_saas_test</code></td><td>개발/테스트 사본</td><td>—</td></tr>
            <tr><td colspan="3">공통 접속: <code>root</code> / <code><REMOVED_DB_PASSWORD></code> (localhost) · 서울미녀 접속 설정 파일: <code>gangnamunni/dbconfig.php</code> · HMS: <code>.env</code></td></tr>
          </tbody>
        </table>

        <h3>📧 메일서버 (Mailcow · Docker)</h3>
        <table>
          <tbody>
            <tr><th>관리 콘솔</th><td>https://mail.seoulbeauty.app · <code>admin@seoulbeauty.app</code> / <code>SeoulB3auty!2026</code></td></tr>
            <tr><th>위치</th><td>contabo1 <code>/opt/mailcow-dockerized/</code> (Docker 18컨테이너)</td></tr>
          </tbody>
        </table>

        <div class="warn">🔐 이 화면은 <strong>관리자 로그인 사용자에게만</strong> 표시됩니다(비로그인 시 로그인 페이지로 이동). <strong>인수 완료 확인 후 서버·DB·메일 비밀번호를 반드시 변경</strong>하세요 — 변경 시 DB 접속 설정 파일(<code>dbconfig.php</code>, HMS <code>.env</code>)도 함께 갱신해야 크론·서비스가 정상 동작합니다.</div>
      </div>
    </section>

    <!-- ━━━ API/크론 ━━━ -->
    <section class="section" id="sec-apis">
      <div class="card">
        <h2>API / 크론 / 상시 서비스</h2>
        <h3>API 아키텍처 (REST)</h3>
        <table>
          <tbody>
            <tr><th>HMS API</th><td><code>/api/*</code> (Laravel Sanctum 인증) — 모바일 앱 메인 API</td></tr>
            <tr><th>서울미녀 AJAX</th><td>루트 <code>api_*.php</code> 44개 (검색/채팅/쿠폰/포인트/지갑/CPV/신고 등)</td></tr>
            <tr><th>폐기 API</th><td><code>/kv-api/</code> (KiviCare) — 2026-03-12 폐기, 500 반환. 절대 사용 금지</td></tr>
          </tbody>
        </table>
        <h3>크론 (root crontab)</h3>
        <table>
          <thead><tr><th>주기</th><th>스크립트</th><th>역할</th></tr></thead>
          <tbody>
            <tr><td>매 1분</td><td><code>sync/cron_sync.sh</code></td><td>HMS↔강남언니↔Gnuboard 양방향 동기화</td></tr>
            <tr><td>매 30분</td><td><code>cron_treatment_scores.php</code></td><td>시술 점수 집계</td></tr>
            <tr><td>03:00</td><td><code>cron_purge_trash.php</code></td><td>메일 휴지통 정리</td></tr>
            <tr><td>00:05</td><td><code>cron_cpv_daily.php</code></td><td>CPV 일별 정산</td></tr>
          </tbody>
        </table>
        <h3>systemd 서비스</h3>
        <table>
          <thead><tr><th>서비스</th><th>역할</th></tr></thead>
          <tbody>
            <tr><td><code>httpd</code> / <code>mariadb</code></td><td>웹 / DB</td></tr>
            <tr><td><code>hms-artisan.service</code></td><td>HMS Laravel artisan serve</td></tr>
            <tr><td><code>laravel-api.service</code></td><td>hospital-api (포트 8000)</td></tr>
            <tr><td><code>auto-test-v2.service</code></td><td>QA 자동화 엔진 (Node.js)</td></tr>
            <tr><td>Docker (mailcow 18종)</td><td>메일서버</td></tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- ━━━ 잔여 ━━━ -->
    <section class="section" id="sec-remaining">
      <div class="card">
        <h2>잔여 작업 / 협의 필요 항목</h2>
        <div class="warn" style="border-left-color:#f5a524; font-size:14px;">
          📌 <strong>계약 기준 "최종 완료"로 남은 핵심은 2건뿐입니다</strong> — 운영 기능·앱(Android 출시·iOS 출시대기)은 완료 상태.
        </div>
        <table>
          <thead><tr><th>핵심 잔여</th><th>상태</th><th>내용</th></tr></thead>
          <tbody>
            <tr><td><strong>① CPV 과금 시스템</strong></td><td><span class="badge b-wait">개발됨·비활성</span></td><td>핵심 수익모델(3/11 요건). 14파일+8테이블 구현·배포 완료, 안전모드(비활성). <strong>기능 확인서 컨펌 시 활성화</strong></td></tr>
            <tr><td><strong>② Figma 디자인 고도화</strong></td><td><span class="badge b-wait">부분</span></td><td>3/20 요건(GNB 4탭·지역+시술 복합필터·커뮤니티 CRUD·마이페이지). 기능 일부 구현, <strong>디자인 반영 미완</strong></td></tr>
          </tbody>
        </table>
        <h3>🔵 발주사 결정·제공이 필요한 항목 (8건)</h3>
        <table>
          <thead><tr><th>#</th><th>항목</th></tr></thead>
          <tbody>
            <tr><td>1</td><td>Figma 디자인 반영 범위 확정 (전체 리뉴얼 vs 부분 반영)</td></tr>
            <tr><td>2</td><td>카카오 로그인 — JS 키 발급 + 사업자 등록</td></tr>
            <tr><td>3</td><td>지도 API — 카카오 vs 네이버 선택 + 키 발급</td></tr>
            <tr><td>4</td><td>시술 카테고리 분류표 최종 확정</td></tr>
            <tr><td>5</td><td>병원 부관리자 계정 발급/접속 절차 정책</td></tr>
            <tr><td>6</td><td>이벤트/광고 이미지 등록 방식 (URL vs 직접 업로드)</td></tr>
            <tr><td>7</td><td>평점 산정 로직 정책 (단순 평균 vs 가중치)</td></tr>
            <tr><td>8</td><td>'성형외과 전문의' 표기 방식</td></tr>
          </tbody>
        </table>
        <h3>📅 향후 개발 목록 (마일스톤 Phase 2~5, 75건)</h3>
        <div class="note">상세 목록: 패키지 <code>04_docs/</code> 및 GitHub의 <code>_milestone_20260527_1차검수.md</code> · <code>_remaining_dev_tasks_20260612.html</code> 참고.
        Phase 2(15건) 병원리스트/탭 개선 → Phase 3(20건) UI/UX·예약 → Phase 4(17건) Figma 리뉴얼·GNB 개편·지도·카카오 → Phase 5(23건) 관리자·매뉴얼.</div>
        <h3>🎨 Figma 매칭 잔여 (5건 — 기능은 정상 동작) <a class="openbtn" style="background:#7c3aed;font-size:11px;" href="https://hms.seoulbeauty.app/handover_test_20260612/compare.html" target="_blank">Figma↔구현 12화면 비교 ↗</a></h3>
        <ul class="compact">
          <li>메인 카테고리 그리드 컬럼 수 (3 vs 4) — 협의</li>
          <li>메인 이벤트 섹션 형태 (배너 vs 카드 3개) — 협의</li>
          <li>상단 헤더 표시 (비로그인 상태 디자인) — 협의</li>
          <li>마이페이지 카운터 / 병원페이지 도구 — Figma보다 풍부, 유지 권장</li>
        </ul>
      </div>
    </section>

    <!-- ━━━ 체크리스트 ━━━ -->
    <section class="section" id="sec-checklist">
      <div class="card">
        <h2>인수 확인 체크리스트</h2>
        <div class="meta">인수자가 각 항목 수령·확인 후 체크해 주세요. (체크 상태는 이 브라우저에 저장됩니다)</div>
        <div id="checkList">
          <div class="check-item"><input type="checkbox" data-k="c1"><span><strong>웹 소스</strong> — 01_web_source/ tar 2종 수령 및 압축 해제 확인</span></div>
          <div class="check-item"><input type="checkbox" data-k="c2"><span><strong>앱 소스</strong> — 02_app_source/ zip 4종 수령 확인</span></div>
          <div class="check-item"><input type="checkbox" data-k="c3"><span><strong>DB</strong> — 03_database/ 덤프 임포트 테스트 (gunzip | mysql)</span></div>
          <div class="check-item"><input type="checkbox" data-k="c4"><span><strong>문서</strong> — HANDOVER_README.md + ERD 열람</span></div>
          <div class="check-item"><input type="checkbox" data-k="c5"><span><strong>GitHub</strong> — Collaborator 초대 수락, clone 확인</span></div>
          <div class="check-item"><input type="checkbox" data-k="c6"><span><strong>서버</strong> — SSH 접속 확인 (root)</span></div>
          <div class="check-item"><input type="checkbox" data-k="c7"><span><strong>관리자</strong> — admin 계정 로그인 + 주요 메뉴 동작 확인</span></div>
          <div class="check-item"><input type="checkbox" data-k="c8"><span><strong>키류</strong> — Android 키스토어 / iOS .p12·프로비저닝 수령</span></div>
          <div class="check-item"><input type="checkbox" data-k="c9"><span><strong>Firebase</strong> — 콘솔 프로젝트 권한 이전 확인</span></div>
          <div class="check-item"><input type="checkbox" data-k="c10"><span><strong>도메인/Cloudflare</strong> — DNS 제어권 이전 협의 완료</span></div>
          <div class="check-item"><input type="checkbox" data-k="c11"><span><strong>스토어</strong> — Play Console / App Store Connect 권한·앱 이전 협의 완료</span></div>
          <div class="check-item"><input type="checkbox" data-k="c12"><span><strong>크론/서비스</strong> — 동기화 크론 4종 + systemd 서비스 동작 확인</span></div>
        </div>
        <div class="note">✅ 전 항목 확인 후 인수 완료를 회신해 주시면 인수인계가 종결됩니다. 이후 운영 비밀번호 변경을 권장합니다.</div>
      </div>
    </section>


    <!-- GitHub 작업 흐름 -->
    <section class="section" id="sec-workflow">
      <div class="card">
        <h1>&#128640; GitHub 작업 흐름 &mdash; 인수자 필독</h1>
        <div class="meta">최종 업데이트: 2026-06-17 &middot; 이 섹션을 먼저 읽으세요</div>

        <h2>&#128230; 저장소 구조</h2>
        <table>
          <thead><tr><th style="width:40%">저장소</th><th>내용</th><th>역할</th></tr></thead>
          <tbody>
            <tr><td><code>appdosa1004-cyber/hms-saas</code></td><td>서버 전체 소스 (Laravel + hairwang_full + hospital-api)</td><td><span class="badge b-done">메인 작업 저장소</span></td></tr>
            <tr><td><code>appdosa1004-cyber/site_get</code></td><td>작업 도구 + 배포 스크립트</td><td><span class="badge b-gray">보조 도구</span></td></tr>
          </tbody>
        </table>

        <h2>&#128193; 서버 경로 구조</h2>
        <table>
          <thead><tr><th style="width:50%">경로</th><th>내용</th><th>비고</th></tr></thead>
          <tbody>
            <tr><td><code>hairwang_full/gangnamunni/</code></td><td>&#11088; 서울미녀 PHP 메인 사이트</td><td><strong>가장 많이 수정하는 폴더</strong></td></tr>
            <tr><td><code>hairwang_full/gangnamunni/uploads/</code></td><td>사용자 업로드 이미지</td><td>git 제외 — 서버에만 존재</td></tr>
            <tr><td><code>hairwang_full/plugin/, theme/, bbs/</code></td><td>Gnuboard 컴포넌트</td><td>git 관리</td></tr>
            <tr><td><code>hospital-api/Modules/</code></td><td>&#11088; HMS API 핵심 모듈</td><td>git 관리</td></tr>
            <tr><td><code>hospital-api/vendor/, vendor/</code></td><td>Composer 패키지</td><td>git 제외 — composer install로 재설치</td></tr>
            <tr><td><code>.env</code></td><td>DB/API 환경변수</td><td>git 제외 — 서버 직접 관리</td></tr>
            <tr><td><code>/var/www/html/backups/</code></td><td>&#128190; 서버 백업 폴더</td><td>작업 전 백업 필수</td></tr>
          </tbody>
        </table>

        <h2>&#128296; 최초 세팅 (처음 한 번만)</h2>

        <div class="warn">&#9888; <strong>왜 압축 파일부터 받아야 하나요?</strong><br>
        GitHub에는 이미지(uploads)·데이터(data)·환경설정(.env)·패키지(vendor)가 없습니다.
        git clone만 하면 불완전한 상태입니다. <strong>반드시 압축 파일 먼저 받고 → GitHub 연동</strong> 순서로 진행하세요.</div>

        <h3>1단계 — 서버 최종 압축 파일 직접 받기 (완전한 환경 구성)</h3>

        <div class="warn">&#9888; <strong>반드시 인수 시점의 최신 압축본을 직접 만들어서 받아야 합니다.</strong><br>
        재작업이 진행 중일 수 있으므로, 서버에 접속해서 <strong>지금 이 순간</strong> 최종 압축본을 생성한 뒤 다운받으세요.<br>
        미리 만들어진 파일을 그냥 받으면 최신 소스가 아닐 수 있습니다.</div>

        <div style="background:var(--code-bg);border-radius:10px;padding:16px 18px;margin:10px 0;font-family:Consolas,monospace;font-size:13px;line-height:1.8;">
<pre style="margin:0;white-space:pre-wrap;">## [서버에서] 최종 압축본 직접 생성
ssh root@5.104.82.35

FNAME="hms-saas-v5.8.0_$(date +%Y%m%d_%H%M%S).tar.gz"
tar -czf /var/www/html/backups/$FNAME   /var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas/

echo "완료: $FNAME"
ls -lh /var/www/html/backups/$FNAME</pre>
        </div>

        <div style="background:var(--code-bg);border-radius:10px;padding:16px 18px;margin:10px 0;font-family:Consolas,monospace;font-size:13px;line-height:1.8;">
<pre style="margin:0;white-space:pre-wrap;">## [PC에서] 위에서 출력된 파일명으로 다운로드 (Git Bash 또는 WSL)
# 예: hms-saas-v5.8.0_20260617_153000.tar.gz
scp root@5.104.82.35:/var/www/html/backups/여기에_위_파일명.tar.gz /c/hms-src.tar.gz

## 압축 해제
mkdir -p /h/seoul_beauty_project/hms-saas
tar -xzf /c/hms-src.tar.gz --strip-components=8 -C /h/seoul_beauty_project/hms-saas</pre>
        </div>
        <div class="note">&#9989; 이 단계 완료 후 <code>H:\seoul_beauty_project\hms-saas\</code> 폴더에 <strong>uploads, data, .env, vendor 포함한 완전한 소스</strong>가 있습니다.</div>

        <h3>2단계 — GitHub 연동 (이후 개발 흐름 연결)</h3>
        <div style="background:var(--code-bg);border-radius:10px;padding:16px 18px;margin:10px 0;font-family:Consolas,monospace;font-size:13px;line-height:1.8;">
<pre style="margin:0;white-space:pre-wrap;">cd H:/seoul_beauty_project/hms-saas

# 기존 git 정보 제거 후 GitHub 저장소 연결
rm -rf .git
git init
git remote add origin https://github.com/appdosa1004-cyber/hms-saas.git
git fetch origin master
git checkout -b master --track origin/master
git reset origin/master</pre>
        </div>
        <div class="note">&#9989; 이후부터는 <code>git pull origin master</code> 한 줄로 최신 소스를 받을 수 있습니다.</div>

        <h3>3단계 — Composer 패키지 설치</h3>
        <div style="background:var(--code-bg);border-radius:10px;padding:16px 18px;margin:10px 0;font-family:Consolas,monospace;font-size:13px;line-height:1.8;">
<pre style="margin:0;white-space:pre-wrap;">cd H:/seoul_beauty_project/hms-saas
composer install
cd hospital-api &amp;&amp; composer install &amp;&amp; cd ..</pre>
        </div>

        <h2>&#128295; .dev/ 개발 환경 폴더 — 먼저 확인하세요</h2>
        <div class="note">&#9989; <strong>서버 접속 정보·배포 스크립트·작업 규칙이 모두 여기 있습니다.</strong>
        Cursor AI / VS Code에서 이 폴더를 열면 바로 작업 가능합니다.</div>
        <table>
          <thead><tr><th style="width:35%">파일</th><th>설명</th></tr></thead>
          <tbody>
            <tr><td><code>.dev/CLAUDE.md</code></td><td>프로젝트 규칙 (금지사항·DB구조·배포흐름) — Claude/Cursor가 자동 읽음</td></tr>
            <tr><td><code>.dev/server_info.md</code></td><td>서버·DB·계정 접속 정보 <span class="badge b-no">GitHub 비공개</span> 별도 전달</td></tr>
            <tr><td><code>.dev/deploy.py</code></td><td>git push 후 서버 자동 git pull</td></tr>
            <tr><td><code>.dev/sync_check.py</code></td><td>서버·PC·GitHub 3자 커밋 동일성 확인</td></tr>
            <tr><td><code>.dev/cursor_test_prompt.md</code></td><td>Cursor AI 연동 테스트 + 일상 작업 프롬프트</td></tr>
          </tbody>
        </table>
        <div style="background:var(--code-bg);border-radius:10px;padding:14px 18px;margin:10px 0;font-family:Consolas,monospace;font-size:13px;line-height:1.8;">
<pre style="margin:0;white-space:pre-wrap;">python .dev/sync_check.py   # 동기화 확인
python .dev/deploy.py       # 서버 배포 (git push 후)</pre>
        </div>

        <h2>&#128204; GitHub 저장소 유의사항 — 꼭 읽으세요</h2>
        <table>
          <thead><tr><th style="width:30%">저장소</th><th>용도</th><th>주의</th></tr></thead>
          <tbody>
            <tr>
              <td><a href="https://github.com/appdosa1004-cyber/hms-saas" target="_blank"><code>hms-saas</code></a></td>
              <td>&#11088; <strong>메인 작업 저장소</strong> — 서버 전체 소스 (PHP, Laravel, HMS API)<br>이 폴더에서 수정 → git push → 서버 git pull</td>
              <td>새 폴더에 프로젝트를 만들어도 <strong>이 저장소를 remote로 연결</strong>해야 합니다. 별도 저장소 새로 만들지 마세요.</td>
            </tr>
            <tr>
              <td><a href="https://github.com/appdosa1004-cyber/site_get" target="_blank"><code>site_get</code></a></td>
              <td>배포 스크립트·작업 도구 모음 (인수인계 파일 포함)</td>
              <td>서비스 소스는 여기에 없습니다. 혼용 금지.</td>
            </tr>
          </tbody>
        </table>

        <div class="warn">&#9888; <strong>새 PC나 새 폴더에서 작업 시작할 때</strong><br>
        &bull; 1단계: 서버 압축본 직접 생성 &amp; 다운로드 → 압축 해제<br>
        &bull; 2단계: <code>git remote add origin https://github.com/appdosa1004-cyber/hms-saas.git</code> 으로 기존 저장소 연결<br>
        &bull; 절대 <code>git init</code>만 하고 GitHub에 새 저장소를 만들지 마세요 — 이력이 분리됩니다.</div>

        <div class="note">&#9989; <strong>Cursor AI / VS Code에서 작업 시</strong><br>
        <code>H:\seoul_beauty_project\hms-saas\</code> 폴더를 열고 → 파일 수정 → 터미널에서 <code>git push origin master</code> → 서버에서 <code>git pull origin master</code></div>

        <h2>&#9999; 일반 작업 흐름 (매번)</h2>
        <div style="background:var(--code-bg);border-radius:10px;padding:16px 18px;margin:10px 0;font-family:Consolas,monospace;font-size:13px;line-height:1.8;">
<pre style="margin:0;white-space:pre-wrap;"># [PC] 파일 수정 후
git add 수정한파일.php
git commit -m "수정 내용 설명"
git push origin master

# [서버] 반영
ssh root@5.104.82.35
cd /var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas
git pull origin master

# gangnamunni PHP 변경 → Apache 즉시 서빙 (재시작 불필요)
# Laravel 변경 시:
php artisan cache:clear &amp;&amp; php artisan config:clear</pre>
        </div>

        <div class="note">&#9989; <strong>gangnamunni/ PHP 수정은 git pull 즉시 반영</strong>됩니다. 빌드나 재시작 불필요.</div>
        <div class="warn">&#9888; <strong>uploads/, data/ 폴더</strong>는 git에 없습니다. 사용자 업로드 이미지가 저장되며 서버가 원본입니다. git에 추가하지 마세요.</div>

        <h2>&#128196; 주요 작업 폴더</h2>
        <table>
          <thead><tr><th>작업 목적</th><th>수정 위치</th></tr></thead>
          <tbody>
            <tr><td>서울미녀 웹 페이지 수정</td><td><code>hairwang_full/gangnamunni/*.php</code></td></tr>
            <tr><td>관리자 대시보드</td><td><code>hairwang_full/gangnamunni/admin_*.php</code></td></tr>
            <tr><td>공통 헤더/푸터</td><td><code>hairwang_full/gangnamunni/includes/</code></td></tr>
            <tr><td>HMS API 기능</td><td><code>hospital-api/Modules/</code></td></tr>
            <tr><td>Laravel 백엔드</td><td><code>app/, routes/, resources/</code></td></tr>
            <tr><td>CSS/JS 스타일</td><td><code>hairwang_full/gangnamunni/css/, js/</code></td></tr>
          </tbody>
        </table>

        <h2>&#128683; 개발 필수 규칙</h2>
        <table>
          <thead><tr><th>구분</th><th>규칙</th></tr></thead>
          <tbody>
            <tr><td><span class="badge b-no">금지</span></td><td>Gnuboard <strong>init.php 사용 금지</strong> — 세션 충돌 발생. <code>gangnamunni/dbconfig.php</code> 사용</td></tr>
            <tr><td><span class="badge b-no">금지</span></td><td><strong>KiviCare /kv-api/ 사용 금지</strong> — HMS <code>/api/*</code> 만 사용</td></tr>
            <tr><td><span class="badge b-no">금지</span></td><td><code>gangnamunni/</code> <strong>하위 서브디렉토리에 PHP 생성 금지</strong> — admin_ 접두사로 루트에 배치</td></tr>
            <tr><td><span class="badge b-wait">필수</span></td><td>SQL <strong>Prepared Statement 필수</strong> — 사용자 입력 직접 삽입 금지</td></tr>
            <tr><td><span class="badge b-wait">필수</span></td><td>세션 변수 접두사 <strong>ad_admin_</strong> 고정 (예: <code>$_SESSION['ad_admin_logged_in']</code>)</td></tr>
            <tr><td><span class="badge b-done">권장</span></td><td>서버 작업 전 <strong>/var/www/html/backups/ 에 백업</strong> 생성 후 진행</td></tr>
          </tbody>
        </table>

        <h2>&#128241; 서버 접속 정보</h2>
        <table>
          <tbody>
            <tr><th style="width:30%">SSH 접속</th><td><code>ssh root@5.104.82.35</code> (비밀번호: <REMOVED_SSH_PASSWORD>)</td></tr>
            <tr><th>DB 접속</th><td><code>mysql -u root -p</code> (비밀번호: <REMOVED_DB_PASSWORD>) &rarr; DB: hms_saas</td></tr>
            <tr><th>Apache 재시작</th><td><code>systemctl restart httpd</code></td></tr>
            <tr><th>HMS artisan 재시작</th><td><code>systemctl restart hms-artisan</code></td></tr>
            <tr><th>GitHub 저장소</th><td><a href="https://github.com/appdosa1004-cyber/hms-saas" target="_blank">github.com/appdosa1004-cyber/hms-saas</a></td></tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- 서버 백업 정책 -->
    <section class="section" id="sec-responsive">
      <div class="card">
        <h1>&#128190; 서버 백업 정책</h1>
        <div class="meta">백업 경로: /var/www/html/backups/ &middot; 2026-06-17 확정</div>

        <div class="note">&#9989; <strong>모든 서버 작업 전 반드시 이 폴더에 백업 생성 후 진행</strong>하세요. 디스크 여유 448GB+ 충분합니다.</div>

        <h2>&#128193; 백업 폴더</h2>
        <table>
          <tbody>
            <tr><th style="width:30%">서버 백업 폴더</th><td><code>/var/www/html/backups/</code></td></tr>
            <tr><th>서버</th><td>5.104.82.35 (Contabo, root/<REMOVED_SSH_PASSWORD>)</td></tr>
            <tr><th>현재 백업</th><td><code>hms-saas-v5.8.0_20260617_075639.tar.gz</code> (1.9GB, 2026-06-17)</td></tr>
            <tr><th>디스크 여유</th><td>800GB 중 448GB 여유</td></tr>
          </tbody>
        </table>

        <h2>&#128295; 백업 명령어 (작업 전 실행)</h2>
        <div style="background:var(--code-bg);border-radius:10px;padding:16px 18px;margin:10px 0;font-family:Consolas,monospace;font-size:13px;line-height:1.8;">
<pre style="margin:0;white-space:pre-wrap;">ssh root@5.104.82.35

cd /var/www/html/backups
tar -czf hms-saas-v5.8.0_$(date +%Y%m%d_%H%M%S).tar.gz /var/www/html/multi-hospital-management-system-saas-hospital/

ls -lh /var/www/html/backups/</pre>
        </div>

        <h2>&#128197; 백업 히스토리</h2>
        <table>
          <thead><tr><th>파일명</th><th>생성일</th><th>크기</th><th>사유</th></tr></thead>
          <tbody>
            <tr><td><code>hms-saas-v5.8.0_20260617_075639.tar.gz</code></td><td>2026-06-17</td><td>1.9GB</td><td>인수인계 준비 — GitHub 연동 작업 전 전체 백업</td></tr>
          </tbody>
        </table>

        <h2>&#9851; 원복 방법 (장애 발생 시)</h2>
        <div style="background:var(--code-bg);border-radius:10px;padding:16px 18px;margin:10px 0;font-family:Consolas,monospace;font-size:13px;line-height:1.8;">
<pre style="margin:0;white-space:pre-wrap;">cd /var/www/html/backups
tar -xzf hms-saas-v5.8.0_20260617_075639.tar.gz -C /

systemctl restart httpd
systemctl restart hms-artisan</pre>
        </div>

        <div class="warn">&#9888; git으로 소스만 되돌리려면: <code>git log</code> &rarr; 커밋 ID 확인 후 <code>git checkout {커밋ID}</code>. DB는 별도 mysqldump 백업 필요.</div>
      </div>
    </section>

  </main>
</div>

<script>
// 테마 토글
function toggleTheme() {
  var html = document.documentElement;
  var next = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
  html.setAttribute('data-bs-theme', next);
  localStorage.setItem('handover_theme', next);
  updateThemeBtn();
}
function updateThemeBtn() {
  var btn = document.getElementById('themeToggle');
  var dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
  btn.textContent = dark ? '☀️ 주간' : '🌙 야간';
}
updateThemeBtn();

// 그룹 접기/펼치기
document.querySelectorAll('.nav-group > .gtitle').forEach(function(t) {
  t.addEventListener('click', function() {
    this.parentNode.classList.toggle('collapsed');
  });
});

// 좌측 메뉴 네비게이션
document.querySelectorAll('#sideNav a').forEach(function(a) {
  a.addEventListener('click', function(e) {
    var sec = this.getAttribute('data-sec');
    if (!sec) return; // data-sec 없는 외부링크(검증 리포트 등)는 기본 동작 허용
    e.preventDefault();
    document.querySelectorAll('#sideNav a').forEach(function(x){ x.classList.remove('active'); });
    this.classList.add('active');
    document.querySelectorAll('.section').forEach(function(s){ s.classList.remove('active'); });
    document.getElementById('sec-' + sec).classList.add('active');
    history.replaceState(null, '', '#' + sec);
    window.scrollTo(0, 0);
  });
});
// 해시 직접 진입
(function(){
  var h = location.hash.replace('#','');
  if (h && document.getElementById('sec-' + h)) {
    document.querySelector('#sideNav a[data-sec="' + h + '"]').click();
  }
})();

// 체크리스트 localStorage 저장
document.querySelectorAll('#checkList input[type=checkbox]').forEach(function(cb) {
  var k = 'handover_chk_' + cb.getAttribute('data-k');
  cb.checked = localStorage.getItem(k) === '1';
  cb.addEventListener('change', function() {
    localStorage.setItem(k, this.checked ? '1' : '0');
  });
});
// 서버구조 섹션 내 탭 전환 (폴더구조 / 백업)
function sdTab(btn, paneId){
  var sec = btn.closest('.section');
  sec.querySelectorAll('.sd-tab').forEach(function(b){
    b.classList.remove('active');
    b.style.background = 'transparent'; b.style.color = 'inherit';
  });
  btn.classList.add('active');
  btn.style.background = getComputedStyle(document.documentElement).getPropertyValue('--side-active') || '#ae1932';
  btn.style.color = '#fff';
  sec.querySelectorAll('.sd-pane').forEach(function(p){ p.style.display = 'none'; });
  var pane = document.getElementById(paneId);
  if(pane) pane.style.display = 'block';
}
</script>
</body>
</html>
