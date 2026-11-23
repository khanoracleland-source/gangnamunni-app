<?php
// 결제 수수료율 표(gangnamunni_payment_fee_rates)를 읽고 쓰는 함수. 쓰기는 최고관리자 화면에서만 부른다(권한·CSRF 는 부르는 쪽).
// 행은 추가만 하고, 아직 적용 전(effective_from > 오늘)인 행만 지울 수 있다: 이미 적용된 요율은 이전 결제의 계산 근거라 그대로 둔다.
require_once __DIR__ . '/payment_fee.php';

if (!function_exists('platform_payment_fee_rates_list')) {
    /** @return array<int,array<string,mixed>> newest start first */
    function platform_payment_fee_rates_list(mysqli $db): array {
        $rows = [];
        try {
            $res = mysqli_query($db, "SELECT id,pay_method,rate_bp,effective_from,created_by_name,created_at
                                      FROM gangnamunni_payment_fee_rates ORDER BY effective_from DESC,id DESC LIMIT 200");
            while ($res && $row = mysqli_fetch_assoc($res)) $rows[] = $row;
        } catch (Throwable $e) {
            error_log('[payment_fee_rates] list failed: ' . $e->getMessage());
        }
        return $rows;
    }

    /** Adds one rate. Returns null on success or an error key: method|rate|date|not_after_latest|must_be_future|duplicate|db. */
    function platform_payment_fee_rate_add(mysqli $db, int $admin_id, string $admin_name, string $method, int $bp, string $from, string $today): ?string {
        if (!in_array($method, ['card', 'bank_transfer'], true)) return 'method';
        if ($bp < 0 || $bp > 10000) return 'rate';
        $limit = date('Y-m-d', strtotime($today . ' +90 days'));
        if (!platform_payment_valid_date($from) || $from < '2020-01-01' || $from > $limit) return 'date';
        $name = mb_substr($admin_name, 0, 191);
        $began = false;
        try {
            mysqli_begin_transaction($db);
            $began = true;
            $st = mysqli_prepare($db, "SELECT effective_from FROM gangnamunni_payment_fee_rates WHERE pay_method=? ORDER BY effective_from DESC LIMIT 1 FOR UPDATE");
            mysqli_stmt_bind_param($st, 's', $method);
            mysqli_stmt_execute($st);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
            mysqli_stmt_close($st);
            $latest = $row ? substr((string)$row['effective_from'], 0, 10) : null;
            $why = platform_payment_fee_order_error($latest, $from, $today);
            if ($why !== null) { mysqli_rollback($db); return $why; }
            $ins = mysqli_prepare($db, "INSERT INTO gangnamunni_payment_fee_rates (pay_method,rate_bp,effective_from,created_by,created_by_name) VALUES (?,?,?,?,?)");
            mysqli_stmt_bind_param($ins, 'sisis', $method, $bp, $from, $admin_id, $name);
            $ok = mysqli_stmt_execute($ins);
            $errno = mysqli_stmt_errno($ins);
            mysqli_stmt_close($ins);
            if (!$ok) { mysqli_rollback($db); return $errno === 1062 ? 'duplicate' : 'db'; }
            mysqli_commit($db);
            error_log("[payment_fee_rates] added: admin={$admin_id} method={$method} rate_bp={$bp} from={$from}");
            return null;
        } catch (Throwable $e) {
            if ($began) @mysqli_rollback($db);
            error_log('[payment_fee_rates] add failed: ' . $e->getMessage());
            return ((int)$e->getCode() === 1062) ? 'duplicate' : 'db';
        }
    }

    /** Removes a rate that has not come into force yet. Returns null on success or an error key: not_found|in_force|db. */
    function platform_payment_fee_rate_remove(mysqli $db, int $admin_id, int $id, string $today): ?string {
        if ($id <= 0) return 'not_found';
        $began = false;
        try {
            mysqli_begin_transaction($db);
            $began = true;
            $st = mysqli_prepare($db, "SELECT pay_method,rate_bp,effective_from FROM gangnamunni_payment_fee_rates WHERE id=? FOR UPDATE");
            mysqli_stmt_bind_param($st, 'i', $id);
            mysqli_stmt_execute($st);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
            mysqli_stmt_close($st);
            if (!$row) { mysqli_rollback($db); return 'not_found'; }
            $from = substr((string)$row['effective_from'], 0, 10);
            if (!($from > $today)) { mysqli_rollback($db); return 'in_force'; }
            $del = mysqli_prepare($db, "DELETE FROM gangnamunni_payment_fee_rates WHERE id=? AND effective_from>?");
            mysqli_stmt_bind_param($del, 'is', $id, $today);
            $ok = mysqli_stmt_execute($del);
            $affected = mysqli_stmt_affected_rows($del);
            mysqli_stmt_close($del);
            if (!$ok || $affected !== 1) { mysqli_rollback($db); return $ok ? 'in_force' : 'db'; }
            mysqli_commit($db);
            error_log("[payment_fee_rates] removed: admin={$admin_id} id={$id} method={$row['pay_method']} rate_bp={$row['rate_bp']} from={$from}");
            return null;
        } catch (Throwable $e) {
            if ($began) @mysqli_rollback($db);
            error_log('[payment_fee_rates] remove failed: ' . $e->getMessage());
            return 'db';
        }
    }
}
