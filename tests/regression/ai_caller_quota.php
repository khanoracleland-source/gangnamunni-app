<?php
// No DB and no AI provider needed — this exercises the caller-side quota that
// guards every member-reachable AI endpoint (api/ai_suggest.php).
//
//   php tests/regression/ai_caller_quota.php
//
// What it protects: callAI() applies cost control only when a hospital_id is
// passed (hospital wallet). An endpoint that calls it without one — which is
// every member-facing one — spends the platform's provider keys with no cap.
// aiCheckCallerQuota() is what bounds that, so its arithmetic and its
// "check everything before recording anything" property are load-bearing.
namespace AiCallerQuotaRegression;

$tmp = sys_get_temp_dir() . '/ai_quota_test_' . bin2hex(random_bytes(6));
@mkdir($tmp . '/cache', 0755, true);
define('G5_DATA_PATH', $tmp);

// ai_proxy.php opens its own DB handle at include time when $connect is empty,
// with @mysqli_connect() — which under PHP 8.1+ default error mode throws
// instead of returning false. The quota helpers need no database, so turn
// mysqli reporting off for the include and let that connect fail quietly.
\mysqli_report(MYSQLI_REPORT_OFF);
$connect = null;
require_once __DIR__ . '/../../hairwang_full/gangnamunni/includes/ai_proxy.php';

/**
 * @param mixed $condition
 * @param mixed $message
 */
function check($condition, $message) {
    if (!$condition) throw new \RuntimeException("FAIL: $message");
    echo "ok - $message\n";
}

function allowed_run(string $caller, bool $isAdmin, int $attempts): int {
    $ok = 0;
    for ($i = 0; $i < $attempts; $i++) {
        if (\aiCheckCallerQuota($caller, $isAdmin)['allowed']) $ok++;
    }
    return $ok;
}

try {
    check(function_exists('aiRateHit') && function_exists('aiCheckCallerQuota'),
        'the quota helpers are defined in ai_proxy.php');

    // ── aiRateHit semantics ─────────────────────────────────────────────
    $k = 'unit_' . bin2hex(random_bytes(4));
    for ($i = 0; $i < 3; $i++) {
        check(\aiRateHit('t', $k, 3, 60)['allowed'] === true, 'aiRateHit allows call ' . ($i + 1) . ' of 3');
    }
    $blocked = \aiRateHit('t', $k, 3, 60);
    check($blocked['allowed'] === false, 'aiRateHit blocks the 4th call in the window');
    check($blocked['retry'] > 0 && $blocked['retry'] <= 60, 'a blocked call reports a retry delay inside the window');

    // record=false must not consume budget
    $k2 = 'unit_' . bin2hex(random_bytes(4));
    for ($i = 0; $i < 5; $i++) \aiRateHit('t', $k2, 2, 60, false);
    check(\aiRateHit('t', $k2, 2, 60)['allowed'] === true, 'record=false probes do not consume the allowance');

    // ── member burst limit: 10 per minute ───────────────────────────────
    $_SERVER['REMOTE_ADDR'] = '203.0.113.' . random_int(1, 254);
    $memberA = 'member:a_' . bin2hex(random_bytes(4));
    check(allowed_run($memberA, false, 10) === 10, 'a member gets 10 AI calls per minute');
    $over = \aiCheckCallerQuota($memberA, false);
    check($over['allowed'] === false, 'the member\'s 11th call in the same minute is refused');
    check($over['reason'] === 'burst', 'the refusal names the per-caller burst limit');

    // ── counters are per caller ─────────────────────────────────────────
    $memberB = 'member:b_' . bin2hex(random_bytes(4));
    check(allowed_run($memberB, false, 10) === 10, 'a different member has its own allowance');

    // ── the shared per-IP ceiling is exactly 30, which also proves that a
    //    refused call does not advance the other counters (otherwise memberA's
    //    11th attempt above would have eaten one of the 30) ───────────────
    $memberC = 'member:c_' . bin2hex(random_bytes(4));
    $cAllowed = allowed_run($memberC, false, 10);
    check($cAllowed === 10, 'a third member from the same IP still reaches the IP ceiling exactly (30 total)');
    $memberD = 'member:d_' . bin2hex(random_bytes(4));
    $ipBlocked = \aiCheckCallerQuota($memberD, false);
    check($ipBlocked['allowed'] === false, 'a fresh member from that IP is refused once the IP ceiling is reached');
    check($ipBlocked['reason'] === 'ip_burst', 'the refusal names the per-IP limit, not the per-caller one');

    // ── admins get a higher per-caller limit ────────────────────────────
    $_SERVER['REMOTE_ADDR'] = '198.51.100.' . random_int(1, 254);
    $admin = 'admin:' . bin2hex(random_bytes(4));
    check(allowed_run($admin, true, 30) === 30, 'an admin caller is not stopped at the member limit of 10');

    // ── a missing REMOTE_ADDR must not disable the caller limit ─────────
    unset($_SERVER['REMOTE_ADDR']);
    $memberE = 'member:e_' . bin2hex(random_bytes(4));
    check(allowed_run($memberE, false, 10) === 10, 'without a client IP the per-caller limit still applies');
    check(\aiCheckCallerQuota($memberE, false)['allowed'] === false,
        'and it still refuses the 11th call (IP checks are skipped, not the caller ones)');

    echo "\nALL PASS\n";
} finally {
    foreach (glob($tmp . '/cache/ai_rate/*.json') ?: [] as $f) @unlink($f);
    @rmdir($tmp . '/cache/ai_rate');
    @rmdir($tmp . '/cache');
    @rmdir($tmp);
}
