<?php
/**
 * 포인트 제도 v2 — 적립 규칙 엔진 (#포인트제도 v2)
 *
 * 규칙 문구: includes/points_policy_content.php (사용자 화면)
 * 금액/한도: gangnamunni_point_settings 의 v2_* 키 (관리자 > 포인트 관리)
 * 모든 함수는 v2 스위치가 꺼져 있으면 아무 것도 하지 않는다(0/false 반환).
 *
 * 적립 reference_type 규칙(멱등 키 = reference_type + reference_id + type):
 *   review_photo   : 일반 후기(전·후 사진)   ref_id = hospital_reviews.id
 *   review_receipt : 영수증 인증 후기         ref_id = hospital_reviews.id
 *   review_first   : 첫 후기(주간 일괄)       ref_id = hospital_reviews.id
 *   progress       : 경과 후기               ref_id = gangnamunni_progress_reviews.id
 *   booking        : 앱 결제 금액 적립        ref_id = gangnamunni_bookings.id
 *   review         : (v1) 후기 승인 적립      ref_id = hospital_reviews.id
 */
require_once __DIR__ . '/points_ledger.php';

if (!defined('POINTS_POLICY_ENGINE_LOADED')) {
    define('POINTS_POLICY_ENGINE_LOADED', 1);

    /** 후기 적립 유형 → [reference_type, 설정키, 라벨] */
    function points_v2_review_categories() {
        return [
            'photo'   => ['review_photo',   'v2_review_photo_point',   '일반 후기(전·후 사진) 적립'],
            'receipt' => ['review_receipt', 'v2_review_receipt_point', '영수증 인증 후기 적립'],
        ];
    }

    /** 이번 달(1일~말일) 해당 유형 적립 건수 */
    function points_v2_month_count($connect, $member_no, array $ref_types) {
        if (empty($ref_types)) return 0;
        $ph = implode(',', array_fill(0, count($ref_types), '?'));
        $sql = "SELECT COUNT(*) AS c FROM gangnamunni_points
                WHERE member_id = ? AND type = 'earn' AND reference_type IN ($ph)
                  AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
        $st = mysqli_prepare($connect, $sql);
        if (!$st) return 0;
        $types = 'i' . str_repeat('s', count($ref_types));
        $args = array_merge([(int)$member_no], array_values($ref_types));
        mysqli_stmt_bind_param($st, $types, ...$args);
        mysqli_stmt_execute($st);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
        mysqli_stmt_close($st);
        return (int)($row['c'] ?? 0);
    }

    /**
     * 후기 승인 시 유형별 적립(관리자가 유형 선택).
     * @param array  $review   hospital_reviews 행(user_id 필요)
     * @param string $category photo|receipt|none
     * @return array ['amount'=>int, 'message'=>string]
     */
    function points_v2_review_award($connect, array $review, $rid, $category) {
        if (!points_v2_on($connect, 'v2_policy_enabled')) return ['amount' => 0, 'message' => ''];
        $cats = points_v2_review_categories();
        if (!isset($cats[$category])) return ['amount' => 0, 'message' => '적립 없음'];
        [$ref_type, $key, $label] = $cats[$category];
        $mb_no = points_member_no($connect, $review['user_id'] ?? '');
        if ($mb_no <= 0) return ['amount' => 0, 'message' => '회원 아님(적립 제외)'];
        $cap = points_v2_int($connect, 'v2_monthly_cap', 3);
        if ($cap > 0 && points_v2_month_count($connect, $mb_no, [$ref_type]) >= $cap) {
            return ['amount' => 0, 'message' => "이번 달 {$label} 한도({$cap}회) 초과"];
        }
        $pt = points_v2_int($connect, $key, 0);
        if ($pt <= 0) return ['amount' => 0, 'message' => '적립 금액 0'];
        $r = points_earn($connect, $mb_no, $pt, $label, $ref_type, (int)$rid);
        if (!empty($r['ok']) && empty($r['skipped'])) return ['amount' => $pt, 'message' => ''];
        return ['amount' => 0, 'message' => !empty($r['skipped']) ? '이미 적립됨' : '적립 실패'];
    }

    /** 후기 거부/삭제 시 해당 후기로 적립된 모든 포인트 회수(멱등). v1 'review' 포함. */
    function points_v2_review_revoke_all($connect, $rid, $reason = '후기 거부/삭제 회수') {
        foreach (['review', 'review_photo', 'review_receipt', 'review_first'] as $rt) {
            try { points_revoke_earn($connect, $rt, (int)$rid, $reason); }
            catch (\Throwable $e) { error_log('[points_v2_review_revoke_all] ' . $rt . ' #' . $rid . ': ' . $e->getMessage()); }
        }
    }

    /** 결제 금액 구간별 적립액(사용 포인트와 무관하게 총 시술 금액 기준) */
    function points_v2_payment_tier_point($connect, $price) {
        $price = (int)$price;
        if ($price <= 0) return 0;
        if ($price < points_v2_int($connect, 'v2_pay_tier1_under', 50000)) return points_v2_int($connect, 'v2_pay_tier1_point', 3000);
        if ($price < points_v2_int($connect, 'v2_pay_tier2_under', 100000)) return points_v2_int($connect, 'v2_pay_tier2_point', 5000);
        return points_v2_int($connect, 'v2_pay_tier3_point', 10000);
    }

    /**
     * 시술이용권 첫 사용(병원이 예약을 '시술 완료' 처리) 시 적립.
     * 총 시술 금액 = 결제 원장의 gross_amount(포인트 차감 전) → 없으면 예약 가격.
     */
    function points_v2_earn_booking($connect, $member_no, $booking_id, $booking_price) {
        if (!points_v2_on($connect, 'v2_policy_enabled')) return ['ok' => false, 'skipped' => true, 'error' => 'v2_off'];
        $gross = (int)$booking_price;
        $st = mysqli_prepare($connect, "SELECT COALESCE(gross_amount, amount) AS g FROM gangnamunni_platform_payments
            WHERE payment_type='booking' AND booking_id = ? AND status IN ('paid','partially_refunded') LIMIT 1");
        if ($st) {
            $bid = (int)$booking_id;
            mysqli_stmt_bind_param($st, 'i', $bid);
            mysqli_stmt_execute($st);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
            mysqli_stmt_close($st);
            if ($row && (int)$row['g'] > 0) $gross = (int)$row['g'];
        }
        $pt = points_v2_payment_tier_point($connect, $gross);
        if ($pt <= 0) return ['ok' => false, 'skipped' => true, 'error' => 'zero_amount'];
        return points_earn($connect, $member_no, $pt, '앱 결제 금액 적립', 'booking', (int)$booking_id);
    }

    // ── 첫 후기(주간 일괄) ───────────────────────────────────

    /** 관리자 설정 공휴일(YYYY-MM-DD, 쉼표 구분) */
    function points_v2_holidays($connect) {
        $raw = (string)points_setting_safe($connect, 'v2_holidays', '');
        return array_filter(array_map('trim', explode(',', $raw)));
    }

    /** $date(Y-m-d)가 속한 주의 '일괄 적립일' = 화요일(공휴일·주말이면 다음 영업일) */
    function points_v2_batch_day_for_week($connect, $date) {
        $ts = strtotime($date);
        $monday = strtotime('monday this week', $ts);
        $d = strtotime('+1 day', $monday); // 화요일
        $hol = points_v2_holidays($connect);
        while (in_array(date('Y-m-d', $d), $hol, true) || (int)date('N', $d) >= 6) $d = strtotime('+1 day', $d);
        return date('Y-m-d', $d);
    }

    /**
     * 첫 후기 판정 키. 리뷰에 event_id 가 있으면 이벤트, 없으면 (병원 + 시술명) 단위.
     * 정책 문구 "후기가 없던 이벤트에 첫 후기" 의 '이벤트' 정의를 한 곳에서 관리한다.
     */
    function points_v2_first_review_is_first($connect, array $rv) {
        $rid = (int)$rv['id'];
        if (!empty($rv['event_id'])) {
            $st = mysqli_prepare($connect, "SELECT COUNT(*) c FROM hospital_reviews
                WHERE event_id = ? AND is_active = 1 AND (created_at < ? OR (created_at = ? AND id < ?))");
            $eid = (int)$rv['event_id'];
            mysqli_stmt_bind_param($st, 'issi', $eid, $rv['created_at'], $rv['created_at'], $rid);
        } else {
            $st = mysqli_prepare($connect, "SELECT COUNT(*) c FROM hospital_reviews
                WHERE hospital_id = ? AND TRIM(COALESCE(treatments,'')) = TRIM(?) AND is_active = 1
                  AND (created_at < ? OR (created_at = ? AND id < ?))");
            $hid = (int)$rv['hospital_id'];
            $tr = (string)($rv['treatments'] ?? '');
            mysqli_stmt_bind_param($st, 'isssi', $hid, $tr, $rv['created_at'], $rv['created_at'], $rid);
        }
        mysqli_stmt_execute($st);
        $c = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($st))['c'] ?? 1);
        mysqli_stmt_close($st);
        return $c === 0;
    }

    /**
     * 전주(월~일) 작성·승인된 첫 후기에 일괄 적립. 오늘이 일괄 적립일이 아니면 아무 것도 안 함($force 제외).
     * @return array ['ran'=>bool, 'awarded'=>int, 'skipped'=>array]
     */
    function points_v2_first_review_batch($connect, $today = null, $force = false) {
        $out = ['ran' => false, 'awarded' => 0, 'skipped' => []];
        if (!points_v2_on($connect, 'v2_first_review_enabled')) return $out;
        $today = $today ?: date('Y-m-d');
        if (!$force && points_v2_batch_day_for_week($connect, $today) !== $today) return $out;
        $out['ran'] = true;
        $this_monday = date('Y-m-d', strtotime('monday this week', strtotime($today)));
        $from = date('Y-m-d 00:00:00', strtotime('-7 days', strtotime($this_monday)));
        $to   = $this_monday . ' 00:00:00';
        $pt   = points_v2_int($connect, 'v2_review_first_point', 10000);
        $cap  = points_v2_int($connect, 'v2_first_yearly_cap', 5);
        if ($pt <= 0) return $out;

        $st = mysqli_prepare($connect, "SELECT id, hospital_id, user_id, treatments, event_id, created_at
            FROM hospital_reviews WHERE is_active = 1 AND created_at >= ? AND created_at < ? ORDER BY created_at, id");
        mysqli_stmt_bind_param($st, 'ss', $from, $to);
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        $rows = [];
        while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
        mysqli_stmt_close($st);

        foreach ($rows as $rv) {
            $mb_no = points_member_no($connect, $rv['user_id'] ?? '');
            if ($mb_no <= 0) { $out['skipped'][] = [$rv['id'], 'not_member']; continue; }
            if (!points_v2_first_review_is_first($connect, $rv)) { $out['skipped'][] = [$rv['id'], 'not_first']; continue; }
            $yc = mysqli_prepare($connect, "SELECT COUNT(*) c FROM gangnamunni_points WHERE member_id = ? AND type='earn'
                AND reference_type='review_first' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)");
            mysqli_stmt_bind_param($yc, 'i', $mb_no);
            mysqli_stmt_execute($yc);
            $cnt = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($yc))['c'] ?? 0);
            mysqli_stmt_close($yc);
            if ($cap > 0 && $cnt >= $cap) { $out['skipped'][] = [$rv['id'], 'yearly_cap']; continue; }
            $r = points_earn($connect, $mb_no, $pt, '첫 후기 작성 적립', 'review_first', (int)$rv['id']);
            if (!empty($r['ok']) && empty($r['skipped'])) $out['awarded']++;
        }
        return $out;
    }

    // ── 경과 후기 ────────────────────────────────────────────

    function points_v2_progress_required_entries($connect, $kind) {
        return $kind === 'surgery'
            ? points_v2_int($connect, 'v2_progress_surgery_entries', 10)
            : points_v2_int($connect, 'v2_progress_procedure_entries', 3);
    }

    /**
     * 경과 후기 승인 적립(시술 6,000P / 수술 15,000P, 월 3회 합산 한도).
     * @return array ['amount'=>int, 'message'=>string]
     */
    function points_v2_progress_award($connect, array $pr) {
        if (!points_v2_on($connect, 'v2_policy_enabled')) return ['amount' => 0, 'message' => '포인트 제도 v2 꺼짐(적립 없음)'];
        $mb_no = (int)$pr['member_id'];
        $cap = points_v2_int($connect, 'v2_monthly_cap', 3);
        if ($cap > 0 && points_v2_month_count($connect, $mb_no, ['progress']) >= $cap) {
            return ['amount' => 0, 'message' => "이번 달 경과 후기 적립 한도({$cap}회) 초과"];
        }
        $surgery = ($pr['kind'] ?? '') === 'surgery';
        $pt = points_v2_int($connect, $surgery ? 'v2_progress_surgery_point' : 'v2_progress_procedure_point', $surgery ? 15000 : 6000);
        if ($pt <= 0) return ['amount' => 0, 'message' => '적립 금액 0'];
        $r = points_earn($connect, $mb_no, $pt, $surgery ? '경과 후기 적립(수술)' : '경과 후기 적립(시술)', 'progress', (int)$pr['id']);
        if (!empty($r['ok']) && empty($r['skipped'])) return ['amount' => $pt, 'message' => ''];
        return ['amount' => 0, 'message' => !empty($r['skipped']) ? '이미 적립됨' : '적립 실패'];
    }

    // ── 소멸 ─────────────────────────────────────────────────

    /**
     * 기한이 지난(expires_at < 오늘) lot 의 잔량을 소멸 처리. 회원 잠금 안에서 잔량을 다시 읽어 이중 소멸을 막는다.
     * @return array ['expired_lots'=>int, 'expired_points'=>int]
     */
    function points_v2_expire_due($connect, $limit = 500) {
        $out = ['expired_lots' => 0, 'expired_points' => 0];
        if (!points_v2_on($connect, 'v2_expire_job_enabled')) return $out;
        $limit = max(1, (int)$limit);
        $res = mysqli_query($connect, "SELECT id, member_id FROM gangnamunni_point_lots
            WHERE remaining > 0 AND expires_at IS NOT NULL AND expires_at < CURDATE() ORDER BY expires_at, id LIMIT $limit");
        if (!$res) return $out;
        $lots = [];
        while ($r = mysqli_fetch_assoc($res)) $lots[] = $r;
        foreach ($lots as $lot) {
            $r = points_txn($connect, (int)$lot['member_id'], 'expire', 0, '유효기간 만료 소멸', null, null, null, ['expire_lot' => (int)$lot['id']]);
            if (!empty($r['ok']) && empty($r['skipped'])) {
                $out['expired_lots']++;
                // 금액은 원장 행에서 확인
                $q = mysqli_prepare($connect, "SELECT amount FROM gangnamunni_points WHERE id = ?");
                $pid = (int)($r['point_id'] ?? 0);
                mysqli_stmt_bind_param($q, 'i', $pid);
                mysqli_stmt_execute($q);
                $out['expired_points'] += (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($q))['amount'] ?? 0);
                mysqli_stmt_close($q);
            }
        }
        return $out;
    }

    /**
     * 사용자/관리자 후기 삭제·거부 훅(v2 정책 ON 일 때만). mysqli 연결이 없으면(PDO 경로) 직접 연다.
     */
    function points_v2_on_review_removed($connect, $rid, $reason = '후기 삭제 회수') {
        try {
            $own = false;
            if (!$connect && defined('G5_MYSQL_HOST')) {
                $connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? (int)G5_MYSQL_PORT : 3306);
                if ($connect) { mysqli_set_charset($connect, 'utf8mb4'); mysqli_query($connect, "SET time_zone = '+09:00'"); $own = true; }
            }
            if (!$connect) return;
            if (points_v2_on($connect, 'v2_policy_enabled')) points_v2_review_revoke_all($connect, (int)$rid, $reason);
            if ($own) mysqli_close($connect);
        } catch (\Throwable $e) {
            error_log('[points_v2_on_review_removed] #' . (int)$rid . ': ' . $e->getMessage());
        }
    }
}
