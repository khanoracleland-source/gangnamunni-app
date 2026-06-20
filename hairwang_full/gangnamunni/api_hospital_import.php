<?php
/**
 * 레퍼런스 앱 병원 정보 검색 API
 * 강남언니 / 바비톡 / 여신티켓 + 확장 가능
 *
 * GET ?action=search&q=병원명           → 3개 플랫폼 동시 검색
 * GET ?action=search&platform=gangnamunni&q=병원명  → 개별 검색
 * GET ?action=platforms                 → 지원 플랫폼 목록
 * GET ?action=check&q=병원명            → 3개 플랫폼 등록 여부 체크
 *
 * @created 2026-04-03
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// 플랫폼 정의 (확장 가능)
$PLATFORMS = [
    'gangnamunni' => ['name' => '강남언니', 'color' => '#ff6b81', 'icon' => 'bi-heart-pulse', 'enabled' => true],
    'babitalk'    => ['name' => '바비톡',   'color' => '#6c5ce7', 'icon' => 'bi-chat-heart',  'enabled' => true],
    'yeoshin'     => ['name' => '여신티켓', 'color' => '#00b894', 'icon' => 'bi-ticket-perforated', 'enabled' => true],
];

$action = $_GET['action'] ?? '';
$query = trim($_GET['q'] ?? '');
$platform = $_GET['platform'] ?? '';

// 플랫폼 목록
if ($action === 'platforms') {
    $list = [];
    foreach ($PLATFORMS as $key => $p) {
        if ($p['enabled']) $list[] = ['key' => $key, 'name' => $p['name'], 'color' => $p['color'], 'icon' => $p['icon']];
    }
    echo json_encode(['success' => true, 'platforms' => $list], JSON_UNESCAPED_UNICODE);
    exit;
}

// 등록 여부 체크 (3개 동시)
if ($action === 'check') {
    if (empty($query)) { echo json_encode(['success' => false, 'error' => '검색어 필요'], JSON_UNESCAPED_UNICODE); exit; }

    $results = [];
    foreach ($PLATFORMS as $key => $p) {
        if (!$p['enabled']) continue;
        $found = searchPlatform($key, $query);
        $results[$key] = [
            'platform' => $p['name'],
            'color' => $p['color'],
            'icon' => $p['icon'],
            'registered' => !empty($found),
            'count' => count($found),
            'hospitals' => array_slice($found, 0, 3), // 상위 3개만
        ];
    }
    echo json_encode(['success' => true, 'query' => $query, 'results' => $results], JSON_UNESCAPED_UNICODE);
    exit;
}

// 병원 검색
if ($action === 'search') {
    if (empty($query)) { echo json_encode(['success' => false, 'error' => '검색어 필요'], JSON_UNESCAPED_UNICODE); exit; }

    $all = [];
    if ($platform && isset($PLATFORMS[$platform])) {
        $all = searchPlatform($platform, $query);
    } else {
        // 전체 검색
        foreach ($PLATFORMS as $key => $p) {
            if (!$p['enabled']) continue;
            $found = searchPlatform($key, $query);
            $all = array_merge($all, $found);
        }
    }
    echo json_encode(['success' => true, 'query' => $query, 'count' => count($all), 'results' => array_slice($all, 0, 10)], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => false, 'error' => 'action 필요 (platforms, search, check)'], JSON_UNESCAPED_UNICODE);
exit;

// ===== 플랫폼별 검색 =====

function searchPlatform($platform, $query) {
    switch ($platform) {
        case 'gangnamunni': return searchGangnamunni($query);
        case 'babitalk': return searchBabitalk($query);
        case 'yeoshin': return searchYeoshin($query);
        default: return [];
    }
}

// 강남언니: __NEXT_DATA__ → hospitals 배열
function searchGangnamunni($query) {
    $url = 'https://www.gangnamunni.com/search?query=' . urlencode($query);
    $html = curlFetch($url);
    if (!$html) return [];

    $results = [];
    if (preg_match('/<script id="__NEXT_DATA__"[^>]*>(.*?)<\/script>/s', $html, $m)) {
        $data = json_decode($m[1], true);
        $hospitals = $data['props']['pageProps']['hospitals'] ?? [];
        foreach ($hospitals as $h) {
            $results[] = [
                'source' => '강남언니',
                'source_key' => 'gangnamunni',
                'source_color' => '#ff6b81',
                'id' => $h['id'] ?? 0,
                'name' => $h['name'] ?? '',
                'rating' => $h['rating'] ?? 0,
                'review_count' => $h['reviewCount'] ?? 0,
                'image' => $h['profileImage'] ?? ($h['mainImage'] ?? ''),
                'url' => 'https://www.gangnamunni.com/hospital/' . ($h['id'] ?? ''),
            ];
        }
    }
    return $results;
}

// 바비톡: HTML 파싱 (SPA, 제한적)
function searchBabitalk($query) {
    $url = 'https://web.babitalk.com/search?keyword=' . urlencode($query) . '&tab=hospital';
    $html = curlFetch($url);
    if (!$html) return [];

    $results = [];
    // 바비톡은 SPA이므로 og:title 등 메타태그에서 추출 시도
    // 또는 SSR 데이터가 있는 경우
    if (preg_match('/<script id="__NEXT_DATA__"[^>]*>(.*?)<\/script>/s', $html, $m)) {
        $data = json_decode($m[1], true);
        $pp = $data['props']['pageProps'] ?? [];
        // 바비톡 구조에 맞게 파싱
        $items = $pp['hospitals'] ?? $pp['searchResult'] ?? $pp['items'] ?? [];
        foreach ($items as $h) {
            $results[] = [
                'source' => '바비톡',
                'source_key' => 'babitalk',
                'source_color' => '#6c5ce7',
                'id' => $h['id'] ?? $h['hospitalId'] ?? 0,
                'name' => $h['name'] ?? $h['hospitalName'] ?? '',
                'rating' => $h['rating'] ?? $h['score'] ?? 0,
                'review_count' => $h['reviewCount'] ?? 0,
                'image' => $h['image'] ?? $h['thumbnail'] ?? $h['profileImage'] ?? '',
                'url' => 'https://web.babitalk.com/hospital/' . ($h['id'] ?? $h['hospitalId'] ?? ''),
            ];
        }
    }
    // HTML에서 병원 이름 추출 시도 (폴백)
    if (empty($results)) {
        preg_match_all('/<(?:a|div)[^>]*(?:hospital|clinic)[^>]*>.*?<(?:h[2-6]|span|strong)[^>]*>([^<]{3,60})<\/(?:h[2-6]|span|strong)>/is', $html, $matches);
        foreach (array_slice($matches[1] ?? [], 0, 5) as $name) {
            $name = trim(strip_tags($name));
            if ($name) $results[] = ['source' => '바비톡', 'source_key' => 'babitalk', 'source_color' => '#6c5ce7', 'name' => $name, 'id' => 0, 'image' => '', 'url' => ''];
        }
    }
    return $results;
}

// 여신티켓: __NEXT_DATA__ 또는 HTML
function searchYeoshin($query) {
    $url = 'https://www.yeoshin.co.kr/search?query=' . urlencode($query);
    $html = curlFetch($url);
    if (!$html) return [];

    $results = [];
    if (preg_match('/<script id="__NEXT_DATA__"[^>]*>(.*?)<\/script>/s', $html, $m)) {
        $data = json_decode($m[1], true);
        $pp = $data['props']['pageProps'] ?? [];
        // dehydratedState → queries → hospitals
        $dh = $pp['dehydratedState'] ?? [];
        $queries = $dh['queries'] ?? [];
        foreach ($queries as $q) {
            $qdata = $q['state']['data'] ?? [];
            $items = $qdata['hospitals'] ?? $qdata['clinics'] ?? $qdata['items'] ?? $qdata['data'] ?? [];
            if (is_array($items)) {
                foreach ($items as $h) {
                    if (!is_array($h)) continue;
                    $results[] = [
                        'source' => '여신티켓',
                        'source_key' => 'yeoshin',
                        'source_color' => '#00b894',
                        'id' => $h['id'] ?? $h['clinicId'] ?? $h['hospitalId'] ?? 0,
                        'name' => $h['name'] ?? $h['clinicName'] ?? $h['hospitalName'] ?? '',
                        'rating' => $h['rating'] ?? $h['score'] ?? 0,
                        'review_count' => $h['reviewCount'] ?? $h['reviewCnt'] ?? 0,
                        'image' => $h['image'] ?? $h['thumbnail'] ?? $h['profileImage'] ?? $h['imageUrl'] ?? '',
                        'address' => $h['address'] ?? $h['addr'] ?? '',
                        'url' => 'https://www.yeoshin.co.kr/hospital/' . ($h['id'] ?? $h['clinicId'] ?? ''),
                    ];
                }
            }
        }
    }
    // HTML 폴백
    if (empty($results)) {
        preg_match_all('/<(?:a|div)[^>]*(?:hospital|clinic)[^>]*>.*?<(?:h[2-6]|span|strong|p)[^>]*>([^<]{3,60})<\/(?:h[2-6]|span|strong|p)>/is', $html, $matches);
        foreach (array_slice($matches[1] ?? [], 0, 5) as $name) {
            $name = trim(strip_tags($name));
            if ($name) $results[] = ['source' => '여신티켓', 'source_key' => 'yeoshin', 'source_color' => '#00b894', 'name' => $name, 'id' => 0, 'image' => '', 'url' => ''];
        }
    }
    return $results;
}

function curlFetch($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => ['Accept-Language: ko-KR,ko;q=0.9'],
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($code >= 200 && $code < 400) ? $res : false;
}
