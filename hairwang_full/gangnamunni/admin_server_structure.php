<?php
// ============================================================================
// [Handover - 2026-06-15] 서버 폴더 구조 상세 설명 페이지
// 원인 : 인수인계 시 hms-saas 폴더 구조를 한눈에 이해할 수 있는 가이드 필요
// 변경 : admin_server_structure.php 신규 — 트리 + 폴더별 설명 + 용량
// 영향 : 사이드바 "📂 서버 폴더 구조" 메뉴
// ============================================================================
session_start();

$admin_page_title = '서버 폴더 구조';
$admin_current = 'server_structure';

require_once __DIR__ . '/dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die('DB 연결 실패: ' . mysqli_connect_error());
mysqli_set_charset($connect, 'utf8mb4');

if (function_exists('loadThemeSettings')) loadThemeSettings($connect);
if (function_exists('require_admin_login')) require_admin_login();

include __DIR__ . '/includes/admin_header.php';

$HMS = '/var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas';
?>
<style>
.ss-wrap { max-width: 1100px; }
.ss-path { background:#0f172a; color:#7ee7ff; font-family:ui-monospace,Menlo,monospace; font-size:13px; padding:10px 14px; border-radius:8px; word-break:break-all; }
.ss-card { background:var(--bs-body-bg); border:1px solid var(--bs-border-color); border-radius:12px; padding:18px 20px; margin-bottom:18px; }
.ss-h { font-size:16px; font-weight:700; margin:0 0 12px; padding-bottom:8px; border-bottom:2px solid var(--bs-primary); }
.ss-legend { display:flex; gap:14px; flex-wrap:wrap; font-size:12px; color:var(--bs-secondary-color); margin-bottom:8px; }
.ss-legend span { display:inline-flex; align-items:center; gap:4px; }
.dot { width:12px; height:12px; border-radius:3px; display:inline-block; }
.d-core{background:#16a34a}.d-web{background:#ae1932}.d-app{background:#0891b2}.d-fw{background:#6366f1}.d-data{background:#d97706}.d-bak{background:#9ca3af}
table.ss-tbl { width:100%; border-collapse:collapse; font-size:13.5px; }
.ss-tbl th, .ss-tbl td { text-align:left; padding:9px 11px; border-bottom:1px solid var(--bs-border-color); vertical-align:top; }
.ss-tbl th { background:var(--bs-tertiary-bg); white-space:nowrap; }
.ss-tbl code { background:var(--bs-tertiary-bg); padding:2px 7px; border-radius:5px; font-size:12.5px; color:#be185d; white-space:nowrap; }
.ss-tag { font-size:11px; font-weight:700; padding:2px 8px; border-radius:20px; color:#fff; white-space:nowrap; }
.t-core{background:#16a34a}.t-web{background:#ae1932}.t-app{background:#0891b2}.t-fw{background:#6366f1}.t-data{background:#d97706}.t-bak{background:#9ca3af}
.ss-sz { font-family:ui-monospace,monospace; color:var(--bs-secondary-color); font-size:12px; }
.ss-tree { font-family:ui-monospace,Menlo,monospace; font-size:13px; line-height:1.7; background:var(--bs-tertiary-bg); padding:16px 18px; border-radius:10px; overflow-x:auto; }
.ss-tree .c1{color:#16a34a;font-weight:700}.ss-tree .c2{color:#ae1932;font-weight:700}.ss-tree .c3{color:#0891b2}.ss-tree .c4{color:#9ca3af}
.ss-note { background:#fff7ed; border-left:4px solid #d97706; padding:12px 15px; border-radius:0 8px 8px 0; font-size:13px; margin:10px 0; }
[data-bs-theme="dark"] .ss-note{ background:#2d2416; }
</style>

<div class="container-fluid py-3 ss-wrap">
  <h2 class="mb-2">📂 서버 폴더 구조 — HMS-SaaS 백엔드</h2>
  <p class="text-secondary mb-2">서울미녀/서울뷰티 전체 백엔드의 루트 폴더입니다. 인수인계 시 이 구조를 이해하면 전체 시스템을 파악할 수 있습니다.</p>
  <div class="ss-path"><?= htmlspecialchars($HMS) ?></div>

  <div class="ss-note">
    💡 <b>한눈에</b>: 이 폴더는 <b>Laravel 10 (PHP 8.1)</b> 기반 HMS(병원관리) 백엔드입니다.
    이 안의 <code>hairwang_full/gangnamunni/</code> 가 <b>실제 서울미녀 웹서비스</b>(환자/관리자 페이지)이고,
    나머지는 Laravel 프레임워크 표준 구조 + HMS API입니다.
  </div>

  <div class="ss-card">
    <div class="ss-h">🗂 전체 구조 트리</div>
    <div class="ss-legend">
      <span><i class="dot d-core"></i> 핵심 코드</span>
      <span><i class="dot d-web"></i> 서울미녀 웹</span>
      <span><i class="dot d-app"></i> 앱/API</span>
      <span><i class="dot d-fw"></i> 프레임워크</span>
      <span><i class="dot d-data"></i> 데이터/리소스</span>
      <span><i class="dot d-bak"></i> 백업/기타</span>
    </div>
    <div class="ss-tree">hms-saas/
├── <span class="c1">app/</span>                  Laravel 핵심 로직 (Models·Http·Livewire·Repositories…)  <span class="c4">5.2M</span>
├── <span class="c1">routes/</span>               URL 라우팅 (api.php · web.php · tenant.php …)            <span class="c4">252K</span>
├── <span class="c1">config/</span>               설정 파일 (DB·앱·서비스)                                <span class="c4">236K</span>
├── <span class="c1">database/</span>             마이그레이션·시더                                        <span class="c4">2.4M</span>
├── <span class="c3">hospital-api/</span>         HMS REST API (서버↔앱 통신)                              <span class="c4">1.1G</span>
├── <span class="c2">hairwang_full/</span>        ★ 서울미녀 웹서비스 루트 (아래 상세)                      <span class="c4">4.4G</span>
│   └── <span class="c2">gangnamunni/</span>      ★★ 실제 환자/관리자 페이지 (PHP)                          <span class="c4">3.1G</span>
├── <span style="color:#d97706">resources/</span>            Blade 뷰·JS·SCSS 원본                                    <span class="c4">38M</span>
├── <span style="color:#d97706">public/</span>               웹 공개 루트 (index.php·assets)                          <span class="c4">58M</span>
├── <span style="color:#6366f1">vendor/</span>               Composer 패키지 (Laravel 등) — 자동생성                  <span class="c4">328M</span>
├── <span style="color:#d97706">storage/</span>              로그·캐시·업로드                                         <span class="c4">35M</span>
├── <span style="color:#d97706">lang/</span>                 다국어 (10개 언어)                                       <span class="c4">1.9M</span>
├── <span style="color:#d97706">docs/</span>                 문서                                                     <span class="c4">9.9M</span>
├── <span class="c4">sql_dumps/</span>            DB 덤프 백업                                             <span class="c4">24M</span>
└── composer.json · package.json · webpack.mix.js …  (빌드 설정)</div>
  </div>

  <div class="ss-card">
    <div class="ss-h">📋 최상위 폴더 상세 설명</div>
    <table class="ss-tbl">
      <thead><tr><th>폴더</th><th>분류</th><th>용량</th><th>설명</th></tr></thead>
      <tbody>
      <tr><td><code>app/</code></td><td><span class="ss-tag t-core">핵심</span></td><td class="ss-sz">5.2M</td>
        <td>Laravel 애플리케이션 핵심. <code>Models</code>(DB모델), <code>Http</code>(컨트롤러), <code>Livewire</code>(실시간 UI), <code>Repositories</code>(데이터 접근), <code>helpers.php</code>. <b>비즈니스 로직의 본체</b>.</td></tr>
      <tr><td><code>routes/</code></td><td><span class="ss-tag t-core">핵심</span></td><td class="ss-sz">252K</td>
        <td>URL → 코드 연결. <code>api.php</code>(앱 API), <code>web.php</code>(웹), <code>tenant.php</code>(멀티테넌트 병원별), <code>landing.php</code>. <b>어떤 주소가 무슨 기능인지</b>의 지도.</td></tr>
      <tr><td><code>config/</code></td><td><span class="ss-tag t-core">핵심</span></td><td class="ss-sz">236K</td>
        <td>DB 접속·앱 이름·메일·캐시 등 환경 설정. 실제 값은 루트 <code>.env</code>에 있음.</td></tr>
      <tr><td><code>database/</code></td><td><span class="ss-tag t-core">핵심</span></td><td class="ss-sz">2.4M</td>
        <td>마이그레이션(테이블 스키마 정의)·시더(초기 데이터). DB 구조의 설계도.</td></tr>
      <tr><td><code>hospital-api/</code></td><td><span class="ss-tag t-app">앱/API</span></td><td class="ss-sz">1.1G</td>
        <td>HMS REST API 모듈. 모바일 앱(서울미녀 환자/파트너)이 <code>/api/*</code>로 호출하는 백엔드.</td></tr>
      <tr><td><code>hairwang_full/</code></td><td><span class="ss-tag t-web">서울미녀 웹</span></td><td class="ss-sz">4.4G</td>
        <td><b>★ 서울미녀 웹서비스 전체.</b> Gnuboard 기반 + 그 안의 <code>gangnamunni/</code>가 핵심. (아래 별도 표)</td></tr>
      <tr><td><code>resources/</code></td><td><span class="ss-tag t-data">리소스</span></td><td class="ss-sz">38M</td>
        <td>Blade 템플릿·JS/SCSS 원본. 빌드 전 소스 (빌드 결과는 <code>public/</code>).</td></tr>
      <tr><td><code>public/</code></td><td><span class="ss-tag t-data">리소스</span></td><td class="ss-sz">58M</td>
        <td>웹 공개 루트. <code>index.php</code>(진입점)·이미지·빌드된 CSS/JS. 외부에서 접근 가능한 유일한 폴더.</td></tr>
      <tr><td><code>vendor/</code></td><td><span class="ss-tag t-fw">프레임워크</span></td><td class="ss-sz">328M</td>
        <td>Composer로 설치된 외부 패키지(Laravel 등). <b>자동 생성</b> — <code>composer install</code>로 재생성 가능, 백업/이관 시 제외해도 됨.</td></tr>
      <tr><td><code>storage/</code></td><td><span class="ss-tag t-data">데이터</span></td><td class="ss-sz">35M</td>
        <td>런타임 데이터: 로그·캐시·세션·업로드 파일. <code>logs/</code>는 주기적 정리 대상.</td></tr>
      <tr><td><code>lang/</code></td><td><span class="ss-tag t-data">데이터</span></td><td class="ss-sz">1.9M</td>
        <td>다국어 번역(ko 기본 + 10개 언어).</td></tr>
      <tr><td><code>docs/</code> · <code>sql_dumps/</code></td><td><span class="ss-tag t-bak">기타</span></td><td class="ss-sz">9.9M / 24M</td>
        <td>문서 / DB 덤프 백업. 인계 참고용.</td></tr>
      </tbody>
    </table>
  </div>

  <div class="ss-card">
    <div class="ss-h">🌐 hairwang_full/ 내부 (서울미녀 웹서비스)</div>
    <p class="text-secondary" style="font-size:13px">Gnuboard5 기반 + 서울미녀 스타일 커스텀. <b><code>gangnamunni/</code>가 실제 운영 핵심</b>입니다.</p>
    <table class="ss-tbl">
      <thead><tr><th>폴더</th><th>용량</th><th>설명</th></tr></thead>
      <tbody>
      <tr><td><code>gangnamunni/</code></td><td class="ss-sz">3.1G</td>
        <td><b>★★ 실제 서울미녀 페이지.</b> 환자(index/hospitals/detail/mypage…)·관리자(admin_*.php) PHP 260+개. <b>이번 인수인계의 핵심 작업 폴더</b>. 도메인: <code>/hairwang/app/</code> (디스크 폴더명은 여전히 gangnamunni)</td></tr>
      <tr><td><code>hairwang_app_source/</code></td><td class="ss-sz"><s>814M</s></td><td>✅ <b>2026-06-15 삭제됨</b>(구 hairwang3 앱·운영무관·260룰 주범). PC 백업: <code>_handover_from_server\hairwang_app_source_20260615.tar.gz</code>(249M) · 서버 압축본: <code>/root/_archive/</code>. 최신 앱소스는 <code>/var/www/html/seoulbeauty_app_source/</code>.</td></tr>
      <tr><td><code>adm/</code> · <code>bbs/</code> · <code>lib/</code> · <code>plugin/</code></td><td class="ss-sz">4.3M·732K·5.8M·86M</td><td>Gnuboard5 코어(관리/게시판/라이브러리/플러그인). <b>프레임워크 부분 — 함부로 수정 금지</b>.</td></tr>
      <tr><td><code>data/</code></td><td class="ss-sz">217M</td><td>Gnuboard 업로드 데이터(이미지 등). <b>사용자 콘텐츠</b> — 백업 필수.</td></tr>
      <tr><td><code>theme/</code> · <code>skin/</code> · <code>css/</code> · <code>js/</code></td><td class="ss-sz">38M·1.6M·560K·2.8M</td><td>디자인 테마·스킨·정적 리소스.</td></tr>
      <tr><td><code>api/</code> · <code>mobile/</code></td><td class="ss-sz">296K·2.5M</td><td>웹 API·모바일 웹뷰용 페이지.</td></tr>
      <tr><td><code>shop/</code></td><td class="ss-sz">3.1M</td><td>영카트(결제/상품) — PG 연동.</td></tr>
      <tr><td><code>save_database/</code> · <code>db/</code> · <code></code></td><td class="ss-sz">401M·70M</td>
        <td><span class="ss-tag t-bak">백업</span> DB/소스 백업본. <b>인계 시 제외 가능</b>(용량만 차지).</td></tr>
      <tr><td><code>phpMyAdmin-5.2.2/</code></td><td class="ss-sz">67M</td><td>DB 관리 도구(중복 설치). 운영 무관.</td></tr>
      </tbody>
    </table>
  </div>

  <div class="ss-card">
    <div class="ss-h">📌 인수인계 핵심 정리</div>
    <table class="ss-tbl">
      <thead><tr><th>무엇</th><th>위치</th></tr></thead>
      <tbody>
      <tr><td><b>① 서울미녀 웹 (실제 운영)</b></td><td><code><?= htmlspecialchars($HMS) ?>/hairwang_full/gangnamunni/</code></td></tr>
      <tr><td><b>② HMS 백엔드 (Laravel)</b></td><td><code><?= htmlspecialchars($HMS) ?></code></td></tr>
      <tr><td><b>③ 모바일 앱 소스</b></td><td><code>/var/www/html/seoulbeauty_app_source/</code></td></tr>
      <tr><td><b>④ DB</b></td><td><code>hms_saas</code> (MariaDB, phpMyAdmin으로 접근)</td></tr>
      </tbody>
    </table>
    <div class="ss-note">
      ⚙️ <b>기술 스택</b>: Laravel 10.19 · PHP 8.1 · MariaDB · Gnuboard5(서울미녀 웹) · Bootstrap 5.3 · Flutter(앱).
    </div>
  </div>

  <div class="ss-card">
    <div class="ss-h">📦 이관(인계) 시 제외 가능한 폴더 — 현재 크기 기준</div>
    <p class="text-secondary" style="font-size:13px;margin-top:0">
      전체 용량 중 상당 부분은 <b>"소스 코드"가 아니라 명령어 한 번으로 다시 만들어지는 부산물·백업</b>입니다.
      인계 패키지를 만들 때 이것들을 빼면 용량이 크게 줄고, 받는 쪽은 <code>composer install</code> 등으로 그대로 복원됩니다.
      <br>(비유: <b>레시피(코드)는 넘기고, 이미 만든 요리(부산물)는 안 넘긴다</b> — 레시피로 다시 만들면 동일)
    </p>
    <table class="ss-tbl">
      <thead><tr><th>제외 대상</th><th>현재 크기</th><th>정체</th><th>복원 방법</th></tr></thead>
      <tbody>
      <tr><td><code>vendor/</code></td><td class="ss-sz">215M</td><td>Composer 외부 패키지(Laravel 등)</td><td><code>composer install</code></td></tr>
      <tr><td><code>node_modules/</code> (전체)</td><td class="ss-sz">113M</td><td>npm JS 라이브러리</td><td><code>npm install</code></td></tr>
      <tr><td><code>*.log</code> (전체)</td><td class="ss-sz">288M</td><td>로그 파일 — 과거 기록</td><td>자동 생성</td></tr>
      <tr><td><code>storage/framework</code>·<code>logs</code></td><td class="ss-sz">34M</td><td>실행 중 만들어진 캐시</td><td>자동 재생성</td></tr>
      <tr><td><code>*_backup_*</code> 폴더</td><td class="ss-sz">2.0G</td><td>과거 백업 사본(운영본 아님)</td><td>불필요</td></tr>
      <tr><td><code>save_database/</code></td><td class="ss-sz">400M</td><td>구 DB 백업</td><td>불필요</td></tr>
      <tr style="font-weight:700"><td>제외 합계</td><td class="ss-sz">≈ 3.0G</td><td colspan="2"></td></tr>
      </tbody>
    </table>
    <div class="ss-note" style="border-left-color:#16a34a;background:#f0fdf4;">
      ✅ <b>현재 6.9G → 제외 후 약 3.8G (56%)</b> 로 줄어듭니다. 받는 쪽에서 <code>composer install</code> + <code>npm install</code> 하면 다시 동일하게 복원됩니다.
    </div>
    <div class="ss-note" style="border-left-color:#dc2626;background:#fef2f2;">
      ⚠️ <b>절대 제외하면 안 되는 것</b>(자동 복원 불가 — 진짜 데이터):
      <br>• <code>hairwang_full/.../data/</code> (217M) — 사용자가 올린 이미지·파일
      <br>• <code>.env</code> — DB 비밀번호 등 환경설정 (자동생성 안 됨)
      <br>• <b>DB <code>hms_saas</code></b> — 모든 데이터 본체 (별도 SQL 덤프 백업 필수)
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
