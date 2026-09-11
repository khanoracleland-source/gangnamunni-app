<?php
/** Public search URLs are independent of the HMS/auth deployment paths. */
function seoulbeautyCanonicalUrl($uri) {
    $path = parse_url($uri, PHP_URL_PATH);
    if (!is_string($path) || !preg_match('~^/(?:hairwang/)?gangnamunni/(.*)$~', $path, $matches)) return null;
    $page = $matches[1];
    $base = 'https://seoulbeauty.app/gangnamunni/';
    if ($page === '' || $page === 'index.php') return $base;
    parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $query);
    if (in_array($page, ['detail.php', 'doctor_detail.php', 'event_detail.php'], true)) {
        $id = $query['id'] ?? '';
        if (!is_string($id) || !ctype_digit($id) || (int)$id < 1) return null;
        return $base . $page . '?id=' . (int)$id;
    }
    if (!in_array($page, ['reviews.php', 'hospitals.php', 'events.php', 'tips.php', 'columns.php',
        'price_compare.php', 'treatments.php', 'hospital_ranking.php', 'qna.php',
        'terms_of_service.php', 'privacy_policy.php', 'community.php', 'community_guidelines.php'], true)) return null;
    // Unknown scalar parameters may also change content. Keep them rather than
    // incorrectly canonicalizing a search or paginated result to another page.
    $params = [];
    foreach ($query as $key => $value) {
        if (preg_match('/^utm_/i', $key) || in_array($key, ['gclid', 'fbclid'], true)) continue;
        if (is_string($value) && $value !== '') $params[$key] = $value;
    }
    ksort($params);
    return $base . $page . ($params ? '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986) : '');
}

function generateSeoulbeautyCanonicalLink() {
    $url = seoulbeautyCanonicalUrl($_SERVER['REQUEST_URI'] ?? '');
    return $url ? '<link rel="canonical" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . "\n" : '';
}
