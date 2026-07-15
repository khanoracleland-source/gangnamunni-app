<?php
/**
 * 병원 승인 시 자동 계정 생성 공용 헬퍼.
 * - g5_member(그누보드 회원) + gangnamunni_admins(hospital_admin) + sso_tokens 생성
 * - 승인 이메일 + 병원 관리자 알림
 * 단일 승인(approve)·일괄 승인(bulk_approve) 에서 공통 사용.
 * 이미 존재하는 계정은 재사용(중복 생성 안 함).
 *
 * @param mysqli $connect
 * @param array  $hospital_info  gangnamunni_hospitals 행 (id, email, name, contact_name ... 포함)
 */
if (!function_exists('provision_hospital_accounts')) {
function provision_hospital_accounts($connect, $hospital_info) {
    if (!$hospital_info || empty($hospital_info['email'])) return;

    $hospital_id     = (int)($hospital_info['id'] ?? 0);
    $h_email         = $hospital_info['email'];
    $h_name          = $hospital_info['name'] ?? '';
    $h_contact       = $hospital_info['contact_name'] ?? $h_name;
    $h_phone         = $hospital_info['phone'] ?? '';
    $h_contact_phone = $hospital_info['contact_phone'] ?? $h_phone;
    $h_address       = $hospital_info['address'] ?? '';
    $website         = $hospital_info['website'] ?? '';
    $temp_password   = substr(str_shuffle('abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8);
    $hashed_pw       = password_hash($temp_password, PASSWORD_DEFAULT);
    $client_ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // mb_id 생성: 이메일 앞부분(영숫자 16자), 중복 시 숫자 추가
    $mb_id_base = substr(preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $h_email)[0]), 0, 16);
    if (empty($mb_id_base)) $mb_id_base = 'hosp';
    $mb_id = $mb_id_base; $suffix = 1;
    while (true) {
        $chk = mysqli_prepare($connect, "SELECT mb_id FROM g5_member WHERE mb_id=? LIMIT 1");
        mysqli_stmt_bind_param($chk, 's', $mb_id);
        mysqli_stmt_execute($chk);
        $ex = mysqli_stmt_get_result($chk)->fetch_assoc();
        mysqli_stmt_close($chk);
        if (!$ex) break;
        $mb_id = $mb_id_base . $suffix; $suffix++;
    }

    // ① g5_member — 이메일 매칭 있으면 병원관계자로 전환, 없으면 생성
    $chk_g5 = mysqli_prepare($connect, "SELECT mb_id FROM g5_member WHERE mb_email=? LIMIT 1");
    mysqli_stmt_bind_param($chk_g5, 's', $h_email);
    mysqli_stmt_execute($chk_g5);
    $g5_row = mysqli_stmt_get_result($chk_g5)->fetch_assoc();
    mysqli_stmt_close($chk_g5);
    if ($g5_row) {
        $mb_id = $g5_row['mb_id'];
        $upd = mysqli_prepare($connect, "UPDATE g5_member SET mb_1='hospital' WHERE mb_id=?");
        mysqli_stmt_bind_param($upd, 's', $mb_id);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);
    } else {
        $g5_pw   = password_hash($temp_password, PASSWORD_DEFAULT);
        $mb_nick = $h_contact ?: $h_name;
        $mb_1    = 'hospital';
        $mb_level = 2;
        $now = date('Y-m-d H:i:s');
        $stmt_g5 = mysqli_prepare($connect,
            "INSERT INTO g5_member (mb_id, mb_password, mb_name, mb_nick, mb_email, mb_homepage, mb_level, mb_hp, mb_tel, mb_addr1, mb_1, mb_datetime, mb_ip, mb_mailling, mb_open) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)");
        mysqli_stmt_bind_param($stmt_g5, 'ssssssissssss',
            $mb_id, $g5_pw, $h_contact, $mb_nick, $h_email,
            $website, $mb_level, $h_contact_phone, $h_phone,
            $h_address, $mb_1, $now, $client_ip);
        if (!mysqli_stmt_execute($stmt_g5)) {
            error_log('[provision] g5_member INSERT 실패: ' . mysqli_error($connect));
        }
        mysqli_stmt_close($stmt_g5);
    }

    // ② gangnamunni_admins — username(=email) 없으면 hospital_admin 생성
    $chk_a = mysqli_prepare($connect, "SELECT id FROM gangnamunni_admins WHERE username=? LIMIT 1");
    mysqli_stmt_bind_param($chk_a, 's', $h_email);
    mysqli_stmt_execute($chk_a);
    $ex_a = mysqli_stmt_get_result($chk_a)->fetch_assoc();
    mysqli_stmt_close($chk_a);
    if (!$ex_a) {
        $stmt_adm = mysqli_prepare($connect,
            "INSERT INTO gangnamunni_admins (username, password, name, email, phone, role, hospital_id, hospital_name, is_active, created_at) VALUES (?, ?, ?, ?, ?, 'hospital_admin', ?, ?, 1, NOW())");
        mysqli_stmt_bind_param($stmt_adm, 'sssssis', $h_email, $hashed_pw, $h_contact, $h_email, $h_contact_phone, $hospital_id, $h_name);
        if (!mysqli_stmt_execute($stmt_adm)) {
            error_log('[provision] gangnamunni_admins INSERT 실패: ' . mysqli_error($connect));
        }
        mysqli_stmt_close($stmt_adm);
    }

    // ③ sso_tokens — SSO 토큰 생성 (로그인 준비)
    $sso_token   = bin2hex(random_bytes(32));
    $sso_expires = date('Y-m-d H:i:s', time() + 86400);
    $stmt_sso = mysqli_prepare($connect,
        "INSERT INTO sso_tokens (token, user_type, g5_mb_id, ip_address, expires_at, created_at) VALUES (?, 'admin', ?, ?, ?, NOW())");
    mysqli_stmt_bind_param($stmt_sso, 'ssss', $sso_token, $mb_id, $client_ip, $sso_expires);
    mysqli_stmt_execute($stmt_sso);
    mysqli_stmt_close($stmt_sso);

    // ④ 승인 이메일 + 병원 관리자 알림
    if (file_exists(__DIR__ . '/mail_helper.php')) {
        require_once __DIR__ . '/mail_helper.php';
        $base_url = defined('SITE_FULL_URL') ? SITE_FULL_URL : '';
        $approve_body = "<h2>병원 등록이 승인되었습니다!</h2>
            <p>안녕하세요, <strong>" . htmlspecialchars($h_contact) . "</strong>님.</p>
            <p><strong>" . htmlspecialchars($h_name) . "</strong>의 입점 신청이 승인되었습니다.</p>
            <hr>
            <h3>관리자 대시보드 로그인</h3>
            <p><strong>URL:</strong> <a href='" . $base_url . "dashboard.php'>" . $base_url . "dashboard.php</a></p>
            <p><strong>아이디:</strong> " . htmlspecialchars($h_email) . "</p>
            <p><strong>임시 비밀번호:</strong> " . htmlspecialchars($temp_password) . "</p>
            <hr>
            <h3>커뮤니티 (그누보드) 로그인</h3>
            <p><strong>아이디:</strong> " . htmlspecialchars($mb_id) . "</p>
            <p><strong>비밀번호:</strong> " . htmlspecialchars($temp_password) . " (동일)</p>
            <hr>
            <p style='color:#e74c3c;'><strong>보안을 위해 로그인 후 반드시 비밀번호를 변경해 주세요.</strong></p>
            <p>감사합니다.<br>서울미녀 팀</p>";
        @mail_send(0, $h_email, '[서울미녀] 병원 등록 승인 완료 - 계정 안내', $approve_body);

        if (function_exists('notifyMember')) {
            notifyMember($connect, $mb_id, 'hospital_approve',
                '병원 등록이 승인되었습니다',
                htmlspecialchars($h_name) . '의 입점이 승인되었습니다. 관리자 계정으로 로그인해주세요.',
                'dashboard.php'
            );
        }
    }
}
}
