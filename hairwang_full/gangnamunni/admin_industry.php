<?php
/**
 * 업종 선택 관리 페이지
 * 멀티 산업 플랫폼 — 업종 프리셋 전환 + 용어 미리보기 + 도메인/브랜딩 설정
 */
session_start();
$admin_page_title = '업종 설정';
$admin_current = 'industry';
$admin_page_description = '플랫폼 업종을 선택하면 용어, 카테고리, 테마 색상이 자동으로 변경됩니다.';
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_admin_login();
require_permission('site_settings');

require_once __DIR__ . '/includes/industry_terms.php';
require_once __DIR__ . '/includes/settings_helper.php';

// 현재 업종
$current_preset = industry_id();

// AJAX 저장
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    if ($_POST['action'] === 'change_industry') {
        $new_id = trim($_POST['preset_id'] ?? '');
        $presets_dir = __DIR__ . '/includes/presets/';
        if (!file_exists($presets_dir . $new_id . '.json')) {
            echo json_encode(['success' => false, 'error' => '프리셋 없음']);
            exit;
        }

        // DB에 저장
        updateSetting($connect, 'industry_preset', $new_id);

        // dbconfig.php에 INDUSTRY_PRESET 상수 업데이트 (있으면)
        $dbconfig_path = __DIR__ . '/../dbconfig.php';
        if (file_exists($dbconfig_path) && is_writable($dbconfig_path)) {
            $content = file_get_contents($dbconfig_path);
            if (strpos($content, 'INDUSTRY_PRESET') !== false) {
                $content = preg_replace(
                    "/define\s*\(\s*'INDUSTRY_PRESET'\s*,\s*'[^']*'\s*\)/",
                    "define('INDUSTRY_PRESET', '{$new_id}')",
                    $content
                );
            } else {
                // 상수 없으면 추가
                $content = rtrim($content) . "\ndefine('INDUSTRY_PRESET', '{$new_id}');\n";
            }
            file_put_contents($dbconfig_path, $content);
        }

        // 프리셋 로드 → 테마 색상 자동 변경
        IndustryPreset::getInstance()->load($new_id);
        $preset_data = IndustryPreset::getInstance()->getAll();
        $theme = $preset_data['default_theme'] ?? [];
        if (!empty($theme['primary_color'])) {
            $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_theme_settings SET setting_value = ? WHERE setting_key = 'primary_color'");
            if ($stmt) { mysqli_stmt_bind_param($stmt, 's', $theme['primary_color']); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt); }
        }

        echo json_encode(['success' => true, 'preset_id' => $new_id, 'display_name' => $preset_data['display_name'] ?? $new_id]);
        exit;
    }

    if ($_POST['action'] === 'save_branding') {
        $fields = ['site_domain', 'site_base_path', 'app_name', 'app_package_id'];
        foreach ($fields as $f) {
            if (isset($_POST[$f])) {
                updateSetting($connect, $f, trim($_POST[$f]));
            }
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_POST['action'] === 'preview_preset') {
        $pid = trim($_POST['preset_id'] ?? '');
        $file = __DIR__ . '/includes/presets/' . $pid . '.json';
        if (file_exists($file)) {
            echo file_get_contents($file);
        } else {
            echo json_encode(['error' => 'not found']);
        }
        exit;
    }

    exit;
}

// 프리셋 목록
$available = IndustryPreset::getAvailablePresets();

// 브랜딩 설정
$branding = [
    'site_domain' => getSettingValue($connect, 'site_domain', 'hms.seoulbeauty.app'),
    'site_base_path' => getSettingValue($connect, 'site_base_path', '/hairwang/gangnamunni/'),
    'app_name' => getSettingValue($connect, 'app_name', site_name()),
    'app_package_id' => getSettingValue($connect, 'app_package_id', 'com.celebhospital.patient'),
];

include __DIR__ . '/includes/admin_header.php';
?>

<style>
.preset-card { border:2px solid var(--bs-border-color); border-radius:12px; padding:20px; cursor:pointer; transition:all 0.2s; text-align:center; height:100%; }
.preset-card:hover { transform:translateY(-2px); box-shadow:0 4px 16px rgba(0,0,0,0.1); }
.preset-card.active { border-color:var(--bs-primary); background:rgba(var(--bs-primary-rgb),0.05); }
.preset-icon { font-size:2.5rem; margin-bottom:8px; }
.preset-name { font-size:16px; font-weight:700; }
.preset-desc { font-size:12px; color:var(--bs-secondary-color); margin-top:4px; }
.term-preview { background:var(--bs-tertiary-bg); border-radius:8px; padding:16px; }
.term-row { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid var(--bs-border-color-translucent); font-size:13px; }
.term-row:last-child { border-bottom:none; }
.term-key { color:var(--bs-secondary-color); }
.term-val { font-weight:600; }
.color-dot { width:20px; height:20px; border-radius:50%; display:inline-block; border:1px solid rgba(0,0,0,0.1); vertical-align:middle; }
.brand-input { max-width:400px; }
.save-indicator { font-size:12px; margin-left:8px; }
</style>

<div class="container-fluid py-3">
<div class="row g-4">

<!-- LEFT: 업종 선택 -->
<div class="col-lg-7">
    <h4 class="mb-3"><i class="bi bi-buildings me-2"></i>업종 설정</h4>

    <!-- 업종 카드 그리드 -->
    <div class="row g-3 mb-4">
        <?php foreach ($available as $p): ?>
        <div class="col-6 col-md-4">
            <div class="preset-card <?php echo $p['id'] === $current_preset ? 'active' : ''; ?>"
                 onclick="selectPreset('<?php echo $p['id']; ?>')" data-id="<?php echo $p['id']; ?>">
                <div class="preset-icon"><i class="bi <?php echo $p['icon']; ?>"></i></div>
                <div class="preset-name"><?php echo htmlspecialchars($p['name']); ?></div>
                <div class="preset-desc"><?php echo htmlspecialchars($p['name_en']); ?></div>
                <?php if ($p['id'] === $current_preset): ?>
                <span class="badge bg-primary mt-2">현재</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 용어 미리보기 -->
    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0"><i class="bi bi-translate me-2"></i>용어 미리보기</h6></div>
        <div class="card-body">
            <div class="term-preview" id="termPreview">
                <div class="text-center text-muted py-3">업종을 선택하면 용어가 표시됩니다</div>
            </div>
        </div>
    </div>

    <!-- 카테고리 미리보기 -->
    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0"><i class="bi bi-grid-3x3-gap me-2"></i>기본 카테고리</h6></div>
        <div class="card-body">
            <div id="catPreview" class="d-flex flex-wrap gap-2">
                <span class="text-muted">업종 선택 시 표시</span>
            </div>
        </div>
    </div>

    <!-- 브랜딩/도메인 설정 -->
    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0"><i class="bi bi-globe me-2"></i>브랜딩 · 도메인 설정</h6></div>
        <div class="card-body">
            <?php
            $brand_fields = [
                ['key'=>'site_domain',     'label'=>'도메인',        'placeholder'=>'hms.seoulbeauty.app',     'icon'=>'bi-globe2'],
                ['key'=>'site_base_path',  'label'=>'URL 경로',     'placeholder'=>'/hairwang/gangnamunni/',   'icon'=>'bi-folder'],
                ['key'=>'app_name',        'label'=>'앱 이름',      'placeholder'=>'서울미녀',                  'icon'=>'bi-phone'],
                ['key'=>'app_package_id',  'label'=>'앱 패키지 ID', 'placeholder'=>'com.celebhospital.patient', 'icon'=>'bi-box'],
            ];
            foreach ($brand_fields as $bf):
            ?>
            <div class="mb-3 d-flex align-items-center gap-2">
                <label class="form-label mb-0 fw-bold" style="min-width:120px;"><i class="bi <?php echo $bf['icon']; ?> me-1"></i><?php echo $bf['label']; ?></label>
                <input type="text" class="form-control form-control-sm brand-input js-brand"
                       data-key="<?php echo $bf['key']; ?>"
                       value="<?php echo htmlspecialchars($branding[$bf['key']]); ?>"
                       placeholder="<?php echo $bf['placeholder']; ?>">
                <span class="save-indicator" id="bi-<?php echo $bf['key']; ?>"></span>
            </div>
            <?php endforeach; ?>
            <div class="text-muted small mt-2"><i class="bi bi-info-circle me-1"></i>변경 시 자동 저장됩니다. 도메인 변경 후 Apache vhost + SSL 설정이 필요합니다.</div>
        </div>
    </div>
</div>

<!-- RIGHT: 라이브 프리뷰 -->
<div class="col-lg-5">
    <div style="position:sticky;top:70px;" class="text-center">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-bold"><i class="bi bi-phone me-1"></i>프리뷰</span>
            <select class="form-select form-select-sm" id="indDevSel" onchange="indApplyDevice(this.value)" style="width:auto;font-size:12px;">
                <option value="412,915">Galaxy S26</option>
                <option value="375,812">iPhone XS</option>
                <option value="1280,800">PC</option>
            </select>
            <button class="btn btn-sm btn-outline-secondary" onclick="indReload()"><i class="bi bi-arrow-clockwise"></i></button>
        </div>
        <iframe id="indPreview" style="width:412px;height:915px;border:2px solid var(--bs-border-color);border-radius:8px;background:var(--bs-body-bg);"
                src="https://<?php echo htmlspecialchars($branding['site_domain'] . $branding['site_base_path']); ?>index.php"></iframe>
    </div>
</div>

</div>
</div>

<script>
var currentPreset = '<?php echo $current_preset; ?>';

function selectPreset(id) {
    // 미리보기 로드
    fetch('admin_industry.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=preview_preset&preset_id=' + encodeURIComponent(id)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.error) return;

        // 용어 표시
        var terms = data.terminology || {};
        var html = '';
        var keys = ['business','staff','client','service','appointment','department','review','category','booking','consultation','tips','news','price','ranking','location'];
        keys.forEach(function(k) {
            if (terms[k]) html += '<div class="term-row"><span class="term-key">' + k + '</span><span class="term-val">' + terms[k] + '</span></div>';
        });
        document.getElementById('termPreview').innerHTML = html;

        // 카테고리 표시
        var cats = data.default_categories || [];
        var chtml = '';
        cats.forEach(function(c) {
            chtml += '<span class="badge bg-light text-dark border" style="font-size:13px;padding:6px 12px;">' + (c.emoji || '') + ' ' + c.name + '</span>';
        });
        document.getElementById('catPreview').innerHTML = chtml || '<span class="text-muted">카테고리 없음</span>';

        // 카드 active 표시
        document.querySelectorAll('.preset-card').forEach(function(c) {
            c.classList.toggle('active', c.dataset.id === id);
        });
    });
}

// 카드 더블클릭 → 업종 변경 적용
document.querySelectorAll('.preset-card').forEach(function(card) {
    card.addEventListener('dblclick', function() {
        var id = this.dataset.id;
        if (id === currentPreset) return;
        if (!confirm('"' + this.querySelector('.preset-name').textContent + '" 업종으로 변경하시겠습니까?\n용어, 카테고리, 테마 색상이 변경됩니다.')) return;

        fetch('admin_industry.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=change_industry&preset_id=' + encodeURIComponent(id)
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) {
                alert('업종이 "' + d.display_name + '"으로 변경되었습니다.');
                location.reload();
            }
        });
    });
});

// 브랜딩 자동 저장
document.querySelectorAll('.js-brand').forEach(function(el) {
    var timer;
    el.addEventListener('change', function() {
        clearTimeout(timer);
        saveBrand(this);
    });
    el.addEventListener('input', function() {
        var self = this;
        clearTimeout(timer);
        timer = setTimeout(function() { saveBrand(self); }, 800);
    });
});
function saveBrand(el) {
    var key = el.dataset.key;
    var st = document.getElementById('bi-' + key);
    if (st) st.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:12px;height:12px;"></span>';
    fetch('admin_industry.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=save_branding&' + key + '=' + encodeURIComponent(el.value)
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (st) st.innerHTML = d.success ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-x-circle text-danger"></i>';
        setTimeout(function() { if (st) st.innerHTML = ''; }, 2000);
    });
}

// 프리뷰
function indReload() { document.getElementById('indPreview').src = document.getElementById('indPreview').src.split('?')[0] + '?_t=' + Date.now(); }
function indApplyDevice(v) { var s=v.split(','); var f=document.getElementById('indPreview'); f.style.width=s[0]+'px'; f.style.height=s[1]+'px'; }

// 초기 로드: 현재 프리셋 미리보기
selectPreset(currentPreset);
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
