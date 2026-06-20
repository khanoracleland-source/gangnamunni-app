<?php
/**
 * ========================================
 * 고급 테마 관리 페이지 (Advanced)
 * Bootstrap 5.3 + 공통 관리자 레이아웃
 * ========================================
 */

session_start();
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);

if (!$connect) {
    die("MySQL 연결 실패: " . mysqli_connect_error());
}

mysqli_set_charset($connect, 'utf8mb4');

// 로그인 체크 (공통 세션 키 사용)
$is_logged_in = isset($_SESSION['ad_admin_logged_in']) && $_SESSION['ad_admin_logged_in'] === true;
if (!$is_logged_in) { header('Location: dashboard.php'); exit; }
require_permission('theme_advanced');
if (isset($_GET['logout'])) { session_destroy(); header('Location: dashboard.php'); exit; }

$admin_user = $_SESSION['ad_admin_user'] ?? 'admin';

// 테마 설정 로드 (getTheme 함수 사용을 위해)
loadThemeSettings($connect);

// 프리셋 적용
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_preset'])) {
    $preset_id = intval($_POST['preset_id']);

    $query = "SELECT theme_data FROM gangnamunni_theme_presets WHERE id = {$preset_id}";
    $result = mysqli_query($connect, $query);

    if ($result && $row = mysqli_fetch_assoc($result)) {
        $theme_data = json_decode($row['theme_data'], true);

        foreach ($theme_data as $key => $value) {
            $escaped_key = mysqli_real_escape_string($connect, $key);
            $escaped_value = mysqli_real_escape_string($connect, $value);

            mysqli_query($connect, "UPDATE gangnamunni_theme_settings
                                    SET setting_value = '{$escaped_value}'
                                    WHERE setting_key = '{$escaped_key}'");
        }

        // 이력 저장
        $action = "프리셋 적용: " . mysqli_real_escape_string($connect, $_POST['preset_name']);
        $ip = $_SERVER['REMOTE_ADDR'];
        mysqli_query($connect, "INSERT INTO gangnamunni_theme_history (admin_user, action, ip_address)
                                VALUES ('{$admin_user}', '{$action}', '{$ip}')");

        $save_message = '프리셋이 적용되었습니다.';
    }
}

// 설정 저장 처리 (기존과 동일하지만 이력 추가)
$save_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_theme'])) {
    $changed_settings = [];

    foreach ($_POST as $key => $value) {
        if ($key !== 'save_theme') {
            $escaped_key = mysqli_real_escape_string($connect, $key);
            $escaped_value = mysqli_real_escape_string($connect, $value);

            // 이전 값 가져오기
            $prev_result = mysqli_query($connect, "SELECT setting_value FROM gangnamunni_theme_settings WHERE setting_key = '{$escaped_key}'");
            $prev_value = ($prev_result && $row = mysqli_fetch_assoc($prev_result)) ? $row['setting_value'] : '';

            if ($prev_value !== $value) {
                $changed_settings[$key] = ['old' => $prev_value, 'new' => $value];
            }

            $query = "UPDATE gangnamunni_theme_settings
                     SET setting_value = '{$escaped_value}'
                     WHERE setting_key = '{$escaped_key}'";
            mysqli_query($connect, $query);
        }
    }

    // 이력 저장
    if (!empty($changed_settings)) {
        $action = "테마 설정 변경";
        $changed_json = mysqli_real_escape_string($connect, json_encode($changed_settings, JSON_UNESCAPED_UNICODE));
        $ip = $_SERVER['REMOTE_ADDR'];
        mysqli_query($connect, "INSERT INTO gangnamunni_theme_history
                                (admin_user, action, changed_settings, ip_address)
                                VALUES ('{$admin_user}', '{$action}', '{$changed_json}', '{$ip}')");
    }

    $save_message = '테마 설정이 저장되었습니다.';
}

// 다크모드 토글 설정 추가
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_darkmode'])) {
    $darkmode_enabled = isset($_POST['darkmode_enabled']) ? 1 : 0;

    mysqli_query($connect, "INSERT INTO gangnamunni_theme_settings (setting_key, setting_value, setting_type)
                            VALUES ('darkmode_enabled', '{$darkmode_enabled}', 'boolean')
                            ON DUPLICATE KEY UPDATE setting_value = '{$darkmode_enabled}'");

    $save_message = '다크모드 설정이 저장되었습니다.';
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

// 프리셋 목록 가져오기
$presets = [];
$presets_query = "SELECT * FROM gangnamunni_theme_presets ORDER BY is_default DESC, name";
$presets_result = mysqli_query($connect, $presets_query);
if ($presets_result) {
    while ($row = mysqli_fetch_assoc($presets_result)) {
        $presets[] = $row;
    }
}

// 변경 이력 가져오기
$history = [];
$history_query = "SELECT * FROM gangnamunni_theme_history ORDER BY created_at DESC LIMIT 20";
$history_result = mysqli_query($connect, $history_query);
if ($history_result) {
    while ($row = mysqli_fetch_assoc($history_result)) {
        $history[] = $row;
    }
}

// 한글/영문 폰트 목록
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
    'Rubik' => 'Rubik',
    'Playfair Display' => 'Playfair Display'
];

// 공통 헤더 include
$admin_page_title = '고급 테마 관리';
$admin_current = 'theme_advanced';

// 페이지별 추가 CSS
$admin_extra_css = '
/* 색상 입력 그룹 */
.color-input-group {
    display: flex;
    gap: 8px;
    align-items: center;
}

.color-input-swatch {
    width: 48px;
    height: 38px;
    border: 1px solid var(--bs-border-color);
    border-radius: 6px;
    cursor: pointer;
    padding: 2px;
}

/* 프리셋 카드 */
.preset-card {
    border: 2px solid var(--bs-border-color);
    border-radius: 8px;
    padding: 16px;
    cursor: pointer;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.preset-card:hover {
    border-color: var(--bs-primary);
    box-shadow: 0 0 0 3px rgba(var(--bs-primary-rgb), 0.15);
}

.preset-card.preset-default {
    border-color: var(--bs-warning);
}

.preset-color-dot {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: 1px solid var(--bs-border-color);
    display: inline-block;
}

/* 다크모드 스위치 */
.darkmode-switch {
    transform: scale(1.3);
    margin-right: 8px;
}

/* 이력 타임라인 */
.history-item {
    border-left: 3px solid var(--bs-primary);
    padding-left: 12px;
}

/* 하단 고정 저장 바 */
.save-bar {
    position: sticky;
    bottom: 0;
    z-index: 100;
}

/* 접근성 대비 결과 */
.contrast-preview {
    width: 56px;
    height: 56px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 18px;
    border: 1px solid var(--bs-border-color);
}
';

include __DIR__ . '/includes/admin_header.php';
?>

<!-- 페이지 헤더 -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-gear-wide-connected"></i> 고급 테마 관리</h5>
    <span class="badge bg-secondary"><i class="bi bi-person"></i> <?php echo htmlspecialchars($admin_user); ?></span>
</div>

<!-- 알림 메시지 -->
<?php if ($save_message): ?>
<div class="alert alert-success alert-dismissible fade show py-2" role="alert">
    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($save_message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Bootstrap 탭 네비게이션 -->
<ul class="nav nav-pills nav-fill flex-nowrap overflow-auto mb-3" id="themeTab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active text-nowrap" id="tab-presets" data-bs-toggle="pill" data-bs-target="#pane-presets" type="button" role="tab">
            <i class="bi bi-box-seam"></i> 프리셋
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link text-nowrap" id="tab-colors" data-bs-toggle="pill" data-bs-target="#pane-colors" type="button" role="tab">
            <i class="bi bi-palette"></i> 색상
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link text-nowrap" id="tab-fonts" data-bs-toggle="pill" data-bs-target="#pane-fonts" type="button" role="tab">
            <i class="bi bi-fonts"></i> 폰트
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link text-nowrap" id="tab-text" data-bs-toggle="pill" data-bs-target="#pane-text" type="button" role="tab">
            <i class="bi bi-card-text"></i> 텍스트
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link text-nowrap" id="tab-darkmode" data-bs-toggle="pill" data-bs-target="#pane-darkmode" type="button" role="tab">
            <i class="bi bi-moon-stars"></i> 다크모드
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link text-nowrap" id="tab-logo" data-bs-toggle="pill" data-bs-target="#pane-logo" type="button" role="tab">
            <i class="bi bi-image"></i> 로고
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link text-nowrap" id="tab-contrast" data-bs-toggle="pill" data-bs-target="#pane-contrast" type="button" role="tab">
            <i class="bi bi-universal-access"></i> 접근성
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link text-nowrap" id="tab-history" data-bs-toggle="pill" data-bs-target="#pane-history" type="button" role="tab">
            <i class="bi bi-clock-history"></i> 이력
        </button>
    </li>
</ul>

<form method="POST" action="" id="themeForm" enctype="multipart/form-data">
<div class="tab-content" id="themeTabContent">

    <!-- ============================================================ -->
    <!-- 프리셋 탭 -->
    <!-- ============================================================ -->
    <div class="tab-pane fade show active" id="pane-presets" role="tabpanel">
        <div class="card mb-3">
            <div class="card-header py-2">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-box-seam"></i> 테마 프리셋</h6>
            </div>
            <div class="card-body">
                <p class="text-body-secondary small mb-3">미리 만들어진 테마를 선택하여 원클릭으로 적용하세요.</p>

                <div class="row g-3">
                    <?php foreach ($presets as $preset):
                        $theme_data = json_decode($preset['theme_data'], true);
                        $primary = $theme_data['primary_color'] ?? '#667eea';
                        $secondary = $theme_data['secondary_color'] ?? '#764ba2';
                    ?>
                    <div class="col-sm-6 col-lg-4 col-xl-3">
                        <div class="preset-card <?php echo $preset['is_default'] ? 'preset-default' : ''; ?> h-100 position-relative">
                            <?php if ($preset['is_default']): ?>
                            <span class="badge bg-warning text-dark position-absolute top-0 end-0 m-2">기본</span>
                            <?php endif; ?>

                            <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($preset['name']); ?></h6>
                            <p class="text-body-secondary small mb-2"><?php echo htmlspecialchars($preset['description']); ?></p>

                            <div class="d-flex gap-2 mb-3">
                                <span class="preset-color-dot" style="background: <?php echo $primary; ?>"></span>
                                <span class="preset-color-dot" style="background: <?php echo $secondary; ?>"></span>
                            </div>

                            <form method="POST" class="d-inline">
                                <input type="hidden" name="preset_id" value="<?php echo $preset['id']; ?>">
                                <input type="hidden" name="preset_name" value="<?php echo htmlspecialchars($preset['name']); ?>">
                                <button type="submit" name="apply_preset" class="btn btn-primary btn-sm w-100">
                                    <i class="bi bi-check2-circle"></i> 적용하기
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php if (empty($presets)): ?>
                    <div class="col-12">
                        <div class="text-center text-body-secondary py-5">
                            <i class="bi bi-box-seam d-block" style="font-size:48px;"></i>
                            등록된 프리셋이 없습니다.
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- 색상 설정 탭 -->
    <!-- ============================================================ -->
    <div class="tab-pane fade" id="pane-colors" role="tabpanel">
        <div class="card mb-3">
            <div class="card-header py-2">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-palette"></i> 브랜드 색상</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php
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

                    foreach ($color_settings as $key => $label):
                        $value = $settings[$key]['setting_value'] ?? '#000000';
                    ?>
                    <div class="col-sm-6 col-lg-4">
                        <label class="form-label fw-semibold small"><?php echo $label; ?></label>
                        <div class="color-input-group">
                            <input type="color"
                                   class="form-control form-control-color color-input-swatch"
                                   value="<?php echo $value; ?>"
                                   onchange="document.getElementById('<?php echo $key; ?>_text').value = this.value;">
                            <input type="text"
                                   class="form-control form-control-sm font-monospace"
                                   id="<?php echo $key; ?>_text"
                                   name="<?php echo $key; ?>"
                                   value="<?php echo $value; ?>"
                                   placeholder="#000000">
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- 폰트 설정 탭 -->
    <!-- ============================================================ -->
    <div class="tab-pane fade" id="pane-fonts" role="tabpanel">
        <div class="card mb-3">
            <div class="card-header py-2">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-fonts"></i> 폰트 설정</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">한글 폰트</label>
                        <select name="font_korean" class="form-select form-select-sm" onchange="updateFontPreview('korean', this.value)">
                            <?php foreach ($korean_fonts as $fval => $flabel): ?>
                            <option value="<?php echo $fval; ?>"
                                    <?php echo ($settings['font_korean']['setting_value'] ?? '') === $fval ? 'selected' : ''; ?>>
                                <?php echo $flabel; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">영문 폰트</label>
                        <select name="font_english" class="form-select form-select-sm" onchange="updateFontPreview('english', this.value)">
                            <?php foreach ($english_fonts as $fval => $flabel): ?>
                            <option value="<?php echo $fval; ?>"
                                    <?php echo ($settings['font_english']['setting_value'] ?? '') === $fval ? 'selected' : ''; ?>>
                                <?php echo $flabel; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">기본 폰트 크기</label>
                        <input type="text" name="font_size_base" class="form-control form-control-sm"
                               value="<?php echo $settings['font_size_base']['setting_value'] ?? '14px'; ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">일반 폰트 굵기</label>
                        <input type="number" name="font_weight_normal" class="form-control form-control-sm"
                               value="<?php echo $settings['font_weight_normal']['setting_value'] ?? '400'; ?>"
                               min="100" max="900" step="100">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- 텍스트 설정 탭 -->
    <!-- ============================================================ -->
    <div class="tab-pane fade" id="pane-text" role="tabpanel">
        <!-- 기본 뷰 설정 -->
        <div class="card mb-3">
            <div class="card-header py-2">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-layout-split"></i> 기본 뷰 설정</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">병원 리스트 기본 뷰</label>
                        <select name="default_hospital_view" class="form-select form-select-sm">
                            <option value="card" <?php echo getTheme('default_hospital_view') === 'card' ? 'selected' : ''; ?>>카드형 (기본)</option>
                            <option value="list" <?php echo getTheme('default_hospital_view') === 'list' ? 'selected' : ''; ?>>리스트형</option>
                        </select>
                        <div class="form-text">사용자가 처음 방문 시 기본으로 표시될 뷰 형태입니다. 사용자가 변경하면 해당 사용자는 변경된 설정이 유지됩니다.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">꿀팁 리스트 기본 뷰</label>
                        <select name="default_tip_view" class="form-select form-select-sm">
                            <option value="card" <?php echo getTheme('default_tip_view') === 'card' ? 'selected' : ''; ?>>카드형 (기본)</option>
                            <option value="list" <?php echo getTheme('default_tip_view') === 'list' ? 'selected' : ''; ?>>리스트형</option>
                        </select>
                        <div class="form-text">사용자가 처음 방문 시 기본으로 표시될 뷰 형태입니다.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 사이트 기본 정보 -->
        <div class="card mb-3">
            <div class="card-header py-2">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-card-text"></i> 사이트 기본 정보</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">사이트 이름</label>
                        <input type="text" name="site_name" class="form-control form-control-sm"
                               value="<?php echo $settings['site_name']['setting_value'] ?? '서울미녀'; ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">섹션 제목 - 꿀팁</label>
                        <input type="text" name="section_tips_title" class="form-control form-control-sm"
                               value="<?php echo $settings['section_tips_title']['setting_value'] ?? '서울미녀 꿀팁'; ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">섹션 제목 - 병원</label>
                        <input type="text" name="section_hospitals_title" class="form-control form-control-sm"
                               value="<?php echo $settings['section_hospitals_title']['setting_value'] ?? '추천 병원'; ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- 다크모드 탭 -->
    <!-- ============================================================ -->
    <div class="tab-pane fade" id="pane-darkmode" role="tabpanel">
        <div class="card mb-3">
            <div class="card-header py-2">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-moon-stars"></i> 다크모드 설정</h6>
            </div>
            <div class="card-body">
                <p class="text-body-secondary small mb-3">사용자가 다크모드를 선택할 수 있도록 설정합니다.</p>

                <div class="d-flex align-items-center gap-3 p-3 bg-body-tertiary rounded">
                    <div class="form-check form-switch">
                        <input class="form-check-input darkmode-switch" type="checkbox" role="switch"
                               id="darkmodeEnabled" name="darkmode_enabled"
                               <?php echo ($settings['darkmode_enabled']['setting_value'] ?? '0') == '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold" for="darkmodeEnabled">다크모드 활성화</label>
                    </div>
                </div>

                <div class="mt-4 p-3 bg-body-tertiary rounded">
                    <h6 class="fw-semibold mb-3">다크모드 색상 프리뷰</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="p-3 bg-white rounded border">
                                <strong class="text-dark">라이트 모드</strong>
                                <div class="mt-2 p-3 bg-white text-dark border rounded">
                                    밝은 배경에 어두운 텍스트
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 rounded" style="background: #1a1a1a;">
                                <strong class="text-white">다크 모드</strong>
                                <div class="mt-2 p-3 rounded" style="background: #121212; color: #E0E0E0; border: 1px solid #333;">
                                    어두운 배경에 밝은 텍스트
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- 로고 관리 탭 -->
    <!-- ============================================================ -->
    <div class="tab-pane fade" id="pane-logo" role="tabpanel">
        <div class="card mb-3">
            <div class="card-header py-2">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-image"></i> 로고 관리</h6>
            </div>
            <div class="card-body">
                <p class="text-body-secondary small mb-3">로고 이미지를 업로드하면 자동으로 여러 크기로 생성됩니다.</p>

                <div class="border border-2 border-dashed rounded text-center p-5 bg-body-tertiary"
                     style="cursor: pointer;"
                     onclick="document.getElementById('logoUpload').click()">
                    <i class="bi bi-cloud-arrow-up d-block mb-2" style="font-size: 48px; color: var(--bs-secondary-color);"></i>
                    <h6 class="fw-semibold mb-1">로고 이미지 업로드</h6>
                    <p class="text-body-secondary small mb-0">PNG, JPG, SVG 파일 지원 (권장: 1024x1024)</p>
                    <input type="file" id="logoUpload" name="logo_upload" accept="image/*" class="d-none">
                </div>

                <div class="mt-4">
                    <h6 class="fw-semibold mb-2">현재 로고</h6>
                    <div class="p-4 bg-body-tertiary rounded text-center">
                        <?php
                        $logo_path = $settings['site_logo_path']['setting_value'] ?? 'assets/images/logo_128.png';
                        if (file_exists(__DIR__ . '/' . $logo_path)):
                        ?>
                            <img src="<?php echo $logo_path; ?>" alt="Logo" class="img-fluid" style="max-width: 200px; max-height: 200px;">
                        <?php else: ?>
                            <i class="bi bi-hospital d-block" style="font-size: 64px; color: var(--bs-secondary-color);"></i>
                            <p class="text-body-secondary mt-2 mb-0">로고가 없습니다</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- 접근성 탭 -->
    <!-- ============================================================ -->
    <div class="tab-pane fade" id="pane-contrast" role="tabpanel">
        <div class="card mb-3">
            <div class="card-header py-2">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-universal-access"></i> 색상 접근성 검사</h6>
            </div>
            <div class="card-body">
                <p class="text-body-secondary small mb-3">WCAG 2.1 AA 기준에 따라 색상 대비를 검사합니다.</p>

                <div class="p-3 bg-body-tertiary rounded mb-3">
                    <h6 class="fw-semibold mb-3">텍스트 색상 vs 배경색</h6>
                    <div id="contrastResults">
                        <p class="text-body-secondary small mb-0">"접근성" 탭을 열면 자동으로 대비 검사가 실행됩니다.</p>
                    </div>
                </div>

                <div class="alert alert-info mb-0">
                    <h6 class="alert-heading fw-semibold small">
                        <i class="bi bi-universal-access"></i> 접근성 가이드
                    </h6>
                    <ul class="mb-0 small">
                        <li>일반 텍스트: 4.5:1 이상</li>
                        <li>큰 텍스트: 3:1 이상</li>
                        <li>색맹 사용자 고려</li>
                        <li>명도 차이 충분히 확보</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- 변경 이력 탭 -->
    <!-- ============================================================ -->
    <div class="tab-pane fade" id="pane-history" role="tabpanel">
        <div class="card mb-3">
            <div class="card-header py-2">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history"></i> 변경 이력 (최근 20개)</h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($history)): ?>
                <div class="text-center text-body-secondary py-5">
                    <i class="bi bi-clock-history d-block" style="font-size:48px;"></i>
                    변경 이력이 없습니다.
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush" style="max-height: 500px; overflow-y: auto;">
                    <?php foreach ($history as $item): ?>
                    <div class="list-group-item">
                        <div class="history-item">
                            <div class="d-flex gap-3 text-body-secondary small mb-1">
                                <span><i class="bi bi-person"></i> <?php echo htmlspecialchars($item['admin_user']); ?></span>
                                <span><i class="bi bi-calendar3"></i> <?php echo $item['created_at']; ?></span>
                                <span><i class="bi bi-globe"></i> <?php echo htmlspecialchars($item['ip_address']); ?></span>
                            </div>
                            <div class="fw-semibold small"><?php echo htmlspecialchars($item['action']); ?></div>
                            <?php if ($item['changed_settings']): ?>
                            <details class="mt-1">
                                <summary class="text-primary small" style="cursor: pointer;">변경 내역 보기</summary>
                                <pre class="mt-1 p-2 bg-body-tertiary rounded small mb-0" style="font-size: 11px; overflow-x: auto;"><?php echo htmlspecialchars($item['changed_settings']); ?></pre>
                            </details>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div><!-- /.tab-content -->

<!-- 하단 저장 버튼 바 -->
<div class="save-bar card border-top mt-3">
    <div class="card-body py-2 d-flex gap-2 align-items-center">
        <button type="submit" name="save_theme" class="btn btn-primary btn-sm fw-bold">
            <i class="bi bi-floppy"></i> 변경사항 저장
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="location.reload()">
            <i class="bi bi-arrow-clockwise"></i> 새로고침
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.open('index.php', '_blank')">
            <i class="bi bi-box-arrow-up-right"></i> 사이트 보기
        </button>
    </div>
</div>
</form>

<?php
// 페이지별 추가 JS
$admin_extra_js = '
// 폰트 미리보기 업데이트
function updateFontPreview(type, fontName) {
    var link = document.createElement("link");
    link.href = "https://fonts.googleapis.com/css2?family=" + fontName.replace(/ /g, "+") + ":wght@400;700&display=swap";
    link.rel = "stylesheet";
    document.head.appendChild(link);
}

// 접근성 탭 활성화 시 대비 검사 실행
var contrastTab = document.getElementById("tab-contrast");
if (contrastTab) {
    contrastTab.addEventListener("shown.bs.tab", function() {
        checkContrast();
    });
}

// 색상 대비 검사
function checkContrast() {
    var form = document.getElementById("themeForm");
    var textColorEl = form.querySelector("[name=text_color]");
    var bgColorEl = form.querySelector("[name=background_color]");

    if (!textColorEl || !bgColorEl) return;

    var textColor = textColorEl.value || "#000000";
    var bgColor = bgColorEl.value || "#FFFFFF";

    var ratio = calculateContrastRatio(textColor, bgColor);
    var pass = ratio >= 4.5;

    var html = "<div class=\"d-flex align-items-center gap-3 p-3 bg-body rounded\">" +
        "<div class=\"contrast-preview\" style=\"background:" + bgColor + ";\">" +
        "<span style=\"color:" + textColor + ";\">Aa</span></div>" +
        "<div class=\"flex-grow-1\">" +
        "<div class=\"fw-bold small mb-1\">대비율: " + ratio.toFixed(2) + ":1</div>" +
        "<span class=\"badge " + (pass ? "bg-success" : "bg-danger") + "\">" +
        (pass ? "WCAG AA 통과" : "WCAG AA 실패") + "</span>" +
        "</div></div>";

    document.getElementById("contrastResults").innerHTML = html;
}

// 대비율 계산
function calculateContrastRatio(color1, color2) {
    function getLuminance(color) {
        var rgb = parseInt(color.slice(1), 16);
        var r = ((rgb >> 16) & 0xff) / 255;
        var g = ((rgb >> 8) & 0xff) / 255;
        var b = ((rgb >> 0) & 0xff) / 255;

        var a = [r, g, b].map(function(v) {
            return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
        });

        return a[0] * 0.2126 + a[1] * 0.7152 + a[2] * 0.0722;
    }

    var lum1 = getLuminance(color1);
    var lum2 = getLuminance(color2);
    var brightest = Math.max(lum1, lum2);
    var darkest = Math.min(lum1, lum2);

    return (brightest + 0.05) / (darkest + 0.05);
}
';

include __DIR__ . '/includes/admin_footer.php';
mysqli_close($connect);
?>
