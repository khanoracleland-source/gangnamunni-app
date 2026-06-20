<?php
/**
 * 회원 양방향 동기화 시스템
 * Gnuboard g5_member ↔ HMS users/patients
 *
 * 동일 DB(hms_saas)에서 직접 동기화
 * @created 2026-02-11
 */

/**
 * 동기화 상태 조회
 */
function get_sync_status($mb_no, $connect) {
    $stmt = mysqli_prepare($connect, "SELECT * FROM member_sync WHERE mb_no = ?");
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, 'i', $mb_no);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $row;
}

/**
 * 전체 동기화 통계
 */
function get_sync_stats($connect) {
    $stats = [
        'total_members' => 0,
        'synced' => 0,
        'pending' => 0,
        'error' => 0,
        'unlinked' => 0,
        'hms_patients' => 0,
        'tenants' => 0,
    ];
    $r = mysqli_query($connect, "SELECT COUNT(*) as c FROM g5_member WHERE mb_leave_date = ''");
    $stats['total_members'] = (int)mysqli_fetch_assoc($r)['c'];

    $r = mysqli_query($connect, "SELECT sync_status, COUNT(*) as c FROM member_sync GROUP BY sync_status");
    while ($row = mysqli_fetch_assoc($r)) {
        $stats[$row['sync_status']] = (int)$row['c'];
    }

    $r = mysqli_query($connect, "SELECT COUNT(*) as c FROM patients");
    $stats['hms_patients'] = (int)mysqli_fetch_assoc($r)['c'];

    $r = mysqli_query($connect, "SELECT COUNT(*) as c FROM tenants");
    $stats['tenants'] = (int)mysqli_fetch_assoc($r)['c'];

    return $stats;
}

/**
 * Gnuboard 회원 → HMS 사용자/환자 동기화
 * @return array ['success' => bool, 'message' => string, 'hms_user_id' => int]
 */
function sync_member_to_hms($mb_no, $connect, $tenant_id = null) {
    // 1. Gnuboard 회원 조회
    $member = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM g5_member WHERE mb_no = " . intval($mb_no)));
    if (!$member) {
        return ['success' => false, 'message' => '회원을 찾을 수 없습니다.'];
    }

    // 2. 기존 매핑 확인
    $existing = get_sync_status($mb_no, $connect);

    // 3. 이메일 유효성 (HMS는 이메일 필수+유니크)
    $email = $member['mb_email'];
    if (empty($email)) {
        $email = $member['mb_id'] . '@seoulbeauty.app'; // 자동 생성
    }

    // 4. HMS users 테이블에 이미 같은 이메일이 있는지 확인
    $hms_user_id = null;
    if ($existing && $existing['hms_user_id']) {
        $hms_user_id = $existing['hms_user_id'];
    } else {
        // 이메일로 기존 사용자 검색
        $stmt = mysqli_prepare($connect, "SELECT id FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $r = mysqli_stmt_get_result($stmt);
        $existing_user = mysqli_fetch_assoc($r);
        mysqli_stmt_close($stmt);
        if ($existing_user) {
            $hms_user_id = $existing_user['id'];
        }
    }

    // 5. 성별 변환 (Gnuboard: M/F → HMS: 1/2)
    $gender = 0;
    if ($member['mb_sex'] === 'M') $gender = 1;
    elseif ($member['mb_sex'] === 'F') $gender = 2;

    // 6. 생년월일
    $dob = null;
    if (!empty($member['mb_birth']) && $member['mb_birth'] !== '0000-00-00') {
        $dob = $member['mb_birth'];
        if (strlen($dob) === 8) {
            $dob = substr($dob, 0, 4) . '-' . substr($dob, 4, 2) . '-' . substr($dob, 6, 2);
        }
    }

    // 7. 전화번호
    $phone = $member['mb_hp'] ?: $member['mb_tel'] ?: '';

    // 8. 비밀번호 (Gnuboard bcrypt 해시를 그대로 복사 → 동일 비밀번호로 HMS 로그인 가능)
    $hms_password = $member['mb_password'];

    try {
        if ($hms_user_id) {
            // 기존 사용자 업데이트
            $stmt = mysqli_prepare($connect,
                "UPDATE users SET first_name=?, phone=?, gender=?, dob=?, username=?, status=1, updated_at=NOW() WHERE id=?");
            $name = $member['mb_name'] ?: $member['mb_nick'];
            mysqli_stmt_bind_param($stmt, 'ssissi', $name, $phone, $gender, $dob, $member['mb_id'], $hms_user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } else {
            // 새 사용자 생성
            $name = $member['mb_name'] ?: $member['mb_nick'];
            $stmt = mysqli_prepare($connect,
                "INSERT INTO users (first_name, last_name, email, password, phone, gender, dob, username, status, owner_type, language, hospital_name, tenant_id, created_at, updated_at)
                 VALUES (?, '', ?, ?, ?, ?, ?, ?, 1, 'App\\\\Models\\\\Patient', 'ko', '', ?, NOW(), NOW())");
            mysqli_stmt_bind_param($stmt, 'ssssisss', $name, $email, $hms_password, $phone, $gender, $dob, $member['mb_id'], $tenant_id);
            mysqli_stmt_execute($stmt);
            $hms_user_id = mysqli_insert_id($connect);
            mysqli_stmt_close($stmt);

            if (!$hms_user_id) {
                error_log('HMS 사용자 생성 실패: ' . mysqli_error($connect));
                return ['success' => false, 'message' => '사용자 생성에 실패했습니다.'];
            }
        }

        // 9. patients 테이블에 레코드 생성/확인
        $hms_patient_id = null;
        $pr = mysqli_query($connect, "SELECT id FROM patients WHERE user_id = {$hms_user_id}");
        $existing_patient = mysqli_fetch_assoc($pr);
        if ($existing_patient) {
            $hms_patient_id = $existing_patient['id'];
        } else {
            // 환자 레코드 생성
            $unique_id = 'SB-' . str_pad($mb_no, 6, '0', STR_PAD_LEFT);
            $stmt = mysqli_prepare($connect,
                "INSERT INTO patients (user_id, tenant_id, patient_unique_id, created_at, updated_at)
                 VALUES (?, ?, ?, NOW(), NOW())");
            mysqli_stmt_bind_param($stmt, 'iss', $hms_user_id, $tenant_id, $unique_id);
            mysqli_stmt_execute($stmt);
            $hms_patient_id = mysqli_insert_id($connect);
            mysqli_stmt_close($stmt);
        }

        // 10. 매핑 테이블 업데이트
        if ($existing) {
            $stmt = mysqli_prepare($connect,
                "UPDATE member_sync SET hms_user_id=?, hms_patient_id=?, sync_status='synced', last_synced_at=NOW(), error_message=NULL WHERE mb_no=?");
            mysqli_stmt_bind_param($stmt, 'iii', $hms_user_id, $hms_patient_id, $mb_no);
        } else {
            $stmt = mysqli_prepare($connect,
                "INSERT INTO member_sync (mb_no, mb_id, hms_user_id, hms_patient_id, sync_status, last_synced_at)
                 VALUES (?, ?, ?, ?, 'synced', NOW())");
            mysqli_stmt_bind_param($stmt, 'isii', $mb_no, $member['mb_id'], $hms_user_id, $hms_patient_id);
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return [
            'success' => true,
            'message' => "동기화 완료: {$member['mb_name']} → HMS User #{$hms_user_id}",
            'hms_user_id' => $hms_user_id,
            'hms_patient_id' => $hms_patient_id,
        ];
    } catch (Exception $e) {
        // 에러 기록
        $err_msg = $e->getMessage();
        $stmt = mysqli_prepare($connect,
            "INSERT INTO member_sync (mb_no, mb_id, sync_status, error_message) VALUES (?, ?, 'error', ?)
             ON DUPLICATE KEY UPDATE sync_status='error', error_message=?, updated_at=NOW()");
        mysqli_stmt_bind_param($stmt, 'isss', $mb_no, $member['mb_id'], $err_msg, $err_msg);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return ['success' => false, 'message' => '동기화 실패: ' . $err_msg];
    }
}

/**
 * HMS 환자 → Gnuboard 회원 동기화
 */
function sync_patient_to_gnuboard($hms_user_id, $connect) {
    // HMS 사용자 조회
    $user = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM users WHERE id = " . intval($hms_user_id)));
    if (!$user) {
        return ['success' => false, 'message' => 'HMS 사용자를 찾을 수 없습니다.'];
    }

    // 기존 매핑 확인
    $existing = mysqli_fetch_assoc(mysqli_query($connect,
        "SELECT * FROM member_sync WHERE hms_user_id = " . intval($hms_user_id)));

    $mb_no = $existing ? $existing['mb_no'] : null;

    // 성별 변환 (HMS: 1/2 → Gnuboard: M/F)
    $sex = '';
    if ($user['gender'] == 1) $sex = 'M';
    elseif ($user['gender'] == 2) $sex = 'F';

    $name = $user['first_name'] . ($user['last_name'] ? ' ' . $user['last_name'] : '');
    $name = trim($name);
    if (!$name) $name = 'HMS User ' . $hms_user_id;

    // NULL 방지 (g5_member 컬럼은 NOT NULL)
    $email = $user['email'] ?? '';
    $phone = $user['phone'] ?? '';

    if ($mb_no) {
        // 기존 회원 업데이트
        $stmt = mysqli_prepare($connect,
            "UPDATE g5_member SET mb_name=?, mb_nick=?, mb_email=?, mb_hp=?, mb_sex=? WHERE mb_no=?");
        mysqli_stmt_bind_param($stmt, 'sssssi', $name, $name, $email, $phone, $sex, $mb_no);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } else {
        // 새 회원 생성
        $mb_id = $user['username'] ?: 'hms_' . $hms_user_id;

        // 중복 체크
        $check = mysqli_fetch_assoc(mysqli_query($connect,
            "SELECT mb_no FROM g5_member WHERE mb_id = '" . mysqli_real_escape_string($connect, $mb_id) . "'"));
        if ($check) {
            // 이미 존재하면 매핑만 생성
            $mb_no = $check['mb_no'];
        } else {
            $hms_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($connect,
                "INSERT INTO g5_member (mb_id, mb_password, mb_name, mb_nick, mb_email, mb_hp, mb_sex, mb_level, mb_datetime, mb_ip)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), '')");
            $level = 2;
            mysqli_stmt_bind_param($stmt, 'sssssssi', $mb_id, $hms_password, $name, $name, $email, $phone, $sex, $level);
            mysqli_stmt_execute($stmt);
            $mb_no = mysqli_insert_id($connect);
            mysqli_stmt_close($stmt);
        }
    }

    if ($mb_no) {
        // 매핑 업데이트
        $patient = mysqli_fetch_assoc(mysqli_query($connect,
            "SELECT id FROM patients WHERE user_id = " . intval($hms_user_id)));
        $patient_id = $patient ? $patient['id'] : null;
        $mb_id_val = $user['username'] ?: 'hms_' . $hms_user_id;

        if ($existing) {
            $stmt = mysqli_prepare($connect,
                "UPDATE member_sync SET sync_status='synced', last_synced_at=NOW(), error_message=NULL WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'i', $existing['id']);
        } else {
            $stmt = mysqli_prepare($connect,
                "INSERT INTO member_sync (mb_no, mb_id, hms_user_id, hms_patient_id, sync_status, last_synced_at)
                 VALUES (?, ?, ?, ?, 'synced', NOW())
                 ON DUPLICATE KEY UPDATE hms_user_id=?, sync_status='synced', last_synced_at=NOW()");
            mysqli_stmt_bind_param($stmt, 'isiii', $mb_no, $mb_id_val, $hms_user_id, $patient_id, $hms_user_id);
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return ['success' => true, 'message' => "동기화 완료: HMS User #{$hms_user_id} → {$name}"];
    }

    return ['success' => false, 'message' => 'Gnuboard 회원 생성 실패'];
}

/**
 * 전체 Gnuboard → HMS 일괄 동기화
 */
function sync_all_members_to_hms($connect, $tenant_id = null, $limit = 100) {
    $results = ['success' => 0, 'fail' => 0, 'skip' => 0, 'errors' => []];

    // 아직 동기화되지 않은 활성 회원 조회
    $sql = "SELECT m.mb_no FROM g5_member m
            LEFT JOIN member_sync s ON m.mb_no = s.mb_no
            WHERE m.mb_leave_date = '' AND (s.id IS NULL OR s.sync_status != 'synced')
            ORDER BY m.mb_no ASC LIMIT {$limit}";
    $r = mysqli_query($connect, $sql);

    while ($row = mysqli_fetch_assoc($r)) {
        $result = sync_member_to_hms($row['mb_no'], $connect, $tenant_id);
        if ($result['success']) {
            $results['success']++;
        } else {
            $results['fail']++;
            $results['errors'][] = "mb_no={$row['mb_no']}: {$result['message']}";
        }
    }

    return $results;
}

/**
 * 전체 HMS → Gnuboard 일괄 동기화
 */
function sync_all_patients_to_gnuboard($connect, $limit = 100) {
    $results = ['success' => 0, 'fail' => 0, 'errors' => []];

    // 매핑되지 않은 HMS 환자 사용자 조회
    $sql = "SELECT u.id FROM users u
            LEFT JOIN member_sync s ON u.id = s.hms_user_id
            WHERE u.owner_type = 'App\\\\Models\\\\Patient' AND (s.id IS NULL OR s.sync_status != 'synced')
            ORDER BY u.id ASC LIMIT {$limit}";
    $r = mysqli_query($connect, $sql);

    while ($row = mysqli_fetch_assoc($r)) {
        $result = sync_patient_to_gnuboard($row['id'], $connect);
        if ($result['success']) {
            $results['success']++;
        } else {
            $results['fail']++;
            $results['errors'][] = "user_id={$row['id']}: {$result['message']}";
        }
    }

    return $results;
}

/**
 * 매핑 해제
 */
function unlink_member_sync($mb_no, $connect) {
    $stmt = mysqli_prepare($connect, "UPDATE member_sync SET sync_status='unlinked', hms_user_id=NULL, hms_patient_id=NULL WHERE mb_no=?");
    mysqli_stmt_bind_param($stmt, 'i', $mb_no);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}
