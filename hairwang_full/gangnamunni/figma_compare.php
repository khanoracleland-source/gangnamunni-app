<?php
// ============================================================================
// [Figma Review - 2026-06-05] 피그마 vs 구현 비교 — 관리자 레이아웃 통합 버전
// 원인 : figma_compare.html이 독립 페이지여서 사이드바 네비 사용 불가
// 변경 : admin_header.php + admin_footer.php 레이아웃 안에 비교 콘텐츠 임베드
// 영향 범위 : 사이드바 "피그마 vs 구현 비교" 메뉴 링크
// ============================================================================
session_start();

$admin_page_title = '피그마 vs 구현 비교';
$admin_current = 'figma_compare';

require_once __DIR__ . '/dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die('DB 연결 실패: ' . mysqli_connect_error());
mysqli_set_charset($connect, 'utf8mb4');

if (function_exists('loadThemeSettings')) loadThemeSettings($connect);
if (function_exists('require_admin_login')) require_admin_login();

include __DIR__ . '/includes/admin_header.php';

// figma_compare.html에서 <body>~</body> 사이 콘텐츠만 추출
$html_path = __DIR__ . '/figma_compare.html';
$html = file_exists($html_path) ? file_get_contents($html_path) : '';
$body_html = '';
$style_html = '';

if ($html) {
    // <style>...</style> 추출
    if (preg_match('/<style[^>]*>(.*?)<\/style>/s', $html, $m)) {
        $style_html = $m[1];
    }
    // <body>...</body> 추출 — body 안에 이미 메인 <script>가 포함되어 있으므로
    // 별도 $script_html 추출/주입은 하지 않는다. (중복 주입 시 const 중복 선언으로 크래시) (한글 주석)
    if (preg_match('/<body[^>]*>(.*?)<\/body>/s', $html, $m)) {
        $body_html = $m[1];

        // (1) HTML 원본의 window.API_BASE="" 설정 라인을 제거 — figma_compare.php가
        //     아래에서 올바른 경로로 설정하므로 이게 남아 있으면 덮어써서 /api_...(루트) 404 발생.
        $body_html = preg_replace('/window\.API_BASE\s*=\s*(["\']).*?\1\s*;?/', '/* API_BASE set by wrapper */', $body_html);

        // (2) body 내부 메인 <script>...</script> 를 1회 실행 가드로 감싼다 —
        //     관리자 SPA(fetch)가 인라인 스크립트를 재실행할 때 const 중복 선언("QUALITY_KEY
        //     has already been declared")을 방지. 함수들은 최초 실행 시 전역에 등록되어 유지된다.
        $body_html = preg_replace_callback('/(<script[^>]*>)(.*?)(<\/script>)/s', function($mm) {
            $code = $mm[2];
            if (trim($code) === '') return $mm[0]; // 빈/외부 스크립트는 그대로
            return $mm[1]
                . "if(!window.__figmaCompareInit){window.__figmaCompareInit=true;\n"
                . $code
                . "\n}"
                . $mm[3];
        }, $body_html);
    }
}
?>

<style>
/* admin 레이아웃 안에 임베드되므로 다크 배경 제거하고 컴팩트하게 */
.figma-compare-wrapper {
  background: #f7f7f9;
  margin: -16px;  /* admin content padding 상쇄 */
  padding: 16px;
  min-height: calc(100vh - 80px);
}
.figma-compare-wrapper .container { max-width: 100%; }
/* 비교 페이지 자체 헤더는 유지 (제목 + 컨트롤 버튼들 포함) — 단 스타일만 admin과 어울리게 */
.figma-compare-wrapper header {
  background: #fff !important;
  color: #333 !important;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
  padding: 18px 20px !important;
}
.figma-compare-wrapper header h1 {
  -webkit-text-fill-color: #ae1932 !important;
  background: none !important;
  font-size: 20px !important;
  margin: 0 0 6px !important;
}
.figma-compare-wrapper header p { color: #666 !important; }
.figma-compare-wrapper header .ctrl-btn {
  background: #f1f1f4;
  color: #333;
  border: 1px solid #e0e0e4;
}
.figma-compare-wrapper header .ctrl-btn:hover { background: #e8e8ec; }
.figma-compare-wrapper header .ctrl-btn.active {
  background: #f24e1e;
  color: #fff;
  border-color: #f24e1e;
}
[data-bs-theme="dark"] .figma-compare-wrapper header {
  background: #2a2a3e !important;
  color: #eee !important;
}
[data-bs-theme="dark"] .figma-compare-wrapper header p { color: #aaa !important; }
[data-bs-theme="dark"] .figma-compare-wrapper header .ctrl-btn {
  background: rgba(255,255,255,0.08);
  color: #fff;
  border-color: rgba(255,255,255,0.15);
}

.figma-compare-toolbar {
  position: sticky;
  top: 56px;
  z-index: 50;
  background: #fff;
  border-radius: 10px;
  padding: 12px 16px;
  margin-bottom: 16px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.06);
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}
.figma-compare-toolbar .toolbar-label {
  font-size: 12px;
  color: #888;
  margin-right: 4px;
}
.figma-compare-toolbar .ctrl-btn {
  background: #f1f1f4;
  color: #333;
  border: 1px solid #e0e0e4;
  padding: 6px 12px;
  border-radius: 6px;
  cursor: pointer;
  font-size: 12px;
}
.figma-compare-toolbar .ctrl-btn:hover { background: #e8e8ec; }
.figma-compare-toolbar .ctrl-btn.active {
  background: #f24e1e;
  color: #fff;
  border-color: #f24e1e;
}

/* dark theme handling — admin 다크 모드 시 자동 적용 */
[data-bs-theme="dark"] .figma-compare-wrapper {
  background: #1a1a2e;
}
[data-bs-theme="dark"] .figma-compare-toolbar {
  background: #2a2a3e;
  color: #ddd;
}
[data-bs-theme="dark"] .figma-compare-toolbar .ctrl-btn {
  background: rgba(255,255,255,0.08);
  color: #fff;
  border-color: rgba(255,255,255,0.15);
}

<?= $style_html ?>
</style>

<?php
// API 경로 기준값 — 빈 문자열이면 '/api_...' 루트 절대경로가 되어 404 발생.
// 앱이 /hairwang/gangnamunni/ 하위이므로 SITE_BASE_PATH(끝 슬래시 제거)를 기준으로 설정. (한글 주석)
$api_base = rtrim(defined('SITE_BASE_PATH') ? SITE_BASE_PATH : '/hairwang/gangnamunni/', '/');
?>
<script>window.API_BASE = <?= json_encode($api_base) ?>;</script>
<div class="figma-compare-wrapper">
<?= $body_html ?>
</div>
<?php /* 메인 스크립트는 $body_html 내부에 이미 포함(1회 실행 가드 적용)되어 있으므로 별도 주입하지 않음 (한글 주석) */ ?>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
