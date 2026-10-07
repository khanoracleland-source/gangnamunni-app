<?php

if (!defined('POINTS_LEDGER_LOADED')) {
    define('POINTS_LEDGER_LOADED', 1);
    define('POINTS_SCHEMA_VERSION', 3); // 스키마 목표 버전(v3: 유형별 적립 on/off 키 추가)

    /** 잔액을 증가시키는 유형(+), 그 외는 감소(-). 'admin' 은 과거 스키마 적립 유형(하위호환). */
    function points_is_credit($type) {
        return in_array($type, ['earn', 'refund', 'admin_add', 'admin'], true);
    }

   
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

    function points_enabled($connect) {
        return (int)points_setting($connect, 'enabled', '0') === 1;
    }

    function points_kind_enabled($connect, $kind) {
        if (!points_enabled($connect)) return false;
        return (int)points_setting($connect, $kind . '_enabled', '1') === 1;
    }

    function points_kind_enabled_safe($connect, $kind) {
        try { return points_kind_enabled($connect, $kind); }
        catch (\Throwable $e) { return false; }
    }


    function points_balance_safe($connect, $member_no) {
        try { return points_balance($connect, $member_no); }
        catch (\Throwable $e) { error_log('[points] balance unavailable: ' . $e->getMessage()); return 0; }
    }


    function points_enabled_safe($connect) {
        try { return points_enabled($connect); }
        catch (\Throwable $e) { return false; }
    }

    function points_setting_safe($connect, $key, $default = null) {
        try { return points_setting($connect, $key, $default); }
        catch (\Throwable $e) { return $default; }
    }

    function points_save_settings($connect, array $pairs) {
        points_ensure_schema($connect);
        foreach ($pairs as $k => $v) {
            $st = mysqli_prepare($connect, "INSERT INTO gangnamunni_point_settings (skey, sval) VALUES (?, ?)
                                            ON DUPLICATE KEY UPDATE sval = VALUES(sval)");
            if ($st) { $v = (string)$v; mysqli_stmt_bind_param($st, 'ss', $k, $v); mysqli_stmt_execute($st); mysqli_stmt_close($st); }
        }
    }

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


    function points_txn($connect, $member_no, $type, $amount, $reason = '', $ref_type = null, $ref_id = null, $expire_days = null, array $opts = []) {
        points_ensure_schema($connect);
        $member_no = (int)$member_no;
        $amount = (int)$amount;
        $valid_types = ['earn','use','refund','expire','admin_add','admin_sub','earn_cancel'];
        $expire_lot = (int)($opts['expire_lot'] ?? 0);
        if ($expire_lot > 0 && $type !== 'expire') $expire_lot = 0;
        if ($expire_lot > 0 && $amount <= 0) $amount = 1; // 실제 금액은 잠금 안에서 확정
        if ($member_no <= 0 || $amount <= 0 || !in_array($type, $valid_types, true)) {
            return ['ok' => false, 'balance' => 0, 'skipped' => false, 'error' => 'invalid_args'];
        }

      
        $lock = 'pts_' . $member_no;
        $lk = mysqli_prepare($connect, "SELECT GET_LOCK(?, 5)");
        $got = 0;
        if ($lk) { mysqli_stmt_bind_param($lk, 's', $lock); mysqli_stmt_execute($lk); $r=mysqli_stmt_get_result($lk); $got=(int)(mysqli_fetch_row($r)[0] ?? 0); mysqli_stmt_close($lk); }
        if ($got !== 1) {
            return ['ok' => false, 'balance' => points_balance($connect, $member_no), 'skipped' => false, 'error' => 'lock_failed'];
        }

        try {
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
            if ($expire_lot > 0) {
                $amount = points_lot_remaining($connect, $expire_lot, $member_no);
                if ($amount <= 0) return ['ok' => true, 'balance' => $cur, 'skipped' => true, 'error' => null];
            }
            if (in_array($type, ['use', 'admin_sub'], true) && $amount > $cur && empty($opts['allow_negative'])) {
                return ['ok' => false, 'balance' => $cur, 'skipped' => false, 'error' => 'insufficient'];
            }
            $delta = points_is_credit($type) ? $amount : -$amount;
            $new = $cur + $delta;

            $expires_at = null;
            if (points_is_credit($type)) {
                if ($expire_days === null) {
                    $expire_days = points_v2_on($connect, 'v2_policy_enabled')
                        ? (int)points_setting($connect, 'v2_expire_days', '90')
                        : (int)points_setting($connect, 'expire_days', '0');
                }
                if (!empty($opts['restore_from'])) $expire_days = 0; // 복구분은 원래 lot 의 기한을 따른다
                $expire_days = (int)$expire_days;
                if ($expire_days > 0) $expires_at = date('Y-m-d', strtotime("+{$expire_days} days"));
            }

            $ins = mysqli_prepare($connect, "INSERT INTO gangnamunni_points
                (member_id, type, amount, balance, reason, reference_type, reference_id, expires_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$ins) return ['ok' => false, 'balance' => $cur, 'skipped' => false, 'error' => 'prepare_failed'];
            $rid = $ref_id === null ? null : (int)$ref_id;
            mysqli_stmt_bind_param($ins, 'isiissss', $member_no, $type, $amount, $new, $reason, $ref_type, $rid, $expires_at);
            $ok = mysqli_stmt_execute($ins);
            $err = $ok ? null : mysqli_error($connect);
            $point_id = $ok ? (int)mysqli_insert_id($connect) : 0;
            mysqli_stmt_close($ins);

            if ($ok && $point_id > 0 && points_v2_schema_ready($connect)) {
                try {
                    points_lots_apply($connect, $member_no, $point_id, $type, $cur, $new, $expires_at, $opts);
                } catch (\Throwable $e) {
                    error_log('[points_lots_apply] member ' . $member_no . ' point ' . $point_id . ': ' . $e->getMessage());
                }
            }

            if (!$ok && $err && stripos($err, 'Duplicate') !== false) {
                return ['ok' => true, 'balance' => points_balance($connect, $member_no), 'skipped' => true, 'error' => null];
            }
            return ['ok' => (bool)$ok, 'balance' => $ok ? $new : $cur, 'skipped' => false, 'error' => $err, 'point_id' => $point_id];
        } finally {
            if ($got) {
                $ul = mysqli_prepare($connect, "SELECT RELEASE_LOCK(?)");
                if ($ul) { mysqli_stmt_bind_param($ul, 's', $lock); mysqli_stmt_execute($ul); mysqli_stmt_close($ul); }
            }
        }
    }



    function points_earn($connect, $member_no, $amount, $reason, $ref_type = null, $ref_id = null) {
        return points_txn($connect, $member_no, 'earn', $amount, $reason, $ref_type, $ref_id);
    }

    function points_earn_signup($connect, $member_no) {
        $member_no = (int)$member_no;
        if ($member_no <= 0 || !points_kind_enabled($connect, 'signup')) {
            return ['ok' => false, 'skipped' => true, 'balance' => 0, 'amount' => 0, 'error' => 'disabled_or_invalid'];
        }

        $amount = max(0, (int)points_setting($connect, 'signup_point', '1000'));
        if ($amount <= 0) {
            return ['ok' => false, 'skipped' => true, 'balance' => points_balance($connect, $member_no), 'amount' => 0, 'error' => 'zero_amount'];
        }

        $result = points_earn($connect, $member_no, $amount, '신규 가입 포인트 증정', 'signup', $member_no);
        $result['amount'] = $amount;
        return $result;
    }

    function points_use($connect, $member_no, $amount, $reason, $ref_type = null, $ref_id = null) {
        return points_txn($connect, $member_no, 'use', $amount, $reason, $ref_type, $ref_id);
    }

    function points_refund($connect, $member_no, $amount, $reason, $ref_type = null, $ref_id = null) {
        return points_txn($connect, $member_no, 'refund', $amount, $reason, $ref_type, $ref_id);
    }


    function points_earn_booking($connect, $member_no, $booking_id, $price) {
        if (!points_kind_enabled($connect, 'booking')) return ['ok' => false, 'skipped' => true, 'balance' => 0, 'error' => 'disabled'];
        $percent = (float)points_setting($connect, 'booking_percent', '1');
        $amount = (int)floor(((int)$price) * $percent / 100);
        if ($amount <= 0) return ['ok' => false, 'skipped' => true, 'balance' => 0, 'error' => 'zero_amount'];
        return points_earn($connect, $member_no, $amount, "예약 확정 적립 (" . rtrim(rtrim(number_format($percent, 2), '0'), '.') . "%)", 'booking', $booking_id);
    }

    function points_revoke_earn($connect, $ref_type, $ref_id, $reason = '적립 취소') {
        points_ensure_schema($connect);
        $ref_type = (string)$ref_type;
        $ref_id = (int)$ref_id;
        if ($ref_type === '' || $ref_id <= 0) return ['ok' => false, 'skipped' => true, 'balance' => 0, 'error' => 'invalid'];
        $st = mysqli_prepare($connect, "SELECT id, member_id, amount FROM gangnamunni_points WHERE reference_type=? AND reference_id=? AND type='earn' LIMIT 1");
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
        $opts = [];
        if (points_v2_schema_ready($connect)) {
            $lot = points_lot_by_point($connect, (int)$row['id']);
            if ($lot) $opts['lot_id'] = (int)$lot['id'];
        }
        return points_txn($connect, $member_no, 'earn_cancel', $amount, $reason, $ref_type, $ref_id, null, $opts);
    }

    function points_cancel_booking($connect, $member_no, $booking_id) {
        return points_revoke_earn($connect, 'booking', $booking_id, '예약 취소 회수');
    }


    function points_v2_schema_ready($connect) {
        try { return (int)points_setting($connect, 'v2_schema_version', '0') >= 1; }
        catch (\Throwable $e) { return false; }
    }


    function points_v2_on($connect, $key) {
        try {
            if (!points_v2_schema_ready($connect) || !points_enabled($connect)) return false;
            if ($key !== 'v2_policy_enabled' && (int)points_setting($connect, 'v2_policy_enabled', '0') !== 1) return false;
            if (in_array($key, ['v2_expire_job_enabled', 'v2_point_use_enabled'], true)
                && (int)points_setting($connect, 'lots_ready', '0') !== 1) return false;
            return (int)points_setting($connect, $key, '0') === 1;
        } catch (\Throwable $e) {
            return false;
        }
    }

    function points_v2_int($connect, $key, $default) {
        return (int)points_setting_safe($connect, $key, (string)$default);
    }


    function points_lot_by_point($connect, $point_id) {
        $st = mysqli_prepare($connect, "SELECT * FROM gangnamunni_point_lots WHERE point_id = ? LIMIT 1");
        if (!$st) return null;
        $point_id = (int)$point_id;
        mysqli_stmt_bind_param($st, 'i', $point_id);
        mysqli_stmt_execute($st);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st)) ?: null;
        mysqli_stmt_close($st);
        return $row;
    }

    function points_lot_remaining($connect, $lot_id, $member_no) {
        $st = mysqli_prepare($connect, "SELECT remaining FROM gangnamunni_point_lots WHERE id = ? AND member_id = ? LIMIT 1");
        if (!$st) return 0;
        $lot_id = (int)$lot_id; $member_no = (int)$member_no;
        mysqli_stmt_bind_param($st, 'ii', $lot_id, $member_no);
        mysqli_stmt_execute($st);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
        mysqli_stmt_close($st);
        return $row ? max(0, (int)$row['remaining']) : 0;
    }


    function points_lots_apply($connect, $member_no, $point_id, $type, $cur, $new, $expires_at, array $opts = []) {
        $member_no = (int)$member_no; $point_id = (int)$point_id;
        if (points_is_credit($type)) {
            $gain = max(0, (int)$new) - max(0, (int)$cur);
            $restore_from = (int)($opts['restore_from'] ?? 0);
            if ($restore_from > 0 && $gain > 0) {
                $st = mysqli_prepare($connect, "SELECT a.lot_id, a.amount FROM gangnamunni_point_lot_allocs a
                    JOIN gangnamunni_point_lots l ON l.id = a.lot_id
                    WHERE a.point_id = ? AND l.member_id = ?
                    ORDER BY (l.expires_at IS NULL), l.expires_at DESC, l.id DESC");
                mysqli_stmt_bind_param($st, 'ii', $restore_from, $member_no);
                mysqli_stmt_execute($st);
                $res = mysqli_stmt_get_result($st);
                $allocs = [];
                while ($a = mysqli_fetch_assoc($res)) $allocs[] = $a;
                mysqli_stmt_close($st);
                foreach ($allocs as $a) {
                    if ($gain <= 0) break;
                    $back = min($gain, (int)$a['amount']);
                    $lid = (int)$a['lot_id'];
                    $up = mysqli_prepare($connect, "UPDATE gangnamunni_point_lots SET remaining = remaining + ? WHERE id = ?");
                    mysqli_stmt_bind_param($up, 'ii', $back, $lid);
                    mysqli_stmt_execute($up);
                    mysqli_stmt_close($up);
                    $gain -= $back;
                }
                if ($gain <= 0) return;
            }
            $amount = abs((int)$new - (int)$cur);
            $ins = mysqli_prepare($connect, "INSERT INTO gangnamunni_point_lots (member_id, point_id, amount, remaining, expires_at) VALUES (?, ?, ?, ?, ?)");
            $exp = $restore_from > 0 ? null : $expires_at;
            mysqli_stmt_bind_param($ins, 'iiiis', $member_no, $point_id, $amount, $gain, $exp);
            mysqli_stmt_execute($ins);
            mysqli_stmt_close($ins);
            return;
        }

        $left = max(0, (int)$cur) - max(0, (int)$new);
        if ($left <= 0) return;
        $lots = [];
        $target = (int)($opts['lot_id'] ?? ($opts['expire_lot'] ?? 0));
        if ($target > 0) {
            $st = mysqli_prepare($connect, "SELECT id, remaining FROM gangnamunni_point_lots WHERE id = ? AND member_id = ? AND remaining > 0");
            mysqli_stmt_bind_param($st, 'ii', $target, $member_no);
            mysqli_stmt_execute($st);
            $r = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
            mysqli_stmt_close($st);
            if ($r) $lots[] = $r;
        }
        $st = mysqli_prepare($connect, "SELECT id, remaining FROM gangnamunni_point_lots
            WHERE member_id = ? AND remaining > 0 AND id <> ?
            ORDER BY (expires_at IS NULL), expires_at, id");
        mysqli_stmt_bind_param($st, 'ii', $member_no, $target);
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        while ($r = mysqli_fetch_assoc($res)) $lots[] = $r;
        mysqli_stmt_close($st);

        foreach ($lots as $lot) {
            if ($left <= 0) break;
            $take = min($left, (int)$lot['remaining']);
            $lid = (int)$lot['id'];
            $up = mysqli_prepare($connect, "UPDATE gangnamunni_point_lots SET remaining = remaining - ? WHERE id = ? AND remaining >= ?");
            mysqli_stmt_bind_param($up, 'iii', $take, $lid, $take);
            mysqli_stmt_execute($up);
            $changed = mysqli_stmt_affected_rows($up);
            mysqli_stmt_close($up);
            if ($changed !== 1) continue;
            $al = mysqli_prepare($connect, "INSERT INTO gangnamunni_point_lot_allocs (point_id, lot_id, amount) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($al, 'iii', $point_id, $lid, $take);
            mysqli_stmt_execute($al);
            mysqli_stmt_close($al);
            $left -= $take;
        }
        if ($left > 0) error_log("[points_lots_apply] member {$member_no}: {$left}P not covered by lots (run backfill)");
    }

    function points_lots_rebuild_member($connect, $member_no) {
        $member_no = (int)$member_no;
        if ($member_no <= 0) return false;
        $lock = 'pts_' . $member_no;
        $lk = mysqli_prepare($connect, "SELECT GET_LOCK(?, 10)");
        mysqli_stmt_bind_param($lk, 's', $lock); mysqli_stmt_execute($lk);
        $got = (int)(mysqli_fetch_row(mysqli_stmt_get_result($lk))[0] ?? 0); mysqli_stmt_close($lk);
        if ($got !== 1) return false;
        try {
            $d = mysqli_prepare($connect, "DELETE a FROM gangnamunni_point_lot_allocs a JOIN gangnamunni_point_lots l ON l.id = a.lot_id WHERE l.member_id = ?");
            mysqli_stmt_bind_param($d, 'i', $member_no); mysqli_stmt_execute($d); mysqli_stmt_close($d);
            $d = mysqli_prepare($connect, "DELETE FROM gangnamunni_point_lots WHERE member_id = ?");
            mysqli_stmt_bind_param($d, 'i', $member_no); mysqli_stmt_execute($d); mysqli_stmt_close($d);

            $st = mysqli_prepare($connect, "SELECT id, type, amount, reference_type, reference_id, expires_at FROM gangnamunni_points WHERE member_id = ? ORDER BY id");
            mysqli_stmt_bind_param($st, 'i', $member_no); mysqli_stmt_execute($st);
            $res = mysqli_stmt_get_result($st);
            $rows = [];
            while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
            mysqli_stmt_close($st);

            $bal = 0;
            $earn_point_by_ref = [];
            $use_point_by_ref = [];
            foreach ($rows as $r) {
                $amt = (int)$r['amount'];
                $new = points_is_credit($r['type']) ? $bal + $amt : $bal - $amt;
                $opts = [];
                if ($r['type'] === 'earn_cancel') {
                    $key = $r['reference_type'] . ':' . $r['reference_id'];
                    if (isset($earn_point_by_ref[$key])) {
                        $lot = points_lot_by_point($connect, $earn_point_by_ref[$key]);
                        if ($lot) $opts['lot_id'] = (int)$lot['id'];
                    }
                } elseif ($r['type'] === 'refund' && $r['reference_type'] !== null) {
                    $key = $r['reference_type'] . ':' . $r['reference_id'];
                    if (isset($use_point_by_ref[$key])) $opts['restore_from'] = $use_point_by_ref[$key];
                }
                points_lots_apply($connect, $member_no, (int)$r['id'], $r['type'], $bal, $new, $r['expires_at'], $opts);
                if ($r['type'] === 'earn' && $r['reference_type'] !== null) {
                    $earn_point_by_ref[$r['reference_type'] . ':' . $r['reference_id']] = (int)$r['id'];
                }
                if ($r['type'] === 'use' && $r['reference_type'] !== null) {
                    $use_point_by_ref[$r['reference_type'] . ':' . $r['reference_id']] = (int)$r['id'];
                }
                $bal = $new;
            }
            return true;
        } finally {
            $ul = mysqli_prepare($connect, "SELECT RELEASE_LOCK(?)");
            mysqli_stmt_bind_param($ul, 's', $lock); mysqli_stmt_execute($ul); mysqli_stmt_close($ul);
        }
    }

    function points_next_expiry($connect, $member_no) {
        if (!points_v2_schema_ready($connect) || (int)points_setting_safe($connect, 'lots_ready', '0') !== 1) return null;
        $member_no = (int)$member_no;
        $st = mysqli_prepare($connect, "SELECT expires_at, SUM(remaining) AS amt FROM gangnamunni_point_lots
            WHERE member_id = ? AND remaining > 0 AND expires_at IS NOT NULL AND expires_at >= CURDATE()
            GROUP BY expires_at ORDER BY expires_at LIMIT 1");
        if (!$st) return null;
        mysqli_stmt_bind_param($st, 'i', $member_no);
        mysqli_stmt_execute($st);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
        mysqli_stmt_close($st);
        return $row ? ['date' => $row['expires_at'], 'amount' => (int)$row['amt']] : ['date' => null, 'amount' => 0];
    }
}
