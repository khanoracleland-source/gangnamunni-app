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
    if (in_array($_SESSION['ad_admin_role'] ?? '', ['hospital_super_admin', 'hospital_admin', 'hospital_sub_admin', 'hospital_staff'], true)) {
        return -1; // hospital account without a valid tenant must never fall back to global access
    }
    return null;
}

/**
 * 현재 관리자가 관리하는 병원 ID 목록 (다병원 지원)
 * 규칙: 병원.email == 관리자 로그인(username/email) 인 모든 병원.
 *  - super_admin        → null (전체 접근)
 *  - 이메일 매칭 병원 있음 → 해당 병원 id 배열 (한 관리자가 여러 병원 소유 가능)
 *  - 매칭 없음 + 단일 hospital_id 세션값 있음 → [그 id]
 *  - 매칭 없음 + 없음     → null (병원 소속 없는 플랫폼 관리자 = 전체)
 * @return array|null  병원 id 배열 또는 null(전체)
 */
function get_hospital_filter_ids($connect) {
    $role = $_SESSION['ad_admin_role'] ?? '';
    if (in_array($role, ['super_admin', 'admin', 'editor', 'viewer', 'staff'], true)) return null;

    $login = trim($_SESSION['ad_admin_user'] ?? '');
    $mail  = trim($_SESSION['ad_admin_email'] ?? '');
    $ids = [];
    if ($connect && ($login !== '' || $mail !== '')) {
        $stmt = mysqli_prepare($connect, "SELECT id FROM gangnamunni_hospitals WHERE email = ? OR email = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ss', $login, $mail);
            mysqli_stmt_execute($stmt);
            $r = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($r)) $ids[] = (int)$row['id'];
            mysqli_stmt_close($stmt);
        }
    }
    if (!empty($ids)) return array_values(array_unique($ids));

    // 이메일 매칭 병원 없음 → 세션 단일 hospital_id 있으면 그것.
    // 병원 역할인데 유효한 소속이 없으면 빈 배열(접근 0건)로 fail closed.
    $hid = (int)($_SESSION['ad_admin_hospital_id'] ?? 0);
    return $hid > 0 ? [$hid] : [];
}

/**
 * 엄격한 병원 범위: super_admin 만 전체, 그 외 모든 관리자는 소속 병원만.
 * 병원별 관리 페이지에서 플랫폼 역할이 전체 데이터로 승격되는 것을 막을 때 사용한다.
 *
 * @return array|null 병원 id 배열, super_admin 은 null
 */
function get_strict_admin_hospital_ids($connect) {
    if (($_SESSION['ad_admin_role'] ?? '') === 'super_admin') {
        return null;
    }

    $ids = [];
    $login = trim((string)($_SESSION['ad_admin_user'] ?? ''));
    $mail = trim((string)($_SESSION['ad_admin_email'] ?? ''));
    if ($connect && ($login !== '' || $mail !== '')) {
        $stmt = mysqli_prepare($connect, "SELECT id FROM gangnamunni_hospitals WHERE email = ? OR email = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ss', $login, $mail);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) {
                $ids[] = (int)$row['id'];
            }
            mysqli_stmt_close($stmt);
        }
    }

    $hospital_id = (int)($_SESSION['ad_admin_hospital_id'] ?? 0);
    if ($hospital_id > 0) {
        $ids[] = $hospital_id;
    }

    return array_values(array_unique(array_filter($ids)));
}

/**
 * get_hospital_filter_ids 를 SQL IN 절 문자열로 반환 (정수만 담기므로 안전).
 * @return string|null  "1,2,3" 또는 null(전체)
 */
function get_hospital_filter_in($connect) {
    $ids = get_hospital_filter_ids($connect);
    if ($ids === null) return null;
    if (empty($ids)) return '0'; // 소속은 있으나 병원이 없음 → 아무것도 안 보이게
    return implode(',', array_map('intval', $ids));
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
