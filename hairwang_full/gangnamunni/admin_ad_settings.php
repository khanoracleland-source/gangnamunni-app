<?php
/**
 * 광고·디스플레이 설정 — 탭 + 라이브 프리뷰 + 즉시 저장
 */
session_start();
$admin_page_title = '광고·디스플레이 설정';
$admin_current = 'ad_settings';
$admin_page_description = '페이지별 광고 ON/OFF, 디자인, 레이아웃을 실시간 미리보기와 함께 관리합니다.';
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
if (!defined('SITE_FULL_URL')) define('SITE_FULL_URL', 'https://hms.seoulbeauty.app/hairwang/gangnamunni/');

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_admin_login();

require_once __DIR__ . '/includes/settings_helper.php';
$all = getAllSettings($connect);

// 탭 정의: 페이지별로 설정 그룹핑
$tabs = [
    'main' => [
        'label' => '메인 페이지',
        'icon' => 'bi-house-fill',
        'preview' => 'index.php',
        'items' => [
            ['key'=>'ad_show_index',         'label'=>'광고 배너 (gangnamunni_ads)',  'type'=>'toggle', 'ad'=>true,  'desc'=>'ON 시 아래 위치 설정에 따라 표시. OFF 시 광고 완전 숨김'],
            ['key'=>'ad_home_after_search',  'label'=>'  └ 위치: 검색바 아래',    'type'=>'toggle', 'ad'=>false, 'desc'=>'ON=검색바 아래에 표시 / OFF=분류-이벤트 사이에 표시'],
            ['key'=>'ad_home_after_events',  'label'=>'  └ 위치: 이벤트 아래 추가', 'type'=>'toggle', 'ad'=>false, 'desc'=>'ON=이벤트 섹션 아래에 광고 한 벌 더 표시'],
            ['key'=>'promo_show_index',      'label'=>'프로모션 배너 (gangnamunni_promos)', 'type'=>'toggle', 'ad'=>true,  'desc'=>'광고와 별도 데이터. 프로모션 캐러셀 슬라이드'],
            ['key'=>'ad_auto_scroll',        'label'=>'자동 슬라이드',           'type'=>'toggle'],
            ['key'=>'ad_auto_scroll_speed',  'label'=>'슬라이드 속도',           'type'=>'number', 'suffix'=>'ms'],
            ['key'=>'fd_ad_banner_sticky',   'label'=>'배너 고정 (sticky)',      'type'=>'toggle'],
            ['key'=>'fd_ad_banner_title',    'label'=>'배너 타이틀 텍스트',      'type'=>'text'],
        ],
    ],
    'hospitals' => [
        'label' => '병원 목록',
        'icon' => 'bi-building',
        'preview' => 'hospitals.php',
        'items' => [
            ['key'=>'ad_show_hospitals',       'label'=>'병원 페이지 배너',   'type'=>'toggle', 'ad'=>true],
            ['key'=>'ad_show_hospitals_infeed', 'label'=>'인피드 광고',       'type'=>'toggle', 'ad'=>true, 'desc'=>'병원 카드 사이 삽입 광고'],
            ['key'=>'ad_infeed_interval',      'label'=>'인피드 간격',       'type'=>'number', 'suffix'=>'번째 마다'],
        ],
    ],
    'reviews' => [
        'label' => '후기',
        'icon' => 'bi-chat-quote',
        'preview' => 'reviews.php',
        'items' => [
            ['key'=>'ad_show_reviews', 'label'=>'후기 페이지 광고', 'type'=>'toggle', 'ad'=>true],
        ],
    ],
    'events' => [
        'label' => '이벤트',
        'icon' => 'bi-calendar-event',
        'preview' => 'events.php',
        'items' => [
            ['key'=>'ad_show_events', 'label'=>'이벤트 페이지 광고', 'type'=>'toggle', 'ad'=>true],
        ],
    ],
    'community' => [
        'label' => '커뮤니티·칼럼',
        'icon' => 'bi-newspaper',
        'preview' => 'tips.php',
        'items' => [
            ['key'=>'ad_show_tips',    'label'=>'커뮤니티 페이지 광고', 'type'=>'toggle', 'ad'=>true],
            ['key'=>'ad_show_columns', 'label'=>'칼럼 페이지 광고',    'type'=>'toggle', 'ad'=>true],
        ],
    ],
    'consult' => [
        'label' => '상담',
        'icon' => 'bi-headset',
        'preview' => 'consultation.php',
        'items' => [
            ['key'=>'ad_show_consultation', 'label'=>'상담 페이지 광고', 'type'=>'toggle', 'ad'=>true],
        ],
    ],
    'design' => [
        'label' => '디자인',
        'icon' => 'bi-palette',
        'preview' => 'index.php',
        'items' => [
            ['key'=>'fd_header_bg_color',      'label'=>'헤더 배경색',          'type'=>'color'],
            ['key'=>'fd_search_border_radius', 'label'=>'검색바 둥글기',        'type'=>'number', 'suffix'=>'px'],
            ['key'=>'fd_card_border_radius',   'label'=>'카드 둥글기',          'type'=>'number', 'suffix'=>'px'],
            ['key'=>'fd_chip_border_radius',   'label'=>'칩 둥글기',            'type'=>'number', 'suffix'=>'px'],
        ],
    ],
];

include __DIR__ . '/includes/admin_header.php';
?>

<style>
.preview-frame { border:2px solid var(--bs-border-color); border-radius:8px; background:var(--bs-body-bg); transition:width 0.3s ease; }
.preview-wrap { position:sticky; top:70px; }
.size-input { width:60px; font-size:12px; text-align:center; padding:2px 4px; }
.setting-row { padding:10px 0; border-bottom:1px solid var(--bs-border-color-translucent); display:flex; align-items:center; gap:10px; }
.setting-row:last-child { border-bottom:none; }
.setting-label { flex:1; min-width:0; }
.setting-label .desc { font-size:12px; color:var(--bs-secondary-color); margin-top:2px; }
.setting-ctrl { flex-shrink:0; display:flex; align-items:center; gap:8px; }
.save-status { width:20px; text-align:center; }
.tab-badge { font-size:10px; vertical-align:middle; }
.ad-edit-btn { font-size:11px; padding:2px 8px; }
.color-group { display:flex; align-items:center; gap:4px; }
.color-group input[type=color] { width:36px; height:30px; padding:1px 2px; border:1px solid var(--bs-border-color); border-radius:4px; cursor:pointer; }
.color-group input[type=text] { width:90px; font-size:13px; }
</style>

<div class="container-fluid py-3">
<div class="row g-3">

<!-- LEFT: 탭 + 설정 -->
<div class="col-lg-7">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="bi bi-sliders"></i> 광고·디스플레이 설정</h4>
        <a href="admin_ads.php" class="btn btn-outline-primary btn-sm" target="_blank"><i class="bi bi-megaphone me-1"></i>광고 관리</a>
    </div>

    <!-- 탭 네비게이션 -->
    <ul class="nav nav-tabs nav-fill mb-0" role="tablist">
    <?php $first = true; foreach ($tabs as $tid => $tab): ?>
        <?php
        // 탭별 ON 개수 카운트
        $on_cnt = 0; $total_cnt = 0;
        foreach ($tab['items'] as $item) {
            if ($item['type'] === 'toggle') {
                $total_cnt++;
                if (($all[$item['key']]['setting_value'] ?? '0') === '1') $on_cnt++;
            }
        }
        ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $first ? 'active' : ''; ?> px-2 py-2"
                    data-bs-toggle="tab" data-bs-target="#tab-<?php echo $tid; ?>"
                    data-preview="<?php echo $tab['preview']; ?>" type="button" role="tab">
                <i class="bi <?php echo $tab['icon']; ?> me-1"></i><span class="d-none d-md-inline"><?php echo $tab['label']; ?></span>
                <?php if ($total_cnt > 0): ?>
                <span class="badge bg-<?php echo $on_cnt > 0 ? 'success' : 'secondary'; ?> tab-badge ms-1"><?php echo $on_cnt; ?>/<?php echo $total_cnt; ?></span>
                <?php endif; ?>
            </button>
        </li>
    <?php $first = false; endforeach; ?>
    </ul>

    <!-- 탭 콘텐츠 -->
    <div class="tab-content border border-top-0 rounded-bottom bg-body p-3">
    <?php $first = true; foreach ($tabs as $tid => $tab): ?>
        <div class="tab-pane fade <?php echo $first ? 'show active' : ''; ?>" id="tab-<?php echo $tid; ?>" role="tabpanel">

            <?php foreach ($tab['items'] as $item):
                $key = $item['key'];
                $val = $all[$key]['setting_value'] ?? '';
                $type = $item['type'];
            ?>
            <div class="setting-row">
                <div class="setting-label">
                    <strong><?php echo $item['label']; ?></strong>
                    <?php if (!empty($item['desc'])): ?>
                    <div class="desc"><?php echo $item['desc']; ?></div>
                    <?php endif; ?>
                </div>
                <div class="setting-ctrl">
                    <?php if ($type === 'toggle'): ?>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input js-setting" type="checkbox" data-key="<?php echo $key; ?>"
                                   id="sw-<?php echo $key; ?>" <?php echo $val === '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="sw-<?php echo $key; ?>">
                                <span class="badge <?php echo $val === '1' ? 'bg-success' : 'bg-secondary'; ?>"><?php echo $val === '1' ? 'ON' : 'OFF'; ?></span>
                            </label>
                        </div>
                    <?php elseif ($type === 'number'): ?>
                        <input type="number" class="form-control form-control-sm js-setting" data-key="<?php echo $key; ?>"
                               value="<?php echo htmlspecialchars($val); ?>" style="width:90px;">
                        <?php if (!empty($item['suffix'])): ?><small class="text-muted"><?php echo $item['suffix']; ?></small><?php endif; ?>
                    <?php elseif ($type === 'color'): ?>
                        <div class="color-group">
                            <input type="color" value="<?php echo htmlspecialchars($val ?: '#000000'); ?>"
                                   onchange="this.nextElementSibling.value=this.value; saveSetting('<?php echo $key; ?>',this.value)"
                                   oninput="this.nextElementSibling.value=this.value">
                            <input type="text" class="form-control form-control-sm js-setting" data-key="<?php echo $key; ?>"
                                   value="<?php echo htmlspecialchars($val); ?>" pattern="#[0-9A-Fa-f]{6}"
                                   onchange="this.previousElementSibling.value=this.value">
                        </div>
                    <?php else: ?>
                        <input type="text" class="form-control form-control-sm js-setting" data-key="<?php echo $key; ?>"
                               value="<?php echo htmlspecialchars($val); ?>" style="width:180px;">
                    <?php endif; ?>

                    <?php if (!empty($item['ad'])): ?>
                    <a href="admin_ads.php" target="_blank" class="btn btn-outline-secondary ad-edit-btn" title="광고 편집"><i class="bi bi-pencil-square"></i></a>
                    <?php endif; ?>

                    <span class="save-status" id="st-<?php echo $key; ?>"></span>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="text-end mt-2">
                <a href="<?php echo $tab['preview']; ?>" target="_blank" class="btn btn-sm btn-link text-muted">
                    <i class="bi bi-box-arrow-up-right me-1"></i>페이지 새 탭으로 보기
                </a>
            </div>
        </div>
    <?php $first = false; endforeach; ?>
    </div>

    <div class="mt-3 text-muted small">
        <i class="bi bi-info-circle me-1"></i>설정 변경 시 자동 저장되며 오른쪽 미리보기에 즉시 반영됩니다.
        &nbsp;|&nbsp; <a href="admin_design_tokens.php">전체 디자인 토큰 관리 →</a>
    </div>
</div>

<!-- RIGHT: 라이브 프리뷰 -->
<div class="col-lg-5">
    <div class="preview-wrap text-center">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
                <select class="form-select form-select-sm" id="deviceSelect" onchange="applyDevice(this.value)" style="width:auto;font-size:12px;">
                    <optgroup label="Phone">
                        <option value="412,915" selected>Galaxy S26 (412×915)</option>
                        <option value="412,892">Galaxy S25 (412×892)</option>
                        <option value="412,883">Galaxy S24 (412×883)</option>
                        <option value="393,852">iPhone 16 Pro (393×852)</option>
                        <option value="430,932">iPhone 16 Pro Max (430×932)</option>
                        <option value="375,812">iPhone XS (375×812)</option>
                        <option value="360,800">Galaxy A13 (360×800)</option>
                        <option value="375,667">iPhone SE (375×667)</option>
                    </optgroup>
                    <optgroup label="PC">
                        <option value="1920,1280">FHD (1920×1280)</option>
                        <option value="1440,900">WXGA+ (1440×900)</option>
                        <option value="1280,800">WXGA (1280×800)</option>
                    </optgroup>
                </select>
            </div>
            <div class="d-flex align-items-center gap-1">
                <input type="number" class="form-control form-control-sm size-input" id="prevW" value="412" onchange="applySize()">
                <span class="text-muted small">×</span>
                <input type="number" class="form-control form-control-sm size-input" id="prevH" value="915" onchange="applySize()">
            </div>
            <div class="d-flex gap-1">
                <button class="btn btn-sm btn-outline-secondary" onclick="reloadPreview()" title="새로고침"><i class="bi bi-arrow-clockwise"></i></button>
                <button class="btn btn-sm btn-outline-primary" onclick="window.open(BASE+currentPage,'_blank')" title="새 탭으로 열기"><i class="bi bi-box-arrow-up-right"></i></button>
            </div>
        </div>
        <div class="text-muted small mb-1" id="preview-url"><?php echo SITE_FULL_URL; ?>index.php</div>
        <iframe id="previewFrame" class="preview-frame" style="width:412px;height:915px;" src="<?php echo SITE_FULL_URL; ?>index.php"></iframe>
    </div>
</div>

</div>
</div>

<script>
var BASE = '<?php echo SITE_FULL_URL; ?>';
var currentPage = 'index.php';

function reloadPreview() {
    document.getElementById('previewFrame').src = BASE + currentPage + '?_t=' + Date.now();
}
function applyDevice(val) {
    var parts = val.split(',');
    document.getElementById('prevW').value = parts[0];
    document.getElementById('prevH').value = parts[1];
    applySize();
}
function applySize() {
    var w = parseInt(document.getElementById('prevW').value) || 412;
    var h = parseInt(document.getElementById('prevH').value) || 915;
    var frame = document.getElementById('previewFrame');
    frame.style.width = w + 'px';
    frame.style.height = h + 'px';
}
function setPreview(page) {
    currentPage = page;
    document.getElementById('preview-url').textContent = BASE + page;
    reloadPreview();
}

// 탭 전환 시 미리보기 자동 변경
document.querySelectorAll('[data-bs-toggle="tab"]').forEach(function(t) {
    t.addEventListener('shown.bs.tab', function() {
        var p = this.getAttribute('data-preview');
        if (p) setPreview(p);
    });
});

// AJAX 즉시 저장
function saveSetting(key, value) {
    var st = document.getElementById('st-' + key);
    if (st) st.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:14px;height:14px;"></span>';

    fetch('api_save_setting.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'key=' + encodeURIComponent(key) + '&value=' + encodeURIComponent(value)
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) {
            if (st) st.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i>';
            reloadPreview();
            setTimeout(function() { if (st) st.innerHTML = ''; }, 2000);
        } else {
            if (st) st.innerHTML = '<i class="bi bi-x-circle-fill text-danger"></i>';
        }
    })
    .catch(function() {
        if (st) st.innerHTML = '<i class="bi bi-x-circle-fill text-danger"></i>';
    });
}

// 이벤트 바인딩
document.querySelectorAll('.js-setting').forEach(function(el) {
    if (el.type === 'checkbox') {
        el.addEventListener('change', function() {
            var val = this.checked ? '1' : '0';
            saveSetting(this.dataset.key, val);
            // 뱃지 업데이트
            var badge = this.nextElementSibling ? this.nextElementSibling.querySelector('.badge') : null;
            if (badge) {
                badge.className = 'badge ' + (this.checked ? 'bg-success' : 'bg-secondary');
                badge.textContent = this.checked ? 'ON' : 'OFF';
            }
        });
    } else {
        var timer;
        el.addEventListener('input', function() {
            var self = this;
            clearTimeout(timer);
            timer = setTimeout(function() { saveSetting(self.dataset.key, self.value); }, 600);
        });
        el.addEventListener('change', function() {
            clearTimeout(timer);
            saveSetting(this.dataset.key, this.value);
        });
    }
});
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
