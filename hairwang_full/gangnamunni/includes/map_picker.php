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

$pk_lat_field  = $picker_config['lat_field'] ?? 'latitude';
$pk_lng_field  = $picker_config['lng_field'] ?? 'longitude';
$pk_addr_field = $picker_config['addr_field'] ?? 'address';
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

<div class="card mb-3">
    <div class="card-header"><i class="bi bi-geo-alt me-1"></i> 위치 정보
        <span class="badge <?php echo $pk_use_kakao ? 'bg-warning text-dark' : 'bg-secondary'; ?> ms-2" style="font-size:11px;">
            <?php echo $pk_use_kakao ? '카카오 지도' : 'OSM'; ?>
        </span>
    </div>
    <div class="card-body">
        <!-- 주소 → 좌표 변환 -->
        <div class="mb-3">
            <div class="input-group input-group-sm">
                <input type="text" class="form-control" id="mpSearchInput" placeholder="주소 또는 장소명으로 검색...">
                <button type="button" class="btn btn-outline-primary" onclick="mpSearchAddress()">
                    <i class="bi bi-search me-1"></i>검색
                </button>
                <button type="button" class="btn btn-outline-success" onclick="mpGeocodeFromAddress()">
                    <i class="bi bi-pin-map me-1"></i>주소 필드에서 변환
                </button>
            </div>
            <div class="form-text">지도를 클릭하거나 마커를 드래그하여 정확한 위치를 지정하세요</div>
        </div>

        <!-- 검색 결과 -->
        <div id="mpSearchResults" style="display:none;" class="mb-2">
            <div class="list-group list-group-flush" id="mpResultList" style="max-height:180px;overflow-y:auto;"></div>
        </div>

        <!-- 지도 -->
        <style>
        /* Leaflet 폴백 시 타일 깨짐 방지 (Bootstrap img{max-width:100%} 충돌) */
        #mpPickerMap img.leaflet-tile { max-width: none !important; width: 256px !important; height: 256px !important; }
        #mpPickerMap .leaflet-pane { max-width: none; }
        /* 카카오 지도 타일도 동일 보호 */
        #mpPickerMap img { max-width: none; }
        </style>
        <div id="mpPickerMap" style="width:100%;height:320px;border-radius:8px;border:1px solid #dee2e6;margin-bottom:12px;"></div>

        <!-- 좌표 입력 -->
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold small">위도 (Latitude)</label>
                <input type="number" step="any" name="<?php echo $pk_lat_field; ?>" id="mpLatInput"
                       class="form-control form-control-sm"
                       value="<?php echo $pk_has_coords ? $pk_lat_val : ''; ?>"
                       placeholder="37.498095">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold small">경도 (Longitude)</label>
                <input type="number" step="any" name="<?php echo $pk_lng_field; ?>" id="mpLngInput"
                       class="form-control form-control-sm"
                       value="<?php echo $pk_has_coords ? $pk_lng_val : ''; ?>"
                       placeholder="127.027610">
            </div>
        </div>
    </div>
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
    window.mpGeocodeFromAddress = function() {
        var addrInput = document.querySelector('input[name="<?php echo $pk_addr_field; ?>"], textarea[name="<?php echo $pk_addr_field; ?>"]');
        if (!addrInput || !addrInput.value.trim()) {
            alert('주소 필드에 값을 먼저 입력하세요.');
            return;
        }
        var q = addrInput.value.trim();
        document.getElementById('mpSearchInput').value = q;
        geocoder.addressSearch(q, function(res, st) {
            if (st === kakao.maps.services.Status.OK && res.length) {
                moveMarker(res[0].y, res[0].x, true);
                map.setLevel(3);
            } else {
                // 주소가 도로명/지번으로 안 잡히면 키워드 검색으로 재시도
                places.keywordSearch(q, function(data, s2) {
                    if (s2 === kakao.maps.services.Status.OK && data.length) {
                        moveMarker(data[0].y, data[0].x, true);
                        map.setLevel(3);
                    } else {
                        alert('주소를 좌표로 변환할 수 없습니다. 검색창에서 병원명으로 검색하거나 지도를 직접 클릭하세요.');
                    }
                });
            }
        });
    };

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
            setTimeout(function(){ lmap.invalidateSize(); }, 300);
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
                    btn.onclick = function(){ moveMap(parseFloat(item.lat), parseFloat(item.lon)); wrap.style.display = 'none'; };
                    list.appendChild(btn);
                });
            });
        };
        window.mpGeocodeFromAddress = function() {
            var addrInput = document.querySelector('input[name="<?php echo $pk_addr_field; ?>"], textarea[name="<?php echo $pk_addr_field; ?>"]');
            if (!addrInput || !addrInput.value.trim()) { alert('주소 필드에 값을 먼저 입력하세요.'); return; }
            var q = addrInput.value.trim(); document.getElementById('mpSearchInput').value = q;
            fetch('https://nominatim.openstreetmap.org/search?q=' + encodeURIComponent(q) + '&format=json&limit=1&countrycodes=kr')
            .then(function(r){ return r.json(); }).then(function(data){
                if (data.length > 0) moveMap(parseFloat(data[0].lat), parseFloat(data[0].lon));
                else alert('주소를 좌표로 변환할 수 없습니다. 지도에서 직접 위치를 클릭하세요.');
            });
        };
        if (window.L) { ready(); return; }
        var css = document.createElement('link'); css.rel = 'stylesheet'; css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'; document.head.appendChild(css);
        var js = document.createElement('script'); js.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'; js.onload = ready; document.head.appendChild(js);
    }

    // kakao 전역이 정의됐는지 확인 후 로드, 아니면 폴백
    var mpKakaoTries = 0;
    (function waitKakao() {
        if (typeof kakao !== 'undefined' && kakao.maps) { kakao.maps.load(initKakaoPicker); return; }
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

    window.mpGeocodeFromAddress = function() {
        var addrInput = document.querySelector('input[name="<?php echo $pk_addr_field; ?>"], textarea[name="<?php echo $pk_addr_field; ?>"]');
        if (!addrInput || !addrInput.value.trim()) {
            alert('주소 필드에 값을 먼저 입력하세요.');
            return;
        }
        var q = addrInput.value.trim();
        document.getElementById('mpSearchInput').value = q;
        fetch('https://nominatim.openstreetmap.org/search?q=' + encodeURIComponent(q) + '&format=json&limit=1&countrycodes=kr')
        .then(function(r){ return r.json(); })
        .then(function(data){
            if (data.length > 0) {
                moveMap(parseFloat(data[0].lat), parseFloat(data[0].lon));
                marker.bindPopup('주소에서 변환됨').openPopup();
            } else {
                alert('주소를 좌표로 변환할 수 없습니다. 지도에서 직접 위치를 클릭하세요.');
            }
        });
    };

    setTimeout(function(){ map.invalidateSize(); }, 500);
})();
</script>
<?php endif; ?>
