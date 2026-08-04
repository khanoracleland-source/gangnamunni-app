<?php
/**
 * 병원 관리 페이지
 * 병원 목록, 상세 보기, 수정, 승인/반려 워크플로우
 * Bootstrap 5.3 + 주간/야간 모드
 * @created 2026-03-04
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/notification_helper.php';
require_once __DIR__ . '/includes/hospital_treatment_images.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("MySQL 연결 실패: " . mysqli_connect_error());
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
hti_ensure_schema($connect);

// 승인/반려 메일 본문에서 SITE_FULL_URL 을 사용하는데, 이 상수는 admin_header.php(하단 include)에서
// 정의되어 POST 처리 시점엔 미정의 → PHP 8 에서 "Undefined constant" 치명적 오류(백지화) 발생.
// 다른 admin_* 페이지와 동일하게 여기서 미리 guard-define 한다.
if (!defined('SITE_FULL_URL')) define('SITE_FULL_URL', 'https://hms.seoulbeauty.app/hairwang/gangnamunni/');

// 로그인 + 권한 체크
require_admin_login();
require_permission('hospitals');

if (empty($_SESSION['admin_hospitals_csrf'])) {
    $_SESSION['admin_hospitals_csrf'] = bin2hex(random_bytes(32));
}
$admin_hospitals_csrf = $_SESSION['admin_hospitals_csrf'];

// 병원 역할 격리: get_hospital_filter() 는 super_admin/admin/editor(hospital_id 없음)면 null(전체),
// hospital_* 역할이면 자기 hospital_id 반환 → superadmin 은 전체 병원, 병원 관리자는 자기 병원만 표시.
require_once __DIR__ . '/includes/hospital_filter.php';
require_once __DIR__ . '/includes/hospital_provision_helper.php'; // 승인 시 계정 자동 생성(단일/일괄 공통)
// 다병원 지원: 한 관리자가 email 이 일치하는 여러 병원을 소유할 수 있다. null 이면 전체(플랫폼).
$my_ids = get_hospital_filter_ids($connect);
// editor 는 항상 본인 소속 병원으로 제한(공유 헬퍼는 건드리지 않고 이 페이지에서만 강제).
// 소속 병원 없는 editor 는 빈 배열 → 목록 아무것도 없음 + 아래 POST 가드가 모든 액션 차단.
if (($_SESSION['ad_admin_role'] ?? '') === 'editor' && $my_ids === null) {
    $ed_hid = (int)($_SESSION['ad_admin_hospital_id'] ?? 0);
    $my_ids = $ed_hid > 0 ? [$ed_hid] : [];
}
$my_in  = $my_ids === null ? null : (empty($my_ids) ? '0' : implode(',', array_map('intval', $my_ids)));

// 병원 역할은 플랫폼 전용 액션(승인/반려/삭제/일괄) 금지 + 나머지 액션은 자기 소유 병원만 대상 허용.
if ($my_ids !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $_pa = $_POST['action'] ?? '';
    $_platform_only = ['approve', 'reject', 'bulk_approve', 'bulk_reject', 'bulk_delete', 'delete_hospital'];
    $_target_hid = intval($_POST['hospital_id'] ?? 0);
    if (in_array($_pa, $_platform_only, true) || !in_array($_target_hid, $my_ids, true)) {
        header('Location: admin_hospitals.php?msg=' . urlencode('접근 권한이 없습니다.'));
        exit;
    }
}

$message = '';
$error = '';
$current_admin = get_current_admin();

// ===== POST 처리 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['admin_csrf']) || !hash_equals($admin_hospitals_csrf, (string)$_POST['admin_csrf'])) {
        http_response_code(403);
        exit('잘못된 요청입니다. 페이지를 새로고침한 후 다시 시도해주세요.');
    }
    $action = $_POST['action'] ?? '';

    // 승인 — 자동 계정 3곳 생성 (gangnamunni_admins + g5_member + sso_tokens) + 이메일
    if ($action === 'approve') {
        $hospital_id = intval($_POST['hospital_id'] ?? 0);
        if ($hospital_id > 0) {
            // 병원 전체 정보 조회
            $stmt_info = mysqli_prepare($connect, "SELECT * FROM gangnamunni_hospitals WHERE id=?");
            mysqli_stmt_bind_param($stmt_info, 'i', $hospital_id);
            mysqli_stmt_execute($stmt_info);
            $hospital_info = mysqli_stmt_get_result($stmt_info)->fetch_assoc();
            mysqli_stmt_close($stmt_info);

            // 상태 업데이트
            $stmt = mysqli_prepare($connect,
                "UPDATE gangnamunni_hospitals SET status='approved', is_active=1, ad_grade='none', reviewed_by=?, reviewed_at=NOW(), updated_at=NOW() WHERE id=?"
            );
            $admin_name = $current_admin['name'];
            mysqli_stmt_bind_param($stmt, 'si', $admin_name, $hospital_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = "병원 #{$hospital_id}이(가) 승인되었습니다.";

                // 단일 승인도 bulk 승인과 동일한 공용 계정/메일 프로비저닝을 사용한다.
                if ($hospital_info) provision_hospital_accounts($connect, $hospital_info);

                /* 레거시 단일 승인 프로비저닝 코드는 공용 helper로 대체됨. */
                if (false && $hospital_info && !empty($hospital_info['email'])) {
                    $h_email = $hospital_info['email'];
                    $h_name = $hospital_info['name'];
                    $h_contact = $hospital_info['contact_name'] ?? $h_name;
                    $h_phone = $hospital_info['phone'] ?? '';
                    $h_contact_phone = $hospital_info['contact_phone'] ?? $h_phone;
                    $h_address = $hospital_info['address'] ?? '';
                    $h_owner = $hospital_info['owner_name'] ?? '';
                    $temp_password = substr(str_shuffle('abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8);
                    $hashed_pw = password_hash($temp_password, PASSWORD_DEFAULT);

                    // mb_id 생성: 이메일 앞부분, 최대 20자, 중복 시 숫자 추가
                    $mb_id_base = substr(preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $h_email)[0]), 0, 16);
                    if (empty($mb_id_base)) $mb_id_base = 'hosp';
                    $mb_id = $mb_id_base;
                    $mb_id_suffix = 1;
                    while (true) {
                        $chk_mb = mysqli_prepare($connect, "SELECT mb_id FROM g5_member WHERE mb_id=? LIMIT 1");
                        mysqli_stmt_bind_param($chk_mb, 's', $mb_id);
                        mysqli_stmt_execute($chk_mb);
                        $exist_mb = mysqli_stmt_get_result($chk_mb)->fetch_assoc();
                        mysqli_stmt_close($chk_mb);
                        if (!$exist_mb) break;
                        $mb_id = $mb_id_base . $mb_id_suffix;
                        $mb_id_suffix++;
                    }

                    // ① g5_member INSERT
                    $g5_exists = false;
                    $chk_g5 = mysqli_prepare($connect, "SELECT mb_id FROM g5_member WHERE mb_email=? LIMIT 1");
                    mysqli_stmt_bind_param($chk_g5, 's', $h_email);
                    mysqli_stmt_execute($chk_g5);
                    $g5_row = mysqli_stmt_get_result($chk_g5)->fetch_assoc();
                    mysqli_stmt_close($chk_g5);

                    if ($g5_row) {
                        $mb_id = $g5_row['mb_id'];
                        $g5_exists = true;
                        // mb_1을 hospital로 업데이트
                        $g5_pw_existing = password_hash($temp_password, PASSWORD_DEFAULT);
                        $upd_g5 = mysqli_prepare($connect, "UPDATE g5_member SET mb_1='hospital', mb_password=? WHERE mb_id=?");
                        mysqli_stmt_bind_param($upd_g5, 'ss', $g5_pw_existing, $mb_id);
                        mysqli_stmt_execute($upd_g5);
                        mysqli_stmt_close($upd_g5);
                        $message .= " 기존 회원({$mb_id}) → 병원관계자로 전환.";
                    } else {
                        // g5_member 비밀번호는 그누보드 방식 (sha256 + salt)
                        $g5_pw = password_hash($temp_password, PASSWORD_DEFAULT);
                        $mb_nick = $h_contact ?: $h_name;
                        $mb_1 = 'hospital'; // 병원관계자 표시
                        $mb_level = 2;
                        $now = date('Y-m-d H:i:s');

                        $stmt_g5 = mysqli_prepare($connect,
                            "INSERT INTO g5_member (mb_id, mb_password, mb_name, mb_nick, mb_email, mb_homepage, mb_level, mb_hp, mb_tel, mb_addr1, mb_1, mb_datetime, mb_ip, mb_mailling, mb_open) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)"
                        );
                        $website = $hospital_info['website'] ?? '';
                        $client_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                        mysqli_stmt_bind_param($stmt_g5, 'ssssssissssss',
                            $mb_id, $g5_pw, $h_contact, $mb_nick, $h_email,
                            $website, $mb_level, $h_contact_phone, $h_phone,
                            $h_address, $mb_1, $now, $client_ip
                        );
                        if (mysqli_stmt_execute($stmt_g5)) {
                            $message .= " 그누보드 회원({$mb_id}) 자동 생성.";
                        } else {
                            error_log('[Hospital Approve] g5_member INSERT 실패: ' . mysqli_error($connect));
                        }
                        mysqli_stmt_close($stmt_g5);
                    }

                    // ② gangnamunni_admins INSERT
                    $stmt_chk = mysqli_prepare($connect, "SELECT id, hospital_id FROM gangnamunni_admins WHERE username=? LIMIT 1");
                    mysqli_stmt_bind_param($stmt_chk, 's', $h_email);
                    mysqli_stmt_execute($stmt_chk);
                    $existing_admin = mysqli_stmt_get_result($stmt_chk)->fetch_assoc();
                    mysqli_stmt_close($stmt_chk);

                    if (!$existing_admin) {
                        $stmt_adm = mysqli_prepare($connect,
                            "INSERT INTO gangnamunni_admins (username, password, name, email, phone, role, hospital_id, hospital_name, is_active, created_at) VALUES (?, ?, ?, ?, ?, 'hospital_admin', ?, ?, 1, NOW())"
                        );
                        mysqli_stmt_bind_param($stmt_adm, 'sssssis', $h_email, $hashed_pw, $h_contact, $h_email, $h_contact_phone, $hospital_id, $h_name);
                        if (mysqli_stmt_execute($stmt_adm)) {
                            $message .= " 관리자 계정 자동 생성.";
                        }
                        mysqli_stmt_close($stmt_adm);
                    } else {
                        // 다병원: 기존 계정이 있으면 hospital_id만 업데이트 (최신 병원)
                        $message .= " 기존 관리자 계정 사용 (다병원).";
                        $upd_adm = mysqli_prepare($connect, "UPDATE gangnamunni_admins SET password=?, email=?, phone=?, hospital_id=?, hospital_name=?, is_active=1 WHERE username=?");
                        mysqli_stmt_bind_param($upd_adm, 'sssiss', $hashed_pw, $h_email, $h_contact_phone, $hospital_id, $h_name, $h_email);
                        mysqli_stmt_execute($upd_adm);
                        mysqli_stmt_close($upd_adm);
                    }

                    // ③ sso_tokens — SSO 토큰 생성 (로그인 준비)
                    $sso_token = bin2hex(random_bytes(32));
                    $sso_expires = date('Y-m-d H:i:s', time() + 86400);
                    $stmt_sso = mysqli_prepare($connect,
                        "INSERT INTO sso_tokens (token, user_type, g5_mb_id, ip_address, expires_at, created_at) VALUES (?, 'admin', ?, ?, ?, NOW())"
                    );
                    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                    mysqli_stmt_bind_param($stmt_sso, 'ssss', $sso_token, $mb_id, $client_ip, $sso_expires);
                    mysqli_stmt_execute($stmt_sso);
                    mysqli_stmt_close($stmt_sso);

                    // ④ 승인 이메일 발송 (3개 계정 정보 포함)
                    if (file_exists(__DIR__ . '/includes/mail_helper.php')) {
                        require_once __DIR__ . '/includes/mail_helper.php';
                        $approve_body = "<h2>병원 등록이 승인되었습니다!</h2>
                            <p>안녕하세요, <strong>" . htmlspecialchars($h_contact) . "</strong>님.</p>
                            <p><strong>" . htmlspecialchars($h_name) . "</strong>의 입점 신청이 승인되었습니다.</p>
                            <hr>
                            <h3>관리자 대시보드 로그인</h3>
                            <p><strong>URL:</strong> <a href='" . SITE_FULL_URL . "dashboard.php'>" . SITE_FULL_URL . "dashboard.php</a></p>
                            <p><strong>아이디:</strong> " . htmlspecialchars($h_email) . "</p>
                            <p><strong>임시 비밀번호:</strong> " . htmlspecialchars($temp_password) . "</p>
                            <hr>
                            <h3>커뮤니티 (그누보드) 로그인</h3>
                            <p><strong>아이디:</strong> " . htmlspecialchars($mb_id) . "</p>
                            <p><strong>비밀번호:</strong> " . htmlspecialchars($temp_password) . " (동일)</p>
                            <hr>
                            <p style='color:#e74c3c;'><strong>보안을 위해 로그인 후 반드시 비밀번호를 변경해 주세요.</strong></p>
                            <p>감사합니다.<br>서울미녀 팀</p>";
                        // 발송 결과를 확인·기록한다. 실패를 조용히 삼키면(@) "승인 완료"만 뜨고
                        // 실제 메일은 안 가는 상황이 생기므로, 결과를 관리자 화면과 로그에 반영한다.
                        $approve_mail_ok = false;
                        try {
                            $mres = mail_send(0, $h_email, '[서울미녀] 병원 등록 승인 완료 - 계정 안내', $approve_body);
                            $approve_mail_ok = is_array($mres) ? !empty($mres['success']) : (bool) $mres;
                        } catch (\Throwable $e) {
                            error_log('[Hospital Approve] 승인 이메일 예외: ' . $e->getMessage());
                        }
                        if ($approve_mail_ok) {
                            $message .= ' 승인 안내 메일을 ' . htmlspecialchars($h_email) . ' 로 발송했습니다.';
                        } else {
                            error_log('[Hospital Approve] 승인 이메일 발송 실패 → ' . $h_email);
                            $error = '병원은 승인됐지만 승인 안내 메일 발송에 실패했습니다. 메일 설정을 확인하거나 아래 계정 정보를 수동으로 전달해 주세요. (아이디: '
                                   . htmlspecialchars($h_email) . ' / 임시 비밀번호: ' . htmlspecialchars($temp_password) . ')';
                        }

                        // 병원 관리자에게 인앱 + FCM 알림
                        notifyMember($connect, $mb_id, 'hospital_approve',
                            '병원 등록이 승인되었습니다',
                            htmlspecialchars($h_name) . '의 입점이 승인되었습니다. 관리자 계정으로 로그인해주세요.',
                            'dashboard.php'
                        );
                    }
                }

                // 관리자들에게도 알림
                notifyAdmins($connect, 'hospital_approve',
                    '병원 승인 완료',
                    '병원 #' . $hospital_id . ' 승인됨',
                    'admin_hospitals.php'
                );
            } else {
                $error = '승인 처리 실패: ' . mysqli_error($connect);
            }
            mysqli_stmt_close($stmt);
        }
    }

    // 반려 — 사유 저장 + 이메일 발송
    if ($action === 'reject') {
        $hospital_id = intval($_POST['hospital_id'] ?? 0);
        $reject_reason = trim($_POST['reject_reason'] ?? '');
        if ($hospital_id > 0) {
            // 병원 정보 조회
            $stmt_info2 = mysqli_prepare($connect, "SELECT name, email, contact_name FROM gangnamunni_hospitals WHERE id=?");
            mysqli_stmt_bind_param($stmt_info2, 'i', $hospital_id);
            mysqli_stmt_execute($stmt_info2);
            $reject_info = mysqli_stmt_get_result($stmt_info2)->fetch_assoc();
            mysqli_stmt_close($stmt_info2);

            $stmt = mysqli_prepare($connect,
                "UPDATE gangnamunni_hospitals SET status='rejected', is_active=0, reviewed_by=?, reviewed_at=NOW(), reject_reason=?, rejection_reason=?, updated_at=NOW() WHERE id=?"
            );
            $admin_name = $current_admin['name'];
            mysqli_stmt_bind_param($stmt, 'sssi', $admin_name, $reject_reason, $reject_reason, $hospital_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = "병원 #{$hospital_id}이(가) 반려되었습니다.";

                // 반려 이메일 발송
                if ($reject_info && !empty($reject_info['email']) && file_exists(__DIR__ . '/includes/mail_helper.php')) {
                    require_once __DIR__ . '/includes/mail_helper.php';
                    $reject_body = "<h2>병원 등록 신청 반려 안내</h2>
                        <p>안녕하세요, <strong>" . htmlspecialchars($reject_info['contact_name'] ?? $reject_info['name']) . "</strong>님.</p>
                        <p><strong>" . htmlspecialchars($reject_info['name']) . "</strong>의 입점 신청이 반려되었습니다.</p>
                        <div style='background:#fff3cd;padding:15px;border-radius:8px;margin:15px 0;'>
                            <strong>반려 사유:</strong><br>" . nl2br(htmlspecialchars($reject_reason)) . "
                        </div>
                        <p>서류를 보완하신 후 재신청 가능합니다.</p>
                        <p><a href='" . SITE_FULL_URL . "hospital_apply.php'>재신청하기</a></p>
                        <p>감사합니다.<br>서울미녀 팀</p>";
                    try {
                        $reject_mail = mail_send(0, $reject_info['email'], '[서울미녀] 병원 등록 신청 반려 안내', $reject_body);
                        if (!(is_array($reject_mail) ? !empty($reject_mail['success']) : (bool)$reject_mail)) error_log('[Hospital Reject] 이메일 발송 실패 → ' . $reject_info['email']);
                    } catch (Throwable $e) { error_log('[Hospital Reject] 이메일 예외: ' . $e->getMessage()); }
                }

                // 관리자 알림
                notifyAdmins($connect, 'hospital_reject',
                    '병원 신청 반려',
                    htmlspecialchars($reject_info['name'] ?? '') . ' — ' . mb_substr($reject_reason, 0, 50),
                    'admin_hospitals.php'
                );
            } else {
                $error = '반려 처리 실패: ' . mysqli_error($connect);
            }
            mysqli_stmt_close($stmt);
        }
    }

    // 수정
    if ($action === 'edit_hospital') {
        // ================================================================
        // [프론트 #24 - 2026-05-28] 병원 수정 저장 시 HTTP 500 픽스
        // 원인 : 기존 코드 자체는 정상이나 HTTP-FPM 환경에서 일부 케이스 (메모리 한도,
        //        한글 POST 인코딩, 특정 컬럼 NULL 처리 등) 에서 500 발생 가능.
        // 변경 : try-catch + error_log + 메모리/실행시간 사전 보장 + NULL 안전 처리.
        //        실패 시 명확한 에러 메시지 + 서버 로그 기록으로 추가 진단 가능.
        // 영향 범위 : edit_hospital 핸들러만. 기존 SQL 로직 100% 보존 (try 안에 그대로).
        // ================================================================
        @ini_set('memory_limit', '256M');
        @set_time_limit(60);
        try {
            $hospital_id = intval($_POST['hospital_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $introduction = trim($_POST['introduction'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            // JSON 컬럼 정규화 — gangnamunni_hospitals 에 json_valid() CHECK 제약이 걸려 있어
            // (categories/badges/treatment_tags/subway_names/business_hours) 반드시 유효한 JSON 이어야 함.
            // 이미 유효한 JSON(예: treatment_tags 객체 배열)은 그대로 보존, CSV 문자열이면 배열로 변환,
            // 빈 값은 [] / {} 로 저장하여 CONSTRAINT 위반(저장 실패)을 방지한다.
            $normalize_json_list = function($raw) {
                $raw = trim($raw);
                if ($raw === '') return '[]';
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) return $raw; // 이미 유효한 JSON 배열/객체
                $items = array_values(array_filter(array_map('trim', explode(',', $raw)), function($v){ return $v !== ''; }));
                return json_encode($items, JSON_UNESCAPED_UNICODE);
            };
            $normalize_json_obj = function($raw) {
                $raw = trim($raw);
                if ($raw === '') return '{}';
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) return $raw;
                return '{}';
            };
            $categories = $normalize_json_list($_POST['categories'] ?? '');
            $badges = $normalize_json_list($_POST['badges'] ?? '');
            $treatment_tags = $normalize_json_list($_POST['treatment_tags'] ?? '');
            $subway_names = $normalize_json_list($_POST['subway_names'] ?? '');
            $business_hours = $normalize_json_obj($_POST['business_hours'] ?? '');
            $latitude = floatval($_POST['latitude'] ?? 0);
            $longitude = floatval($_POST['longitude'] ?? 0);
            $show_rating = isset($_POST['show_rating']) ? 1 : 0;
            $ad_grade_input = trim($_POST['ad_grade'] ?? 'none');
            $allowed_grades = ['S','A','B','C','D','E','none'];
            $ad_grade = in_array($ad_grade_input, $allowed_grades) ? $ad_grade_input : 'none';

            if ($hospital_id <= 0 || empty($name)) {
                $error = '병원 ID와 이름은 필수입니다.';
            } else {
                $stmt = mysqli_prepare($connect,
                    "UPDATE gangnamunni_hospitals SET name=?, description=?, introduction=?, address=?, phone=?, email=?, categories=?, badges=?, treatment_tags=?, subway_names=?, business_hours=?, latitude=?, longitude=?, show_rating=?, ad_grade=?, updated_at=NOW() WHERE id=?"
                );
                if (!$stmt) {
                    $err_msg = mysqli_error($connect);
                    error_log("[admin_hospitals #24] Prepare 실패: {$err_msg}");
                    $error = 'SQL Prepare 실패: ' . $err_msg;
                } else {
                    if (!mysqli_stmt_bind_param($stmt, 'sssssssssssddisi',
                        $name, $description, $introduction, $address, $phone, $email,
                        $categories, $badges, $treatment_tags, $subway_names, $business_hours,
                        $latitude, $longitude, $show_rating, $ad_grade, $hospital_id
                    )) {
                        $err_msg = mysqli_stmt_error($stmt);
                        error_log("[admin_hospitals #24] BindParam 실패: {$err_msg}");
                        $error = 'Bind Param 실패: ' . $err_msg;
                    } elseif (mysqli_stmt_execute($stmt)) {
                        $message = "병원 '{$name}' 정보가 수정되었습니다.";
                    } else {
                        $err_msg = mysqli_error($connect);
                        error_log("[admin_hospitals #24] Execute 실패: {$err_msg} / hospital_id={$hospital_id}");
                        $error = '수정 실패: ' . $err_msg;
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        } catch (Throwable $e) {
            error_log("[admin_hospitals #24] Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            $error = '시스템 오류가 발생했습니다. 관리자에게 문의하세요. (' . $e->getMessage() . ')';
        }
    }
    /* 기존 코드 (보존) — 신택스 참고용
    if ($action === 'edit_hospital_OLD_BACKUP') {
        // ... 기존 로직 동일 ...
    }
    */

    // 삭제
    if ($action === 'delete_hospital') {
        $hospital_id = intval($_POST['hospital_id'] ?? 0);
        if ($hospital_id > 0) {
            // 관련 이미지 삭제
            $stmt_img = mysqli_prepare($connect, "DELETE FROM hospital_images WHERE hospital_id=?");
            mysqli_stmt_bind_param($stmt_img, 'i', $hospital_id);
            mysqli_stmt_execute($stmt_img);
            mysqli_stmt_close($stmt_img);

            // 병원 삭제
            $stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_hospitals WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = "병원 #{$hospital_id}이(가) 삭제되었습니다.";
                // 삭제 후 목록으로 리다이렉트
                if (isset($_GET['view'])) {
                    header('Location: admin_hospitals.php?msg=' . urlencode($message));
                    exit;
                }
            } else {
                $error = '삭제 실패: ' . mysqli_error($connect);
            }
            mysqli_stmt_close($stmt);
        }
    }

    // 일괄 삭제
    if ($action === 'bulk_delete') {
        $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
        if (!empty($ids)) {
            $placeholders = implode(',', $ids);
            mysqli_query($connect, "DELETE FROM hospital_images WHERE hospital_id IN ({$placeholders})");
            mysqli_query($connect, "DELETE FROM gangnamunni_hospital_revisions WHERE hospital_id IN ({$placeholders})");
            $del_result = mysqli_query($connect, "DELETE FROM gangnamunni_hospitals WHERE id IN ({$placeholders})");
            $message = count($ids) . '개 병원이 삭제되었습니다.';
        }
    }

    // 일괄 승인
    if ($action === 'bulk_approve') {
        $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
        if (!empty($ids)) {
            $admin_name = $current_admin['name'];
            foreach ($ids as $bid) {
                $stmt_ba = mysqli_prepare($connect, "UPDATE gangnamunni_hospitals SET status='approved', is_active=1, ad_grade='none', reviewed_by=?, reviewed_at=NOW() WHERE id=? AND status='pending'");
                mysqli_stmt_bind_param($stmt_ba, 'si', $admin_name, $bid);
                mysqli_stmt_execute($stmt_ba);
                $ba_approved = mysqli_stmt_affected_rows($stmt_ba) > 0;
                mysqli_stmt_close($stmt_ba);
                // 실제로 대기→승인된 병원만 계정 자동 생성 (단일 승인과 동일하게 g5_member+관리자+SSO)
                if ($ba_approved) {
                    $ba_hi_r = mysqli_query($connect, "SELECT * FROM gangnamunni_hospitals WHERE id=" . (int)$bid);
                    $ba_hi = $ba_hi_r ? mysqli_fetch_assoc($ba_hi_r) : null;
                    if ($ba_hi) provision_hospital_accounts($connect, $ba_hi);
                }
            }
            $message = count($ids) . '개 병원이 승인되었습니다.';
        }
    }

    // 일괄 반려
    if ($action === 'bulk_reject') {
        $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
        $reason = trim($_POST['reject_reason'] ?? '일괄 반려');
        if (!empty($ids)) {
            $admin_name = $current_admin['name'];
            foreach ($ids as $bid) {
                $stmt_br = mysqli_prepare($connect, "UPDATE gangnamunni_hospitals SET status='rejected', is_active=0, reviewed_by=?, reviewed_at=NOW(), reject_reason=?, rejection_reason=? WHERE id=? AND status='pending'");
                mysqli_stmt_bind_param($stmt_br, 'sssi', $admin_name, $reason, $reason, $bid);
                mysqli_stmt_execute($stmt_br);
                mysqli_stmt_close($stmt_br);
            }
            $message = count($ids) . '개 병원이 반려되었습니다.';
        }
    }

    // 수정 이력 확인 완료
    if ($action === 'mark_reviewed') {
        $hospital_id = intval($_POST['hospital_id'] ?? 0);
        if ($hospital_id > 0) {
            mysqli_query($connect, "UPDATE gangnamunni_hospital_revisions SET reviewed=1 WHERE hospital_id={$hospital_id} AND reviewed=0");
            $stmt_mr = mysqli_prepare($connect, "UPDATE gangnamunni_hospitals SET has_unreviewed_changes=0 WHERE id=?");
            mysqli_stmt_bind_param($stmt_mr, 'i', $hospital_id);
            mysqli_stmt_execute($stmt_mr);
            mysqli_stmt_close($stmt_mr);
            $message = '수정 내역을 확인 완료했습니다.';
        }
    }

    // 활성/비활성 토글
    if ($action === 'toggle_active') {
        $hospital_id = intval($_POST['hospital_id'] ?? 0);
        if ($hospital_id > 0) {
            $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_hospitals SET is_active = IF(is_active=1, 0, 1), updated_at=NOW() WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = '활성 상태가 변경되었습니다.';
            }
            mysqli_stmt_close($stmt);
        }
    }

    // AI 설정 저장
    if ($action === 'save_ai_settings') {
        $hospital_id = intval($_POST['hospital_id'] ?? 0);
        if ($hospital_id > 0) {
            $ai_enabled = isset($_POST['ai_enabled']) ? 1 : 0;
            $ai_use_own_key = isset($_POST['ai_use_own_key']) ? 1 : 0;
            $ai_own_provider = in_array($_POST['ai_own_provider'] ?? '', ['openai','anthropic','google']) ? $_POST['ai_own_provider'] : 'openai';
            $ai_cost_from_wallet = isset($_POST['ai_cost_from_wallet']) ? 1 : 0;

            // 자체 키 암호화 저장
            $ai_key_raw = trim($_POST['ai_own_api_key'] ?? '');
            $ai_key_sql = '';
            if (!empty($ai_key_raw) && strpos($ai_key_raw, '****') === false) {
                // 새 키 입력 → 암호화
                require_once __DIR__ . '/includes/ai_proxy.php';
                $ai_key_encrypted = aiEncryptKey($ai_key_raw);
                $ai_key_sql = ", ai_own_api_key = '" . mysqli_real_escape_string($connect, $ai_key_encrypted) . "'";
            }

            $sql = "UPDATE gangnamunni_hospitals SET ai_enabled={$ai_enabled}, ai_use_own_key={$ai_use_own_key}, ai_own_provider='{$ai_own_provider}', ai_cost_from_wallet={$ai_cost_from_wallet}{$ai_key_sql}, updated_at=NOW() WHERE id={$hospital_id}";
            if (mysqli_query($connect, $sql)) {
                $message = 'AI 설정이 저장되었습니다.';
            } else {
                $error = 'AI 설정 저장 실패: ' . mysqli_error($connect);
            }
        }
    }

    // POST 후 redirect (PRG 패턴) - view 모드일 때
    if (!empty($message) && isset($_GET['view'])) {
        $view_id = intval($_GET['view']);
        $edit_flag = isset($_GET['edit']) ? '&edit=1' : '';
        header('Location: admin_hospitals.php?view=' . $view_id . $edit_flag . '&msg=' . urlencode($message));
        exit;
    }
}

// GET 파라미터에서 메시지 수신 (PRG 패턴)
if (isset($_GET['msg']) && !empty($_GET['msg'])) {
    $message = $_GET['msg'];
}

// ===== 상세 보기 / 수정 모드 =====
$view_mode = isset($_GET['view']) ? intval($_GET['view']) : 0;
$edit_mode = isset($_GET['edit']) && $_GET['edit'] == '1';
$hospital = null;
$hospital_images = [];
$application_treatments = [];

if ($view_mode > 0) {
    // 범위 제한 관리자(editor·병원역할)는 자기 소유 병원 상세만 열람 가능
    if ($my_ids !== null && !in_array($view_mode, $my_ids, true)) {
        header('Location: admin_hospitals.php?msg=' . urlencode('접근 권한이 없습니다.'));
        exit;
    }
    $stmt = mysqli_prepare($connect, "SELECT * FROM gangnamunni_hospitals WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $view_mode);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $hospital = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($hospital) {
        // 병원 이미지 조회
        $img_stmt = mysqli_prepare($connect, "SELECT * FROM hospital_images WHERE hospital_id = ? ORDER BY sort_order ASC, id ASC");
        mysqli_stmt_bind_param($img_stmt, 'i', $view_mode);
        mysqli_stmt_execute($img_stmt);
        $img_result = mysqli_stmt_get_result($img_stmt);
        while ($img_row = mysqli_fetch_assoc($img_result)) {
            $hospital_images[] = $img_row;
        }
        mysqli_stmt_close($img_stmt);

        // 신청서에서 선택한 시술과 함께 제출한 시술 이미지 조회
        try {
            $treatment_stmt = mysqli_prepare($connect,
                "SELECT ht.id AS hospital_treatment_id, ht.treatment_id, ht.status,
                        COALESCE(tm.name, CONCAT('시술 #', ht.treatment_id)) AS treatment_name,
                        ti.id AS image_id, ti.image_url, ti.caption, ti.approval_status, ti.reject_reason
                 FROM gangnamunni_hospital_treatments ht
                 LEFT JOIN gangnamunni_treatment_master tm ON tm.id = ht.treatment_id
                 LEFT JOIN gangnamunni_hospital_treatment_images ti ON ti.hospital_treatment_id = ht.id
                 WHERE ht.hospital_id = ?
                 ORDER BY tm.name ASC, ti.sort_order ASC, ti.id ASC");
            if ($treatment_stmt) {
                mysqli_stmt_bind_param($treatment_stmt, 'i', $view_mode);
                mysqli_stmt_execute($treatment_stmt);
                $treatment_result = mysqli_stmt_get_result($treatment_stmt);
                while ($row = mysqli_fetch_assoc($treatment_result)) {
                    $ht_id = (int)$row['hospital_treatment_id'];
                    if (!isset($application_treatments[$ht_id])) {
                        $application_treatments[$ht_id] = [
                            'name' => $row['treatment_name'],
                            'status' => $row['status'],
                            'images' => [],
                        ];
                    }
                    if (!empty($row['image_url'])) {
                        $application_treatments[$ht_id]['images'][] = [
                            'url' => $row['image_url'],
                            'caption' => $row['caption'] ?? '',
                            'approval_status' => $row['approval_status'] ?? 'approved',
                            'reject_reason' => $row['reject_reason'] ?? '',
                        ];
                    }
                }
                mysqli_stmt_close($treatment_stmt);
            }
        } catch (\mysqli_sql_exception $e) {
            error_log('[admin_hospitals] 신청 시술 이미지 조회 실패: ' . $e->getMessage());
        }
    }
}

// ===== 목록 모드: 필터, 검색, 페이징 =====
$filter_status = $_GET['status'] ?? 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
if (!in_array($per_page, [10, 20, 50])) $per_page = 20;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

// WHERE 조건 조립
$where = '1=1';
$where_params = [];
$where_types = '';

// 병원 역할이면 자기 소유 병원만 조회 (목록·통계 모두 격리, 다병원 IN)
$stat_scope = '';
if ($my_in !== null) {
    $where .= ' AND id IN (' . $my_in . ')';
    $stat_scope = ' AND id IN (' . $my_in . ')';
}

if ($filter_status === 'pending') {
    $where .= " AND status = 'pending'";
} elseif ($filter_status === 'approved') {
    $where .= " AND status = 'approved'";
} elseif ($filter_status === 'rejected') {
    $where .= " AND status = 'rejected'";
}

if (!empty($search)) {
    $where .= ' AND (name LIKE ? OR address LIKE ? OR phone LIKE ? OR email LIKE ? OR categories LIKE ?)';
    $search_param = "%{$search}%";
    $where_params = [$search_param, $search_param, $search_param, $search_param, $search_param];
    $where_types = 'sssss';
}

// 통계
$stats = [
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
];
$stat_result = mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_hospitals WHERE 1=1{$stat_scope}");
if ($stat_result) $stats['total'] = (int)mysqli_fetch_assoc($stat_result)['c'];
$stat_result = mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_hospitals WHERE status='pending'{$stat_scope}");
if ($stat_result) $stats['pending'] = (int)mysqli_fetch_assoc($stat_result)['c'];
$stat_result = mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_hospitals WHERE status='approved'{$stat_scope}");
if ($stat_result) $stats['approved'] = (int)mysqli_fetch_assoc($stat_result)['c'];
$stat_result = mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_hospitals WHERE status='rejected'{$stat_scope}");
if ($stat_result) $stats['rejected'] = (int)mysqli_fetch_assoc($stat_result)['c'];

// 필터링된 전체 건수
$count_sql = "SELECT COUNT(*) as c FROM gangnamunni_hospitals WHERE {$where}";
if (!empty($where_params)) {
    $count_stmt = mysqli_prepare($connect, $count_sql);
    mysqli_stmt_bind_param($count_stmt, $where_types, ...$where_params);
    mysqli_stmt_execute($count_stmt);
    $total = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['c'];
    mysqli_stmt_close($count_stmt);
} else {
    $total = (int)mysqli_fetch_assoc(mysqli_query($connect, $count_sql))['c'];
}
$total_pages = $total > 0 ? ceil($total / $per_page) : 0;

// 목록 조회
$hospitals = [];
if (!$view_mode) {
    $list_sql = "SELECT * FROM gangnamunni_hospitals WHERE {$where} ORDER BY created_at DESC LIMIT ?, ?";
    if (!empty($where_params)) {
        $list_stmt = mysqli_prepare($connect, $list_sql);
        $bind_types = $where_types . 'ii';
        $bind_params = array_merge($where_params, [$offset, $per_page]);
        mysqli_stmt_bind_param($list_stmt, $bind_types, ...$bind_params);
    } else {
        $list_stmt = mysqli_prepare($connect, $list_sql);
        mysqli_stmt_bind_param($list_stmt, 'ii', $offset, $per_page);
    }
    mysqli_stmt_execute($list_stmt);
    $list_result = mysqli_stmt_get_result($list_stmt);
    while ($row = mysqli_fetch_assoc($list_result)) {
        $hospitals[] = $row;
    }
    mysqli_stmt_close($list_stmt);
}

// URL 빌더 헬퍼 (현재 필터 유지)
function buildHospitalUrl($overrides = []) {
    global $filter_status, $per_page, $search;
    $params = [
        'status' => $overrides['status'] ?? $filter_status,
        'per_page' => $overrides['per_page'] ?? $per_page,
        'page' => $overrides['page'] ?? 1,
    ];
    if (!empty($overrides['search'] ?? $search)) {
        $params['search'] = $overrides['search'] ?? $search;
    }
    return '?' . http_build_query($params);
}

// 상태 배지 헬퍼
function statusBadge($status) {
    $map = [
        'pending'  => ['bg-warning text-dark', '대기'],
        'approved' => ['bg-success', '승인'],
        'rejected' => ['bg-danger', '반려'],
    ];
    $info = $map[$status] ?? ['bg-secondary', $status];
    return '<span class="badge ' . $info[0] . '">' . htmlspecialchars($info[1]) . '</span>';
}

// 공통 헤더
$admin_page_title = '병원 관리';
$admin_current = 'hospitals';

$admin_extra_css = '
.hospital-stat-card { border: none; border-radius: 12px; transition: transform 0.2s; cursor: pointer; }
.hospital-stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
.hospital-stat-card.active-filter { box-shadow: 0 0 0 2px var(--bs-primary); }
.hospital-detail-label { font-size: 12px; font-weight: 600; color: var(--bs-secondary-color); margin-bottom: 2px; }
.hospital-detail-value { font-size: 14px; margin-bottom: 12px; }
.hospital-img-thumb { width: 80px; height: 60px; object-fit: cover; border-radius: 6px; border: 1px solid var(--bs-border-color); }
.hospital-img-large { max-width: 100%; max-height: 300px; object-fit: contain; border-radius: 8px; }
.application-asset { height: 100%; border: 1px solid var(--bs-border-color); border-radius: 10px; overflow: hidden; background: var(--bs-body-bg); }
.application-asset-preview { height: 210px; display: flex; align-items: center; justify-content: center; background: var(--bs-tertiary-bg); overflow: hidden; }
.application-asset-preview img { width: 100%; height: 100%; object-fit: contain; cursor: pointer; }
.application-asset-missing { color: var(--bs-secondary-color); text-align: center; }
.application-treatment-image { width: 100%; height: 150px; object-fit: cover; cursor: pointer; border-radius: 8px; border: 1px solid var(--bs-border-color); }
.category-badge { font-size: 11px; padding: 3px 8px; border-radius: 10px; }
.tag-badge { font-size: 11px; padding: 2px 6px; border-radius: 4px; }
';

include __DIR__ . '/includes/admin_header.php';
?>

<!-- 알림 메시지 -->
<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show py-2" role="alert">
    <i class="bi bi-check-circle me-1"></i> <?php echo htmlspecialchars($message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
    <i class="bi bi-exclamation-circle me-1"></i> <?php echo htmlspecialchars($error); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($view_mode && $hospital): ?>
<!-- ============================================================ -->
<!-- 상세 보기 / 수정 모드                                        -->
<!-- ============================================================ -->

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <a href="admin_hospitals.php" class="btn btn-outline-secondary btn-sm me-2">
            <i class="bi bi-arrow-left"></i> 목록으로
        </a>
        <span class="fw-bold fs-5"><?php echo htmlspecialchars($hospital['name']); ?></span>
        <?php echo statusBadge($hospital['status'] ?? 'pending'); ?>
    </div>
    <div class="d-flex gap-1">
        <?php if (!$edit_mode): ?>
        <a href="?view=<?php echo $hospital['id']; ?>&edit=1" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil me-1"></i>수정
        </a>
        <?php else: ?>
        <a href="?view=<?php echo $hospital['id']; ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-eye me-1"></i>보기 모드
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($edit_mode): ?>
<!-- ===== 수정 폼 ===== -->
<form method="POST" id="editHospitalForm">
    <input type="hidden" name="action" value="edit_hospital">
    <input type="hidden" name="hospital_id" value="<?php echo $hospital['id']; ?>">

    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-pencil me-1"></i> 기본 정보 수정</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">병원 이름 <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control form-control-sm" required
                           value="<?php echo htmlspecialchars($hospital['name'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">이메일</label>
                    <input type="email" name="email" class="form-control form-control-sm"
                           value="<?php echo htmlspecialchars($hospital['email'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">전화번호</label>
                    <input type="text" name="phone" class="form-control form-control-sm"
                           value="<?php echo htmlspecialchars($hospital['phone'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">주소</label>
                    <input type="text" name="address" class="form-control form-control-sm"
                           value="<?php echo htmlspecialchars($hospital['address'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">광고 등급</label>
                    <?php $cur_grade = $hospital['ad_grade'] ?? 'none'; ?>
                    <select name="ad_grade" class="form-select form-select-sm">
                        <option value="none" <?php echo $cur_grade === 'none' ? 'selected' : ''; ?>>없음</option>
                        <option value="S" <?php echo $cur_grade === 'S' ? 'selected' : ''; ?>>S (최우선, 빨간 AD)</option>
                        <option value="A" <?php echo $cur_grade === 'A' ? 'selected' : ''; ?>>A (우선, 빨간 AD)</option>
                        <option value="B" <?php echo $cur_grade === 'B' ? 'selected' : ''; ?>>B (제휴, 파란 뱃지)</option>
                        <option value="C" <?php echo $cur_grade === 'C' ? 'selected' : ''; ?>>C (제휴, 파란 뱃지)</option>
                        <option value="D" <?php echo $cur_grade === 'D' ? 'selected' : ''; ?>>D (제휴, 회색 텍스트)</option>
                        <option value="E" <?php echo $cur_grade === 'E' ? 'selected' : ''; ?>>E (제휴, 회색 텍스트)</option>
                    </select>
                    <div class="form-text">S/A: 빨간 AD 뱃지 | B/C: 파란 제휴 뱃지 | D/E: 회색 제휴 텍스트 | 목록 상위 노출</div>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold small">설명</label>
                    <textarea name="description" class="form-control form-control-sm" rows="3"><?php echo htmlspecialchars($hospital['description'] ?? ''); ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold small">소개</label>
                    <textarea name="introduction" class="form-control form-control-sm" rows="3"><?php echo htmlspecialchars($hospital['introduction'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-tags me-1"></i> 카테고리 / 태그</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">카테고리 (쉼표 구분)</label>
                    <input type="text" name="categories" class="form-control form-control-sm"
                           value="<?php echo htmlspecialchars($hospital['categories'] ?? ''); ?>"
                           placeholder="성형외과, 피부과, 치과">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">뱃지 (쉼표 구분)</label>
                    <input type="text" name="badges" class="form-control form-control-sm"
                           value="<?php echo htmlspecialchars($hospital['badges'] ?? ''); ?>"
                           placeholder="인기, 신규, 이벤트">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">시술 태그 (쉼표 구분)</label>
                    <input type="text" name="treatment_tags" class="form-control form-control-sm"
                           value="<?php echo htmlspecialchars($hospital['treatment_tags'] ?? ''); ?>"
                           placeholder="보톡스, 필러, 리프팅">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">인근 지하철 (쉼표 구분)</label>
                    <input type="text" name="subway_names" class="form-control form-control-sm"
                           value="<?php echo htmlspecialchars($hospital['subway_names'] ?? ''); ?>"
                           placeholder="강남역, 신논현역">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold small">영업시간</label>
                    <?php
                    $bh_data = json_decode($hospital['business_hours'] ?? '{}', true) ?: [];
                    $bh_days = ['월'=>'월요일','화'=>'화요일','수'=>'수요일','목'=>'목요일','금'=>'금요일','토'=>'토요일','일'=>'일요일'];
                    $bh_statuses = ['normal'=>'정상진료','off'=>'휴무','construction'=>'공사중','temp_closed'=>'임시휴업'];
                    ?>
                    <input type="hidden" name="business_hours" id="bhHiddenInput" value="<?php echo htmlspecialchars($hospital['business_hours'] ?? ''); ?>">
                    <table class="table table-sm table-bordered mb-2" style="font-size:13px;">
                        <thead><tr><th style="width:75px;">요일</th><th style="width:110px;">시작</th><th style="width:110px;">종료</th><th style="width:110px;">상태</th></tr></thead>
                        <tbody>
                        <?php foreach ($bh_days as $bdk => $bdl):
                            $bval = $bh_data[$bdk] ?? '';
                            $b_is_off = in_array($bval, ['휴무','공사중','임시휴업','']);
                            $b_status = 'normal';
                            if ($bval === '휴무') $b_status = 'off';
                            elseif ($bval === '공사중') $b_status = 'construction';
                            elseif ($bval === '임시휴업') $b_status = 'temp_closed';
                            $b_parts = (!$b_is_off && strpos($bval, '~') !== false) ? explode('~', $bval) : ['', ''];
                        ?>
                        <tr>
                            <td class="fw-semibold"><?php echo $bdl; ?></td>
                            <td><input type="time" class="form-control form-control-sm bh-start" data-day="<?php echo $bdk; ?>" value="<?php echo htmlspecialchars($b_parts[0]); ?>" <?php echo $b_is_off ? 'disabled' : ''; ?>></td>
                            <td><input type="time" class="form-control form-control-sm bh-end" data-day="<?php echo $bdk; ?>" value="<?php echo htmlspecialchars($b_parts[1]); ?>" <?php echo $b_is_off ? 'disabled' : ''; ?>></td>
                            <td>
                                <select class="form-select form-select-sm bh-status" data-day="<?php echo $bdk; ?>" onchange="bhToggle(this)">
                                    <?php foreach ($bh_statuses as $bsv => $bsl): ?>
                                    <option value="<?php echo $bsv; ?>" <?php echo $b_status === $bsv ? 'selected' : ''; ?>><?php echo $bsl; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php $bh_lunch = $bh_data['점심'] ?? ''; $bh_lunch_parts = strpos($bh_lunch, '~') !== false ? explode('~', $bh_lunch) : ['13:00','14:00']; ?>
                    <div class="d-flex align-items-center gap-2 mb-2" style="font-size:13px;">
                        <span class="fw-semibold" style="width:75px;">점심시간</span>
                        <input type="time" class="form-control form-control-sm" id="bhLunchStart" value="<?php echo htmlspecialchars($bh_lunch_parts[0]); ?>" style="width:110px;">
                        <span>~</span>
                        <input type="time" class="form-control form-control-sm" id="bhLunchEnd" value="<?php echo htmlspecialchars($bh_lunch_parts[1] ?? '14:00'); ?>" style="width:110px;">
                    </div>
                    <script>
                    function bhToggle(sel) {
                        var tr = sel.closest('tr');
                        var inputs = tr.querySelectorAll('input[type=time]');
                        var off = (sel.value !== 'normal');
                        inputs.forEach(function(inp) { inp.disabled = off; if (off) inp.value = ''; });
                    }
                    function bhCollectJSON() {
                        var data = {};
                        var statusMap = {'off':'휴무','construction':'공사중','temp_closed':'임시휴업'};
                        document.querySelectorAll('.bh-status').forEach(function(sel) {
                            var dk = sel.dataset.day;
                            if (sel.value !== 'normal') {
                                data[dk] = statusMap[sel.value] || '휴무';
                            } else {
                                var s = document.querySelector('.bh-start[data-day="'+dk+'"]').value;
                                var e = document.querySelector('.bh-end[data-day="'+dk+'"]').value;
                                data[dk] = (s && e) ? s+'~'+e : '휴무';
                            }
                        });
                        var ls = document.getElementById('bhLunchStart').value;
                        var le = document.getElementById('bhLunchEnd').value;
                        if (ls && le) data['점심'] = ls+'~'+le;
                        document.getElementById('bhHiddenInput').value = JSON.stringify(data);
                    }
                    // 폼 제출 전 JSON 수집
                    var bhForm = document.getElementById('bhHiddenInput').closest('form');
                    if (bhForm) bhForm.addEventListener('submit', function() { bhCollectJSON(); });
                    </script>
                </div>
                <div class="col-md-6">
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="show_rating" id="showRating" <?php echo (($hospital['show_rating'] ?? 1) == 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label small" for="showRating">평점 표시 (OFF 시 상세페이지에서 별점/평점 숨김)</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    // 지도 피커 컴포넌트 (드래그 가능 마커 + 검색 + 주소→좌표 변환)
    $picker_config = [
        'lat_field'  => 'latitude',
        'lng_field'  => 'longitude',
        'addr_field' => 'address',
        'lat_value'  => $hospital['latitude'] ?? '',
        'lng_value'  => $hospital['longitude'] ?? '',
    ];
    include __DIR__ . '/includes/map_picker.php';
    ?>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>저장</button>
        <a href="?view=<?php echo $hospital['id']; ?>" class="btn btn-secondary btn-sm">취소</a>
    </div>
</form>

<!-- ===== 이미지 관리 섹션 ===== -->
<!-- 업로드/수정/삭제는 전용 페이지(admin_hospital_images.php)에서 처리 — 여기서는 현재 이미지 미리보기 + 관리 진입 -->
<div class="card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-images me-1"></i> 병원 이미지 (<?php echo count($hospital_images); ?>장)</span>
        <a href="admin_hospital_images.php?hospital_id=<?php echo $hospital['id']; ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-pencil-square me-1"></i>이미지 관리 (추가/수정/삭제)
        </a>
    </div>
    <div class="card-body">
        <?php if (!empty($hospital_images)): ?>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($hospital_images as $img): ?>
            <div class="text-center" style="width:100px;">
                <img src="<?php echo htmlspecialchars($img['image_url']); ?>" alt=""
                     style="width:100px;height:80px;object-fit:cover;border-radius:8px;border:1px solid #eee;">
                <div class="small text-muted mt-1"><?php echo htmlspecialchars($img['image_type']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-muted mb-0">등록된 이미지가 없습니다. <strong>"이미지 관리"</strong> 버튼으로 추가하세요.</p>
        <?php endif; ?>
    </div>
</div>

<!-- ===== AI 설정 섹션 ===== -->
<div class="card mt-3 border-info">
    <div class="card-header bg-info bg-opacity-10">
        <i class="bi bi-robot me-1"></i> AI 기능 설정
        <span class="badge bg-<?php echo (($hospital['ai_enabled'] ?? 0) == 1) ? 'success' : 'secondary'; ?> ms-2">
            <?php echo (($hospital['ai_enabled'] ?? 0) == 1) ? 'ON' : 'OFF'; ?>
        </span>
    </div>
    <div class="card-body">
        <form method="POST" action="?view=<?php echo $hospital['id']; ?>&edit=1">
            <input type="hidden" name="action" value="save_ai_settings">
            <input type="hidden" name="hospital_id" value="<?php echo $hospital['id']; ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="ai_enabled" id="aiEnabled" <?php echo (($hospital['ai_enabled'] ?? 0) == 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="aiEnabled"><strong>AI 기능 활성화</strong></label>
                        <div class="form-text">이 병원에서 AI 기능(후기검수, 광고검수, AI제안 등) 사용 허용</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="ai_use_own_key" id="aiUseOwnKey" <?php echo (($hospital['ai_use_own_key'] ?? 0) == 1) ? 'checked' : ''; ?> onchange="document.getElementById('ownKeyArea').style.display=this.checked?'block':'none'">
                        <label class="form-check-label" for="aiUseOwnKey"><strong>자체 AI API 키 사용</strong></label>
                        <div class="form-text">병원이 직접 등록한 API 키 사용 (플랫폼 비용 차감 없음)</div>
                    </div>
                </div>
            </div>

            <div id="ownKeyArea" style="display:<?php echo (($hospital['ai_use_own_key'] ?? 0) == 1) ? 'block' : 'none'; ?>;">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">AI 프로바이더</label>
                        <select name="ai_own_provider" class="form-select form-select-sm">
                            <option value="openai" <?php echo (($hospital['ai_own_provider'] ?? 'openai') === 'openai') ? 'selected' : ''; ?>>OpenAI (GPT)</option>
                            <option value="anthropic" <?php echo (($hospital['ai_own_provider'] ?? '') === 'anthropic') ? 'selected' : ''; ?>>Anthropic (Claude)</option>
                            <option value="google" <?php echo (($hospital['ai_own_provider'] ?? '') === 'google') ? 'selected' : ''; ?>>Google (Gemini)</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small fw-bold">API 키</label>
                        <input type="password" name="ai_own_api_key" class="form-control form-control-sm" placeholder="<?php echo !empty($hospital['ai_own_api_key']) ? '(등록됨 — 변경 시 새 키 입력)' : 'sk-... 또는 API 키 입력'; ?>">
                        <div class="form-text">변경하지 않으려면 비워두세요. AES-256-CBC 암호화 저장됩니다.</div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="ai_cost_from_wallet" id="aiCostWallet" <?php echo (($hospital['ai_cost_from_wallet'] ?? 1) == 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="aiCostWallet"><strong>플랫폼 키 사용 시 지갑 차감</strong></label>
                        <div class="form-text">자체 키 미사용 시, AI 비용을 CPV 지갑에서 차감</div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-info btn-sm"><i class="bi bi-check-lg me-1"></i>AI 설정 저장</button>
        </form>
    </div>
</div>

<?php else: ?>
<!-- ===== 상세 보기 ===== -->

<!-- 승인/반려 액션 카드 -->
<?php if (($hospital['status'] ?? 'pending') === 'pending'): ?>
<div class="card mb-3 border-warning">
    <div class="card-header bg-warning bg-opacity-10">
        <i class="bi bi-clock-history me-1"></i> 승인 대기 중 - 승인 또는 반려 처리가 필요합니다
    </div>
    <div class="card-body">
        <div class="d-flex gap-2 flex-wrap">
            <form method="POST" class="d-inline" onsubmit="return confirm('이 병원을 승인하시겠습니까?')">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="hospital_id" value="<?php echo $hospital['id']; ?>">
                <button type="submit" class="btn btn-success btn-sm">
                    <i class="bi bi-check-lg me-1"></i>승인
                </button>
            </form>
            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">
                <i class="bi bi-x-lg me-1"></i>반려
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (($hospital['status'] ?? '') === 'rejected' && !empty($hospital['reject_reason'])): ?>
<div class="alert alert-danger py-2 mb-3">
    <i class="bi bi-exclamation-triangle me-1"></i>
    <strong>반려 사유:</strong> <?php echo htmlspecialchars($hospital['reject_reason']); ?>
    <span class="text-body-secondary small ms-2">
        (<?php echo htmlspecialchars($hospital['reviewed_by'] ?? ''); ?>,
         <?php echo $hospital['reviewed_at'] ? date('Y-m-d H:i', strtotime($hospital['reviewed_at'])) : '-'; ?>)
    </span>
</div>
<?php endif; ?>

<?php if (($hospital['status'] ?? '') === 'approved' && !empty($hospital['reviewed_by'])): ?>
<div class="alert alert-success py-2 mb-3">
    <i class="bi bi-check-circle me-1"></i>
    <strong>승인 완료</strong> -
    <?php echo htmlspecialchars($hospital['reviewed_by']); ?>,
    <?php echo $hospital['reviewed_at'] ? date('Y-m-d H:i', strtotime($hospital['reviewed_at'])) : '-'; ?>
</div>
<?php endif; ?>

<!-- 수정됨 알림 -->
<?php
$unreviewed = $hospital['has_unreviewed_changes'] ?? 0;
$revisions = [];
if ($unreviewed) {
    $rv_stmt = mysqli_prepare($connect, "SELECT * FROM gangnamunni_hospital_revisions WHERE hospital_id=? AND reviewed=0 ORDER BY changed_at DESC");
    mysqli_stmt_bind_param($rv_stmt, 'i', $hospital['id']);
    mysqli_stmt_execute($rv_stmt);
    $rv_res = mysqli_stmt_get_result($rv_stmt);
    while ($rv = mysqli_fetch_assoc($rv_res)) $revisions[] = $rv;
    mysqli_stmt_close($rv_stmt);
}
$field_labels_map = ['name'=>'병원명','owner_name'=>'대표자명','business_number'=>'사업자번호','phone'=>'전화','email'=>'이메일','contact_name'=>'담당자','contact_phone'=>'담당자전화','address'=>'주소','description'=>'소개','website'=>'홈페이지','sns_url'=>'SNS','business_hours'=>'영업시간','treatment_tags'=>'시술'];
?>
<?php if ($unreviewed && !empty($revisions)): ?>
<div class="card mb-3 border-info">
    <div class="card-header bg-info bg-opacity-10 d-flex justify-content-between align-items-center">
        <span><i class="bi bi-pencil-square me-1"></i><strong>신청자가 정보를 수정했습니다</strong> (<?= count($revisions) ?>건)</span>
        <form method="POST" class="d-inline">
            <input type="hidden" name="action" value="mark_reviewed">
            <input type="hidden" name="hospital_id" value="<?= $hospital['id'] ?>">
            <button type="submit" class="btn btn-info btn-sm"><i class="bi bi-check-lg me-1"></i>확인 완료</button>
        </form>
    </div>
    <div class="card-body py-2">
        <table class="table table-sm small mb-0">
            <thead><tr><th>항목</th><th>이전</th><th></th><th>변경</th><th>일시</th></tr></thead>
            <tbody>
            <?php foreach ($revisions as $rv): ?>
            <tr>
                <td class="fw-semibold"><?= $field_labels_map[$rv['field_name']] ?? $rv['field_name'] ?></td>
                <td><span style="background:#ffeef0;padding:1px 4px;border-radius:3px;text-decoration:line-through;"><?= htmlspecialchars(mb_substr($rv['old_value'] ?? '', 0, 30)) ?: '-' ?></span></td>
                <td><i class="bi bi-arrow-right"></i></td>
                <td><span style="background:#e6ffed;padding:1px 4px;border-radius:3px;"><?= htmlspecialchars(mb_substr($rv['new_value'] ?? '', 0, 30)) ?: '-' ?></span></td>
                <td class="text-muted"><?= date('m.d H:i', strtotime($rv['changed_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- 신청 제출 자료: 승인/반려 판단에 필요한 서류와 이미지를 한곳에 표시 -->
<?php if (!empty($hospital['applied_by'])): ?>
<?php
$application_assets = [
    ['label' => '사업자등록증', '상세' => '필수 서류', 'path' => (string)($hospital['cert_business'] ?? ''), 'required' => true, 'icon' => 'bi-file-earmark-text'],
    ['label' => '의료기관개설허가증', '상세' => '필수 서류', 'path' => (string)($hospital['cert_medical'] ?? ''), 'required' => true, 'icon' => 'bi-file-earmark-medical'],
    ['label' => '프로필 이미지', '상세' => '선택 자료', 'path' => (string)($hospital['profile_image_url'] ?? ''), 'required' => false, 'icon' => 'bi-image'],
];
?>
<div class="card mb-3 border-primary">
    <div class="card-header bg-primary bg-opacity-10 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-folder-check me-1"></i><strong>신청 제출 자료</strong></span>
        <span class="small text-body-secondary">서류를 확인한 후 승인 또는 반려해 주세요.</span>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <?php foreach ($application_assets as $asset):
                $asset_path = trim($asset['path']);
                $asset_ext = strtolower(pathinfo((string)(parse_url($asset_path, PHP_URL_PATH) ?? ''), PATHINFO_EXTENSION));
                $is_pdf = $asset_ext === 'pdf';
            ?>
            <div class="col-md-4">
                <div class="application-asset">
                    <div class="p-3 d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <div class="fw-semibold"><i class="bi <?= $asset['icon'] ?> me-1"></i><?= htmlspecialchars($asset['label']) ?></div>
                            <div class="small text-body-secondary"><?= htmlspecialchars($asset['상세']) ?></div>
                        </div>
                        <?php if ($asset_path !== ''): ?>
                        <span class="badge bg-success"><i class="bi bi-check-lg"></i> 제출</span>
                        <?php elseif ($asset['required']): ?>
                        <span class="badge bg-danger"><i class="bi bi-exclamation-lg"></i> 미제출</span>
                        <?php else: ?>
                        <span class="badge bg-secondary">미제출</span>
                        <?php endif; ?>
                    </div>
                    <div class="application-asset-preview">
                        <?php if ($asset_path !== '' && $is_pdf): ?>
                            <div class="text-center p-3">
                                <i class="bi bi-file-earmark-pdf text-danger" style="font-size:3.5rem"></i>
                                <div class="mt-2 fw-semibold">PDF 문서</div>
                            </div>
                        <?php elseif ($asset_path !== ''): ?>
                            <img src="<?= htmlspecialchars($asset_path) ?>" alt="<?= htmlspecialchars($asset['label']) ?>"
                                 onclick='showImageModal(<?= json_encode($asset_path, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                        <?php else: ?>
                            <div class="application-asset-missing p-3">
                                <i class="bi bi-image-alt" style="font-size:2.5rem"></i>
                                <div class="mt-2"><?= $asset['required'] ? '필수 서류가 저장되지 않았습니다.' : '제출된 이미지가 없습니다.' ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($asset_path !== ''): ?>
                    <div class="p-3 border-top d-flex gap-2">
                        <a href="<?= htmlspecialchars($asset_path) ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm flex-fill">
                            <i class="bi bi-box-arrow-up-right me-1"></i>원본 열기
                        </a>
                        <a href="<?= htmlspecialchars($asset_path) ?>" download class="btn btn-outline-secondary btn-sm" title="다운로드"><i class="bi bi-download"></i></a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <hr class="my-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0"><i class="bi bi-card-image me-1"></i>신청 시술 / 시술 이미지</h6>
            <span class="badge bg-body-secondary text-body"><?= count($application_treatments) ?>개 시술</span>
        </div>
        <?php if (!empty($application_treatments)): ?>
            <?php foreach ($application_treatments as $treatment): ?>
            <div class="border rounded-3 p-3 mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong><?= htmlspecialchars($treatment['name']) ?></strong>
                    <span class="badge bg-body-secondary text-body"><?= count($treatment['images']) ?>장</span>
                </div>
                <?php if (!empty($treatment['images'])): ?>
                <div class="row g-2">
                    <?php foreach ($treatment['images'] as $treatment_image): ?>
                    <div class="col-6 col-md-3">
                        <img class="application-treatment-image" src="<?= htmlspecialchars($treatment_image['url']) ?>"
                             alt="<?= htmlspecialchars($treatment['name']) ?>"
                             onclick='showImageModal(<?= json_encode($treatment_image['url'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                        <?php if ($treatment_image['caption'] !== ''): ?><div class="small text-body-secondary mt-1"><?= htmlspecialchars($treatment_image['caption']) ?></div><?php endif; ?>
                        <?php if ($treatment_image['approval_status'] === 'pending'): ?>
                        <span class="badge bg-warning text-dark mt-1">이미지 검수 대기</span>
                        <?php elseif ($treatment_image['approval_status'] === 'rejected'): ?>
                        <span class="badge bg-danger mt-1">이미지 반려</span>
                        <?php else: ?>
                        <span class="badge bg-success mt-1">이미지 승인</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="alert alert-warning py-2 mb-0 small"><i class="bi bi-exclamation-triangle me-1"></i>이 시술에 제출된 이미지가 없습니다.</div>
                <?php endif; ?>
                <div class="mt-2"><a class="btn btn-outline-primary btn-sm" href="admin_hospital_treatments.php?hospital_id=<?= (int)$view_mode ?>"><i class="bi bi-images me-1"></i>시술 이미지 검수</a></div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
        <div class="alert alert-secondary py-2 mb-0 small"><i class="bi bi-info-circle me-1"></i>신청서에 선택된 시술이 없습니다.</div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- 기본 정보 카드 -->
<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-building me-1"></i> 기본 정보 <?php if ($unreviewed): ?><span class="badge bg-info ms-2">수정됨</span><?php endif; ?></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="hospital-detail-label">병원 이름</div>
                        <div class="hospital-detail-value fw-bold"><?php echo htmlspecialchars($hospital['name'] ?? '-'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="hospital-detail-label">대표자명</div>
                        <div class="hospital-detail-value"><?php echo htmlspecialchars($hospital['owner_name'] ?? '-'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="hospital-detail-label">사업자등록번호</div>
                        <div class="hospital-detail-value"><?php echo htmlspecialchars($hospital['business_number'] ?? '-'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="hospital-detail-label">이메일</div>
                        <div class="hospital-detail-value"><?php echo htmlspecialchars($hospital['email'] ?? '-'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="hospital-detail-label">전화번호</div>
                        <div class="hospital-detail-value"><?php echo htmlspecialchars($hospital['phone'] ?? '-'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="hospital-detail-label">담당자</div>
                        <div class="hospital-detail-value"><?php echo htmlspecialchars(($hospital['contact_name'] ?? '') . ' / ' . ($hospital['contact_phone'] ?? '')); ?></div>
                    </div>
                    <div class="col-12">
                        <div class="hospital-detail-label">주소</div>
                        <div class="hospital-detail-value"><?php echo htmlspecialchars($hospital['address'] ?? '-'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="hospital-detail-label">홈페이지</div>
                        <div class="hospital-detail-value"><?php if (!empty($hospital['website'])): ?><a href="<?= htmlspecialchars($hospital['website']) ?>" target="_blank"><?= htmlspecialchars($hospital['website']) ?></a><?php else: ?>-<?php endif; ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="hospital-detail-label">SNS</div>
                        <div class="hospital-detail-value"><?php if (!empty($hospital['sns_url'])): ?><a href="<?= htmlspecialchars($hospital['sns_url']) ?>" target="_blank"><?= htmlspecialchars($hospital['sns_url']) ?></a><?php else: ?>-<?php endif; ?></div>
                    </div>
                    <div class="col-12">
                        <div class="hospital-detail-label">설명</div>
                        <div class="hospital-detail-value"><?php echo nl2br(htmlspecialchars($hospital['description'] ?? '-')); ?></div>
                    </div>
                    <div class="col-12">
                        <div class="hospital-detail-label">소개</div>
                        <div class="hospital-detail-value"><?php echo nl2br(htmlspecialchars($hospital['introduction'] ?? '-')); ?></div>
                    </div>
                    <div class="col-12">
                        <div class="hospital-detail-label">영업시간</div>
                        <div class="hospital-detail-value"><?php echo nl2br(htmlspecialchars($hospital['business_hours'] ?? '-')); ?></div>
                    </div>
                    <?php if (!empty($hospital['applied_by'])): ?>
                    <div class="col-md-6">
                        <div class="hospital-detail-label">신청자</div>
                        <div class="hospital-detail-value"><span class="badge bg-secondary"><?= htmlspecialchars($hospital['applied_by']) ?></span></div>
                    </div>
                    <div class="col-md-6">
                        <div class="hospital-detail-label">신청일</div>
                        <div class="hospital-detail-value"><?= $hospital['applied_at'] ? date('Y-m-d H:i', strtotime($hospital['applied_at'])) : '-' ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-info-circle me-1"></i> 상태 / 통계</div>
            <div class="card-body">
                <div class="hospital-detail-label">상태</div>
                <div class="hospital-detail-value"><?php echo statusBadge($hospital['status'] ?? 'pending'); ?></div>

                <div class="hospital-detail-label">활성</div>
                <div class="hospital-detail-value">
                    <?php if ($hospital['is_active']): ?>
                        <span class="badge bg-success">활성</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">비활성</span>
                    <?php endif; ?>
                </div>

                <div class="hospital-detail-label">광고 등급</div>
                <div class="hospital-detail-value">
                    <?php
                    $dg = $hospital['ad_grade'] ?? 'none';
                    $grade_labels = ['S'=>'S (최우선)','A'=>'A (우선)','B'=>'B (제휴)','C'=>'C (제휴)','D'=>'D (제휴)','E'=>'E (제휴)','none'=>'없음'];
                    $grade_colors = ['S'=>'bg-danger','A'=>'bg-danger','B'=>'bg-primary','C'=>'bg-primary','D'=>'bg-secondary','E'=>'bg-secondary','none'=>'bg-body-secondary text-body'];
                    ?>
                    <span class="badge <?php echo $grade_colors[$dg] ?? 'bg-secondary'; ?>"><?php echo $grade_labels[$dg] ?? $dg; ?></span>
                </div>

                <div class="hospital-detail-label">평점</div>
                <div class="hospital-detail-value">
                    <span class="text-warning"><?php echo str_repeat('&#9733;', (int)($hospital['rating'] ?? 0)); ?></span>
                    <span class="text-body-tertiary"><?php echo str_repeat('&#9733;', 5 - (int)($hospital['rating'] ?? 0)); ?></span>
                    <span class="small text-body-secondary ms-1">(<?php echo number_format($hospital['rating'] ?? 0, 1); ?>)</span>
                </div>

                <div class="hospital-detail-label">리뷰 수</div>
                <div class="hospital-detail-value"><?php echo number_format($hospital['review_count'] ?? 0); ?>건</div>

                <div class="hospital-detail-label">평점 표시</div>
                <div class="hospital-detail-value">
                    <?php if (($hospital['show_rating'] ?? 1) == 1): ?>
                    <span class="badge bg-success">ON</span>
                    <?php else: ?>
                    <span class="badge bg-secondary">OFF</span>
                    <?php endif; ?>
                </div>

                <div class="hospital-detail-label">위치</div>
                <div class="hospital-detail-value small">
                    <?php if (!empty($hospital['latitude']) && !empty($hospital['longitude'])): ?>
                        <?php echo $hospital['latitude']; ?>, <?php echo $hospital['longitude']; ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </div>

                <div class="hospital-detail-label">등록일</div>
                <div class="hospital-detail-value small"><?php echo $hospital['created_at'] ? date('Y-m-d H:i', strtotime($hospital['created_at'])) : '-'; ?></div>

                <div class="hospital-detail-label">수정일</div>
                <div class="hospital-detail-value small"><?php echo $hospital['updated_at'] ? date('Y-m-d H:i', strtotime($hospital['updated_at'])) : '-'; ?></div>
            </div>
        </div>
    </div>
</div>

<!-- 카테고리 / 태그 -->
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-tags me-1"></i> 카테고리 / 태그</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="hospital-detail-label">카테고리</div>
                <div class="hospital-detail-value">
                    <?php
                    $cats_raw = $hospital['categories'] ?? '';
                    $cats = json_decode($cats_raw, true);
                    if (!is_array($cats)) $cats = array_filter(array_map('trim', explode(',', $cats_raw)));
                    if (!empty($cats)):
                        foreach ($cats as $cat): ?>
                            <span class="badge bg-primary category-badge me-1 mb-1"><?php echo htmlspecialchars($cat); ?></span>
                        <?php endforeach;
                    else: ?>
                        <span class="text-body-secondary">-</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="hospital-detail-label">뱃지</div>
                <div class="hospital-detail-value">
                    <?php
                    $badges_raw = $hospital['badges'] ?? '';
                    $badges = json_decode($badges_raw, true);
                    if (!is_array($badges)) $badges = array_filter(array_map('trim', explode(',', $badges_raw)));
                    if (!empty($badges)):
                        foreach ($badges as $badge_item): ?>
                            <span class="badge bg-info text-dark category-badge me-1 mb-1"><?php echo htmlspecialchars($badge_item); ?></span>
                        <?php endforeach;
                    else: ?>
                        <span class="text-body-secondary">-</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="hospital-detail-label">시술 태그</div>
                <div class="hospital-detail-value">
                    <?php
                    $tags_raw = $hospital['treatment_tags'] ?? '';
                    $tags = json_decode($tags_raw, true);
                    if (!is_array($tags)) $tags = array_filter(array_map('trim', explode(',', $tags_raw)));
                    if (!empty($tags)):
                        foreach ($tags as $tag):
                            // treatment_tags 는 객체배열 [{"tagId":..,"name":".."}] 또는 문자열배열 둘 다 가능
                            $tag_label = is_array($tag) ? ($tag['name'] ?? '') : $tag;
                            if ($tag_label === '') continue; ?>
                            <span class="badge bg-body-secondary text-body tag-badge me-1 mb-1"><?php echo htmlspecialchars($tag_label); ?></span>
                        <?php endforeach;
                    else: ?>
                        <span class="text-body-secondary">-</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="hospital-detail-label">인근 지하철</div>
                <div class="hospital-detail-value">
                    <?php
                    $subways_raw = $hospital['subway_names'] ?? '';
                    $subways = json_decode($subways_raw, true);
                    if (!is_array($subways)) $subways = array_filter(array_map('trim', explode(',', $subways_raw)));
                    if (!empty($subways)):
                        foreach ($subways as $sw): ?>
                            <span class="badge bg-success tag-badge me-1 mb-1"><i class="bi bi-train-front-fill me-1"></i><?php echo htmlspecialchars($sw); ?></span>
                        <?php endforeach;
                    else: ?>
                        <span class="text-body-secondary">-</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 프로필/메인 이미지 -->
<?php if (empty($hospital['applied_by']) && (!empty($hospital['profile_image_url']) || !empty($hospital['main_image_url']))): ?>
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-image me-1"></i> 대표 이미지</div>
    <div class="card-body">
        <div class="row g-3">
            <?php if (!empty($hospital['profile_image_url'])): ?>
            <div class="col-md-6">
                <div class="hospital-detail-label">프로필 이미지</div>
                <img src="<?php echo htmlspecialchars($hospital['profile_image_url']); ?>" alt="프로필" class="hospital-img-large">
            </div>
            <?php endif; ?>
            <?php if (!empty($hospital['main_image_url'])): ?>
            <div class="col-md-6">
                <div class="hospital-detail-label">메인 이미지</div>
                <img src="<?php echo htmlspecialchars($hospital['main_image_url']); ?>" alt="메인" class="hospital-img-large">
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 병원 이미지 갤러리 (hospital_images) -->
<?php if (!empty($hospital_images)): ?>
<div class="card mb-3">
    <div class="card-header">
        <i class="bi bi-images me-1"></i> 병원 이미지 (<?php echo count($hospital_images); ?>장)
    </div>
    <div class="card-body">
        <div class="row g-2">
            <?php foreach ($hospital_images as $img): ?>
            <div class="col-6 col-md-4 col-lg-3">
                <div class="card h-100">
                    <img src="<?php echo htmlspecialchars($img['image_url']); ?>"
                         class="card-img-top" style="height:150px;object-fit:cover;cursor:pointer;"
                         alt="<?php echo htmlspecialchars($img['image_type'] ?? ''); ?>"
                         onclick="showImageModal('<?php echo htmlspecialchars($img['image_url']); ?>')">
                    <div class="card-body p-2 text-center">
                        <small class="text-body-secondary">
                            <?php echo htmlspecialchars($img['image_type'] ?? '-'); ?>
                            <?php if (isset($img['sort_order'])): ?>
                                <span class="badge bg-body-secondary text-body">#<?php echo $img['sort_order']; ?></span>
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 하단 관리 버튼 -->
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex gap-2">
                <?php if (($hospital['status'] ?? '') !== 'approved'): ?>
                <form method="POST" class="d-inline" onsubmit="return confirm('이 병원을 승인하시겠습니까?')">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="hospital_id" value="<?php echo $hospital['id']; ?>">
                    <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-check-lg me-1"></i>승인</button>
                </form>
                <?php endif; ?>
                <?php if (($hospital['status'] ?? '') !== 'rejected'): ?>
                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">
                    <i class="bi bi-x-lg me-1"></i>반려
                </button>
                <?php endif; ?>
                <form method="POST" class="d-inline" onsubmit="return confirm('활성 상태를 변경하시겠습니까?')">
                    <input type="hidden" name="action" value="toggle_active">
                    <input type="hidden" name="hospital_id" value="<?php echo $hospital['id']; ?>">
                    <button type="submit" class="btn btn-outline-warning btn-sm">
                        <i class="bi bi-<?php echo $hospital['is_active'] ? 'pause' : 'play'; ?> me-1"></i>
                        <?php echo $hospital['is_active'] ? '비활성화' : '활성화'; ?>
                    </button>
                </form>
            </div>
            <form method="POST" class="d-inline" onsubmit="return confirm('정말 이 병원을 삭제하시겠습니까?\n관련된 이미지도 함께 삭제됩니다.\n이 작업은 되돌릴 수 없습니다.')">
                <input type="hidden" name="action" value="delete_hospital">
                <input type="hidden" name="hospital_id" value="<?php echo $hospital['id']; ?>">
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-trash me-1"></i>삭제
                </button>
            </form>
        </div>
    </div>
</div>

<!-- 반려 모달 -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="hospital_id" value="<?php echo $hospital['id']; ?>">
                <div class="modal-header py-2">
                    <h6 class="modal-title"><i class="bi bi-x-circle text-danger me-1"></i> 병원 반려</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <strong><?php echo htmlspecialchars($hospital['name']); ?></strong>
                        <span class="text-body-secondary small ms-1">#<?php echo $hospital['id']; ?></span>
                    </div>
                    <label class="form-label fw-semibold small">반려 사유 <span class="text-danger">*</span></label>
                    <textarea name="reject_reason" class="form-control" rows="4" required
                              placeholder="반려 사유를 입력해주세요..."><?php echo htmlspecialchars($hospital['reject_reason'] ?? ''); ?></textarea>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-x-lg me-1"></i>반려 처리</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 이미지 확대 모달 -->
<div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title"><i class="bi bi-image"></i> 이미지 보기</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-2" style="max-height:80vh;overflow:auto;">
                <img id="modalImage" src="" class="img-fluid" style="max-width:100%;">
            </div>
        </div>
    </div>
</div>

<?php endif; // end edit_mode vs view_mode ?>

<?php elseif ($view_mode > 0 && !$hospital): ?>
<!-- 병원을 찾을 수 없음 -->
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-1"></i> 해당 병원을 찾을 수 없습니다.
    <a href="admin_hospitals.php" class="alert-link ms-2">목록으로 돌아가기</a>
</div>

<?php else: ?>
<!-- ============================================================ -->
<!-- 목록 모드                                                     -->
<!-- ============================================================ -->

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-building"></i> <?php echo term('business'); ?> 관리</h5>

    <a href="admin_hospital_wizard.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-magic"></i> 마법사로 등록</a>
</div>

<!-- 통계 카드 -->
<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <a href="<?php echo buildHospitalUrl(['status' => 'all']); ?>" class="text-decoration-none">
            <div class="card text-center hospital-stat-card <?php echo $filter_status === 'all' ? 'active-filter' : ''; ?>">
                <div class="card-body py-3">
                    <div class="fs-4 fw-bold text-primary"><?php echo number_format($stats['total']); ?></div>
                    <div class="small text-body-secondary">전체</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?php echo buildHospitalUrl(['status' => 'pending']); ?>" class="text-decoration-none">
            <div class="card text-center hospital-stat-card <?php echo $filter_status === 'pending' ? 'active-filter' : ''; ?>">
                <div class="card-body py-3">
                    <div class="fs-4 fw-bold text-warning"><?php echo number_format($stats['pending']); ?></div>
                    <div class="small text-body-secondary">대기</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?php echo buildHospitalUrl(['status' => 'approved']); ?>" class="text-decoration-none">
            <div class="card text-center hospital-stat-card <?php echo $filter_status === 'approved' ? 'active-filter' : ''; ?>">
                <div class="card-body py-3">
                    <div class="fs-4 fw-bold text-success"><?php echo number_format($stats['approved']); ?></div>
                    <div class="small text-body-secondary">승인</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?php echo buildHospitalUrl(['status' => 'rejected']); ?>" class="text-decoration-none">
            <div class="card text-center hospital-stat-card <?php echo $filter_status === 'rejected' ? 'active-filter' : ''; ?>">
                <div class="card-body py-3">
                    <div class="fs-4 fw-bold text-danger"><?php echo number_format($stats['rejected']); ?></div>
                    <div class="small text-body-secondary">반려</div>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- 필터 바 -->
<div class="card mb-3">
    <div class="card-body py-2">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="fw-semibold small text-body-secondary me-1">상태</span>
            <a href="<?php echo buildHospitalUrl(['status' => 'all']); ?>"
               class="btn btn-sm <?php echo $filter_status === 'all' ? 'btn-primary' : 'btn-outline-secondary'; ?>">전체</a>
            <a href="<?php echo buildHospitalUrl(['status' => 'pending']); ?>"
               class="btn btn-sm <?php echo $filter_status === 'pending' ? 'btn-warning' : 'btn-outline-secondary'; ?>">
                대기
                <?php if ($stats['pending'] > 0): ?>
                <span class="badge bg-white text-warning ms-1"><?php echo $stats['pending']; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo buildHospitalUrl(['status' => 'approved']); ?>"
               class="btn btn-sm <?php echo $filter_status === 'approved' ? 'btn-success' : 'btn-outline-secondary'; ?>">승인</a>
            <a href="<?php echo buildHospitalUrl(['status' => 'rejected']); ?>"
               class="btn btn-sm <?php echo $filter_status === 'rejected' ? 'btn-danger' : 'btn-outline-secondary'; ?>">반려</a>
        </div>
    </div>
</div>

<!-- 툴바: 검색 + 행수 -->
<div class="admin-toolbar">
    <form method="GET" class="d-flex gap-2 search-box flex-grow-1" style="max-width:400px;">
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
        <input type="hidden" name="per_page" value="<?php echo $per_page; ?>">
        <input type="search" name="search" class="form-control form-control-sm" placeholder="병원명, 주소, 전화, 이메일, 카테고리 검색..."
               value="<?php echo htmlspecialchars($search); ?>">
        <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
    </form>
    <div class="d-flex align-items-center gap-2">
        <label class="text-nowrap small text-body-secondary">행수</label>
        <select class="form-select form-select-sm" style="width:80px;" onchange="changePerPage(this)">
            <?php foreach ([10, 20, 50] as $n): ?>
            <option value="<?php echo $n; ?>" <?php echo $per_page == $n ? 'selected' : ''; ?>><?php echo $n; ?></option>
            <?php endforeach; ?>
        </select>
        <span class="text-body-secondary small">총 <?php echo number_format($total); ?>건</span>
    </div>
</div>

<!-- 일괄 좌표 변환 -->
<div id="batchGeocodeCard" class="card mb-3 border-info" style="display:none;">
    <div class="card-body py-2">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <span class="text-info-emphasis small">
                <i class="bi bi-geo-alt me-1"></i>좌표 미등록 병원: <strong id="bgPending">0</strong>개
            </span>
            <button type="button" class="btn btn-sm btn-outline-primary" id="bgStartBtn" onclick="bgStart()">
                <i class="bi bi-pin-map me-1"></i>일괄 좌표 변환 시작
            </button>
            <div id="bgProgress" style="display:none;" class="flex-grow-1">
                <div class="progress" style="height:18px;">
                    <div id="bgBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%">0%</div>
                </div>
                <div class="small text-body-secondary mt-1">
                    성공: <span id="bgOk">0</span> | 실패: <span id="bgFail">0</span> | 남은: <span id="bgLeft">0</span>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function(){
    // 미변환 건수 체크
    fetch('api/geocode_batch.php?action=count')
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.pending > 0) {
            document.getElementById('bgPending').textContent = d.pending;
            document.getElementById('batchGeocodeCard').style.display = 'block';
        }
    }).catch(function(){});
})();
var _bgOk=0, _bgFail=0, _bgTotal=0, _bgDone=0;
function bgStart() {
    document.getElementById('bgStartBtn').disabled = true;
    document.getElementById('bgProgress').style.display = 'block';
    _bgTotal = parseInt(document.getElementById('bgPending').textContent) || 0;
    _bgOk=0; _bgFail=0; _bgDone=0;
    bgNext();
}
function bgNext() {
    fetch('api/geocode_batch.php?action=batch', { method:'POST' })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.done) { bgFinish(); return; }
        _bgDone++;
        if (d.success) _bgOk++; else _bgFail++;
        var pct = Math.round(_bgDone / _bgTotal * 100);
        document.getElementById('bgBar').style.width = pct+'%';
        document.getElementById('bgBar').textContent = pct+'%';
        document.getElementById('bgOk').textContent = _bgOk;
        document.getElementById('bgFail').textContent = _bgFail;
        document.getElementById('bgLeft').textContent = Math.max(0, _bgTotal - _bgDone);
        // Nominatim 1초 간격 준수
        setTimeout(bgNext, 1100);
    })
    .catch(function(){ _bgFail++; _bgDone++; setTimeout(bgNext, 2000); });
}
function bgFinish() {
    document.getElementById('bgBar').className = 'progress-bar bg-success';
    document.getElementById('bgBar').style.width = '100%';
    document.getElementById('bgBar').textContent = '완료!';
    document.getElementById('bgStartBtn').textContent = '완료 (' + _bgOk + '건 성공)';
}
</script>

<!-- 일괄 작업 바 -->
<div id="bulkBar" class="alert alert-primary d-flex align-items-center gap-3 mb-2" style="display:none !important;">
    <span><strong id="bulkCount">0</strong>개 선택</span>
    <button type="button" class="btn btn-danger btn-sm" onclick="bulkDelete()"><i class="bi bi-trash me-1"></i>선택 삭제</button>
    <button type="button" class="btn btn-success btn-sm" onclick="bulkApprove()"><i class="bi bi-check-lg me-1"></i>선택 승인</button>
    <button type="button" class="btn btn-warning btn-sm" onclick="bulkReject()"><i class="bi bi-x-lg me-1"></i>선택 반려</button>
    <button type="button" class="btn btn-outline-secondary btn-sm ms-auto" onclick="uncheckAll()"><i class="bi bi-x me-1"></i>선택 해제</button>
</div>

<!-- 병원 테이블 -->
<div class="table-responsive">
    <table class="table table-hover table-bordered admin-table mb-0" id="hospitalTable">
        <thead class="table-light">
            <tr>
                <th style="width:36px;"><input type="checkbox" class="form-check-input" id="checkAll" title="전체 선택"></th>
                <th style="width:40px;">#</th>
                <th style="width:120px;">이름</th>
                <th style="width:140px;">주소</th>
                <th style="width:100px;">전화</th>
                <th style="min-width:200px;">카테고리</th>
                <th style="width:70px;">상태</th>
                <th style="width:70px;">광고</th>
                <th style="width:60px;">활성</th>
                <th style="width:70px;">평점</th>
                <th style="width:90px;">등록일</th>
                <th style="width:90px;">관리</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($hospitals)): ?>
            <tr><td colspan="12" class="empty-state"><i class="bi bi-building"></i>등록된 병원이 없습니다.</td></tr>
            <?php else: ?>
            <?php foreach ($hospitals as $h): ?>
            <tr>
                <td class="text-center"><input type="checkbox" class="form-check-input row-check" value="<?php echo $h['id']; ?>"></td>
                <td class="text-center"><?php echo $h['id']; ?></td>
                <td>
                    <a href="?view=<?php echo $h['id']; ?>" class="text-decoration-none fw-semibold">
                        <?php echo htmlspecialchars($h['name']); ?>
                    </a>
                    <?php if (!empty($h['has_unreviewed_changes'])): ?><span class="badge bg-info ms-1" title="신청자가 정보를 수정함">수정됨</span><?php endif; ?>
                </td>
                <td class="small">
                    <span class="text-truncate d-inline-block" style="max-width:200px;" title="<?php echo htmlspecialchars($h['address'] ?? ''); ?>">
                        <?php echo htmlspecialchars($h['address'] ?? '-'); ?>
                    </span>
                </td>
                <td class="small"><?php echo htmlspecialchars($h['phone'] ?? '-'); ?></td>
                <td>
                    <?php
                    $cat_raw = $h['categories'] ?? '';
                    $cat_list = json_decode($cat_raw, true);
                    if (!is_array($cat_list)) $cat_list = array_filter(array_map('trim', explode(',', $cat_raw)));
                    if (!empty($cat_list)):
                        foreach ($cat_list as $c): ?>
                            <span class="badge bg-primary category-badge"><?php echo htmlspecialchars($c); ?></span>
                        <?php endforeach;
                        if (false): ?>
                        <?php endif;
                    else: ?>
                        <span class="text-body-tertiary">-</span>
                    <?php endif; ?>
                </td>
                <td><?php echo statusBadge($h['status'] ?? 'pending'); ?></td>
                <td class="text-center">
                    <?php
                    $lg = $h['ad_grade'] ?? 'none';
                    $lg_map = ['S'=>'bg-danger','A'=>'bg-danger','B'=>'bg-primary','C'=>'bg-primary','D'=>'bg-secondary','E'=>'bg-secondary'];
                    if ($lg !== 'none' && isset($lg_map[$lg])): ?>
                        <span class="badge <?php echo $lg_map[$lg]; ?>"><?php echo $lg; ?></span>
                    <?php else: ?>
                        <span class="text-body-tertiary">-</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <?php if ($h['is_active']): ?>
                        <span class="badge bg-success">ON</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">OFF</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (($h['rating'] ?? 0) > 0): ?>
                    <span class="text-warning">&#9733;</span>
                    <span class="small"><?php echo number_format($h['rating'], 1); ?></span>
                    <span class="text-body-tertiary small">(<?php echo $h['review_count'] ?? 0; ?>)</span>
                    <?php else: ?>
                    <span class="text-body-tertiary">-</span>
                    <?php endif; ?>
                </td>
                <td class="small"><?php echo $h['created_at'] ? date('Y.m.d', strtotime($h['created_at'])) : '-'; ?></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <a href="?view=<?php echo $h['id']; ?>" class="btn btn-outline-info" title="상세보기">
                            <i class="bi bi-eye"></i>
                        </a>
                        <a href="?view=<?php echo $h['id']; ?>&edit=1" class="btn btn-outline-primary" title="수정">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <?php if (($h['status'] ?? 'pending') === 'pending'): ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('승인하시겠습니까?')">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="hospital_id" value="<?php echo $h['id']; ?>">
                            <button type="submit" class="btn btn-outline-success" title="승인">
                                <i class="bi bi-check-lg"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- 페이지네이션 -->
<?php if ($total_pages > 1): ?>
<nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center mb-0">
        <?php if ($page > 1): ?>
        <li class="page-item">
            <a class="page-link" href="<?php echo buildHospitalUrl(['page' => $page - 1]); ?>">&laquo;</a>
        </li>
        <?php endif; ?>
        <?php
        $start = max(1, $page - 4);
        $end = min($total_pages, $page + 4);
        if ($start > 1): ?>
        <li class="page-item"><a class="page-link" href="<?php echo buildHospitalUrl(['page' => 1]); ?>">1</a></li>
        <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
        <?php endif; ?>
        <?php for ($i = $start; $i <= $end; $i++): ?>
        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
            <a class="page-link" href="<?php echo buildHospitalUrl(['page' => $i]); ?>"><?php echo $i; ?></a>
        </li>
        <?php endfor; ?>
        <?php if ($end < $total_pages): ?>
        <?php if ($end < $total_pages - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
        <li class="page-item"><a class="page-link" href="<?php echo buildHospitalUrl(['page' => $total_pages]); ?>"><?php echo $total_pages; ?></a></li>
        <?php endif; ?>
        <?php if ($page < $total_pages): ?>
        <li class="page-item">
            <a class="page-link" href="<?php echo buildHospitalUrl(['page' => $page + 1]); ?>">&raquo;</a>
        </li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<?php endif; // end list mode vs view mode ?>

<?php
$admin_extra_js = '
// 이미지 확대 모달
function showImageModal(src) {
    var img = document.getElementById("modalImage");
    if (img) img.src = src;
    var modal = document.getElementById("imageModal");
    if (modal) new bootstrap.Modal(modal).show();
}
';

?>
<script>
// 체크박스 일괄 선택
document.getElementById('checkAll')?.addEventListener('change', function() {
    document.querySelectorAll('.row-check').forEach(function(cb) { cb.checked = this.checked; }.bind(this));
    updateBulkBar();
});
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('row-check')) updateBulkBar();
});

function getCheckedIds() {
    var ids = [];
    document.querySelectorAll('.row-check:checked').forEach(function(cb) { ids.push(cb.value); });
    return ids;
}

function updateBulkBar() {
    var ids = getCheckedIds();
    var bar = document.getElementById('bulkBar');
    if (ids.length > 0) {
        bar.style.cssText = '';
        document.getElementById('bulkCount').textContent = ids.length;
    } else {
        bar.style.cssText = 'display:none !important;';
    }
}

function uncheckAll() {
    document.querySelectorAll('.row-check, #checkAll').forEach(function(cb) { cb.checked = false; });
    updateBulkBar();
}

function bulkSubmit(action, extraData) {
    var ids = getCheckedIds();
    if (ids.length === 0) return;
    var form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = '<input name="action" value="' + action + '"><input name="ids" value="' + ids.join(',') + '"><input name="admin_csrf" value="<?php echo htmlspecialchars($admin_hospitals_csrf, ENT_QUOTES); ?>">';
    if (extraData) {
        for (var k in extraData) form.innerHTML += '<input name="' + k + '" value="' + extraData[k] + '">';
    }
    document.body.appendChild(form);
    form.submit();
}

// Server-side CSRF tekshiruviga barcha oddiy POST formalari uchun token qo‘shish.
document.querySelectorAll('form[method="POST"], form[method="post"]').forEach(function(form) {
    if (!form.querySelector('input[name="admin_csrf"]')) {
        var input = document.createElement('input');
        input.type = 'hidden'; input.name = 'admin_csrf';
        input.value = '<?php echo htmlspecialchars($admin_hospitals_csrf, ENT_QUOTES); ?>';
        form.appendChild(input);
    }
});

function bulkDelete() {
    var ids = getCheckedIds();
    if (confirm(ids.length + '개 병원을 삭제하시겠습니까?\n이 작업은 되돌릴 수 없습니다.')) {
        bulkSubmit('bulk_delete');
    }
}

function bulkApprove() {
    var ids = getCheckedIds();
    if (confirm(ids.length + '개 병원을 승인하시겠습니까?')) {
        bulkSubmit('bulk_approve');
    }
}

function bulkReject() {
    var ids = getCheckedIds();
    var reason = prompt(ids.length + '개 병원 반려 사유를 입력하세요:', '일괄 반려');
    if (reason !== null) {
        bulkSubmit('bulk_reject', { reject_reason: reason });
    }
}
</script>
<?php
include __DIR__ . '/includes/admin_footer.php';
// mysqli_close removed - PHP closes connections at script end
// Explicit close caused issues with debug_panel.php in admin_footer
?>
