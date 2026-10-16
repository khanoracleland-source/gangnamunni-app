<?php
/**
 * CPV 과금 엔진 — 핵심 과금 처리 로직
 *
 * 방문자의 페이지 조회를 감지하여 병원 지갑에서 CPV 비용을 차감합니다.
 * 남용 방지, 지갑 잔액/한도 검증, 테스트 모드 등을 모두 처리합니다.
 *
 * 의존성: cpv_helper.php (getCpvSetting, isCpvEnabled, isCpvTestMode, calculateCpvRate)
 * DB 연결: global $connect (mysqli)
 */

require_once __DIR__ . '/cpv_helper.php';
require_once __DIR__ . '/client_ip.php';

/**
 * 방문자 IP 주소 추출
 * 프록시 헤더(CF-Connecting-IP, X-Forwarded-For)는 실제 프록시에서 온 요청일 때만 신뢰한다.
 * (클라이언트가 헤더를 위조해 중복/일일 한도 검사를 우회하고 병원 지갑을 소진시키는 것을 방지)
 */
function getCpvVisitorIp() {
    return seoulbeauty_client_ip();
}

/**
 * 방문자 쿠키 ID 조회/생성
 * 쿠키 없으면 새로 발급하여 세팅
 */
function getCpvVisitorCookie() {
    $cookie_name = 'cpv_vid';
    if (!empty($_COOKIE[$cookie_name])) {
        return $_COOKIE[$cookie_name];
    }
    // 새 쿠키 발급 (UUID v4 형식)
    $vid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    // 30일 유효
    setcookie($cookie_name, $vid, time() + 86400 * 30, '/', '', true, true);
    return $vid;
}

/**
 * 방문자 세션 ID 조회
 * 세션이 시작되어 있으면 session_id() 반환, 아니면 빈 문자열
 */
function getCpvVisitorSession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return session_id();
    }
    return '';
}

/**
 * CPV 조회 로그 기록
 * 과금 여부와 관계없이 모든 조회를 기록합니다.
 */
function logCpvView($connect, $hospital_id, $page_type, $target_id, $visitor_ip,
                     $visitor_session, $visitor_cookie, $user_agent, $keyword,
                     $procedure_type, $cpv_rate, $is_charged, $charge_skip_reason, $page_url) {
    $stmt = mysqli_prepare($connect,
        "INSERT INTO gangnamunni_cpv_logs
         (hospital_id, page_type, target_id, visitor_ip, visitor_session, visitor_cookie,
          user_agent, keyword, procedure_type, cpv_rate, is_charged, charge_skip_reason,
          page_url, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    if (!$stmt) return false;

    mysqli_stmt_bind_param($stmt, 'issssssssidss',
        $hospital_id, $page_type, $target_id, $visitor_ip,
        $visitor_session, $visitor_cookie, $user_agent,
        $keyword, $procedure_type, $cpv_rate,
        $is_charged, $charge_skip_reason, $page_url
    );
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}

/**
 * 중복 조회 검사 (Anti-abuse)
 * 동일 IP + 세션/쿠키 기준으로 N분 이내 중복 여부 확인
 *
 * @return bool true이면 중복 (차감 스킵해야 함)
 */
function checkCpvDuplicate($connect, $hospital_id, $page_type, $target_id, $visitor_ip, $visitor_cookie) {
    $minutes = (int) getCpvSetting('anti_abuse_minutes', 30);
    if ($minutes <= 0) return false;

    $stmt = mysqli_prepare($connect,
        "SELECT COUNT(*) as cnt FROM gangnamunni_cpv_logs
         WHERE hospital_id = ? AND page_type = ? AND target_id = ?
           AND (visitor_ip = ? OR visitor_cookie = ?)
           AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)"
    );
    if (!$stmt) return false;

    mysqli_stmt_bind_param($stmt, 'issssi',
        $hospital_id, $page_type, $target_id,
        $visitor_ip, $visitor_cookie, $minutes
    );
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return ($row && (int) $row['cnt'] > 0);
}

/**
 * 일일 조회 한도 검사
 * 동일 방문자(IP+쿠키)가 특정 병원에 대해 하루 최대 조회 수 초과 여부
 *
 * @return bool true이면 한도 초과 (차감 스킵해야 함)
 */
function checkCpvDailyLimit($connect, $hospital_id, $visitor_ip, $visitor_cookie) {
    $limit = (int) getCpvSetting('daily_view_limit', 10);
    if ($limit <= 0) return false;

    $stmt = mysqli_prepare($connect,
        "SELECT COUNT(*) as cnt FROM gangnamunni_cpv_logs
         WHERE hospital_id = ? AND DATE(created_at) = CURDATE()
           AND (visitor_ip = ? OR visitor_cookie = ?)"
    );
    if (!$stmt) return false;

    mysqli_stmt_bind_param($stmt, 'iss', $hospital_id, $visitor_ip, $visitor_cookie);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return ($row && (int) $row['cnt'] >= $limit);
}

/**
 * 병원 지갑 조회
 *
 * @return array|null 지갑 정보 또는 null
 */
function getCpvWallet($connect, $hospital_id) {
    $stmt = mysqli_prepare($connect,
        "SELECT * FROM gangnamunni_cpv_wallets WHERE hospital_id = ? LIMIT 1"
    );
    if (!$stmt) return null;

    mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $wallet = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $wallet ?: null;
}

/**
 * 오늘 해당 병원의 총 지출 금액 조회
 */
function getCpvDailySpent($connect, $hospital_id) {
    $stmt = mysqli_prepare($connect,
        "SELECT COALESCE(SUM(cpv_rate), 0) as total
         FROM gangnamunni_cpv_logs
         WHERE hospital_id = ? AND DATE(created_at) = CURDATE() AND is_charged = 1"
    );
    if (!$stmt) return 0;

    mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $row ? (int) $row['total'] : 0;
}

/**
 * 지갑 행 잠금 (트랜잭션 안에서만 호출)
 * 같은 병원에 대한 CPV 과금 요청을 직렬화해, 잠금 이후의 중복/한도 재검사가
 * 먼저 커밋된 요청의 로그를 반드시 보도록 한다.
 *
 * @return array|null 잠긴 지갑 행
 */
function lockCpvWallet($connect, $hospital_id) {
    $stmt = mysqli_prepare($connect,
        "SELECT * FROM gangnamunni_cpv_wallets WHERE hospital_id = ? LIMIT 1 FOR UPDATE"
    );
    if (!$stmt) return null;

    mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $wallet = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $wallet ?: null;
}

/**
 * 지갑 잔액 차감
 * SELECT ... FOR UPDATE 로 동시성 제어
 *
 * @return bool 성공 여부
 */
function deductCpvWallet($connect, $hospital_id, $amount) {
    $stmt = mysqli_prepare($connect,
        "UPDATE gangnamunni_cpv_wallets
         SET balance = balance - ?, updated_at = NOW()
         WHERE hospital_id = ? AND balance >= ?"
    );
    if (!$stmt) return false;

    mysqli_stmt_bind_param($stmt, 'did', $amount, $hospital_id, $amount);
    $ok = mysqli_stmt_execute($stmt);
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    return ($ok && $affected > 0);
}

/**
 * 지갑 일시정지 처리
 */
function pauseCpvWallet($connect, $hospital_id, $reason = '') {
    $stmt = mysqli_prepare($connect,
        "UPDATE gangnamunni_cpv_wallets
         SET is_paused = 1, updated_at = NOW()
         WHERE hospital_id = ?"
    );
    if (!$stmt) return false;

    mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

/**
 * CPV 거래 내역 기록
 */
function recordCpvTransaction($connect, $hospital_id, $type, $amount, $balance_after, $description) {
    $stmt = mysqli_prepare($connect,
        "INSERT INTO gangnamunni_cpv_transactions
         (hospital_id, type, amount, balance_after, description, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())"
    );
    if (!$stmt) return false;

    mysqli_stmt_bind_param($stmt, 'isdds',
        $hospital_id, $type, $amount, $balance_after, $description
    );
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

/**
 * 일일 통계 갱신 (UPSERT)
 * 오늘 날짜의 해당 병원 통계를 누적합니다.
 */
function updateCpvDailyStats($connect, $hospital_id, $cpv_rate, $is_charged) {
    // UPSERT: ON DUPLICATE KEY UPDATE
    $stmt = mysqli_prepare($connect,
        "INSERT INTO gangnamunni_cpv_daily_stats
         (hospital_id, stat_date, total_views, charged_views, total_amount, avg_rate, unique_visitors)
         VALUES (?, CURDATE(), 1, ?, ?, ?, 0)
         ON DUPLICATE KEY UPDATE
           total_views = total_views + 1,
           charged_views = charged_views + VALUES(charged_views),
           total_amount = total_amount + VALUES(total_amount),
           avg_rate = CASE
             WHEN charged_views + VALUES(charged_views) > 0
             THEN (total_amount + VALUES(total_amount)) / (charged_views + VALUES(charged_views))
             ELSE 0
           END"
    );
    if (!$stmt) return false;

    $charged_inc = $is_charged ? 1 : 0;
    $amount_inc = $is_charged ? $cpv_rate : 0;
    $rate_val = $is_charged ? (double) $cpv_rate : 0.0;

    mysqli_stmt_bind_param($stmt, 'iidd',
        $hospital_id, $charged_inc, $amount_inc, $rate_val
    );
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

/**
 * CPV 과금 메인 처리 함수
 *
 * 모든 검증 → 과금 → 기록을 원자적으로 처리합니다.
 *
 * @param int    $hospital_id    병원 ID
 * @param string $page_type      페이지 유형 (hospital, doctor, review, event 등)
 * @param string $target_id      대상 ID (병원/의사/후기/이벤트 등의 PK)
 * @param string $page_url       현재 페이지 URL
 * @param string $keyword        유입 키워드 (nullable)
 * @param string $procedure_type 시술 유형 (nullable)
 *
 * @return array ['success' => bool, 'charged' => bool, 'rate' => int, 'reason' => string]
 */
function processCpvView($hospital_id, $page_type, $target_id, $page_url, $keyword = null, $procedure_type = null) {
    global $connect;

    // 기본 반환 구조
    $result = [
        'success' => false,
        'charged' => false,
        'rate'    => 0,
        'reason'  => ''
    ];

    if (!$connect) {
        $result['reason'] = 'no_db';
        return $result;
    }

    // 마스터 스위치 확인
    if (!isCpvEnabled('cpv_charging_enabled')) {
        $result['success'] = true;
        $result['reason'] = 'disabled';
        return $result;
    }

    // 방문자 정보 수집
    $visitor_ip      = getCpvVisitorIp();
    $visitor_session  = getCpvVisitorSession();
    $visitor_cookie   = getCpvVisitorCookie();
    $user_agent       = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // user_agent 길이 제한 (DB 컬럼 안전)
    if (mb_strlen($user_agent) > 500) {
        $user_agent = mb_substr($user_agent, 0, 500);
    }

    $hospital_id  = (int) $hospital_id;
    $target_id    = (string) $target_id;
    $page_type    = (string) $page_type;
    $page_url     = (string) $page_url;
    $keyword      = $keyword ? (string) $keyword : null;
    $procedure_type = $procedure_type ? (string) $procedure_type : null;

    // --- Anti-abuse 검사 ---

    // 1) 중복 조회 검사 (동일 IP/쿠키 + N분 이내)
    if (checkCpvDuplicate($connect, $hospital_id, $page_type, $target_id, $visitor_ip, $visitor_cookie)) {
        logCpvView($connect, $hospital_id, $page_type, $target_id, $visitor_ip,
            $visitor_session, $visitor_cookie, $user_agent, $keyword,
            $procedure_type, 0, 0, 'duplicate', $page_url);

        $result['success'] = true;
        $result['reason'] = 'duplicate';
        return $result;
    }

    // 2) 일일 조회 한도 검사
    if (checkCpvDailyLimit($connect, $hospital_id, $visitor_ip, $visitor_cookie)) {
        logCpvView($connect, $hospital_id, $page_type, $target_id, $visitor_ip,
            $visitor_session, $visitor_cookie, $user_agent, $keyword,
            $procedure_type, 0, 0, 'daily_limit', $page_url);

        $result['success'] = true;
        $result['reason'] = 'daily_limit';
        return $result;
    }

    // --- 지갑 검사 ---

    // 3) 지갑 존재 여부
    $wallet = getCpvWallet($connect, $hospital_id);
    if (!$wallet) {
        logCpvView($connect, $hospital_id, $page_type, $target_id, $visitor_ip,
            $visitor_session, $visitor_cookie, $user_agent, $keyword,
            $procedure_type, 0, 0, 'no_wallet', $page_url);

        $result['success'] = true;
        $result['reason'] = 'no_wallet';
        return $result;
    }

    // 4) 지갑 비활성 상태
    if (empty($wallet['is_active'])) {
        logCpvView($connect, $hospital_id, $page_type, $target_id, $visitor_ip,
            $visitor_session, $visitor_cookie, $user_agent, $keyword,
            $procedure_type, 0, 0, 'inactive', $page_url);

        $result['success'] = true;
        $result['reason'] = 'inactive';
        return $result;
    }

    // 5) 지갑 일시정지 상태
    if (!empty($wallet['is_paused'])) {
        logCpvView($connect, $hospital_id, $page_type, $target_id, $visitor_ip,
            $visitor_session, $visitor_cookie, $user_agent, $keyword,
            $procedure_type, 0, 0, 'paused', $page_url);

        $result['success'] = true;
        $result['reason'] = 'paused';
        return $result;
    }

    // CPV 단가 계산
    $cpv_rate = calculateCpvRate($hospital_id, $keyword, $procedure_type, false);
    $result['rate'] = $cpv_rate;

    // 6) 잔액 부족 → 자동 일시정지
    if ((float) $wallet['balance'] <= 0) {
        $auto_pause_zero = getCpvSetting('auto_pause_zero', '1') === '1';
        if ($auto_pause_zero) {
            pauseCpvWallet($connect, $hospital_id, 'zero_balance');
        }

        logCpvView($connect, $hospital_id, $page_type, $target_id, $visitor_ip,
            $visitor_session, $visitor_cookie, $user_agent, $keyword,
            $procedure_type, $cpv_rate, 0, 'zero_balance', $page_url);

        $result['success'] = true;
        $result['reason'] = 'zero_balance';
        return $result;
    }

    // 7) 일일 예산 한도 초과
    if ($wallet['daily_cap'] > 0) {
        $daily_spent = getCpvDailySpent($connect, $hospital_id);
        $auto_pause_cap = getCpvSetting('auto_pause_cap', '1') === '1';

        if ($daily_spent >= (int) $wallet['daily_cap']) {
            if ($auto_pause_cap) {
                // 일일 한도 초과 시 일시정지하지 않고 스킵만 (다음 날 자동 리셋)
            }

            logCpvView($connect, $hospital_id, $page_type, $target_id, $visitor_ip,
                $visitor_session, $visitor_cookie, $user_agent, $keyword,
                $procedure_type, $cpv_rate, 0, 'daily_cap', $page_url);

            $result['success'] = true;
            $result['reason'] = 'daily_cap';
            return $result;
        }
    }

    // --- 테스트 모드 ---
    if (isCpvTestMode()) {
        logCpvView($connect, $hospital_id, $page_type, $target_id, $visitor_ip,
            $visitor_session, $visitor_cookie, $user_agent, $keyword,
            $procedure_type, $cpv_rate, 0, 'test_mode', $page_url);

        // 테스트 모드에서도 일일 통계는 기록 (조회수만, 과금 제외)
        updateCpvDailyStats($connect, $hospital_id, $cpv_rate, false);

        $result['success'] = true;
        $result['reason'] = 'test_mode';
        return $result;
    }

    // --- 실제 과금 처리 (트랜잭션) ---
    mysqli_begin_transaction($connect);

    try {
        // 동시성: 위(330행~)의 중복·한도 검사는 잠금 없이 수행되는 사전 필터라서,
        // 같은 방문자의 동시 요청 두 개가 모두 통과해 이중 과금될 수 있었다.
        // 지갑 행을 FOR UPDATE 로 잠가 같은 병원의 과금을 직렬화한 뒤 다시 검사한다.
        // (잠금 획득 전에는 이 트랜잭션에서 일반 SELECT 를 하지 않으므로, 재검사 스냅샷은
        //  잠금 이후 시점 = 앞선 요청의 커밋된 로그가 보이는 시점에 만들어진다.)
        $locked_wallet = lockCpvWallet($connect, $hospital_id);
        $recheck_reason = null;
        if (!$locked_wallet) {
            $recheck_reason = 'no_wallet';
        } elseif (checkCpvDuplicate($connect, $hospital_id, $page_type, $target_id, $visitor_ip, $visitor_cookie)) {
            $recheck_reason = 'duplicate';
        } elseif (checkCpvDailyLimit($connect, $hospital_id, $visitor_ip, $visitor_cookie)) {
            $recheck_reason = 'daily_limit';
        } elseif (empty($locked_wallet['is_active'])) {
            $recheck_reason = 'inactive';
        } elseif (!empty($locked_wallet['is_paused'])) {
            $recheck_reason = 'paused';
        } elseif ((int) $locked_wallet['daily_cap'] > 0
            && getCpvDailySpent($connect, $hospital_id) >= (int) $locked_wallet['daily_cap']) {
            $recheck_reason = 'daily_cap';
        }

        if ($recheck_reason !== null) {
            mysqli_rollback($connect);

            $logged_rate = in_array($recheck_reason, ['no_wallet', 'duplicate', 'daily_limit', 'inactive', 'paused'], true) ? 0 : $cpv_rate;
            logCpvView($connect, $hospital_id, $page_type, $target_id, $visitor_ip,
                $visitor_session, $visitor_cookie, $user_agent, $keyword,
                $procedure_type, $logged_rate, 0, $recheck_reason, $page_url);

            $result['success'] = true;
            $result['reason'] = $recheck_reason;
            return $result;
        }

        // 잔액 차감 (동시성 안전: balance >= amount 조건 포함)
        if (!deductCpvWallet($connect, $hospital_id, $cpv_rate)) {
            mysqli_rollback($connect);

            logCpvView($connect, $hospital_id, $page_type, $target_id, $visitor_ip,
                $visitor_session, $visitor_cookie, $user_agent, $keyword,
                $procedure_type, $cpv_rate, 0, 'insufficient_balance', $page_url);

            $result['success'] = true;
            $result['reason'] = 'insufficient_balance';
            return $result;
        }

        // 차감 후 잔액 조회
        $updated_wallet = getCpvWallet($connect, $hospital_id);
        $balance_after = $updated_wallet ? (float) $updated_wallet['balance'] : 0;

        // 거래 내역 기록
        $desc = sprintf('CPV 차감: %s #%s (%s)', $page_type, $target_id, $page_url);
        if (mb_strlen($desc) > 500) {
            $desc = mb_substr($desc, 0, 500);
        }
        recordCpvTransaction($connect, $hospital_id, 'cpv_charge', $cpv_rate, $balance_after, $desc);

        // 일일 통계 갱신
        updateCpvDailyStats($connect, $hospital_id, $cpv_rate, true);

        // 로그 기록 (과금 성공)
        logCpvView($connect, $hospital_id, $page_type, $target_id, $visitor_ip,
            $visitor_session, $visitor_cookie, $user_agent, $keyword,
            $procedure_type, $cpv_rate, 1, null, $page_url);

        mysqli_commit($connect);

        $result['success'] = true;
        $result['charged'] = true;
        $result['reason'] = 'charged';
        return $result;

    } catch (\Exception $e) {
        mysqli_rollback($connect);

        // 에러 로그 기록
        error_log('[CPV Engine] processCpvView 트랜잭션 실패: ' . $e->getMessage());

        logCpvView($connect, $hospital_id, $page_type, $target_id, $visitor_ip,
            $visitor_session, $visitor_cookie, $user_agent, $keyword,
            $procedure_type, $cpv_rate, 0, 'error', $page_url);

        $result['reason'] = 'error';
        return $result;
    }
}
