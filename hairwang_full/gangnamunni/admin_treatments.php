<?php
/**
 * 시술 마스터 관리 페이지 (admin_treatments.php)
 * - 시술 목록, 등록, 수정, 삭제, 활성/비활성 토글, 카테고리 탭 필터
 * - 별칭(aliases) 관리, 벌크 액션
 * Bootstrap 5.3 + 공통 관리자 레이아웃
 */
session_start();

$admin_page_title = '시술 마스터 관리';
$admin_current = 'treatment_master';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/hospital_treatment_images.php';

// DB 연결
if (!isset($connect) || !$connect) {
    $connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    if ($connect) mysqli_set_charset($connect, 'utf8mb4');
}
if (!$connect) die('DB 연결 실패');

loadThemeSettings($connect);
require_admin_login();
require_permission('treatments');
// 시술 마스터는 모든 병원이 공유하는 전역 카탈로그 → 최고관리자 전용 (병원 계정은 partner_treatments.php 에서 자기 병원 시술만 관리)
if (($_SESSION['ad_admin_role'] ?? '') !== 'super_admin') {
    require_platform_admin_page();
    http_response_code(403);
    exit('시술 마스터는 최고관리자만 관리할 수 있습니다.');
}
$treatment_image_schema_ready = hti_ensure_schema($connect);

// [05-15/05-16] 재시술 간격(일)과 필수 여부는 시술 마스터에서 관리한다. 컬럼은 sql/treatment_master_revisit_20261021.sql 로 추가한다.
function tm_revisit_column_exists($connect) {
    $r = @mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_treatment_master LIKE 'revisit_days'");
    return $r && mysqli_num_rows($r) > 0;
}
// 입력 정규화: 비우거나 0 = 제한 없음(NULL). 1~3650일. 잘못된 값이면 false.
function tm_parse_revisit($raw_days, $strict_flag) {
    $raw_days = trim((string)$raw_days);
    if ($raw_days !== '' && !preg_match('/^\d{1,4}$/', $raw_days)) return false;
    $days = ($raw_days === '' || (int)$raw_days === 0) ? null : min(3650, (int)$raw_days);
    return ['days' => $days, 'strict' => ($days !== null && (string)$strict_flag === '1') ? 1 : 0];
}
function tm_save_revisit($connect, $id, array $rv) {
    if ($id <= 0 || !tm_revisit_column_exists($connect)) return false;
    $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_treatment_master SET revisit_days = ?, revisit_strict = ? WHERE id = ?");
    if (!$stmt) return false;
    mysqli_stmt_bind_param($stmt, 'iii', $rv['days'], $rv['strict'], $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}


// CSRF 토큰 생성
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// CSRF 검증 함수
function verify_csrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
}

// 카테고리 목록 조회
$categories = [];
$cat_r = mysqli_query($connect, "SELECT * FROM gangnamunni_treatment_categories WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
if ($cat_r) while ($c = mysqli_fetch_assoc($cat_r)) $categories[$c['id']] = $c;

// DB ENUM 값(none/local/sedation/general)은 그대로 저장하고 화면에만 한국어로 표시한다.
$anesthesia_types = [
    'none' => '없음',
    'local' => '국소마취',
    'sedation' => '수면마취',
    'general' => '전신마취',
];

// 언어 목록
$lang_options = ['ko' => '한국어', 'en' => '영어', 'zh' => '중국어', 'ja' => '일본어'];

$msg = '';
$msg_type = 'success';

// ===== POST Actions =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // CSRF 검증
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $msg = 'CSRF 토큰이 유효하지 않습니다. 페이지를 새로고침해주세요.';
        $msg_type = 'danger';
    } else {

        // 시술 테이블 표시 모드 변경
        if ($action === 'save_table_mode') {
            $allowed = ['excel', 'row', 'hybrid'];
            $mode = in_array($_POST['tc_table_mode'] ?? '', $allowed) ? $_POST['tc_table_mode'] : 'excel';
            $escaped = mysqli_real_escape_string($connect, $mode);
            $chk = mysqli_query($connect, "SELECT id FROM gangnamunni_settings WHERE setting_key = 'tc_table_mode'");
            if ($chk && mysqli_num_rows($chk) > 0) {
                mysqli_query($connect, "UPDATE gangnamunni_settings SET setting_value = '{$escaped}' WHERE setting_key = 'tc_table_mode'");
            } else {
                mysqli_query($connect, "INSERT INTO gangnamunni_settings (setting_key, setting_value, setting_group, description) VALUES ('tc_table_mode', '{$escaped}', 'home', '홈 시술 카테고리 테이블 표시 모드')");
            }
            header('Location: admin_treatments.php?tab=categories&msg=' . urlencode('테이블 표시 모드가 변경되었습니다.'));
            exit;
        }

        // [05-18] 같은 날 함께 예약하기 어려운 시술 조합 추가/삭제 (전체 병원 공통)
        if ($action === 'conflict_add' || $action === 'conflict_delete') {
            $tbl_chk = mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_treatment_conflicts'");
            if (!$tbl_chk || mysqli_num_rows($tbl_chk) === 0) {
                header('Location: admin_treatments.php?tab=conflicts&msg=' . urlencode('조합 테이블이 아직 없습니다. sql/treatment_conflicts_20261023.sql 을 먼저 실행해주세요.') . '&msg_type=danger');
                exit;
            }
            if ($action === 'conflict_delete') {
                $cid = intval($_POST['conflict_id'] ?? 0);
                if ($cid > 0) {
                    $stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_treatment_conflicts WHERE id = ?");
                    if ($stmt) { mysqli_stmt_bind_param($stmt, 'i', $cid); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt); }
                }
                header('Location: admin_treatments.php?tab=conflicts&msg=' . urlencode('조합이 삭제되었습니다.'));
                exit;
            }
            $a = intval($_POST['tm_a'] ?? 0);
            $b = intval($_POST['tm_b'] ?? 0);
            if ($a <= 0 || $b <= 0 || $a === $b) {
                header('Location: admin_treatments.php?tab=conflicts&msg=' . urlencode('서로 다른 두 시술을 선택해주세요.') . '&msg_type=danger');
                exit;
            }
            if ($a > $b) { $tmp = $a; $a = $b; $b = $tmp; }
            $exists = 0;
            $chk = mysqli_prepare($connect, "SELECT COUNT(*) FROM gangnamunni_treatment_master WHERE id IN (?, ?)");
            if ($chk) {
                mysqli_stmt_bind_param($chk, 'ii', $a, $b);
                mysqli_stmt_execute($chk);
                mysqli_stmt_bind_result($chk, $exists);
                mysqli_stmt_fetch($chk);
                mysqli_stmt_close($chk);
            }
            if ((int)$exists !== 2) {
                header('Location: admin_treatments.php?tab=conflicts&msg=' . urlencode('존재하지 않는 시술입니다.') . '&msg_type=danger');
                exit;
            }
            $stmt = mysqli_prepare($connect, "INSERT IGNORE INTO gangnamunni_treatment_conflicts (tm_a_id, tm_b_id) VALUES (?, ?)");
            if ($stmt) { mysqli_stmt_bind_param($stmt, 'ii', $a, $b); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt); }
            header('Location: admin_treatments.php?tab=conflicts&msg=' . urlencode('함께 예약 불가 조합이 추가되었습니다.'));
            exit;
        }

        // 시술 등록
        if ($action === 'create') {
            $category_id = intval($_POST['category_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $hashtag = trim($_POST['hashtag'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $duration_min = intval($_POST['duration_min'] ?? 0);
            $recovery_days = intval($_POST['recovery_days'] ?? 0);
            $anesthesia_type = trim($_POST['anesthesia_type'] ?? 'none');
            if (!array_key_exists($anesthesia_type, $anesthesia_types)) $anesthesia_type = 'none';
            $repeat_cycle_months = intval($_POST['repeat_cycle_months'] ?? 0);
            $revisit = tm_parse_revisit($_POST['revisit_days'] ?? '', $_POST['revisit_strict'] ?? '');
            $precautions = trim($_POST['precautions'] ?? '');
            $avg_price_min = intval($_POST['avg_price_min'] ?? 0);
            $avg_price_max = intval($_POST['avg_price_max'] ?? 0);
            $default_image_url = trim($_POST['default_image_url'] ?? '');
            $default_upload = hti_handle_upload('default_image');
            if ($revisit === false) {
                $msg = '재시술 간격은 일 단위 숫자(0~3650)로 입력해주세요.';
                $msg_type = 'danger';
            } elseif (is_array($default_upload) && isset($default_upload['error'])) {
                $msg = $default_upload['error'];
                $msg_type = 'danger';
            } elseif (is_array($default_upload) && !empty($default_upload['url'])) {
                $default_image_url = $default_upload['url'];
            }
            if ($default_image_url !== '' && !preg_match('#^uploads/treatments/[A-Za-z0-9._-]+$#', $default_image_url)
                && (!filter_var($default_image_url, FILTER_VALIDATE_URL)
                    || !in_array(strtolower((string)parse_url($default_image_url, PHP_URL_SCHEME)), ['http', 'https'], true))) {
                $msg = '기본 이미지 URL은 http:// 또는 https:// 형식으로 입력해주세요.';
                $msg_type = 'danger';
            }

            if ($msg_type === 'danger') {
                // The upload validation message is already set.
            } elseif ($name === '') {
                $msg = '시술명을 입력해주세요.';
                $msg_type = 'danger';
            } elseif ($category_id <= 0) {
                $msg = '카테고리를 선택해주세요.';
                $msg_type = 'danger';
            } else {
                // 해시태그 자동 생성
                if ($hashtag === '') {
                    $hashtag = '#' . str_replace(' ', '', $name);
                } elseif (substr($hashtag, 0, 1) !== '#') {
                    $hashtag = '#' . $hashtag;
                }

                $created_by = $_SESSION['ad_admin_name'] ?? 'admin';

                $sort_order = intval($_POST['sort_order'] ?? 0);
                $stmt = mysqli_prepare($connect,
                    "INSERT INTO gangnamunni_treatment_master
                    (category_id, sort_order, name, hashtag, description, default_image_url, duration_min, recovery_days, anesthesia_type, repeat_cycle_months, precautions, avg_price_min, avg_price_max, is_active, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)"
                );
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'iissssiisisiis',
                        $category_id, $sort_order, $name, $hashtag, $description, $default_image_url,
                        $duration_min, $recovery_days, $anesthesia_type,
                        $repeat_cycle_months, $precautions,
                        $avg_price_min, $avg_price_max, $created_by
                    );
                    if (mysqli_stmt_execute($stmt)) {
                        $new_id = (int)mysqli_insert_id($connect);
                        $rv_note = '';
                        if ($revisit['days'] !== null && !tm_save_revisit($connect, $new_id, $revisit)) {
                            $rv_note = ' (재시술 간격은 저장되지 않았습니다. sql/treatment_master_revisit_20261021.sql 을 먼저 실행해주세요.)';
                        }
                        header('Location: admin_treatments.php?msg=' . urlencode('시술이 등록되었습니다.' . $rv_note));
                        exit;
                    } else {
                        $msg = '시술 등록 실패: ' . mysqli_error($connect);
                        $msg_type = 'danger';
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        }

        // 시술 수정
        if ($action === 'edit') {
            $id = intval($_POST['id'] ?? 0);
            $category_id = intval($_POST['category_id'] ?? 0);
            $sort_order = intval($_POST['sort_order'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $hashtag = trim($_POST['hashtag'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $duration_min = intval($_POST['duration_min'] ?? 0);
            $recovery_days = intval($_POST['recovery_days'] ?? 0);
            $anesthesia_type = trim($_POST['anesthesia_type'] ?? 'none');
            if (!array_key_exists($anesthesia_type, $anesthesia_types)) $anesthesia_type = 'none';
            $repeat_cycle_months = intval($_POST['repeat_cycle_months'] ?? 0);
            $revisit = tm_parse_revisit($_POST['revisit_days'] ?? '', $_POST['revisit_strict'] ?? '');
            $precautions = trim($_POST['precautions'] ?? '');
            $avg_price_min = intval($_POST['avg_price_min'] ?? 0);
            $avg_price_max = intval($_POST['avg_price_max'] ?? 0);
            // Preserve the current image unless the admin explicitly replaces
            // or removes it. Uploaded files are stored as relative URLs.
            $default_image_url = trim($_POST['current_default_image_url'] ?? '');
            $replacement_image_url = trim($_POST['default_image_url'] ?? '');
            if (!empty($_POST['remove_default_image'])) {
                $default_image_url = '';
            }
            if ($replacement_image_url !== '') {
                $default_image_url = $replacement_image_url;
            }
            $default_upload = null;
            if (!$treatment_image_schema_ready) {
                $msg = '기본 이미지 저장 컬럼이 없습니다. sql/treatment_master_default_image.sql을 DB에 적용해주세요.';
                $msg_type = 'danger';
            } else {
                try {
                    $default_upload = hti_handle_upload('default_image');
                    if (is_array($default_upload) && isset($default_upload['error'])) {
                        $msg = $default_upload['error'];
                        $msg_type = 'danger';
                    } elseif (is_array($default_upload) && !empty($default_upload['url'])) {
                        $default_image_url = $default_upload['url'];
                    }
                } catch (Throwable $e) {
                    error_log('[admin_treatments] 기본 이미지 업로드 실패: ' . $e->getMessage());
                    $msg = '이미지 업로드 처리 중 오류가 발생했습니다.';
                    $msg_type = 'danger';
                }
            }
            if ($default_image_url !== '' && !preg_match('#^uploads/treatments/[A-Za-z0-9._-]+$#', $default_image_url)
                && (!filter_var($default_image_url, FILTER_VALIDATE_URL)
                    || !in_array(strtolower((string)parse_url($default_image_url, PHP_URL_SCHEME)), ['http', 'https'], true))) {
                $msg = '기본 이미지 URL은 http:// 또는 https:// 형식으로 입력해주세요.';
                $msg_type = 'danger';
            }

            if ($revisit === false) {
                $msg = '재시술 간격은 일 단위 숫자(0~3650)로 입력해주세요.';
                $msg_type = 'danger';
            }
            if ($msg_type === 'danger') {
                // The upload validation message is already set.
            } elseif ($id <= 0 || $name === '' || $category_id <= 0) {
                $msg = '필수 항목을 모두 입력해주세요.';
                $msg_type = 'danger';
            } else {
                if ($hashtag === '') {
                    $hashtag = '#' . str_replace(' ', '', $name);
                } elseif (substr($hashtag, 0, 1) !== '#') {
                    $hashtag = '#' . $hashtag;
                }

                $stmt = null;
                try {
                    $stmt = mysqli_prepare($connect,
                        "UPDATE gangnamunni_treatment_master SET
                        category_id=?, sort_order=?, name=?, hashtag=?, description=?, default_image_url=?, duration_min=?, recovery_days=?,
                        anesthesia_type=?, repeat_cycle_months=?, precautions=?, avg_price_min=?, avg_price_max=?
                        WHERE id=?"
                    );
                    if (!$stmt) throw new RuntimeException(mysqli_error($connect));
                    mysqli_stmt_bind_param($stmt, 'iissssiisisiii',
                        $category_id, $sort_order, $name, $hashtag, $description, $default_image_url,
                        $duration_min, $recovery_days, $anesthesia_type,
                        $repeat_cycle_months, $precautions,
                        $avg_price_min, $avg_price_max, $id
                    );
                    if (mysqli_stmt_execute($stmt)) {
                        $rv_note = '';
                        if (tm_revisit_column_exists($connect)) {
                            if (!tm_save_revisit($connect, $id, $revisit)) $rv_note = ' (재시술 간격 저장에 실패했습니다.)';
                        } elseif ($revisit['days'] !== null) {
                            $rv_note = ' (재시술 간격은 저장되지 않았습니다. sql/treatment_master_revisit_20261021.sql 을 먼저 실행해주세요.)';
                        }
                        // [05-18] 같은 날 함께 예약 불가 시술: 이 시술의 조합을 제출된 목록으로 교체한다(양방향으로 저장).
                        if (($_POST['conflict_field'] ?? '') === '1') {
                            $ct_chk = mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_treatment_conflicts'");
                            if ($ct_chk && mysqli_num_rows($ct_chk) > 0) {
                                $picked = [];
                                foreach ((array)($_POST['conflict_with'] ?? []) as $raw_c) {
                                    $cid_c = intval($raw_c);
                                    if ($cid_c > 0 && $cid_c !== $id) $picked[$cid_c] = true;
                                }
                                $picked = array_keys($picked);
                                // 모달 목록에는 활성(is_active=1) 시술만 나온다. 비활성 시술과의 기존 조합은 건드리지 않도록
                                // 선택값 검증과 삭제 범위를 모두 활성 시술로 한정한다.
                                $active_ids = [];
                                $ar = mysqli_query($connect, "SELECT id FROM gangnamunni_treatment_master WHERE is_active = 1");
                                while ($ar && ($arow = $ar->fetch_assoc())) $active_ids[(int)$arow['id']] = true;
                                $picked = array_values(array_filter($picked, static function ($pid) use ($active_ids) { return isset($active_ids[$pid]); }));
                                $active_list = $active_ids ? implode(',', array_keys($active_ids)) : '0';
                                $okc = mysqli_query($connect, "DELETE FROM gangnamunni_treatment_conflicts
                                    WHERE (tm_a_id = " . (int)$id . " AND tm_b_id IN ({$active_list}))
                                       OR (tm_b_id = " . (int)$id . " AND tm_a_id IN ({$active_list}))");
                                foreach ($picked as $pid) {
                                    $lo = min($id, $pid); $hi = max($id, $pid);
                                    $okc = $okc && mysqli_query($connect, "INSERT IGNORE INTO gangnamunni_treatment_conflicts (tm_a_id, tm_b_id) VALUES ({$lo}, {$hi})");
                                }
                                if (!$okc) $rv_note .= ' (함께 예약 불가 시술 저장에 실패했습니다.)';
                            } elseif (!empty($_POST['conflict_with'])) {
                                $rv_note .= ' (함께 예약 불가 시술은 저장되지 않았습니다. sql/treatment_conflicts_20261023.sql 을 먼저 실행해주세요.)';
                            }
                        }
                        header('Location: admin_treatments.php?msg=' . urlencode('시술이 수정되었습니다.' . $rv_note));
                        exit;
                    }
                    throw new RuntimeException(mysqli_stmt_error($stmt));
                } catch (Throwable $e) {
                    error_log('[admin_treatments] 시술 수정 실패(id=' . $id . '): ' . $e->getMessage());
                    // 관리자 전용 페이지에서 실제 DB 오류를 보여줘 배포 환경의 원인을 즉시 확인할 수 있게 한다.
                    $msg = '시술 수정에 실패했습니다: ' . $e->getMessage();
                    $msg_type = 'danger';
                } finally {
                    if ($stmt) mysqli_stmt_close($stmt);
                }
            }
        }

        // 시술 삭제
        if ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                // 별칭도 함께 삭제
                $stmt_a = mysqli_prepare($connect, "DELETE FROM gangnamunni_treatment_aliases WHERE treatment_id = ?");
                if ($stmt_a) { mysqli_stmt_bind_param($stmt_a, 'i', $id); mysqli_stmt_execute($stmt_a); mysqli_stmt_close($stmt_a); }

                $stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_treatment_master WHERE id = ?");
                if ($stmt) { mysqli_stmt_bind_param($stmt, 'i', $id); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt); }

                header('Location: admin_treatments.php?msg=' . urlencode('시술이 삭제되었습니다.'));
                exit;
            }
        }

        // 활성/비활성 토글
        if ($action === 'toggle') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_treatment_master SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?");
                if ($stmt) { mysqli_stmt_bind_param($stmt, 'i', $id); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt); }
                header('Location: admin_treatments.php?msg=' . urlencode('시술 상태가 변경되었습니다.'));
                exit;
            }
        }

        // 벌크 삭제
        if ($action === 'bulk_delete') {
            $ids = $_POST['selected_ids'] ?? [];
            if (is_array($ids) && count($ids) > 0) {
                $deleted = 0;
                foreach ($ids as $raw_id) {
                    $bid = intval($raw_id);
                    if ($bid > 0) {
                        $stmt_a = mysqli_prepare($connect, "DELETE FROM gangnamunni_treatment_aliases WHERE treatment_id = ?");
                        if ($stmt_a) { mysqli_stmt_bind_param($stmt_a, 'i', $bid); mysqli_stmt_execute($stmt_a); mysqli_stmt_close($stmt_a); }

                        $stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_treatment_master WHERE id = ?");
                        if ($stmt) { mysqli_stmt_bind_param($stmt, 'i', $bid); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt); $deleted++; }
                    }
                }
                header('Location: admin_treatments.php?msg=' . urlencode($deleted . '개 시술이 삭제되었습니다.'));
                exit;
            }
        }

        // 벌크 토글
        if ($action === 'bulk_toggle') {
            $ids = $_POST['selected_ids'] ?? [];
            $toggle_to = intval($_POST['toggle_to'] ?? 1);
            if (is_array($ids) && count($ids) > 0) {
                $toggled = 0;
                foreach ($ids as $raw_id) {
                    $bid = intval($raw_id);
                    if ($bid > 0) {
                        $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_treatment_master SET is_active = ? WHERE id = ?");
                        if ($stmt) { mysqli_stmt_bind_param($stmt, 'ii', $toggle_to, $bid); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt); $toggled++; }
                    }
                }
                $status_text = $toggle_to ? '활성화' : '비활성화';
                header('Location: admin_treatments.php?msg=' . urlencode($toggled . '개 시술이 ' . $status_text . '되었습니다.'));
                exit;
            }
        }

        // === 드래그 앤 드롭 순서 저장 (AJAX) ===
        if ($action === 'reorder_categories') {
            $order = json_decode($_POST['order'] ?? '[]', true);
            if (is_array($order)) {
                foreach ($order as $i => $cid) {
                    $cid = intval($cid);
                    $sort = $i + 1;
                    $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_treatment_categories SET sort_order = ? WHERE id = ?");
                    if ($stmt) { mysqli_stmt_bind_param($stmt, 'ii', $sort, $cid); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt); }
                }
            }
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }

        if ($action === 'reorder_treatments') {
            $order = json_decode($_POST['order'] ?? '[]', true);
            if (is_array($order)) {
                foreach ($order as $i => $tid) {
                    $tid = intval($tid);
                    $sort = $i + 1;
                    $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_treatment_master SET sort_order = ? WHERE id = ?");
                    if ($stmt) { mysqli_stmt_bind_param($stmt, 'ii', $sort, $tid); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt); }
                }
            }
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }

        // === 대분류(카테고리) CRUD ===
        // 카테고리 추가
        if ($action === 'cat_create') {
            $cat_name = trim($_POST['cat_name'] ?? '');
            $cat_icon = trim($_POST['cat_icon'] ?? '');
            $cat_sort = intval($_POST['cat_sort'] ?? 0);
            if ($cat_name === '') {
                $msg = '카테고리명을 입력해주세요.';
                $msg_type = 'danger';
            } else {
                $cat_slug = preg_replace('/[^a-z0-9_]/', '', strtolower(str_replace(' ', '_', $cat_name)));
                if ($cat_slug === '') $cat_slug = 'cat_' . time();
                $stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_treatment_categories (name, slug, icon, sort_order, is_active) VALUES (?, ?, ?, ?, 1)");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'sssi', $cat_name, $cat_slug, $cat_icon, $cat_sort);
                    if (mysqli_stmt_execute($stmt)) {
                        header('Location: admin_treatments.php?tab=categories&msg=' . urlencode('카테고리가 추가되었습니다.'));
                        exit;
                    } else {
                        $msg = '카테고리 추가 실패: ' . mysqli_error($connect);
                        $msg_type = 'danger';
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        }

        // 카테고리 수정
        if ($action === 'cat_edit') {
            $cat_id = intval($_POST['cat_id'] ?? 0);
            $cat_name = trim($_POST['cat_name'] ?? '');
            $cat_icon = trim($_POST['cat_icon'] ?? '');
            $cat_sort = intval($_POST['cat_sort'] ?? 0);
            if ($cat_id <= 0 || $cat_name === '') {
                $msg = '카테고리명을 입력해주세요.';
                $msg_type = 'danger';
            } else {
                $cat_slug = preg_replace('/[^a-z0-9_]/', '', strtolower(str_replace(' ', '_', $cat_name)));
                if ($cat_slug === '') $cat_slug = 'cat_' . time();
                $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_treatment_categories SET name=?, slug=?, icon=?, sort_order=? WHERE id=?");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'sssii', $cat_name, $cat_slug, $cat_icon, $cat_sort, $cat_id);
                    if (mysqli_stmt_execute($stmt)) {
                        header('Location: admin_treatments.php?tab=categories&msg=' . urlencode('카테고리가 수정되었습니다.'));
                        exit;
                    } else {
                        $msg = '카테고리 수정 실패: ' . mysqli_error($connect);
                        $msg_type = 'danger';
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        }

        // 카테고리 삭제
        if ($action === 'cat_delete') {
            $cat_id = intval($_POST['cat_id'] ?? 0);
            if ($cat_id > 0) {
                // 소속 시술 수 확인
                $check_stmt = mysqli_prepare($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_treatment_master WHERE category_id = ?");
                mysqli_stmt_bind_param($check_stmt, 'i', $cat_id);
                mysqli_stmt_execute($check_stmt);
                $check_r = mysqli_stmt_get_result($check_stmt);
                $cnt = ($check_r && $row = mysqli_fetch_assoc($check_r)) ? intval($row['cnt']) : 0;
                mysqli_stmt_close($check_stmt);

                if ($cnt > 0) {
                    $msg = '이 카테고리에 ' . $cnt . '개 시술이 있어 삭제할 수 없습니다. 시술을 먼저 이동/삭제해주세요.';
                    $msg_type = 'danger';
                } else {
                    $stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_treatment_categories WHERE id = ?");
                    if ($stmt) { mysqli_stmt_bind_param($stmt, 'i', $cat_id); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt); }
                    header('Location: admin_treatments.php?tab=categories&msg=' . urlencode('카테고리가 삭제되었습니다.'));
                    exit;
                }
            }
        }

        // 카테고리 활성/비활성 토글
        if ($action === 'cat_toggle') {
            $cat_id = intval($_POST['cat_id'] ?? 0);
            if ($cat_id > 0) {
                $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_treatment_categories SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?");
                if ($stmt) { mysqli_stmt_bind_param($stmt, 'i', $cat_id); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt); }
                header('Location: admin_treatments.php?tab=categories&msg=' . urlencode('카테고리 상태가 변경되었습니다.'));
                exit;
            }
        }

        // 별칭 추가
        if ($action === 'add_alias') {
            $treatment_id = intval($_POST['treatment_id'] ?? 0);
            $alias = trim($_POST['alias'] ?? '');
            $lang = trim($_POST['lang'] ?? 'ko');

            if ($treatment_id <= 0 || $alias === '') {
                $msg = '별칭을 입력해주세요.';
                $msg_type = 'danger';
            } else {
                $stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_treatment_aliases (treatment_id, alias, lang) VALUES (?, ?, ?)");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'iss', $treatment_id, $alias, $lang);
                    if (mysqli_stmt_execute($stmt)) {
                        header('Location: admin_treatments.php?msg=' . urlencode('별칭이 추가되었습니다.') . '&alias_for=' . $treatment_id);
                        exit;
                    } else {
                        $msg = '별칭 추가 실패: ' . mysqli_error($connect);
                        $msg_type = 'danger';
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        }

        // 별칭 삭제
        if ($action === 'delete_alias') {
            $alias_id = intval($_POST['alias_id'] ?? 0);
            $treatment_id = intval($_POST['treatment_id'] ?? 0);
            if ($alias_id > 0) {
                $stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_treatment_aliases WHERE id = ?");
                if ($stmt) { mysqli_stmt_bind_param($stmt, 'i', $alias_id); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt); }
                header('Location: admin_treatments.php?msg=' . urlencode('별칭이 삭제되었습니다.') . '&alias_for=' . $treatment_id);
                exit;
            }
        }

    } // end CSRF check
}

// GET ?msg= 파라미터 처리
if (!empty($_GET['msg'])) {
    $msg = $_GET['msg'];
    $msg_type = (($_GET['msg_type'] ?? '') === 'danger') ? 'danger' : 'success';
}

// ===== 필터 =====
$filter_category = intval($_GET['cat'] ?? 0);
$search = trim($_GET['q'] ?? '');

// 시술 데이터 조회 (서브쿼리로 카운트 포함)
$where_clauses = ["1=1"];
$bind_types = '';
$bind_values = [];

if ($filter_category > 0) {
    $where_clauses[] = "t.category_id = ?";
    $bind_types .= 'i';
    $bind_values[] = $filter_category;
}

if ($search !== '') {
    $where_clauses[] = "(t.name LIKE ? OR t.hashtag LIKE ? OR t.description LIKE ?)";
    $bind_types .= 'sss';
    $search_like = '%' . $search . '%';
    $bind_values[] = $search_like;
    $bind_values[] = $search_like;
    $bind_values[] = $search_like;
}

$where = implode(' AND ', $where_clauses);

$data_sql = "SELECT t.*,
    c.name AS category_name, c.icon AS category_icon,
    (SELECT COUNT(*) FROM gangnamunni_treatment_aliases WHERE treatment_id = t.id) AS alias_count,
    (SELECT COUNT(*) FROM gangnamunni_hospital_treatments WHERE treatment_id = t.id) AS hospital_count,
    (SELECT COUNT(*) FROM gangnamunni_treatment_reviews WHERE treatment_id = t.id) AS review_count
    FROM gangnamunni_treatment_master t
    LEFT JOIN gangnamunni_treatment_categories c ON t.category_id = c.id
    WHERE " . $where . "
    ORDER BY c.sort_order ASC, t.sort_order ASC, t.popularity_score DESC, t.id ASC";

$stmt_d = mysqli_prepare($connect, $data_sql);
if ($bind_types && $stmt_d) {
    mysqli_stmt_bind_param($stmt_d, $bind_types, ...$bind_values);
}
mysqli_stmt_execute($stmt_d);
$r = mysqli_stmt_get_result($stmt_d);
$rows = [];
if ($r) while ($row = mysqli_fetch_assoc($r)) $rows[] = $row;
mysqli_stmt_close($stmt_d);

// 카테고리별 통계
$cat_counts = [];
$cat_r2 = mysqli_query($connect, "SELECT category_id, COUNT(*) as cnt FROM gangnamunni_treatment_master GROUP BY category_id");
if ($cat_r2) while ($cc = mysqli_fetch_assoc($cat_r2)) $cat_counts[intval($cc['category_id'])] = intval($cc['cnt']);

$total_all = array_sum($cat_counts);
$total_active = 0;
$total_inactive = 0;
$sr2 = mysqli_query($connect, "SELECT is_active, COUNT(*) as cnt FROM gangnamunni_treatment_master GROUP BY is_active");
if ($sr2) while ($s2 = mysqli_fetch_assoc($sr2)) {
    if ($s2['is_active'] == 1) $total_active = (int)$s2['cnt'];
    else $total_inactive = (int)$s2['cnt'];
}

// 별칭 조회 (모달용 - alias_for 파라미터가 있을 때)
$alias_for = intval($_GET['alias_for'] ?? 0);
$aliases_data = [];
if ($alias_for > 0) {
    $stmt_al = mysqli_prepare($connect, "SELECT * FROM gangnamunni_treatment_aliases WHERE treatment_id = ? ORDER BY lang ASC, id ASC");
    if ($stmt_al) {
        mysqli_stmt_bind_param($stmt_al, 'i', $alias_for);
        mysqli_stmt_execute($stmt_al);
        $al_r = mysqli_stmt_get_result($stmt_al);
        if ($al_r) while ($al = mysqli_fetch_assoc($al_r)) $aliases_data[] = $al;
        mysqli_stmt_close($stmt_al);
    }
}

// 전체 카테고리 목록 (비활성 포함 - 카테고리 관리용)
$all_categories = [];
$cat_all_r = mysqli_query($connect, "SELECT c.*, (SELECT COUNT(*) FROM gangnamunni_treatment_master WHERE category_id = c.id) AS treatment_count FROM gangnamunni_treatment_categories c ORDER BY c.sort_order ASC, c.id ASC");
if ($cat_all_r) while ($ac = mysqli_fetch_assoc($cat_all_r)) $all_categories[] = $ac;

// 현재 탭
$current_tab = trim($_GET['tab'] ?? 'treatments');

// [05-18] 수정 모달의 "같은 날 함께 예약 불가 시술" 용 데이터 (시술 ID => 함께 못 하는 시술 ID 목록)
$tm_conflict_ready = false; $tm_conflict_map = []; $tm_conflict_masters = [];
$tm_ct = mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_treatment_conflicts'");
$tm_conflict_ready = $tm_ct && mysqli_num_rows($tm_ct) > 0;
if ($current_tab === 'treatments') {
    $tm_mr = mysqli_query($connect, "SELECT id, name FROM gangnamunni_treatment_master WHERE is_active = 1 ORDER BY name ASC");
    while ($tm_mr && ($tm_m = $tm_mr->fetch_assoc())) { $tm_conflict_masters[] = ['id' => (int)$tm_m['id'], 'name' => (string)$tm_m['name']]; }
    if ($tm_conflict_ready) {
        $tm_cr = mysqli_query($connect, "SELECT tm_a_id, tm_b_id FROM gangnamunni_treatment_conflicts");
        while ($tm_cr && ($tm_c = $tm_cr->fetch_assoc())) {
            $tm_conflict_map[(int)$tm_c['tm_a_id']][] = (int)$tm_c['tm_b_id'];
            $tm_conflict_map[(int)$tm_c['tm_b_id']][] = (int)$tm_c['tm_a_id'];
        }
    }
}

// 전체 별칭 맵 (JSON으로 JS에 전달)
$all_aliases = [];
$al_all = mysqli_query($connect, "SELECT * FROM gangnamunni_treatment_aliases ORDER BY treatment_id, lang, id");
if ($al_all) while ($a = mysqli_fetch_assoc($al_all)) {
    $tid = intval($a['treatment_id']);
    if (!isset($all_aliases[$tid])) $all_aliases[$tid] = [];
    $all_aliases[$tid][] = $a;
}

require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <?php if (!empty($msg)): ?>
    <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show">
        <?php echo htmlspecialchars($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="bi bi-heart-pulse me-2"></i>시술 마스터 관리</h4>
        <div class="btn-group btn-group-sm">
            <a href="admin_treatments.php?tab=treatments" class="btn btn-<?php echo $current_tab === 'treatments' ? 'primary' : 'outline-primary'; ?>">
                <i class="bi bi-list-ul me-1"></i>중분류 (시술)
            </a>
            <a href="admin_treatments.php?tab=categories" class="btn btn-<?php echo $current_tab === 'categories' ? 'primary' : 'outline-primary'; ?>">
                <i class="bi bi-folder me-1"></i>대분류 (카테고리)
            </a>
            <a href="admin_treatments.php?tab=conflicts" class="btn btn-<?php echo $current_tab === 'conflicts' ? 'primary' : 'outline-primary'; ?>">
                <i class="bi bi-slash-circle me-1"></i>함께 예약 불가
            </a>
        </div>
    </div>

    <?php if ($current_tab === 'conflicts'): ?>
    <?php
        // [05-18] 같은 날 함께 예약하기 어려운 시술 조합
        $conflict_ready = false; $conflict_pairs = []; $conflict_masters = [];
        $ct = mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_treatment_conflicts'");
        $conflict_ready = $ct && mysqli_num_rows($ct) > 0;
        $mr = mysqli_query($connect, "SELECT id, name FROM gangnamunni_treatment_master WHERE is_active = 1 ORDER BY name ASC");
        while ($mr && ($m = $mr->fetch_assoc())) { $conflict_masters[(int)$m['id']] = (string)$m['name']; }
        if ($conflict_ready) {
            $cr = mysqli_query($connect, "SELECT c.id, a.name AS a_name, b.name AS b_name FROM gangnamunni_treatment_conflicts c
                LEFT JOIN gangnamunni_treatment_master a ON a.id = c.tm_a_id LEFT JOIN gangnamunni_treatment_master b ON b.id = c.tm_b_id ORDER BY c.id DESC");
            while ($cr && ($c = $cr->fetch_assoc())) { $conflict_pairs[] = $c; }
        }
    ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-slash-circle me-2"></i>같은 날 함께 예약 불가 시술 조합 (<?php echo count($conflict_pairs); ?>건)</h6></div>
        <div class="card-body">
            <p class="text-muted small mb-3">서로 가까워서 한날 함께 시술하기 어려운 시술을 한 쌍씩 등록하세요. 모든 병원에 공통으로 적용되며, 고객이 앱에서 이 조합을 한 번에 예약하려고 하면 "같은 날 함께 예약하기 어려운 시술이 있어요" 안내가 나오고 하나를 빼거나 날짜를 나눠 예약하도록 안내합니다.</p>
            <?php if (!$conflict_ready): ?>
                <div class="alert alert-warning mb-0">조합 테이블이 아직 없습니다. <code>sql/treatment_conflicts_20261023.sql</code> 을 먼저 실행해주세요.</div>
            <?php else: ?>
            <form method="post" class="row g-2 align-items-center mb-3">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="conflict_add">
                <div class="col-md-5">
                    <select name="tm_a" class="form-select form-select-sm" required>
                        <option value="">시술 선택</option>
                        <?php foreach ($conflict_masters as $mid => $mname): ?><option value="<?php echo (int)$mid; ?>"><?php echo htmlspecialchars($mname, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto text-muted">+</div>
                <div class="col-md-5">
                    <select name="tm_b" class="form-select form-select-sm" required>
                        <option value="">시술 선택</option>
                        <?php foreach ($conflict_masters as $mid => $mname): ?><option value="<?php echo (int)$mid; ?>"><?php echo htmlspecialchars($mname, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-sm btn-primary">조합 추가</button></div>
            </form>
            <?php if (empty($conflict_pairs)): ?>
                <div class="text-muted small">등록된 조합이 없습니다.</div>
            <?php else: ?>
            <ul class="list-group">
                <?php foreach ($conflict_pairs as $cp_row): ?>
                <li class="list-group-item d-flex align-items-center">
                    <span class="flex-grow-1"><?php echo htmlspecialchars((string)($cp_row['a_name'] ?? '(삭제된 시술)'), ENT_QUOTES, 'UTF-8'); ?> <span class="text-muted">+</span> <?php echo htmlspecialchars((string)($cp_row['b_name'] ?? '(삭제된 시술)'), ENT_QUOTES, 'UTF-8'); ?></span>
                    <form method="post" onsubmit="return confirm('이 조합을 삭제할까요?')">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="conflict_delete">
                        <input type="hidden" name="conflict_id" value="<?php echo (int)$cp_row['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">삭제</button>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php elseif ($current_tab === 'categories'): ?>

    <!-- 적용 페이지 바로가기 -->
    <div class="alert alert-info d-flex align-items-center flex-wrap gap-2 py-2 mb-3">
        <strong class="me-2"><i class="bi bi-link-45deg me-1"></i>카테고리 적용 페이지:</strong>
        <a href="index.php" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-house me-1"></i>메인 (시술 테이블)</a>
        <a href="hospitals.php" target="_blank" class="btn btn-sm btn-outline-success"><i class="bi bi-building me-1"></i>병원 리스트 (필터)</a>
        <a href="admin_treatments.php" target="_blank" class="btn btn-sm btn-outline-info"><i class="bi bi-list-ul me-1"></i>중분류 관리</a>
        <a href="admin_hospital_treatments.php" target="_blank" class="btn btn-sm btn-outline-warning"><i class="bi bi-hospital me-1"></i>병원별 시술</a>
        <a href="treatment_ranking.php" target="_blank" class="btn btn-sm btn-outline-danger"><i class="bi bi-trophy me-1"></i>시술 랭킹</a>
        <a href="admin_client_spec.php?gid=222025344" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-table me-1"></i>청담분류 시트</a>
    </div>

    <!-- ====== 홈 시술 테이블 표시 설정 ====== -->
    <?php
    $current_tc_mode = 'excel';
    $tc_mode_q = mysqli_query($connect, "SELECT setting_value FROM gangnamunni_settings WHERE setting_key = 'tc_table_mode' LIMIT 1");
    if ($tc_mode_q && $tc_mode_r = mysqli_fetch_assoc($tc_mode_q)) {
        $current_tc_mode = $tc_mode_r['setting_value'];
    }
    ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-3">
            <form method="POST" class="d-flex align-items-center flex-wrap gap-3">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="save_table_mode">
                <span class="fw-bold"><i class="bi bi-table me-1"></i>홈 시술 테이블 모드:</span>
                <div class="btn-group btn-group-sm" role="group">
                    <input type="radio" class="btn-check" name="tc_table_mode" value="excel" id="tcModeExcel" <?php echo $current_tc_mode === 'excel' ? 'checked' : ''; ?>>
                    <label class="btn btn-outline-primary" for="tcModeExcel"><i class="bi bi-file-earmark-spreadsheet me-1"></i>엑셀형</label>
                    <input type="radio" class="btn-check" name="tc_table_mode" value="row" id="tcModeRow" <?php echo $current_tc_mode === 'row' ? 'checked' : ''; ?>>
                    <label class="btn btn-outline-primary" for="tcModeRow"><i class="bi bi-arrows me-1"></i>행별 스크롤</label>
                    <input type="radio" class="btn-check" name="tc_table_mode" value="hybrid" id="tcModeHybrid" <?php echo $current_tc_mode === 'hybrid' ? 'checked' : ''; ?>>
                    <label class="btn btn-outline-primary" for="tcModeHybrid"><i class="bi bi-layout-split me-1"></i>하이브리드</label>
                </div>
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check-lg me-1"></i>저장</button>
                <a href="index.php" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye me-1"></i>미리보기</a>
            </form>
            <div class="text-muted small mt-2">
                <b>엑셀형</b>: 대분류 고정 + 전체 가로 스크롤 &nbsp;|&nbsp;
                <b>행별</b>: 각 행 독립 스크롤 &nbsp;|&nbsp;
                <b>하이브리드</b>: 대분류 고정 + 각 행 독립 스크롤
            </div>
        </div>
    </div>

    <!-- ====== 대분류 카테고리 관리 ====== -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-folder-plus me-2"></i>대분류 카테고리 관리</h5>
            <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#catCreateForm">
                <i class="bi bi-plus-lg me-1"></i>카테고리 추가
            </button>
        </div>
        <div class="card-body">
            <!-- 추가 폼 (접힘) -->
            <div class="collapse mb-3" id="catCreateForm">
                <form method="post" class="card card-body bg-light border-0">
                    <input type="hidden" name="action" value="cat_create">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">카테고리명 <span class="text-danger">*</span></label>
                            <input type="text" name="cat_name" class="form-control form-control-sm" required placeholder="예: 리프팅">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">아이콘 (Bootstrap Icons)</label>
                            <input type="text" name="cat_icon" class="form-control form-control-sm" placeholder="예: bi-arrow-up-circle">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">정렬 순서</label>
                            <input type="number" name="cat_sort" class="form-control form-control-sm" value="<?php echo count($all_categories) + 1; ?>" min="0">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-plus-lg me-1"></i>추가</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- 카테고리 목록 -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="catTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width:30px;"></th>
                            <th style="width:50px;">ID</th>
                            <th style="width:50px;">순서</th>
                            <th>카테고리명</th>
                            <th style="width:180px;">아이콘</th>
                            <th style="width:80px;" class="text-center">시술 수</th>
                            <th style="width:80px;" class="text-center">상태</th>
                            <th style="width:250px;">관리</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($all_categories)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">등록된 카테고리가 없습니다.</td></tr>
                    <?php else: foreach ($all_categories as $ac): ?>
                        <tr id="cat-row-<?php echo $ac['id']; ?>" data-id="<?php echo $ac['id']; ?>">
                            <td class="drag-handle" style="cursor:grab;"><i class="bi bi-grip-vertical text-muted"></i></td>
                            <td><?php echo $ac['id']; ?></td>
                            <td>
                                <span class="cat-view-<?php echo $ac['id']; ?>"><?php echo $ac['sort_order']; ?></span>
                                <input type="number" class="form-control form-control-sm cat-edit-<?php echo $ac['id']; ?>" value="<?php echo $ac['sort_order']; ?>" style="display:none;width:60px;" min="0">
                            </td>
                            <td>
                                <?php if (!empty($ac['icon'])): ?><i class="<?php echo htmlspecialchars($ac['icon']); ?> me-2"></i><?php endif; ?>
                                <span class="cat-view-<?php echo $ac['id']; ?>"><strong><?php echo htmlspecialchars($ac['name']); ?></strong></span>
                                <input type="text" class="form-control form-control-sm cat-edit-<?php echo $ac['id']; ?>" value="<?php echo htmlspecialchars($ac['name']); ?>" style="display:none;" required>
                            </td>
                            <td>
                                <span class="cat-view-<?php echo $ac['id']; ?>"><code><?php echo htmlspecialchars($ac['icon'] ?? ''); ?></code></span>
                                <input type="text" class="form-control form-control-sm cat-edit-<?php echo $ac['id']; ?>" value="<?php echo htmlspecialchars($ac['icon'] ?? ''); ?>" style="display:none;" placeholder="bi-icon-name">
                            </td>
                            <td class="text-center">
                                <a href="admin_treatments.php?cat=<?php echo $ac['id']; ?>" target="_blank" class="badge bg-info text-decoration-none"><?php echo intval($ac['treatment_count']); ?></a>
                            </td>
                            <td class="text-center">
                                <?php if ($ac['is_active']): ?>
                                    <span class="badge bg-success">활성</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">비활성</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <!-- 인라인 수정 -->
                                <span class="cat-view-<?php echo $ac['id']; ?>">
                                    <button class="btn btn-outline-primary btn-sm" onclick="catEditStart(<?php echo $ac['id']; ?>)" title="수정"><i class="bi bi-pencil"></i></button>
                                </span>
                                <span class="cat-edit-<?php echo $ac['id']; ?>" style="display:none;">
                                    <button class="btn btn-primary btn-sm" onclick="catEditSave(<?php echo $ac['id']; ?>)" title="저장"><i class="bi bi-check-lg"></i></button>
                                    <button class="btn btn-secondary btn-sm" onclick="catEditCancel(<?php echo $ac['id']; ?>)" title="취소"><i class="bi bi-x-lg"></i></button>
                                </span>
                                <!-- 토글 -->
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="cat_toggle">
                                    <input type="hidden" name="cat_id" value="<?php echo $ac['id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <?php if ($ac['is_active']): ?>
                                    <button class="btn btn-outline-warning btn-sm" title="비활성화"><i class="bi bi-eye-slash"></i></button>
                                    <?php else: ?>
                                    <button class="btn btn-outline-success btn-sm" title="활성화"><i class="bi bi-eye"></i></button>
                                    <?php endif; ?>
                                </form>
                                <!-- 삭제 -->
                                <form method="post" class="d-inline" onsubmit="return confirm('정말 삭제하시겠습니까?<?php echo $ac['treatment_count'] > 0 ? ' (소속 시술 ' . $ac['treatment_count'] . '개 있음 - 삭제 불가)' : ''; ?>');">
                                    <input type="hidden" name="action" value="cat_delete">
                                    <input type="hidden" name="cat_id" value="<?php echo $ac['id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <button class="btn btn-outline-danger btn-sm" title="삭제" <?php echo $ac['treatment_count'] > 0 ? 'disabled' : ''; ?>><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
    <script>
    // 대분류 드래그 앤 드롭
    document.addEventListener('DOMContentLoaded', function() {
        var catBody = document.querySelector('#catTable tbody');
        if (catBody) {
            new Sortable(catBody, {
                handle: '.drag-handle',
                animation: 150,
                ghostClass: 'table-primary',
                onEnd: function() {
                    var order = [];
                    catBody.querySelectorAll('tr[data-id]').forEach(function(tr) {
                        order.push(tr.dataset.id);
                    });
                    // AJAX 저장
                    var fd = new FormData();
                    fd.append('action', 'reorder_categories');
                    fd.append('csrf_token', '<?php echo $csrf_token; ?>');
                    fd.append('order', JSON.stringify(order));
                    fetch('admin_treatments.php', { method: 'POST', body: fd })
                    .then(r => r.json()).then(function(d) {
                        if (d.ok) {
                            // 순서 번호 업데이트
                            catBody.querySelectorAll('tr[data-id]').forEach(function(tr, i) {
                                tr.querySelectorAll('.cat-view-' + tr.dataset.id)[0].textContent = i + 1;
                            });
                        }
                    });
                }
            });
        }
    });

    function catEditStart(id) {
        document.querySelectorAll('.cat-view-' + id).forEach(el => el.style.display = 'none');
        document.querySelectorAll('.cat-edit-' + id).forEach(el => el.style.display = '');
    }
    function catEditCancel(id) {
        document.querySelectorAll('.cat-view-' + id).forEach(el => el.style.display = '');
        document.querySelectorAll('.cat-edit-' + id).forEach(el => el.style.display = 'none');
    }
    function catEditSave(id) {
        var inputs = document.querySelectorAll('.cat-edit-' + id + '[type="text"], .cat-edit-' + id + '[type="number"]');
        var name = '', icon = '', sort = 0;
        inputs.forEach(function(inp) {
            if (inp.type === 'number') sort = parseInt(inp.value) || 0;
            else if (inp.placeholder === 'bi-icon-name') icon = inp.value;
            else name = inp.value;
        });
        if (!name) { alert('카테고리명을 입력해주세요.'); return; }

        var form = document.createElement('form');
        form.method = 'post';
        form.innerHTML = '<input type="hidden" name="action" value="cat_edit">' +
            '<input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">' +
            '<input type="hidden" name="cat_id" value="' + id + '">' +
            '<input type="hidden" name="cat_name" value="' + name.replace(/"/g, '&quot;') + '">' +
            '<input type="hidden" name="cat_icon" value="' + icon.replace(/"/g, '&quot;') + '">' +
            '<input type="hidden" name="cat_sort" value="' + sort + '">';
        document.body.appendChild(form);
        form.submit();
    }
    </script>

    <?php else: ?>
    <!-- ====== 중분류 시술 관리 (기존) ====== -->

    <!-- 통계 카드 -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <h5 class="mb-0"><?php echo number_format($total_all); ?></h5>
                    <small class="text-primary">전체</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <h5 class="mb-0"><?php echo number_format($total_active); ?></h5>
                    <small class="text-success">활성</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <h5 class="mb-0"><?php echo number_format($total_inactive); ?></h5>
                    <small class="text-danger">비활성</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <h5 class="mb-0"><?php echo count($categories); ?></h5>
                    <small class="text-info">카테고리</small>
                </div>
            </div>
        </div>
    </div>

    <!-- 카테고리 탭 -->
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link <?php echo $filter_category === 0 ? 'active' : ''; ?>"
               href="admin_treatments.php<?php echo $search !== '' ? '?q=' . urlencode($search) : ''; ?>">
                전체 <span class="badge bg-secondary ms-1"><?php echo $total_all; ?></span>
            </a>
        </li>
        <?php foreach ($categories as $cid => $cat): ?>
        <li class="nav-item">
            <a class="nav-link <?php echo $filter_category === $cid ? 'active' : ''; ?>"
               href="admin_treatments.php?cat=<?php echo $cid; ?><?php echo $search !== '' ? '&q=' . urlencode($search) : ''; ?>">
                <?php if (!empty($cat['icon'])): ?><i class="<?php echo htmlspecialchars($cat['icon']); ?> me-1"></i><?php endif; ?>
                <?php echo htmlspecialchars($cat['name']); ?>
                <span class="badge bg-secondary ms-1"><?php echo $cat_counts[$cid] ?? 0; ?></span>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <!-- 검색 + 벌크 액션 -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <form method="get" class="d-flex gap-2">
                        <?php if ($filter_category > 0): ?>
                        <input type="hidden" name="cat" value="<?php echo $filter_category; ?>">
                        <?php endif; ?>
                        <input type="text" name="q" class="form-control form-control-sm" placeholder="시술명, 해시태그, 설명 검색" value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-outline-primary btn-sm"><i class="bi bi-search"></i></button>
                        <?php if ($search !== '' || $filter_category > 0): ?>
                        <a href="admin_treatments.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="col-md-7 text-end">
                    <button type="button" class="btn btn-primary btn-sm me-2" data-bs-toggle="modal" data-bs-target="#createModal">
                        <i class="bi bi-plus-lg me-1"></i>시술 등록
                    </button>
                    <div class="btn-group btn-group-sm" id="bulkActions" style="display:none;">
                        <button type="button" class="btn btn-outline-success" onclick="bulkToggle(1)">
                            <i class="bi bi-eye me-1"></i>활성화
                        </button>
                        <button type="button" class="btn btn-outline-warning" onclick="bulkToggle(0)">
                            <i class="bi bi-eye-slash me-1"></i>비활성화
                        </button>
                        <button type="button" class="btn btn-outline-danger" onclick="bulkDelete()">
                            <i class="bi bi-trash me-1"></i>삭제
                        </button>
                    </div>
                    <span id="selectedCount" class="text-muted ms-2" style="display:none;"></span>
                </div>
            </div>
        </div>
    </div>

    <!-- 시술 목록 -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="treatmentTable">
                <thead class="table-light">
                    <tr>
                        <th style="width:30px;"></th>
                        <th style="width:40px;">
                            <input type="checkbox" class="form-check-input" id="checkAll" onchange="toggleAll(this)">
                        </th>
                        <th style="width:50px;cursor:pointer;" onclick="sortTable(2,'num')">순서 <i class="bi bi-arrow-down-up text-muted" style="font-size:0.7rem;"></i></th>
                        <th style="width:130px;cursor:pointer;" onclick="sortTable(3,'str')">해시태그 <i class="bi bi-arrow-down-up text-muted" style="font-size:0.7rem;"></i></th>
                        <th style="cursor:pointer;" onclick="sortTable(4,'str')">시술명 <i class="bi bi-arrow-down-up text-muted" style="font-size:0.7rem;"></i></th>
                        <th style="width:100px;cursor:pointer;" onclick="sortTable(5,'str')">카테고리 <i class="bi bi-arrow-down-up text-muted" style="font-size:0.7rem;"></i></th>
                        <th style="width:70px;cursor:pointer;" class="text-center" onclick="sortTable(6,'num')">시간 <i class="bi bi-arrow-down-up text-muted" style="font-size:0.7rem;"></i></th>
                        <th style="width:70px;cursor:pointer;" class="text-center" onclick="sortTable(7,'num')">회복 <i class="bi bi-arrow-down-up text-muted" style="font-size:0.7rem;"></i></th>
                        <th style="width:140px;cursor:pointer;" class="text-center" onclick="sortTable(8,'num')">가격대(만원) <i class="bi bi-arrow-down-up text-muted" style="font-size:0.7rem;"></i></th>
                        <th style="width:60px;cursor:pointer;" class="text-center" onclick="sortTable(9,'num')">별칭 <i class="bi bi-arrow-down-up text-muted" style="font-size:0.7rem;"></i></th>
                        <th style="width:60px;cursor:pointer;" class="text-center" onclick="sortTable(10,'num')">병원 <i class="bi bi-arrow-down-up text-muted" style="font-size:0.7rem;"></i></th>
                        <th style="width:60px;cursor:pointer;" class="text-center" onclick="sortTable(11,'num')">후기 <i class="bi bi-arrow-down-up text-muted" style="font-size:0.7rem;"></i></th>
                        <th style="width:60px;cursor:pointer;" class="text-center" onclick="sortTable(12,'str')">상태 <i class="bi bi-arrow-down-up text-muted" style="font-size:0.7rem;"></i></th>
                        <th style="width:150px;">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="13" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox" style="font-size:2rem;"></i><br>
                            등록된 시술이 없습니다.
                        </td>
                    </tr>
                    <?php else: foreach ($rows as $t): ?>
                    <tr data-id="<?php echo $t['id']; ?>">
                        <td class="drag-handle-t" style="cursor:grab;"><i class="bi bi-grip-vertical text-muted"></i></td>
                        <td>
                            <input type="checkbox" class="form-check-input row-check" value="<?php echo $t['id']; ?>" onchange="updateBulkUI()">
                        </td>
                        <td class="text-center text-muted sort-num"><?php echo intval($t['sort_order'] ?? 0); ?></td>
                        <td>
                            <span class="text-primary fw-bold"><?php echo htmlspecialchars($t['hashtag'] ?? ''); ?></span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <?php if (!empty($t['default_image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($t['default_image_url'], ENT_QUOTES); ?>"
                                     alt="" style="width:44px;height:44px;object-fit:cover;border-radius:6px;flex:0 0 auto;">
                                <?php else: ?>
                                <span class="d-inline-flex align-items-center justify-content-center bg-light text-muted border rounded"
                                      style="width:44px;height:44px;flex:0 0 auto;" title="기본 이미지 미등록"><i class="bi bi-image"></i></span>
                                <?php endif; ?>
                                <div><strong><?php echo htmlspecialchars($t['name']); ?></strong>
                            <?php if (!empty($t['description'])): ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars(mb_substr($t['description'], 0, 50, 'UTF-8')); ?><?php echo mb_strlen($t['description'], 'UTF-8') > 50 ? '...' : ''; ?></small>
                            <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php
                            $cat_colors = [1 => 'primary', 2 => 'success', 3 => 'info', 4 => 'warning', 5 => 'danger', 6 => 'secondary'];
                            $cc = $cat_colors[$t['category_id']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?php echo $cc; ?>"><?php echo htmlspecialchars($t['category_name'] ?? '미분류'); ?></span>
                        </td>
                        <td class="text-center">
                            <?php echo $t['duration_min'] > 0 ? $t['duration_min'] . '분' : '-'; ?>
                        </td>
                        <td class="text-center">
                            <?php echo $t['recovery_days'] > 0 ? $t['recovery_days'] . '일' : '-'; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($t['avg_price_min'] > 0 || $t['avg_price_max'] > 0): ?>
                            <small><?php echo number_format($t['avg_price_min']); ?>~<?php echo number_format($t['avg_price_max']); ?>만원</small>
                            <?php else: ?>
                            <small class="text-muted">-</small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <a href="#" class="text-decoration-none" onclick="openAliasModal(<?php echo $t['id']; ?>, '<?php echo htmlspecialchars(addslashes($t['name']), ENT_QUOTES); ?>')">
                                <span class="badge bg-outline-secondary border"><?php echo intval($t['alias_count']); ?></span>
                            </a>
                        </td>
                        <td class="text-center">
                            <a href="admin_hospital_treatments.php?treatment_id=<?php echo $t['id']; ?>" target="_blank" class="text-decoration-none" title="이 시술을 제공하는 병원 보기">
                                <span class="badge bg-outline-secondary border"><?php echo intval($t['hospital_count']); ?></span>
                            </a>
                        </td>
                        <td class="text-center">
                            <a href="admin_treatments.php?review_for=<?php echo $t['id']; ?>" class="text-decoration-none" title="이 시술의 후기 보기" data-bs-toggle="modal" data-bs-target="#reviewModal" onclick="loadReviews(<?php echo $t['id']; ?>, '<?php echo htmlspecialchars(addslashes($t['name']), ENT_QUOTES); ?>')">
                                <span class="badge bg-outline-secondary border"><?php echo intval($t['review_count']); ?></span>
                            </a>
                        </td>
                        <td class="text-center">
                            <?php if ($t['is_active']): ?>
                                <span class="badge bg-success">활성</span>
                            <?php else: ?>
                                <span class="badge bg-danger">비활성</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <!-- 수정 -->
                            <button class="btn btn-outline-primary btn-sm" title="수정"
                                onclick='openEditModal(<?php echo htmlspecialchars(json_encode($t, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES); ?>)'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <!-- 별칭 -->
                            <button class="btn btn-outline-info btn-sm" title="별칭 관리"
                                onclick="openAliasModal(<?php echo $t['id']; ?>, '<?php echo htmlspecialchars(addslashes($t['name']), ENT_QUOTES); ?>')">
                                <i class="bi bi-translate"></i>
                            </button>
                            <!-- 토글 -->
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <?php if ($t['is_active']): ?>
                                <button class="btn btn-outline-warning btn-sm" title="비활성화"><i class="bi bi-eye-slash"></i></button>
                                <?php else: ?>
                                <button class="btn btn-outline-success btn-sm" title="활성화"><i class="bi bi-eye"></i></button>
                                <?php endif; ?>
                            </form>
                            <!-- 삭제 -->
                            <form method="post" class="d-inline" onsubmit="return confirm('정말 삭제하시겠습니까? 관련 별칭도 함께 삭제됩니다.');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <button class="btn btn-outline-danger btn-sm" title="삭제"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 등록 모달 -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>시술 등록</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">카테고리 <span class="text-danger">*</span></label>
                            <select name="category_id" class="form-select" required>
                                <option value="">선택하세요</option>
                                <?php foreach ($categories as $cid => $cat): ?>
                                <option value="<?php echo $cid; ?>"<?php echo ($filter_category > 0 && $filter_category == $cid) ? ' selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">시술명 <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required maxlength="200" placeholder="예: 울쎄라 리프팅" oninput="autoHashtag(this, 'create_hashtag')">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">정렬 순서</label>
                            <input type="number" name="sort_order" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">해시태그</label>
                            <input type="text" name="hashtag" id="create_hashtag" class="form-control" maxlength="100" placeholder="자동 생성됨">
                            <div class="form-text"># 접두사 자동 추가. 비워두면 시술명 기반 자동 생성.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">마취 유형</label>
                            <select name="anesthesia_type" class="form-select">
                                <?php foreach ($anesthesia_types as $at_value => $at_label): ?>
                                <option value="<?php echo htmlspecialchars($at_value); ?>"><?php echo htmlspecialchars($at_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">설명</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="시술에 대한 설명"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">기본 시술 상세 이미지</label>
                            <input type="file" name="default_image" class="form-control" accept=".jpg,.jpeg,.png,.gif,.webp">
                            <input type="url" name="default_image_url" class="form-control mt-2" maxlength="500" placeholder="또는 https://... 이미지 URL">
                            <div class="form-text">병원이 이 시술에 대한 이미지를 따로 등록하지 않은 경우에만 노출됩니다.</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">시술 시간 (분)</label>
                            <input type="number" name="duration_min" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">회복 기간 (일)</label>
                            <input type="number" name="recovery_days" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">반복 주기 (개월)</label>
                            <input type="number" name="repeat_cycle_months" class="form-control" value="0" min="0">
                            <div class="form-text">0이면 반복 불필요 (안내용)</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">재시술 간격 (일)</label>
                            <div class="input-group">
                                <input type="number" name="revisit_days" class="form-control" min="0" max="3650" inputmode="numeric" placeholder="예: 90">
                                <span class="input-group-text bg-white">
                                    <label class="mb-0 d-flex align-items-center gap-1"><input type="checkbox" name="revisit_strict" value="1" class="form-check-input mt-0"> 필수</label>
                                </span>
                            </div>
                            <div class="form-text">비우거나 0이면 제한 없음. 이 기간 전에 다시 예약하면 안내가 표시됩니다. <b>필수</b>를 체크하면 기간이 지나기 전에는 예약할 수 없고, 해제하면 권장(&quot;그래도 예약&quot; 가능)입니다.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">최소 가격 (만원)</label>
                            <input type="number" name="avg_price_min" class="form-control" value="0" min="0" step="1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">최대 가격 (만원)</label>
                            <input type="number" name="avg_price_max" class="form-control" value="0" min="0" step="1">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">주의사항</label>
                            <textarea name="precautions" class="form-control" rows="2" placeholder="시술 전후 주의사항"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>등록</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.cf-panel { border: 1px solid #dee2e6; border-radius: .75rem; background: #fafbfc; padding: .75rem; }
.cf-search { position: relative; margin-bottom: .6rem; }
.cf-search > i { position: absolute; left: .75rem; top: 50%; transform: translateY(-50%); color: #8a94a0; font-size: .85rem; pointer-events: none; }
.cf-search > input { padding-left: 2.1rem; border-radius: 2rem; background: #fff; }
.cf-chips { display: flex; flex-wrap: wrap; gap: .4rem; max-height: 168px; overflow-y: auto; padding: 2px; }
.cf-chip { position: relative; display: inline-flex; align-items: center; gap: .35rem; margin: 0; padding: .32rem .8rem; border: 1px solid #d5dae0; border-radius: 2rem; background: #fff; color: #3b4450; font-size: .85rem; line-height: 1.3; cursor: pointer; user-select: none; transition: background .12s, border-color .12s, color .12s; }
.cf-chip:hover { border-color: #9aa5b1; background: #f4f6f8; }
.cf-chip input { position: absolute; opacity: 0; pointer-events: none; }
.cf-chip .cf-check { display: none; font-size: .8rem; }
.cf-chip:has(input:checked) { background: #fdecef; border-color: #ac1830; color: #ac1830; font-weight: 600; }
.cf-chip:has(input:checked) .cf-check { display: inline-flex; }
/* 선택된 시술은 맨 앞으로, 선택을 풀면 원래(이름순) 자리로 돌아간다 */
.cf-chip:has(input:checked) { order: -1; }
.cf-chip:has(input:focus-visible) { box-shadow: 0 0 0 .2rem rgba(172, 24, 48, .2); }
.cf-empty { padding: .6rem; text-align: center; color: #8a94a0; font-size: .85rem; }
</style>

<!-- 수정 모달 -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>시술 수정</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">카테고리 <span class="text-danger">*</span></label>
                            <select name="category_id" id="edit_category_id" class="form-select" required>
                                <option value="">선택하세요</option>
                                <?php foreach ($categories as $cid => $cat): ?>
                                <option value="<?php echo $cid; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">시술명 <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="edit_name" class="form-control" required maxlength="200" oninput="autoHashtag(this, 'edit_hashtag')">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">정렬 순서</label>
                            <input type="number" name="sort_order" id="edit_sort_order" class="form-control" min="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">해시태그</label>
                            <input type="text" name="hashtag" id="edit_hashtag" class="form-control" maxlength="100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">마취 유형</label>
                            <select name="anesthesia_type" id="edit_anesthesia_type" class="form-select">
                                <?php foreach ($anesthesia_types as $at_value => $at_label): ?>
                                <option value="<?php echo htmlspecialchars($at_value); ?>"><?php echo htmlspecialchars($at_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">설명</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">기본 시술 상세 이미지</label>
                            <div id="edit_default_image_preview" class="mb-2"></div>
                            <input type="hidden" name="current_default_image_url" id="edit_current_default_image_url">
                            <input type="file" name="default_image" id="edit_default_image" class="form-control" accept=".jpg,.jpeg,.png,.gif,.webp">
                            <input type="text" name="default_image_url" id="edit_default_image_url" class="form-control mt-2" maxlength="500" placeholder="또는 https://... 이미지 URL">
                            <div class="form-check mt-2" id="edit_remove_default_image_wrap">
                                <input class="form-check-input" type="checkbox" name="remove_default_image" value="1" id="edit_remove_default_image">
                                <label class="form-check-label" for="edit_remove_default_image">현재 기본 이미지 삭제</label>
                            </div>
                            <div class="form-text">파일을 선택하거나 새 URL을 입력하면 기존 이미지가 교체됩니다.</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">시술 시간 (분)</label>
                            <input type="number" name="duration_min" id="edit_duration_min" class="form-control" min="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">회복 기간 (일)</label>
                            <input type="number" name="recovery_days" id="edit_recovery_days" class="form-control" min="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">반복 주기 (개월)</label>
                            <input type="number" name="repeat_cycle_months" id="edit_repeat_cycle_months" class="form-control" min="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">재시술 간격 (일)</label>
                            <div class="input-group">
                                <input type="number" name="revisit_days" id="edit_revisit_days" class="form-control" min="0" max="3650" inputmode="numeric" placeholder="예: 90">
                                <span class="input-group-text bg-white">
                                    <label class="mb-0 d-flex align-items-center gap-1"><input type="checkbox" name="revisit_strict" id="edit_revisit_strict" value="1" class="form-check-input mt-0"> 필수</label>
                                </span>
                            </div>
                            <div class="form-text">비우거나 0이면 제한 없음. 이 기간 전에 다시 예약하면 안내가 표시됩니다. <b>필수</b>를 체크하면 기간이 지나기 전에는 예약할 수 없고, 해제하면 권장(&quot;그래도 예약&quot; 가능)입니다.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">최소 가격 (만원)</label>
                            <input type="number" name="avg_price_min" id="edit_avg_price_min" class="form-control" min="0" step="1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">최대 가격 (만원)</label>
                            <input type="number" name="avg_price_max" id="edit_avg_price_max" class="form-control" min="0" step="1">
                        </div>
                        <div class="col-12">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <label class="form-label fw-bold mb-0">같은 날 함께 예약 불가 시술</label>
                                <span class="badge rounded-pill text-bg-light border" id="edit_conflict_count">0개 선택</span>
                            </div>
                            <?php if ($tm_conflict_ready): ?>
                            <input type="hidden" name="conflict_field" value="1">
                            <div class="cf-panel">
                                <div class="cf-search">
                                    <i class="bi bi-search"></i>
                                    <input type="text" id="edit_conflict_search" class="form-control form-control-sm" placeholder="시술 이름 검색" autocomplete="off">
                                </div>
                                <div class="cf-chips" id="edit_conflict_box">
                                    <?php foreach ($tm_conflict_masters as $cm): ?>
                                    <label class="cf-chip" data-tm-id="<?php echo (int)$cm['id']; ?>" data-name="<?php echo htmlspecialchars(mb_strtolower($cm['name']), ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="checkbox" name="conflict_with[]" value="<?php echo (int)$cm['id']; ?>">
                                        <span class="cf-check"><i class="bi bi-slash-circle"></i></span>
                                        <span><?php echo htmlspecialchars($cm['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                                <div class="cf-empty" id="edit_conflict_empty" style="display:none;">검색 결과가 없습니다.</div>
                            </div>
                            <div class="form-text"><i class="bi bi-info-circle me-1"></i>선택한 시술과는 같은 날 함께 예약할 수 없습니다. 상대 시술에도 자동으로 반영됩니다(양방향).</div>
                            <?php else: ?>
                            <div class="alert alert-warning py-2 mb-0 small">조합 테이블이 아직 없습니다. <code>sql/treatment_conflicts_20261023.sql</code> 을 먼저 실행해주세요.</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">주의사항</label>
                            <textarea name="precautions" id="edit_precautions" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>저장</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 별칭 관리 모달 -->
<div class="modal fade" id="aliasModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-translate me-2"></i>별칭 관리 - <span id="alias_treatment_name"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- 기존 별칭 목록 -->
                <div id="aliasListContainer" class="mb-3">
                    <p class="text-muted" id="aliasEmpty">별칭이 없습니다.</p>
                    <ul class="list-group" id="aliasList"></ul>
                </div>

                <hr>

                <!-- 별칭 추가 폼 -->
                <form method="post">
                    <input type="hidden" name="action" value="add_alias">
                    <input type="hidden" name="treatment_id" id="alias_treatment_id">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <div class="row g-2">
                        <div class="col-5">
                            <input type="text" name="alias" class="form-control form-control-sm" placeholder="별칭 입력" required>
                        </div>
                        <div class="col-4">
                            <select name="lang" class="form-select form-select-sm">
                                <?php foreach ($lang_options as $lk => $lv): ?>
                                <option value="<?php echo $lk; ?>"><?php echo htmlspecialchars($lv); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-3">
                            <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-plus-lg"></i> 추가</button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
            </div>
        </div>
    </div>
</div>

<!-- 벌크 액션 숨은 폼 -->
<form method="post" id="bulkForm" style="display:none;">
    <input type="hidden" name="action" id="bulkAction">
    <input type="hidden" name="toggle_to" id="bulkToggleTo">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <div id="bulkIdsContainer"></div>
</form>

<script>
// 전체 별칭 데이터 (PHP -> JS)
var allAliases = <?php echo json_encode($all_aliases, JSON_UNESCAPED_UNICODE); ?>;
var csrfToken = '<?php echo $csrf_token; ?>';
var langMap = <?php echo json_encode($lang_options, JSON_UNESCAPED_UNICODE); ?>;

// 중분류 드래그 앤 드롭
document.addEventListener('DOMContentLoaded', function() {
    var tBody = document.querySelector('#treatmentTable tbody');
    if (tBody) {
        new Sortable(tBody, {
            handle: '.drag-handle-t',
            animation: 150,
            ghostClass: 'table-primary',
            onEnd: function() {
                var order = [];
                tBody.querySelectorAll('tr[data-id]').forEach(function(tr) {
                    order.push(tr.dataset.id);
                });
                var fd = new FormData();
                fd.append('action', 'reorder_treatments');
                fd.append('csrf_token', csrfToken);
                fd.append('order', JSON.stringify(order));
                fetch('admin_treatments.php', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); }).then(function(d) {
                    if (d.ok) {
                        tBody.querySelectorAll('tr[data-id] .sort-num').forEach(function(el, i) {
                            el.textContent = i + 1;
                        });
                    }
                });
            }
        });
    }
});

// 해시태그 자동 생성
function autoHashtag(input, targetId) {
    var target = document.getElementById(targetId);
    if (target && target.value === '' || target.dataset.auto === '1') {
        target.value = '#' + input.value.replace(/\s+/g, '');
        target.dataset.auto = '1';
    }
}

// 수정 모달 열기
window.TM_CONFLICTS = <?php echo json_encode((object)$tm_conflict_map); ?>;
// 함께 예약 불가 시술: 검색 필터 + 선택 개수 표시
function cfRefresh() {
    var box = document.getElementById('edit_conflict_box');
    if (!box) return;
    var q = ((document.getElementById('edit_conflict_search') || {}).value || '').trim().toLowerCase();
    var visible = 0, checked = 0;
    box.querySelectorAll('label[data-tm-id]').forEach(function(lb) {
        var isSelf = lb.dataset.self === '1';
        var match = !q || (lb.getAttribute('data-name') || '').indexOf(q) !== -1;
        lb.style.display = (!isSelf && match) ? '' : 'none';
        if (!isSelf && match) visible++;
        if (!isSelf && lb.querySelector('input').checked) checked++;
    });
    var cnt = document.getElementById('edit_conflict_count');
    if (cnt) cnt.textContent = checked + '개 선택';
    var empty = document.getElementById('edit_conflict_empty');
    if (empty) empty.style.display = visible ? 'none' : '';
}
document.addEventListener('DOMContentLoaded', function() {
    var box = document.getElementById('edit_conflict_box');
    var search = document.getElementById('edit_conflict_search');
    if (box) box.addEventListener('change', cfRefresh);
    if (search) search.addEventListener('input', cfRefresh);
});
function openEditModal(t) {
    document.getElementById('edit_id').value = t.id;
    document.getElementById('edit_category_id').value = t.category_id;
    document.getElementById('edit_sort_order').value = t.sort_order || 0;
    document.getElementById('edit_name').value = t.name;
    document.getElementById('edit_hashtag').value = t.hashtag || '';
    document.getElementById('edit_hashtag').dataset.auto = '0';
    document.getElementById('edit_description').value = t.description || '';
    document.getElementById('edit_current_default_image_url').value = t.default_image_url || '';
    document.getElementById('edit_default_image_url').value = '';
    document.getElementById('edit_default_image').value = '';
    document.getElementById('edit_remove_default_image').checked = false;
    document.getElementById('edit_remove_default_image_wrap').style.display = t.default_image_url ? '' : 'none';
    var preview = document.getElementById('edit_default_image_preview');
    preview.textContent = '';
    if (t.default_image_url) {
        var image = document.createElement('img');
        image.src = t.default_image_url;
        image.alt = '현재 기본 이미지';
        image.style.cssText = 'max-width:240px;max-height:160px;object-fit:cover;border-radius:8px;';
        preview.appendChild(image);
    } else {
        preview.innerHTML = '<span class="text-muted small">등록된 기본 이미지가 없습니다.</span>';
    }
    document.getElementById('edit_duration_min').value = t.duration_min || 0;
    document.getElementById('edit_recovery_days').value = t.recovery_days || 0;
    document.getElementById('edit_anesthesia_type').value = t.anesthesia_type || 'none';
    document.getElementById('edit_repeat_cycle_months').value = t.repeat_cycle_months || 0;
    document.getElementById('edit_revisit_days').value = t.revisit_days ? t.revisit_days : '';
    document.getElementById('edit_revisit_strict').checked = String(t.revisit_strict) === '1';
    document.getElementById('edit_avg_price_min').value = t.avg_price_min || 0;
    document.getElementById('edit_avg_price_max').value = t.avg_price_max || 0;
    document.getElementById('edit_precautions').value = t.precautions || '';
    // [05-18] 함께 예약 불가 시술 체크 상태 (자기 자신은 숨김)
    var cbox = document.getElementById('edit_conflict_box');
    if (cbox) {
        var picked = (window.TM_CONFLICTS && window.TM_CONFLICTS[t.id]) || [];
        var csearch = document.getElementById('edit_conflict_search');
        if (csearch) csearch.value = '';
        cbox.querySelectorAll('label[data-tm-id]').forEach(function(lb) {
            var tid = parseInt(lb.getAttribute('data-tm-id'), 10);
            lb.dataset.self = (tid === parseInt(t.id, 10)) ? '1' : '';
            lb.querySelector('input').checked = picked.indexOf(tid) !== -1;
        });
        cfRefresh();
        cbox.scrollTop = 0;
    }
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

// 별칭 모달 열기
function openAliasModal(treatmentId, treatmentName) {
    document.getElementById('alias_treatment_id').value = treatmentId;
    document.getElementById('alias_treatment_name').textContent = treatmentName;

    var aliases = allAliases[treatmentId] || [];
    var list = document.getElementById('aliasList');
    var empty = document.getElementById('aliasEmpty');

    list.innerHTML = '';
    if (aliases.length === 0) {
        empty.style.display = 'block';
    } else {
        empty.style.display = 'none';
        aliases.forEach(function(a) {
            var li = document.createElement('li');
            li.className = 'list-group-item d-flex justify-content-between align-items-center';
            li.innerHTML =
                '<span>' +
                    '<span class="badge bg-secondary me-2">' + (langMap[a.lang] || a.lang) + '</span>' +
                    escapeHtml(a.alias) +
                '</span>' +
                '<form method="post" class="d-inline" onsubmit="return confirm(\'이 별칭을 삭제하시겠습니까?\');">' +
                    '<input type="hidden" name="action" value="delete_alias">' +
                    '<input type="hidden" name="alias_id" value="' + a.id + '">' +
                    '<input type="hidden" name="treatment_id" value="' + treatmentId + '">' +
                    '<input type="hidden" name="csrf_token" value="' + csrfToken + '">' +
                    '<button class="btn btn-outline-danger btn-sm py-0 px-1" title="삭제"><i class="bi bi-x-lg"></i></button>' +
                '</form>';
            list.appendChild(li);
        });
    }

    new bootstrap.Modal(document.getElementById('aliasModal')).show();
}

// HTML 이스케이프
function escapeHtml(text) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}

// 전체 선택 토글
function toggleAll(cb) {
    document.querySelectorAll('.row-check').forEach(function(c) { c.checked = cb.checked; });
    updateBulkUI();
}

// 벌크 UI 업데이트
function updateBulkUI() {
    var checked = document.querySelectorAll('.row-check:checked');
    var bulkActions = document.getElementById('bulkActions');
    var countEl = document.getElementById('selectedCount');
    if (checked.length > 0) {
        bulkActions.style.display = '';
        countEl.style.display = '';
        countEl.textContent = checked.length + '개 선택됨';
    } else {
        bulkActions.style.display = 'none';
        countEl.style.display = 'none';
    }
}

// 벌크 삭제
function bulkDelete() {
    var checked = document.querySelectorAll('.row-check:checked');
    if (checked.length === 0) return;
    if (!confirm(checked.length + '개 시술을 삭제하시겠습니까? 관련 별칭도 함께 삭제됩니다.')) return;

    var form = document.getElementById('bulkForm');
    document.getElementById('bulkAction').value = 'bulk_delete';
    var container = document.getElementById('bulkIdsContainer');
    container.innerHTML = '';
    checked.forEach(function(c) {
        var inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'selected_ids[]'; inp.value = c.value;
        container.appendChild(inp);
    });
    form.style.display = '';
    form.submit();
}

// 벌크 토글
function bulkToggle(toggleTo) {
    var checked = document.querySelectorAll('.row-check:checked');
    if (checked.length === 0) return;
    var action = toggleTo ? '활성화' : '비활성화';
    if (!confirm(checked.length + '개 시술을 ' + action + '하시겠습니까?')) return;

    var form = document.getElementById('bulkForm');
    document.getElementById('bulkAction').value = 'bulk_toggle';
    document.getElementById('bulkToggleTo').value = toggleTo;
    var container = document.getElementById('bulkIdsContainer');
    container.innerHTML = '';
    checked.forEach(function(c) {
        var inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'selected_ids[]'; inp.value = c.value;
        container.appendChild(inp);
    });
    form.style.display = '';
    form.submit();
}

// alias_for 파라미터가 있으면 별칭 모달 자동 오픈
<?php if ($alias_for > 0): ?>
document.addEventListener('DOMContentLoaded', function() {
    <?php
    // 해당 시술명 조회
    $alias_name = '';
    foreach ($rows as $r) { if ($r['id'] == $alias_for) { $alias_name = $r['name']; break; } }
    if ($alias_name === '') {
        $stmt_n = mysqli_prepare($connect, "SELECT name FROM gangnamunni_treatment_master WHERE id = ?");
        if ($stmt_n) {
            mysqli_stmt_bind_param($stmt_n, 'i', $alias_for);
            mysqli_stmt_execute($stmt_n);
            $n_r = mysqli_stmt_get_result($stmt_n);
            if ($n_r && $n_row = mysqli_fetch_assoc($n_r)) $alias_name = $n_row['name'];
            mysqli_stmt_close($stmt_n);
        }
    }
    ?>
    openAliasModal(<?php echo $alias_for; ?>, '<?php echo htmlspecialchars(addslashes($alias_name), ENT_QUOTES); ?>');
});
<?php endif; ?>
</script>

<!-- 후기 모달 -->
<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-chat-left-text me-2"></i>시술 후기 - <span id="review_treatment_name"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="reviewListContainer">
                    <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
            </div>
        </div>
    </div>
</div>

<script>
// 테이블 정렬
var sortDir = {};
function sortTable(colIdx, type) {
    var table = document.getElementById('treatmentTable');
    if (!table) return;
    var tbody = table.querySelector('tbody');
    var rows = Array.from(tbody.querySelectorAll('tr[data-id]'));
    if (rows.length === 0) return;

    var dir = sortDir[colIdx] === 'asc' ? 'desc' : 'asc';
    sortDir[colIdx] = dir;

    rows.sort(function(a, b) {
        var aCell = a.cells[colIdx] ? a.cells[colIdx].textContent.trim() : '';
        var bCell = b.cells[colIdx] ? b.cells[colIdx].textContent.trim() : '';
        if (type === 'num') {
            var aNum = parseFloat(aCell.replace(/[^0-9.\-]/g, '')) || 0;
            var bNum = parseFloat(bCell.replace(/[^0-9.\-]/g, '')) || 0;
            return dir === 'asc' ? aNum - bNum : bNum - aNum;
        } else {
            return dir === 'asc' ? aCell.localeCompare(bCell, 'ko') : bCell.localeCompare(aCell, 'ko');
        }
    });

    rows.forEach(function(row) { tbody.appendChild(row); });
}

// 후기 로드
function loadReviews(treatmentId, treatmentName) {
    document.getElementById('review_treatment_name').textContent = treatmentName;
    var container = document.getElementById('reviewListContainer');
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';

    fetch('api_treatments.php?action=reviews&treatment_id=' + treatmentId)
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.reviews || data.reviews.length === 0) {
            container.innerHTML = '<div class="text-center py-4 text-muted"><i class="bi bi-inbox d-block mb-2" style="font-size:2rem;"></i>후기가 없습니다.</div>';
            return;
        }
        var html = '<div class="list-group">';
        data.reviews.forEach(function(r) {
            var stars = '';
            for (var i = 0; i < 5; i++) stars += i < (r.rating || 0) ? '★' : '☆';
            html += '<div class="list-group-item">' +
                '<div class="d-flex justify-content-between align-items-start">' +
                    '<div><strong>' + escapeHtml(r.hospital_name || '병원') + '</strong>' +
                    ' <span class="text-warning">' + stars + '</span></div>' +
                    '<small class="text-muted">' + (r.created_at || '') + '</small>' +
                '</div>' +
                '<p class="mb-1 mt-1">' + escapeHtml(r.content || r.review_text || '-') + '</p>' +
                '<small class="text-muted">작성자: ' + escapeHtml(r.author || r.user_name || '익명') + '</small>' +
            '</div>';
        });
        html += '</div>';
        container.innerHTML = html;
    })
    .catch(function() {
        container.innerHTML = '<div class="text-center py-4 text-muted">후기를 불러올 수 없습니다.</div>';
    });
}
</script>

<?php endif; // end tab switch ?>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
