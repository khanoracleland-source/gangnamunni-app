<?php
/**
 * ========================================
 * 포인트 원장(Ledger) — 단일 진실 공급원
 * ========================================
 *
 * 모든 포인트 증감은 반드시 이 파일의 함수를 통해서만 처리한다.
 * - 잔액(balance)은 각 거래 행에 스냅샷으로 기록하며, 잔액 조회는 마지막 행의 balance 를 사용한다(SUM 미사용).
 * - member_id 는 g5_member.mb_no (INT) 로 통일. mb_id(로그인 문자열)는 points_member_no() 로 변환.
 * - 동일 이벤트 중복 지급 방지: (reference_type, reference_id, type) 유니크 + 사전 존재검사(멱등).
 * - 회원별 GET_LOCK 으로 동시성 직렬화.
 *
 * @file    gangnamunni/includes/points_ledger.php
 * @created 2026-07-24
 */

if (!defined('POINTS_LEDGER_LOADED')) {
    define('POINTS_LEDGER_LOADED', 1);
    define('POINTS_SCHEMA_VERSION', 2); // 스키마 목표 버전

    /** 잔액을 증가시키는 유형(+), 그 외는 감소(-). 'admin' 은 과거 스키마 적립 유형(하위호환). */
    function points_is_credit($type) {
        return in_array($type, ['earn', 'refund', 'admin_add', 'admin'], true);
    }

    /**
     * 배포된 스키마 버전을 확인한다. Web request에서는 절대 DDL을 실행하지 않는다.
     * 설치/업그레이드는 배포 단계에서 sql/points_ledger.sql 로만 수행한다.
     */
    function points_ensure_schema($connect) {
        static $done = false;
        if ($done) return;

        try {
            $r = mysqli_query($connect, "SELECT sval FROM gangnamunni_point_settings WHERE skey='schema_version' LIMIT 1");
        } catch (Throwable $e) {
            error_log('[points_ensure_schema] schema check failed: ' . $e->getMessage());
            throw new RuntimeException('Point schema is not installed. Run the deployment migration.');
        }
        if ($r && ($row = mysqli_fetch_assoc($r)) && (int)$row['sval'] >= POINTS_SCHEMA_VERSION) {
            $done = true;
            return;
        }
        error_log('[points_ensure_schema] point schema is missing or outdated; run gangnamunni/sql/points_ledger.sql');
        throw new RuntimeException('Point schema is not installed. Run the deployment migration.');
    }

    /**
     * 설정 로드(요청당 캐시). 반환: 연관배열(문자열 값).
     */
    function points_settings($connect) {
        static $cache = null;
        if ($cache !== null) return $cache;
        points_ensure_schema($connect);
        $cache = [];
        $r = mysqli_query($connect, "SELECT skey, sval FROM gangnamunni_point_settings");
        if ($r) while ($row = mysqli_fetch_assoc($r)) $cache[$row['skey']] = $row['sval'];
        return $cache;
    }

    function points_setting($connect, $key, $default = null) {
        $s = points_settings($connect);
        return array_key_exists($key, $s) ? $s[$key] : $default;
    }

    /** 포인트 제도 전체 활성화 여부 */
    function points_enabled($connect) {
        return (int)points_setting($connect, 'enabled', '0') === 1;
    }

    // ── 안전 조회(비핵심 표시용) ─────────────────────────────
    // 스키마 미설치 등으로 원장을 쓸 수 없을 때, 공개 페이지가 500으로 죽지 않도록
    // 예외를 삼키고 안전한 기본값을 돌려준다. (핵심 증감 경로는 계속 엄격하게 동작)

    /** 잔액 조회(실패 시 0). 마이페이지·등급 등 표시용. */
    function points_balance_safe($connect, $member_no) {
        try { return points_balance($connect, $member_no); }
        catch (\Throwable $e) { error_log('[points] balance unavailable: ' . $e->getMessage()); return 0; }
    }

    /** 활성화 여부(실패 시 false). */
    function points_enabled_safe($connect) {
        try { return points_enabled($connect); }
        catch (\Throwable $e) { return false; }
    }

    /** 설정값(실패 시 기본값). */
    function points_setting_safe($connect, $key, $default = null) {
        try { return points_setting($connect, $key, $default); }
        catch (\Throwable $e) { return $default; }
    }

    /** 설정 저장(관리자). $pairs = ['signup_point'=>'1000', ...] */
    function points_save_settings($connect, array $pairs) {
        points_ensure_schema($connect);
        foreach ($pairs as $k => $v) {
            $st = mysqli_prepare($connect, "INSERT INTO gangnamunni_point_settings (skey, sval) VALUES (?, ?)
                                            ON DUPLICATE KEY UPDATE sval = VALUES(sval)");
            if ($st) { $v = (string)$v; mysqli_stmt_bind_param($st, 'ss', $k, $v); mysqli_stmt_execute($st); mysqli_stmt_close($st); }
        }
    }

    /**
     * mb_id(로그인 문자열) → mb_no(INT) 변환. 실패 시 0.
     */
    function points_member_no($connect, $mb_id) {
        $mb_id = trim((string)$mb_id);
        if ($mb_id === '') return 0;
        if (ctype_digit($mb_id)) {
            // 이미 숫자면 mb_no 로 간주하되, 존재 확인
            $st = mysqli_prepare($connect, "SELECT mb_no FROM g5_member WHERE mb_no = ? LIMIT 1");
            if ($st) { $n=(int)$mb_id; mysqli_stmt_bind_param($st, 'i', $n); mysqli_stmt_execute($st); $res=mysqli_stmt_get_result($st); $row=$res?mysqli_fetch_assoc($res):null; mysqli_stmt_close($st); if ($row) return (int)$row['mb_no']; }
        }
        $st = mysqli_prepare($connect, "SELECT mb_no FROM g5_member WHERE mb_id = ? LIMIT 1");
        if (!$st) return 0;
        mysqli_stmt_bind_param($st, 's', $mb_id);
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($st);
        return $row ? (int)$row['mb_no'] : 0;
    }

    /**
     * 현재 잔액 = 마지막 거래 행의 balance. 거래 없으면 0.
     */
    function points_balance($connect, $member_no) {
        $member_no = (int)$member_no;
        if ($member_no <= 0) return 0;
        points_ensure_schema($connect);
        $st = mysqli_prepare($connect, "SELECT balance FROM gangnamunni_points WHERE member_id = ? ORDER BY id DESC LIMIT 1");
        if (!$st) return 0;
        mysqli_stmt_bind_param($st, 'i', $member_no);
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($st);
        return $row ? (int)$row['balance'] : 0;
    }

    /**
     * 핵심 거래 기록기. 모든 증감의 단일 진입점.
     *
     * @param int    $member_no  g5_member.mb_no
     * @param string $type       earn|use|refund|expire|admin_add|admin_sub|earn_cancel
     * @param int    $amount     항상 양수
     * @param string $reason     표시용 사유
     * @param string $ref_type   signup|review|booking|payment|refund|admin|event (null 가능)
     * @param int    $ref_id     참조 대상 id (null 가능)
     * @param int    $expire_days 유효기간(일). null 이면 설정값(적립계열만 적용)
     * @return array ['ok'=>bool, 'balance'=>int, 'skipped'=>bool, 'error'=>string|null]
     */
    function points_txn($connect, $member_no, $type, $amount, $reason = '', $ref_type = null, $ref_id = null, $expire_days = null) {
        points_ensure_schema($connect);
        $member_no = (int)$member_no;
        $amount = (int)$amount;
        $valid_types = ['earn','use','refund','expire','admin_add','admin_sub','earn_cancel'];
        if ($member_no <= 0 || $amount <= 0 || !in_array($type, $valid_types, true)) {
            return ['ok' => false, 'balance' => 0, 'skipped' => false, 'error' => 'invalid_args'];
        }

        // 회원별 직렬화 잠금
        $lock = 'pts_' . $member_no;
        $lk = mysqli_prepare($connect, "SELECT GET_LOCK(?, 5)");
        $got = 0;
        if ($lk) { mysqli_stmt_bind_param($lk, 's', $lock); mysqli_stmt_execute($lk); $r=mysqli_stmt_get_result($lk); $got=(int)(mysqli_fetch_row($r)[0] ?? 0); mysqli_stmt_close($lk); }
        if ($got !== 1) {
            return ['ok' => false, 'balance' => points_balance($connect, $member_no), 'skipped' => false, 'error' => 'lock_failed'];
        }

        try {
            // 멱등: 동일 (ref_type, ref_id, type) 이미 있으면 skip
            if ($ref_type !== null && $ref_id !== null) {
                $chk = mysqli_prepare($connect, "SELECT balance FROM gangnamunni_points WHERE reference_type = ? AND reference_id = ? AND type = ? LIMIT 1");
                if ($chk) {
                    $rid = (int)$ref_id;
                    mysqli_stmt_bind_param($chk, 'sis', $ref_type, $rid, $type);
                    mysqli_stmt_execute($chk);
                    $res = mysqli_stmt_get_result($chk);
                    $exist = $res ? mysqli_fetch_assoc($res) : null;
                    mysqli_stmt_close($chk);
                    if ($exist) {
                        return ['ok' => true, 'balance' => points_balance($connect, $member_no), 'skipped' => true, 'error' => null];
                    }
                }
            }

            $cur = points_balance($connect, $member_no);
            if (in_array($type, ['use', 'admin_sub'], true) && $amount > $cur) {
                return ['ok' => false, 'balance' => $cur, 'skipped' => false, 'error' => 'insufficient'];
            }
            $delta = points_is_credit($type) ? $amount : -$amount;
            $new = $cur + $delta;

            // 만료일: 적립계열만
            $expires_at = null;
            if (points_is_credit($type)) {
                if ($expire_days === null) $expire_days = (int)points_setting($connect, 'expire_days', '0');
                $expire_days = (int)$expire_days;
                if ($expire_days > 0) $expires_at = date('Y-m-d', strtotime("+{$expire_days} days"));
            }

            $ins = mysqli_prepare($connect, "INSERT INTO gangnamunni_points
                (member_id, type, amount, balance, reason, reference_type, reference_id, expires_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$ins) return ['ok' => false, 'balance' => $cur, 'skipped' => false, 'error' => 'prepare_failed'];
            // reference_id / expires_at 는 NULL 가능 → 문자열('s')로 바인딩하면 null 이 SQL NULL 로 저장됨
            $rid = $ref_id === null ? null : (int)$ref_id;
            mysqli_stmt_bind_param($ins, 'isiissss', $member_no, $type, $amount, $new, $reason, $ref_type, $rid, $expires_at);
            $ok = mysqli_stmt_execute($ins);
            $err = $ok ? null : mysqli_error($connect);
            mysqli_stmt_close($ins);

            // 유니크 충돌(동시 중복) → skip 처리
            if (!$ok && $err && stripos($err, 'Duplicate') !== false) {
                return ['ok' => true, 'balance' => points_balance($connect, $member_no), 'skipped' => true, 'error' => null];
            }
            return ['ok' => (bool)$ok, 'balance' => $ok ? $new : $cur, 'skipped' => false, 'error' => $err];
        } finally {
            if ($got) {
                $ul = mysqli_prepare($connect, "SELECT RELEASE_LOCK(?)");
                if ($ul) { mysqli_stmt_bind_param($ul, 's', $lock); mysqli_stmt_execute($ul); mysqli_stmt_close($ul); }
            }
        }
    }

    // ── 편의 래퍼 ───────────────────────────────────────────

    /** 적립 */
    function points_earn($connect, $member_no, $amount, $reason, $ref_type = null, $ref_id = null) {
        return points_txn($connect, $member_no, 'earn', $amount, $reason, $ref_type, $ref_id);
    }

    /** 사용(차감). 잔액 부족 시 실패. */
    function points_use($connect, $member_no, $amount, $reason, $ref_type = null, $ref_id = null) {
        return points_txn($connect, $member_no, 'use', $amount, $reason, $ref_type, $ref_id);
    }

    /** 결제취소 시 사용포인트 복구 */
    function points_refund($connect, $member_no, $amount, $reason, $ref_type = null, $ref_id = null) {
        return points_txn($connect, $member_no, 'refund', $amount, $reason, $ref_type, $ref_id);
    }

    /**
     * 예약 확정 시 예약금액의 booking_percent% 적립.
     * @param int $price 예약 금액(원)
     */
    function points_earn_booking($connect, $member_no, $booking_id, $price) {
        if (!points_enabled($connect)) return ['ok' => false, 'skipped' => true, 'balance' => 0, 'error' => 'disabled'];
        $percent = (float)points_setting($connect, 'booking_percent', '1');
        $amount = (int)floor(((int)$price) * $percent / 100);
        if ($amount <= 0) return ['ok' => false, 'skipped' => true, 'balance' => 0, 'error' => 'zero_amount'];
        return points_earn($connect, $member_no, $amount, "예약 확정 적립 (" . rtrim(rtrim(number_format($percent, 2), '0'), '.') . "%)", 'booking', $booking_id);
    }

    /**
     * 특정 참조(reference_type, reference_id)로 적립된 포인트를 회수(clawback).
     * 원 적립(earn) 행을 찾아, 그 회원에게 동일 금액을 earn_cancel 로 차감한다.
     *
     * - 회원번호는 원 적립 행에서 읽으므로 별도 인자 불필요(후기 등 작성자 자동 판별).
     * - (ref_type, ref_id, earn_cancel) 유니크로 이중 회수 방지(멱등).
     */
    function points_revoke_earn($connect, $ref_type, $ref_id, $reason = '적립 취소') {
        points_ensure_schema($connect);
        $ref_type = (string)$ref_type;
        $ref_id = (int)$ref_id;
        if ($ref_type === '' || $ref_id <= 0) return ['ok' => false, 'skipped' => true, 'balance' => 0, 'error' => 'invalid'];
        $st = mysqli_prepare($connect, "SELECT member_id, amount FROM gangnamunni_points WHERE reference_type=? AND reference_id=? AND type='earn' LIMIT 1");
        if (!$st) return ['ok' => false, 'skipped' => false, 'balance' => 0, 'error' => 'prepare_failed'];
        mysqli_stmt_bind_param($st, 'si', $ref_type, $ref_id);
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($st);
        if (!$row) return ['ok' => false, 'skipped' => true, 'balance' => 0, 'error' => 'no_earn'];
        $member_no = (int)$row['member_id'];
        $amount = (int)$row['amount'];
        if ($member_no <= 0 || $amount <= 0) return ['ok' => false, 'skipped' => true, 'balance' => 0, 'error' => 'zero'];
        return points_txn($connect, $member_no, 'earn_cancel', $amount, $reason, $ref_type, $ref_id);
    }

    /**
     * 예약 취소 시 해당 예약으로 적립된 포인트를 회수(clawback).
     */
    function points_cancel_booking($connect, $member_no, $booking_id) {
        return points_revoke_earn($connect, 'booking', $booking_id, '예약 취소 회수');
    }
}
