<?php
/**
 * 테마 관리 페이지
 * Bootstrap 5.3 + 공통 관리자 레이아웃
 */

session_start();

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
if (!defined('SITE_FULL_URL')) define('SITE_FULL_URL', 'https://hms.seoulbeauty.app/hairwang/app/');

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("MySQL 연결 실패: " . mysqli_connect_error());
mysqli_set_charset($connect, 'utf8mb4');
require_once __DIR__ . '/theme_helper.php';
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }
loadThemeSettings($connect);


// 로그인 체크
$is_logged_in = isset($_SESSION['ad_admin_logged_in']) && $_SESSION['ad_admin_logged_in'] === true;
if (!$is_logged_in) { header('Location: dashboard.php'); exit; }
require_permission('theme');
if (isset($_GET['logout'])) { session_destroy(); header('Location: dashboard.php'); exit; }

// 설정 저장 처리
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_theme'])) {
    foreach ($_POST as $key => $value) {
        if ($key !== 'save_theme') {
            $escaped_key = mysqli_real_escape_string($connect, $key);
            $escaped_value = mysqli_real_escape_string($connect, $value);

            $query = "UPDATE gangnamunni_theme_settings
                     SET setting_value = '{$escaped_value}'
                     WHERE setting_key = '{$escaped_key}'";
            mysqli_query($connect, $query);
        }
    }
    $message = '테마 설정이 저장되었습니다.';
}

// 현재 설정 불러오기
$settings = [];
$query = "SELECT * FROM gangnamunni_theme_settings ORDER BY setting_key";
$result = mysqli_query($connect, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $settings[$row['setting_key']] = $row;
    }
}

// Google Fonts 목록 (무료, 한글 지원)
$korean_fonts = [
    'Noto Sans KR' => 'Noto Sans KR',
    'Nanum Gothic' => 'Nanum Gothic',
    'Nanum Myeongjo' => 'Nanum Myeongjo',
    'Nanum Square' => 'Nanum Square',
    'Black Han Sans' => 'Black Han Sans',
    'Do Hyeon' => 'Do Hyeon',
    'Jua' => 'Jua',
    'Sunflower' => 'Sunflower',
    'Gothic A1' => 'Gothic A1',
    'IBM Plex Sans KR' => 'IBM Plex Sans KR'
];

$english_fonts = [
    'Roboto' => 'Roboto',
    'Open Sans' => 'Open Sans',
    'Lato' => 'Lato',
    'Montserrat' => 'Montserrat',
    'Poppins' => 'Poppins',
    'Raleway' => 'Raleway',
    'Inter' => 'Inter',
    'Work Sans' => 'Work Sans',
    'Nunito' => 'Nunito',
    'Rubik' => 'Rubik'
];

// 색상 설정 항목 정의
$color_settings = [
    'primary_color' => '메인 브랜드 컬러',
    'secondary_color' => '보조 브랜드 컬러',
    'text_color' => '기본 텍스트 색상',
    'text_secondary_color' => '보조 텍스트 색상',
    'background_color' => '배경색',
    'border_color' => '테두리 색상',
    'button_text_color' => '버튼 텍스트 색상',
    'link_color' => '링크 색상',
    'link_hover_color' => '링크 호버 색상'
];

// 공통 헤더 include
$admin_page_title = '테마 설정';
$admin_current = 'theme';
$admin_extra_css = '
    .color-swatch {
        width: 42px;
        height: 38px;
        border: 1px solid var(--bs-border-color);
        border-radius: var(--bs-border-radius);
        cursor: pointer;
        padding: 2px;
        flex-shrink: 0;
    }
    .color-swatch::-webkit-color-swatch-wrapper { padding: 0; }
    .color-swatch::-webkit-color-swatch { border: none; border-radius: 4px; }
    .font-preview-box {
        padding: 12px 16px;
        background: var(--bs-tertiary-bg);
        border: 1px solid var(--bs-border-color);
        border-radius: var(--bs-border-radius);
        margin-top: 8px;
    }
    .preview-card {
        border: 2px solid var(--bs-border-color);
        border-radius: var(--bs-border-radius);
        padding: 24px;
    }
    .preview-btn {
        display: inline-block;
        padding: 10px 20px;
        border-radius: 8px;
        color: #fff;
        text-decoration: none;
        cursor: pointer;
        border: none;
        font-size: 14px;
    }
    .sticky-save-bar {
        position: sticky;
        bottom: 0;
        z-index: 10;
        background: var(--bs-body-bg);
        border-top: 1px solid var(--bs-border-color);
        padding: 12px 0;
        margin: 0 -16px;
        padding-left: 16px;
        padding-right: 16px;
    }
';
include __DIR__ . '/includes/admin_header.php';

?>
<!-- [추가 2026-06-19] 중복 안내 (인수인계) -->
<div class="alert alert-warning d-flex align-items-start gap-2 mb-3">
    <i class="bi bi-exclamation-triangle-fill mt-1"></i>
    <div class="small"><strong>중복 안내:</strong> 이 페이지는 <strong>고급 테마 관리(admin_theme_advanced.php)</strong>와 동일한 테마 설정을 다룹니다. 프리셋·변경이력·WCAG 기능이 포함된 <strong>고급 테마 관리 사용을 권장</strong>합니다. (중복 페이지)</div>
</div>
<?php
?>

<!-- 페이지 헤더 -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-palette"></i> 테마 설정</h5>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.open('index.php', '_blank')">
            <i class="bi bi-box-arrow-up-right"></i> 사이트 보기
        </button>
    </div>
</div>

<!-- 알림 메시지 -->
<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show py-2" role="alert">
    <?php echo htmlspecialchars($message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
    <?php echo htmlspecialchars($error); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- 탭 네비게이션 -->
<ul class="nav nav-tabs mb-3" id="themeTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="colors-tab" data-bs-toggle="tab" data-bs-target="#colors" type="button" role="tab">
            <i class="bi bi-palette2"></i> 색상 설정
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="fonts-tab" data-bs-toggle="tab" data-bs-target="#fonts" type="button" role="tab">
            <i class="bi bi-fonts"></i> 폰트 설정
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="text-tab" data-bs-toggle="tab" data-bs-target="#text" type="button" role="tab">
            <i class="bi bi-card-text"></i> 텍스트 설정
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="layout-tab" data-bs-toggle="tab" data-bs-target="#layout" type="button" role="tab">
            <i class="bi bi-grid-1x2"></i> 레이아웃 설정
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="preview-tab" data-bs-toggle="tab" data-bs-target="#preview" type="button" role="tab">
            <i class="bi bi-eye"></i> 미리보기
        </button>
    </li>
</ul>

<form method="POST" action="" id="themeForm">
    <div class="tab-content" id="themeTabContent">

        <!-- 색상 설정 탭 -->
        <div class="tab-pane fade show active" id="colors" role="tabpanel">
            <div class="card">
                <div class="card-header py-2">
                    <h6 class="mb-0 fw-semibold">브랜드 색상</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php foreach ($color_settings as $key => $label):
                            $value = $settings[$key]['setting_value'] ?? '#000000';
                        ?>
                        <div class="col-md-6 col-lg-4">
                            <label class="form-label fw-semibold small"><?php echo $label; ?></label>
                            <div class="input-group input-group-sm">
                                <input type="color"
                                       class="form-control form-control-color color-swatch"
                                       value="<?php echo htmlspecialchars($value); ?>"
                                       onchange="document.getElementById('<?php echo $key; ?>_text').value = this.value; updatePreview();"
                                       title="<?php echo $label; ?>">
                                <input type="text"
                                       class="form-control form-control-sm font-monospace"
                                       id="<?php echo $key; ?>_text"
                                       name="<?php echo $key; ?>"
                                       value="<?php echo htmlspecialchars($value); ?>"
                                       placeholder="#000000"
                                       onchange="this.previousElementSibling.value = this.value; updatePreview();">
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 폰트 설정 탭 -->
        <div class="tab-pane fade" id="fonts" role="tabpanel">
            <div class="card mb-3">
                <div class="card-header py-2">
                    <h6 class="mb-0 fw-semibold">폰트 패밀리</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">한글 폰트</label>
                            <p class="text-body-secondary small mb-2">사이트의 한글 텍스트에 적용될 폰트입니다</p>
                            <select name="font_korean" class="form-select form-select-sm" onchange="updateFontPreview('korean', this.value)">
                                <?php foreach ($korean_fonts as $val => $label): ?>
                                <option value="<?php echo $val; ?>"
                                        <?php echo ($settings['font_korean']['setting_value'] ?? '') === $val ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="font-preview-box" id="korean-preview"
                                 style="font-family: '<?php echo htmlspecialchars($settings['font_korean']['setting_value'] ?? 'Noto Sans KR'); ?>', sans-serif;">
                                한글 폰트 미리보기 - 서울미녀에 오신 것을 환영합니다!<br>
                                <strong>굵은 글씨: 아름다움을 더하는 당신의 선택</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">영문 폰트</label>
                            <p class="text-body-secondary small mb-2">사이트의 영문 텍스트에 적용될 폰트입니다</p>
                            <select name="font_english" class="form-select form-select-sm" onchange="updateFontPreview('english', this.value)">
                                <?php foreach ($english_fonts as $val => $label): ?>
                                <option value="<?php echo $val; ?>"
                                        <?php echo ($settings['font_english']['setting_value'] ?? '') === $val ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="font-preview-box" id="english-preview"
                                 style="font-family: '<?php echo htmlspecialchars($settings['font_english']['setting_value'] ?? 'Roboto'); ?>', sans-serif;">
                                English Font Preview - Welcome to Seoul Beauty!<br>
                                <strong>Bold Text: Your Choice for Beauty</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header py-2">
                    <h6 class="mb-0 fw-semibold">폰트 크기 / 굵기</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4 col-lg">
                            <label class="form-label fw-semibold small">기본 폰트 크기</label>
                            <input type="text" name="font_size_base" class="form-control form-control-sm"
                                   value="<?php echo htmlspecialchars($settings['font_size_base']['setting_value'] ?? '14px'); ?>"
                                   placeholder="14px">
                        </div>
                        <div class="col-md-4 col-lg">
                            <label class="form-label fw-semibold small">큰 폰트 크기</label>
                            <input type="text" name="font_size_large" class="form-control form-control-sm"
                                   value="<?php echo htmlspecialchars($settings['font_size_large']['setting_value'] ?? '18px'); ?>"
                                   placeholder="18px">
                        </div>
                        <div class="col-md-4 col-lg">
                            <label class="form-label fw-semibold small">작은 폰트 크기</label>
                            <input type="text" name="font_size_small" class="form-control form-control-sm"
                                   value="<?php echo htmlspecialchars($settings['font_size_small']['setting_value'] ?? '12px'); ?>"
                                   placeholder="12px">
                        </div>
                        <div class="col-md-6 col-lg">
                            <label class="form-label fw-semibold small">일반 굵기 (100-900)</label>
                            <input type="number" name="font_weight_normal" class="form-control form-control-sm"
                                   value="<?php echo htmlspecialchars($settings['font_weight_normal']['setting_value'] ?? '400'); ?>"
                                   min="100" max="900" step="100">
                        </div>
                        <div class="col-md-6 col-lg">
                            <label class="form-label fw-semibold small">굵은 굵기 (100-900)</label>
                            <input type="number" name="font_weight_bold" class="form-control form-control-sm"
                                   value="<?php echo htmlspecialchars($settings['font_weight_bold']['setting_value'] ?? '700'); ?>"
                                   min="100" max="900" step="100">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 텍스트 설정 탭 -->
        <div class="tab-pane fade" id="text" role="tabpanel">
            <div class="card mb-3">
                <div class="card-header py-2">
                    <h6 class="mb-0 fw-semibold">사이트 기본 정보</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">사이트 이름</label>
                            <input type="text" name="site_name" class="form-control form-control-sm"
                                   value="<?php echo htmlspecialchars($settings['site_name']['setting_value'] ?? '서울미녀'); ?>"
                                   placeholder="서울미녀">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">로고 이미지 경로</label>
                            <input type="text" name="site_logo_path" class="form-control form-control-sm"
                                   value="<?php echo htmlspecialchars($settings['site_logo_path']['setting_value'] ?? 'assets/images/logo_128.png'); ?>"
                                   placeholder="assets/images/logo_128.png">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header py-2">
                    <h6 class="mb-0 fw-semibold">섹션 제목</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">꿀팁 섹션 제목</label>
                            <input type="text" name="section_tips_title" class="form-control form-control-sm"
                                   value="<?php echo htmlspecialchars($settings['section_tips_title']['setting_value'] ?? '서울미녀 꿀팁'); ?>"
                                   placeholder="서울미녀 꿀팁">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">병원 섹션 제목</label>
                            <input type="text" name="section_hospitals_title" class="form-control form-control-sm"
                                   value="<?php echo htmlspecialchars($settings['section_hospitals_title']['setting_value'] ?? '추천 병원'); ?>"
                                   placeholder="추천 병원">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">카테고리 섹션 제목</label>
                            <input type="text" name="section_categories_title" class="form-control form-control-sm"
                                   value="<?php echo htmlspecialchars($settings['section_categories_title']['setting_value'] ?? '관심있는 시술을 선택해보세요.'); ?>"
                                   placeholder="관심있는 시술을 선택해보세요.">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header py-2">
                    <h6 class="mb-0 fw-semibold">버튼 텍스트</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">예약 버튼</label>
                            <input type="text" name="button_booking" class="form-control form-control-sm"
                                   value="<?php echo htmlspecialchars($settings['button_booking']['setting_value'] ?? '예약하기'); ?>"
                                   placeholder="예약하기">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">상담 버튼</label>
                            <input type="text" name="button_consultation" class="form-control form-control-sm"
                                   value="<?php echo htmlspecialchars($settings['button_consultation']['setting_value'] ?? '상담하기'); ?>"
                                   placeholder="상담하기">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">더보기 버튼</label>
                            <input type="text" name="button_more" class="form-control form-control-sm"
                                   value="<?php echo htmlspecialchars($settings['button_more']['setting_value'] ?? '전체보기'); ?>"
                                   placeholder="전체보기">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 레이아웃 설정 탭 -->
        <div class="tab-pane fade" id="layout" role="tabpanel">
            <div class="card">
                <div class="card-header py-2">
                    <h6 class="mb-0 fw-semibold">레이아웃 설정</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6 col-lg-4">
                            <label class="form-label fw-semibold small">테두리 둥글기</label>
                            <input type="text" name="border_radius" class="form-control form-control-sm"
                                   value="<?php echo htmlspecialchars($settings['border_radius']['setting_value'] ?? '12px'); ?>"
                                   placeholder="12px">
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <label class="form-label fw-semibold small">카드 그림자</label>
                            <input type="text" name="card_shadow" class="form-control form-control-sm"
                                   value="<?php echo htmlspecialchars($settings['card_shadow']['setting_value'] ?? '0 2px 8px rgba(0,0,0,0.08)'); ?>"
                                   placeholder="0 2px 8px rgba(0,0,0,0.08)">
                        </div>
                        <div class="col-md-4 col-lg">
                            <label class="form-label fw-semibold small">작은 여백</label>
                            <input type="text" name="spacing_small" class="form-control form-control-sm"
                                   value="<?php echo htmlspecialchars($settings['spacing_small']['setting_value'] ?? '8px'); ?>"
                                   placeholder="8px">
                        </div>
                        <div class="col-md-4 col-lg">
                            <label class="form-label fw-semibold small">중간 여백</label>
                            <input type="text" name="spacing_medium" class="form-control form-control-sm"
                                   value="<?php echo htmlspecialchars($settings['spacing_medium']['setting_value'] ?? '16px'); ?>"
                                   placeholder="16px">
                        </div>
                        <div class="col-md-4 col-lg">
                            <label class="form-label fw-semibold small">큰 여백</label>
                            <input type="text" name="spacing_large" class="form-control form-control-sm"
                                   value="<?php echo htmlspecialchars($settings['spacing_large']['setting_value'] ?? '24px'); ?>"
                                   placeholder="24px">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 미리보기 탭 — 라이브 iframe -->
        <div class="tab-pane fade" id="preview" role="tabpanel">
            <div class="card">
                <div class="card-header py-2">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-phone me-1"></i>라이브 미리보기</h6>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <select class="form-select form-select-sm" onchange="thApplyDevice(this.value)" style="width:auto;font-size:12px;">
                                <optgroup label="Phone">
                                    <option value="412,915" selected>Galaxy S26</option>
                                    <option value="412,892">Galaxy S25</option>
                                    <option value="393,852">iPhone 16 Pro</option>
                                    <option value="430,932">iPhone 16 Pro Max</option>
                                    <option value="375,812">iPhone XS</option>
                                    <option value="375,667">iPhone SE</option>
                                </optgroup>
                                <optgroup label="PC">
                                    <option value="1920,1280">FHD</option>
                                    <option value="1280,800">WXGA</option>
                                </optgroup>
                            </select>
                            <div class="d-flex align-items-center gap-1">
                                <input type="number" class="form-control form-control-sm" id="thW" value="412" onchange="thApplySize()" style="width:60px;font-size:12px;text-align:center;">
                                <span class="text-muted">×</span>
                                <input type="number" class="form-control form-control-sm" id="thH" value="915" onchange="thApplySize()" style="width:60px;font-size:12px;text-align:center;">
                            </div>
                            <select class="form-select form-select-sm" onchange="thSetPage(this.value)" style="width:auto;font-size:12px;">
                                <option value="index.php">홈</option>
                                <option value="hospitals.php">병원</option>
                                <option value="reviews.php">후기</option>
                                <option value="mypage.php">마이</option>
                            </select>
                            <button class="btn btn-sm btn-outline-secondary" onclick="thReload()" title="새로고침"><i class="bi bi-arrow-clockwise"></i></button>
                            <button class="btn btn-sm btn-outline-primary" onclick="window.open(thBase+thPage,'_blank')" title="새 탭"><i class="bi bi-box-arrow-up-right"></i></button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0 text-center">
                    <iframe id="thPreviewFrame" src="<?php echo SITE_FULL_URL; ?>index.php" style="width:412px;height:915px;border:none;border-radius:0 0 8px 8px;"></iframe>
                </div>
            </div>
        </div>

    </div><!-- /.tab-content -->

    <!-- 하단 고정 저장 바 -->
    <div class="sticky-save-bar d-flex gap-2 mt-3">
        <button type="submit" name="save_theme" class="btn btn-primary btn-sm">
            <i class="bi bi-check-lg"></i> 저장하기
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="location.reload()">
            <i class="bi bi-arrow-counterclockwise"></i> 초기화
        </button>
        <button type="button" class="btn btn-outline-info btn-sm" onclick="window.open('index.php', '_blank')">
            <i class="bi bi-box-arrow-up-right"></i> 실제 페이지 보기
        </button>
    </div>
</form>

<?php
$admin_extra_js = '
// 라이브 프리뷰
var thBase = "' . SITE_FULL_URL . '";
var thPage = "index.php";
function thReload() { var f=document.getElementById("thPreviewFrame"); if(f) f.src = thBase + thPage + "?_t=" + Date.now(); }
function thSetPage(p) { thPage = p; thReload(); }
function thApplyDevice(v) { var s=v.split(","); document.getElementById("thW").value=s[0]; document.getElementById("thH").value=s[1]; thApplySize(); }
function thApplySize() { var f=document.getElementById("thPreviewFrame"); if(f) { f.style.width=(parseInt(document.getElementById("thW").value)||412)+"px"; f.style.height=(parseInt(document.getElementById("thH").value)||915)+"px"; } }

// 폰트 미리보기 업데이트
function updateFontPreview(type, fontName) {
    var previewId = type + "-preview";
    var preview = document.getElementById(previewId);
    if (preview) {
        preview.style.fontFamily = "\'" + fontName + "\', sans-serif";
    }

    // Google Fonts 동적 로드
    var link = document.createElement("link");
    link.href = "https://fonts.googleapis.com/css2?family=" + fontName.replace(/ /g, "+") + ":wght@400;700&display=swap";
    link.rel = "stylesheet";
    document.head.appendChild(link);

    updatePreview();
}

// 실시간 미리보기 업데이트
function updatePreview() {
    var form = document.getElementById("themeForm");
    var previewBox = document.getElementById("previewBox");
    if (!form || !previewBox) return;

    // 버튼 색상 업데이트
    var bookingBtn = document.getElementById("preview-booking-btn");
    var consultBtn = document.getElementById("preview-consultation-btn");
    var moreLink = document.getElementById("preview-more-link");

    if (bookingBtn && form.primary_color) {
        bookingBtn.style.background = form.primary_color.value;
        if (form.button_text_color) bookingBtn.style.color = form.button_text_color.value;
    }
    if (consultBtn && form.secondary_color) {
        consultBtn.style.background = form.secondary_color.value;
        if (form.button_text_color) consultBtn.style.color = form.button_text_color.value;
    }
    if (moreLink && form.link_color) {
        moreLink.style.color = form.link_color.value;
    }

    // 텍스트 업데이트
    var nameEl = document.getElementById("preview-site-name");
    var tipsEl = document.getElementById("preview-tips-title");
    var hospsEl = document.getElementById("preview-hospitals-title");
    var catsEl = document.getElementById("preview-categories-title");

    if (nameEl && form.site_name) nameEl.textContent = form.site_name.value;
    if (tipsEl && form.section_tips_title) tipsEl.textContent = form.section_tips_title.value;
    if (hospsEl && form.section_hospitals_title) hospsEl.textContent = form.section_hospitals_title.value;
    if (catsEl && form.section_categories_title) catsEl.textContent = form.section_categories_title.value;

    if (bookingBtn && form.button_booking) bookingBtn.textContent = form.button_booking.value;
    if (consultBtn && form.button_consultation) consultBtn.textContent = form.button_consultation.value;
    if (moreLink && form.button_more) moreLink.innerHTML = form.button_more.value + " &rarr;";

    // 폰트 업데이트
    if (form.font_korean && form.font_english) {
        previewBox.style.fontFamily = "\'" + form.font_korean.value + "\', \'" + form.font_english.value + "\', sans-serif";
    }
}

// 색상 입력 변경 시 미리보기 업데이트
document.querySelectorAll(".form-control-color").forEach(function(input) {
    input.addEventListener("input", updatePreview);
});

// 텍스트 입력 변경 시 미리보기 업데이트
document.querySelectorAll(".form-control").forEach(function(input) {
    input.addEventListener("input", updatePreview);
});

// 폰트 선택 변경 시 미리보기 업데이트
document.querySelectorAll(".form-select").forEach(function(select) {
    select.addEventListener("change", updatePreview);
});

// 페이지 로드 시 미리보기 초기화
window.addEventListener("load", function() {
    updatePreview();
});
';

include __DIR__ . '/includes/admin_footer.php';
mysqli_close($connect);
?>
