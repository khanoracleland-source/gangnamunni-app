<?php
/**
 * 시술 패키지 관리 페이지 (admin_treatment_packages.php)
 * - 패키지 CRUD (생성/조회/수정/삭제)
 * - 병원 선택 → 해당 병원 패키지 관리
 * - 패키지 내 시술 항목 체크박스 선택
 * - 할인율 자동 계산 (원가 대비 패키지가)
 * - 통계: 전체/활성 패키지, 평균 할인율
 *
 * Bootstrap 5.3 + 다크모드 지원
 * @created 2026-03-25
 */

session_start();
$admin_page_title = '시술 패키지 관리';
$admin_current = 'treatment_packages';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

// DB 연결
if (!isset($connect) || !$connect) {
    $connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    if ($connect) mysqli_set_charset($connect, 'utf8mb4');
}
if (!$connect) die('DB 연결 실패');

loadThemeSettings($connect);
require_admin_login();
require_permission('treatment_packages');
require_once __DIR__ . '/includes/hospital_filter.php';
$my_ids = get_strict_admin_hospital_ids($connect);
$my_in = $my_ids === null ? null : (empty($my_ids) ? '0' : implode(',', array_map('intval', $my_ids)));

// ===== 테이블 자동 생성 =====
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_treatment_packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    package_price INT NOT NULL DEFAULT 0,
    original_price INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    deleted_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_hospital (hospital_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_treatment_package_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    package_id INT NOT NULL,
    treatment_id INT NOT NULL,
    sort_order INT DEFAULT 0,
    INDEX idx_package (package_id),
    INDEX idx_treatment (treatment_id),
    UNIQUE KEY uk_package_treatment (package_id, treatment_id),
    CONSTRAINT fk_package_item_package FOREIGN KEY (package_id) REFERENCES gangnamunni_treatment_packages(id) ON DELETE CASCADE,
    CONSTRAINT fk_package_item_treatment FOREIGN KEY (treatment_id) REFERENCES gangnamunni_treatment_master(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 기존 설치에도 soft-delete 컬럼을 멱등 적용한다.
$deleted_col = mysqli_query($connect, "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gangnamunni_treatment_packages' AND COLUMN_NAME = 'deleted_at' LIMIT 1");
if ($deleted_col && mysqli_num_rows($deleted_col) === 0) {
    mysqli_query($connect, "ALTER TABLE gangnamunni_treatment_packages ADD COLUMN deleted_at DATETIME DEFAULT NULL AFTER is_active");
}

// 기존 설치에도 중복 방지 제약을 적용한다. 기존 중복 데이터가 있으면 데이터를 임의 삭제하지 않고 로그로 남긴다.
$unique_idx = mysqli_query($connect, "SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gangnamunni_treatment_package_items' AND INDEX_NAME = 'uk_package_treatment' LIMIT 1");
if ($unique_idx && mysqli_num_rows($unique_idx) === 0) {
    try {
        if (!mysqli_query($connect, "ALTER TABLE gangnamunni_treatment_package_items ADD UNIQUE KEY uk_package_treatment (package_id, treatment_id)")) {
            error_log('[treatment_packages] 중복 방지 인덱스 생성 실패: ' . mysqli_error($connect));
        }
    } catch (Throwable $e) {
        error_log('[treatment_packages] 중복 방지 인덱스 생성 실패: ' . $e->getMessage());
    }
}

// CSRF 토큰
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
}

function package_treatment_ids_for_hospital($connect, $hospital_id, $raw_ids) {
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)$raw_ids))));
    if ($hospital_id <= 0 || empty($ids)) return [];
    $in = implode(',', $ids);
    $allowed = [];
    $stmt = mysqli_prepare($connect, "SELECT DISTINCT treatment_id FROM gangnamunni_hospital_treatments WHERE hospital_id = ? AND status = 'active' AND is_active = 1 AND treatment_id IN ({$in})");
    if (!$stmt) throw new RuntimeException('패키지 시술 권한 확인 실패');
    mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) $allowed[] = (int)$row['treatment_id'];
    mysqli_stmt_close($stmt);
    return $allowed;
}

function package_execute_or_throw($stmt, $message) {
    if (!$stmt || !mysqli_stmt_execute($stmt)) {
        throw new RuntimeException($message . ($stmt ? ': ' . mysqli_stmt_error($stmt) : ''));
    }
}

function package_redirect($hospital_id, $message, $type = 'success') {
    header('Location: admin_treatment_packages.php?hospital_id=' . intval($hospital_id)
        . '&msg=' . urlencode($message) . '&msg_type=' . urlencode($type));
    exit;
}

function package_actual_prices($connect, $hospital_id, array $treatment_ids) {
    $ids = array_values(array_unique(array_filter(array_map('intval', $treatment_ids))));
    if ($hospital_id <= 0 || empty($ids)) return [];
    $in = implode(',', $ids);
    $prices = [];
    $stmt = mysqli_prepare($connect,
        "SELECT ht.treatment_id,
                MIN(COALESCE((SELECT MIN(hp.price) FROM gangnamunni_hospital_treatment_prices hp WHERE hp.hospital_treatment_id = ht.id AND hp.price > 0),
                             NULLIF(ht.price_min, 0), NULLIF(ht.price_max, 0), 0)) AS actual_price
         FROM gangnamunni_hospital_treatments ht
         WHERE ht.hospital_id = ? AND ht.status = 'active' AND ht.is_active = 1 AND ht.treatment_id IN ({$in})
         GROUP BY ht.treatment_id");
    if (!$stmt) throw new RuntimeException('시술 가격 조회 준비 실패');
    mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
    package_execute_or_throw($stmt, '시술 가격 조회 실패');
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) $prices[(int)$row['treatment_id']] = (int)$row['actual_price'];
    mysqli_stmt_close($stmt);
    return $prices;
}

function package_has_missing_prices(array $treatment_ids, array $actual_prices) {
    foreach ($treatment_ids as $treatment_id) {
        if (($actual_prices[(int)$treatment_id] ?? 0) <= 0) return true;
    }
    return false;
}

// 병원 목록 조회
$hospitals = [];
$hospital_scope_sql = $my_in === null ? '' : " AND id IN ({$my_in})";
$h_r = mysqli_query($connect, "SELECT id, name FROM gangnamunni_hospitals WHERE is_active = 1{$hospital_scope_sql} ORDER BY name ASC");
if ($h_r) while ($h = mysqli_fetch_assoc($h_r)) $hospitals[] = $h;

// 선택된 병원
$selected_hospital = intval($_GET['hospital_id'] ?? $_POST['hospital_id'] ?? 0);
if ($my_ids !== null && !in_array($selected_hospital, $my_ids, true)) {
    $selected_hospital = $my_ids[0] ?? 0;
}
if ($selected_hospital === 0 && count($hospitals) > 0) {
    $selected_hospital = intval($hospitals[0]['id']);
}

$msg = '';
$msg_type = 'success';

// ===== POST 액션 처리 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = 'CSRF 토큰이 유효하지 않습니다. 페이지를 새로고침해주세요.';
        $msg_type = 'danger';
    } else {

        $known_actions = ['create_package', 'update_package', 'delete_package', 'toggle_active'];
        if (!in_array($action, $known_actions, true)) {
            http_response_code(400);
            die('지원하지 않는 요청입니다.');
        }

        $posted_hospital_id = intval($_POST['hospital_id'] ?? 0);
        $package_id = intval($_POST['id'] ?? 0);
        $action_hospital_id = $posted_hospital_id;
        if ($package_id > 0) {
            $owner_stmt = mysqli_prepare($connect, "SELECT hospital_id FROM gangnamunni_treatment_packages WHERE id = ? AND deleted_at IS NULL");
            if (!$owner_stmt) { http_response_code(500); die('패키지 조회에 실패했습니다.'); }
            mysqli_stmt_bind_param($owner_stmt, 'i', $package_id);
            mysqli_stmt_execute($owner_stmt);
            $owner_row = mysqli_fetch_assoc(mysqli_stmt_get_result($owner_stmt));
            mysqli_stmt_close($owner_stmt);
            if (!$owner_row) { http_response_code(404); die('패키지를 찾을 수 없습니다.'); }
            $action_hospital_id = (int)$owner_row['hospital_id'];
        }
        if ($my_ids !== null && !in_array($action_hospital_id, $my_ids, true)) {
            http_response_code(403);
            die('자신의 병원 패키지만 관리할 수 있습니다.');
        }
        if ($action_hospital_id > 0) $selected_hospital = $action_hospital_id;

        // 패키지 생성
        if ($action === 'create_package') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $hospital_id = intval($_POST['hospital_id'] ?? 0);
            $package_price = intval($_POST['package_price'] ?? 0);
            $treatment_ids = $_POST['treatment_ids'] ?? [];

            if ($name === '' || mb_strlen($name, 'UTF-8') > 200) {
                $msg = '패키지명을 입력해주세요.';
                $msg_type = 'danger';
            } elseif (mb_strlen($description, 'UTF-8') > 5000) {
                $msg = '패키지 설명은 5000자 이하로 입력해주세요.';
                $msg_type = 'danger';
            } elseif ($hospital_id <= 0) {
                $msg = '병원을 선택해주세요.';
                $msg_type = 'danger';
            } elseif ($package_price <= 0 || $package_price > 2147483647) {
                $msg = '패키지 가격을 입력해주세요.';
                $msg_type = 'danger';
            } elseif (empty($treatment_ids)) {
                $msg = '최소 1개 이상의 시술을 선택해주세요.';
                $msg_type = 'danger';
            } else {
                // 원가 계산: 마스터 평균가가 아닌 이 병원의 실제 활성 시술가 합산
                $ids_safe = package_treatment_ids_for_hospital($connect, $hospital_id, $treatment_ids);
                if (count($ids_safe) !== count(array_unique(array_filter(array_map('intval', $treatment_ids))))) {
                    http_response_code(400);
                    die('해당 병원에 활성 배정된 시술만 패키지에 추가할 수 있습니다.');
                }
                $actual_prices = package_actual_prices($connect, $hospital_id, $ids_safe);
                if (package_has_missing_prices($ids_safe, $actual_prices)) {
                    package_redirect($hospital_id, '가격이 설정되지 않은 시술은 패키지에 추가할 수 없습니다.', 'warning');
                }
                $original_price = array_sum($actual_prices);
                if ($original_price <= 0) package_redirect($hospital_id, '선택한 시술의 병원 가격을 먼저 설정해주세요.', 'warning');
                if ($package_price > $original_price) {
                    package_redirect($hospital_id, '패키지 가격은 선택 시술 합계(' . number_format($original_price) . '만원)보다 높게 설정할 수 없습니다.', 'warning');
                }

                mysqli_begin_transaction($connect);
                try {
                $stmt = mysqli_prepare($connect,
                    "INSERT INTO gangnamunni_treatment_packages (hospital_id, name, description, package_price, original_price, is_active)
                     VALUES (?, ?, ?, ?, ?, 1)"
                );
                if (!$stmt) throw new RuntimeException('패키지 등록 준비 실패');
                    mysqli_stmt_bind_param($stmt, 'issii', $hospital_id, $name, $description, $package_price, $original_price);
                    package_execute_or_throw($stmt, '패키지 등록 실패');
                        $pkg_id = mysqli_insert_id($connect);
                        // 패키지 항목 추가
                        $sort = 0;
                        $item_stmt = mysqli_prepare($connect,
                            "INSERT INTO gangnamunni_treatment_package_items (package_id, treatment_id, sort_order) VALUES (?, ?, ?)"
                        );
                        if (!$item_stmt) throw new RuntimeException('패키지 시술 저장 준비 실패');
                            foreach ($ids_safe as $tid) {
                                $sort++;
                                mysqli_stmt_bind_param($item_stmt, 'iii', $pkg_id, $tid, $sort);
                                package_execute_or_throw($item_stmt, '패키지 시술 저장 실패');
                            }
                            mysqli_stmt_close($item_stmt);
                        $selected_hospital = $hospital_id;
                        mysqli_commit($connect);
                        package_redirect($hospital_id, '패키지가 등록되었습니다.');
                } catch (Throwable $e) {
                    mysqli_rollback($connect);
                    error_log('[treatment_packages] create 실패: ' . $e->getMessage());
                    package_redirect($hospital_id, '패키지 등록에 실패했습니다. 변경사항은 저장되지 않았습니다.', 'danger');
                }
            }
        }

        // 패키지 수정
        if ($action === 'update_package') {
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $package_price = intval($_POST['package_price'] ?? 0);
            $treatment_ids = $_POST['treatment_ids'] ?? [];

            if ($id <= 0 || $name === '' || mb_strlen($name, 'UTF-8') > 200
                || mb_strlen($description, 'UTF-8') > 5000 || $package_price <= 0
                || $package_price > 2147483647 || empty($treatment_ids)) {
                $msg = '필수 항목을 모두 입력해주세요.';
                $msg_type = 'danger';
            } else {
                // 병원의 실제 활성 시술가로 원가 재계산
                $ids_safe = package_treatment_ids_for_hospital($connect, $action_hospital_id, $treatment_ids);
                if (count($ids_safe) !== count(array_unique(array_filter(array_map('intval', $treatment_ids))))) {
                    http_response_code(400);
                    die('해당 병원에 활성 배정된 시술만 패키지에 추가할 수 있습니다.');
                }
                $actual_prices = package_actual_prices($connect, $action_hospital_id, $ids_safe);
                if (package_has_missing_prices($ids_safe, $actual_prices)) {
                    package_redirect($action_hospital_id, '가격이 설정되지 않은 시술은 패키지에 추가할 수 없습니다.', 'warning');
                }
                $original_price = array_sum($actual_prices);
                if ($original_price <= 0) package_redirect($action_hospital_id, '선택한 시술의 병원 가격을 먼저 설정해주세요.', 'warning');
                if ($package_price > $original_price) {
                    package_redirect($action_hospital_id, '패키지 가격은 선택 시술 합계(' . number_format($original_price) . '만원)보다 높게 설정할 수 없습니다.', 'warning');
                }

                mysqli_begin_transaction($connect);
                try {
                $stmt = mysqli_prepare($connect,
                    "UPDATE gangnamunni_treatment_packages SET name = ?, description = ?, package_price = ?, original_price = ?, updated_at = NOW() WHERE id = ? AND hospital_id = ? AND deleted_at IS NULL"
                );
                if (!$stmt) throw new RuntimeException('패키지 수정 준비 실패');
                    mysqli_stmt_bind_param($stmt, 'ssiiii', $name, $description, $package_price, $original_price, $id, $action_hospital_id);
                    package_execute_or_throw($stmt, '패키지 수정 실패');
                        // 기존 항목 삭제 후 재삽입
                        $del_stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_treatment_package_items WHERE package_id = ?");
                        if (!$del_stmt) throw new RuntimeException('기존 패키지 시술 삭제 준비 실패');
                            mysqli_stmt_bind_param($del_stmt, 'i', $id);
                            package_execute_or_throw($del_stmt, '기존 패키지 시술 삭제 실패');
                            mysqli_stmt_close($del_stmt);
                        $sort = 0;
                        $item_stmt = mysqli_prepare($connect,
                            "INSERT INTO gangnamunni_treatment_package_items (package_id, treatment_id, sort_order) VALUES (?, ?, ?)"
                        );
                        if (!$item_stmt) throw new RuntimeException('패키지 시술 저장 준비 실패');
                            foreach ($ids_safe as $tid) {
                                $sort++;
                                mysqli_stmt_bind_param($item_stmt, 'iii', $id, $tid, $sort);
                                package_execute_or_throw($item_stmt, '패키지 시술 저장 실패');
                            }
                            mysqli_stmt_close($item_stmt);

                        // 병원 ID 가져오기 (리다이렉트용)
                        $pkg_r = mysqli_query($connect, "SELECT hospital_id FROM gangnamunni_treatment_packages WHERE id = $id");
                        $h_id = ($pkg_r && $r = mysqli_fetch_assoc($pkg_r)) ? intval($r['hospital_id']) : $selected_hospital;

                        mysqli_commit($connect);
                        package_redirect($h_id, '패키지가 수정되었습니다.');
                } catch (Throwable $e) {
                    mysqli_rollback($connect);
                    error_log('[treatment_packages] update 실패: ' . $e->getMessage());
                    package_redirect($action_hospital_id, '패키지 수정에 실패했습니다. 기존 내용은 유지됩니다.', 'danger');
                }
            }
        }

        // 패키지 삭제
        if ($action === 'delete_package') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                $del_pkg = mysqli_prepare($connect, "UPDATE gangnamunni_treatment_packages SET is_active = 0, deleted_at = NOW(), updated_at = NOW() WHERE id = ? AND hospital_id = ? AND deleted_at IS NULL");
                if (!$del_pkg) package_redirect($action_hospital_id, '패키지 삭제 준비에 실패했습니다.', 'danger');
                mysqli_stmt_bind_param($del_pkg, 'ii', $id, $action_hospital_id);
                if (!mysqli_stmt_execute($del_pkg)) package_redirect($action_hospital_id, '패키지 삭제에 실패했습니다.', 'danger');
                mysqli_stmt_close($del_pkg);
                package_redirect($selected_hospital, '패키지가 삭제되었습니다.');
            }
        }

        // 활성/비활성 토글
        if ($action === 'toggle_active') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_treatment_packages SET is_active = IF(is_active=1,0,1), updated_at = NOW() WHERE id = ? AND hospital_id = ? AND deleted_at IS NULL");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'ii', $id, $action_hospital_id);
                    if (mysqli_stmt_execute($stmt)) {
                        header("Location: admin_treatment_packages.php?hospital_id={$selected_hospital}&msg=" . urlencode('상태가 변경되었습니다.'));
                        exit;
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        }

    } // CSRF end
}

// GET 메시지 처리
if (isset($_GET['msg']) && $_GET['msg'] !== '') {
    $msg = $_GET['msg'];
    $allowed_msg_types = ['success', 'danger', 'warning', 'info'];
    $msg_type = in_array($_GET['msg_type'] ?? '', $allowed_msg_types, true) ? $_GET['msg_type'] : 'success';
}

// ===== 통계 조회 =====
$stat_total = 0;
$stat_active = 0;
$stat_avg_discount = 0;

if ($selected_hospital > 0) {
    $stat_r = mysqli_query($connect,
        "SELECT
            COUNT(*) AS total,
            SUM(is_active) AS active_count,
            AVG(CASE WHEN original_price > 0 THEN ROUND((original_price - package_price) / original_price * 100, 1) ELSE 0 END) AS avg_discount
         FROM gangnamunni_treatment_packages
         WHERE hospital_id = " . intval($selected_hospital) . " AND deleted_at IS NULL"
    );
    if ($stat_r && $s = mysqli_fetch_assoc($stat_r)) {
        $stat_total = intval($s['total']);
        $stat_active = intval($s['active_count']);
        $stat_avg_discount = round(floatval($s['avg_discount']), 1);
    }
}

// ===== 패키지 목록 조회 (선택된 병원) =====
$packages = [];
if ($selected_hospital > 0) {
    $pkg_r = mysqli_query($connect,
        "SELECT p.*, h.name AS hospital_name
         FROM gangnamunni_treatment_packages p
         LEFT JOIN gangnamunni_hospitals h ON h.id = p.hospital_id
         WHERE p.hospital_id = " . intval($selected_hospital) . " AND p.deleted_at IS NULL
         ORDER BY p.is_active DESC, p.created_at DESC"
    );
    if ($pkg_r) {
        while ($p = mysqli_fetch_assoc($pkg_r)) {
            // 패키지 항목 조회
            $p['items'] = [];
            $item_r = mysqli_query($connect,
                "SELECT pi.*, tm.name AS treatment_name, tc.name AS category_name,
                        COALESCE((
                            SELECT MIN(COALESCE(
                                (SELECT MIN(hp.price) FROM gangnamunni_hospital_treatment_prices hp WHERE hp.hospital_treatment_id = ht2.id AND hp.price > 0),
                                NULLIF(ht2.price_min, 0), NULLIF(ht2.price_max, 0)
                            ))
                            FROM gangnamunni_hospital_treatments ht2
                            WHERE ht2.hospital_id = " . intval($p['hospital_id']) . "
                              AND ht2.treatment_id = pi.treatment_id
                              AND ht2.status = 'active' AND ht2.is_active = 1
                        ), 0) AS actual_price
                 FROM gangnamunni_treatment_package_items pi
                 LEFT JOIN gangnamunni_treatment_master tm ON tm.id = pi.treatment_id
                 LEFT JOIN gangnamunni_treatment_categories tc ON tc.id = tm.category_id
                 WHERE pi.package_id = " . intval($p['id']) . "
                 ORDER BY pi.sort_order ASC"
            );
            if ($item_r) while ($it = mysqli_fetch_assoc($item_r)) $p['items'][] = $it;
            $packages[] = $p;
        }
    }
}

// ===== 시술 마스터 목록 (모달 체크박스용) =====
$treatments = [];
$cat_groups = []; // 카테고리별 그룹핑
$tm_stmt = mysqli_prepare($connect,
    "SELECT tm.id, tm.name, tm.category_id, tc.name AS category_name,
            MIN(COALESCE((SELECT MIN(hp.price) FROM gangnamunni_hospital_treatment_prices hp WHERE hp.hospital_treatment_id = ht.id AND hp.price > 0),
                         NULLIF(ht.price_min, 0), NULLIF(ht.price_max, 0), 0)) AS actual_price
     FROM gangnamunni_hospital_treatments ht
     JOIN gangnamunni_treatment_master tm ON tm.id = ht.treatment_id
     LEFT JOIN gangnamunni_treatment_categories tc ON tc.id = tm.category_id
     WHERE ht.hospital_id = ? AND ht.status = 'active' AND ht.is_active = 1 AND tm.is_active = 1
     GROUP BY tm.id, tm.name, tm.category_id, tc.name, tc.sort_order
     ORDER BY tc.sort_order ASC, tc.name ASC, tm.name ASC"
);
$tm_r = false;
if ($tm_stmt) {
    mysqli_stmt_bind_param($tm_stmt, 'i', $selected_hospital);
    mysqli_stmt_execute($tm_stmt);
    $tm_r = mysqli_stmt_get_result($tm_stmt);
}
if ($tm_r) {
    while ($t = mysqli_fetch_assoc($tm_r)) {
        $treatments[$t['id']] = $t;
        $cat_name = $t['category_name'] ?: '미분류';
        if (!isset($cat_groups[$cat_name])) $cat_groups[$cat_name] = [];
        $cat_groups[$cat_name][] = $t;
    }
}
if ($tm_stmt) mysqli_stmt_close($tm_stmt);

require_once __DIR__ . '/includes/admin_header.php';
?>

<!-- 알림 메시지 -->
<?php if ($msg): ?>
<div class="alert alert-<?= htmlspecialchars($msg_type) ?> alert-dismissible fade show" role="alert">
    <i class="bi bi-<?= $msg_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-1"></i>
    <?= htmlspecialchars($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- 페이지 제목 + 병원 선택 -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <h4 class="mb-2 mb-md-0"><i class="bi bi-box-seam me-2"></i>시술 패키지 관리</h4>
    <div class="d-flex align-items-center gap-2">
        <label class="form-label mb-0 fw-semibold text-nowrap">병원 선택:</label>
        <select id="hospitalSelect" class="form-select form-select-sm" style="min-width: 220px;"
                onchange="location.href='admin_treatment_packages.php?hospital_id='+this.value">
            <?php foreach ($hospitals as $h): ?>
            <option value="<?= $h['id'] ?>" <?= $h['id'] == $selected_hospital ? 'selected' : '' ?>>
                <?= htmlspecialchars($h['name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-primary btn-sm text-nowrap" data-bs-toggle="modal" data-bs-target="#packageModal" onclick="openCreateModal()">
            <i class="bi bi-plus-lg me-1"></i>새 패키지
        </button>
    </div>
</div>

<!-- 통계 카드 -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body text-center">
                <div class="text-muted small mb-1">전체 패키지</div>
                <div class="fs-3 fw-bold text-primary"><?= number_format($stat_total) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body text-center">
                <div class="text-muted small mb-1">활성 패키지</div>
                <div class="fs-3 fw-bold text-success"><?= number_format($stat_active) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body text-center">
                <div class="text-muted small mb-1">평균 할인율</div>
                <div class="fs-3 fw-bold text-danger"><?= $stat_avg_discount ?>%</div>
            </div>
        </div>
    </div>
</div>

<!-- 패키지 목록 -->
<?php if (empty($packages)): ?>
<div class="card shadow-sm">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-box-seam fs-1 d-block mb-3"></i>
        등록된 패키지가 없습니다. <strong>새 패키지</strong> 버튼을 눌러 추가해주세요.
    </div>
</div>
<?php else: ?>
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:50px">#</th>
                    <th>패키지명</th>
                    <th class="text-center">시술 수</th>
                    <th class="text-end">원가</th>
                    <th class="text-end">패키지가</th>
                    <th class="text-center">할인율</th>
                    <th class="text-center">상태</th>
                    <th class="text-center" style="width:160px">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($packages as $idx => $pkg):
                    $discount = $pkg['original_price'] > 0
                        ? round(($pkg['original_price'] - $pkg['package_price']) / $pkg['original_price'] * 100, 1)
                        : 0;
                ?>
                <tr>
                    <td class="text-muted"><?= $idx + 1 ?></td>
                    <td>
                        <div class="fw-semibold"><?= htmlspecialchars($pkg['name']) ?></div>
                        <?php if ($pkg['description']): ?>
                        <small class="text-muted"><?= mb_strimwidth(htmlspecialchars($pkg['description']), 0, 60, '...') ?></small>
                        <?php endif; ?>
                        <!-- 포함 시술 목록 (접이식) -->
                        <div class="mt-1">
                            <a class="small text-decoration-none" data-bs-toggle="collapse" href="#items_<?= $pkg['id'] ?>" role="button">
                                <i class="bi bi-chevron-down me-1"></i>포함 시술 보기
                            </a>
                            <div class="collapse mt-1" id="items_<?= $pkg['id'] ?>">
                                <?php foreach ($pkg['items'] as $item): ?>
                                <span class="badge bg-light text-dark border me-1 mb-1">
                                    <?= htmlspecialchars($item['treatment_name'] ?? '삭제된 시술') ?>
                                    <?php if ($item['actual_price'] > 0): ?>
                                    <small class="text-muted">(<?= number_format($item['actual_price']) ?>만원)</small>
                                    <?php endif; ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-secondary"><?= count($pkg['items']) ?>개</span>
                    </td>
                    <td class="text-end text-muted">
                        <?= $pkg['original_price'] > 0 ? number_format($pkg['original_price']) . '만원' : '-' ?>
                    </td>
                    <td class="text-end fw-bold">
                        <?= number_format($pkg['package_price']) ?>만원
                    </td>
                    <td class="text-center">
                        <?php if ($discount > 0): ?>
                        <span class="badge bg-danger"><?= $discount ?>%</span>
                        <?php else: ?>
                        <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <form method="post" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="hidden" name="action" value="toggle_active">
                            <input type="hidden" name="id" value="<?= $pkg['id'] ?>">
                            <input type="hidden" name="hospital_id" value="<?= $selected_hospital ?>">
                            <button type="submit" class="btn btn-sm btn-<?= $pkg['is_active'] ? 'success' : 'outline-secondary' ?>"
                                    title="<?= $pkg['is_active'] ? '활성' : '비활성' ?>">
                                <i class="bi bi-<?= $pkg['is_active'] ? 'check-circle-fill' : 'x-circle' ?>"></i>
                            </button>
                        </form>
                    </td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-primary me-1" title="수정"
                                onclick='openEditModal(<?= htmlspecialchars(
                                    json_encode($pkg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE),
                                    ENT_QUOTES | ENT_SUBSTITUTE,
                                    'UTF-8'
                                ) ?>)'>
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form method="post" class="d-inline" onsubmit="return confirm('이 패키지를 삭제하시겠습니까?')">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="hidden" name="action" value="delete_package">
                            <input type="hidden" name="id" value="<?= $pkg['id'] ?>">
                            <input type="hidden" name="hospital_id" value="<?= $selected_hospital ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="삭제">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- 패키지 생성/수정 모달 -->
<div class="modal fade" id="packageModal" tabindex="-1" aria-labelledby="packageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post" id="packageForm">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" id="formAction" value="create_package">
                <input type="hidden" name="id" id="formId" value="">
                <input type="hidden" name="hospital_id" value="<?= $selected_hospital ?>">

                <div class="modal-header">
                    <h5 class="modal-title" id="packageModalLabel">
                        <i class="bi bi-box-seam me-2"></i>새 패키지 등록
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- 패키지 기본 정보 -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">패키지명 <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="pkgName" class="form-control" required maxlength="200" placeholder="예: 윤곽 리프팅 풀패키지">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">설명</label>
                        <textarea name="description" id="pkgDesc" class="form-control" rows="2" maxlength="5000" placeholder="패키지 설명 (선택사항)"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">패키지 가격 (만원) <span class="text-danger">*</span></label>
                        <input type="number" name="package_price" id="pkgPrice" class="form-control" required min="1" max="2147483647" step="1" placeholder="예: 110">
                    </div>

                    <!-- 원가/할인율 표시 -->
                    <div class="alert alert-light border mb-3 d-flex justify-content-between align-items-center" id="discountInfo">
                        <span>선택 시술 합계: <strong id="totalOriginal">0</strong>만원</span>
                        <span>할인율: <strong id="discountRate" class="text-danger">0%</strong></span>
                    </div>
                    <div class="alert alert-warning d-none mb-3" id="priceWarning" role="alert">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        패키지 가격은 선택한 시술의 합계보다 높게 설정할 수 없습니다.
                    </div>

                    <!-- 시술 선택 (카테고리별 그룹) -->
                    <label class="form-label fw-semibold">포함 시술 선택 <span class="text-danger">*</span></label>
                    <div class="border rounded p-3" style="max-height: 350px; overflow-y: auto;">
                        <?php if (empty($cat_groups)): ?>
                        <p class="text-muted mb-0">등록된 시술이 없습니다.</p>
                        <?php else: ?>
                            <?php foreach ($cat_groups as $cat_name => $items): ?>
                            <div class="mb-3">
                                <div class="fw-semibold text-muted small mb-1">
                                    <i class="bi bi-tag me-1"></i><?= htmlspecialchars($cat_name) ?>
                                </div>
                                <?php foreach ($items as $t): ?>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input treatment-checkbox"
                                           name="treatment_ids[]" value="<?= $t['id'] ?>"
                                           id="treat_<?= $t['id'] ?>"
                                           data-price="<?= intval($t['actual_price']) ?>"
                                           <?= intval($t['actual_price']) <= 0 ? 'disabled' : '' ?>
                                           onchange="calcDiscount()">
                                    <label class="form-check-label" for="treat_<?= $t['id'] ?>">
                                        <?php if ($t['actual_price'] <= 0): ?>
                                        <span class="badge bg-warning text-dark me-1">가격 미설정</span>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($t['name']) ?>
                                        <?php if ($t['actual_price'] > 0): ?>
                                        <small class="text-muted">(<?= number_format($t['actual_price']) ?>만원)</small>
                                        <?php endif; ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="bi bi-check-lg me-1"></i>등록
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// 할인율 계산
function calcDiscount() {
    var checks = document.querySelectorAll('.treatment-checkbox:checked');
    var total = 0;
    checks.forEach(function(c) { total += parseInt(c.dataset.price) || 0; });
    document.getElementById('totalOriginal').textContent = total.toLocaleString();
    var price = parseInt(document.getElementById('pkgPrice').value) || 0;
    var rate = total > 0 ? Math.round((total - price) / total * 1000) / 10 : 0;
    document.getElementById('discountRate').textContent = (rate > 0 ? rate : 0) + '%';
    var invalidPrice = total > 0 && price > total;
    document.getElementById('priceWarning').classList.toggle('d-none', !invalidPrice);
    document.getElementById('pkgPrice').setCustomValidity(invalidPrice
        ? '패키지 가격은 선택 시술 합계보다 높을 수 없습니다.'
        : '');
}

// 패키지가 입력 시 할인율 재계산
document.getElementById('pkgPrice').addEventListener('input', calcDiscount);
document.getElementById('packageForm').addEventListener('submit', function(event) {
    var checked = document.querySelectorAll('.treatment-checkbox:checked');
    var total = 0;
    checked.forEach(function(c) { total += parseInt(c.dataset.price) || 0; });
    var price = parseInt(document.getElementById('pkgPrice').value) || 0;
    if (checked.length === 0) {
        event.preventDefault();
        alert('최소 1개 이상의 시술을 선택해주세요.');
    } else if (total <= 0) {
        event.preventDefault();
        alert('선택한 시술의 병원 가격을 먼저 설정해주세요.');
    } else if (price > total) {
        event.preventDefault();
        document.getElementById('pkgPrice').reportValidity();
    }
});

// 새 패키지 모달
function openCreateModal() {
    document.getElementById('packageModalLabel').innerHTML = '<i class="bi bi-box-seam me-2"></i>새 패키지 등록';
    document.getElementById('formAction').value = 'create_package';
    document.getElementById('formId').value = '';
    document.getElementById('pkgName').value = '';
    document.getElementById('pkgDesc').value = '';
    document.getElementById('pkgPrice').value = '';
    document.getElementById('submitBtn').innerHTML = '<i class="bi bi-check-lg me-1"></i>등록';
    // 체크박스 초기화
    document.querySelectorAll('.treatment-checkbox').forEach(function(c) { c.checked = false; });
    calcDiscount();
}

// 수정 모달
function openEditModal(pkg) {
    document.getElementById('packageModalLabel').innerHTML = '<i class="bi bi-pencil me-2"></i>패키지 수정';
    document.getElementById('formAction').value = 'update_package';
    document.getElementById('formId').value = pkg.id;
    document.getElementById('pkgName').value = pkg.name;
    document.getElementById('pkgDesc').value = pkg.description || '';
    document.getElementById('pkgPrice').value = pkg.package_price;
    document.getElementById('submitBtn').innerHTML = '<i class="bi bi-check-lg me-1"></i>수정';

    // 체크박스: 패키지 항목에 해당하는 것만 체크
    var itemIds = (pkg.items || []).map(function(it) { return parseInt(it.treatment_id); });
    document.querySelectorAll('.treatment-checkbox').forEach(function(c) {
        c.checked = !c.disabled && itemIds.indexOf(parseInt(c.value)) !== -1;
    });
    calcDiscount();

    var modal = new bootstrap.Modal(document.getElementById('packageModal'));
    modal.show();
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
