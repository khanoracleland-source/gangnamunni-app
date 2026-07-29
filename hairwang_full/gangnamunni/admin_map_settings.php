<?php
/**
 * 지도 API 키 관리
 * - 카카오 지도: 최대 10개 키 등록, 랜덤 로테이션
 * - 구글 지도: 1개 키 (무료 $200/월 크레딧)
 * - OpenStreetMap: 키 불필요 (무료)
 * - 지도 제공자 선택: kakao / google / osm
 */
session_start();
$admin_page_title = '지도 API 키 관리';
$admin_current = 'map_settings';

// [수정 2026-06-19] Gnuboard init.php(→common.php) 제거 — 세션 활성 상태에서 ini_set/
//   session_set_cookie_params 호출로 "headers already sent" → HTTP 500 발생하던 것을 정정.
//   다른 관리자 페이지와 동일한 표준 헤더 패턴(dbconfig+theme_helper+admin_auth)으로 통일.
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_admin_login();
require_permission('settings');

if (!function_exists('getSettingValue')) {
    require_once __DIR__ . '/includes/settings_helper.php';
}

// DB 연결을 설정 로드/POST 처리보다 먼저 생성 (getSettingValue/saveMapSetting이 $connect 사용)
if (!isset($connect) || !$connect) {
    $connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    if ($connect) mysqli_set_charset($connect, 'utf8mb4');
}

$message = '';
$msg_type = 'success';

// 설정 저장 함수
function saveMapSetting($connect, $key, $value) {
    $stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    mysqli_stmt_bind_param($stmt, 'sss', $key, $value, $value);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// POST 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_all') {
        // 1) 지도 제공자 저장
        $provider = $_POST['map_provider'] ?? 'kakao';
        if (!in_array($provider, ['kakao', 'google', 'osm'])) $provider = 'kakao';
        saveMapSetting($connect, 'map_provider', $provider);

        // 2) 카카오 키 저장
        $keys = [];
        for ($i = 0; $i < 10; $i++) {
            $key = trim($_POST["kakao_key_$i"] ?? '');
            if (!empty($key) && preg_match('/^[a-f0-9]{32}$/i', $key)) {
                $keys[] = $key;
            } elseif (!empty($key)) {
                $message = "카카오 키 #" . ($i + 1) . " 형식이 올바르지 않습니다 (32자리 hex).";
                $msg_type = 'danger';
            }
        }
        if (empty($message)) {
            $keys = array_unique($keys);
            $keys_json = json_encode(array_values($keys));
            saveMapSetting($connect, 'kakao_map_api_keys', $keys_json);
            $single_key = count($keys) > 0 ? $keys[0] : '';
            saveMapSetting($connect, 'kakao_map_api_key', $single_key);
        }

        // 3) 구글 키 저장
        $google_key = trim($_POST['google_maps_key'] ?? '');
        saveMapSetting($connect, 'google_maps_api_key', $google_key);

        if (empty($message)) {
            $message = "지도 설정이 저장되었습니다. (제공자: " . strtoupper($provider) . ", 카카오 " . count($keys) . "키" . (!empty($google_key) ? ", 구글 1키" : "") . ")";
            $msg_type = 'success';
        }
    }
}

// 현재 설정 로드
$map_provider = getSettingValue($connect, 'map_provider', 'kakao');
$keys_json = getSettingValue($connect, 'kakao_map_api_keys', '[]');
$kakao_keys = json_decode($keys_json, true);
if (!is_array($kakao_keys)) $kakao_keys = [];
$google_key = getSettingValue($connect, 'google_maps_api_key', '');

if (isset($connect) && $connect) loadThemeSettings($connect);
require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-8">

            <div class="d-flex align-items-center mb-4">
                <h4 class="mb-0">🗺 지도 API 설정</h4>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <form method="post">
            <input type="hidden" name="action" value="save_all">

            <!-- 지도 제공자 선택 -->
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0">지도 제공자 선택</h6></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-check border rounded p-3 h-100 <?php echo $map_provider === 'kakao' ? 'border-primary bg-primary bg-opacity-10' : ''; ?>">
                                <input class="form-check-input" type="radio" name="map_provider" value="kakao" id="provKakao" <?php echo $map_provider === 'kakao' ? 'checked' : ''; ?> onchange="toggleProviders()">
                                <label class="form-check-label" for="provKakao">
                                    <strong>🟡 카카오 지도</strong><br>
                                    <small class="text-muted">한국 최적화, 한국어 지명, 정확한 도로명<br>
                                    무료 300,000회/일, API 키 필요</small>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check border rounded p-3 h-100 <?php echo $map_provider === 'google' ? 'border-primary bg-primary bg-opacity-10' : ''; ?>">
                                <input class="form-check-input" type="radio" name="map_provider" value="google" id="provGoogle" <?php echo $map_provider === 'google' ? 'checked' : ''; ?> onchange="toggleProviders()">
                                <label class="form-check-label" for="provGoogle">
                                    <strong>🔵 구글 지도</strong><br>
                                    <small class="text-muted">글로벌 지원, 다국어, 스트리트뷰<br>
                                    무료 $200/월 크레딧, API 키 필요</small>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check border rounded p-3 h-100 <?php echo $map_provider === 'osm' ? 'border-primary bg-primary bg-opacity-10' : ''; ?>">
                                <input class="form-check-input" type="radio" name="map_provider" value="osm" id="provOsm" <?php echo $map_provider === 'osm' ? 'checked' : ''; ?> onchange="toggleProviders()">
                                <label class="form-check-label" for="provOsm">
                                    <strong>🟢 OpenStreetMap</strong><br>
                                    <small class="text-muted">완전 무료, API 키 불필요<br>
                                    Leaflet.js, 오픈소스</small>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
            <!-- 카카오 키 -->
            <div class="col-lg-6">
            <div class="card mb-3" id="kakaoSection">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h6 class="mb-0">🟡 카카오 지도 API 키 (최대 10개)</h6>
                    <span class="badge bg-warning text-dark"><?php echo count($kakao_keys); ?>개 활성</span>
                </div>
                <div class="card-body">
                    <div class="alert alert-light border mb-3" style="font-size:12px;">
                        <a href="https://developers.kakao.com" target="_blank">Kakao Developers</a> →
                        애플리케이션 → 앱 키 → <strong>JavaScript 키</strong><br>
                        플랫폼 → 웹 → <code>https://hms.seoulbeauty.app</code> 등록 필수
                    </div>
                    <?php for ($i = 0; $i < 10; $i++): ?>
                    <?php $val = $kakao_keys[$i] ?? ''; ?>
                    <div class="input-group input-group-sm mb-1">
                        <span class="input-group-text" style="width:30px;justify-content:center;"><?php echo $i + 1; ?></span>
                        <input type="text" name="kakao_key_<?php echo $i; ?>" class="form-control font-monospace"
                               value="<?php echo htmlspecialchars($val); ?>"
                               placeholder="32자리 JavaScript 키" maxlength="32" id="kkey_<?php echo $i; ?>">
                        <span class="input-group-text" id="kst_<?php echo $i; ?>" style="width:50px;justify-content:center;font-size:11px;">
                            <?php echo !empty($val) ? '✅' : '—'; ?>
                        </span>
                    </div>
                    <?php endfor; ?>
                    <button type="button" class="btn btn-outline-warning btn-sm mt-2 w-100" onclick="testKakaoKeys()">🔍 카카오 키 유효성 검사</button>
                </div>
            </div>
            </div>

            <!-- 구글 키 -->
            <div class="col-lg-6">
            <div class="card mb-3" id="googleSection">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h6 class="mb-0">🔵 구글 지도 API 키</h6>
                    <span class="badge <?php echo !empty($google_key) ? 'bg-primary' : 'bg-secondary'; ?>">
                        <?php echo !empty($google_key) ? '활성' : '미등록'; ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="alert alert-light border mb-3" style="font-size:12px;">
                        <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a> →
                        API 및 서비스 → 사용자 인증 정보<br>
                        <strong>Maps JavaScript API</strong> + <strong>Geocoding API</strong> 활성화 필수<br>
                        HTTP 리퍼러 제한: <code>https://hms.seoulbeauty.app/*</code><br>
                        월 $200 무료 크레딧 (약 28,000 로드)
                    </div>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">API Key</span>
                        <input type="text" name="google_maps_key" class="form-control font-monospace"
                               value="<?php echo htmlspecialchars($google_key); ?>"
                               placeholder="AIzaSy..." id="gkey">
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm mt-2 w-100" onclick="testGoogleKey()">🔍 구글 키 유효성 검사</button>

                    <hr>
                    <div class="card-header p-0 border-0 mt-2"><h6 class="mb-2">🟢 OpenStreetMap (Leaflet.js)</h6></div>
                    <div class="alert alert-success mb-0" style="font-size:12px;">
                        <strong>API 키 불필요</strong> — 완전 무료 오픈소스 지도<br>
                        별도 설정 없이 즉시 사용 가능. 한국어 지명 지원 (제한적).<br>
                        카카오/구글 키 없을 때 자동 폴백으로도 동작합니다.
                    </div>
                </div>
            </div>
            </div>
            </div>

            <!-- 저장 버튼 -->
            <div class="d-flex justify-content-end mb-3">
                <button type="submit" class="btn btn-primary">💾 전체 설정 저장</button>
            </div>
            </form>

            <!-- 지도 미리보기 -->
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0">📍 지도 미리보기 (서울미녀 강남점)</h6></div>
                <div class="card-body">
                    <div id="previewMap" style="width:100%;height:300px;border-radius:8px;border:1px solid #ddd;"></div>
                    <div class="mt-2 d-flex justify-content-between align-items-center">
                        <small class="text-muted">현재 제공자: <strong><?php echo strtoupper($map_provider); ?></strong></small>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="loadPreview()">🔄 새로고침</button>
                    </div>
                </div>
            </div>

            <!-- 지도 사용 현황 -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">📋 지도 사용 페이지 현황</h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>페이지</th><th>파일</th><th>지도 유형</th><th>상태</th></tr></thead>
                        <tbody>
                            <tr><td>병원 상세</td><td><code>detail.php</code></td><td>kakao_map.php (제공자 자동 선택)</td><td><span class="badge bg-success">적용</span></td></tr>
                            <tr><td>지도 탐색</td><td><code>map.php</code></td><td>카카오/구글 (클러스터 + 다중 마커)</td><td><span class="badge bg-success">적용</span></td></tr>
                            <tr><td>예약 페이지</td><td><code>booking.php</code></td><td>kakao_map.php (제공자 자동 선택)</td><td><span class="badge bg-success">적용</span></td></tr>
                            <tr><td>의사 상세</td><td><code>doctor_detail.php</code></td><td>kakao_map.php (제공자 자동 선택)</td><td><span class="badge bg-success">적용</span></td></tr>
                            <tr><td>지도 선택기</td><td><code>includes/map_picker.php</code></td><td>카카오 지도 우선 (키 없으면 OSM 폴백)</td><td><span class="badge bg-success">적용</span></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
function toggleProviders() {
    const prov = document.querySelector('input[name="map_provider"]:checked')?.value;
    document.getElementById('kakaoSection').style.opacity = (prov === 'kakao') ? '1' : '0.5';
    document.getElementById('googleSection').style.opacity = (prov === 'google') ? '1' : '0.5';
}
toggleProviders();

// 카카오 키 유효성 검사
async function testKakaoKeys() {
    for (let i = 0; i < 10; i++) {
        const input = document.getElementById('kkey_' + i);
        const st = document.getElementById('kst_' + i);
        const key = input.value.trim();
        if (!key) { st.textContent = '—'; continue; }
        if (!/^[a-f0-9]{32}$/i.test(key)) { st.textContent = '❌'; st.title = '형식오류'; continue; }

        st.textContent = '⏳';
        try {
            const r = await fetch('https://dapi.kakao.com/v2/maps/sdk.js?appkey=' + key);
            if (r.ok) { st.textContent = '✅'; st.title = '유효'; }
            else { st.textContent = '❌'; st.title = 'HTTP ' + r.status; }
        } catch(e) {
            st.textContent = '⚠️'; st.title = 'CORS (키 존재 가능)';
        }
    }
}

// 구글 키 유효성 검사
async function testGoogleKey() {
    const key = document.getElementById('gkey').value.trim();
    if (!key) { alert('구글 API 키를 입력하세요.'); return; }
    // 간단한 Geocoding API 호출로 검증
    try {
        const r = await fetch('https://maps.googleapis.com/maps/api/geocode/json?address=Seoul&key=' + key);
        const d = await r.json();
        if (d.status === 'OK') alert('✅ 구글 API 키 유효! (Geocoding OK)');
        else if (d.status === 'REQUEST_DENIED') alert('❌ 키가 유효하지 않거나 Geocoding API가 활성화되지 않았습니다.\n' + (d.error_message || ''));
        else alert('⚠️ 응답: ' + d.status);
    } catch(e) {
        alert('❌ 검사 실패: ' + e.message);
    }
}

// 미리보기 — 서버에서 현재 제공자/키를 PHP로 전달
var _previewProvider = '<?php echo $map_provider; ?>';
var _previewKakaoKey = '<?php echo htmlspecialchars($kakao_keys[0] ?? ''); ?>';
var _previewGoogleKey = '<?php echo htmlspecialchars($google_key); ?>';

function loadPreview() {
    const el = document.getElementById('previewMap');
    // 폼에서 선택된 제공자 우선, 없으면 서버 설정
    const prov = document.querySelector('input[name="map_provider"]:checked')?.value || _previewProvider;
    const kakaoKey = document.getElementById('kkey_0')?.value.trim() || _previewKakaoKey;
    const googleKey = document.getElementById('gkey')?.value.trim() || _previewGoogleKey;

    el.innerHTML = '';

    if (prov === 'google' && googleKey) {
        if (window._gmapLoaded) { initGooglePreview(); return; }
        window.initGooglePreview = function() {
            window._gmapLoaded = true;
            var map = new google.maps.Map(el, { center: {lat:37.4979, lng:127.0276}, zoom:15 });
            new google.maps.Marker({ position: {lat:37.4979, lng:127.0276}, map: map });
        };
        var s = document.createElement('script');
        s.src = 'https://maps.googleapis.com/maps/api/js?key=' + googleKey + '&callback=initGooglePreview';
        document.head.appendChild(s);
    } else if (prov === 'kakao' && kakaoKey) {
        if (window._kakaoLoaded) { initKakaoPreview(); return; }
        var s = document.createElement('script');
        s.src = '//dapi.kakao.com/v2/maps/sdk.js?appkey=' + kakaoKey + '&autoload=false';
        s.onload = function() { window._kakaoLoaded = true; initKakaoPreview(); };
        s.onerror = function() { el.innerHTML = '<div class="text-center text-danger p-4">카카오 SDK 로드 실패. 키를 확인하세요.</div>'; };
        document.head.appendChild(s);
    } else {
        loadOsmPreview(el);
    }
}

function initKakaoPreview() {
    var el = document.getElementById('previewMap');
    el.innerHTML = '';
    kakao.maps.load(function() {
        var map = new kakao.maps.Map(el, { center: new kakao.maps.LatLng(37.4979, 127.0276), level: 3 });
        new kakao.maps.Marker({ map: map, position: new kakao.maps.LatLng(37.4979, 127.0276) });
    });
}

function loadOsmPreview(el) {
    if (!window.L) {
        var link = document.createElement('link'); link.rel = 'stylesheet'; link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'; document.head.appendChild(link);
        var s = document.createElement('script'); s.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        s.onload = function() { showOsmMap(el); }; document.head.appendChild(s);
    } else { showOsmMap(el); }
}

function showOsmMap(el) {
    var map = L.map(el).setView([37.4979, 127.0276], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap', maxZoom: 19 }).addTo(map);
    L.marker([37.4979, 127.0276]).addTo(map);
}

// 페이지 로드 시 자동으로 미리보기 표시
document.addEventListener('DOMContentLoaded', function() { loadPreview(); });
</script>

<?php
mysqli_close($connect);
require_once __DIR__ . '/includes/admin_footer.php';
?>
