<?php
/**
 * 병원별 시술 배정 관리 페이지 (admin_hospital_treatments.php)
 * - 병원 선택 후 시술 배정/해제/독립 가격 옵션 수정/프로모션 토글
 * Bootstrap 5.3 + 다크모드 지원
 * @created 2026-03-25
 */
session_start();

$admin_page_title = '병원별 시술 배정';
$admin_current = 'hospital_treatments';

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
require_permission('hospital_treatments');

// super_admin 만 전체, 그 외 모든 관리자는 자기 소속 병원만.
require_once __DIR__ . '/includes/hospital_filter.php';
$my_ids = get_strict_admin_hospital_ids($connect);
$my_in  = $my_ids === null ? null : (empty($my_ids) ? '0' : implode(',', array_map('intval', $my_ids)));

require_once __DIR__ . '/includes/hospital_treatment_images.php';
hti_ensure_schema($connect);

// 세부 옵션명을 자유롭게 저장한다. 기존 설치는 관리자 페이지 진입 시 안전하게 업그레이드한다.
$option_name_column = false;
$option_col_r = @mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_hospital_treatment_prices LIKE 'option_name'");
if ($option_col_r && mysqli_num_rows($option_col_r) > 0) {
    $option_name_column = true;
} elseif (@mysqli_query($connect, "ALTER TABLE gangnamunni_hospital_treatment_prices ADD COLUMN option_name VARCHAR(100) NOT NULL DEFAULT '' AFTER hospital_treatment_id")) {
    $option_name_column = true;
}

// 승인 절차 폐기에 따라 과거 승인 대기 건을 모두 활성화한다.
mysqli_query($connect, "UPDATE gangnamunni_hospital_treatments SET status = 'active', is_active = 1, reject_reason = NULL, updated_at = NOW() WHERE status = 'pending'");

// CSRF 토큰 생성
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// CSRF 검증 함수
function verify_csrf_ht($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
}

// ===== 병원 목록 로드 (병원 역할은 자기 소유 병원들만) =====
$hospitals = [];
if ($my_in !== null) {
    $h_r = mysqli_query($connect, "SELECT id, name FROM gangnamunni_hospitals WHERE is_active = 1 AND id IN (" . $my_in . ") ORDER BY name ASC");
} else {
    $h_r = mysqli_query($connect, "SELECT id, name FROM gangnamunni_hospitals WHERE is_active = 1 ORDER BY name ASC");
}
if ($h_r) while ($h = mysqli_fetch_assoc($h_r)) $hospitals[] = $h;

// 선택된 병원 (병원 역할: 선택값이 자기 소유가 아니면 첫 병원으로 — 다병원이면 소유 병원 간 전환 가능)
$hospital_id = intval($_GET['hospital_id'] ?? $_POST['hospital_id'] ?? 0);
if ($my_ids !== null && !in_array($hospital_id, $my_ids, true)) {
    $hospital_id = $my_ids[0] ?? 0;
}

// 카테고리 목록
$categories = [];
$cat_r = mysqli_query($connect, "SELECT * FROM gangnamunni_treatment_categories WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
if ($cat_r) while ($c = mysqli_fetch_assoc($cat_r)) $categories[$c['id']] = $c;

// 설정 조회 (관리 모드)
$treatment_mgmt_mode = 'both';
$set_r = mysqli_query($connect, "SELECT `key`, `value` FROM settings WHERE `key` = 'treatment_mgmt_mode'");
if ($set_r) while ($s = mysqli_fetch_assoc($set_r)) {
    if ($s['key'] === 'treatment_mgmt_mode') $treatment_mgmt_mode = $s['value'];
}
$admin_role = (string)($_SESSION['ad_admin_role'] ?? '');
$is_super_admin = ($admin_role === 'super_admin');
$is_hospital_account = in_array($admin_role, ['hospital_super_admin', 'hospital_admin', 'hospital_sub_admin', 'hospital_staff'], true);
$can_manage_treatments = $is_super_admin
    || ($is_hospital_account && in_array($treatment_mgmt_mode, ['hospital_only', 'both'], true))
    || (!$is_hospital_account && in_array($treatment_mgmt_mode, ['admin_only', 'both'], true));

function ht_redirect($hospital_id, $message, $type = 'success') {
    header('Location: admin_hospital_treatments.php?hospital_id=' . intval($hospital_id)
        . '&msg=' . urlencode($message) . '&msg_type=' . urlencode($type));
    exit;
}

function ht_execute_or_throw($stmt, $message) {
    if (!$stmt || !mysqli_stmt_execute($stmt)) {
        throw new RuntimeException($message . ($stmt ? ': ' . mysqli_stmt_error($stmt) : ''));
    }
}

// 가격은 변환 없이 원 단위 정수 그대로 사용한다.
function ht_price_won($price) {
    return intval($price);
}

// DB에서 삭제된 로컬 시술 이미지 파일도 함께 정리한다.
function ht_delete_local_image_files(array $urls) {
    foreach (array_unique($urls) as $url) {
        if ($url !== '' && strpos($url, 'uploads/treatments/') === 0 && strpos($url, '..') === false) {
            $path = __DIR__ . '/' . $url;
            if (is_file($path)) @unlink($path);
        }
    }
}

$msg = '';
$msg_type = 'success';

// ===== POST Actions =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $post_hospital_id = intval($_POST['hospital_id'] ?? 0);

    if (!verify_csrf_ht($_POST['csrf_token'] ?? '')) {
        $msg = 'CSRF 토큰이 유효하지 않습니다. 페이지를 새로고침해주세요.';
        $msg_type = 'danger';
    } else {

        $known_actions = ['assign', 'remove', 'update_price', 'toggle_promoted', 'image_add', 'image_delete', 'image_move', 'image_approve', 'image_reject'];
        if (!in_array($action, $known_actions, true)) ht_redirect($post_hospital_id, '지원하지 않는 요청입니다.', 'danger');
        if (in_array($action, ['image_approve', 'image_reject'], true) && !$is_super_admin) {
            ht_redirect($post_hospital_id, '시술 이미지 검수는 최고관리자만 처리할 수 있습니다.', 'danger');
        }
        if (in_array($action, ['assign', 'remove', 'update_price', 'toggle_promoted', 'image_add', 'image_delete', 'image_move'], true) && !$can_manage_treatments) {
            ht_redirect($post_hospital_id, '현재 시술 관리 설정에서는 이 작업을 수행할 수 없습니다.', 'danger');
        }

        // 병원 역할 격리(다병원): 대상 병원이 자기 소유가 아니면 첫 병원으로 강제 +
        // id 기반 액션(해제/가격/승인/거절/프로모션)의 대상 행이 자기 소유 병원 소속인지 검증.
        if ($my_ids !== null) {
            if (!in_array($post_hospital_id, $my_ids, true)) $post_hospital_id = $my_ids[0] ?? 0;
            $row_id = intval($_POST['id'] ?? 0);
            if ($row_id > 0) {
                $own = mysqli_prepare($connect, "SELECT hospital_id FROM gangnamunni_hospital_treatments WHERE id = ?");
                mysqli_stmt_bind_param($own, 'i', $row_id);
                mysqli_stmt_execute($own);
                $own_row = mysqli_fetch_assoc(mysqli_stmt_get_result($own));
                mysqli_stmt_close($own);
                if (!$own_row || !in_array((int)$own_row['hospital_id'], $my_ids, true)) {
                    header('Location: admin_hospital_treatments.php?msg=' . urlencode('접근 권한이 없습니다.'));
                    exit;
                }
            }
        }

        // --- 시술 배정 (벌크) ---
        if ($action === 'assign' && $post_hospital_id > 0) {
            $treatment_ids = $_POST['treatment_ids'] ?? [];
            if (empty($treatment_ids)) {
                $msg = '배정할 시술을 선택해주세요.';
                $msg_type = 'warning';
            } else {
                $added = 0;
                $skipped = 0;
                $orphan_image_urls = [];
                mysqli_begin_transaction($connect);
                try {
                foreach ($treatment_ids as $tid) {
                    $tid = intval($tid);
                    if ($tid <= 0) continue;
                    // 중복 체크
                    $chk = mysqli_prepare($connect, "SELECT id, status, is_active FROM gangnamunni_hospital_treatments WHERE hospital_id = ? AND treatment_id = ?");
                    mysqli_stmt_bind_param($chk, 'ii', $post_hospital_id, $tid);
                    mysqli_stmt_execute($chk);
                    $chk_r = mysqli_stmt_get_result($chk);
                    if ($existing = mysqli_fetch_assoc($chk_r)) {
                        mysqli_stmt_close($chk);
                        if (intval($existing['is_active']) === 0 || in_array($existing['status'], ['inactive', 'rejected'], true)) {
                            $new_status = 'active';
                            $existing_id = intval($existing['id']);

                            // 재배정은 새 배정과 동일하게 초기 상태로 시작한다. 해제 전에 저장된
                            // 가격 옵션/상세 이미지가 같은 id를 따라 되살아나는 문제를 방지한다.
                            $img_stmt = mysqli_prepare($connect, "SELECT image_url FROM gangnamunni_hospital_treatment_images WHERE hospital_treatment_id = ?");
                            mysqli_stmt_bind_param($img_stmt, 'i', $existing_id);
                            ht_execute_or_throw($img_stmt, '기존 이미지 조회 실패');
                            $img_r = mysqli_stmt_get_result($img_stmt);
                            while ($img = mysqli_fetch_assoc($img_r)) $orphan_image_urls[] = (string)($img['image_url'] ?? '');
                            mysqli_stmt_close($img_stmt);

                            $del_prices = mysqli_prepare($connect, "DELETE FROM gangnamunni_hospital_treatment_prices WHERE hospital_treatment_id = ?");
                            mysqli_stmt_bind_param($del_prices, 'i', $existing_id);
                            ht_execute_or_throw($del_prices, '기존 가격 옵션 초기화 실패');
                            mysqli_stmt_close($del_prices);
                            $del_images = mysqli_prepare($connect, "DELETE FROM gangnamunni_hospital_treatment_images WHERE hospital_treatment_id = ?");
                            mysqli_stmt_bind_param($del_images, 'i', $existing_id);
                            ht_execute_or_throw($del_images, '기존 이미지 초기화 실패');
                            mysqli_stmt_close($del_images);

                            $reactivate = mysqli_prepare($connect, "UPDATE gangnamunni_hospital_treatments SET status = ?, is_active = 1, reject_reason = NULL, is_promoted = 0, price_min = 0, price_max = 0, dosage_value = NULL, dosage_unit = NULL, updated_at = NOW() WHERE id = ?");
                            mysqli_stmt_bind_param($reactivate, 'si', $new_status, $existing_id);
                            ht_execute_or_throw($reactivate, '시술 재배정 실패');
                            mysqli_stmt_close($reactivate);
                            $added++;
                        } else {
                            $skipped++;
                        }
                        continue;
                    }
                    mysqli_stmt_close($chk);

                    $status = 'active';
                    $registered_by = $is_hospital_account ? 'hospital' : 'admin';
                    $stmt = mysqli_prepare($connect,
                        "INSERT INTO gangnamunni_hospital_treatments (hospital_id, treatment_id, price_min, price_max, is_promoted, note, status, registered_by, is_active, created_at, updated_at)
                         VALUES (?, ?, 0, 0, 0, '', ?, ?, 1, NOW(), NOW())");
                    mysqli_stmt_bind_param($stmt, 'iiss', $post_hospital_id, $tid, $status, $registered_by);
                    if (mysqli_stmt_execute($stmt)) {
                        $added++;
                    } else {
                        throw new RuntimeException('시술 배정 저장 실패: ' . mysqli_stmt_error($stmt));
                    }
                    mysqli_stmt_close($stmt);
                }
                mysqli_commit($connect);
                ht_delete_local_image_files($orphan_image_urls);
                } catch (Throwable $e) {
                    mysqli_rollback($connect);
                    error_log('[hospital_treatments] assign 실패: ' . $e->getMessage());
                    ht_redirect($post_hospital_id, '시술 배정에 실패했습니다. 변경사항은 저장되지 않았습니다.', 'danger');
                }
                $msg = "{$added}개 시술이 배정되었습니다.";
                if ($skipped > 0) $msg .= " (이미 배정된 {$skipped}개 건너뜀)";
                header("Location: admin_hospital_treatments.php?hospital_id={$post_hospital_id}&msg=" . urlencode($msg));
                exit;
            }
        }

        // --- 시술 해제 ---
        if ($action === 'remove') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                $image_urls = [];
                mysqli_begin_transaction($connect);
                try {
                    $img_stmt = mysqli_prepare($connect, "SELECT image_url FROM gangnamunni_hospital_treatment_images WHERE hospital_treatment_id = ?");
                    mysqli_stmt_bind_param($img_stmt, 'i', $id);
                    ht_execute_or_throw($img_stmt, '기존 이미지 조회 실패');
                    $img_r = mysqli_stmt_get_result($img_stmt);
                    while ($img = mysqli_fetch_assoc($img_r)) $image_urls[] = (string)($img['image_url'] ?? '');
                    mysqli_stmt_close($img_stmt);

                    $del_prices = mysqli_prepare($connect, "DELETE FROM gangnamunni_hospital_treatment_prices WHERE hospital_treatment_id = ?");
                    mysqli_stmt_bind_param($del_prices, 'i', $id);
                    ht_execute_or_throw($del_prices, '가격 옵션 삭제 실패');
                    mysqli_stmt_close($del_prices);
                    $del_images = mysqli_prepare($connect, "DELETE FROM gangnamunni_hospital_treatment_images WHERE hospital_treatment_id = ?");
                    mysqli_stmt_bind_param($del_images, 'i', $id);
                    ht_execute_or_throw($del_images, '상세 이미지 삭제 실패');
                    mysqli_stmt_close($del_images);

                    $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_hospital_treatments SET is_active = 0, is_promoted = 0, price_min = 0, price_max = 0, dosage_value = NULL, dosage_unit = NULL, updated_at = NOW() WHERE id = ? AND is_active = 1");
                    mysqli_stmt_bind_param($stmt, 'i', $id);
                    ht_execute_or_throw($stmt, '시술 배정 해제 실패');
                    $affected = mysqli_stmt_affected_rows($stmt);
                    mysqli_stmt_close($stmt);
                    mysqli_commit($connect);
                    ht_delete_local_image_files($image_urls);
                    ht_redirect($post_hospital_id, $affected ? '시술 배정과 가격 옵션, 상세 이미지가 삭제되었습니다.' : '이미 해제되었거나 존재하지 않는 시술입니다.', $affected ? 'success' : 'warning');
                } catch (Throwable $e) {
                    mysqli_rollback($connect);
                    error_log('[hospital_treatments] remove 실패: ' . $e->getMessage());
                    ht_redirect($post_hospital_id, '시술 배정 해제에 실패했습니다. 기존 데이터는 유지됩니다.', 'danger');
                }
            }
        }

        // --- 가격 수정 (샷수/용량·단위·가격 여러 개) ---
        if ($action === 'update_price') {
            $id = intval($_POST['id'] ?? 0);
            // 배열로 전달된 가격 옵션들 (단일 값이 오면 배열로 감싼다)
            $in_dv = $_POST['dosage_value'] ?? [];
            $in_du = $_POST['dosage_unit'] ?? [];
            $in_pr = $_POST['price'] ?? [];
            $in_on = $_POST['option_name'] ?? [];
            if (!is_array($in_dv)) $in_dv = [$in_dv];
            if (!is_array($in_du)) $in_du = [$in_du];
            if (!is_array($in_pr)) $in_pr = [$in_pr];
            if (!is_array($in_on)) $in_on = [$in_on];

            // 화면과 DB 모두 원 단위 전체 금액을 사용한다.
            $options = [];
            $cnt = max(count($in_dv), count($in_pr));
            for ($i = 0; $i < $cnt; $i++) {
                $dv = (isset($in_dv[$i]) && trim((string)$in_dv[$i]) !== '') ? floatval($in_dv[$i]) : 0;
                $du = isset($in_du[$i]) ? trim((string)$in_du[$i]) : '';
                $pr_won = (isset($in_pr[$i]) && trim((string)$in_pr[$i]) !== '') ? intval($in_pr[$i]) : 0;
                $on = isset($in_on[$i]) ? trim((string)$in_on[$i]) : '';
                if ($dv > 0 && $du !== '' && $pr_won > 0) {
                    $options[] = [
                        'name' => mb_substr($on, 0, 100, 'UTF-8'),
                        'dv' => $dv,
                        'du' => mb_substr($du, 0, 20, 'UTF-8'),
                        'pr' => $pr_won,
                    ];
                }
            }

            if ($id <= 0) {
                $msg = '잘못된 요청입니다.'; $msg_type = 'danger';
            } elseif (empty($options)) {
                $msg = '최소 1개의 샷수/용량과 가격을 입력해주세요.'; $msg_type = 'warning';
            } else {
                $option_keys = [];
                foreach ($options as $opt) {
                    // 같은 용량/단위라도 시술 부위·유형(옵션명)이 다르면 별도 옵션이다.
                    // 예: 사각턱 / 1회, 주름 / 1회
                    $key = mb_strtolower($opt['name'], 'UTF-8') . '|' . $opt['dv'] . '|' . mb_strtolower($opt['du'], 'UTF-8');
                    if (isset($option_keys[$key])) ht_redirect($post_hospital_id, '동일한 옵션명, 용량, 단위의 가격 옵션을 중복 등록할 수 없습니다.', 'warning');
                    $option_keys[$key] = true;
                }
                // 대상 배정 시술이 존재하는지 확인한다.
                $old_stmt = mysqli_prepare($connect, "SELECT id FROM gangnamunni_hospital_treatments WHERE id = ?");
                mysqli_stmt_bind_param($old_stmt, 'i', $id);
                mysqli_stmt_execute($old_stmt);
                $old_row = mysqli_fetch_assoc(mysqli_stmt_get_result($old_stmt));
                mysqli_stmt_close($old_stmt);
                if (!$old_row) ht_redirect($post_hospital_id, '대상 시술을 찾을 수 없습니다.', 'danger');

                mysqli_begin_transaction($connect);
                try {
                // 기존 옵션 전체 삭제 후 재삽입 (동기화)
                $del = mysqli_prepare($connect, "DELETE FROM gangnamunni_hospital_treatment_prices WHERE hospital_treatment_id = ?");
                mysqli_stmt_bind_param($del, 'i', $id);
                mysqli_stmt_execute($del);
                mysqli_stmt_close($del);

                $ins = mysqli_prepare($connect, $option_name_column
                    ? "INSERT INTO gangnamunni_hospital_treatment_prices (hospital_treatment_id, option_name, dosage_value, dosage_unit, price, sort_order) VALUES (?, ?, ?, ?, ?, ?)"
                    : "INSERT INTO gangnamunni_hospital_treatment_prices (hospital_treatment_id, dosage_value, dosage_unit, price, sort_order) VALUES (?, ?, ?, ?, ?)");
                foreach ($options as $so => $opt) {
                    if ($option_name_column) mysqli_stmt_bind_param($ins, 'isdsii', $id, $opt['name'], $opt['dv'], $opt['du'], $opt['pr'], $so);
                    else mysqli_stmt_bind_param($ins, 'idsii', $id, $opt['dv'], $opt['du'], $opt['pr'], $so);
                    ht_execute_or_throw($ins, '가격 옵션 저장 실패');
                }
                mysqli_stmt_close($ins);

                // 각 옵션은 독립 가격 행이며 부모 시술에 대표/MIN/MAX 가격을 만들지 않는다.
                $upd = mysqli_prepare($connect,
                    "UPDATE gangnamunni_hospital_treatments SET price_min = 0, price_max = 0, dosage_value = NULL, dosage_unit = NULL, updated_at = NOW() WHERE id = ?");
                mysqli_stmt_bind_param($upd, 'i', $id);
                ht_execute_or_throw($upd, '시술 수정일 갱신 실패');
                mysqli_stmt_close($upd);
                mysqli_commit($connect);
                } catch (Throwable $e) {
                    mysqli_rollback($connect);
                    error_log('[hospital_treatments] update_price 실패: ' . $e->getMessage());
                    ht_redirect($post_hospital_id, '가격 저장에 실패했습니다. 기존 가격은 유지됩니다.', 'danger');
                }
                header("Location: admin_hospital_treatments.php?hospital_id={$post_hospital_id}&msg=" . urlencode('가격이 수정되었습니다.'));
                exit;
            }
        }

        // --- 프로모션 토글 ---
        if ($action === 'toggle_promoted') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = mysqli_prepare($connect,
                    "UPDATE gangnamunni_hospital_treatments SET is_promoted = IF(is_promoted = 1, 0, 1), updated_at = NOW() WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                header("Location: admin_hospital_treatments.php?hospital_id={$post_hospital_id}&msg=" . urlencode('프로모션 상태가 변경되었습니다.'));
                exit;
            }
        }

        // 시술 상세페이지 이미지 추가
        if ($action === 'image_add') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                $own = mysqli_prepare($connect, "SELECT hospital_id, treatment_id FROM gangnamunni_hospital_treatments WHERE id = ?");
                mysqli_stmt_bind_param($own, 'i', $id);
                mysqli_stmt_execute($own);
                $ht_row = mysqli_fetch_assoc(mysqli_stmt_get_result($own));
                mysqli_stmt_close($own);

                if (!$ht_row) {
                    $msg = '배정된 시술을 찾을 수 없습니다.';
                    $msg_type = 'danger';
                } else {
                    $up = hti_handle_upload('image_file');
                    $image_url = '';
                    if ($up && isset($up['error'])) {
                        $msg = $up['error'];
                        $msg_type = 'danger';
                    } elseif ($up && isset($up['url'])) {
                        $image_url = $up['url'];
                    } else {
                        $image_url = trim($_POST['image_url'] ?? '');
                        if ($image_url !== '' && (strlen($image_url) > 2048 || !filter_var($image_url, FILTER_VALIDATE_URL)
                            || !in_array(strtolower((string)parse_url($image_url, PHP_URL_SCHEME)), ['http', 'https'], true))) {
                            $image_url = '';
                            $msg = '이미지 URL은 유효한 http 또는 https 주소여야 합니다.';
                            $msg_type = 'warning';
                        }
                    }

                    if ($image_url === '') {
                        if ($msg === '') {
                            $msg = '이미지 파일을 선택하거나 이미지 URL을 입력해주세요.';
                            $msg_type = 'warning';
                        }
                    } else {
                        $caption = trim($_POST['caption'] ?? '');
                        $next_order = 0;
                        $ord_stmt = mysqli_prepare($connect,
                            "SELECT COALESCE(MAX(sort_order), -1) + 1 FROM gangnamunni_hospital_treatment_images WHERE hospital_treatment_id = ?");
                        if ($ord_stmt) {
                            mysqli_stmt_bind_param($ord_stmt, 'i', $id);
                            mysqli_stmt_execute($ord_stmt);
                            mysqli_stmt_bind_result($ord_stmt, $next_order);
                            mysqli_stmt_fetch($ord_stmt);
                            mysqli_stmt_close($ord_stmt);
                        }
                        $ins = mysqli_prepare($connect,
                            "INSERT INTO gangnamunni_hospital_treatment_images
                                (hospital_treatment_id, hospital_id, treatment_id, image_url, caption, sort_order, approval_status, uploaded_by)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        if ($ins) {
                            $h_id = intval($ht_row['hospital_id']);
                            $t_id = intval($ht_row['treatment_id']);
                            $next_order = intval($next_order);
                            $image_status = $is_hospital_account ? 'pending' : 'approved';
                            $uploaded_by = $is_hospital_account ? 'hospital' : 'admin';
                            mysqli_stmt_bind_param($ins, 'iiississ', $id, $h_id, $t_id, $image_url, $caption, $next_order, $image_status, $uploaded_by);
                            mysqli_stmt_execute($ins);
                            mysqli_stmt_close($ins);
                            $image_message = $image_status === 'pending' ? '시술 이미지가 등록되었으며 최고관리자 검수 후 공개됩니다.' : '시술 이미지가 등록되었습니다.';
                            header("Location: admin_hospital_treatments.php?hospital_id={$post_hospital_id}&msg=" . urlencode($image_message));
                            exit;
                        }
                        $msg = '이미지 등록에 실패했습니다.';
                        $msg_type = 'danger';
                    }
                }
            }
        }

        // 시술 상세페이지 이미지 삭제
        if ($action === 'image_delete') {
            $id = intval($_POST['id'] ?? 0);
            $image_id = intval($_POST['image_id'] ?? 0);
            if ($id > 0 && $image_id > 0) {
                $sel = mysqli_prepare($connect,
                    "SELECT image_url FROM gangnamunni_hospital_treatment_images WHERE id = ? AND hospital_treatment_id = ?");
                $old_url = '';
                if ($sel) {
                    mysqli_stmt_bind_param($sel, 'ii', $image_id, $id);
                    mysqli_stmt_execute($sel);
                    $sel_row = mysqli_fetch_assoc(mysqli_stmt_get_result($sel));
                    mysqli_stmt_close($sel);
                    $old_url = $sel_row['image_url'] ?? '';
                }

                $del = mysqli_prepare($connect,
                    "DELETE FROM gangnamunni_hospital_treatment_images WHERE id = ? AND hospital_treatment_id = ?");
                if ($del) {
                    mysqli_stmt_bind_param($del, 'ii', $image_id, $id);
                    mysqli_stmt_execute($del);
                    mysqli_stmt_close($del);
                }

                if ($old_url !== '' && strpos($old_url, 'uploads/treatments/') === 0 && strpos($old_url, '..') === false) {
                    $path = __DIR__ . '/' . $old_url;
                    if (is_file($path)) @unlink($path);
                }
                header("Location: admin_hospital_treatments.php?hospital_id={$post_hospital_id}&msg=" . urlencode('시술 이미지가 삭제되었습니다.'));
                exit;
            }
        }

        if ($action === 'image_approve' || $action === 'image_reject') {
            $id = intval($_POST['id'] ?? 0);
            $image_id = intval($_POST['image_id'] ?? 0);
            $reason = trim((string)($_POST['reject_reason'] ?? ''));
            if ($action === 'image_reject' && $reason === '') ht_redirect($post_hospital_id, '이미지 반려 사유를 입력해주세요.', 'warning');
            if (mb_strlen($reason, 'UTF-8') > 1000) ht_redirect($post_hospital_id, '반려 사유는 1000자 이하로 입력해주세요.', 'warning');
            $new_status = $action === 'image_approve' ? 'approved' : 'rejected';
            $reviewer = mb_substr((string)($_SESSION['ad_admin_name'] ?? $_SESSION['ad_admin_user'] ?? 'super_admin'), 0, 100, 'UTF-8');
            $review = mysqli_prepare($connect, "UPDATE gangnamunni_hospital_treatment_images SET approval_status = ?, reject_reason = ?, reviewed_at = NOW(), reviewed_by = ? WHERE id = ? AND hospital_treatment_id = ? AND approval_status = 'pending'");
            mysqli_stmt_bind_param($review, 'sssii', $new_status, $reason, $reviewer, $image_id, $id);
            ht_execute_or_throw($review, '이미지 검수 저장 실패');
            $affected = mysqli_stmt_affected_rows($review);
            mysqli_stmt_close($review);
            if (!$affected) ht_redirect($post_hospital_id, '이미 처리되었거나 존재하지 않는 이미지입니다.', 'warning');
            ht_redirect($post_hospital_id, $new_status === 'approved' ? '시술 이미지가 승인되어 공개되었습니다.' : '시술 이미지가 반려되었습니다.');
        }

        if ($action === 'image_move') {
            $id = intval($_POST['id'] ?? 0);
            $image_id = intval($_POST['image_id'] ?? 0);
            $direction = (string)($_POST['direction'] ?? '');
            if ($id <= 0 || $image_id <= 0 || !in_array($direction, ['up', 'down'], true)) {
                ht_redirect($post_hospital_id, '잘못된 이미지 순서 요청입니다.', 'danger');
            }
            $ids = [];
            $list = mysqli_prepare($connect, "SELECT id FROM gangnamunni_hospital_treatment_images WHERE hospital_treatment_id = ? ORDER BY sort_order ASC, id ASC");
            mysqli_stmt_bind_param($list, 'i', $id);
            ht_execute_or_throw($list, '이미지 목록 조회 실패');
            $list_result = mysqli_stmt_get_result($list);
            while ($image_row = mysqli_fetch_assoc($list_result)) $ids[] = intval($image_row['id']);
            mysqli_stmt_close($list);
            $position = array_search($image_id, $ids, true);
            $target = $position === false ? false : ($direction === 'up' ? $position - 1 : $position + 1);
            if ($position === false || $target < 0 || $target >= count($ids)) ht_redirect($post_hospital_id, '더 이상 이동할 수 없습니다.', 'warning');
            [$ids[$position], $ids[$target]] = [$ids[$target], $ids[$position]];
            mysqli_begin_transaction($connect);
            try {
                $order_stmt = mysqli_prepare($connect, "UPDATE gangnamunni_hospital_treatment_images SET sort_order = ? WHERE id = ? AND hospital_treatment_id = ?");
                foreach ($ids as $sort_order => $ordered_id) {
                    mysqli_stmt_bind_param($order_stmt, 'iii', $sort_order, $ordered_id, $id);
                    ht_execute_or_throw($order_stmt, '이미지 순서 저장 실패');
                }
                mysqli_stmt_close($order_stmt);
                mysqli_commit($connect);
                ht_redirect($post_hospital_id, '이미지 순서가 변경되었습니다.');
            } catch (Throwable $e) {
                mysqli_rollback($connect);
                error_log('[hospital_treatments] image_move 실패: ' . $e->getMessage());
                ht_redirect($post_hospital_id, '이미지 순서를 변경하지 못했습니다.', 'danger');
            }
        }

    }
}

// GET ?msg= 파라미터 처리
if (!empty($_GET['msg'])) {
    $msg = $_GET['msg'];
    $msg_type = in_array($_GET['msg_type'] ?? '', ['success', 'danger', 'warning', 'info'], true) ? $_GET['msg_type'] : 'success';
}

// ===== 데이터 로드 (병원 선택 시) =====
$assigned_treatments = [];
$pending_treatments = [];
$stats = ['total_assigned' => 0, 'pending' => 0, 'promoted' => 0];
$selected_hospital_name = '';

if ($hospital_id > 0) {
    // 병원명
    foreach ($hospitals as $h) {
        if (intval($h['id']) === $hospital_id) {
            $selected_hospital_name = $h['name'];
            break;
        }
    }

    // 통계
    $stat_sql = "SELECT
        COUNT(CASE WHEN status = 'active' THEN 1 END) AS total_assigned,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) AS pending,
        COUNT(CASE WHEN is_promoted = 1 AND status = 'active' THEN 1 END) AS promoted
        FROM gangnamunni_hospital_treatments WHERE hospital_id = ? AND is_active = 1";
    $stat_stmt = mysqli_prepare($connect, $stat_sql);
    mysqli_stmt_bind_param($stat_stmt, 'i', $hospital_id);
    mysqli_stmt_execute($stat_stmt);
    $stat_r = mysqli_stmt_get_result($stat_stmt);
    if ($s = mysqli_fetch_assoc($stat_r)) {
        $stats['total_assigned'] = intval($s['total_assigned']);
        $stats['pending'] = intval($s['pending']);
        $stats['promoted'] = intval($s['promoted']);
    }
    mysqli_stmt_close($stat_stmt);

    // 승인 대기 시술
    $pend_sql = "SELECT ht.*, tm.name AS treatment_name, tm.hashtag, tc.name AS category_name
        FROM gangnamunni_hospital_treatments ht
        LEFT JOIN gangnamunni_treatment_master tm ON ht.treatment_id = tm.id
        LEFT JOIN gangnamunni_treatment_categories tc ON tm.category_id = tc.id
        WHERE ht.hospital_id = ? AND ht.status = 'pending' AND ht.is_active = 1
        ORDER BY ht.created_at DESC";
    $pend_stmt = mysqli_prepare($connect, $pend_sql);
    mysqli_stmt_bind_param($pend_stmt, 'i', $hospital_id);
    mysqli_stmt_execute($pend_stmt);
    $pend_r = mysqli_stmt_get_result($pend_stmt);
    while ($row = mysqli_fetch_assoc($pend_r)) $pending_treatments[] = $row;
    mysqli_stmt_close($pend_stmt);

    // 배정된 활성 시술
    $assign_sql = "SELECT ht.*, tm.name AS treatment_name, tm.hashtag, tc.name AS category_name, tc.id AS cat_id
        FROM gangnamunni_hospital_treatments ht
        LEFT JOIN gangnamunni_treatment_master tm ON ht.treatment_id = tm.id
        LEFT JOIN gangnamunni_treatment_categories tc ON tm.category_id = tc.id
        WHERE ht.hospital_id = ? AND ht.status = 'active' AND ht.is_active = 1
        ORDER BY tc.sort_order ASC, tm.sort_order ASC, tm.name ASC";
    $assign_stmt = mysqli_prepare($connect, $assign_sql);
    mysqli_stmt_bind_param($assign_stmt, 'i', $hospital_id);
    mysqli_stmt_execute($assign_stmt);
    $assign_r = mysqli_stmt_get_result($assign_stmt);
    while ($row = mysqli_fetch_assoc($assign_r)) $assigned_treatments[] = $row;
    mysqli_stmt_close($assign_stmt);
}

// 배정 시술별 가격 옵션(샷수/용량·단위·가격) 로드 → 수정 모달·목록 표시용
$ht_prices = [];
if (!empty($assigned_treatments)) {
    $ht_ids = array_map(function ($r) { return intval($r['id']); }, $assigned_treatments);
    $ht_in = implode(',', $ht_ids); // 정수만 담기므로 안전
    // 마이그레이션 미적용(테이블 없음) 등으로 실패해도 목록 페이지가 500 나지 않도록 방어
    try {
        $pr_r = mysqli_query($connect,
            "SELECT hospital_treatment_id, " . ($option_name_column ? "option_name" : "'' AS option_name") . ", dosage_value, dosage_unit, price
             FROM gangnamunni_hospital_treatment_prices
             WHERE hospital_treatment_id IN ({$ht_in})
             ORDER BY sort_order ASC, id ASC");
        if ($pr_r) while ($pr = mysqli_fetch_assoc($pr_r)) {
            $ht_prices[intval($pr['hospital_treatment_id'])][] = [
                'dosage_value' => rtrim(rtrim(number_format((float)$pr['dosage_value'], 2, '.', ''), '0'), '.'),
                'option_name'  => $pr['option_name'] ?? '',
                'dosage_unit'  => $pr['dosage_unit'],
                'price'        => intval($pr['price']),
            ];
        }
    } catch (\mysqli_sql_exception $e) {
        error_log('[hospital_treatments] 가격 옵션 로드 실패(마이그레이션 확인): ' . $e->getMessage());
    }
}

$ht_images = [];
if (!empty($assigned_treatments)) {
    $ht_images = hti_images_by_ht_ids($connect, array_map(function ($r) {
        return intval($r['id']);
    }, $assigned_treatments));
}

// ===== 미배정 시술 목록 (추가용) =====
$available_treatments = [];
if ($hospital_id > 0) {
    $avail_sql = "SELECT tm.*, tc.name AS category_name, tc.id AS cat_id
        FROM gangnamunni_treatment_master tm
        LEFT JOIN gangnamunni_treatment_categories tc ON tm.category_id = tc.id
        WHERE tm.is_active = 1
          AND tm.id NOT IN (SELECT treatment_id FROM gangnamunni_hospital_treatments WHERE hospital_id = ? AND is_active = 1 AND status IN ('active','pending'))
        ORDER BY tc.sort_order ASC, tm.name ASC";
    $avail_stmt = mysqli_prepare($connect, $avail_sql);
    mysqli_stmt_bind_param($avail_stmt, 'i', $hospital_id);
    mysqli_stmt_execute($avail_stmt);
    $avail_r = mysqli_stmt_get_result($avail_stmt);
    while ($row = mysqli_fetch_assoc($avail_r)) $available_treatments[] = $row;
    mysqli_stmt_close($avail_stmt);
}

// ===== 병원 미선택 시: 전체 요약 통계 =====
$global_stats = [];
$top_hospitals = [];
$global_pending_count = 0;
if ($hospital_id <= 0 && $my_ids === null) {
    // 전체 통계
    $gs_r = mysqli_query($connect, "SELECT
        COUNT(DISTINCT hospital_id) AS hospital_count,
        COUNT(*) AS total_assignments,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) AS pending_count,
        COUNT(CASE WHEN status = 'active' AND is_promoted = 1 THEN 1 END) AS promoted_count
        FROM gangnamunni_hospital_treatments");
    if ($gs_r) $global_stats = mysqli_fetch_assoc($gs_r);
    $global_pending_count = intval($global_stats['pending_count'] ?? 0);

    // Top 10 병원 (시술 수 기준)
    $top_sql = "SELECT h.id, h.name, COUNT(ht.id) AS treatment_count,
        COUNT(CASE WHEN ht.is_promoted = 1 THEN 1 END) AS promoted_count,
        COUNT(CASE WHEN ht.status = 'pending' THEN 1 END) AS pending_count
        FROM gangnamunni_hospital_treatments ht
        JOIN gangnamunni_hospitals h ON ht.hospital_id = h.id
        WHERE ht.status = 'active'
        GROUP BY h.id, h.name
        ORDER BY treatment_count DESC
        LIMIT 10";
    $top_r = mysqli_query($connect, $top_sql);
    if ($top_r) while ($row = mysqli_fetch_assoc($top_r)) $top_hospitals[] = $row;
}

require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <?php if (!empty($msg)): ?>
    <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show">
        <i class="bi bi-<?php echo $msg_type === 'danger' ? 'exclamation-triangle-fill' : 'check-circle-fill'; ?> me-1"></i>
        <?php echo htmlspecialchars($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- 페이지 헤더 -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-clipboard2-pulse me-2"></i>병원별 시술 배정</h4>
            <small class="text-muted">
                관리 모드: <span class="badge bg-secondary"><?php
                    $mode_labels = ['both' => '관리자+병원', 'admin_only' => '관리자 전용', 'hospital_only' => '병원 자체'];
                    echo $mode_labels[$treatment_mgmt_mode] ?? $treatment_mgmt_mode;
                ?></span>
            </small>
        </div>
    </div>

    <!-- 병원 선택 드롭다운 -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label fw-bold mb-1"><i class="bi bi-hospital me-1"></i>병원 선택</label>
                    <select name="hospital_id" class="form-select" onchange="this.form.submit()">
                        <option value="">-- 병원을 선택하세요 --</option>
                        <?php foreach ($hospitals as $h): ?>
                        <option value="<?php echo intval($h['id']); ?>" <?php echo $hospital_id === intval($h['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($h['name']); ?> (ID: <?php echo intval($h['id']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>조회</button>
                </div>
                <?php if ($hospital_id > 0): ?>
                <div class="col-md-2">
                    <a href="admin_hospital_treatments.php" class="btn btn-outline-secondary w-100"><i class="bi bi-x-lg me-1"></i>초기화</a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

<?php if ($hospital_id <= 0): ?>
    <!-- ===== 병원 미선택: 전체 요약 ===== -->
    <div class="alert alert-info">
        <i class="bi bi-info-circle-fill me-2"></i>병원을 선택하면 해당 병원의 시술 배정을 관리할 수 있습니다.
    </div>

    <!-- 전체 통계 카드 -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <h4 class="mb-0 text-primary"><?php echo number_format(intval($global_stats['hospital_count'] ?? 0)); ?></h4>
                    <small class="text-muted">참여 병원</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <h4 class="mb-0 text-success"><?php echo number_format(intval($global_stats['total_assignments'] ?? 0)); ?></h4>
                    <small class="text-muted">총 배정</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <h4 class="mb-0 text-danger"><?php echo number_format(intval($global_stats['promoted_count'] ?? 0)); ?></h4>
                    <small class="text-muted">프로모션</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Top 10 병원 -->
    <?php if (!empty($top_hospitals)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent">
            <h6 class="mb-0"><i class="bi bi-trophy me-2"></i>Top 10 병원 (배정 시술 수)</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:50px;">#</th>
                        <th>병원명</th>
                        <th class="text-center" style="width:100px;">배정 시술</th>
                        <th class="text-center" style="width:100px;">프로모션</th>
                        <th style="width:100px;">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_hospitals as $idx => $th): ?>
                    <tr>
                        <td class="fw-bold"><?php echo $idx + 1; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($th['name']); ?></strong>
                            <small class="text-muted d-block">ID: <?php echo intval($th['id']); ?></small>
                        </td>
                        <td class="text-center"><span class="badge bg-primary"><?php echo number_format(intval($th['treatment_count'])); ?></span></td>
                        <td class="text-center"><span class="badge bg-danger"><?php echo number_format(intval($th['promoted_count'])); ?></span></td>
                        <td>
                            <a href="admin_hospital_treatments.php?hospital_id=<?php echo intval($th['id']); ?>" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-arrow-right"></i> 관리
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

<?php else: ?>
    <!-- ===== 병원 선택됨: 시술 배정 관리 ===== -->

    <!-- 통계 카드 -->
    <div class="row g-3 mb-4">
        <div class="col-4 col-md">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <h5 class="mb-0 text-primary"><?php echo number_format($stats['total_assigned']); ?></h5>
                    <small class="text-muted">배정 시술</small>
                </div>
            </div>
        </div>
        <div class="col-4 col-md">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <h5 class="mb-0 text-danger"><?php echo number_format($stats['promoted']); ?></h5>
                    <small class="text-muted">프로모션</small>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== 승인 대기 섹션 ===== -->
    <?php if (false): // 승인 워크플로우 폐기 ?>
    <div class="card border-0 shadow-sm mb-4 border-start border-4 border-warning">
        <div class="card-header bg-warning bg-opacity-10">
            <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>승인 대기 시술 (<?php echo count($pending_treatments); ?>건)</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>시술명</th>
                        <th>카테고리</th>
                        <th class="text-center">해시태그</th>
                        <th class="text-center">가격</th>
                        <th class="text-center">등록자</th>
                        <th class="text-center">신청일</th>
                        <th class="text-center" style="width:200px;">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_treatments as $pt): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($pt['treatment_name'] ?? '(삭제됨)'); ?></strong></td>
                        <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($pt['category_name'] ?? '-'); ?></span></td>
                        <td class="text-center"><small class="text-muted"><?php echo htmlspecialchars($pt['hashtag'] ?? ''); ?></small></td>
                        <td class="text-center"><span class="text-muted">-</span></td>
                        <td class="text-center"><span class="badge bg-<?php echo $pt['registered_by'] === 'admin' ? 'primary' : 'success'; ?>"><?php echo $pt['registered_by'] === 'admin' ? '관리자' : '병원'; ?></span></td>
                        <td class="text-center"><small><?php echo substr($pt['created_at'] ?? '', 0, 16); ?></small></td>
                        <td class="text-center">
                            <?php if ($is_platform_approver): ?>
                            <!-- 승인 -->
                            <form method="post" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="id" value="<?php echo intval($pt['id']); ?>">
                                <input type="hidden" name="hospital_id" value="<?php echo $hospital_id; ?>">
                                <button type="submit" class="btn btn-success btn-sm" title="승인"><i class="bi bi-check-lg"></i> 승인</button>
                            </form>
                            <!-- 거절 -->
                            <button type="button" class="btn btn-danger btn-sm" title="거절"
                                onclick="openRejectModal(<?php echo intval($pt['id']); ?>, '<?php echo htmlspecialchars(addslashes($pt['treatment_name'] ?? ''), ENT_QUOTES); ?>')">
                                <i class="bi bi-x-lg"></i> 거절
                            </button>
                            <?php else: ?>
                            <span class="text-muted small">최고관리자 검토 대기</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===== 배정 시술 테이블 ===== -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-list-check me-2"></i>배정 시술 목록 (<?php echo count($assigned_treatments); ?>건)</h6>
            <?php if ($can_manage_treatments): ?><button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#addTreatmentSection">
                <i class="bi bi-plus-lg me-1"></i>시술 추가
            </button><?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:50px;">#</th>
                        <th style="width:100px;">해시태그</th>
                        <th>시술명</th>
                        <th class="text-center" style="width:100px;">카테고리</th>
                        <th class="text-center" style="width:160px;">가격 (원)</th>
                        <th class="text-center" style="width:80px;">프로모션</th>
                        <th style="width:150px;">비고</th>
                        <th class="text-center" style="width:80px;">등록자</th>
                        <th class="text-center" style="width:100px;">상세 이미지</th>
                        <th class="text-center" style="width:140px;">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($assigned_treatments)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox" style="font-size:2rem;"></i><br>
                            배정된 시술이 없습니다. <strong>"시술 추가"</strong> 버튼을 눌러 배정하세요.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($assigned_treatments as $idx => $at): ?>
                    <tr>
                        <td><?php echo $idx + 1; ?></td>
                        <td><small class="text-primary"><?php echo htmlspecialchars($at['hashtag'] ?? ''); ?></small></td>
                        <td><strong><?php echo htmlspecialchars($at['treatment_name'] ?? '(삭제됨)'); ?></strong></td>
                        <td class="text-center"><span class="badge bg-info text-dark"><?php echo htmlspecialchars($at['category_name'] ?? '-'); ?></span></td>
                        <td class="text-center">
                            <?php $opts = $ht_prices[intval($at['id'])] ?? []; ?>
                            <?php if (!empty($opts)): ?>
                            <div class="d-flex flex-wrap gap-1 justify-content-center">
                                <?php foreach ($opts as $op): ?>
                                <span class="badge bg-light text-dark border"><?php echo htmlspecialchars(($op['option_name'] !== '' ? $op['option_name'] . ' · ' : '') . $op['dosage_value'] . $op['dosage_unit']); ?> · <?php echo number_format(ht_price_won($op['price'])); ?>원</span>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <span class="text-muted small">가격 옵션 미등록</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <form method="post" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="toggle_promoted">
                                <input type="hidden" name="id" value="<?php echo intval($at['id']); ?>">
                                <input type="hidden" name="hospital_id" value="<?php echo $hospital_id; ?>">
                                <button type="submit" class="btn btn-sm btn-link p-0" title="프로모션 토글">
                                    <?php if (intval($at['is_promoted'])): ?>
                                        <i class="bi bi-star-fill text-warning fs-5"></i>
                                    <?php else: ?>
                                        <i class="bi bi-star text-muted fs-5"></i>
                                    <?php endif; ?>
                                </button>
                            </form>
                        </td>
                        <td><small class="text-muted"><?php echo htmlspecialchars(mb_substr($at['note'] ?? '', 0, 20, 'UTF-8')); ?></small></td>
                        <td class="text-center">
                            <span class="badge bg-<?php echo $at['registered_by'] === 'admin' ? 'primary' : 'success'; ?>">
                                <?php echo $at['registered_by'] === 'admin' ? '관리자' : '병원'; ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <?php
                            $treatment_images = $ht_images[intval($at['id'])] ?? [];
                            $img_cnt = count($treatment_images);
                            $pending_img_cnt = count(array_filter($treatment_images, function ($image) {
                                return ($image['approval_status'] ?? 'approved') === 'pending';
                            }));
                            ?>
                            <button type="button"
                                class="btn btn-sm <?php echo $img_cnt ? 'btn-outline-success' : 'btn-outline-secondary'; ?>"
                                title="시술 상세페이지 이미지 관리"
                                onclick="openTreatmentImageModal(<?php echo intval($at['id']); ?>, <?php echo htmlspecialchars(json_encode($at['treatment_name'] ?? '', JSON_UNESCAPED_UNICODE), ENT_QUOTES); ?>)">
                                <i class="bi bi-images"></i>
                                <span class="ms-1"><?php echo $img_cnt; ?></span>
                            </button>
                            <?php if ($pending_img_cnt > 0): ?><span class="badge bg-warning text-dark ms-1"><?php echo $pending_img_cnt; ?> 검수</span><?php endif; ?>
                        </td>
                        <td class="text-center">
                            <!-- 가격 수정 -->
                            <button class="btn btn-outline-primary btn-sm" title="가격 수정"
                                onclick="openPriceModal(<?php echo intval($at['id']); ?>, '<?php echo htmlspecialchars(addslashes($at['treatment_name'] ?? ''), ENT_QUOTES); ?>')">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <!-- 배정 해제 -->
                            <form method="post" class="d-inline" onsubmit="return confirm('이 시술 배정을 해제하시겠습니까?');">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="id" value="<?php echo intval($at['id']); ?>">
                                <input type="hidden" name="hospital_id" value="<?php echo $hospital_id; ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm" title="배정 해제"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ===== 시술 추가 섹션 (Collapse) ===== -->
    <div class="collapse mb-4" id="addTreatmentSection">
        <div class="card border-0 shadow-sm border-start border-4 border-primary">
            <div class="card-header bg-primary bg-opacity-10">
                <h6 class="mb-0"><i class="bi bi-plus-circle me-2"></i>시술 추가 (미배정 시술 <?php echo count($available_treatments); ?>건)</h6>
            </div>
            <div class="card-body">
                <?php if (empty($available_treatments)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-check-circle" style="font-size:2rem;"></i><br>
                        모든 시술이 이미 배정되어 있습니다.
                    </div>
                <?php else: ?>
                <form method="post" id="assignForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="assign">
                    <input type="hidden" name="hospital_id" value="<?php echo $hospital_id; ?>">

                    <!-- 카테고리 탭 필터 -->
                    <ul class="nav nav-pills nav-fill mb-3" id="catTabs">
                        <li class="nav-item">
                            <a class="nav-link active" href="#" data-cat="all" onclick="filterCategory(event, 'all')">전체 (<?php echo count($available_treatments); ?>)</a>
                        </li>
                        <?php
                        $avail_cats = [];
                        foreach ($available_treatments as $at) {
                            $cid = intval($at['cat_id'] ?? 0);
                            $cname = $at['category_name'] ?? '미분류';
                            if (!isset($avail_cats[$cid])) $avail_cats[$cid] = ['name' => $cname, 'count' => 0];
                            $avail_cats[$cid]['count']++;
                        }
                        foreach ($avail_cats as $cid => $ci): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-cat="<?php echo $cid; ?>" onclick="filterCategory(event, <?php echo $cid; ?>)">
                                <?php echo htmlspecialchars($ci['name']); ?> (<?php echo $ci['count']; ?>)
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>

                    <!-- 전체 선택/해제 + 추가 버튼 -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                            <label class="form-check-label" for="selectAll">보이는 항목 전체 선택</label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-lg me-1"></i>선택 시술 추가
                        </button>
                    </div>

                    <!-- 시술 체크박스 목록 -->
                    <div class="row g-2" id="treatmentList" style="max-height:400px; overflow-y:auto;">
                        <?php foreach ($available_treatments as $at): ?>
                        <div class="col-md-4 col-sm-6 treatment-item" data-cat="<?php echo intval($at['cat_id'] ?? 0); ?>">
                            <div class="card card-body py-2 px-3">
                                <div class="form-check">
                                    <input class="form-check-input treatment-cb" type="checkbox" name="treatment_ids[]" value="<?php echo intval($at['id']); ?>" id="t_<?php echo intval($at['id']); ?>">
                                    <label class="form-check-label" for="t_<?php echo intval($at['id']); ?>">
                                        <strong><?php echo htmlspecialchars($at['name']); ?></strong>
                                        <?php if (!empty($at['hashtag'])): ?>
                                        <small class="text-primary d-block"><?php echo htmlspecialchars($at['hashtag']); ?></small>
                                        <?php endif; ?>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($at['category_name'] ?? '미분류'); ?>
                                        </small>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ===== 승인 이력 섹션 ===== -->
    <?php
    $approval_logs = [];
    if ($hospital_id > 0) {
        $log_sql = "SELECT al.*, tm.name AS treatment_name, tm.hashtag
            FROM gangnamunni_treatment_approval_log al
            LEFT JOIN gangnamunni_treatment_master tm ON al.treatment_id = tm.id
            WHERE al.hospital_id = ?
            ORDER BY al.created_at DESC
            LIMIT 50";
        $log_stmt = mysqli_prepare($connect, $log_sql);
        if ($log_stmt) {
            mysqli_stmt_bind_param($log_stmt, 'i', $hospital_id);
            mysqli_stmt_execute($log_stmt);
            $log_r = mysqli_stmt_get_result($log_stmt);
            while ($row = mysqli_fetch_assoc($log_r)) $approval_logs[] = $row;
            mysqli_stmt_close($log_stmt);
        }
    }
    if (false): // 승인 워크플로우 폐기
    ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent">
            <h6 class="mb-0"><i class="bi bi-journal-text me-2"></i>승인 처리 이력 (최근 50건)</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:160px;">일시</th>
                        <th>시술명</th>
                        <th class="text-center" style="width:100px;">처리</th>
                        <th class="text-center" style="width:120px;">이전 상태</th>
                        <th class="text-center" style="width:120px;">변경 상태</th>
                        <th>사유</th>
                        <th class="text-center" style="width:100px;">처리자</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($approval_logs as $log):
                        $action_badges = [
                            'submitted' => '<span class="badge bg-info">제출</span>',
                            'approved' => '<span class="badge bg-success">승인</span>',
                            'rejected' => '<span class="badge bg-danger">거절</span>',
                            'resubmitted' => '<span class="badge bg-warning text-dark">재제출</span>',
                            'cancelled' => '<span class="badge bg-secondary">취소</span>',
                        ];
                        $status_labels = [
                            'pending' => '<span class="badge bg-warning text-dark">대기</span>',
                            'active' => '<span class="badge bg-success">활성</span>',
                            'rejected' => '<span class="badge bg-danger">거절</span>',
                        ];
                    ?>
                    <tr>
                        <td><small><?php echo substr($log['created_at'] ?? '', 0, 19); ?></small></td>
                        <td>
                            <strong><?php echo htmlspecialchars($log['treatment_name'] ?? '(삭제됨)'); ?></strong>
                            <?php if (!empty($log['hashtag'])): ?>
                            <small class="text-primary ms-1"><?php echo htmlspecialchars($log['hashtag']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?php echo $action_badges[$log['action']] ?? htmlspecialchars($log['action']); ?></td>
                        <td class="text-center"><?php echo $status_labels[$log['old_status'] ?? ''] ?? '<span class="text-muted">-</span>'; ?></td>
                        <td class="text-center"><?php echo $status_labels[$log['new_status'] ?? ''] ?? htmlspecialchars($log['new_status'] ?? '-'); ?></td>
                        <td><small class="text-muted"><?php echo htmlspecialchars(mb_substr($log['reason'] ?? '', 0, 50, 'UTF-8')); ?></small></td>
                        <td class="text-center"><small><?php echo htmlspecialchars($log['admin_name'] ?? $log['hospital_user'] ?? '-'); ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

<?php endif; ?>
</div>

<!-- ===== 가격 수정 모달 ===== -->
<div class="modal fade" id="priceModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="update_price">
            <input type="hidden" name="id" id="price_id">
            <input type="hidden" name="hospital_id" value="<?php echo $hospital_id; ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-currency-won me-2"></i>가격 수정</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">시술: <strong id="price_treatment_name"></strong></p>
                    <div class="d-flex fw-bold text-muted small px-1 mb-1" style="gap:8px;">
                        <div style="flex:1.4;">옵션명</div>
                        <div style="flex:1;">샷수/용량</div>
                        <div style="width:96px;">단위</div>
                        <div style="width:140px;">가격 (원)</div>
                        <div style="width:34px;"></div>
                    </div>
                    <div id="priceRows"></div>
                    <button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="addPriceRow()">
                        <i class="bi bi-plus-lg me-1"></i>세부 옵션 추가
                    </button>
                    <div class="form-text mt-2">가격은 축약하지 않은 원 단위 전체 금액으로 입력해주세요 (예: 10000, 250000). 최소 1개 이상 입력해야 합니다. 2개 이상이면 예약 화면에서 고객이 세부 옵션 1개를 선택하게 됩니다.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>저장</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- 시술 상세페이지 이미지 추가/삭제 모달 -->
<div class="modal fade" id="treatmentImageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-images me-2"></i>시술 상세페이지 이미지 — <span id="ti_treatment_name"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">등록한 이미지는 해당 병원의 이 시술 상세페이지에만 노출됩니다.</p>
                <div id="ti_gallery" class="row g-2 mb-3"></div>
                <form method="post" enctype="multipart/form-data" class="border-top pt-3">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="image_add">
                    <input type="hidden" name="id" id="ti_ht_id">
                    <input type="hidden" name="hospital_id" value="<?php echo $hospital_id; ?>">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label fw-bold small mb-1">이미지 파일</label>
                            <input type="file" name="image_file" accept=".jpg,.jpeg,.png,.gif,.webp" class="form-control form-control-sm">
                            <div class="form-text">JPG · PNG · GIF · WebP / 10MB 이하</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small mb-1">또는 이미지 URL</label>
                            <input type="text" name="image_url" class="form-control form-control-sm" placeholder="https://...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small mb-1">설명 (선택)</label>
                            <input type="text" name="caption" class="form-control form-control-sm" maxlength="200">
                        </div>
                    </div>
                    <div class="text-end mt-3">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-upload me-1"></i>이미지 등록</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<form method="post" id="ti_delete_form" class="d-none">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="image_delete">
    <input type="hidden" name="id" id="ti_del_ht_id">
    <input type="hidden" name="image_id" id="ti_del_image_id">
    <input type="hidden" name="hospital_id" value="<?php echo $hospital_id; ?>">
</form>
<form method="post" id="ti_move_form" class="d-none">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="image_move">
    <input type="hidden" name="id" id="ti_move_ht_id">
    <input type="hidden" name="image_id" id="ti_move_image_id">
    <input type="hidden" name="direction" id="ti_move_direction">
    <input type="hidden" name="hospital_id" value="<?php echo $hospital_id; ?>">
</form>
<form method="post" id="ti_review_form" class="d-none">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" id="ti_review_action">
    <input type="hidden" name="id" id="ti_review_ht_id">
    <input type="hidden" name="image_id" id="ti_review_image_id">
    <input type="hidden" name="reject_reason" id="ti_review_reason">
    <input type="hidden" name="hospital_id" value="<?php echo $hospital_id; ?>">
</form>

<script>
var HT_IMAGES = <?php echo json_encode($ht_images, JSON_UNESCAPED_UNICODE); ?>;
var IS_SUPER_ADMIN = <?php echo $is_super_admin ? 'true' : 'false'; ?>;

function tiEscape(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function(c) {
        return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];
    });
}

function openTreatmentImageModal(htId, name) {
    document.getElementById('ti_ht_id').value = htId;
    document.getElementById('ti_treatment_name').textContent = name || '';
    var list = HT_IMAGES[htId] || [];
    var gallery = document.getElementById('ti_gallery');
    if (!list.length) {
        gallery.innerHTML = '<div class="col-12 text-center text-muted py-4 border rounded">'
            + '<i class="bi bi-image" style="font-size:1.8rem;"></i><br>등록된 이미지가 없습니다.</div>';
    } else {
        gallery.innerHTML = list.map(function(img, i) {
            var status = img.approval_status || 'approved';
            var badge = status === 'pending' ? '<span class="badge bg-warning text-dark">검수 대기</span>'
                : (status === 'rejected' ? '<span class="badge bg-danger">반려</span>' : '<span class="badge bg-success">승인</span>');
            var review = (IS_SUPER_ADMIN && status === 'pending')
                ? '<div class="d-flex gap-1 mt-1"><button type="button" class="btn btn-success btn-sm flex-fill" onclick="reviewTreatmentImage(' + htId + ',' + img.id + ',\'approve\')">승인</button><button type="button" class="btn btn-danger btn-sm flex-fill" onclick="reviewTreatmentImage(' + htId + ',' + img.id + ',\'reject\')">반려</button></div>' : '';
            var reason = status === 'rejected' && img.reject_reason ? '<small class="text-danger d-block mt-1">' + tiEscape(img.reject_reason) + '</small>' : '';
            return '<div class="col-6 col-md-3"><div class="border rounded overflow-hidden h-100 d-flex flex-column">'
                + '<img src="' + tiEscape(img.image_url) + '" alt="" style="width:100%;aspect-ratio:4/3;object-fit:cover;">'
                + '<div class="px-2 pt-2">' + badge + reason + review + '</div>'
                + '<div class="p-2 d-flex align-items-center justify-content-between gap-1">'
                + '<small class="text-muted text-truncate">' + (i + 1) + '. ' + tiEscape(img.caption || '') + '</small>'
                + '<span class="btn-group btn-group-sm"><button type="button" class="btn btn-outline-secondary py-0 px-1" onclick="moveTreatmentImage(' + htId + ',' + img.id + ',\'up\')" title="앞으로"><i class="bi bi-arrow-left"></i></button>'
                + '<button type="button" class="btn btn-outline-secondary py-0 px-1" onclick="moveTreatmentImage(' + htId + ',' + img.id + ',\'down\')" title="뒤로"><i class="bi bi-arrow-right"></i></button></span>'
                + '<button type="button" class="btn btn-outline-danger btn-sm py-0 px-1" '
                + 'onclick="deleteTreatmentImage(' + htId + ',' + img.id + ')" title="삭제">'
                + '<i class="bi bi-trash"></i></button></div></div></div>';
        }).join('');
    }
    new bootstrap.Modal(document.getElementById('treatmentImageModal')).show();
}

function reviewTreatmentImage(htId, imageId, decision) {
    var reason = '';
    if (decision === 'reject') {
        reason = window.prompt('이미지 반려 사유를 입력해주세요.');
        if (reason === null) return;
        reason = reason.trim();
        if (!reason) { alert('반려 사유는 필수입니다.'); return; }
    } else if (!confirm('이 이미지를 승인하고 공개하시겠습니까?')) {
        return;
    }
    document.getElementById('ti_review_action').value = decision === 'approve' ? 'image_approve' : 'image_reject';
    document.getElementById('ti_review_ht_id').value = htId;
    document.getElementById('ti_review_image_id').value = imageId;
    document.getElementById('ti_review_reason').value = reason;
    document.getElementById('ti_review_form').submit();
}

function moveTreatmentImage(htId, imageId, direction) {
    document.getElementById('ti_move_ht_id').value = htId;
    document.getElementById('ti_move_image_id').value = imageId;
    document.getElementById('ti_move_direction').value = direction;
    document.getElementById('ti_move_form').submit();
}

function deleteTreatmentImage(htId, imageId) {
    if (!confirm('이 이미지를 삭제하시겠습니까?')) return;
    document.getElementById('ti_del_ht_id').value = htId;
    document.getElementById('ti_del_image_id').value = imageId;
    document.getElementById('ti_delete_form').submit();
}

// 가격 수정 모달
// 배정 시술별 가격 옵션 맵 (id → [{dosage_value, dosage_unit, price}, ...])
var HT_PRICES = <?php echo json_encode($ht_prices, JSON_UNESCAPED_UNICODE); ?>;
var PRICE_UNITS = ['샷','cc','ml','회','병','IU','기타'];

function priceToWon(price) {
    return Number(price) || 0;
}

function priceRowHtml(dv, du, pr, optionName) {
    var opts = PRICE_UNITS.map(function(u){
        return '<option value="'+u+'"'+(u===du?' selected':'')+'>'+u+'</option>';
    }).join('');
    return '<div class="d-flex align-items-center mb-2 price-row" style="gap:8px;">' +
        '<input type="text" name="option_name[]" class="form-control form-control-sm" style="flex:1.4;" maxlength="100" placeholder="예: 주름 100샷" value="'+tiEscape(optionName || '')+'">' +
        '<input type="number" name="dosage_value[]" class="form-control form-control-sm" style="flex:1;" min="0.01" step="0.01" required placeholder="예: 100" value="'+(dv==null?'':dv)+'">' +
        '<select name="dosage_unit[]" class="form-select form-select-sm" style="width:96px;">'+opts+'</select>' +
        '<input type="number" name="price[]" class="form-control form-control-sm" style="width:140px;" min="1" step="1" required placeholder="예: 40825" value="'+(pr==null||pr===''?'':priceToWon(pr))+'">' +
        '<button type="button" class="btn btn-outline-danger btn-sm price-del" style="width:34px;" onclick="removePriceRow(this)"><i class="bi bi-x-lg"></i></button>' +
        '</div>';
}

function addPriceRow(dv, du, pr, optionName) {
    document.getElementById('priceRows').insertAdjacentHTML('beforeend', priceRowHtml(dv, du || '회', pr, optionName));
    refreshPriceDelButtons();
}

function removePriceRow(btn) {
    var row = btn.closest('.price-row');
    if (row) row.remove();
    refreshPriceDelButtons();
}

// 최소 1개 유지 — 행이 1개뿐이면 삭제 버튼 비활성화
function refreshPriceDelButtons() {
    var rows = document.querySelectorAll('#priceRows .price-row');
    rows.forEach(function(r){
        var del = r.querySelector('.price-del');
        if (del) del.disabled = (rows.length <= 1);
    });
}

function openPriceModal(id, name) {
    document.getElementById('price_id').value = id;
    document.getElementById('price_treatment_name').textContent = name;
    document.getElementById('priceRows').innerHTML = '';
    var list = HT_PRICES[id] || [];
    if (!list.length) list = [{dosage_value:'', dosage_unit:'회', price:''}];
    list.forEach(function(o){ addPriceRow(o.dosage_value, o.dosage_unit, o.price, o.option_name); });
    new bootstrap.Modal(document.getElementById('priceModal')).show();
}

// 거절 모달
function openRejectModal(id, name) {
    document.getElementById('reject_id').value = id;
    document.getElementById('reject_treatment_name').textContent = name;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

// 카테고리 탭 필터
function filterCategory(e, catId) {
    e.preventDefault();
    // 탭 활성화
    document.querySelectorAll('#catTabs .nav-link').forEach(function(el) { el.classList.remove('active'); });
    e.target.classList.add('active');
    // 항목 필터
    document.querySelectorAll('.treatment-item').forEach(function(el) {
        if (catId === 'all' || parseInt(el.dataset.cat) === catId) {
            el.style.display = '';
        } else {
            el.style.display = 'none';
        }
    });
    // 전체 선택 체크 해제
    document.getElementById('selectAll').checked = false;
}

// 전체 선택/해제
function toggleSelectAll(el) {
    document.querySelectorAll('.treatment-item').forEach(function(item) {
        if (item.style.display !== 'none') {
            var cb = item.querySelector('.treatment-cb');
            if (cb) cb.checked = el.checked;
        }
    });
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
