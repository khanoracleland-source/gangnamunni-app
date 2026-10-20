<?php
/**
 * 검색 자동완성 (Claude 디자인 03-08) — GET q=키워드  → JSON
 * { treatments:[{name, href, hint?}], hospitals:[{id,name,district,href}] }
 */
require_once __DIR__ . '/includes/init.php';
if (!function_exists('extract_addr_district')) {
    function extract_addr_district($addr) {
        $parts = preg_split('/\s+/u', trim((string)$addr));
        foreach ($parts as $p) {
            if ($p !== '' && mb_substr($p, -1, 1, 'UTF-8') === '구') return mb_substr($p, 0, mb_strlen($p, 'UTF-8') - 1, 'UTF-8');
        }
        return '';
    }
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=30');

$q = trim((string)($_GET['q'] ?? ''));
$out = ['treatments' => [], 'hospitals' => [], 'offered' => ['treatment' => '', 'items' => []]];
if ($q === '' || mb_strlen($q, 'UTF-8') > 40 || !isset($connect) || !$connect) { echo json_encode($out, JSON_UNESCAPED_UNICODE); exit; }

$like = '%' . addcslashes($q, '%_\\') . '%';

// 시술 (최대 4)
$stmt = mysqli_prepare($connect, "SELECT tm.id, tm.name FROM gangnamunni_treatment_master tm
    WHERE tm.is_active = 1 AND tm.name LIKE ?
    ORDER BY (tm.name LIKE ?) DESC, CHAR_LENGTH(tm.name) ASC LIMIT 4");
$prefix = addcslashes($q, '%_\\') . '%';
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ss', $like, $prefix);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $first = true; $first_tid = 0; $first_name = '';
    while ($r = mysqli_fetch_assoc($res)) {
        $out['treatments'][] = ['name' => $r['name'], 'href' => 'hospitals.php?q=' . rawurlencode($r['name'])];
        if ($first) {
            // 가장 많이 등록된 용량 1개를 "용량 자동 선택" 항목으로 제안
            $tid = (int)$r['id']; $first_tid = $tid; $first_name = $r['name'];
            $dq = @mysqli_query($connect, "SELECT p.dosage_value v, p.dosage_unit u, COUNT(*) c
                FROM gangnamunni_hospital_treatment_prices p
                INNER JOIN gangnamunni_hospital_treatments ht ON ht.id = p.hospital_treatment_id
                WHERE ht.treatment_id = {$tid} AND ht.status='active' AND ht.is_active=1 AND p.price>0
                  AND p.dosage_value>0 AND TRIM(IFNULL(p.dosage_unit,''))<>''
                GROUP BY p.dosage_value, p.dosage_unit ORDER BY c DESC LIMIT 1");
            if ($dq && ($d = mysqli_fetch_assoc($dq))) {
                $lab = rtrim(rtrim(number_format((float)$d['v'], 2, '.', ''), '0'), '.') . trim($d['u']);
                $out['treatments'][] = ['name' => $r['name'] . ' ' . $lab,
                    'href' => 'hospitals.php?q=' . rawurlencode($r['name']) . '&dosage=' . rawurlencode($lab), 'hint' => '용량 자동 선택'];
            }
            $first = false;
        }
    }
    mysqli_stmt_close($stmt);
}

// 첫 번째 시술을 실제로 제공하는 병원 (최저가순, 최대 5) — 03-09 결과와 같은 병원들
$out['offered'] = ['treatment' => $first_name, 'items' => []];
if ($first_tid > 0) {
    $oq = @mysqli_query($connect, "SELECT h.id, h.name, h.address, MIN(p.price) AS price
        FROM gangnamunni_hospital_treatments ht
        INNER JOIN gangnamunni_hospital_treatment_prices p ON p.hospital_treatment_id = ht.id AND p.price > 0
        INNER JOIN gangnamunni_hospitals h ON h.id = ht.hospital_id
        WHERE ht.treatment_id = {$first_tid} AND ht.status='active' AND ht.is_active=1
          AND h.is_active=1 AND COALESCE(h.is_demo,0)=0 AND (h.status='approved' OR h.status IS NULL)
        GROUP BY h.id, h.name, h.address ORDER BY price ASC LIMIT 5");
    if ($oq) {
        while ($o = mysqli_fetch_assoc($oq)) {
            $out['offered']['items'][] = ['id' => (int)$o['id'], 'name' => $o['name'],
                'district' => extract_addr_district((string)$o['address']), 'address' => (string)$o['address'], 'price' => (int)$o['price'],
                'href' => 'hospitals.php?q=' . rawurlencode($first_name) . '&hid=' . (int)$o['id']];
        }
    }
}

// 병원 (최대 3)
$stmt = mysqli_prepare($connect, "SELECT id, name, address FROM gangnamunni_hospitals
    WHERE is_active = 1 AND COALESCE(is_demo,0)=0 AND (status='approved' OR status IS NULL) AND name LIKE ?
    ORDER BY (name LIKE ?) DESC, CHAR_LENGTH(name) ASC LIMIT 3");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ss', $like, $prefix);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_assoc($res)) {
        $dist = function_exists('extract_addr_district') ? extract_addr_district((string)$r['address']) : '';
        $out['hospitals'][] = ['id' => (int)$r['id'], 'name' => $r['name'], 'district' => $dist, 'address' => (string)$r['address'],
            'href' => 'hospitals.php?q=' . rawurlencode($r['name']) . '&hid=' . (int)$r['id']];
    }
    mysqli_stmt_close($stmt);
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
