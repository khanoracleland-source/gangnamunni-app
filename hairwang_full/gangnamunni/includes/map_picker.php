<?php
/**
 * 관리자용 지도 피커 컴포넌트
 * - 지도 클릭/드래그로 좌표 입력
 * - 주소 → 좌표 자동 변환
 * - 장소명 검색
 * - 카카오 지도(한국 주소 정확) 우선, API 키 없으면 Leaflet/OSM 폴백
 *   (프론트 detail.php 등과 동일한 카카오 키 사용 → 관리자에서도 한국 주소를 쉽게 탐색)
 *
 * 사용법:
 *   $picker_config = [
 *       'lat_field'  => 'latitude',     // input name (위도)
 *       'lng_field'  => 'longitude',    // input name (경도)
 *       'addr_field' => 'address',      // 주소 input name (geocoding 소스)
 *       'lat_value'  => $hospital['latitude'] ?? '',
 *       'lng_value'  => $hospital['longitude'] ?? '',
 *   ];
 *   include __DIR__ . '/map_picker.php';
 */

$pk_collapse_id = (string)($picker_config['collapse_id'] ?? '');
$pk_collapse_attr = htmlspecialchars($pk_collapse_id, ENT_QUOTES, 'UTF-8');

$pk_lat_field  = $picker_config['lat_field'] ?? 'latitude';
$pk_lng_field  = $picker_config['lng_field'] ?? 'longitude';
$pk_addr_field = $picker_config['addr_field'] ?? 'address';
$pk_header_class = trim('mp-card-head btn border-0 text-start w-100 ' . trim((string)($picker_config['header_class'] ?? '')));
$pk_card_class = trim('mp-card mb-3 ' . trim((string)($picker_config['wrapper_class'] ?? '')));
$pk_lat_val    = floatval($picker_config['lat_value'] ?? 0);
$pk_lng_val    = floatval($picker_config['lng_value'] ?? 0);
$pk_has_coords = ($pk_lat_val != 0 && $pk_lng_val != 0);
$pk_default_lat = $pk_has_coords ? $pk_lat_val : 37.5665;
$pk_default_lng = $pk_has_coords ? $pk_lng_val : 126.978;

// 지도 제공자 + 카카오 키 로드 (프론트 kakao_map.php 와 동일 소스)
$pk_kakao_key = '';
if (isset($connect)) {
    if (!function_exists('getSettingValue')) {
        require_once __DIR__ . '/settings_helper.php';
    }
    // 카카오 복수 키 로테이션 지원 (kakao_map.php 와 동일)
    $pk_keys_json = getSettingValue($connect, 'kakao_map_api_keys', '');
    if (!empty($pk_keys_json)) {
        $pk_keys = json_decode($pk_keys_json, true);
        if (is_array($pk_keys) && count($pk_keys) > 0) {
            $pk_kakao_key = $pk_keys[array_rand($pk_keys)];
        }
    }
    if (empty($pk_kakao_key)) {
        $pk_kakao_key = getSettingValue($connect, 'kakao_map_api_key', '');
    }
}
$pk_use_kakao = !empty($pk_kakao_key);
?>

<style>
/* ===== 지도 피커 스타일 ===== */
.mp-card {
    border: 1px solid #e9ecef; border-radius: 14px; overflow: hidden;
    background: #fff; box-shadow: 0 1px 3px rgba(16,24,40,.04);
}
.mp-card-head {
    display: flex; align-items: center; gap: 8px;
    padding: 14px 16px; border-bottom: 1px solid #f1f3f5;
    font-weight: 700; font-size: 15px; color: #1f2937;
}
.mp-card-head .bi { color: #AC1830; font-size: 17px; }
.mp-provider-badge {
    margin-left: auto; font-size: 11px; font-weight: 600;
    padding: 3px 10px; border-radius: 999px; letter-spacing: .2px;
}
.mp-provider-badge.kakao { background: #fee500; color: #3c1e1e; }
.mp-provider-badge.osm { background: #eef1f4; color: #667085; }
.mp-card-body { padding: 16px; }

/* 검색바 */
.mp-search { display: flex; gap: 8px; margin-bottom: 10px; }
.mp-search-box {
    flex: 1; display: flex; align-items: center; gap: 8px;
    background: #f7f8fa; border: 1px solid #e9ecef; border-radius: 10px;
    padding: 0 12px; transition: border-color .15s, box-shadow .15s;
}
.mp-search-box:focus-within { border-color: #AC1830; box-shadow: 0 0 0 3px rgba(172, 24, 48,.08); background: #fff; }
.mp-search-box .bi { color: #98a2b3; font-size: 15px; }
.mp-search-box input { flex: 1; border: 0; background: transparent; padding: 11px 0; font-size: 14px; outline: none; }
.mp-btn {
    display: inline-flex; align-items: center; gap: 5px; white-space: nowrap;
    border: 0; border-radius: 10px; padding: 0 16px; font-size: 13px; font-weight: 600;
    cursor: pointer; transition: filter .15s;
}
.mp-btn:hover { filter: brightness(.95); }
.mp-btn-primary { background: #AC1830; color: #fff; }
.mp-btn-ghost { background: #f2f4f7; color: #344054; }
.mp-hint { font-size: 12.5px; color: #98a2b3; display: flex; align-items: center; gap: 5px; margin-bottom: 12px; }

/* 검색 결과 */
.mp-results { border: 1px solid #e9ecef; border-radius: 10px; overflow: hidden; margin-bottom: 12px; max-height: 200px; overflow-y: auto; }
.mp-results .list-group-item-action { cursor: pointer; border: 0; border-bottom: 1px solid #f1f3f5; padding: 10px 14px; transition: background .12s; }
.mp-results .list-group-item-action:last-child { border-bottom: 0; }
.mp-results .list-group-item-action:hover { background: #faf6f7; }

/* 지도 */
.mp-map {
    width: 100%; height: 340px; border-radius: 12px; overflow: hidden;
    border: 1px solid #e9ecef; position: relative; margin-bottom: 14px;
    background: #eef1f4;
}
.mp-map-overlay-hint {
    position: absolute; left: 12px; top: 12px; z-index: 5;
    background: rgba(31,41,55,.82); color: #fff; font-size: 12px; font-weight: 500;
    padding: 6px 11px; border-radius: 8px; display: flex; align-items: center; gap: 6px;
    pointer-events: none; backdrop-filter: blur(2px);
}
/* Leaflet 폴백 시 타일 깨짐 방지 (Bootstrap img{max-width:100%} 충돌) */
#mpPickerMap img.leaflet-tile { max-width: none !important; width: 256px !important; height: 256px !important; }
#mpPickerMap .leaflet-pane { max-width: none; }
/* 카카오 지도 타일도 동일 보호 */
#mpPickerMap img { max-width: none; }

/* 좌표 입력 */
.mp-coords { display: flex; gap: 12px; }
.mp-coord-field { flex: 1; }
.mp-coord-field label { font-size: 12px; font-weight: 600; color: #667085; margin-bottom: 5px; display: block; }
.mp-coord-input-wrap { display: flex; align-items: center; gap: 6px; background: #f7f8fa; border: 1px solid #e9ecef; border-radius: 10px; padding: 0 12px; transition: border-color .15s, box-shadow .15s; }
.mp-coord-input-wrap:focus-within { border-color: #AC1830; box-shadow: 0 0 0 3px rgba(172, 24, 48,.08); background: #fff; }
.mp-coord-input-wrap .bi { color: #98a2b3; font-size: 13px; }
.mp-coord-input-wrap input { flex: 1; border: 0; background: transparent; padding: 10px 0; font-size: 14px; outline: none; font-variant-numeric: tabular-nums; }
@media (max-width: 575px) { .mp-search { flex-wrap: wrap; } .mp-btn { flex: 1; justify-content: center; padding: 10px; } }
</style>

<div class="<?= htmlspecialchars($pk_card_class, ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($pk_collapse_id !== ''): ?>
    <button class="<?= htmlspecialchars($pk_header_class, ENT_QUOTES, 'UTF-8') ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $pk_collapse_attr ?>" aria-controls="<?= $pk_collapse_attr ?>" aria-expanded="false">
    <?php else: ?>
    <div class="mp-card-head">
    <?php endif; ?>
        <i class="bi bi-geo-alt-fill"></i> 위치 정보
        <span class="mp-provider-badge <?php echo $pk_use_kakao ? 'kakao' : 'osm'; ?>">
            <?php echo $pk_use_kakao ? '카카오 지도' : 'OpenStreetMap'; ?>
        </span>
    <?php if ($pk_collapse_id !== ''): ?>
        <i class="bi bi-chevron-down"></i>
    </button>
    <?php else: ?>
    </div>
    <?php endif; ?>

    <?php if ($pk_collapse_id !== ''): ?><div class="collapse" id="<?= $pk_collapse_attr ?>"><?php endif; ?>
    <div class="mp-card-body">
        <!-- 주소 → 좌표 변환 -->
        <div class="mp-search">
            <div class="mp-search-box">
                <i class="bi bi-search"></i>
                <input type="text" id="mpSearchInput" placeholder="주소 또는 장소명으로 검색...">
            </div>
            <button type="button" class="mp-btn mp-btn-primary" onclick="mpSearchAddress()">검색</button>
            <button type="button" class="mp-btn mp-btn-ghost" onclick="mpGeocodeFromAddress()">
                <i class="bi bi-pin-map"></i>주소 변환
            </button>
        </div>
        <div class="mp-hint"><i class="bi bi-hand-index-thumb"></i> 지도를 클릭하거나 마커를 드래그하여 정확한 위치를 지정하세요</div>

        <!-- 검색 결과 -->
        <div id="mpSearchResults" style="display:none;">
            <div class="mp-results" id="mpResultList"></div>
        </div>

        <!-- 지도 -->
        <div class="mp-map">
            <div class="mp-map-overlay-hint"><i class="bi bi-cursor-fill"></i> 클릭 또는 드래그로 위치 지정</div>
            <div id="mpPickerMap" style="width:100%;height:100%;"></div>
        </div>

        <!-- 좌표 입력 -->
        <div class="mp-coords">
            <div class="mp-coord-field">
                <label>위도 (Latitude)</label>
                <div class="mp-coord-input-wrap">
                    <i class="bi bi-arrows-collapse"></i>
                    <input type="number" step="any" name="<?php echo $pk_lat_field; ?>" id="mpLatInput"
                           value="<?php echo $pk_has_coords ? $pk_lat_val : ''; ?>" placeholder="37.498095">
                </div>
            </div>
            <div class="mp-coord-field">
                <label>경도 (Longitude)</label>
                <div class="mp-coord-input-wrap">
                    <i class="bi bi-arrows-expand"></i>
                    <input type="number" step="any" name="<?php echo $pk_lng_field; ?>" id="mpLngInput"
                           value="<?php echo $pk_has_coords ? $pk_lng_val : ''; ?>" placeholder="127.027610">
                </div>
            </div>
        </div>
    </div>
    <?php if ($pk_collapse_id !== ''): ?></div><?php endif; ?>
</div>

<?php if ($pk_use_kakao): ?>
<!-- ===================== 카카오 지도 피커 ===================== -->
<?php if (empty($GLOBALS['_kakao_map_sdk_loaded'])): $GLOBALS['_kakao_map_sdk_loaded'] = true; ?>
<script src="//dapi.kakao.com/v2/maps/sdk.js?appkey=<?php echo htmlspecialchars($pk_kakao_key); ?>&libraries=services&autoload=false"></script>
<?php endif; ?>
<script>
(function() {
    var lat = <?php echo $pk_default_lat; ?>;
    var lng = <?php echo $pk_default_lng; ?>;
    var hasCoords = <?php echo $pk_has_coords ? 'true' : 'false'; ?>;
    var latInput = document.getElementById('mpLatInput');
    var lngInput = document.getElementById('mpLngInput');
    var map, marker, geocoder, places;

    function getAddressInput() {
        return document.querySelector('input[name="<?php echo $pk_addr_field; ?>"], textarea[name="<?php echo $pk_addr_field; ?>"]');
    }

    function setAddressValue(value) {
        var addrInput = getAddressInput();
        if (addrInput && value) addrInput.value = value;
    }

    function updateCoords(y, x) {
        latInput.value = parseFloat(y).toFixed(6);
        lngInput.value = parseFloat(x).toFixed(6);
    }

    function moveMarker(y, x, recenter) {
        var pos = new kakao.maps.LatLng(y, x);
        marker.setPosition(pos);
        if (recenter) map.setCenter(pos);
        updateCoords(y, x);
    }

    function initKakaoPicker() {
        var el = document.getElementById('mpPickerMap');
        map = new kakao.maps.Map(el, {
            center: new kakao.maps.LatLng(lat, lng),
            level: hasCoords ? 3 : 6
        });
        map.addControl(new kakao.maps.ZoomControl(), kakao.maps.ControlPosition.RIGHT);
        geocoder = new kakao.maps.services.Geocoder();
        places = new kakao.maps.services.Places();

        marker = new kakao.maps.Marker({
            map: map,
            position: new kakao.maps.LatLng(lat, lng),
            draggable: true
        });

        // 마커 드래그 → 좌표 업데이트
        kakao.maps.event.addListener(marker, 'dragend', function() {
            var p = marker.getPosition();
            updateCoords(p.getLat(), p.getLng());
        });
        // 지도 클릭 → 마커 이동 + 좌표 업데이트
        kakao.maps.event.addListener(map, 'click', function(e) {
            var p = e.latLng;
            moveMarker(p.getLat(), p.getLng(), false);
        });

        var mapSection = document.getElementById('mpPickerMap').closest('.collapse');
        if (mapSection) mapSection.addEventListener('shown.bs.collapse', function() { var center = map.getCenter(); map.relayout(); map.setCenter(center); });
        setTimeout(function(){ map.relayout(); map.setCenter(new kakao.maps.LatLng(lat, lng)); }, 300);
    }

    // 검색 (장소명 우선 → 실패 시 주소)
    window.mpSearchAddress = function() {
        var q = document.getElementById('mpSearchInput').value.trim();
        if (!q) return;
        var list = document.getElementById('mpResultList');
        var wrap = document.getElementById('mpSearchResults');
        list.innerHTML = '<div class="list-group-item text-muted small">검색 중...</div>';
        wrap.style.display = 'block';

        function renderResults(items) {
            list.innerHTML = '';
            if (!items.length) {
                list.innerHTML = '<div class="list-group-item text-muted small">검색 결과가 없습니다</div>';
                return;
            }
            items.forEach(function(it) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'list-group-item list-group-item-action small py-1';
                btn.innerHTML = '<div class="fw-semibold">' + (it.title || '') + '</div>' +
                                '<div class="text-muted" style="font-size:12px;">' + (it.addr || '') + '</div>';
                btn.onclick = function() {
                    setAddressValue(it.addr || it.title || q);
                    moveMarker(it.y, it.x, true);
                    map.setLevel(3);
                    wrap.style.display = 'none';
                };
                list.appendChild(btn);
            });
        }

        // 1) 장소(키워드) 검색
        places.keywordSearch(q, function(data, status) {
            if (status === kakao.maps.services.Status.OK && data.length) {
                renderResults(data.map(function(d){
                    return { title: d.place_name, addr: d.road_address_name || d.address_name, y: d.y, x: d.x };
                }));
            } else {
                // 2) 주소 검색 폴백
                geocoder.addressSearch(q, function(res, st) {
                    if (st === kakao.maps.services.Status.OK && res.length) {
                        renderResults(res.map(function(r){
                            return { title: r.address_name, addr: r.road_address ? r.road_address.address_name : '', y: r.y, x: r.x };
                        }));
                    } else {
                        renderResults([]);
                    }
                });
            }
        });
    };

    // Enter 키 검색
    document.getElementById('mpSearchInput').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); mpSearchAddress(); }
    });

    // 주소 필드에서 좌표 변환
    window.mpGeocodeFromAddress = function(silent) {
        var addrInput = getAddressInput();
        if (!addrInput || !addrInput.value.trim()) {
            if (!silent) alert('주소 필드에 값을 먼저 입력하세요.');
            return;
        }
        var q = addrInput.value.trim();
        document.getElementById('mpSearchInput').value = q;
        geocoder.addressSearch(q, function(res, st) {
            if (st === kakao.maps.services.Status.OK && res.length) {
                setAddressValue(res[0].road_address ? res[0].road_address.address_name : res[0].address_name);
                moveMarker(res[0].y, res[0].x, true);
                map.setLevel(3);
            } else {
                // 주소가 도로명/지번으로 안 잡히면 키워드 검색으로 재시도
                places.keywordSearch(q, function(data, s2) {
                    if (s2 === kakao.maps.services.Status.OK && data.length) {
                        setAddressValue(data[0].road_address_name || data[0].address_name || q);
                        moveMarker(data[0].y, data[0].x, true);
                        map.setLevel(3);
                    } else {
                        if (!silent) alert('주소를 좌표로 변환할 수 없습니다. 검색창에서 병원명으로 검색하거나 지도를 직접 클릭하세요.');
                    }
                });
            }
        });
    };

    function bindAddressSync() {
        var addrInput = getAddressInput();
        var searchInput = document.getElementById('mpSearchInput');
        if (!addrInput) return;
        if (searchInput && addrInput.value.trim() && !searchInput.value.trim()) {
            searchInput.value = addrInput.value.trim();
        }
        addrInput.addEventListener('change', function() {
            if (!addrInput.value.trim()) return;
            if (searchInput) searchInput.value = addrInput.value.trim();
            window.mpGeocodeFromAddress(true);
        });
        if (!hasCoords && addrInput.value.trim()) {
            setTimeout(function(){ window.mpGeocodeFromAddress(true); }, 300);
        }
    }

    // 카카오 SDK 로드 실패(도메인 미등록/네트워크) 시 Leaflet/OSM 로 폴백
    function mpLeafletFallback() {
        var lmap, lmarker;
        function ready() {
            lmap = L.map('mpPickerMap').setView([lat, lng], hasCoords ? 16 : 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap', maxZoom: 19
            }).addTo(lmap);
            lmarker = L.marker([lat, lng], { draggable: true }).addTo(lmap);
            lmarker.on('dragend', function() { var p = lmarker.getLatLng(); updateCoords(p.lat, p.lng); });
            lmap.on('click', function(e) { lmarker.setLatLng(e.latlng); updateCoords(e.latlng.lat, e.latlng.lng); });
            var badge = document.querySelector('.card-header .badge');
            if (badge) { badge.textContent = 'OSM (카카오 로드 실패)'; badge.className = 'badge bg-secondary ms-2'; }
            var mapSection = document.getElementById('mpPickerMap').closest('.collapse');
            if (mapSection) mapSection.addEventListener('shown.bs.collapse', function() { lmap.invalidateSize(); });
            setTimeout(function(){ lmap.invalidateSize(); }, 300);
        }
        function getAddressInput() {
            return document.querySelector('input[name="<?php echo $pk_addr_field; ?>"], textarea[name="<?php echo $pk_addr_field; ?>"]');
        }
        function setAddressValue(value) {
            var addrInput = getAddressInput();
            if (addrInput && value) addrInput.value = value;
        }
        function moveMap(y, x) { var ll = L.latLng(y, x); lmarker.setLatLng(ll); lmap.setView(ll, 16); updateCoords(y, x); }
        window.mpSearchAddress = function() {
            var q = document.getElementById('mpSearchInput').value.trim(); if (!q) return;
            var list = document.getElementById('mpResultList'), wrap = document.getElementById('mpSearchResults');
            list.innerHTML = '<div class="list-group-item text-muted small">검색 중...</div>'; wrap.style.display = 'block';
            fetch('https://nominatim.openstreetmap.org/search?q=' + encodeURIComponent(q) + '&format=json&limit=5&countrycodes=kr&accept-language=ko')
            .then(function(r){ return r.json(); }).then(function(data){
                list.innerHTML = '';
                if (!data.length) { list.innerHTML = '<div class="list-group-item text-muted small">검색 결과가 없습니다</div>'; return; }
                data.forEach(function(item){
                    var btn = document.createElement('button'); btn.type = 'button';
                    btn.className = 'list-group-item list-group-item-action small py-1'; btn.textContent = item.display_name;
                    btn.onclick = function(){ setAddressValue(item.display_name); moveMap(parseFloat(item.lat), parseFloat(item.lon)); wrap.style.display = 'none'; };
                    list.appendChild(btn);
                });
            });
        };
        window.mpGeocodeFromAddress = function(silent) {
            var addrInput = getAddressInput();
            if (!addrInput || !addrInput.value.trim()) { if (!silent) alert('주소 필드에 값을 먼저 입력하세요.'); return; }
            var q = addrInput.value.trim(); document.getElementById('mpSearchInput').value = q;
            fetch('https://nominatim.openstreetmap.org/search?q=' + encodeURIComponent(q) + '&format=json&limit=1&countrycodes=kr')
            .then(function(r){ return r.json(); }).then(function(data){
                if (data.length > 0) { setAddressValue(data[0].display_name); moveMap(parseFloat(data[0].lat), parseFloat(data[0].lon)); }
                else if (!silent) alert('주소를 좌표로 변환할 수 없습니다. 지도에서 직접 위치를 클릭하세요.');
            });
        };
        function bindFallbackAddressSync() {
            var addrInput = getAddressInput();
            var searchInput = document.getElementById('mpSearchInput');
            if (!addrInput) return;
            if (searchInput && addrInput.value.trim() && !searchInput.value.trim()) searchInput.value = addrInput.value.trim();
            addrInput.addEventListener('change', function() {
                if (!addrInput.value.trim()) return;
                if (searchInput) searchInput.value = addrInput.value.trim();
                window.mpGeocodeFromAddress(true);
            });
            if (!hasCoords && addrInput.value.trim()) setTimeout(function(){ window.mpGeocodeFromAddress(true); }, 300);
        }
        if (window.L) { ready(); bindFallbackAddressSync(); return; }
        var css = document.createElement('link'); css.rel = 'stylesheet'; css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'; document.head.appendChild(css);
        var js = document.createElement('script'); js.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'; js.onload = function(){ ready(); bindFallbackAddressSync(); }; document.head.appendChild(js);
    }

    // kakao 전역이 정의됐는지 확인 후 로드, 아니면 폴백
    var mpKakaoTries = 0;
    (function waitKakao() {
        if (typeof kakao !== 'undefined' && kakao.maps) { kakao.maps.load(function(){ initKakaoPicker(); bindAddressSync(); }); return; }
        if (++mpKakaoTries > 20) { mpLeafletFallback(); return; } // ~2초 대기 후 폴백
        setTimeout(waitKakao, 100);
    })();
})();
</script>

<?php else: ?>
<!-- ===================== Leaflet / OpenStreetMap 폴백 ===================== -->
<?php if (empty($GLOBALS['_leaflet_loaded'])): $GLOBALS['_leaflet_loaded'] = true; ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<?php endif; ?>
<script>
(function() {
    var lat = <?php echo $pk_default_lat; ?>;
    var lng = <?php echo $pk_default_lng; ?>;
    var zoom = <?php echo $pk_has_coords ? 16 : 12; ?>;

    var map = L.map('mpPickerMap').setView([lat, lng], zoom);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap', maxZoom: 19
    }).addTo(map);

    var marker = L.marker([lat, lng], { draggable: true }).addTo(map);
    <?php if ($pk_has_coords): ?>
    marker.bindPopup('현재 위치').openPopup();
    <?php endif; ?>

    marker.on('dragend', function() {
        var pos = marker.getLatLng();
        updateCoords(pos.lat, pos.lng);
    });
    map.on('click', function(e) {
        marker.setLatLng(e.latlng);
        updateCoords(e.latlng.lat, e.latlng.lng);
    });

    function updateCoords(lat, lng) {
        document.getElementById('mpLatInput').value = lat.toFixed(6);
        document.getElementById('mpLngInput').value = lng.toFixed(6);
    }

    function getAddressInput() {
        return document.querySelector('input[name="<?php echo $pk_addr_field; ?>"], textarea[name="<?php echo $pk_addr_field; ?>"]');
    }

    function setAddressValue(value) {
        var addrInput = getAddressInput();
        if (addrInput && value) addrInput.value = value;
    }

    function moveMap(lat, lng) {
        var latlng = L.latLng(lat, lng);
        marker.setLatLng(latlng);
        map.setView(latlng, 16);
        updateCoords(lat, lng);
    }

    window.mpSearchAddress = function() {
        var q = document.getElementById('mpSearchInput').value.trim();
        if (!q) return;
        fetch('https://nominatim.openstreetmap.org/search?q=' + encodeURIComponent(q) + '&format=json&limit=5&countrycodes=kr&accept-language=ko')
        .then(function(r){ return r.json(); })
        .then(function(data){
            var list = document.getElementById('mpResultList');
            var wrap = document.getElementById('mpSearchResults');
            list.innerHTML = '';
            if (data.length === 0) {
                list.innerHTML = '<div class="list-group-item text-muted small">검색 결과가 없습니다</div>';
            } else {
                data.forEach(function(item) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'list-group-item list-group-item-action small py-1';
                    btn.textContent = item.display_name;
                    btn.onclick = function() {
                        setAddressValue(item.display_name);
                        moveMap(parseFloat(item.lat), parseFloat(item.lon));
                        wrap.style.display = 'none';
                    };
                    list.appendChild(btn);
                });
            }
            wrap.style.display = 'block';
        });
    };

    document.getElementById('mpSearchInput').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); mpSearchAddress(); }
    });

    window.mpGeocodeFromAddress = function(silent) {
        var addrInput = getAddressInput();
        if (!addrInput || !addrInput.value.trim()) {
            if (!silent) alert('주소 필드에 값을 먼저 입력하세요.');
            return;
        }
        var q = addrInput.value.trim();
        document.getElementById('mpSearchInput').value = q;
        fetch('https://nominatim.openstreetmap.org/search?q=' + encodeURIComponent(q) + '&format=json&limit=1&countrycodes=kr')
        .then(function(r){ return r.json(); })
        .then(function(data){
            if (data.length > 0) {
                setAddressValue(data[0].display_name);
                moveMap(parseFloat(data[0].lat), parseFloat(data[0].lon));
                marker.bindPopup('주소에서 변환됨').openPopup();
            } else {
                if (!silent) alert('주소를 좌표로 변환할 수 없습니다. 지도에서 직접 위치를 클릭하세요.');
            }
        });
    };

    function bindAddressSync() {
        var addrInput = getAddressInput();
        var searchInput = document.getElementById('mpSearchInput');
        if (!addrInput) return;
        if (searchInput && addrInput.value.trim() && !searchInput.value.trim()) searchInput.value = addrInput.value.trim();
        addrInput.addEventListener('change', function() {
            if (!addrInput.value.trim()) return;
            if (searchInput) searchInput.value = addrInput.value.trim();
            window.mpGeocodeFromAddress(true);
        });
        if (!<?php echo $pk_has_coords ? 'true' : 'false'; ?> && addrInput.value.trim()) {
            setTimeout(function(){ window.mpGeocodeFromAddress(true); }, 300);
        }
    }

    bindAddressSync();
    var mapSection = document.getElementById('mpPickerMap').closest('.collapse');
    if (mapSection) mapSection.addEventListener('shown.bs.collapse', function() { map.invalidateSize(); });
    setTimeout(function(){ map.invalidateSize(); }, 500);
})();
</script>
<?php endif; ?>
