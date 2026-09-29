<?php
/**
 * 시술 승인 워크플로우 헬퍼
 * - 승인/거절 로그 기록
 * - 병원 담당자 알림 발송
 *
 * 테이블: gangnamunni_treatment_approval_log
 * 의존: gangnamunni_hospital_treatments, gangnamunni_treatment_master,
 *        gangnamunni_hospitals, gangnamunni_user_notifications
 *
 * @file    includes/treatment_approval_helper.php
 * @created 2026-03-25
 */

// ===== 테이블 자동 생성 (최초 1회) =====

/**
 * 승인 로그 테이블 및 컬럼 자동 생성
 * 이미 존재하면 건너뜀
 *
 * @param mysqli $connect DB 연결
 * @return bool 성공 여부
 */
function ensure_treatment_approval_schema($connect) {
    // 1) gangnamunni_treatment_approval_log 테이블 생성
    $sql_create = "
        CREATE TABLE IF NOT EXISTS gangnamunni_treatment_approval_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            hospital_treatment_id INT NOT NULL,
            hospital_id INT NOT NULL,
            treatment_id INT NOT NULL,
            action ENUM('submitted','approved','rejected','resubmitted','cancelled') NOT NULL,
            old_status VARCHAR(20) DEFAULT NULL,
            new_status VARCHAR(20) NOT NULL,
            reason TEXT DEFAULT NULL,
            admin_id INT DEFAULT NULL,
            admin_name VARCHAR(100) DEFAULT NULL,
            hospital_user VARCHAR(100) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ht_id (hospital_treatment_id),
            INDEX idx_hospital (hospital_id),
            INDEX idx_action (action),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    if (!mysqli_query($connect, $sql_create)) {
        error_log('[treatment_approval] 테이블 생성 실패: ' . mysqli_error($connect));
        return false;
    }

    // 2) gangnamunni_hospital_treatments에 승인/거절 타임스탬프 컬럼 추가
    $columns_to_add = [
        'approved_at'  => "DATETIME DEFAULT NULL",
        'approved_by'  => "VARCHAR(100) DEFAULT NULL",
        'rejected_at'  => "DATETIME DEFAULT NULL",
        'rejected_by'  => "VARCHAR(100) DEFAULT NULL",
    ];

    foreach ($columns_to_add as $col => $def) {
        // 컬럼 존재 여부 체크
        $check = mysqli_query($connect,
            "SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'gangnamunni_hospital_treatments'
               AND COLUMN_NAME = '{$col}' LIMIT 1");
        if ($check && mysqli_num_rows($check) === 0) {
            $alter = "ALTER TABLE gangnamunni_hospital_treatments ADD COLUMN `{$col}` {$def}";
            if (!mysqli_query($connect, $alter)) {
                error_log("[treatment_approval] 컬럼 추가 실패 ({$col}): " . mysqli_error($connect));
            }
        }
    }

    return true;
}

// ===== 승인 로그 기록 =====

/**
 * 시술 승인/거절/제출 등 이력 기록
 *
 * @param mysqli $connect  DB 연결
 * @param array  $data     로그 데이터
 *   - hospital_treatment_id (int, 필수)
 *   - hospital_id           (int, 필수)
 *   - treatment_id          (int, 필수)
 *   - action                (string, 필수: submitted|approved|rejected|resubmitted|cancelled)
 *   - old_status            (string|null)
 *   - new_status            (string, 필수)
 *   - reason                (string|null)
 *   - admin_id              (int|null)
 *   - admin_name            (string|null)
 *   - hospital_user         (string|null)
 * @return int|false  삽입된 로그 ID 또는 실패 시 false
 */
function log_treatment_approval($connect, $data) {
    // 필수 필드 검증
    $required = ['hospital_treatment_id', 'hospital_id', 'treatment_id', 'action', 'new_status'];
    foreach ($required as $key) {
        if (!isset($data[$key]) || $data[$key] === '') {
            error_log("[treatment_approval] 필수 필드 누락: {$key}");
            return false;
        }
    }

    // 유효한 action 값 검증
    $valid_actions = ['submitted', 'approved', 'rejected', 'resubmitted', 'cancelled'];
    if (!in_array($data['action'], $valid_actions, true)) {
        error_log("[treatment_approval] 잘못된 action 값: {$data['action']}");
        return false;
    }

    $stmt = mysqli_prepare($connect,
        "INSERT INTO gangnamunni_treatment_approval_log
         (hospital_treatment_id, hospital_id, treatment_id, action, old_status, new_status, reason, admin_id, admin_name, hospital_user, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

    if (!$stmt) {
        error_log('[treatment_approval] 로그 INSERT prepare 실패: ' . mysqli_error($connect));
        return false;
    }

    $ht_id         = intval($data['hospital_treatment_id']);
    $hospital_id   = intval($data['hospital_id']);
    $treatment_id  = intval($data['treatment_id']);
    $action        = $data['action'];
    $old_status    = $data['old_status'] ?? null;
    $new_status    = $data['new_status'];
    $reason        = $data['reason'] ?? null;
    $admin_id      = isset($data['admin_id']) ? intval($data['admin_id']) : null;
    $admin_name    = $data['admin_name'] ?? null;
    $hospital_user = $data['hospital_user'] ?? null;

    mysqli_stmt_bind_param($stmt, 'iiissssiss',
        $ht_id, $hospital_id, $treatment_id,
        $action, $old_status, $new_status,
        $reason, $admin_id, $admin_name, $hospital_user
    );

    $result = mysqli_stmt_execute($stmt);
    $insert_id = $result ? mysqli_insert_id($connect) : false;
    mysqli_stmt_close($stmt);

    if (!$result) {
        error_log('[treatment_approval] 로그 INSERT 실행 실패: ' . mysqli_error($connect));
    }

    return $insert_id;
}

// ===== 승인/거절 알림 발송 =====

/**
 * 시술 승인/거절 시 병원 담당자에게 알림 발송
 *
 * gangnamunni_user_notifications 테이블에 INSERT
 * 병원 소유자: gangnamunni_hospitals.owner_member_id 또는
 *              gangnamunni_admins.hospital_id 매칭 관리자
 *
 * @param mysqli $connect                DB 연결
 * @param int    $hospital_treatment_id  병원시술 ID
 * @param string $action                 'approved' 또는 'rejected'
 * @param string $admin_name             처리한 관리자 이름
 * @param string|null $reason            거절 사유 (거절 시)
 * @return bool 성공 여부
 */
function notify_treatment_approval($connect, $hospital_treatment_id, $action, $admin_name = '', $reason = null) {
    // 유효한 알림 action만 처리
    if (!in_array($action, ['approved', 'rejected'], true)) {
        return false;
    }

    // 1) hospital_treatment_id로 병원ID, 시술ID 조회
    $stmt = mysqli_prepare($connect,
        "SELECT ht.hospital_id, ht.treatment_id, tm.name AS treatment_name, h.name AS hospital_name
         FROM gangnamunni_hospital_treatments ht
         LEFT JOIN gangnamunni_treatment_master tm ON ht.treatment_id = tm.id
         LEFT JOIN gangnamunni_hospitals h ON ht.hospital_id = h.id
         WHERE ht.id = ?");
    if (!$stmt) {
        error_log('[treatment_approval] 알림 조회 prepare 실패: ' . mysqli_error($connect));
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'i', $hospital_treatment_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$row) {
        error_log("[treatment_approval] hospital_treatment_id={$hospital_treatment_id} 조회 실패");
        return false;
    }

    $hospital_id    = intval($row['hospital_id']);
    $treatment_name = $row['treatment_name'] ?: '(시술명 없음)';
    $hospital_name  = $row['hospital_name'] ?: '(병원명 없음)';

    // 2) 알림 대상 member_id 조회
    //    우선순위: gangnamunni_hospitals.owner_member_id → gangnamunni_admins (hospital_id 매칭)
    $target_member_id = null;

    // 방법 A: hospitals 테이블의 owner_member_id 컬럼 (존재하는 경우)
    $col_check = mysqli_query($connect,
        "SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'gangnamunni_hospitals'
           AND COLUMN_NAME = 'owner_member_id' LIMIT 1");
    if ($col_check && mysqli_num_rows($col_check) > 0) {
        $owner_stmt = mysqli_prepare($connect,
            "SELECT owner_member_id FROM gangnamunni_hospitals WHERE id = ? AND owner_member_id IS NOT NULL");
        if ($owner_stmt) {
            mysqli_stmt_bind_param($owner_stmt, 'i', $hospital_id);
            mysqli_stmt_execute($owner_stmt);
            $owner_r = mysqli_stmt_get_result($owner_stmt);
            $owner_row = mysqli_fetch_assoc($owner_r);
            mysqli_stmt_close($owner_stmt);
            if ($owner_row && !empty($owner_row['owner_member_id'])) {
                $target_member_id = $owner_row['owner_member_id'];
            }
        }
    }

    // 방법 B: gangnamunni_admins 테이블에서 hospital_id 매칭 관리자 조회 (폴백)
    if (!$target_member_id) {
        $admin_col_check = mysqli_query($connect,
            "SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'gangnamunni_admins'
               AND COLUMN_NAME = 'hospital_id' LIMIT 1");
        if ($admin_col_check && mysqli_num_rows($admin_col_check) > 0) {
            $adm_stmt = mysqli_prepare($connect,
                "SELECT id FROM gangnamunni_admins
                 WHERE hospital_id = ? AND is_active = 1 AND role IN ('hospital_super_admin','hospital_admin')
                 ORDER BY id ASC LIMIT 1");
            if ($adm_stmt) {
                mysqli_stmt_bind_param($adm_stmt, 'i', $hospital_id);
                mysqli_stmt_execute($adm_stmt);
                $adm_r = mysqli_stmt_get_result($adm_stmt);
                $adm_row = mysqli_fetch_assoc($adm_r);
                mysqli_stmt_close($adm_stmt);
                if ($adm_row) {
                    $target_member_id = $adm_row['id'];
                }
            }
        }
    }

    // 대상 없으면 로그만 남기고 종료 (에러가 아닌 정상 케이스 — 아직 병원 담당자 미배정)
    if (!$target_member_id) {
        error_log("[treatment_approval] 병원 ID={$hospital_id} 알림 대상 없음 (owner_member_id 미설정)");
        return false;
    }

    // 3) 알림 메시지 구성
    $link = 'partner_treatments.php?tab=pending';
    $icon = 'bi-clipboard-check';

    if ($action === 'approved') {
        $type    = 'treatment_approved';
        $title   = '시술 승인 완료';
        $message = "[{$hospital_name}] '{$treatment_name}' 시술이 승인되었습니다.";
        if ($admin_name) {
            $message .= " (처리: {$admin_name})";
        }
    } else {
        // rejected
        $type    = 'treatment_rejected';
        $title   = '시술 승인 거절';
        $message = "[{$hospital_name}] '{$treatment_name}' 시술이 거절되었습니다.";
        if ($reason) {
            $message .= " 사유: {$reason}";
        }
        if ($admin_name) {
            $message .= " (처리: {$admin_name})";
        }
    }

    // 4) gangnamunni_user_notifications에 INSERT
    $noti_stmt = mysqli_prepare($connect,
        "INSERT INTO gangnamunni_user_notifications (member_id, type, title, message, link, icon, is_read, created_at)
         VALUES (?, ?, ?, ?, ?, ?, 0, NOW())");
    if (!$noti_stmt) {
        error_log('[treatment_approval] 알림 INSERT prepare 실패: ' . mysqli_error($connect));
        return false;
    }

    $mid = strval($target_member_id);
    mysqli_stmt_bind_param($noti_stmt, 'ssssss', $mid, $type, $title, $message, $link, $icon);
    $noti_result = mysqli_stmt_execute($noti_stmt);
    mysqli_stmt_close($noti_stmt);

    if (!$noti_result) {
        error_log('[treatment_approval] 알림 INSERT 실행 실패: ' . mysqli_error($connect));
    }

    return $noti_result;
}

// ===== 유틸리티 함수 =====

/**
 * 특정 병원시술의 승인 이력 조회
 *
 * @param mysqli $connect               DB 연결
 * @param int    $hospital_treatment_id  병원시술 ID
 * @param int    $limit                  최대 조회 건수 (기본 20)
 * @return array 이력 목록 (최신순)
 */
function get_treatment_approval_history($connect, $hospital_treatment_id, $limit = 20) {
    $logs = [];
    $stmt = mysqli_prepare($connect,
        "SELECT * FROM gangnamunni_treatment_approval_log
         WHERE hospital_treatment_id = ?
         ORDER BY created_at DESC
         LIMIT ?");
    if (!$stmt) return $logs;

    mysqli_stmt_bind_param($stmt, 'ii', $hospital_treatment_id, $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $logs[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $logs;
}

/**
 * 병원별 승인 통계 조회
 *
 * @param mysqli $connect     DB 연결
 * @param int    $hospital_id 병원 ID
 * @return array 통계 ['total', 'submitted', 'approved', 'rejected', 'resubmitted', 'cancelled']
 */
function get_treatment_approval_stats($connect, $hospital_id) {
    $stats = [
        'total'       => 0,
        'submitted'   => 0,
        'approved'    => 0,
        'rejected'    => 0,
        'resubmitted' => 0,
        'cancelled'   => 0,
    ];

    $stmt = mysqli_prepare($connect,
        "SELECT action, COUNT(*) as cnt
         FROM gangnamunni_treatment_approval_log
         WHERE hospital_id = ?
         GROUP BY action");
    if (!$stmt) return $stats;

    mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $a = $row['action'];
        $c = intval($row['cnt']);
        if (isset($stats[$a])) $stats[$a] = $c;
        $stats['total'] += $c;
    }
    mysqli_stmt_close($stmt);
    return $stats;
}

/**
 * hospital_treatments 승인/거절 타임스탬프 업데이트
 * (approve/reject 시 log_treatment_approval 과 함께 호출)
 *
 * @param mysqli $connect               DB 연결
 * @param int    $hospital_treatment_id  병원시술 ID
 * @param string $action                 'approved' 또는 'rejected'
 * @param string $by                     처리자 이름
 * @return bool  성공 여부
 */
function update_treatment_approval_timestamp($connect, $hospital_treatment_id, $action, $by = '') {
    if ($action === 'approved') {
        $stmt = mysqli_prepare($connect,
            "UPDATE gangnamunni_hospital_treatments
             SET approved_at = NOW(), approved_by = ?, rejected_at = NULL, rejected_by = NULL, updated_at = NOW()
             WHERE id = ?");
    } elseif ($action === 'rejected') {
        $stmt = mysqli_prepare($connect,
            "UPDATE gangnamunni_hospital_treatments
             SET rejected_at = NOW(), rejected_by = ?, approved_at = NULL, approved_by = NULL, updated_at = NOW()
             WHERE id = ?");
    } else {
        return false;
    }

    if (!$stmt) {
        error_log('[treatment_approval] 타임스탬프 UPDATE prepare 실패: ' . mysqli_error($connect));
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'si', $by, $hospital_treatment_id);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}
