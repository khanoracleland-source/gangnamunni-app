<?php
/**
 * [05-17] 노쇼·잦은 취소로 인한 예약 제한 정책.
 *
 * 최근 N개월 동안 "방문하지 않았거나(no_show) 회원이 직접 취소한(환불 포함)" 예약이 M회 이상이면 새 예약을 막는다.
 * 기준은 사이트 설정(noshow_block_count / noshow_block_months / noshow_count_cancel)이며 횟수 0 이면 사용하지 않는다.
 * 기본 3회 / 3개월 / 취소 포함. 예약 일정 변경은 취소로 세지 않는다.
 * 취소는 cancelled_by='member' 로 기록된 것만 센다(병원·관리자 취소와 결제 미완료 자동 취소는 제외).
 * 컬럼은 sql/booking_cancelled_by_20261021.sql 로 추가한다.
 */

if (!function_exists('booking_noshow_collect')) {

    /** 설정과 최근 기록을 읽는다. 사용하지 않으면 null. */
    function booking_noshow_collect($connect, $mb_id) {
        if ($mb_id === '') return null;
        $limit = 3; $months = 3; $with_cancel = true;
        if (function_exists('getSettingValue')) {
            $limit = (int)getSettingValue($connect, 'noshow_block_count', '3');
            $months = (int)getSettingValue($connect, 'noshow_block_months', '3');
            $with_cancel = getSettingValue($connect, 'noshow_count_cancel', '1') === '1';
        }
        if ($limit <= 0 || $months <= 0) return null;
        $limit = min($limit, 50); $months = min($months, 36);
        if ($with_cancel) {
            $col = @mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_bookings LIKE 'cancelled_by'");
            if (!$col || mysqli_num_rows($col) === 0) $with_cancel = false;
        }
        $cond = "status = 'no_show'" . ($with_cancel
            ? " OR (status IN ('cancelled','refunded') AND cancelled_by = 'member' AND cancelled_at IS NOT NULL)" : '');
        $stmt = mysqli_prepare($connect,
            "SELECT booking_date, booking_time, hospital_name, status,
                    IF(status = 'no_show', booking_date, DATE(cancelled_at)) AS ev
             FROM gangnamunni_bookings
             WHERE mb_id = ? AND ({$cond})
             HAVING ev <= CURDATE() AND ev > DATE_SUB(CURDATE(), INTERVAL ? MONTH)
             ORDER BY ev DESC, booking_time DESC LIMIT 50");
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, 'si', $mb_id, $months);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $rows = [];
        while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
        mysqli_stmt_close($stmt);
        return ['limit' => $limit, 'months' => $months, 'with_cancel' => $with_cancel, 'rows' => $rows];
    }

    /** N번째로 최근인 기록이 기간 밖으로 나가는 날(=다시 예약할 수 있는 날). */
    function booking_noshow_available_from($pivot_date, $months) {
        $pivot = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$pivot_date);
        if (!$pivot) return null;
        $avail = $pivot->modify('+' . (int)$months . ' months');
        // 말일 보정: 11월 30일 + 3개월 = 2월 30일(없음) 이면 SQL 기간 계산과 같게 3월 1일로 맞춘다.
        if ((int)$avail->format('j') < (int)$pivot->format('j')) {
            $avail = $pivot->modify('last day of +' . (int)$months . ' months')->modify('+1 day');
        }
        return $avail;
    }

    /** 예약 제한 여부. null(제한 없음) 또는 안내 시트에 표시할 정보. */
    function booking_noshow_status($connect, $mb_id) {
        $c = booking_noshow_collect($connect, $mb_id);
        if (!$c || count($c['rows']) < $c['limit']) return null;
        $rows = $c['rows'];
        $avail = booking_noshow_available_from($rows[$c['limit'] - 1]['ev'], $c['months']);
        if (!$avail) return null;
        $wd = ['일', '월', '화', '수', '목', '금', '토'];
        $items = []; $has_cancel = false;
        foreach (array_slice($rows, 0, 5) as $r) {
            $d = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$r['booking_date']);
            if (!$d) continue;
            $kind = $r['status'] === 'no_show' ? 'no_show' : 'cancel';
            if ($kind === 'cancel') $has_cancel = true;
            $items[] = ['when' => $d->format('m.d') . ' (' . $wd[(int)$d->format('w')] . ') ' . substr((string)$r['booking_time'], 0, 5),
                        'hospital' => (string)$r['hospital_name'], 'kind' => $kind];
        }
        return ['count' => count($rows), 'months' => $c['months'], 'available_from' => $avail->format('n월 j일'),
                'has_cancel' => $has_cancel || $c['with_cancel'], 'items' => $items];
    }

    /**
     * 회원이 지금 예약을 취소하려 할 때의 사전 안내.
     * 취소가 집계되지 않거나 아직 먼 경우 null. will_block=true 면 이번 취소로 예약이 제한된다.
     */
    function booking_noshow_cancel_preview($connect, $mb_id) {
        $c = booking_noshow_collect($connect, $mb_id);
        if (!$c || !$c['with_cancel']) return null;
        $limit = $c['limit'];
        $after = count($c['rows']) + 1;           // 이번 취소를 더한 횟수
        if ($limit < 2 || $after < $limit - 1) return null;   // 한 번 더 남았을 때부터 미리 알려준다
        $out = ['limit' => $limit, 'months' => $c['months'], 'before' => count($c['rows']), 'after' => $after,
                'will_block' => $after >= $limit, 'available_from' => ''];
        if ($out['will_block']) {
            $events = array_merge([['ev' => date('Y-m-d')]], $c['rows']);   // 오늘 취소가 가장 최근 기록
            $avail = booking_noshow_available_from($events[$limit - 1]['ev'], $c['months']);
            if (!$avail) return null;
            $out['available_from'] = $avail->format('n월 j일');
        }
        return $out;
    }
}
