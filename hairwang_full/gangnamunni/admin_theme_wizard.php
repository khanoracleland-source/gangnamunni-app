<?php
/**
 * 테마 설정 마법사 - 5단계 (프리셋 → 색상 → 폰트/크기 → 레이아웃 → 미리보기+적용)
 */
session_start();
require_once __DIR__ . '/includes/admin_session_guard.php'; sb_admin_session_guard(); // 삭제·비활성·역할변경 관리자 세션 차단
if (!isset($_SESSION['ad_admin_logged_in']) || $_SESSION['ad_admin_logged_in'] !== true) {
    header('Location: dashboard.php'); exit;
}
require_once __DIR__ . '/includes/admin_auth.php';
require_permission('theme');

require_once __DIR__ . '/../dbconfig.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
mysqli_set_charset($connect, 'utf8mb4');
require_once __DIR__ . '/theme_helper.php';
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }
loadThemeSettings($connect);

$esc = function($v) use ($connect) { return mysqli_real_escape_string($connect, $v); };

// 테마 테이블 생성
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_theme_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE,
    setting_value TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 현재 설정 로드
$settings = [];
$r = mysqli_query($connect, "SELECT setting_key, setting_value FROM gangnamunni_theme_settings");
if ($r) while ($row = mysqli_fetch_assoc($r)) $settings[$row['setting_key']] = $row['setting_value'];

// 기본값
$defaults = [
    'preset' => 'default',
    'primary_color' => '#ff6b35',
    'secondary_color' => '#6c757d',
    'accent_color' => '#ffc107',
    'bg_color' => '#ffffff',
    'text_color' => '#212529',
    'font_family' => "'Noto Sans KR', sans-serif",
    'font_size_base' => '14',
    'heading_size' => '18',
    'border_radius' => '12',
    'sidebar_width' => '220',
    'header_height' => '50',
    'card_shadow' => '1',
    'dark_mode' => '0',
    'logo_url' => '',
    'footer_text' => '서울미녀 관리자',
];
foreach ($defaults as $k => $v) {
    if (!isset($settings[$k])) $settings[$k] = $v;
}

// AJAX 저장
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_theme') {
    header('Content-Type: application/json');
    try {
        $data = json_decode($_POST['settings'] ?? '{}', true);
        if (!$data) throw new Exception('데이터 없음');

        foreach ($data as $key => $value) {
            $k = $esc($key);
            $v = $esc($value);
            mysqli_query($connect, "INSERT INTO gangnamunni_theme_settings (setting_key, setting_value) VALUES ('{$k}', '{$v}') ON DUPLICATE KEY UPDATE setting_value='{$v}'");
        }

        // CSS 파일 생성
        $css = generateThemeCSS($data);
        $cssPath = __DIR__ . '/css/theme_custom.css';
        if (!is_dir(dirname($cssPath))) mkdir(dirname($cssPath), 0775, true);
        file_put_contents($cssPath, $css);

        echo json_encode(['success' => true, 'message' => '테마가 적용되었습니다.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

function generateThemeCSS($s) {
    $primary = $s['primary_color'] ?? '#ff6b35';
    $secondary = $s['secondary_color'] ?? '#6c757d';
    $accent = $s['accent_color'] ?? '#ffc107';
    $bg = $s['bg_color'] ?? '#ffffff';
    $text = $s['text_color'] ?? '#212529';
    $font = $s['font_family'] ?? "'Noto Sans KR', sans-serif";
    $fontSize = ($s['font_size_base'] ?? 14) . 'px';
    $headingSize = ($s['heading_size'] ?? 18) . 'px';
    $radius = ($s['border_radius'] ?? 12) . 'px';
    $shadow = ($s['card_shadow'] ?? 1) ? '0 2px 8px rgba(0,0,0,.08)' : 'none';

    return ":root {
  --sb-primary: {$primary};
  --sb-secondary: {$secondary};
  --sb-accent: {$accent};
  --sb-bg: {$bg};
  --sb-text: {$text};
  --sb-font: {$font};
  --sb-font-size: {$fontSize};
  --sb-heading-size: {$headingSize};
  --sb-radius: {$radius};
  --sb-shadow: {$shadow};
}
body { font-family: var(--sb-font); font-size: var(--sb-font-size); color: var(--sb-text); background: var(--sb-bg); }
h1,h2,h3,h4,h5,h6 { font-size: var(--sb-heading-size); }
.btn-primary, .btn-wz-next { background: var(--sb-primary) !important; border-color: var(--sb-primary) !important; }
.wz-card.selected { border-color: var(--sb-primary); }
.wz-step.active .wz-circle { background: var(--sb-primary); border-color: var(--sb-primary); }
.card { border-radius: var(--sb-radius); box-shadow: var(--sb-shadow); }
";
}

$admin_page_title = '테마 설정 마법사';
$admin_current = 'theme';
$admin_extra_css = '<link href="includes/wizard_base.css" rel="stylesheet">';
require_once __DIR__ . '/includes/admin_header.php';
?>

<style>
.color-preset { width: 100%; padding: 20px; border-radius: 14px; border: 3px solid var(--bs-border-color); cursor: pointer; transition: all .25s; text-align: center; }
.color-preset:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.1); }
.color-preset.selected { border-color: #ff6b35; box-shadow: 0 0 0 3px rgba(255,107,53,.2); }
.color-preset .preset-preview { display: flex; gap: 4px; justify-content: center; margin-bottom: 8px; }
.color-preset .preset-dot { width: 24px; height: 24px; border-radius: 50%; border: 1px solid rgba(0,0,0,.1); }
.color-picker-group { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--bs-border-color); }
.color-picker-group:last-child { border-bottom: none; }
.color-picker-group label { min-width: 100px; font-weight: 600; font-size: 13px; }
.color-picker-group input[type="color"] { width: 44px; height: 36px; border: 1px solid var(--bs-border-color); border-radius: 8px; cursor: pointer; padding: 2px; }
.color-picker-group .color-hex { font-family: monospace; font-size: 13px; color: var(--bs-secondary-color); }
.font-card { padding: 16px; text-align: center; height: 100%; }
.font-card .font-sample { font-size: 22px; margin-bottom: 6px; font-weight: 700; }
.slider-group { display: flex; align-items: center; gap: 12px; padding: 10px 0; }
.slider-group label { min-width: 100px; font-weight: 600; font-size: 13px; }
.slider-group input[type="range"] { flex: 1; }
.slider-group .slider-val { min-width: 50px; text-align: right; font-family: monospace; font-size: 13px; }
.layout-option { padding: 16px; text-align: center; }
.layout-option .layout-icon { font-size: 36px; margin-bottom: 6px; }
.preview-frame { border: 1px solid var(--bs-border-color); border-radius: 12px; overflow: hidden; min-height: 400px; }
.preview-topbar { padding: 10px 16px; display: flex; align-items: center; justify-content: space-between; }
.preview-sidebar { width: 180px; min-height: 300px; padding: 12px; float: left; }
.preview-sidebar .menu-item { padding: 8px 10px; border-radius: 6px; font-size: 12px; margin-bottom: 4px; cursor: default; }
.preview-sidebar .menu-item.active { font-weight: 700; }
.preview-content { margin-left: 200px; padding: 16px; }
.preview-card { border-radius: 10px; padding: 16px; margin-bottom: 12px; }
</style>

<div class="wz-steps">
    <div class="wz-step active" data-step="1"><div class="wz-circle">1</div><span class="wz-label">프리셋</span></div>
    <div class="wz-line"></div>
    <div class="wz-step" data-step="2"><div class="wz-circle">2</div><span class="wz-label">색상</span></div>
    <div class="wz-line"></div>
    <div class="wz-step" data-step="3"><div class="wz-circle">3</div><span class="wz-label">폰트</span></div>
    <div class="wz-line"></div>
    <div class="wz-step" data-step="4"><div class="wz-circle">4</div><span class="wz-label">레이아웃</span></div>
    <div class="wz-line"></div>
    <div class="wz-step" data-step="5"><div class="wz-circle">5</div><span class="wz-label">적용</span></div>
</div>

<div class="wz-body">

    <!-- Step 1: 프리셋 -->
    <div class="wz-panel active" id="wzPanel1">
        <h5 class="mb-3"><i class="bi bi-palette"></i> 테마 프리셋 선택</h5>
        <div class="row g-3" id="presetGrid">
            <div class="col-6 col-md-4">
                <div class="color-preset selected" data-value="default" data-colors='{"primary":"#ff6b35","secondary":"#6c757d","accent":"#ffc107","bg":"#ffffff","text":"#212529"}'>
                    <div class="preset-preview">
                        <div class="preset-dot" style="background:#ff6b35"></div>
                        <div class="preset-dot" style="background:#6c757d"></div>
                        <div class="preset-dot" style="background:#ffc107"></div>
                    </div>
                    <div class="fw-bold" style="font-size:13px">기본 (오렌지)</div>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="color-preset" data-value="pink" data-colors='{"primary":"#e91e8c","secondary":"#9c27b0","accent":"#ff4081","bg":"#fff5f9","text":"#333"}'>
                    <div class="preset-preview">
                        <div class="preset-dot" style="background:#e91e8c"></div>
                        <div class="preset-dot" style="background:#9c27b0"></div>
                        <div class="preset-dot" style="background:#ff4081"></div>
                    </div>
                    <div class="fw-bold" style="font-size:13px">핑크 뷰티</div>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="color-preset" data-value="blue" data-colors='{"primary":"#1976d2","secondary":"#455a64","accent":"#00bcd4","bg":"#f5f9ff","text":"#263238"}'>
                    <div class="preset-preview">
                        <div class="preset-dot" style="background:#1976d2"></div>
                        <div class="preset-dot" style="background:#455a64"></div>
                        <div class="preset-dot" style="background:#00bcd4"></div>
                    </div>
                    <div class="fw-bold" style="font-size:13px">블루 클리닉</div>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="color-preset" data-value="green" data-colors='{"primary":"#2e7d32","secondary":"#558b2f","accent":"#8bc34a","bg":"#f5fff5","text":"#1b5e20"}'>
                    <div class="preset-preview">
                        <div class="preset-dot" style="background:#2e7d32"></div>
                        <div class="preset-dot" style="background:#558b2f"></div>
                        <div class="preset-dot" style="background:#8bc34a"></div>
                    </div>
                    <div class="fw-bold" style="font-size:13px">그린 힐링</div>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="color-preset" data-value="gold" data-colors='{"primary":"#bf8c00","secondary":"#8d6e00","accent":"#ffd700","bg":"#fffdf5","text":"#3e2723"}'>
                    <div class="preset-preview">
                        <div class="preset-dot" style="background:#bf8c00"></div>
                        <div class="preset-dot" style="background:#8d6e00"></div>
                        <div class="preset-dot" style="background:#ffd700"></div>
                    </div>
                    <div class="fw-bold" style="font-size:13px">골드 프리미엄</div>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="color-preset" data-value="dark" data-colors='{"primary":"#bb86fc","secondary":"#03dac6","accent":"#cf6679","bg":"#121212","text":"#e0e0e0"}'>
                    <div class="preset-preview">
                        <div class="preset-dot" style="background:#bb86fc"></div>
                        <div class="preset-dot" style="background:#03dac6"></div>
                        <div class="preset-dot" style="background:#cf6679"></div>
                    </div>
                    <div class="fw-bold" style="font-size:13px">다크 모드</div>
                </div>
            </div>
        </div>
        <div class="wz-actions">
            <span></span>
            <button class="btn-wz-next" onclick="wz.next()">색상 커스텀 <i class="bi bi-arrow-right"></i></button>
        </div>
    </div>

    <!-- Step 2: 색상 커스텀 -->
    <div class="wz-panel" id="wzPanel2">
        <h5 class="mb-3"><i class="bi bi-eyedropper"></i> 색상 커스터마이즈</h5>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="color-picker-group">
                    <label>메인 색상</label>
                    <input type="color" id="cpPrimary" value="<?= htmlspecialchars($settings['primary_color']) ?>">
                    <span class="color-hex" id="hexPrimary"><?= htmlspecialchars($settings['primary_color']) ?></span>
                </div>
                <div class="color-picker-group">
                    <label>보조 색상</label>
                    <input type="color" id="cpSecondary" value="<?= htmlspecialchars($settings['secondary_color']) ?>">
                    <span class="color-hex" id="hexSecondary"><?= htmlspecialchars($settings['secondary_color']) ?></span>
                </div>
                <div class="color-picker-group">
                    <label>강조 색상</label>
                    <input type="color" id="cpAccent" value="<?= htmlspecialchars($settings['accent_color']) ?>">
                    <span class="color-hex" id="hexAccent"><?= htmlspecialchars($settings['accent_color']) ?></span>
                </div>
                <div class="color-picker-group">
                    <label>배경 색상</label>
                    <input type="color" id="cpBg" value="<?= htmlspecialchars($settings['bg_color']) ?>">
                    <span class="color-hex" id="hexBg"><?= htmlspecialchars($settings['bg_color']) ?></span>
                </div>
                <div class="color-picker-group">
                    <label>텍스트 색상</label>
                    <input type="color" id="cpText" value="<?= htmlspecialchars($settings['text_color']) ?>">
                    <span class="color-hex" id="hexText"><?= htmlspecialchars($settings['text_color']) ?></span>
                </div>
            </div>
        </div>
        <div class="wz-actions">
            <button class="btn-wz-prev" onclick="wz.prev()"><i class="bi bi-arrow-left"></i> 이전</button>
            <button class="btn-wz-next" onclick="wz.next()">폰트 설정 <i class="bi bi-arrow-right"></i></button>
        </div>
    </div>

    <!-- Step 3: 폰트 + 크기 -->
    <div class="wz-panel" id="wzPanel3">
        <h5 class="mb-3"><i class="bi bi-fonts"></i> 폰트 & 크기 설정</h5>

        <h6 class="small fw-bold text-muted mb-2">폰트 선택</h6>
        <div class="row g-2 mb-3" id="fontGrid">
            <div class="col-6">
                <div class="wz-card font-card selected" data-value="'Noto Sans KR', sans-serif">
                    <div class="wz-check"><i class="bi bi-check"></i></div>
                    <div class="font-sample" style="font-family:'Noto Sans KR'">Aa 가나다</div>
                    <div class="wz-card-name">Noto Sans KR</div>
                </div>
            </div>
            <div class="col-6">
                <div class="wz-card font-card" data-value="'Pretendard', sans-serif">
                    <div class="wz-check"><i class="bi bi-check"></i></div>
                    <div class="font-sample" style="font-family:Pretendard,sans-serif">Aa 가나다</div>
                    <div class="wz-card-name">Pretendard</div>
                </div>
            </div>
            <div class="col-6">
                <div class="wz-card font-card" data-value="'Spoqa Han Sans Neo', sans-serif">
                    <div class="wz-check"><i class="bi bi-check"></i></div>
                    <div class="font-sample" style="font-family:'Spoqa Han Sans Neo',sans-serif">Aa 가나다</div>
                    <div class="wz-card-name">Spoqa Han Sans</div>
                </div>
            </div>
            <div class="col-6">
                <div class="wz-card font-card" data-value="'Nanum Gothic', sans-serif">
                    <div class="wz-check"><i class="bi bi-check"></i></div>
                    <div class="font-sample" style="font-family:'Nanum Gothic',sans-serif">Aa 가나다</div>
                    <div class="wz-card-name">Nanum Gothic</div>
                </div>
            </div>
        </div>

        <h6 class="small fw-bold text-muted mb-2">크기 조절</h6>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="slider-group">
                    <label>본문 크기</label>
                    <input type="range" class="form-range" id="slFontSize" min="12" max="18" value="<?= intval($settings['font_size_base']) ?>">
                    <span class="slider-val" id="valFontSize"><?= intval($settings['font_size_base']) ?>px</span>
                </div>
                <div class="slider-group">
                    <label>제목 크기</label>
                    <input type="range" class="form-range" id="slHeadingSize" min="14" max="28" value="<?= intval($settings['heading_size']) ?>">
                    <span class="slider-val" id="valHeadingSize"><?= intval($settings['heading_size']) ?>px</span>
                </div>
                <div class="slider-group">
                    <label>둥글기</label>
                    <input type="range" class="form-range" id="slRadius" min="0" max="24" value="<?= intval($settings['border_radius']) ?>">
                    <span class="slider-val" id="valRadius"><?= intval($settings['border_radius']) ?>px</span>
                </div>
            </div>
        </div>
        <div class="wz-actions">
            <button class="btn-wz-prev" onclick="wz.prev()"><i class="bi bi-arrow-left"></i> 이전</button>
            <button class="btn-wz-next" onclick="wz.next()">레이아웃 <i class="bi bi-arrow-right"></i></button>
        </div>
    </div>

    <!-- Step 4: 레이아웃 -->
    <div class="wz-panel" id="wzPanel4">
        <h5 class="mb-3"><i class="bi bi-layout-sidebar"></i> 레이아웃 설정</h5>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="slider-group">
                    <label>사이드바 너비</label>
                    <input type="range" class="form-range" id="slSidebar" min="180" max="280" step="10" value="<?= intval($settings['sidebar_width']) ?>">
                    <span class="slider-val" id="valSidebar"><?= intval($settings['sidebar_width']) ?>px</span>
                </div>
                <div class="slider-group">
                    <label>상단바 높이</label>
                    <input type="range" class="form-range" id="slHeader" min="40" max="70" step="5" value="<?= intval($settings['header_height']) ?>">
                    <span class="slider-val" id="valHeader"><?= intval($settings['header_height']) ?>px</span>
                </div>
            </div>
        </div>

        <h6 class="small fw-bold text-muted mb-2">옵션</h6>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="optShadow" <?= $settings['card_shadow'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="optShadow">카드 그림자 효과</label>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="optDark" <?= $settings['dark_mode'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="optDark">다크 모드 기본값</label>
                </div>
            </div>
        </div>

        <div class="mb-3 mt-3">
            <label class="form-label small fw-bold">푸터 텍스트</label>
            <input type="text" class="form-control" id="footerText" value="<?= htmlspecialchars($settings['footer_text']) ?>" placeholder="하단에 표시할 텍스트">
        </div>

        <div class="wz-actions">
            <button class="btn-wz-prev" onclick="wz.prev()"><i class="bi bi-arrow-left"></i> 이전</button>
            <button class="btn-wz-next" onclick="wz.next()">미리보기 <i class="bi bi-arrow-right"></i></button>
        </div>
    </div>

    <!-- Step 5: 미리보기 + 적용 -->
    <div class="wz-panel" id="wzPanel5">
        <h5 class="mb-3"><i class="bi bi-eye"></i> 미리보기 & 적용</h5>
        <div class="preview-frame" id="themePreview"></div>

        <div class="wz-summary mt-3">
            <h6><i class="bi bi-gear"></i> 설정 요약</h6>
            <div id="settingSummary" class="small"></div>
        </div>

        <div class="wz-actions">
            <button class="btn-wz-prev" onclick="wz.prev()"><i class="bi bi-arrow-left"></i> 수정</button>
            <button class="btn-wz-submit" id="btnThemeApply" onclick="applyTheme()"><i class="bi bi-check-lg"></i> 테마 적용</button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
<script src="includes/wizard_base.js"></script>
<script>
const wz = new WizardBase(5, { storageKey: 'theme_wz' });
const theme = <?= json_encode($settings, JSON_UNESCAPED_UNICODE) ?>;

// Step 1: 프리셋
document.querySelectorAll('.color-preset').forEach(p => {
    p.addEventListener('click', () => {
        document.querySelectorAll('.color-preset').forEach(x => x.classList.remove('selected'));
        p.classList.add('selected');
        const colors = JSON.parse(p.dataset.colors);
        theme.primary_color = colors.primary;
        theme.secondary_color = colors.secondary;
        theme.accent_color = colors.accent;
        theme.bg_color = colors.bg;
        theme.text_color = colors.text;
        theme.preset = p.dataset.value;
        // 색상 피커 업데이트
        updateColorPickers();
    });
});

function updateColorPickers() {
    const map = { Primary: 'primary_color', Secondary: 'secondary_color', Accent: 'accent_color', Bg: 'bg_color', Text: 'text_color' };
    Object.entries(map).forEach(([id, key]) => {
        const el = document.getElementById('cp' + id);
        const hex = document.getElementById('hex' + id);
        if (el) el.value = theme[key];
        if (hex) hex.textContent = theme[key];
    });
}

// Step 2: 색상 피커 이벤트
['Primary','Secondary','Accent','Bg','Text'].forEach(id => {
    const keyMap = { Primary:'primary_color', Secondary:'secondary_color', Accent:'accent_color', Bg:'bg_color', Text:'text_color' };
    const el = document.getElementById('cp' + id);
    if (el) el.addEventListener('input', () => {
        theme[keyMap[id]] = el.value;
        document.getElementById('hex' + id).textContent = el.value;
    });
});

// Step 3: 폰트
const fontSelect = initCardSelect(document.getElementById('fontGrid'), {
    onChange: (vals) => { theme.font_family = vals[0] || "'Noto Sans KR', sans-serif"; }
});

// 슬라이더
['FontSize','HeadingSize','Radius','Sidebar','Header'].forEach(id => {
    const keyMap = { FontSize:'font_size_base', HeadingSize:'heading_size', Radius:'border_radius', Sidebar:'sidebar_width', Header:'header_height' };
    const sl = document.getElementById('sl' + id);
    const val = document.getElementById('val' + id);
    if (sl) sl.addEventListener('input', () => {
        theme[keyMap[id]] = sl.value;
        val.textContent = sl.value + 'px';
    });
});

// Step 5: 미리보기
wz.onStepChange = (step) => { if (step === 5) renderPreview(); };

function renderPreview() {
    const p = theme;
    const shadow = document.getElementById('optShadow')?.checked ? '0 2px 8px rgba(0,0,0,.08)' : 'none';
    const radius = (p.border_radius || 12) + 'px';

    document.getElementById('themePreview').innerHTML = `
        <div class="preview-topbar" style="background:${wzEsc(p.primary_color)};color:#fff;height:${p.header_height||50}px">
            <div class="fw-bold">서울미녀 관리자</div>
            <div class="small">admin</div>
        </div>
        <div style="display:flex;min-height:300px">
            <div class="preview-sidebar" style="background:${wzEsc(p.bg_color)};border-right:1px solid #ddd;width:${p.sidebar_width||220}px;color:${wzEsc(p.text_color)}">
                <div class="menu-item active" style="background:${wzEsc(p.primary_color)}20;color:${wzEsc(p.primary_color)};border-radius:${radius}">대시보드</div>
                <div class="menu-item" style="border-radius:${radius}">병원 관리</div>
                <div class="menu-item" style="border-radius:${radius}">후기 관리</div>
                <div class="menu-item" style="border-radius:${radius}">회원 관리</div>
            </div>
            <div style="flex:1;padding:16px;background:${wzEsc(p.bg_color)}">
                <h6 style="font-family:${p.font_family};font-size:${p.heading_size||18}px;color:${wzEsc(p.text_color)}">대시보드</h6>
                <div style="display:flex;gap:10px;margin-top:10px">
                    <div class="preview-card" style="flex:1;background:var(--bs-body-bg);box-shadow:${shadow};border-radius:${radius};border:1px solid #eee">
                        <div style="font-size:${p.font_size_base||14}px;color:${wzEsc(p.text_color)}">오늘 예약</div>
                        <div style="font-size:24px;font-weight:700;color:${wzEsc(p.primary_color)}">12</div>
                    </div>
                    <div class="preview-card" style="flex:1;background:var(--bs-body-bg);box-shadow:${shadow};border-radius:${radius};border:1px solid #eee">
                        <div style="font-size:${p.font_size_base||14}px;color:${wzEsc(p.text_color)}">신규 후기</div>
                        <div style="font-size:24px;font-weight:700;color:${wzEsc(p.accent_color)}">5</div>
                    </div>
                </div>
                <div style="margin-top:12px;padding:12px;background:var(--bs-body-bg);border-radius:${radius};box-shadow:${shadow};border:1px solid #eee">
                    <div style="display:flex;gap:8px">
                        <button style="padding:6px 16px;border-radius:${radius};background:${wzEsc(p.primary_color)};color:#fff;border:none;font-size:${p.font_size_base||14}px">버튼 1</button>
                        <button style="padding:6px 16px;border-radius:${radius};background:${wzEsc(p.secondary_color)};color:#fff;border:none;font-size:${p.font_size_base||14}px">버튼 2</button>
                    </div>
                </div>
            </div>
        </div>
        <div style="padding:8px 16px;background:#f8f9fa;font-size:11px;color:#888;text-align:center">${wzEsc(document.getElementById('footerText')?.value || p.footer_text)}</div>
    `;

    // 설정 요약
    document.getElementById('settingSummary').innerHTML = `
        <div class="row g-1">
            <div class="col-6">프리셋: <strong>${wzEsc(p.preset)}</strong></div>
            <div class="col-6">폰트: <strong>${wzEsc(p.font_family.split(',')[0].replace(/'/g,''))}</strong></div>
            <div class="col-6">본문: <strong>${p.font_size_base}px</strong></div>
            <div class="col-6">제목: <strong>${p.heading_size}px</strong></div>
            <div class="col-6">둥글기: <strong>${p.border_radius}px</strong></div>
            <div class="col-6">사이드바: <strong>${p.sidebar_width}px</strong></div>
        </div>
    `;
}

// 적용
async function applyTheme() {
    const btn = document.getElementById('btnThemeApply');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 적용 중...';

    theme.card_shadow = document.getElementById('optShadow')?.checked ? '1' : '0';
    theme.dark_mode = document.getElementById('optDark')?.checked ? '1' : '0';
    theme.footer_text = document.getElementById('footerText')?.value || '';

    const fd = new FormData();
    fd.append('action', 'save_theme');
    fd.append('settings', JSON.stringify(theme));

    try {
        const res = await fetch('', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            wz.clearStorage();
            alert('테마가 적용되었습니다! 페이지를 새로고침합니다.');
            location.reload();
        } else {
            alert(data.error || '오류 발생');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> 테마 적용';
        }
    } catch (e) {
        alert('네트워크 오류: ' + e.message);
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> 테마 적용';
    }
}
</script>
