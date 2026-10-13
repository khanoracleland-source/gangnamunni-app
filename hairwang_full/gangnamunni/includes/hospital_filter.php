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
    // 최고관리자는 병원 소속과 무관하게 항상 전체 접근
    if (($_SESSION['ad_admin_role'] ?? '') === 'super_admin') return null;
    if (isset($_SESSION['ad_admin_hospital_id']) && $_SESSION['ad_admin_hospital_id']) {
        return (int)$_SESSION['ad_admin_hospital_id'];
    }
    if (in_array($_SESSION['ad_admin_role'] ?? '', ['hospital_super_admin', 'hospital_admin'], true)) {
        return -1; // hospital account without a valid tenant must never fall back to global access
    }
    return null;
}

/**
 * 현재 관리자가 관리하는 병원 ID 목록
 * 규칙: 관리자 관리(admin_admins.php)에서 지정한 hospital_id 만 사용한다. (이메일 매칭 없음)
 *  - 플랫폼 역할(super_admin/admin/editor/viewer) → null (전체 접근, 호출 페이지에서 별도 제한 가능)
 *  - 병원 역할 → [hospital_id], 미배정이면 빈 배열(접근 0건, fail closed)
 * @return array|null  병원 id 배열 또는 null(전체)
 */
function get_hospital_filter_ids($connect) {
    $role = $_SESSION['ad_admin_role'] ?? '';
    if (in_array($role, ['super_admin', 'admin', 'editor', 'viewer'], true)) return null;

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

    // 관리자 관리에서 지정한 hospital_id 만 사용한다. (이메일 매칭 없음)
    $hospital_id = (int)($_SESSION['ad_admin_hospital_id'] ?? 0);
    return $hospital_id > 0 ? [$hospital_id] : [];
}

/**
 * 현재 관리자가 접근할 수 있는 HMS tenant ID 목록 (인력·직원 등 HMS 테이블 격리용)
 * admin_personnel.php 와 같은 규칙: super_admin 은 null(전체), 그 외는 소속 병원의
 * gangnamunni_hospitals.hms_hospital_id + (구형) gangnamunni_hospital_mapping.hms_tenant_id.
 * 매핑이 없으면 빈 배열 → 아무것도 보이지 않음 (fail-closed)
 *
 * @return array|null  tenant id 문자열 배열, super_admin 은 null
 */
function get_admin_allowed_tenant_ids($connect) {
    $hospital_ids = get_strict_admin_hospital_ids($connect);
    if ($hospital_ids === null) return null;
    $tenant_ids = [];
    if (!empty($hospital_ids) && $connect) {
        $list = implode(',', array_map('intval', $hospital_ids));
        $queries = [
            "SELECT hms_hospital_id AS tenant_id FROM gangnamunni_hospitals
              WHERE id IN ({$list}) AND hms_hospital_id IS NOT NULL AND hms_hospital_id <> ''",
            // 구형 매핑 테이블 (없는 환경도 있음)
            "SELECT hms_tenant_id AS tenant_id FROM gangnamunni_hospital_mapping
              WHERE hospital_id IN ({$list}) AND hms_tenant_id IS NOT NULL AND hms_tenant_id <> ''",
        ];
        foreach ($queries as $sql) {
            try {
                $r = mysqli_query($connect, $sql);
                while ($r && $row = mysqli_fetch_assoc($r)) $tenant_ids[] = (string)$row['tenant_id'];
            } catch (Throwable $e) {
                // 테이블이 없으면 건너뜀 — 결과가 비면 fail-closed
            }
        }
    }
    return array_values(array_unique($tenant_ids));
}

/**
 * tenant 범위 SQL 조건. $allowed 가 null 이면 '1=1', 비어 있으면 '1=0'.
 * @param string $column  예: 'e.tenant_id'
 */
function admin_tenant_scope_sql($connect, $allowed, $column) {
    if ($allowed === null) return '1=1';
    if (empty($allowed)) return '1=0';
    $quoted = array_map(function ($t) use ($connect) {
        return "'" . mysqli_real_escape_string($connect, (string)$t) . "'";
    }, $allowed);
    return $column . ' IN (' . implode(',', $quoted) . ')';
}

/**
 * 등록 마법사 등에서 넘어온 tenant_id 검증: 존재하는 tenant 이고 현재 관리자 범위 안이어야 한다.
 * super_admin 은 빈 값(미지정)도 허용, 그 외 관리자는 반드시 소속 tenant 를 지정해야 한다.
 * @return array [bool ok, string|null tenant_id]
 */
function admin_validate_tenant_id($connect, $allowed, $tenant_id) {
    $tenant_id = trim((string)$tenant_id);
    if ($tenant_id === '' || $tenant_id === '0') {
        return [$allowed === null, null];
    }
    if ($allowed !== null && !in_array($tenant_id, $allowed, true)) return [false, null];
    $stmt = mysqli_prepare($connect, "SELECT id FROM tenants WHERE id = ? LIMIT 1");
    if (!$stmt) return [false, null];
    mysqli_stmt_bind_param($stmt, 's', $tenant_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row ? [true, (string)$row['id']] : [false, null];
}

/**
 * 현재 관리자에게 배정된 병원 목록 (이벤트·광고 신청 등 병원 단위 작업용)
 *  - super_admin → 전체 병원 (최고관리자는 모든 병원을 대신 관리할 수 있다)
 *  - 그 외 → 관리자 관리에서 지정한 hospital_id 1곳, 미배정이면 빈 배열(fail closed)
 * 이메일 매칭은 사용하지 않는다.
 */
function get_assigned_admin_hospital_ids($connect) {
    if (($_SESSION['ad_admin_role'] ?? '') === 'super_admin') {
        $ids = [];
        $r = $connect ? mysqli_query($connect, "SELECT id FROM gangnamunni_hospitals") : false;
        if ($r) while ($row = mysqli_fetch_assoc($r)) $ids[] = (int)$row['id'];
        return $ids;
    }
    $hospital_id = (int)($_SESSION['ad_admin_hospital_id'] ?? 0);
    return $hospital_id > 0 ? [$hospital_id] : [];
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
