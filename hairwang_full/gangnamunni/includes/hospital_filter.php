<?php
/**
 * 병원별 데이터 필터 미들웨어
 * hospital_admin 역할이면 자기 병원 데이터만 접근
 *
 * 사용법:
 *   require_once __DIR__ . '/hospital_filter.php';
 *   $hid = get_hospital_filter();
 *   if ($hid) { $query .= " AND hospital_id = $hid"; }
 */

/**
 * 현재 로그인 관리자의 hospital_id 반환
 * super_admin/admin은 null (전체 접근)
 * hospital_admin은 해당 hospital_id
 */
function get_hospital_filter() {
    if (isset($_SESSION['ad_admin_hospital_id']) && $_SESSION['ad_admin_hospital_id']) {
        return (int)$_SESSION['ad_admin_hospital_id'];
    }
    return null;
}

/**
 * 현재 관리자가 병원 관리자인지 확인
 */
function is_hospital_admin() {
    return isset($_SESSION['ad_admin_role']) && $_SESSION['ad_admin_role'] === 'hospital_admin';
}

/**
 * 현재 관리자의 병원 이름 반환
 */
function get_hospital_name() {
    return $_SESSION['ad_admin_hospital_name'] ?? '';
}

/**
 * SQL 쿼리에 hospital_id 필터 자동 적용
 * @param string $query SQL 쿼리
 * @param string $alias 테이블 별칭 (예: 'b' for bookings)
 * @return string 필터가 적용된 쿼리
 */
function apply_hospital_filter($query, $alias = '') {
    $hid = get_hospital_filter();
    if (!$hid) return $query;

    $col = $alias ? "{$alias}.hospital_id" : "hospital_id";

    // WHERE 절이 있으면 AND 추가, 없으면 WHERE 추가
    if (preg_match('/\bWHERE\b/i', $query)) {
        // WHERE 뒤에 바로 추가
        $query = preg_replace('/\bWHERE\b/i', "WHERE {$col} = {$hid} AND", $query, 1);
    } elseif (preg_match('/\bORDER\s+BY\b/i', $query)) {
        // ORDER BY 앞에 WHERE 추가
        $query = preg_replace('/\bORDER\s+BY\b/i', "WHERE {$col} = {$hid} ORDER BY", $query, 1);
    } elseif (preg_match('/\bGROUP\s+BY\b/i', $query)) {
        // GROUP BY 앞에 WHERE 추가
        $query = preg_replace('/\bGROUP\s+BY\b/i', "WHERE {$col} = {$hid} GROUP BY", $query, 1);
    } elseif (preg_match('/\bLIMIT\b/i', $query)) {
        // LIMIT 앞에 WHERE 추가
        $query = preg_replace('/\bLIMIT\b/i', "WHERE {$col} = {$hid} LIMIT", $query, 1);
    } else {
        // 쿼리 끝에 WHERE 추가
        $query .= " WHERE {$col} = {$hid}";
    }

    return $query;
}

/**
 * Prepared statement용 hospital_id 파라미터 반환
 * @return array ['where' => 'AND hospital_id = ?', 'param' => hospital_id] or ['where' => '', 'param' => null]
 */
function hospital_filter_param($alias = '') {
    $hid = get_hospital_filter();
    if (!$hid) return ['where' => '', 'param' => null, 'type' => ''];
    $col = $alias ? "{$alias}.hospital_id" : "hospital_id";
    return ['where' => "AND {$col} = ?", 'param' => $hid, 'type' => 'i'];
}

/**
 * gangnamunni hospital_id -> HMS tenant_id
 */
function get_hms_tenant_id() {
    global $connect;
    $hid = get_hospital_filter();
    if (!$hid || !$connect) return null;
    
    $stmt = $connect->prepare("SELECT hms_tenant_id FROM gangnamunni_hospital_mapping WHERE hospital_id = ?");
    $stmt->bind_param('i', $hid);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row ? $row['hms_tenant_id'] : null;
}

/**
 * HMS tenant_id WHERE filter
 */
function apply_hms_tenant_filter($query, $alias = '') {
    $tid = get_hms_tenant_id();
    if (!$tid) return $query;
    $col = $alias ? $alias . '.tenant_id' : 'tenant_id';
    $safe = addslashes($tid);
    if (stripos($query, 'WHERE') !== false) {
        return preg_replace('/WHERE/i', "WHERE " . $col . " = '" . $safe . "' AND", $query, 1);
    }
    return $query . " WHERE " . $col . " = '" . $safe . "'";
}


/**
 * Check if hospital is suspended
 */
function is_hospital_suspended() {
    global $connect;
    $hid = get_hospital_filter();
    if (!$hid || !$connect) return false;

    $stmt = $connect->prepare("SELECT status FROM gangnamunni_hospital_suspension WHERE hospital_id = ?");
    $stmt->bind_param('i', $hid);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return ($row && $row['status'] === 'suspended');
}

/**
 * Block access if hospital is suspended (call in admin pages)
 */
function check_hospital_suspension() {
    if (is_hospital_suspended()) {
        echo '<div class="alert alert-danger text-center m-5">';
        echo '<h4><i class="bi bi-exclamation-triangle"></i> Service Suspended</h4>';
        echo '<p>Your hospital account has been suspended due to non-payment.</p>';
        echo '<p>Please contact the administrator to reactivate your account.</p>';
        echo '</div>';
        exit;
    }
}
