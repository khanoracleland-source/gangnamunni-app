<?php
/**
 * 관리자용 지도 피커 컴포넌트
 * - 지도 클릭/드래그로 좌표 입력
 * - 주소 → 좌표 자동 변환 (Nominatim geocoding)
 * - 장소명 검색
 * - Leaflet.js + OpenStreetMap (API 키 불필요)
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
?>

<?php if (empty($GLOBALS['_leaflet_loaded'])): $GLOBALS['_leaflet_loaded'] = true; ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-header"><i class="bi bi-geo-alt me-1"></i> 위치 정보</div>
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
            <div class="list-group list-group-flush" id="mpResultList" style="max-height:150px;overflow-y:auto;"></div>
        </div>

        <!-- 지도 -->
        <style>
        #mpPickerMap img.leaflet-tile { max-width: none !important; width: 256px !important; height: 256px !important; }
        #mpPickerMap .leaflet-pane { max-width: none; }
        </style>
        <div id="mpPickerMap" style="width:100%;height:300px;border-radius:8px;border:1px solid #dee2e6;margin-bottom:12px;"></div>

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

<script>
(function() {
    var lat = <?php echo $pk_default_lat; ?>;
    var lng = <?php echo $pk_default_lng; ?>;
    var zoom = <?php echo $pk_has_coords ? 16 : 12; ?>;

    // 지도 초기화
    var map = L.map('mpPickerMap').setView([lat, lng], zoom);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap', maxZoom: 19
    }).addTo(map);

    // 드래그 가능한 마커
    var marker = L.marker([lat, lng], { draggable: true }).addTo(map);
    <?php if ($pk_has_coords): ?>
    marker.bindPopup('현재 위치').openPopup();
    <?php endif; ?>

    // 마커 드래그 → 좌표 업데이트
    marker.on('dragend', function() {
        var pos = marker.getLatLng();
        updateCoords(pos.lat, pos.lng);
    });

    // 지도 클릭 → 마커 이동 + 좌표 업데이트
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

    // 검색 (Nominatim)
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

    // 지도 리사이즈 대응
    setTimeout(function(){ map.invalidateSize(); }, 500);
})();
</script>
