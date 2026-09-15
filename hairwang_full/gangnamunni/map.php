<?php
/**
 * 지도 기반 병원 탐색 페이지
 * Kakao Maps JS API 사용
 */
require_once __DIR__ . '/includes/init.php';
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }

// 병원 목록 조회 (위치 데이터 포함)
$hospitals = [];
$sql = "SELECT id, name, address, main_image_url, rating, review_count, latitude, longitude, phone
        FROM gangnamunni_hospitals
        WHERE latitude IS NOT NULL AND longitude IS NOT NULL AND latitude != 0
        ORDER BY rating DESC";
$result = mysqli_query($connect, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $hospitals[] = $row;
    }
}

// 카테고리 필터용
$categories = [];
$cat_result = mysqli_query($connect, "SELECT DISTINCT category FROM gangnamunni_treatments WHERE is_active=1 ORDER BY category");
if ($cat_result) {
    while ($r = mysqli_fetch_assoc($cat_result)) {
        if (!empty($r['category'])) $categories[] = $r['category'];
    }
}

// JSON으로 변환
$hospitals_json = json_encode($hospitals, JSON_UNESCAPED_UNICODE);

// 카카오 맵 API 키 (복수키 로테이션)
if (!function_exists('getSettingValue')) {
    require_once __DIR__ . '/includes/settings_helper.php';
}
$kakao_api_key = '';
$kakao_keys_json = getSettingValue($connect, 'kakao_map_api_keys', '');
if (!empty($kakao_keys_json)) {
    $kakao_keys = json_decode($kakao_keys_json, true);
    if (is_array($kakao_keys) && count($kakao_keys) > 0) {
        $kakao_api_key = $kakao_keys[array_rand($kakao_keys)];
    }
}
if (empty($kakao_api_key)) {
    $kakao_api_key = getSettingValue($connect, 'kakao_map_api_key', '');
}

// DB 커넥션은 bottom_nav.php include 이후에 닫음 (bottom_nav.php에서 DB 조회 필요)
?>
<!DOCTYPE html>
<html lang="ko" data-bs-theme="light">
<head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>지도로 <?php echo term('business', '병원'); ?> 찾기 - <?php echo function_exists('site_name') ? site_name() : '서울미녀'; ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>
(function(){var t=localStorage.getItem('theme');if(t==='dark')document.documentElement.setAttribute('data-bs-theme','dark');})();
</script>
<style>
:root { --primary: #AE1932; }
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: var(--bs-body-bg, #fff); color: var(--bs-body-color, #1a1a1a); }

/* 상단 헤더 */
.map-header {
    position: fixed; top: 0; left: 0; right: 0; z-index: 100;
    max-width: 640px; margin: 0 auto;
    background: var(--bs-body-bg, #fff);
    border-bottom: 1px solid var(--bs-border-color, #eee);
    padding: 10px 16px;
    display: flex; align-items: center; gap: 10px;
}
.map-header .back-btn { width: 36px; height: 36px; border: none; background: none; font-size: calc(20px * var(--fs-scale,1) * var(--fs-page,1)); cursor: pointer; color: var(--bs-body-color); }
.map-header .search-box {
    flex: 1; position: relative;
}
.map-header .search-box input {
    width: 100%; padding: 8px 36px 8px 14px;
    border: 1px solid var(--bs-border-color, #ddd); border-radius: 20px;
    font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); background: var(--bs-tertiary-bg, #f5f5f5);
    color: var(--bs-body-color);
}
.map-header .search-box input:focus { outline: none; border-color: var(--primary); }
.map-header .search-box .search-icon {
    position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
    color: var(--bs-secondary-color, #888); font-size: calc(16px * var(--fs-scale,1) * var(--fs-page,1));
}

/* 지도 영역 */
#map-container {
    position: fixed; top: 56px; left: 0; right: 0; bottom: 0;
    max-width: 640px; margin: 0 auto;
}

/* 현위치 버튼 */
.location-btn {
    position: fixed; bottom: 180px; right: calc(50% - 300px);
    z-index: 50; width: 44px; height: 44px;
    background: var(--bs-body-bg, #fff); border: 1px solid var(--bs-border-color, #ddd);
    border-radius: 50%; box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: calc(20px * var(--fs-scale,1) * var(--fs-page,1)); color: var(--primary);
}
@media (max-width: 640px) {
    .location-btn { right: 16px; }
}

/* 하단 병원 카드 슬라이더 */
.bottom-cards {
    position: fixed; bottom: 0; left: 0; right: 0;
    max-width: 640px; margin: 0 auto; z-index: 50;
    padding: 0 16px 16px;
}
.cards-scroll {
    display: flex; gap: 10px; overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scroll-snap-type: x mandatory;
    padding-bottom: 4px;
}
.cards-scroll::-webkit-scrollbar { display: none; }

.map-card {
    flex-shrink: 0; width: 280px;
    background: var(--bs-body-bg, #fff);
    border-radius: 14px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.15);
    overflow: hidden;
    scroll-snap-align: start;
    text-decoration: none; color: inherit;
    display: flex;
}
.map-card .card-img {
    width: 100px; height: 100px; flex-shrink: 0;
    background: var(--bs-tertiary-bg, #eee);
    display: flex; align-items: center; justify-content: center;
}
.map-card .card-img img { width: 100%; height: 100%; object-fit: cover; }
.map-card .card-img .no-img { font-size: calc(28px * var(--fs-scale,1) * var(--fs-page,1)); color: var(--bs-secondary-color, #999); }
.map-card .card-info { padding: 10px 12px; flex: 1; min-width: 0; display: flex; flex-direction: column; justify-content: center; }
.map-card .card-name { font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 4px; }
.map-card .card-rating { font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1)); color: #f59e0b; margin-bottom: 2px; }
.map-card .card-addr { font-size: calc(11px * var(--fs-scale,1) * var(--fs-page,1)); color: var(--bs-secondary-color, #888); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.map-card .card-actions { display: flex; gap: 6px; margin-top: 6px; }
.map-card .card-actions button {
    padding: 4px 10px; border-radius: 6px; font-size: calc(11px * var(--fs-scale,1) * var(--fs-page,1)); cursor: pointer; border: none;
}
.card-actions .btn-detail { background: var(--primary); color: #fff; }
.card-actions .btn-call { background: var(--bs-tertiary-bg, #f0f0f0); color: var(--bs-body-color); }

/* 병원 수 표시 */
.hospital-count {
    position: fixed; top: 64px; left: 50%; transform: translateX(-50%);
    z-index: 50; background: var(--bs-body-bg, #fff);
    padding: 6px 16px; border-radius: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.12);
    font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600;
    max-width: 640px;
}
</style>
<?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head>
<body>

<div class="map-header">
    <button class="back-btn" onclick="history.back()"><i class="bi bi-chevron-left"></i></button>
    <div class="search-box">
        <input type="text" id="map-search" placeholder="<?php echo term('business', '병원'); ?>명, 지역 검색...">
        <i class="bi bi-search search-icon"></i>
    </div>
</div>

<div id="map-container"></div>

<div class="hospital-count" id="hospital-count"></div>

<button class="location-btn" id="my-location-btn" title="현재 위치">
    <i class="bi bi-crosshair"></i>
</button>

<div class="bottom-cards" id="bottom-cards" style="display:none;">
    <div class="cards-scroll" id="cards-scroll"></div>
</div>

<!-- Kakao Maps SDK -->
<script src="//dapi.kakao.com/v2/maps/sdk.js?appkey=<?php echo htmlspecialchars($kakao_api_key); ?>&libraries=services,clusterer"></script>
<script>
(function(){
    var hospitals = <?php echo $hospitals_json; ?>;
    var map, clusterer, markers = [], infowindow;
    var filteredHospitals = hospitals;
    var selectedMarkerIdx = -1;

    // 서울 강남 기본 좌표
    var defaultCenter = new kakao.maps.LatLng(37.5013, 127.0246);

    function initMap() {
        var container = document.getElementById('map-container');
        map = new kakao.maps.Map(container, {
            center: defaultCenter,
            level: 5
        });

        // 클러스터러 초기화
        clusterer = new kakao.maps.MarkerClusterer({
            map: map,
            averageCenter: true,
            minLevel: 4,
            disableClickZoom: true,
            styles: [{
                width: '44px', height: '44px',
                background: 'var(--primary, #AE1932)',
                borderRadius: '50%',
                color: '#fff',
                textAlign: 'center',
                lineHeight: '44px',
                fontSize: '14px',
                fontWeight: 'bold'
            }]
        });

        kakao.maps.event.addListener(clusterer, 'clusterclick', function(cluster) {
            var level = map.getLevel() - 1;
            map.setLevel(level, {anchor: cluster.getCenter()});
        });

        // 지도 이동/줌 이벤트
        kakao.maps.event.addListener(map, 'idle', updateVisibleCards);

        renderMarkers(hospitals);
        updateCount(hospitals.length);

        // 현위치 시도
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(pos) {
                var loc = new kakao.maps.LatLng(pos.coords.latitude, pos.coords.longitude);
                map.setCenter(loc);
            }, function() {}, {timeout: 5000});
        }
    }

    function renderMarkers(list) {
        // 기존 마커 제거
        clusterer.clear();
        markers = [];

        var markerImage = new kakao.maps.MarkerImage(
            'https://t1.daumcdn.net/localimg/localimages/07/mapapidoc/markerStar.png',
            new kakao.maps.Size(24, 35)
        );

        list.forEach(function(h, idx) {
            if (!h.latitude || !h.longitude) return;

            var pos = new kakao.maps.LatLng(parseFloat(h.latitude), parseFloat(h.longitude));
            var marker = new kakao.maps.Marker({
                position: pos,
                image: markerImage
            });

            marker._hospitalIdx = idx;
            marker._hospital = h;

            kakao.maps.event.addListener(marker, 'click', function() {
                selectHospital(idx, h);
                map.panTo(pos);
            });

            markers.push(marker);
        });

        clusterer.addMarkers(markers);
    }

    function selectHospital(idx, h) {
        selectedMarkerIdx = idx;
        var cardsEl = document.getElementById('bottom-cards');
        var scrollEl = document.getElementById('cards-scroll');

        cardsEl.style.display = '';
        scrollEl.innerHTML = '';

        // 선택된 병원 카드 + 주변 카드
        var visible = getVisibleHospitals();
        // 선택된 병원을 앞으로
        var sorted = [h].concat(visible.filter(function(v) { return v.id !== h.id; }));

        sorted.slice(0, 10).forEach(function(hospital) {
            scrollEl.appendChild(createCard(hospital));
        });
    }

    function createCard(h) {
        var card = document.createElement('a');
        card.href = 'detail.php?id=' + h.id;
        card.className = 'map-card';

        var imgHtml = '';
        if (h.main_image_url) {
            imgHtml = '<div class="card-img"><img src="' + escHtml(h.main_image_url) + '" alt=""></div>';
        } else {
            imgHtml = '<div class="card-img"><span class="no-img"><i class="bi bi-building"></i></span></div>';
        }

        var ratingHtml = '';
        if (h.rating > 0) {
            ratingHtml = '<div class="card-rating"><i class="bi bi-star-fill"></i> ' +
                Number(h.rating).toFixed(1) + ' (' + (h.review_count || 0) + ')</div>';
        }

        var addrHtml = h.address ? '<div class="card-addr"><i class="bi bi-geo-alt"></i> ' + escHtml(h.address) + '</div>' : '';

        card.innerHTML = imgHtml +
            '<div class="card-info">' +
            '<div class="card-name">' + escHtml(h.name) + '</div>' +
            ratingHtml + addrHtml +
            '</div>';

        return card;
    }

    function getVisibleHospitals() {
        var bounds = map.getBounds();
        return filteredHospitals.filter(function(h) {
            if (!h.latitude || !h.longitude) return false;
            var pos = new kakao.maps.LatLng(parseFloat(h.latitude), parseFloat(h.longitude));
            return bounds.contain(pos);
        });
    }

    function updateVisibleCards() {
        var visible = getVisibleHospitals();
        updateCount(visible.length);

        var cardsEl = document.getElementById('bottom-cards');
        var scrollEl = document.getElementById('cards-scroll');

        if (visible.length === 0) {
            cardsEl.style.display = 'none';
            return;
        }

        cardsEl.style.display = '';
        scrollEl.innerHTML = '';

        // 평점순 정렬
        visible.sort(function(a, b) { return (b.rating || 0) - (a.rating || 0); });
        visible.slice(0, 10).forEach(function(h) {
            scrollEl.appendChild(createCard(h));
        });
    }

    function updateCount(n) {
        document.getElementById('hospital-count').innerHTML =
            '<i class="bi bi-hospital" style="color:var(--primary);"></i> <?php echo term('business', '병원'); ?> <strong>' + n + '</strong>개';
    }

    function escHtml(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    // 검색
    document.getElementById('map-search').addEventListener('input', function() {
        var q = this.value.trim().toLowerCase();
        if (!q) {
            filteredHospitals = hospitals;
        } else {
            filteredHospitals = hospitals.filter(function(h) {
                return (h.name && h.name.toLowerCase().indexOf(q) >= 0) ||
                       (h.address && h.address.toLowerCase().indexOf(q) >= 0);
            });
        }
        renderMarkers(filteredHospitals);
        updateCount(filteredHospitals.length);

        // 검색 결과에 맞게 지도 범위 조절
        if (filteredHospitals.length > 0 && q) {
            var bounds = new kakao.maps.LatLngBounds();
            filteredHospitals.forEach(function(h) {
                if (h.latitude && h.longitude) {
                    bounds.extend(new kakao.maps.LatLng(parseFloat(h.latitude), parseFloat(h.longitude)));
                }
            });
            map.setBounds(bounds);
        }
    });

    // 현위치 버튼
    document.getElementById('my-location-btn').addEventListener('click', function() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(pos) {
                var loc = new kakao.maps.LatLng(pos.coords.latitude, pos.coords.longitude);
                map.setCenter(loc);
                map.setLevel(4);
            }, function() {
                alert('위치 정보를 가져올 수 없습니다.');
            });
        } else {
            alert('이 브라우저에서는 위치 기능을 지원하지 않습니다.');
        }
    });

    // 초기화
    kakao.maps.load(initMap);
})();
</script>

<?php include_once __DIR__ . '/includes/bottom_nav.php'; ?>
<?php if (isset($connect) && $connect) { mysqli_close($connect); } ?>
</body>
</html>
