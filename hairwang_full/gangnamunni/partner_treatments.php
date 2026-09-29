<?php
/**
 * 병원 파트너 시술 관리 페이지 (partner_treatments.php)
 * - 병원 관리자가 자기 병원의 시술을 추가/수정/삭제
 * - 관리자(ad_admin_logged_in)는 ?hospital_id=X로 아무 병원 조회 가능
 * - 모바일 퍼스트 카드 레이아웃 (640px 컨테이너)
 *
 * @file    gangnamunni/partner_treatments.php
 * @created 2026-03-25
 */
session_start();

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/treatment_units.php';
require_once __DIR__ . '/includes/admin_auth.php';

// DB 연결
if (!isset($connect) || !$connect) {
    $connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    if ($connect) mysqli_set_charset($connect, 'utf8mb4');
}
if (!$connect) die('DB 연결 실패');

loadThemeSettings($connect);
$price_units = treatment_units_list($connect);

// 관리자 여부 판별: 전체 병원 범위를 가진 플랫폼 관리자(최고관리자 포함)만 관리자 권한을 갖는다.
// 병원 계정(및 병원이 배정된 관리자)은 항상 자기 병원으로 고정된다.
$scoped_hospital_id = admin_scoped_hospital_id();
$is_admin = check_admin_login() && $scoped_hospital_id === null;

// 병원 ID 결정: 전체 범위 관리자는 GET 파라미터 허용, 그 외는 배정된 병원만
$hospital_id = 0;
if ($is_admin && isset($_GET['hospital_id']) && intval($_GET['hospital_id']) > 0) {
    $hospital_id = intval($_GET['hospital_id']);
} elseif ($scoped_hospital_id !== null && $scoped_hospital_id > 0) {
    $hospital_id = $scoped_hospital_id;
} elseif ($is_admin) {
    $hospital_id = intval($_SESSION['ad_admin_hospital_id'] ?? 0);
}

if (!$hospital_id) {
    $return_page = basename($_SERVER['PHP_SELF'] ?? 'partner_treatments.php');
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    if ($qs !== '') $return_page .= '?' . $qs;
    header('Location: dashboard.php?return=' . urlencode($return_page));
    exit;
}

// 병원 정보 조회
$hospital_name = '';
$stmt_h = mysqli_prepare($connect, "SELECT id, name FROM gangnamunni_hospitals WHERE id = ? AND is_active = 1");
mysqli_stmt_bind_param($stmt_h, 'i', $hospital_id);
mysqli_stmt_execute($stmt_h);
$h_result = mysqli_stmt_get_result($stmt_h);
$hospital_row = mysqli_fetch_assoc($h_result);
mysqli_stmt_close($stmt_h);

if (!$hospital_row) {
    header('Location: dashboard.php');
    exit;
}
$hospital_name = $hospital_row['name'];

// CSRF 토큰
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// 설정 조회
$treatment_mgmt_mode = 'both';
$treatment_max = 50;

$set_r = mysqli_query($connect, "SELECT `key`, `value` FROM settings WHERE `key` IN ('treatment_mgmt_mode','treatment_max_per_hospital')");
if ($set_r) {
    while ($s = mysqli_fetch_assoc($set_r)) {
        if ($s['key'] === 'treatment_mgmt_mode') $treatment_mgmt_mode = $s['value'];
        if ($s['key'] === 'treatment_max_per_hospital') $treatment_max = intval($s['value']);
    }
}
if ($treatment_max <= 0) $treatment_max = 50;

// 편집 가능 여부
$can_edit = ($treatment_mgmt_mode === 'hospital_only' || $treatment_mgmt_mode === 'both');
// 관리자는 항상 편집 가능
if ($is_admin) $can_edit = true;

// 기존 승인 대기 데이터도 새 정책에 맞춰 즉시 활성화한다.
$legacy_pending = mysqli_prepare($connect, "UPDATE gangnamunni_hospital_treatments SET status = 'active', is_active = 1, reject_reason = NULL, updated_at = NOW() WHERE hospital_id = ? AND status = 'pending'");
if ($legacy_pending) {
    mysqli_stmt_bind_param($legacy_pending, 'i', $hospital_id);
    mysqli_stmt_execute($legacy_pending);
    mysqli_stmt_close($legacy_pending);
}

// 현재 병원 시술 수
$count_stmt = mysqli_prepare($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_hospital_treatments WHERE hospital_id = ? AND is_active = 1");
mysqli_stmt_bind_param($count_stmt, 'i', $hospital_id);
mysqli_stmt_execute($count_stmt);
$count_row = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt));
$current_count = intval($count_row['cnt'] ?? 0);
mysqli_stmt_close($count_stmt);

// ===== POST 처리 =====
$flash_msg = '';
$flash_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_edit) {
    $post_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $post_token)) {
        $flash_msg = '보안 토큰이 유효하지 않습니다. 페이지를 새로고침해주세요.';
        $flash_type = 'error';
    } else {
        $action = $_POST['action'] ?? '';

        // --- 시술 추가 ---
        if ($action === 'add_treatments') {
            $treatment_ids = $_POST['treatment_ids'] ?? [];
            if (empty($treatment_ids)) {
                $flash_msg = '추가할 시술을 선택해주세요.';
                $flash_type = 'warning';
            } else {
                $added = 0;
                $skipped = 0;
                foreach ($treatment_ids as $tid) {
                    $tid = intval($tid);
                    if ($tid <= 0) continue;

                    // 최대 개수 체크
                    if (($current_count + $added) >= $treatment_max) {
                        $flash_msg = "최대 {$treatment_max}개까지 등록 가능합니다. {$added}개 추가됨.";
                        $flash_type = 'warning';
                        break;
                    }

                    // 중복 체크
                    $dup_stmt = mysqli_prepare($connect, "SELECT id FROM gangnamunni_hospital_treatments WHERE hospital_id = ? AND treatment_id = ?");
                    mysqli_stmt_bind_param($dup_stmt, 'ii', $hospital_id, $tid);
                    mysqli_stmt_execute($dup_stmt);
                    $dup_r = mysqli_stmt_get_result($dup_stmt);
                    if (mysqli_fetch_assoc($dup_r)) {
                        $skipped++;
                        mysqli_stmt_close($dup_stmt);
                        continue;
                    }
                    mysqli_stmt_close($dup_stmt);

                    // 마스터에서 기본 가격 조회
                    $price_stmt = mysqli_prepare($connect, "SELECT avg_price_min, avg_price_max FROM gangnamunni_treatment_master WHERE id = ?");
                    mysqli_stmt_bind_param($price_stmt, 'i', $tid);
                    mysqli_stmt_execute($price_stmt);
                    $price_r = mysqli_stmt_get_result($price_stmt);
                    $price_row = mysqli_fetch_assoc($price_r);
                    $p_min = intval($price_row['avg_price_min'] ?? 0);
                    $p_max = intval($price_row['avg_price_max'] ?? 0);
                    mysqli_stmt_close($price_stmt);

                    $status = 'active';
                    $registered_by = $is_admin ? 'admin' : 'hospital';

                    $ins = mysqli_prepare($connect,
                        "INSERT INTO gangnamunni_hospital_treatments (hospital_id, treatment_id, price_min, price_max, is_promoted, note, status, registered_by, is_active, created_at, updated_at)
                         VALUES (?, ?, ?, ?, 0, '', ?, ?, 1, NOW(), NOW())");
                    mysqli_stmt_bind_param($ins, 'iiiiss', $hospital_id, $tid, $p_min, $p_max, $status, $registered_by);
                    if (mysqli_stmt_execute($ins)) {
                        $added++;
                    }
                    mysqli_stmt_close($ins);
                }

                if (empty($flash_msg)) {
                    $flash_msg = "{$added}개 시술이 추가되었습니다.";
                    if ($skipped > 0) $flash_msg .= " (중복 {$skipped}개 건너뜀)";
                }
                // 카운트 갱신
                $current_count += $added;
            }
        }

        // --- 가격 수정 ---
        if ($action === 'update_price') {
            $ht_id = intval($_POST['ht_id'] ?? 0);
            $new_min = intval($_POST['price_min'] ?? 0);
            $new_max = intval($_POST['price_max'] ?? 0);
            if ($new_min < 0) $new_min = 0;
            if ($new_max < 0) $new_max = 0;
            if ($new_max > 0 && $new_max < $new_min) $new_max = $new_min;
            $new_dosage_value = trim($_POST['dosage_value'] ?? '') !== '' ? floatval($_POST['dosage_value']) : null;
            $new_dosage_unit = trim($_POST['dosage_unit'] ?? '') !== '' ? trim($_POST['dosage_unit']) : null;

            // 해당 병원 소유 확인
            $own_stmt = mysqli_prepare($connect, "SELECT id FROM gangnamunni_hospital_treatments WHERE id = ? AND hospital_id = ?");
            mysqli_stmt_bind_param($own_stmt, 'ii', $ht_id, $hospital_id);
            mysqli_stmt_execute($own_stmt);
            $own_r = mysqli_stmt_get_result($own_stmt);
            if (mysqli_fetch_assoc($own_r)) {
                $upd = mysqli_prepare($connect, "UPDATE gangnamunni_hospital_treatments SET price_min = ?, price_max = ?, dosage_value = ?, dosage_unit = ?, updated_at = NOW() WHERE id = ?");
                mysqli_stmt_bind_param($upd, 'iidsi', $new_min, $new_max, $new_dosage_value, $new_dosage_unit, $ht_id);
                mysqli_stmt_execute($upd);
                mysqli_stmt_close($upd);
                $flash_msg = '가격이 수정되었습니다.';
            } else {
                $flash_msg = '권한이 없습니다.';
                $flash_type = 'error';
            }
            mysqli_stmt_close($own_stmt);
        }

        // --- 시술 삭제 ---
        if ($action === 'remove') {
            $ht_id = intval($_POST['ht_id'] ?? 0);
            $own_stmt = mysqli_prepare($connect, "SELECT id FROM gangnamunni_hospital_treatments WHERE id = ? AND hospital_id = ?");
            mysqli_stmt_bind_param($own_stmt, 'ii', $ht_id, $hospital_id);
            mysqli_stmt_execute($own_stmt);
            $own_r = mysqli_stmt_get_result($own_stmt);
            if (mysqli_fetch_assoc($own_r)) {
                $del = mysqli_prepare($connect, "DELETE FROM gangnamunni_hospital_treatments WHERE id = ?");
                mysqli_stmt_bind_param($del, 'i', $ht_id);
                mysqli_stmt_execute($del);
                mysqli_stmt_close($del);
                $current_count = max(0, $current_count - 1);
                $flash_msg = '시술이 삭제되었습니다.';
            } else {
                $flash_msg = '권한이 없습니다.';
                $flash_type = 'error';
            }
            mysqli_stmt_close($own_stmt);
        }
    }
}

// ===== 데이터 조회 =====

// 카테고리 목록
$categories = [];
$cat_r = mysqli_query($connect, "SELECT * FROM gangnamunni_treatment_categories WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
if ($cat_r) while ($c = mysqli_fetch_assoc($cat_r)) $categories[$c['id']] = $c;

// 내 병원 시술 목록 (active)
$my_treatments = [];
$my_stmt = mysqli_prepare($connect,
    "SELECT ht.*, tm.name as treatment_name, tm.hashtag, tc.name as category_name, tc.id as cat_id
     FROM gangnamunni_hospital_treatments ht
     JOIN gangnamunni_treatment_master tm ON ht.treatment_id = tm.id
     LEFT JOIN gangnamunni_treatment_categories tc ON tm.category_id = tc.id
     WHERE ht.hospital_id = ? AND ht.status = 'active' AND ht.is_active = 1
     ORDER BY tc.sort_order ASC, tm.name ASC");
mysqli_stmt_bind_param($my_stmt, 'i', $hospital_id);
mysqli_stmt_execute($my_stmt);
$my_r = mysqli_stmt_get_result($my_stmt);
while ($row = mysqli_fetch_assoc($my_r)) $my_treatments[] = $row;
mysqli_stmt_close($my_stmt);

// 대기/거절 시술
$pending_treatments = [];
$pen_stmt = mysqli_prepare($connect,
    "SELECT ht.*, tm.name as treatment_name, tm.hashtag, tc.name as category_name
     FROM gangnamunni_hospital_treatments ht
     JOIN gangnamunni_treatment_master tm ON ht.treatment_id = tm.id
     LEFT JOIN gangnamunni_treatment_categories tc ON tm.category_id = tc.id
     WHERE ht.hospital_id = ? AND ht.status IN ('pending','rejected') AND ht.is_active = 1
     ORDER BY ht.created_at DESC");
mysqli_stmt_bind_param($pen_stmt, 'i', $hospital_id);
mysqli_stmt_execute($pen_stmt);
$pen_r = mysqli_stmt_get_result($pen_stmt);
while ($row = mysqli_fetch_assoc($pen_r)) $pending_treatments[] = $row;
mysqli_stmt_close($pen_stmt);

// 이미 배정된 시술 ID 목록 (추가 시 제외용)
$assigned_ids = [];
$aid_stmt = mysqli_prepare($connect, "SELECT treatment_id FROM gangnamunni_hospital_treatments WHERE hospital_id = ?");
mysqli_stmt_bind_param($aid_stmt, 'i', $hospital_id);
mysqli_stmt_execute($aid_stmt);
$aid_r = mysqli_stmt_get_result($aid_stmt);
while ($a = mysqli_fetch_assoc($aid_r)) $assigned_ids[] = intval($a['treatment_id']);
mysqli_stmt_close($aid_stmt);

// 추가 가능한 시술 목록 (마스터에서 미배정 시술)
$available_treatments = [];
$avail_q = "SELECT tm.*, tc.name as category_name, tc.id as cat_id
            FROM gangnamunni_treatment_master tm
            LEFT JOIN gangnamunni_treatment_categories tc ON tm.category_id = tc.id
            WHERE tm.is_active = 1
            ORDER BY tc.sort_order ASC, tm.name ASC";
$avail_r = mysqli_query($connect, $avail_q);
if ($avail_r) {
    while ($row = mysqli_fetch_assoc($avail_r)) {
        if (!in_array(intval($row['id']), $assigned_ids)) {
            $available_treatments[] = $row;
        }
    }
}

// 카테고리별 그룹핑 (추가 모달용)
$avail_by_cat = [];
foreach ($available_treatments as $at) {
    $cid = intval($at['cat_id'] ?? 0);
    $cname = $at['category_name'] ?? '미분류';
    if (!isset($avail_by_cat[$cid])) $avail_by_cat[$cid] = ['name' => $cname, 'items' => []];
    $avail_by_cat[$cid]['items'][] = $at;
}

// 탭 선택
$tab = $_GET['tab'] ?? 'my';
if (!in_array($tab, ['my', 'add'], true)) $tab = 'my';

// 페이지 설정
$page_title = '내 병원 시술 관리';
$show_search = false;

// 헤더 include
include __DIR__ . '/includes/header.php';
?>

<style>
/* 파트너 시술 관리 전용 스타일 */
.pt-container { max-width: 640px; margin: 0 auto; padding: 0 16px 100px; }
.pt-hospital-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 14px; background: #FFF0F0; color: #AE1932;
    border-radius: 20px; font-size: 13px; font-weight: 600;
    margin-bottom: 12px;
}
.pt-hospital-badge i { font-size: 15px; }
.pt-counter {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 14px; background: #f8f9fa; border-radius: 10px;
    margin-bottom: 16px; font-size: 13px; color: #555;
}
.pt-counter .count { font-weight: 700; color: var(--bs-body-color, #333); }
.pt-counter .limit-warn { color: #e53935; font-weight: 600; }
.pt-tabs {
    display: flex; gap: 0; border-bottom: 2px solid var(--bs-border-color, #eee);
    margin-bottom: 16px; position: sticky; top: 0; background: var(--bs-body-bg, #fff); z-index: 10;
}
.pt-tab {
    flex: 1; text-align: center; padding: 12px 0; font-size: 14px;
    font-weight: 500; color: #999; text-decoration: none;
    border-bottom: 2px solid transparent; margin-bottom: -2px;
    transition: all 0.2s;
}
.pt-tab.active { color: #AE1932; border-bottom-color: #AE1932; font-weight: 700; }
.pt-tab .badge-count {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 18px; height: 18px; padding: 0 5px;
    background: #eee; color: var(--bs-secondary-color, #666); border-radius: 9px;
    font-size: 11px; font-weight: 600; margin-left: 4px;
}
.pt-tab.active .badge-count { background: #AE1932; color: #fff; }

/* 시술 카드 */
.pt-card {
    background: var(--bs-body-bg, #fff); border: 1px solid var(--bs-border-color, #f0f0f0); border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.pt-card-header {
    display: flex; align-items: flex-start; justify-content: space-between;
    margin-bottom: 8px;
}
.pt-card-title { font-size: 15px; font-weight: 600; color: #222; }
.pt-card-hashtag {
    font-size: 12px; color: #AE1932; background: #FFF0F0;
    padding: 2px 8px; border-radius: 10px; white-space: nowrap;
}
.pt-card-meta {
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    font-size: 12px; color: #888;
}
.pt-card-category {
    background: #f0f0f0; padding: 2px 8px; border-radius: 8px;
    font-size: 11px; color: var(--bs-secondary-color, #666);
}
.pt-card-price { font-size: 13px; color: var(--bs-body-color, #333); font-weight: 600; }
.pt-card-actions {
    display: flex; gap: 8px; margin-top: 10px; padding-top: 10px;
    border-top: 1px solid #f5f5f5;
}
.pt-btn {
    padding: 7px 14px; border-radius: 8px; font-size: 12px;
    font-weight: 600; cursor: pointer; border: none; transition: all 0.15s;
}
.pt-btn-edit { background: #f0f0f0; color: var(--bs-body-color, #333); }
.pt-btn-edit:hover { background: #e0e0e0; }
.pt-btn-del { background: var(--bs-body-bg, #fff); color: #e53935; border: 1px solid #ffcdd2; }
.pt-btn-del:hover { background: #ffebee; }
.pt-btn-primary { background: #AE1932; color: #fff; }
.pt-btn-primary:hover { background: #B71C1C; }
.pt-btn-primary:disabled { background: #ccc; color: #999; cursor: not-allowed; }

/* 상태 배지 */
.pt-status-pending {
    display: inline-block; padding: 3px 10px; border-radius: 10px;
    background: #FFF3E0; color: #E65100; font-size: 11px; font-weight: 600;
}
.pt-status-rejected {
    display: inline-block; padding: 3px 10px; border-radius: 10px;
    background: #FFEBEE; color: #C62828; font-size: 11px; font-weight: 600;
}
.pt-reject-reason {
    margin-top: 6px; padding: 8px 10px; background: #FFF8E1;
    border-radius: 8px; font-size: 12px; color: #795548;
    border-left: 3px solid #FFB300;
}
.pt-promoted {
    display: inline-block; padding: 2px 8px; border-radius: 8px;
    background: #E8F5E9; color: #2E7D32; font-size: 11px; font-weight: 600;
}

/* 추가 탭: 카테고리 필터 */
.pt-cat-filters {
    display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 14px;
    padding-bottom: 10px; border-bottom: 1px solid var(--bs-border-color, #f0f0f0);
}
.pt-cat-btn {
    padding: 6px 14px; border-radius: 20px; font-size: 12px;
    font-weight: 500; background: var(--bs-tertiary-bg, #f5f5f5); color: var(--bs-secondary-color, #666);
    border: none; cursor: pointer; transition: all 0.15s;
}
.pt-cat-btn.active { background: #AE1932; color: #fff; }

/* 체크박스 그리드 */
.pt-check-grid { display: flex; flex-direction: column; gap: 8px; }
.pt-check-item {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px; background: #fafafa; border-radius: 10px;
    border: 1px solid var(--bs-border-color, #f0f0f0); cursor: pointer; transition: all 0.15s;
}
.pt-check-item:hover { border-color: #AE1932; background: #FFF8F8; }
.pt-check-item input[type="checkbox"] { accent-color: #AE1932; width: 18px; height: 18px; flex-shrink: 0; }
.pt-check-info { flex: 1; }
.pt-check-name { font-size: 14px; font-weight: 500; color: var(--bs-body-color, #333); }
.pt-check-sub { font-size: 11px; color: #999; margin-top: 2px; }

/* 인라인 가격 편집 */
.pt-price-form {
    display: flex; align-items: center; gap: 6px; margin-top: 8px;
}
.pt-price-input {
    width: 90px; padding: 6px 8px; border: 1px solid #ddd;
    border-radius: 6px; font-size: 13px; text-align: right;
}
.pt-price-input:focus { border-color: #AE1932; outline: none; }

/* 플래시 메시지 */
.pt-flash {
    padding: 10px 14px; border-radius: 10px; font-size: 13px;
    font-weight: 500; margin-bottom: 14px;
}
.pt-flash-success { background: #E8F5E9; color: #2E7D32; }
.pt-flash-error { background: #FFEBEE; color: #C62828; }
.pt-flash-warning { background: #FFF3E0; color: #E65100; }

/* 읽기 전용 안내 */
.pt-readonly-notice {
    padding: 16px; background: #FFF8E1; border-radius: 12px;
    text-align: center; color: #795548; font-size: 14px;
    margin-bottom: 16px; border: 1px solid #FFE082;
}
.pt-readonly-notice i { font-size: 24px; display: block; margin-bottom: 8px; color: #FFB300; }

/* 빈 상태 */
.pt-empty {
    text-align: center; padding: 40px 16px; color: #999;
}
.pt-empty i { font-size: 40px; display: block; margin-bottom: 12px; color: #ddd; }
.pt-empty p { font-size: 14px; }

/* 선택 카운터 바 (하단 고정) */
.pt-select-bar {
    position: fixed; bottom: 70px; left: 50%; transform: translateX(-50%);
    width: calc(100% - 32px); max-width: 608px;
    background: #AE1932; color: #fff; border-radius: 12px;
    padding: 12px 16px; display: none; align-items: center;
    justify-content: space-between; box-shadow: 0 4px 16px rgba(211,47,47,0.3);
    z-index: 50;
}
.pt-select-bar.show { display: flex; }
.pt-select-count { font-size: 14px; font-weight: 600; }
.pt-select-submit {
    padding: 8px 20px; background: var(--bs-body-bg, #fff); color: #AE1932;
    border: none; border-radius: 8px; font-size: 13px;
    font-weight: 700; cursor: pointer;
}
</style>

<div class="pt-container">

    <!-- 병원 이름 배지 -->
    <div style="margin-top:16px;">
        <span class="pt-hospital-badge">
            <i class="bi bi-building"></i>
            <?php echo htmlspecialchars($hospital_name); ?>
        </span>
        <?php if ($is_admin): ?>
            <span style="font-size:11px;color:#999;margin-left:6px;">관리자 모드</span>
        <?php endif; ?>
    </div>

    <!-- 플래시 메시지 -->
    <?php if (!empty($flash_msg)): ?>
    <div class="pt-flash pt-flash-<?php echo $flash_type; ?>">
        <?php echo htmlspecialchars($flash_msg); ?>
    </div>
    <?php endif; ?>

    <!-- 읽기 전용 안내 (admin_only 모드) -->
    <?php if (!$can_edit): ?>
    <div class="pt-readonly-notice">
        <i class="bi bi-lock"></i>
        관리자만 시술을 관리할 수 있습니다.<br>
        <span style="font-size:12px;color:#a1887f;">시술 추가/수정이 필요하시면 관리자에게 문의해주세요.</span>
    </div>
    <?php endif; ?>

    <!-- 시술 등록 현황 카운터 -->
    <div class="pt-counter">
        <span>등록 시술 <span class="count"><?php echo $current_count; ?></span> / <?php echo $treatment_max; ?>개</span>
        <?php if ($current_count >= $treatment_max): ?>
            <span class="limit-warn">최대 한도 도달</span>
        <?php elseif ($current_count >= ($treatment_max * 0.8)): ?>
            <span class="limit-warn">잔여 <?php echo ($treatment_max - $current_count); ?>개</span>
        <?php endif; ?>
    </div>

    <!-- 탭 네비게이션 -->
    <div class="pt-tabs">
        <a href="?hospital_id=<?php echo $hospital_id; ?>&tab=my" class="pt-tab <?php echo $tab === 'my' ? 'active' : ''; ?>">
            내 시술
            <span class="badge-count"><?php echo count($my_treatments); ?></span>
        </a>
        <?php if ($can_edit): ?>
        <a href="?hospital_id=<?php echo $hospital_id; ?>&tab=add" class="pt-tab <?php echo $tab === 'add' ? 'active' : ''; ?>">
            시술 추가
        </a>
        <?php endif; ?>
    </div>

    <!-- ============ 탭 1: 내 시술 목록 ============ -->
    <?php if ($tab === 'my'): ?>
        <?php if (empty($my_treatments)): ?>
            <div class="pt-empty">
                <i class="bi bi-clipboard-pulse"></i>
                <p>등록된 시술이 없습니다.</p>
                <?php if ($can_edit): ?>
                <a href="?hospital_id=<?php echo $hospital_id; ?>&tab=add" class="pt-btn pt-btn-primary" style="display:inline-block;margin-top:10px;text-decoration:none;">시술 추가하기</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($my_treatments as $t): ?>
            <div class="pt-card">
                <div class="pt-card-header">
                    <div>
                        <div class="pt-card-title"><?php echo htmlspecialchars($t['treatment_name']); ?></div>
                        <div class="pt-card-meta">
                            <span class="pt-card-category"><?php echo htmlspecialchars($t['category_name'] ?? '미분류'); ?></span>
                            <?php if (!empty($t['is_promoted'])): ?>
                                <span class="pt-promoted">프로모션</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($t['hashtag'])): ?>
                        <span class="pt-card-hashtag">#<?php echo htmlspecialchars($t['hashtag']); ?></span>
                    <?php endif; ?>
                </div>

                <div class="pt-card-price">
                    <?php
                    $pmin = intval($t['price_min']);
                    $pmax = intval($t['price_max']);
                    if ($pmin > 0 && $pmax > 0 && $pmax > $pmin) {
                        echo number_format($pmin) . '원 ~ ' . number_format($pmax) . '원';
                    } elseif ($pmin > 0) {
                        echo number_format($pmin) . '원~';
                    } elseif ($pmax > 0) {
                        echo '~' . number_format($pmax) . '원';
                    } else {
                        echo '<span style="color:#bbb;">가격 미설정</span>';
                    }
                    ?>
                    <?php if ($t['dosage_value'] !== null && $t['dosage_unit']): ?>
                    <span style="margin-left:6px;padding:2px 8px;background:#f1f1f1;border-radius:10px;font-size:12px;color:#555;">
                        <?php echo rtrim(rtrim(number_format((float)$t['dosage_value'], 2), '0'), '.') . htmlspecialchars($t['dosage_unit']); ?>
                    </span>
                    <?php endif; ?>
                </div>

                <?php if ($can_edit): ?>
                <div class="pt-card-actions">
                    <button class="pt-btn pt-btn-edit" onclick="togglePriceEdit(<?php echo intval($t['id']); ?>)">
                        <i class="bi bi-pencil"></i> 가격 수정
                    </button>
                    <form method="post" style="display:inline;" onsubmit="return confirm('이 시술을 삭제하시겠습니까?');">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="ht_id" value="<?php echo intval($t['id']); ?>">
                        <button type="submit" class="pt-btn pt-btn-del"><i class="bi bi-trash3"></i> 삭제</button>
                    </form>
                </div>

                <!-- 인라인 가격 편집 폼 (기본 숨김) -->
                <form method="post" id="price-form-<?php echo intval($t['id']); ?>" style="display:none;">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="update_price">
                    <input type="hidden" name="ht_id" value="<?php echo intval($t['id']); ?>">
                    <div class="pt-price-form">
                        <input type="number" name="price_min" class="pt-price-input" value="<?php echo $pmin; ?>" placeholder="최소" min="0">
                        <span style="color:#999;">~</span>
                        <input type="number" name="price_max" class="pt-price-input" value="<?php echo $pmax; ?>" placeholder="최대" min="0">
                        <span style="font-size:12px;color:#999;">원</span>
                        <input type="number" name="dosage_value" class="pt-price-input" style="width:70px;" value="<?php echo $t['dosage_value'] !== null ? htmlspecialchars(rtrim(rtrim(number_format((float)$t['dosage_value'], 2), '0'), '.')) : ''; ?>" placeholder="샷수/용량" min="0" step="0.01">
                        <select name="dosage_unit" class="pt-price-input" style="width:80px;">
                            <option value="">단위없음</option>
                            <?php foreach (treatment_units_with_current($price_units, $t['dosage_unit'] ?? '') as $unit): ?>
                            <option value="<?= htmlspecialchars($unit, ENT_QUOTES, 'UTF-8') ?>" <?= ($t['dosage_unit'] ?? '') === $unit ? 'selected' : '' ?>><?= htmlspecialchars($unit, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="pt-btn pt-btn-primary" style="padding:6px 12px;">저장</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

    <!-- ============ 탭 2: 승인 대기/거절 ============ -->
    <?php elseif ($tab === 'pending'): ?>
        <?php if (empty($pending_treatments)): ?>
            <div class="pt-empty">
                <i class="bi bi-hourglass"></i>
                <p>대기 중인 시술이 없습니다.</p>
            </div>
        <?php else: ?>
            <?php foreach ($pending_treatments as $t): ?>
            <div class="pt-card">
                <div class="pt-card-header">
                    <div>
                        <div class="pt-card-title"><?php echo htmlspecialchars($t['treatment_name']); ?></div>
                        <div class="pt-card-meta">
                            <span class="pt-card-category"><?php echo htmlspecialchars($t['category_name'] ?? '미분류'); ?></span>
                            <?php if ($t['status'] === 'pending'): ?>
                                <span class="pt-status-pending">승인 대기</span>
                            <?php elseif ($t['status'] === 'rejected'): ?>
                                <span class="pt-status-rejected">거절됨</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($t['hashtag'])): ?>
                        <span class="pt-card-hashtag">#<?php echo htmlspecialchars($t['hashtag']); ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($t['status'] === 'rejected' && !empty($t['reject_reason'])): ?>
                <div class="pt-reject-reason">
                    <strong>거절 사유:</strong> <?php echo htmlspecialchars($t['reject_reason']); ?>
                </div>
                <?php endif; ?>

                <?php if ($can_edit): ?>
                <div class="pt-card-actions">
                    <form method="post" style="display:inline;" onsubmit="return confirm('이 요청을 취소하시겠습니까?');">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="ht_id" value="<?php echo intval($t['id']); ?>">
                        <button type="submit" class="pt-btn pt-btn-del"><i class="bi bi-x-circle"></i> 요청 취소</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

    <!-- ============ 탭 3: 시술 추가 ============ -->
    <?php elseif ($tab === 'add' && $can_edit): ?>
        <?php if ($current_count >= $treatment_max): ?>
            <div class="pt-readonly-notice">
                <i class="bi bi-exclamation-triangle"></i>
                최대 등록 한도(<?php echo $treatment_max; ?>개)에 도달했습니다.<br>
                <span style="font-size:12px;">기존 시술을 삭제한 후 추가해주세요.</span>
            </div>
        <?php elseif (empty($available_treatments)): ?>
            <div class="pt-empty">
                <i class="bi bi-check-circle"></i>
                <p>추가 가능한 시술이 없습니다.<br><span style="font-size:12px;color:#bbb;">모든 시술이 이미 등록되어 있습니다.</span></p>
            </div>
        <?php else: ?>
            <!-- 카테고리 필터 탭 -->
            <div class="pt-cat-filters">
                <button class="pt-cat-btn active" onclick="filterCategory(0, this)">전체 (<?php echo count($available_treatments); ?>)</button>
                <?php foreach ($avail_by_cat as $cid => $cdata): ?>
                <button class="pt-cat-btn" onclick="filterCategory(<?php echo $cid; ?>, this)">
                    <?php echo htmlspecialchars($cdata['name']); ?> (<?php echo count($cdata['items']); ?>)
                </button>
                <?php endforeach; ?>
            </div>

            <form method="post" id="addForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="add_treatments">

                <div class="pt-check-grid">
                    <?php foreach ($available_treatments as $at): ?>
                    <label class="pt-check-item" data-cat="<?php echo intval($at['cat_id'] ?? 0); ?>">
                        <input type="checkbox" name="treatment_ids[]" value="<?php echo intval($at['id']); ?>" onchange="updateSelectBar()">
                        <div class="pt-check-info">
                            <div class="pt-check-name">
                                <?php echo htmlspecialchars($at['name']); ?>
                                <?php if (!empty($at['hashtag'])): ?>
                                    <span style="color:#AE1932;font-size:11px;">#<?php echo htmlspecialchars($at['hashtag']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="pt-check-sub">
                                <?php echo htmlspecialchars($at['category_name'] ?? '미분류'); ?>
                                <?php
                                $am = intval($at['avg_price_min'] ?? 0);
                                $ax = intval($at['avg_price_max'] ?? 0);
                                if ($am > 0 || $ax > 0) {
                                    echo ' &middot; 평균 ';
                                    if ($am > 0 && $ax > 0) echo number_format($am) . '~' . number_format($ax) . '원';
                                    elseif ($am > 0) echo number_format($am) . '원~';
                                    else echo '~' . number_format($ax) . '원';
                                }
                                ?>
                            </div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>

                <!-- 하단 고정 선택 바 -->
                <div class="pt-select-bar" id="selectBar">
                    <span class="pt-select-count"><span id="selectCount">0</span>개 선택됨</span>
                    <button type="submit" class="pt-select-submit">시술 추가 요청</button>
                </div>
            </form>
        <?php endif; ?>
    <?php endif; ?>

</div>

<script>
// 가격 편집 토글
function togglePriceEdit(id) {
    var form = document.getElementById('price-form-' + id);
    if (form) {
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
        if (form.style.display === 'block') {
            form.querySelector('input[name="price_min"]').focus();
        }
    }
}

// 카테고리 필터
function filterCategory(catId, btn) {
    document.querySelectorAll('.pt-cat-btn').forEach(function(b) { b.classList.remove('active'); });
    btn.classList.add('active');

    document.querySelectorAll('.pt-check-item').forEach(function(item) {
        if (catId === 0) {
            item.style.display = 'flex';
        } else {
            item.style.display = (item.getAttribute('data-cat') == catId) ? 'flex' : 'none';
        }
    });
}

// 선택 카운터 업데이트
function updateSelectBar() {
    var checked = document.querySelectorAll('#addForm input[type="checkbox"]:checked');
    var bar = document.getElementById('selectBar');
    var countEl = document.getElementById('selectCount');
    if (!bar || !countEl) return;

    if (checked.length > 0) {
        bar.classList.add('show');
        countEl.textContent = checked.length;
    } else {
        bar.classList.remove('show');
    }

    // 최대 개수 경고
    var remaining = <?php echo ($treatment_max - $current_count); ?>;
    if (checked.length > remaining) {
        bar.style.background = '#E65100';
        countEl.parentElement.innerHTML = '<span id="selectCount">' + checked.length + '</span>개 선택 (최대 ' + remaining + '개 추가 가능)';
    }
}
</script>

<?php
echo '<div style="height:70px;"></div>';
include_once __DIR__ . '/includes/bottom_nav.php';
if (file_exists(__DIR__ . '/includes/footer.php')) {
    include __DIR__ . '/includes/footer.php';
}
mysqli_close($connect);
?>
