<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Set only by the hospital page's isolated renderer, never by a request parameter.
$ht_panel = isset($ht_panel_hospital_id) && (int)$ht_panel_hospital_id > 0;
$ht_return_mode = $ht_panel ? ($ht_panel_edit ? 'edit' : 'view') : (string)($_GET['hospital_panel'] ?? '');
$ht_return_mode = in_array($ht_return_mode, ['edit', 'view'], true) ? $ht_return_mode : '';

$admin_page_title = '병원별 시술 배정';
$admin_current = 'hospital_treatments';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/treatment_units.php';
require_once __DIR__ . '/includes/admin_auth.php';

if (!isset($connect) || !$connect) {
    $connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    if ($connect) mysqli_set_charset($connect, 'utf8mb4');
}
if (!$connect) die('DB 연결 실패');

loadThemeSettings($connect);
require_admin_login();
require_permission('hospital_treatments');
$price_units = treatment_units_list($connect);

require_once __DIR__ . '/includes/hospital_filter.php';
$my_ids = get_strict_admin_hospital_ids($connect);
$my_in  = $my_ids === null ? null : (empty($my_ids) ? '0' : implode(',', array_map('intval', $my_ids)));

require_once __DIR__ . '/includes/hospital_treatment_images.php';
hti_ensure_schema($connect);

$option_name_column = false;
$option_col_r = @mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_hospital_treatment_prices LIKE 'option_name'");
if ($option_col_r && mysqli_num_rows($option_col_r) > 0) {
    $option_name_column = true;
} elseif (@mysqli_query($connect, "ALTER TABLE gangnamunni_hospital_treatment_prices ADD COLUMN option_name VARCHAR(100) NOT NULL DEFAULT '' AFTER hospital_treatment_id")) {
    $option_name_column = true;
}

$body_part_column = false;
$body_part_col_r = @mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_hospital_treatment_prices LIKE 'body_part'");
if ($body_part_col_r && mysqli_num_rows($body_part_col_r) > 0) {
    $body_part_column = true;
    $body_part_col = mysqli_fetch_assoc($body_part_col_r);
    if (preg_match('/varchar\((\d+)\)/i', (string)($body_part_col['Type'] ?? ''), $body_part_size) && intval($body_part_size[1]) < 500) {
        @mysqli_query($connect, "ALTER TABLE gangnamunni_hospital_treatment_prices MODIFY COLUMN body_part VARCHAR(500) NOT NULL DEFAULT ''");
    }
} elseif (@mysqli_query($connect, "ALTER TABLE gangnamunni_hospital_treatment_prices ADD COLUMN body_part VARCHAR(500) NOT NULL DEFAULT '' AFTER option_name")) {
    $body_part_column = true;
}

$regular_price_column = false;
$regular_col_r = @mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_hospital_treatment_prices LIKE 'regular_price'");
if ($regular_col_r && mysqli_num_rows($regular_col_r) > 0) {
    $regular_price_column = true;
} elseif (@mysqli_query($connect, "ALTER TABLE gangnamunni_hospital_treatment_prices ADD COLUMN regular_price INT NOT NULL DEFAULT 0 AFTER dosage_unit")) {
    $regular_price_column = true;
}
if ($regular_price_column) {
    @mysqli_query($connect, "UPDATE gangnamunni_hospital_treatment_prices SET regular_price = price WHERE regular_price <= 0 AND price > 0");
}

$representative_price_column = false;
$representative_col_r = @mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_hospital_treatment_prices LIKE 'is_representative'");
if ($representative_col_r && mysqli_num_rows($representative_col_r) > 0) {
    $representative_price_column = true;
} elseif (@mysqli_query($connect, "ALTER TABLE gangnamunni_hospital_treatment_prices ADD COLUMN is_representative TINYINT(1) NOT NULL DEFAULT 0 AFTER price")) {
    $representative_price_column = true;
}

mysqli_query($connect, "UPDATE gangnamunni_hospital_treatments SET status = 'active', is_active = 1, reject_reason = NULL, updated_at = NOW() WHERE status = 'pending'");

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

function verify_csrf_ht($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
}

$hospitals = [];
if ($my_in !== null) {
    $h_r = mysqli_query($connect, "SELECT id, name FROM gangnamunni_hospitals WHERE is_active = 1 AND id IN (" . $my_in . ") ORDER BY name ASC");
} else {
    $h_r = mysqli_query($connect, "SELECT id, name FROM gangnamunni_hospitals WHERE is_active = 1 ORDER BY name ASC");
}
if ($h_r) while ($h = mysqli_fetch_assoc($h_r)) $hospitals[] = $h;

$hospital_id = $ht_panel ? (int)$ht_panel_hospital_id : intval($_GET['hospital_id'] ?? $_POST['hospital_id'] ?? 0);
if ($my_ids !== null && !in_array($hospital_id, $my_ids, true)) {
    if ($ht_panel) {
        echo '<div class="alert alert-warning mb-0">이 병원의 시술을 관리할 권한이 없습니다.</div>';
        return;
    }
    $hospital_id = $my_ids[0] ?? 0;
}

$treatment_mgmt_mode = 'both';
$set_r = mysqli_query($connect, "SELECT `key`, `value` FROM settings WHERE `key` = 'treatment_mgmt_mode'");
if ($set_r) while ($s = mysqli_fetch_assoc($set_r)) {
    if ($s['key'] === 'treatment_mgmt_mode') $treatment_mgmt_mode = $s['value'];
}
$admin_role = (string)($_SESSION['ad_admin_role'] ?? '');
$is_super_admin = ($admin_role === 'super_admin');
$is_hospital_account = in_array($admin_role, ['hospital_super_admin', 'hospital_admin'], true);
$can_manage_treatments = $is_super_admin
    || ($is_hospital_account && in_array($treatment_mgmt_mode, ['hospital_only', 'both'], true))
    || (!$is_hospital_account && in_array($treatment_mgmt_mode, ['admin_only', 'both'], true));

function ht_redirect($hospital_id, $message, $type = 'success') {
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
        && in_array($_GET['hospital_panel'] ?? '', ['edit', 'view'], true)) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => $type === 'success', 'message' => $message, 'type' => $type]);
        exit;
    }
    $mode = (string)($_GET['hospital_panel'] ?? '');
    if (in_array($mode, ['edit', 'view'], true)) {
        $section = $mode === 'edit' ? 'editTreatmentsSection' : 'viewTreatmentsSection';
        header('Location: admin_hospitals.php?view=' . intval($hospital_id)
            . ($mode === 'edit' ? '&edit=1' : '')
            . '&treatment_msg=' . urlencode($message) . '&treatment_msg_type=' . urlencode($type)
            . '#' . $section);
    } else {
        header('Location: admin_hospital_treatments.php?hospital_id=' . intval($hospital_id)
            . '&msg=' . urlencode($message) . '&msg_type=' . urlencode($type));
    }
    exit;
}

function ht_execute_or_throw($stmt, $message) {
    if (!$stmt || !mysqli_stmt_execute($stmt)) {
        throw new RuntimeException($message . ($stmt ? ': ' . mysqli_stmt_error($stmt) : ''));
    }
}

function ht_price_won($price) {
    return intval($price);
}

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

if (!$ht_panel && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $post_hospital_id = intval($_POST['hospital_id'] ?? 0);

    if (!verify_csrf_ht($_POST['csrf_token'] ?? '')) {
        $msg = 'CSRF 토큰이 유효하지 않습니다. 페이지를 새로고침해주세요.';
        $msg_type = 'danger';
    } else {

        $known_actions = ['assign', 'remove', 'update_price', 'toggle_promoted', 'set_min_age', 'image_add', 'image_delete', 'image_move', 'image_approve', 'image_reject'];
        if (!in_array($action, $known_actions, true)) ht_redirect($post_hospital_id, '지원하지 않는 요청입니다.', 'danger');
        if (in_array($action, ['image_approve', 'image_reject'], true) && !$is_super_admin) {
            ht_redirect($post_hospital_id, '시술 이미지 검수는 최고관리자만 처리할 수 있습니다.', 'danger');
        }
        if (in_array($action, ['assign', 'remove', 'update_price', 'toggle_promoted', 'set_min_age', 'image_add', 'image_delete', 'image_move'], true) && !$can_manage_treatments) {
            ht_redirect($post_hospital_id, '현재 시술 관리 설정에서는 이 작업을 수행할 수 없습니다.', 'danger');
        }

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
                    ht_redirect($post_hospital_id, '접근 권한이 없습니다.', 'danger'); // exits internally
                }
            }
        }

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
                    $chk = mysqli_prepare($connect, "SELECT id, status, is_active FROM gangnamunni_hospital_treatments WHERE hospital_id = ? AND treatment_id = ?");
                    mysqli_stmt_bind_param($chk, 'ii', $post_hospital_id, $tid);
                    mysqli_stmt_execute($chk);
                    $chk_r = mysqli_stmt_get_result($chk);
                    if ($existing = mysqli_fetch_assoc($chk_r)) {
                        mysqli_stmt_close($chk);
                        if (intval($existing['is_active']) === 0 || in_array($existing['status'], ['inactive', 'rejected'], true)) {
                            $new_status = 'active';
                            $existing_id = intval($existing['id']);

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
                ht_redirect($post_hospital_id, $msg);
            }
        }

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

        if ($action === 'update_price') {
            if (!$regular_price_column || !$representative_price_column) {
                ht_redirect($post_hospital_id, '가격 컬럼을 준비하지 못했습니다. DB 마이그레이션을 확인해주세요.', 'danger');
            }
            $id = intval($_POST['id'] ?? 0);
            $in_dv = $_POST['dosage_value'] ?? [];
            $in_du = $_POST['dosage_unit'] ?? [];
            $in_rp = $_POST['regular_price'] ?? [];
            $in_pr = $_POST['price'] ?? [];
            $in_rep = $_POST['is_representative'] ?? [];
            $in_on = $_POST['option_name'] ?? [];
            $in_bp = $_POST['body_part'] ?? [];
            if (!is_array($in_dv)) $in_dv = [$in_dv];
            if (!is_array($in_du)) $in_du = [$in_du];
            if (!is_array($in_rp)) $in_rp = [$in_rp];
            if (!is_array($in_pr)) $in_pr = [$in_pr];
            if (!is_array($in_rep)) $in_rep = [$in_rep];
            if (!is_array($in_on)) $in_on = [$in_on];
            if (!is_array($in_bp)) $in_bp = [$in_bp];

            $options = [];
            $cnt = max(count($in_dv), count($in_rp), count($in_pr));
            for ($i = 0; $i < $cnt; $i++) {
                $dv = (isset($in_dv[$i]) && trim((string)$in_dv[$i]) !== '') ? floatval($in_dv[$i]) : 0;
                $du = isset($in_du[$i]) ? trim((string)$in_du[$i]) : '';
                $rp_won = (isset($in_rp[$i]) && trim((string)$in_rp[$i]) !== '') ? max(0, intval($in_rp[$i])) : 0;
                $pr_won = (isset($in_pr[$i]) && trim((string)$in_pr[$i]) !== '') ? max(0, intval($in_pr[$i])) : 0;
                $on = isset($in_on[$i]) ? trim((string)$in_on[$i]) : '';
                $bp = isset($in_bp[$i]) ? trim((string)$in_bp[$i]) : '';
                if ($dv > 0 && $du !== '') {
                    $options[] = [
                        'name' => mb_substr($on, 0, 100, 'UTF-8'),
                        'body_part' => mb_substr($bp, 0, 500, 'UTF-8'),
                        'dv' => $dv,
                        'du' => mb_substr($du, 0, 20, 'UTF-8'),
                        'rp' => $rp_won,
                        'pr' => $pr_won,
                        'rep' => !empty($in_rep[$i]) ? 1 : 0,
                    ];
                }
            }

            if ($id <= 0) {
                $msg = '잘못된 요청입니다.'; $msg_type = 'danger';
            } elseif (empty($options)) {
                $msg = '최소 1개의 샷수/용량과 단위를 입력해주세요.'; $msg_type = 'warning';
            } else {
                $option_keys = [];
                $representative_count = 0;
                foreach ($options as $opt) {
                    if ($opt['rp'] > 0 && $opt['pr'] > 0 && $opt['rp'] < $opt['pr']) ht_redirect($post_hospital_id, '정가는 판매가보다 작을 수 없습니다.', 'warning');
                    $representative_count += $opt['rep'];
                    $key = mb_strtolower($opt['name'], 'UTF-8') . '|' . mb_strtolower($opt['body_part'], 'UTF-8') . '|' . $opt['dv'] . '|' . mb_strtolower($opt['du'], 'UTF-8');
                    if (isset($option_keys[$key])) ht_redirect($post_hospital_id, '동일한 옵션명, 부위, 용량, 단위의 가격 옵션을 중복 등록할 수 없습니다.', 'warning');
                    $option_keys[$key] = true;
                }
                if ($representative_count > 1) ht_redirect($post_hospital_id, '대표 가격 옵션은 하나만 선택할 수 있습니다.', 'warning');
                $old_stmt = mysqli_prepare($connect, "SELECT id FROM gangnamunni_hospital_treatments WHERE id = ?");
                mysqli_stmt_bind_param($old_stmt, 'i', $id);
                mysqli_stmt_execute($old_stmt);
                $old_row = mysqli_fetch_assoc(mysqli_stmt_get_result($old_stmt));
                mysqli_stmt_close($old_stmt);
                if (!$old_row) ht_redirect($post_hospital_id, '대상 시술을 찾을 수 없습니다.', 'danger');

                mysqli_begin_transaction($connect);
                try {
                $del = mysqli_prepare($connect, "DELETE FROM gangnamunni_hospital_treatment_prices WHERE hospital_treatment_id = ?");
                mysqli_stmt_bind_param($del, 'i', $id);
                mysqli_stmt_execute($del);
                mysqli_stmt_close($del);

                $ins = mysqli_prepare($connect, $option_name_column && $body_part_column
                    ? "INSERT INTO gangnamunni_hospital_treatment_prices (hospital_treatment_id, option_name, body_part, dosage_value, dosage_unit, regular_price, price, is_representative, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    : ($option_name_column
                        ? "INSERT INTO gangnamunni_hospital_treatment_prices (hospital_treatment_id, option_name, dosage_value, dosage_unit, regular_price, price, is_representative, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                        : "INSERT INTO gangnamunni_hospital_treatment_prices (hospital_treatment_id, dosage_value, dosage_unit, regular_price, price, is_representative, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)"));
                foreach ($options as $so => $opt) {
                    if ($option_name_column && $body_part_column) mysqli_stmt_bind_param($ins, 'issdsiiii', $id, $opt['name'], $opt['body_part'], $opt['dv'], $opt['du'], $opt['rp'], $opt['pr'], $opt['rep'], $so);
                    elseif ($option_name_column) mysqli_stmt_bind_param($ins, 'isdsiiii', $id, $opt['name'], $opt['dv'], $opt['du'], $opt['rp'], $opt['pr'], $opt['rep'], $so);
                    else mysqli_stmt_bind_param($ins, 'idsiiii', $id, $opt['dv'], $opt['du'], $opt['rp'], $opt['pr'], $opt['rep'], $so);
                    ht_execute_or_throw($ins, '가격 옵션 저장 실패');
                }
                mysqli_stmt_close($ins);

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
                ht_redirect($post_hospital_id, '가격이 수정되었습니다.');
            }
        }

        if ($action === 'set_min_age') {
            // 예약 가능 최소 만 나이. 비우거나 0 이면 제한 없음(NULL).
            $id = intval($_POST['id'] ?? 0);
            $raw_age = trim((string)($_POST['min_age'] ?? ''));
            if ($id <= 0 || $post_hospital_id <= 0) ht_redirect($post_hospital_id, '잘못된 요청입니다.', 'danger');
            if ($raw_age !== '' && !preg_match('/^\d{1,3}$/', $raw_age)) ht_redirect($post_hospital_id, '나이는 숫자로 입력해주세요.', 'danger');
            $age_val = ($raw_age === '' || (int)$raw_age === 0) ? null : min(120, (int)$raw_age);
            // 컬럼은 sql/hospital_treatment_min_age_20261021.sql 로 추가한다(요청 중 DDL 실행 안 함).
            $col_chk = mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_hospital_treatments LIKE 'min_age'");
            if (!$col_chk || mysqli_num_rows($col_chk) === 0) {
                ht_redirect($post_hospital_id, '나이 제한 컬럼이 아직 없습니다. sql/hospital_treatment_min_age_20261021.sql 을 먼저 실행해주세요.', 'danger');
            }
            $ok = false;
            $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_hospital_treatments SET min_age = ?, updated_at = NOW() WHERE id = ? AND hospital_id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'iii', $age_val, $id, $post_hospital_id);
                $ok = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
            ht_redirect($post_hospital_id, $ok ? ($age_val === null ? '나이 제한이 해제되었습니다.' : '예약 가능 최소 나이가 만 ' . $age_val . '세로 설정되었습니다.') : '저장하지 못했습니다.', $ok ? 'success' : 'danger');
        }

        if ($action === 'toggle_promoted') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = mysqli_prepare($connect,
                    "UPDATE gangnamunni_hospital_treatments SET is_promoted = IF(is_promoted = 1, 0, 1), updated_at = NOW() WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                ht_redirect($post_hospital_id, '프로모션 상태가 변경되었습니다.');
            }
        }

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
                            ht_redirect($post_hospital_id, $image_message);
                        }
                        $msg = '이미지 등록에 실패했습니다.';
                        $msg_type = 'danger';
                    }
                }
            }
        }

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
                ht_redirect($post_hospital_id, '시술 이미지가 삭제되었습니다.');
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

// Failed validation also returns to the hospital panel with its error visible.
if (!$ht_panel && $ht_return_mode !== '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    ht_redirect($hospital_id, $msg !== '' ? $msg : '요청을 처리하지 못했습니다.', $msg !== '' ? $msg_type : 'danger');
}
if ($ht_panel && !empty($_GET['treatment_msg'])) {
    $msg = (string)$_GET['treatment_msg'];
    $msg_type = in_array($_GET['treatment_msg_type'] ?? '', ['success', 'danger', 'warning', 'info'], true)
        ? $_GET['treatment_msg_type'] : 'success';
}

if (!$ht_panel && !empty($_GET['msg'])) {
    $msg = $_GET['msg'];
    $msg_type = in_array($_GET['msg_type'] ?? '', ['success', 'danger', 'warning', 'info'], true) ? $_GET['msg_type'] : 'success';
}

$assigned_treatments = [];
$stats = ['total_assigned' => 0, 'promoted' => 0];
$selected_hospital_name = '';

if ($hospital_id > 0) {
    foreach ($hospitals as $h) {
        if (intval($h['id']) === $hospital_id) {
            $selected_hospital_name = $h['name'];
            break;
        }
    }

    $stat_sql = "SELECT
        COUNT(CASE WHEN status = 'active' THEN 1 END) AS total_assigned,
        COUNT(CASE WHEN is_promoted = 1 AND status = 'active' THEN 1 END) AS promoted
        FROM gangnamunni_hospital_treatments WHERE hospital_id = ? AND is_active = 1";
    $stat_stmt = mysqli_prepare($connect, $stat_sql);
    mysqli_stmt_bind_param($stat_stmt, 'i', $hospital_id);
    mysqli_stmt_execute($stat_stmt);
    $stat_r = mysqli_stmt_get_result($stat_stmt);
    if ($s = mysqli_fetch_assoc($stat_r)) {
        $stats['total_assigned'] = intval($s['total_assigned']);
        $stats['promoted'] = intval($s['promoted']);
    }
    mysqli_stmt_close($stat_stmt);

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

$ht_prices = [];
if (!empty($assigned_treatments)) {
    $ht_ids = array_map(function ($r) { return intval($r['id']); }, $assigned_treatments);
    $ht_in = implode(',', $ht_ids);
    try {
        $pr_r = mysqli_query($connect,
            "SELECT hospital_treatment_id, " . ($option_name_column ? "option_name" : "'' AS option_name") . ", " . ($body_part_column ? "body_part" : "'' AS body_part") . ", dosage_value, dosage_unit, " . ($regular_price_column ? "regular_price" : "price AS regular_price") . ", price, " . ($representative_price_column ? "is_representative" : "0 AS is_representative") . "
             FROM gangnamunni_hospital_treatment_prices
             WHERE hospital_treatment_id IN ({$ht_in})
             ORDER BY sort_order ASC, id ASC");
        if ($pr_r) while ($pr = mysqli_fetch_assoc($pr_r)) {
            $ht_prices[intval($pr['hospital_treatment_id'])][] = [
                'dosage_value' => rtrim(rtrim(number_format((float)$pr['dosage_value'], 2, '.', ''), '0'), '.'),
                'option_name'  => $pr['option_name'] ?? '',
                'body_part'    => $pr['body_part'] ?? '',
                'dosage_unit'  => $pr['dosage_unit'],
                'regular_price'=> intval($pr['regular_price']),
                'price'        => intval($pr['price']),
                'is_representative' => intval($pr['is_representative']),
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

$global_stats = [];
$top_hospitals = [];
if ($hospital_id <= 0 && $my_ids === null) {
    $gs_r = mysqli_query($connect, "SELECT
        COUNT(DISTINCT hospital_id) AS hospital_count,
        COUNT(*) AS total_assignments,
        COUNT(CASE WHEN status = 'active' AND is_promoted = 1 THEN 1 END) AS promoted_count
        FROM gangnamunni_hospital_treatments");
    if ($gs_r) $global_stats = mysqli_fetch_assoc($gs_r);

    $top_sql = "SELECT h.id, h.name, COUNT(ht.id) AS treatment_count,
        COUNT(CASE WHEN ht.is_promoted = 1 THEN 1 END) AS promoted_count
        FROM gangnamunni_hospital_treatments ht
        JOIN gangnamunni_hospitals h ON ht.hospital_id = h.id
        WHERE ht.status = 'active'
        GROUP BY h.id, h.name
        ORDER BY treatment_count DESC
        LIMIT 10";
    $top_r = mysqli_query($connect, $top_sql);
    if ($top_r) while ($row = mysqli_fetch_assoc($top_r)) $top_hospitals[] = $row;
}

// 병원이 등록한 상세페이지 이미지 중 검수 대기 목록 (최고관리자 전용, 병원 미선택 화면)
$pending_reviews = [];
$pending_review_total = 0;
if ($hospital_id <= 0 && $is_super_admin && !$ht_panel) {
    $pr_r = mysqli_query($connect, "SELECT i.hospital_id, h.name AS hospital_name, i.hospital_treatment_id AS ht_id,
            tm.name AS treatment_name, COUNT(*) AS pending_count, MIN(i.created_at) AS first_requested_at
        FROM gangnamunni_hospital_treatment_images i
        JOIN gangnamunni_hospital_treatments ht ON ht.id = i.hospital_treatment_id
        JOIN gangnamunni_hospitals h ON h.id = i.hospital_id
        LEFT JOIN gangnamunni_treatment_master tm ON tm.id = i.treatment_id
        WHERE i.approval_status = 'pending' AND ht.status = 'active' AND ht.is_active = 1
        GROUP BY i.hospital_id, h.name, i.hospital_treatment_id, tm.name
        ORDER BY first_requested_at ASC
        LIMIT 200");
    if ($pr_r) while ($row = mysqli_fetch_assoc($pr_r)) {
        $pending_reviews[] = $row;
        $pending_review_total += intval($row['pending_count']);
    }
}

// 검수 목록에서 이동한 경우 해당 시술의 이미지 창을 바로 연다
$review_open = null;
$review_ht_id = intval($_GET['review_ht'] ?? 0);
if ($review_ht_id > 0 && !$ht_panel) {
    foreach ($assigned_treatments as $at_row) {
        if (intval($at_row['id']) === $review_ht_id) {
            $review_open = ['id' => $review_ht_id, 'name' => (string)($at_row['treatment_name'] ?? '')];
            break;
        }
    }
}

$ht_form_action = 'admin_hospital_treatments.php?hospital_id=' . $hospital_id
    . ($ht_return_mode !== '' ? '&hospital_panel=' . $ht_return_mode : '');
if (!$ht_panel) require_once __DIR__ . '/includes/admin_header.php';
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

    <style>
        .ht-hospital-picker {border:1px solid var(--bs-border-color);border-radius:16px;background:var(--bs-body-bg);padding:24px;margin-bottom:24px;}
        .ht-picker-heading {display:flex;align-items:center;gap:12px;margin-bottom:22px;}
        .ht-hospital-icon {display:flex;align-items:center;justify-content:center;flex-shrink:0;width:42px;height:42px;border-radius:12px;background:rgba(var(--bs-primary-rgb),.09);color:var(--bs-primary);font-size:19px;}
        .ht-picker-heading h6 {margin:0 0 4px;font-weight:700;}
        .ht-picker-heading p {margin:0;color:var(--bs-secondary-color);font-size:13px;}
        .ht-picker-fields {display:grid;grid-template-columns:minmax(0,1.5fr) minmax(0,1fr);gap:24px;align-items:start;}
        .ht-picker-fields label {font-size:13px;font-weight:600;margin-bottom:8px;display:block;}
        .ht-search-form {position:relative;min-width:0;}
        .ht-search-box {display:flex;align-items:center;gap:10px;padding:5px 6px 5px 14px;border:1px solid var(--bs-border-color);border-radius:10px;background:var(--bs-tertiary-bg);transition:border-color .15s,box-shadow .15s;}
        .ht-search-box:focus-within {border-color:var(--bs-primary);box-shadow:0 0 0 3px rgba(var(--bs-primary-rgb),.12);}
        .ht-search-box > i {color:var(--bs-secondary-color);}
        .ht-search-box input {min-width:0;width:100%;border:0;outline:0;background:transparent;color:var(--bs-body-color);font-size:14px;height:34px;}
        .ht-search-box .btn {flex-shrink:0;border-radius:7px;padding:7px 16px;font-size:13px;}
        .ht-picker-select {display:flex;gap:8px;}
        .ht-picker-select .form-select {min-width:0;height:46px;border-radius:10px;font-size:14px;}
        .ht-picker-select .btn {flex-shrink:0;border-radius:10px;font-size:13px;}
        .ht-search-popover {position:absolute;top:100%;left:0;right:0;margin-top:8px;z-index:1040;border:1px solid var(--bs-border-color);border-radius:12px;background:var(--bs-body-bg);box-shadow:0 12px 32px rgba(0,0,0,.12);overflow:hidden;}
        .ht-search-status {display:block;padding:12px 16px;font-size:12px;color:var(--bs-secondary-color);border-bottom:1px solid var(--bs-border-color);}
        .ht-search-results {max-height:300px;overflow-y:auto;padding:6px;}
        .ht-search-result {display:flex;align-items:center;gap:12px;padding:12px 10px;border-radius:8px;color:var(--bs-body-color);text-decoration:none;}
        .ht-search-result:hover,.ht-search-result:focus-visible {background:rgba(var(--bs-primary-rgb),.08);color:var(--bs-body-color);outline:2px solid var(--bs-primary);outline-offset:-2px;}
        .ht-result-copy {min-width:0;flex:1;}
        .ht-result-copy strong {display:block;font-size:14px;overflow-wrap:anywhere;}
        .ht-result-copy small {display:block;margin-top:3px;color:var(--bs-secondary-color);font-size:11px;}
        .ht-result-action {flex-shrink:0;font-size:12px;color:var(--bs-primary);}
        .ht-picker-current {display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:18px;padding-top:16px;border-top:1px solid var(--bs-border-color);font-size:13px;}
        .ht-picker-current strong {overflow-wrap:anywhere;}
        .ht-picker-current a {margin-left:auto;color:var(--bs-secondary-color);text-decoration:none;font-size:12px;}
        .ht-search-empty {padding:20px 16px;text-align:center;font-size:13px;color:var(--bs-secondary-color);}
        @media(max-width:767.98px) {
            .ht-hospital-picker {padding:18px;}
            .ht-picker-fields {grid-template-columns:minmax(0,1fr);gap:18px;}
            .ht-result-action {font-size:11px;}
            .ht-search-result {gap:8px;}
        }
    </style>
    <?php if (!$ht_panel): ?>
    <section class="ht-hospital-picker" aria-label="병원 선택">
        <div class="ht-picker-heading">
            <span class="ht-hospital-icon" aria-hidden="true"><i class="bi bi-hospital"></i></span>
            <div><h6>시술을 관리할 병원</h6><p>병원을 검색하거나 목록에서 선택해주세요.</p></div>
        </div>
        <div class="ht-picker-fields">
            <form id="hospitalSearchForm" class="ht-search-form" role="search">
                <label for="hospitalSearch">병원 검색</label>
                <div class="ht-search-box">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input type="search" id="hospitalSearch" placeholder="병원명으로 빠르게 검색" autocomplete="off" aria-controls="hospitalSearchResults" aria-describedby="hospitalSearchStatus">
                    <button type="submit" class="btn btn-primary" aria-label="병원 검색">검색</button>
                </div>
                <div id="hospitalSearchPopover" class="ht-search-popover" hidden>
                    <small id="hospitalSearchStatus" class="ht-search-status" role="status" aria-live="polite"></small>
                    <div id="hospitalSearchResults" class="ht-search-results"></div>
                </div>
            </form>
            <form method="get">
                <label for="hospitalSelect">병원 선택</label>
                <div class="ht-picker-select">
                    <select id="hospitalSelect" name="hospital_id" class="form-select" onchange="this.form.submit()">
                        <option value="">전체 병원 목록에서 선택</option>
                        <?php foreach ($hospitals as $h): ?>
                        <option value="<?php echo intval($h['id']); ?>" <?php echo $hospital_id === intval($h['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($h['name']); ?> (ID: <?php echo intval($h['id']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">조회</button>
                </div>
            </form>
        </div>
        <?php if ($hospital_id > 0 && $selected_hospital_name !== ''): ?>
        <div class="ht-picker-current">
            <i class="bi bi-check-circle-fill text-primary" aria-hidden="true"></i>
            <span class="text-muted">선택된 병원</span><strong><?php echo htmlspecialchars($selected_hospital_name); ?></strong>
            <a href="admin_hospital_treatments.php"><i class="bi bi-arrow-counterclockwise me-1"></i>초기화</a>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

<?php if ($hospital_id <= 0): ?>
    <!-- ===== 병원 미선택: 전체 요약 ===== -->

    <?php if ($is_super_admin): ?>
    <!-- 상세페이지 이미지 검수 대기 (병원 등록분) -->
    <div class="card border-0 shadow-sm mb-4 <?php echo ($pending_review_total ?? 0) > 0 ? 'border-start border-warning border-4' : ''; ?>" id="pendingReviewSection">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-hourglass-split me-2 text-warning"></i>상세 이미지 검수 대기</h6>
            <span class="badge <?php echo ($pending_review_total ?? 0) > 0 ? 'bg-warning text-dark' : 'bg-secondary'; ?>"><?php echo number_format($pending_review_total ?? 0); ?>건</span>
        </div>
        <?php if (empty($pending_reviews)): ?>
        <div class="card-body text-muted small"><i class="bi bi-check2-circle me-1 text-success"></i>검수 대기 중인 이미지가 없습니다.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>병원</th>
                        <th>시술</th>
                        <th class="text-center" style="width:90px;">대기</th>
                        <th style="width:150px;">최초 요청</th>
                        <th style="width:110px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($pending_reviews ?? []) as $pr): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($pr['hospital_name']); ?></strong> <small class="text-muted">#<?php echo intval($pr['hospital_id']); ?></small></td>
                        <td><?php echo htmlspecialchars($pr['treatment_name'] ?? '(삭제된 시술)'); ?></td>
                        <td class="text-center"><span class="badge bg-warning text-dark"><?php echo intval($pr['pending_count']); ?>장</span></td>
                        <td><small class="text-muted"><?php echo $pr['first_requested_at'] ? date('Y-m-d H:i', strtotime($pr['first_requested_at'])) : '-'; ?></small></td>
                        <td>
                            <a class="btn btn-warning btn-sm" href="admin_hospital_treatments.php?hospital_id=<?php echo intval($pr['hospital_id']); ?>&amp;review_ht=<?php echo intval($pr['ht_id']); ?>">
                                <i class="bi bi-search me-1"></i>검수하기
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
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
                        <th class="text-center" style="width:170px;">상세 이미지</th>
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
                                <span class="badge bg-light text-dark border"><?php if (!empty($op['is_representative'])): ?><i class="bi bi-check-square-fill text-primary me-1"></i><?php endif; ?><?php echo htmlspecialchars(($op['option_name'] !== '' ? $op['option_name'] . ' · ' : '') . ($op['body_part'] !== '' ? str_replace('||', ', ', $op['body_part']) . ' · ' : '') . $op['dosage_value'] . $op['dosage_unit']); ?> · 정가 <?php echo ht_price_won($op['regular_price']) > 0 ? number_format(ht_price_won($op['regular_price'])) . '원' : '미입력'; ?> / 판매가 <?php echo ht_price_won($op['price']) > 0 ? number_format(ht_price_won($op['price'])) . '원' : '미입력'; ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <span class="text-muted small">가격 옵션 미등록</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <form method="post" action="<?= htmlspecialchars($ht_form_action, ENT_QUOTES, 'UTF-8') ?>" class="d-inline">
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
                                <span class="text-nowrap">+ 상세 이미지 추가</span><?php if ($img_cnt > 0): ?> <span class="badge bg-success ms-1"><?php echo $img_cnt; ?></span><?php endif; ?>
                            </button>
                            <?php if ($pending_img_cnt > 0): ?><span class="badge bg-warning text-dark ms-1"><?php echo $pending_img_cnt; ?> 검수</span><?php endif; ?>
                        </td>
                        <td class="text-center">
                            <!-- 가격 수정 -->
                            <button class="btn btn-outline-primary btn-sm" title="가격 수정"
                                onclick="openPriceModal(<?php echo intval($at['id']); ?>, '<?php echo htmlspecialchars(addslashes($at['treatment_name'] ?? ''), ENT_QUOTES); ?>')">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <!-- 예약 가능 최소 나이 (비우면 제한 없음) -->
                            <?php if ($can_manage_treatments): ?>
                            <form method="post" action="<?= htmlspecialchars($ht_form_action, ENT_QUOTES, 'UTF-8') ?>" class="d-inline-flex align-items-center gap-1 me-1">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="set_min_age">
                                <input type="hidden" name="id" value="<?php echo intval($at['id']); ?>">
                                <input type="hidden" name="hospital_id" value="<?php echo $hospital_id; ?>">
                                <input type="number" name="min_age" min="0" max="120" inputmode="numeric" class="form-control form-control-sm" style="width:64px;" placeholder="만나이" title="예약 가능 최소 만 나이 (비우면 제한 없음)" value="<?php echo !empty($at['min_age']) ? (int)$at['min_age'] : ''; ?>">
                                <button type="submit" class="btn btn-outline-secondary btn-sm" title="나이 제한 저장"><i class="bi bi-person-lock"></i></button>
                            </form>
                            <?php endif; ?>
                            <!-- 배정 해제 -->
                            <form method="post" action="<?= htmlspecialchars($ht_form_action, ENT_QUOTES, 'UTF-8') ?>" class="d-inline" onsubmit="return confirm('이 시술 배정을 해제하시겠습니까?');">
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
    <div class="collapse<?php echo $can_manage_treatments && ($_GET['add_treatment'] ?? '') === '1' ? ' show' : ''; ?> mb-4" id="addTreatmentSection">
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
                <form method="post" action="<?= htmlspecialchars($ht_form_action, ENT_QUOTES, 'UTF-8') ?>" id="assignForm">
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
                            <label class="card card-body py-2 px-3" for="t_<?php echo intval($at['id']); ?>" style="cursor:pointer;">
                                <span class="form-check d-block">
                                    <input class="form-check-input treatment-cb" type="checkbox" name="treatment_ids[]" value="<?php echo intval($at['id']); ?>" id="t_<?php echo intval($at['id']); ?>">
                                    <span class="form-check-label">
                                        <strong><?php echo htmlspecialchars($at['name']); ?></strong>
                                        <?php if (!empty($at['hashtag'])): ?>
                                        <small class="text-primary d-block"><?php echo htmlspecialchars($at['hashtag']); ?></small>
                                        <?php endif; ?>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($at['category_name'] ?? '미분류'); ?>
                                        </small>
                                    </span>
                                </span>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>


<?php endif; ?>
</div>

<!-- ===== 가격 수정 모달 ===== -->
<div class="modal fade" id="priceModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <form method="post" action="<?= htmlspecialchars($ht_form_action, ENT_QUOTES, 'UTF-8') ?>">
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
                        <div style="width:32px;" class="text-center">대표</div>
                        <div style="flex:1.4;">옵션명</div>
                        <div style="flex:1;">부위 (복수 선택)</div>
                        <div style="flex:1;">샷수/용량</div>
                        <div style="width:96px;">단위</div>
                        <div style="width:130px;">정가 (원)</div>
                        <div style="width:130px;">판매가 (원)</div>
                        <div style="width:34px;"></div>
                    </div>
                    <div id="priceRows"></div>
                    <button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="addPriceRow()">
                        <i class="bi bi-plus-lg me-1"></i>세부 옵션 추가
                    </button>
                    <div class="form-text mt-2">부위 드롭다운을 열어 여러 개를 선택할 수 있습니다.</div>
                    <div class="form-text mt-2">정가와 판매가는 선택 입력입니다. 입력할 경우 축약하지 않은 원 단위 전체 금액을 사용해주세요 (예: 10000, 250000). 두 금액을 모두 입력하면 정가는 판매가보다 작을 수 없습니다.</div>
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
                <form method="post" action="<?= htmlspecialchars($ht_form_action, ENT_QUOTES, 'UTF-8') ?>" enctype="multipart/form-data" class="border-top pt-3">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="image_add">
                    <input type="hidden" name="id" id="ti_ht_id">
                    <input type="hidden" name="hospital_id" value="<?php echo $hospital_id; ?>">
                    <div class="row g-2 align-items-start">
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

<form method="post" action="<?= htmlspecialchars($ht_form_action, ENT_QUOTES, 'UTF-8') ?>" id="ti_delete_form" class="d-none">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="image_delete">
    <input type="hidden" name="id" id="ti_del_ht_id">
    <input type="hidden" name="image_id" id="ti_del_image_id">
    <input type="hidden" name="hospital_id" value="<?php echo $hospital_id; ?>">
</form>
<form method="post" action="<?= htmlspecialchars($ht_form_action, ENT_QUOTES, 'UTF-8') ?>" id="ti_move_form" class="d-none">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="image_move">
    <input type="hidden" name="id" id="ti_move_ht_id">
    <input type="hidden" name="image_id" id="ti_move_image_id">
    <input type="hidden" name="direction" id="ti_move_direction">
    <input type="hidden" name="hospital_id" value="<?php echo $hospital_id; ?>">
</form>
<form method="post" action="<?= htmlspecialchars($ht_form_action, ENT_QUOTES, 'UTF-8') ?>" id="ti_review_form" class="d-none">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" id="ti_review_action">
    <input type="hidden" name="id" id="ti_review_ht_id">
    <input type="hidden" name="image_id" id="ti_review_image_id">
    <input type="hidden" name="reject_reason" id="ti_review_reason">
    <input type="hidden" name="hospital_id" value="<?php echo $hospital_id; ?>">
</form>

<script type="application/json" id="hospitalTreatmentData"><?= json_encode(['images' => $ht_images, 'prices' => $ht_prices, 'isSuperAdmin' => $is_super_admin], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script>
// Search only the hospitals already allowed by the server's hospital filter.
(function () {
    var hospitals = <?php echo json_encode($hospitals, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var input = document.getElementById('hospitalSearch');
    var results = document.getElementById('hospitalSearchResults');
    var status = document.getElementById('hospitalSearchStatus');
    var popover = document.getElementById('hospitalSearchPopover');
    var form = document.getElementById('hospitalSearchForm');
    if (!input || !form) return;
    var canManage = <?php echo $can_manage_treatments ? 'true' : 'false'; ?>;
    function normalize(value) {
        return String(value).normalize('NFKC').toLocaleLowerCase().replace(/\s+/g, '');
    }
    function searchHospitals() {
        var query = normalize(input.value);
        results.replaceChildren();
        popover.hidden = !query;
        status.textContent = '';
        if (!query) return;
        var matches = hospitals.filter(function (hospital) {
            return normalize(hospital.name).includes(query);
        });
        status.textContent = matches.length ? '검색 결과 · ' + matches.length + '개 병원' : '검색 결과가 없습니다';
        if (!matches.length) {
            var empty = document.createElement('div');
            empty.className = 'ht-search-empty';
            empty.textContent = '다른 병원명으로 다시 검색해보세요.';
            results.appendChild(empty);
        }
        matches.forEach(function (hospital) {
            var link = document.createElement('a');
            link.className = 'ht-search-result';
            link.href = 'admin_hospital_treatments.php?hospital_id=' + encodeURIComponent(hospital.id)
                + (canManage ? '&add_treatment=1#addTreatmentSection' : '');
            var icon = document.createElement('span');
            icon.className = 'ht-hospital-icon';
            icon.setAttribute('aria-hidden', 'true');
            icon.innerHTML = '<i class="bi bi-hospital"></i>';
            var copy = document.createElement('span');
            copy.className = 'ht-result-copy';
            var name = document.createElement('strong');
            name.textContent = hospital.name;
            var meta = document.createElement('small');
            meta.textContent = '병원 ID · ' + hospital.id;
            copy.append(name, meta);
            var action = document.createElement('span');
            action.className = 'ht-result-action';
            action.textContent = canManage ? '시술 추가 →' : '선택 →';
            link.append(icon, copy, action);
            results.appendChild(link);
        });
    }
    input.addEventListener('input', searchHospitals);
    input.addEventListener('focus', searchHospitals);
    document.addEventListener('click', function (event) {
        if (!form.contains(event.target)) popover.hidden = true;
    });
    // Some browsers blur the input with relatedTarget=null when a result is
    // pressed. Keep the link visible until its native click can navigate.
    // Close for keyboard users once focus actually enters an outside element.
    document.addEventListener('focusin', function (event) {
        if (!form.contains(event.target)) popover.hidden = true;
    });
    form.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            input.focus();
            popover.hidden = true;
        }
        if (event.target.closest('.ht-search-result') && ['ArrowDown', 'ArrowUp'].includes(event.key)) {
            event.preventDefault();
            var links = Array.from(results.querySelectorAll('a'));
            var index = links.indexOf(event.target.closest('a'));
            var next = event.key === 'ArrowDown' ? index + 1 : index - 1;
            if (next < 0) input.focus();
            else if (links[next]) links[next].focus();
        }
    });
    document.getElementById('hospitalSearchForm').addEventListener('submit', function (event) {
        event.preventDefault();
        searchHospitals();
        var first = results.querySelector('a');
        if (first) first.focus();
    });
    input.addEventListener('keydown', function (event) {
        if (event.key === 'ArrowDown') {
            if (popover.hidden) searchHospitals();
            var first = results.querySelector('a');
            if (first) { event.preventDefault(); first.focus(); }
        }
    });
})();

var HT_IMAGES = <?php echo json_encode($ht_images, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
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
    document.getElementById('ti_review_form').requestSubmit();
}

function moveTreatmentImage(htId, imageId, direction) {
    document.getElementById('ti_move_ht_id').value = htId;
    document.getElementById('ti_move_image_id').value = imageId;
    document.getElementById('ti_move_direction').value = direction;
    document.getElementById('ti_move_form').requestSubmit();
}

function deleteTreatmentImage(htId, imageId) {
    if (!confirm('이 이미지를 삭제하시겠습니까?')) return;
    document.getElementById('ti_del_ht_id').value = htId;
    document.getElementById('ti_del_image_id').value = imageId;
    document.getElementById('ti_delete_form').requestSubmit();
}

var HT_PRICES = <?php echo json_encode($ht_prices, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var PRICE_UNITS = <?php echo json_encode($price_units, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
// 단위 정렬: 영문(abc)이 먼저, 그다음 한글(ㄱㄴㄷ) 순. (PHP treatment_units_compare 와 동일 규칙)
function unitSortGroup(value) {
    var code = String(value || '').charCodeAt(0) || 0;
    var isHangul = (code >= 0x1100 && code <= 0x11FF)
        || (code >= 0x3130 && code <= 0x318F)
        || (code >= 0xAC00 && code <= 0xD7A3);
    return isHangul ? 1 : 0;
}

function sortUnits(list) {
    return list.slice().sort(function(a, b) {
        var groupA = unitSortGroup(a), groupB = unitSortGroup(b);
        if (groupA !== groupB) return groupA - groupB;
        return String(a).localeCompare(String(b), 'ko');
    });
}

var BODY_PART_GROUPS = [
    {label:'얼굴 전체 및 헤어', items:['얼굴 전체 (풀페이스)','헤어라인','두피']},
    {label:'상안면부 (눈/이마 주변)', items:['이마','미간','눈가 (눈웃음 주름)','눈밑 (다크서클, 눈밑 꺼짐)','애교살','관자놀이']},
    {label:'중안면부 (코/볼/팔자 주변)', items:['앞광대','옆광대','코 (콧대, 코끝)','콧볼 (콧볼 축소 등)','팔자주름','인중']},
    {label:'하안면부 (입술/턱 주변)', items:['입술 (입술 볼륨)','입꼬리','사각턱 (턱 근육)','턱끝 (무턱)','이중턱 (턱밑 뱃살)','심부볼 (불독살)','턱선 (마리오넷 주름, 페이스라인)']},
    {label:'바디 (체형 및 레이저 제모 등)', items:['목 (목주름)','승모근 (어깨 라인)','쇄골','겨드랑이','팔 (팔뚝)','복부 (윗배, 아랫배)','러브핸들 (옆구리)','등','허벅지 (안쪽, 바깥쪽)','종아리 (종아리 알)','손등 / 발등']}
];

function priceToWon(price) {
    return Number(price) || 0;
}

var BODY_PART_PICKER_SEQ = 0;

function bodyPartPickerHtml(selectedBodyPart) {
    var selectedItems = selectedBodyPart ? String(selectedBodyPart).split('||').filter(Boolean) : [];
    var knownItems = [];
    var pickerId = 'bodyPartPicker' + (++BODY_PART_PICKER_SEQ);
    var buttonLabel = selectedItems.length === 0 ? '부위 선택' : (selectedItems.length === 1 ? selectedItems[0] : selectedItems[0] + ' 외 ' + (selectedItems.length - 1) + '개');
    var menu = '';
    BODY_PART_GROUPS.forEach(function(group) {
        menu += '<div class="small fw-bold text-muted px-2 pt-2 pb-1">'+tiEscape(group.label)+'</div>';
        group.items.forEach(function(item) {
            knownItems.push(item);
            var checkboxId = pickerId + 'Item' + knownItems.length;
            menu += '<div class="form-check px-2 ms-4 mb-1">' +
                '<input class="form-check-input body-part-check" type="checkbox" value="'+tiEscape(item)+'" id="'+checkboxId+'"'+(selectedItems.indexOf(item) !== -1 ? ' checked' : '')+' onchange="syncBodyParts(this)">' +
                '<label class="form-check-label small" for="'+checkboxId+'">'+tiEscape(item)+'</label></div>';
        });
    });
    selectedItems.forEach(function(item, legacyIndex) {
        if (knownItems.indexOf(item) === -1) {
            var checkboxId = pickerId + 'Legacy' + legacyIndex;
            menu += '<div class="form-check px-2 ms-4 mb-1"><input class="form-check-input body-part-check" type="checkbox" value="'+tiEscape(item)+'" id="'+checkboxId+'" checked onchange="syncBodyParts(this)"><label class="form-check-label small" for="'+checkboxId+'">'+tiEscape(item)+'</label></div>';
        }
    });
    return '<div class="dropdown body-part-picker" style="flex:1;min-width:180px;">' +
        '<button class="btn btn-outline-secondary btn-sm dropdown-toggle w-100 text-start text-truncate body-part-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="'+tiEscape(buttonLabel)+'">'+tiEscape(buttonLabel)+'</button>' +
        '<div class="dropdown-menu p-2 shadow" style="width:310px;max-height:360px;overflow-y:auto;">'+menu+'</div></div>';
}

function syncBodyParts(checkbox) {
    var row = checkbox.closest('.price-row');
    var hidden = row ? row.querySelector('.body-part-value') : null;
    var picker = checkbox.closest('.body-part-picker');
    var toggle = picker ? picker.querySelector('.body-part-toggle') : null;
    if (!hidden || !picker) return;
    var selected = Array.from(picker.querySelectorAll('.body-part-check:checked')).map(function(input){ return input.value; }).filter(Boolean);
    hidden.value = selected.join('||');
    var label = selected.length === 0 ? '부위 선택' : (selected.length === 1 ? selected[0] : selected[0] + ' 외 ' + (selected.length - 1) + '개');
    if (toggle) {
        toggle.textContent = label;
        toggle.title = label;
    }
}

function priceRowHtml(dv, du, regularPrice, pr, optionName, bodyPart, isRepresentative) {
    var units = PRICE_UNITS.slice();
    if (du && units.indexOf(du) === -1) units.push(du);
    units = sortUnits(units);
    var opts = '<option value="">단위 선택</option>' + units.map(function(u){
        return '<option value="'+tiEscape(u)+'"'+(u===du?' selected':'')+'>'+tiEscape(u)+'</option>';
    }).join('');
    return '<div class="d-flex align-items-center mb-2 price-row" style="gap:8px;">' +
        '<div class="d-flex justify-content-center" style="width:32px;">' +
        '<input type="hidden" name="is_representative[]" class="price-representative-value" value="'+(isRepresentative ? '1' : '0')+'">' +
        '<input type="checkbox" class="form-check-input price-representative" aria-label="대표 가격 옵션"'+(isRepresentative ? ' checked' : '')+' onchange="selectRepresentativePrice(this)"></div>' +
        '<input type="text" name="option_name[]" class="form-control form-control-sm" style="flex:1.4;" maxlength="100" placeholder="예: 주름 100샷" value="'+tiEscape(optionName || '')+'">' +
        '<input type="hidden" name="body_part[]" class="body-part-value" value="'+tiEscape(bodyPart || '')+'">' +
        bodyPartPickerHtml(bodyPart) +
        '<input type="number" name="dosage_value[]" class="form-control form-control-sm" style="flex:1;" min="0.01" step="0.01" required placeholder="예: 100" value="'+(dv==null?'':dv)+'">' +
        '<select name="dosage_unit[]" required class="form-select form-select-sm" style="width:96px;">'+opts+'</select>' +
        '<input type="number" name="regular_price[]" class="form-control form-control-sm" style="width:130px;" min="0" step="1" placeholder="선택" value="'+(Number(regularPrice)>0?priceToWon(regularPrice):'')+'">' +
        '<input type="number" name="price[]" class="form-control form-control-sm" style="width:130px;" min="0" step="1" placeholder="선택" value="'+(Number(pr)>0?priceToWon(pr):'')+'">' +
        '<button type="button" class="btn btn-outline-danger btn-sm price-del" style="width:34px;" onclick="removePriceRow(this)"><i class="bi bi-x-lg"></i></button>' +
        '</div>';
}

function addPriceRow(dv, du, regularPrice, pr, optionName, bodyPart, isRepresentative) {
    document.getElementById('priceRows').insertAdjacentHTML('beforeend', priceRowHtml(dv, du || (PRICE_UNITS.indexOf('회') !== -1 ? '회' : ''), regularPrice, pr, optionName, bodyPart, isRepresentative));
    refreshPriceDelButtons();
    refreshRepresentativeCheckboxes();
}

function selectRepresentativePrice(checkbox) {
    var row = checkbox.closest('.price-row');
    var hidden = row ? row.querySelector('.price-representative-value') : null;
    if (hidden) hidden.value = checkbox.checked ? '1' : '0';
    refreshRepresentativeCheckboxes();
}

function refreshRepresentativeCheckboxes() {
    var boxes = Array.from(document.querySelectorAll('#priceRows .price-representative'));
    var selected = boxes.find(function(box){ return box.checked; });
    boxes.forEach(function(box){ box.disabled = !!selected && box !== selected; });
}

function removePriceRow(btn) {
    var row = btn.closest('.price-row');
    if (row) row.remove();
    refreshPriceDelButtons();
    refreshRepresentativeCheckboxes();
}

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
    if (!list.length) list = [{dosage_value:'', dosage_unit:(PRICE_UNITS.indexOf('회') !== -1 ? '회' : ''), regular_price:'', price:'', body_part:'', is_representative:0}];
    list.forEach(function(o){ addPriceRow(o.dosage_value, o.dosage_unit, o.regular_price, o.price, o.option_name, o.body_part, Number(o.is_representative) === 1); });
    new bootstrap.Modal(document.getElementById('priceModal')).show();
}

function filterCategory(e, catId) {
    e.preventDefault();
    document.querySelectorAll('#catTabs .nav-link').forEach(function(el) { el.classList.remove('active'); });
    e.target.classList.add('active');
    document.querySelectorAll('.treatment-item').forEach(function(el) {
        if (catId === 'all' || parseInt(el.dataset.cat) === catId) {
            el.style.display = '';
        } else {
            el.style.display = 'none';
        }
    });
    document.getElementById('selectAll').checked = false;
}

<?php if (!empty($review_open)): ?>
window.addEventListener('load', function() {
    openTreatmentImageModal(<?php echo intval($review_open['id']); ?>, <?php echo json_encode($review_open['name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
});
<?php endif; ?>

function toggleSelectAll(el) {
    document.querySelectorAll('.treatment-item').forEach(function(item) {
        if (item.style.display !== 'none') {
            var cb = item.querySelector('.treatment-cb');
            if (cb) cb.checked = el.checked;
        }
    });
}
</script>

<?php if (!$ht_panel) require_once __DIR__ . '/includes/admin_footer.php'; ?>
