<?php
/**
 * GET /api/ad_slot_tokens.php?booking_ids=1,2 — ad-slot tracking tokens for the Next.js list (JSON).
 *
 * The PHP list page signs a token per rendered ad card (adSlotIssueToken: booking + time + visitor IP,
 * HMAC with the PHP-only secret). The Next.js list renders the same pinned ad cards, so it asks here for
 * the tokens and then reports impressions/clicks to api_ad_slot_tracking.php exactly like footer.php.
 * Only bookings that are active today get a token; the secret never leaves PHP. Tokens are IP-bound and
 * expire (AD_SLOT_TOKEN_TTL), and the tracking endpoint keeps its duplicate / daily-IP limits.
 */
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');

ob_start();
try {
    require_once __DIR__ . '/../includes/init.php';
    require_once __DIR__ . '/../includes/ad_slot_performance.php';
} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(503);
    error_log('[ad_slot_tokens] init failed: ' . $e->getMessage());
    echo json_encode(['error' => 'unavailable']);
    exit;
}
ob_end_clean();
header('Content-Type: application/json; charset=UTF-8');

$ids = [];
foreach (explode(',', (string)($_GET['booking_ids'] ?? '')) as $v) {
    $n = (int)trim($v);
    if ($n > 0) $ids[$n] = $n;
}
$ids = array_slice(array_values($ids), 0, 20);

$tokens = [];
if ($ids && isset($connect) && $connect) {
    $list = implode(',', $ids);
    $r = @mysqli_query($connect, "SELECT id FROM gangnamunni_ad_slot_bookings
        WHERE id IN ({$list}) AND status = 'active' AND start_date <= CURDATE() AND (end_date IS NULL OR end_date >= CURDATE())");
    if ($r) {
        while ($row = mysqli_fetch_assoc($r)) {
            $t = adSlotIssueToken((int)$row['id']);
            if ($t !== '') $tokens[(string)$row['id']] = $t;
        }
    }
}
echo json_encode(['tokens' => (object)$tokens]);
