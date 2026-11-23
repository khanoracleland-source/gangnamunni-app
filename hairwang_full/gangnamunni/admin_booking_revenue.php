<?php
/**
 * Patient booking payment revenue.
 *
 * This page intentionally reads the immutable payment ledger, not booking.price.
 * The future PG callback must write a verified payment into that ledger.
 */
session_start();
$admin_page_title = '예약 결제 매출';
$admin_current = 'booking_revenue';

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/hospital_filter.php';
require_once __DIR__ . '/includes/booking_payment_config.php';
require_admin_login();
require_permission('booking_revenue');
require_once __DIR__ . '/includes/payment_fee_rates.php';
$today = (new DateTime('now', new DateTimeZone('Asia/Seoul')))->format('Y-m-d');
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$is_super = (($_SESSION['ad_admin_role'] ?? '') === 'super_admin');
$scope_ids = null;
if (!$is_super) {
    // Financial data uses the explicit admin-to-hospital assignment only.
    // Unlike general content pages, email matching/multi-hospital discovery must
    // never broaden payment visibility.
    $assigned_hospital_id = (int)($_SESSION['ad_admin_hospital_id'] ?? 0);
    $scope_ids = $assigned_hospital_id > 0 ? [$assigned_hospital_id] : [];
}

function revenue_valid_date($value) {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return false;
    [$year, $month, $day] = array_map('intval', explode('-', $value));
    return checkdate($month, $day, $year);
}

function revenue_query($db, $sql, $types = '', array $params = []) {
    $stmt = mysqli_prepare($db, $sql);
    if (!$stmt) {
        error_log('[admin_booking_revenue] ' . mysqli_error($db));
        return [];
    }
    if ($types !== '') mysqli_stmt_bind_param($stmt, $types, ...$params);
    if (!mysqli_stmt_execute($stmt)) {
        error_log('[admin_booking_revenue] ' . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return [];
    }
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($result && $row = mysqli_fetch_assoc($result)) $rows[] = $row;
    mysqli_stmt_close($stmt);
    return $rows;
}

function revenue_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** Sums of the settlement figures of ledger rows (see platform_payment_figures()). */
function revenue_fig_zero() {
    return ['payments' => 0, 'paid_net' => 0, 'fee' => 0, 'settlement' => 0, 'counted' => 0, 'received' => 0, 'unrecorded' => 0];
}
function revenue_fig_add(array &$acc, array $f) {
    if (!in_array($f['state'], ['counted', 'received'], true)) return;
    $acc['payments']++;
    $acc['paid_net'] += $f['paid_net'];
    if ($f['settlement'] === null) { $acc['unrecorded']++; return; }
    $acc['fee'] += $f['fee'];
    $acc['settlement'] += $f['settlement'];
    $acc[$f['state']] += $f['settlement'];
}

function revenue_mask_phone($phone) {
    $digits = preg_replace('/\D+/', '', (string)$phone);
    return strlen($digits) >= 7 ? substr($digits, 0, 3) . '-****-' . substr($digits, -4) : '-';
}

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-t');
if (!revenue_valid_date($from)) $from = date('Y-m-01');
if (!revenue_valid_date($to)) $to = date('Y-m-t');
if ($from > $to) [$from, $to] = [$to, $from];
$sort = $_GET['sort'] ?? 'payment_desc';
$sort_options = [
    'payment_desc' => ['순 결제액 높은 순', 'net DESC, bookings DESC, hospital_name ASC'],
    'booking_desc' => ['예약 많은 순', 'bookings DESC, net DESC, hospital_name ASC'],
    'name_asc' => ['병원명 가나다순', 'hospital_name ASC'],
    'latest_payment' => ['최근 결제 순', 'last_paid_at DESC, hospital_name ASC'],
];
if (!isset($sort_options[$sort])) $sort = 'payment_desc';
$order_sql = $sort_options[$sort][1];

$requested_hospital = max(0, (int)($_GET['hospital_id'] ?? 0));
$forbidden = false;
if (!$is_super) {
    if (!$scope_ids) {
        $selected_hospital = 0;
    } elseif ($requested_hospital && !in_array($requested_hospital, $scope_ids, true)) {
        $selected_hospital = $scope_ids[0];
        $forbidden = true;
    } else {
        // Hospital administrators go directly to their own report.
        $selected_hospital = $requested_hospital ?: $scope_ids[0];
    }
} else {
    $selected_hospital = $requested_hospital;
}

$table_exists = !empty(revenue_query(
    $connect,
    "SELECT 1 FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gangnamunni_platform_payments' LIMIT 1"
));

// The fee / method columns come from sql/platform_payments_pay_method.sql, sql/platform_payments_fee.sql (and points_v2.sql for points_used):
// until they are installed the page keeps working and simply shows no settlement figures.
$ledger_cols = [];
if ($table_exists) {
    foreach (revenue_query($connect,
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gangnamunni_platform_payments'
           AND COLUMN_NAME IN ('pay_method','fee_rate_bp','fee_amount','points_used')") as $col_row) {
        $ledger_cols[$col_row['COLUMN_NAME']] = true;
    }
}
$fee_ready = isset($ledger_cols['pay_method'], $ledger_cols['fee_rate_bp'], $ledger_cols['fee_amount']);
$rates_table_exists = !empty(revenue_query($connect,
    "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gangnamunni_payment_fee_rates' LIMIT 1"));
$fee_rates = $rates_table_exists && $is_super ? platform_payment_fee_rates_list($connect) : [];
// Setting the rates: super admin only, and only while the server switch G5_PAYMENT_FEES_ADMIN=1 (default off).
$fee_admin = $is_super && $rates_table_exists && booking_payment_setting('G5_PAYMENT_FEES_ADMIN', '0') === '1';
$fee_messages = [
    'method' => '결제 수단이 올바르지 않습니다.',
    'rate' => '요율은 0~100 사이, 소수점 둘째 자리까지 입력하세요. (예: 1.43)',
    'date' => '적용 시작일이 올바르지 않습니다. (2020-01-01 ~ 오늘부터 90일 이내)',
    'not_after_latest' => '이 수단의 마지막 적용 시작일보다 늦은 날짜여야 합니다.',
    'must_be_future' => '이미 요율이 있는 수단은 내일부터 적용되는 날짜만 입력할 수 있습니다.',
    'duplicate' => '같은 날짜의 요율이 이미 있습니다.',
    'in_force' => '이미 적용 중이거나 적용된 요율은 지울 수 없습니다.',
    'not_found' => '요율을 찾을 수 없습니다.',
    'db' => '저장하지 못했습니다. 잠시 후 다시 시도하세요.',
];
$fee_flash = $_SESSION['revenue_fee_flash'] ?? null;
unset($_SESSION['revenue_fee_flash']);
if ($fee_admin && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $csrf_ok = ($_SESSION['csrf_token'] ?? '') !== '' && hash_equals((string)$_SESSION['csrf_token'], (string)($_POST['csrf_token'] ?? ''));
    if (!$csrf_ok) {
        http_response_code(403);
        exit('페이지를 새로고침해주세요.');
    }
    $fee_action = (string)($_POST['fee_action'] ?? '');
    $error = 'db';
    if ($fee_action === 'add') {
        $bp = platform_payment_fee_percent_to_bp((string)($_POST['rate_percent'] ?? ''));
        $error = $bp === null ? 'rate' : platform_payment_fee_rate_add(
            $connect, (int)($_SESSION['ad_admin_id'] ?? 0), (string)($_SESSION['ad_admin_name'] ?? ''),
            (string)($_POST['pay_method'] ?? ''), $bp, (string)($_POST['effective_from'] ?? ''), $today);
    } elseif ($fee_action === 'delete') {
        $error = platform_payment_fee_rate_remove($connect, (int)($_SESSION['ad_admin_id'] ?? 0), (int)($_POST['rate_id'] ?? 0), $today);
    }
    $_SESSION['revenue_fee_flash'] = $error === null
        ? ['ok', $fee_action === 'delete' ? '요율을 지웠습니다.' : '요율을 저장했습니다.']
        : ['error', $fee_messages[$error] ?? $fee_messages['db']];
    $back = [];
    foreach (['from', 'to', 'hospital_id', 'sort'] as $back_key) {
        if (isset($_GET[$back_key]) && is_string($_GET[$back_key]) && $_GET[$back_key] !== '') $back[$back_key] = $_GET[$back_key];
    }
    header('Location: admin_booking_revenue.php' . ($back ? '?' . http_build_query($back) : ''));
    exit;
}

$hospitals = [];
$details = [];
$fig_by_id = [];
$fig_by_hospital = [];
$fig_by_method = [];
$fig_by_payout = [];
$fig_total = revenue_fig_zero();
$fig_held = ['count' => 0, 'amount' => 0];
$treatments = [];
$totals = ['bookings' => 0, 'payments' => 0, 'cancelled_payment_amount' => 0, 'patients' => 0, 'gross' => 0, 'refunded' => 0, 'net' => 0];
$booking_statuses = [];
$hospital_name = '';
$period_end = date('Y-m-t', strtotime($to));

if ($table_exists && ($is_super || !empty($scope_ids))) {
    $base_where = "p.payment_type='booking' AND p.funds_recipient='hospital' AND p.provider NOT IN (" . booking_payment_sql_list(booking_payment_test_providers()) . ")
                   AND p.status IN ('paid','partially_refunded','refunded')
                   AND p.paid_at >= ? AND p.paid_at < DATE_ADD(?, INTERVAL 1 DAY)";
    $base_types = 'ss';
    $base_params = [$from, $to];

    if ($selected_hospital > 0) {
        $where = $base_where . ' AND p.hospital_id = ?';
        $types = $base_types . 'i';
        $params = array_merge($base_params, [$selected_hospital]);

        $details = revenue_query($connect,
            "SELECT p.id, p.booking_id, p.patient_name, p.patient_phone, p.treatment_name,
                    p.amount, p.refunded_amount, (p.amount-p.refunded_amount) AS net_amount,
                    p.currency, p.provider, p.provider_transaction_id, p.status, p.paid_at,
                    b.booking_date, b.booking_time
             FROM gangnamunni_platform_payments p
             LEFT JOIN gangnamunni_bookings b ON b.id=p.booking_id
             WHERE {$where}
             ORDER BY p.paid_at DESC, p.id DESC LIMIT 500",
            $types, $params);

        $treatments = revenue_query($connect,
            "SELECT p.treatment_name, COUNT(*) AS payments,
                    SUM(p.amount-p.refunded_amount) AS net_amount
             FROM gangnamunni_platform_payments p
             WHERE {$where}
             GROUP BY p.treatment_name ORDER BY net_amount DESC",
            $types, $params);

        $sum = revenue_query($connect,
            "SELECT COUNT(*) AS payments,
                    COALESCE(SUM(CASE WHEN p.status='refunded' THEN p.amount ELSE 0 END),0) AS cancelled_payment_amount,
                    COUNT(DISTINCT COALESCE(NULLIF(p.patient_mb_id,''), CONCAT('phone:',p.patient_phone))) AS patients,
                    COALESCE(SUM(p.amount),0) AS gross,
                    COALESCE(SUM(p.refunded_amount),0) AS refunded,
                    COALESCE(SUM(p.amount-p.refunded_amount),0) AS net
             FROM gangnamunni_platform_payments p WHERE {$where}",
            $types, $params);
        if ($sum) $totals = array_map('intval', $sum[0]);

        $name = revenue_query($connect, 'SELECT name FROM gangnamunni_hospitals WHERE id=? LIMIT 1', 'i', [$selected_hospital]);
        $hospital_name = $name[0]['name'] ?? ('#' . $selected_hospital);
    }

    // Settlement figures: every ledger row of the period (not only the 500 shown), counted by PHP with one rule (includes/payment_fee.php).
    $col = function ($name) use ($ledger_cols) { return isset($ledger_cols[$name]) ? 'p.' . $name : 'NULL'; };
    $fig_select = 'p.id,p.hospital_id,p.status,p.amount,p.refunded_amount,' . $col('points_used') . ' AS points_used,' . $col('pay_method') . ' AS pay_method,'
        . $col('fee_rate_bp') . ' AS fee_rate_bp,' . $col('fee_amount') . ' AS fee_amount,b.status AS booking_status,b.booking_date';
    $fig_where = $base_where . ($selected_hospital > 0 ? ' AND p.hospital_id = ?' : '');
    $fig_types = $base_types . ($selected_hospital > 0 ? 'i' : '');
    $fig_params = $selected_hospital > 0 ? array_merge($base_params, [$selected_hospital]) : $base_params;
    $fig_rows = revenue_query($connect,
        "SELECT {$fig_select} FROM gangnamunni_platform_payments p
         LEFT JOIN gangnamunni_bookings b ON b.id=p.booking_id WHERE {$fig_where}",
        $fig_types, $fig_params);
    foreach ($fig_rows as $fig_row) {
        $f = platform_payment_figures($fig_row, $today);
        $hid = (int)$fig_row['hospital_id'];
        if ($selected_hospital > 0) $fig_by_id[(int)$fig_row['id']] = ['pay_method' => $fig_row['pay_method'], 'f' => $f];
        revenue_fig_add($fig_total, $f);
        if (!isset($fig_by_hospital[$hid])) $fig_by_hospital[$hid] = revenue_fig_zero();
        revenue_fig_add($fig_by_hospital[$hid], $f);
        if ($selected_hospital > 0) {
            $mkey = (string)($fig_row['pay_method'] ?? '') !== '' ? (string)$fig_row['pay_method'] : 'unknown';
            if (!isset($fig_by_method[$mkey])) $fig_by_method[$mkey] = revenue_fig_zero();
            revenue_fig_add($fig_by_method[$mkey], $f);
            if (in_array($f['state'], ['counted', 'received'], true)) {
                $wkey = $f['payout_date'] ?? '';
                if (!isset($fig_by_payout[$wkey])) $fig_by_payout[$wkey] = revenue_fig_zero();
                revenue_fig_add($fig_by_payout[$wkey], $f);
            }
        }
    }
    // Held = ordered but not paid yet: a current stock, not bound to the period. No fee is counted for it.
    $held_rows = revenue_query($connect,
        "SELECT COUNT(*) AS c, COALESCE(SUM(p.amount),0) AS s FROM gangnamunni_platform_payments p
         WHERE p.payment_type='booking' AND p.funds_recipient='hospital' AND p.status='pending'
           AND (p.provider IS NULL OR p.provider NOT IN (" . booking_payment_sql_list(booking_payment_test_providers()) . "))" . ($selected_hospital > 0 ? ' AND p.hospital_id = ?' : ''),
        $selected_hospital > 0 ? 'i' : '', $selected_hospital > 0 ? [$selected_hospital] : []);
    if ($held_rows) $fig_held = ['count' => (int)$held_rows[0]['c'], 'amount' => (int)$held_rows[0]['s']];
}

// Reservation statistics exist independently from payment integration.
if ($selected_hospital > 0 && ($is_super || in_array($selected_hospital, $scope_ids ?: [], true))) {
    $booking_statuses = revenue_query($connect,
        "SELECT status,COUNT(*) AS cnt FROM gangnamunni_bookings
         WHERE hospital_id=? AND booking_date>=? AND booking_date<DATE_ADD(?,INTERVAL 1 DAY)
         GROUP BY status ORDER BY cnt DESC",
        'iss', [$selected_hospital, $from, $to]);
    foreach ($booking_statuses as $row) $totals['bookings'] += (int)$row['cnt'];
    if ($hospital_name === '') {
        $name = revenue_query($connect, 'SELECT name FROM gangnamunni_hospitals WHERE id=? LIMIT 1', 'i', [$selected_hospital]);
        $hospital_name = $name[0]['name'] ?? ('#' . $selected_hospital);
    }
}

// Superadmin list always starts from hospitals, so hospitals with zero bookings or
// zero payments are still visible. Separate aggregates prevent join multiplication.
if ($is_super && $selected_hospital === 0) {
    if ($table_exists) {
        $hospitals = revenue_query($connect,
            "SELECT h.id AS hospital_id,h.name AS hospital_name,
                    COALESCE(b.bookings,0) AS bookings,
                    COALESCE(p.payments,0) AS payments,COALESCE(p.cancelled_payment_amount,0) AS cancelled_payment_amount,COALESCE(p.patients,0) AS patients,
                    COALESCE(p.gross,0) AS gross,COALESCE(p.refunded,0) AS refunded,
                    COALESCE(p.net,0) AS net,p.last_paid_at
             FROM gangnamunni_hospitals h
             LEFT JOIN (
                SELECT hospital_id,COUNT(*) AS bookings
                FROM gangnamunni_bookings
                WHERE booking_date>=? AND booking_date<DATE_ADD(?,INTERVAL 1 DAY)
                GROUP BY hospital_id
             ) b ON b.hospital_id=h.id
             LEFT JOIN (
                SELECT hospital_id,COUNT(*) AS payments,
                       SUM(CASE WHEN status='refunded' THEN amount ELSE 0 END) AS cancelled_payment_amount,
                       COUNT(DISTINCT COALESCE(NULLIF(patient_mb_id,''),CONCAT('phone:',patient_phone))) AS patients,
                       SUM(amount) AS gross,SUM(refunded_amount) AS refunded,
                       SUM(amount-refunded_amount) AS net,MAX(paid_at) AS last_paid_at
                FROM gangnamunni_platform_payments
                WHERE payment_type='booking' AND funds_recipient='hospital'
                  AND provider NOT IN (" . booking_payment_sql_list(booking_payment_test_providers()) . ")
                  AND status IN ('paid','partially_refunded','refunded')
                  AND paid_at>=? AND paid_at<DATE_ADD(?,INTERVAL 1 DAY)
                GROUP BY hospital_id
             ) p ON p.hospital_id=h.id
             ORDER BY {$order_sql}",
            'ssss', [$from, $to, $from, $to]);
    } else {
        $hospitals = revenue_query($connect,
            "SELECT h.id AS hospital_id,h.name AS hospital_name,
                    COALESCE(b.bookings,0) AS bookings,0 AS payments,0 AS cancelled_payment_amount,0 AS patients,
                    0 AS gross,0 AS refunded,0 AS net,NULL AS last_paid_at
             FROM gangnamunni_hospitals h
             LEFT JOIN (
                SELECT hospital_id,COUNT(*) AS bookings
                FROM gangnamunni_bookings
                WHERE booking_date>=? AND booking_date<DATE_ADD(?,INTERVAL 1 DAY)
                GROUP BY hospital_id
             ) b ON b.hospital_id=h.id
             ORDER BY {$order_sql}",
            'ss', [$from, $to]);
    }
    $totals = ['bookings' => 0, 'payments' => 0, 'cancelled_payment_amount' => 0, 'patients' => 0, 'gross' => 0, 'refunded' => 0, 'net' => 0];
    foreach ($hospitals as $row) {
        foreach (array_keys($totals) as $key) $totals[$key] += (int)$row[$key];
    }
    foreach ($hospitals as &$hospital_row) {
        $z = $fig_by_hospital[(int)$hospital_row['hospital_id']] ?? revenue_fig_zero();
        $hospital_row['fee'] = $z['fee'];
        $hospital_row['settlement'] = $z['settlement'];
        $hospital_row['unrecorded'] = $z['unrecorded'];
    }
    unset($hospital_row);
}

require_once __DIR__ . '/includes/admin_header.php';
if ($is_super) echo '<div class="px-4 pt-3"><a href="admin_booking_payments.php">예약 결제 조회·환불 관리</a></div>';
?>
<style>
.revenue-wrap{max-width:1280px;margin:auto}.revenue-num{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}
.revenue-link{cursor:pointer}.revenue-link:hover{background:var(--bs-primary-bg-subtle)}
.revenue-head{display:flex;align-items:center;gap:12px;padding-bottom:18px;margin-bottom:22px;border-bottom:1px solid var(--bs-border-color)}
.revenue-head-side{flex:0 0 150px}
.revenue-head-main{flex:1 1 auto}
/* The theme sets text-align on headings, so inheritance is not enough here. */
.revenue-head-main h4{display:block;text-align:center;justify-content:center;font-weight:700;letter-spacing:-.01em}
.revenue-head-main small{display:block;margin-top:5px;text-align:center;line-height:1.5}
.revenue-head-main small .badge{margin-left:4px;vertical-align:baseline}
@media (max-width:991.98px){.revenue-head{display:block;padding-bottom:14px;margin-bottom:18px}
  .revenue-head-side{flex:none}
  .revenue-head-main h4,.revenue-head-main small{text-align:left;justify-content:flex-start}
  .revenue-head .revenue-back{margin-bottom:.75rem}}
</style>
<div class="revenue-wrap px-3 py-4">
  <div class="revenue-head">
    <div class="revenue-head-side">
      <?php if ($is_super && $selected_hospital): ?>
        <a class="revenue-back btn btn-sm btn-outline-secondary" href="?<?=htmlspecialchars(http_build_query(['from'=>$from,'to'=>$to,'sort'=>$sort]))?>"><i class="bi bi-arrow-left"></i> 병원 목록</a>
      <?php endif; ?>
    </div>
    <div class="revenue-head-main">
      <h4 class="mb-1"><i class="bi bi-cash-coin"></i> 예약 결제 매출<?php if ($selected_hospital): ?> / <?=htmlspecialchars($hospital_name)?><?php endif; ?></h4>
      <small class="text-body-secondary">PG에서 검증 완료된 실제 결제만 집계합니다.
        <span class="badge <?=$is_super?'bg-danger':'bg-info'?>"><?=$is_super?'전체 병원':'소속 병원만'?></span>
      </small>
    </div>
    <div class="revenue-head-side" aria-hidden="true"></div>
  </div>

  <?php if ($forbidden): ?><div class="alert alert-danger">다른 병원의 결제 정보에 접근할 수 없습니다.</div><?php endif; ?>
  <?php if (!$is_super && !$scope_ids): ?><div class="alert alert-warning">계정에 연결된 병원이 없어 결제 정보를 조회할 수 없습니다.</div><?php endif; ?>
  <?php if (!$table_exists): ?>
    <div class="alert alert-warning"><strong>결제 원장이 아직 설치되지 않았습니다.</strong><br>
      <code>sql/platform_payments_schema.sql</code>을 적용하면 이 화면에 검증된 예약 결제가 표시됩니다. 예약 가격은 결제로 간주하지 않습니다.
    </div>
  <?php endif; ?>
  <div class="alert alert-info">
    <i class="bi bi-bank"></i>
    모든 결제 금액은 먼저 <strong>Seoul Beauty 플랫폼</strong> 계정으로 수납됩니다.
    병원 정산 금액은 <strong>(카드·계좌이체 결제액 − 수수료) + 포인트 사용액</strong>이며, 결제가 확정되는 시점의 수수료율로 한 번 계산됩니다.
    시술이 완료되면 그 <strong>다음 일요일</strong>에 병원으로 정산됩니다.
    광고 패키지 결제는 플랫폼 매출이므로 병원 지급액에 포함되지 않습니다.
  </div>

  <form class="card card-body mb-3" method="get">
    <?php if ($selected_hospital): ?><input type="hidden" name="hospital_id" value="<?=$selected_hospital?>"><?php endif; ?>
    <div class="row g-2 align-items-end">
      <div class="col-sm-3"><label class="form-label">시작일</label><input class="form-control" type="date" name="from" value="<?=htmlspecialchars($from)?>"></div>
      <div class="col-sm-3"><label class="form-label">종료일</label><input class="form-control" type="date" name="to" value="<?=htmlspecialchars($to)?>"></div>
      <?php if ($is_super && !$selected_hospital): ?>
      <div class="col-sm-3"><label class="form-label">병원 정렬</label><select class="form-select" name="sort">
        <?php foreach ($sort_options as $key=>$option): ?><option value="<?=htmlspecialchars($key)?>" <?=$sort===$key?'selected':''?>><?=htmlspecialchars($option[0])?></option><?php endforeach; ?>
      </select></div>
      <?php endif; ?>
      <div class="col-sm-3"><button class="btn btn-primary w-100"><i class="bi bi-funnel"></i> 조회</button></div>
    </div>
  </form>

  <div class="row g-3 mb-3">
    <?php foreach ([
      ['예약 건수', number_format($totals['bookings'] ?? 0).'건', ''],
      ['취소 결제', number_format($totals['cancelled_payment_amount']).'원', 'text-primary'],
      ['환자 수', number_format($totals['patients']).'명', ''],
      ['순 결제액', number_format($totals['net']).'원', 'text-success']
    ] as $card): ?>
    <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body text-center">
      <small class="text-body-secondary"><?=$card[0]?></small><div class="fs-4 fw-bold <?=$card[2]?>"><?=$card[1]?></div>
    </div></div></div>
    <?php endforeach; ?>
  </div>
  <div class="row g-3 mb-3">
    <?php foreach ([
      ['미결제 주문 (Held)', number_format($fig_held['amount']).'원', '', number_format($fig_held['count']).'건 · 아직 결제 전, 수수료 계산 없음'],
      ['수수료', number_format($fig_total['fee']).'원', 'text-danger', '결제 확정 시 기록된 수수료 합계'],
      ['정산 예정 (Counted)', number_format($fig_total['counted']).'원', 'text-primary', '결제 완료 · 지급 전 (정산 금액)'],
      ['정산 완료 (Received)', number_format($fig_total['received']).'원', 'text-success', '시술 후 첫 일요일에 병원으로 정산됨']
    ] as $card): ?>
    <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body text-center">
      <small class="text-body-secondary"><?=revenue_h($card[0])?></small><div class="fs-4 fw-bold <?=$card[2]?>"><?=$card[1]?></div>
      <small class="text-body-secondary"><?=revenue_h($card[3])?></small>
    </div></div></div>
    <?php endforeach; ?>
  </div>
  <?php if ($table_exists && !$fee_ready): ?><div class="alert alert-warning py-2">결제 수단·수수료 기록용 열이 아직 설치되지 않아 정산 금액을 계산할 수 없습니다. (<code>sql/platform_payments_pay_method.sql</code>, <code>sql/platform_payments_fee.sql</code>)</div>
  <?php elseif ($fig_total['unrecorded'] > 0): ?><div class="alert alert-light py-2">수수료가 기록되지 않은 결제 <strong><?=number_format($fig_total['unrecorded'])?>건</strong>은 수수료·정산 금액에 포함되지 않았습니다(이전 결제 또는 수단·요율 없음).</div><?php endif; ?>
  <?php if ($fee_flash): ?><div class="alert alert-<?=$fee_flash[0]==='ok'?'success':'danger'?> py-2"><?=revenue_h($fee_flash[1])?></div><?php endif; ?>
  <?php if ($totals['refunded'] > 0): ?><div class="alert alert-light py-2">환불액: <strong><?=number_format($totals['refunded'])?>원</strong> (순 결제액에서 차감됨)</div><?php endif; ?>

  <?php if ($is_super && !$selected_hospital): ?>
  <div class="card"><div class="card-header d-flex justify-content-between"><strong>전체 병원 월간 현황</strong><small class="text-body-secondary"><?=count($hospitals)?>개 병원</small></div><div class="table-responsive">
    <table class="table table-hover mb-0"><thead><tr><th>병원</th><th class="revenue-num">예약</th><th class="revenue-num">결제</th><th class="revenue-num">결제 환자</th><th class="revenue-num">승인액</th><th class="revenue-num">환불</th><th class="revenue-num">순 결제액</th><th class="revenue-num">수수료</th><th class="revenue-num">정산 금액</th><th>최근 결제</th></tr></thead><tbody>
    <?php if (!$hospitals): ?><tr><td colspan="10" class="text-center text-body-secondary py-4">등록된 병원이 없습니다.</td></tr><?php endif; ?>
    <?php foreach ($hospitals as $row): $url='?'.http_build_query(['hospital_id'=>(int)$row['hospital_id'],'from'=>$from,'to'=>$to,'sort'=>$sort]); ?>
      <tr class="revenue-link" onclick="location.href='<?=htmlspecialchars($url,ENT_QUOTES)?>'">
        <td><a href="<?=htmlspecialchars($url)?>"><?=htmlspecialchars($row['hospital_name'] ?: '#'.$row['hospital_id'])?></a></td>
        <td class="revenue-num fw-semibold"><?=number_format($row['bookings'])?></td><td class="revenue-num"><?=number_format($row['payments'])?></td><td class="revenue-num"><?=number_format($row['patients'])?></td>
        <td class="revenue-num"><?=number_format($row['gross'])?></td><td class="revenue-num"><?=number_format($row['refunded'])?></td>
        <td class="revenue-num fw-bold text-success"><?=number_format($row['net'])?></td>
        <td class="revenue-num"><?=number_format($row['fee'])?></td><td class="revenue-num fw-bold"><?=number_format($row['settlement'])?></td><td><?=htmlspecialchars($row['last_paid_at'] ?: '-')?></td>
      </tr>
    <?php endforeach; ?></tbody></table>
  </div></div>
  <?php elseif ($selected_hospital): ?>
  <div class="card mb-3"><div class="card-header"><strong>예약 상태 상세</strong></div><div class="card-body d-flex flex-wrap gap-2">
    <?php if (!$booking_statuses): ?><span class="text-body-secondary">해당 기간의 예약이 없습니다.</span><?php endif; ?>
    <?php foreach ($booking_statuses as $row): ?><span class="badge text-bg-light border"><?=htmlspecialchars($row['status'])?> <?=number_format($row['cnt'])?>건</span><?php endforeach; ?>
  </div></div>
  <div class="card mb-3"><div class="card-header"><strong>결제 수단별 정산</strong> <small class="text-body-secondary">(카드·계좌이체 결제액 − 수수료)</small></div><div class="table-responsive">
    <table class="table mb-0"><thead><tr><th>결제 수단</th><th class="revenue-num">결제 건수</th><th class="revenue-num">결제액(환불 제외)</th><th class="revenue-num">수수료</th><th class="revenue-num">정산 금액(포인트 포함)</th></tr></thead><tbody>
    <?php if (!$fig_by_method): ?><tr><td colspan="5" class="text-center text-body-secondary py-4">결제가 없습니다.</td></tr><?php endif; ?>
    <?php foreach ($fig_by_method as $mkey => $m): ?><tr><td><?=revenue_h(platform_payment_method_label($mkey === 'unknown' ? null : $mkey))?></td>
      <td class="revenue-num"><?=number_format($m['payments'])?></td><td class="revenue-num"><?=number_format($m['paid_net'])?></td><td class="revenue-num"><?=number_format($m['fee'])?></td><td class="revenue-num fw-bold"><?=number_format($m['settlement'])?></td></tr><?php endforeach; ?>
    </tbody></table>
  </div></div>
  <div class="card mb-3"><div class="card-header"><strong>주간 정산</strong> <small class="text-body-secondary">(시술 완료 후 첫 일요일에 정산 · 선택한 기간에 결제된 건)</small></div><div class="table-responsive">
    <table class="table mb-0"><thead><tr><th>정산일 (일요일)</th><th>상태</th><th class="revenue-num">결제 건수</th><th class="revenue-num">수수료</th><th class="revenue-num">정산 금액</th></tr></thead><tbody>
    <?php if (!$fig_by_payout): ?><tr><td colspan="5" class="text-center text-body-secondary py-4">결제가 없습니다.</td></tr><?php endif; ?>
    <?php krsort($fig_by_payout); foreach ($fig_by_payout as $wkey => $w): $wkey = (string)$wkey; ?><tr>
      <td><?=$wkey === '' ? '시술 전 (정산일 미정)' : revenue_h($wkey)?></td>
      <td><?php if ($wkey === ''): ?><span class="badge text-bg-light border">정산 예정 (Counted)</span><?php elseif ($wkey <= $today): ?><span class="badge text-bg-success">정산 완료 (Received)</span><?php else: ?><span class="badge text-bg-primary">정산 예정 (Counted)</span><?php endif; ?></td>
      <td class="revenue-num"><?=number_format($w['payments'])?></td><td class="revenue-num"><?=number_format($w['fee'])?></td><td class="revenue-num fw-bold"><?=number_format($w['settlement'])?></td></tr><?php endforeach; ?>
    </tbody></table>
  </div></div>
  <div class="card mb-3"><div class="card-header"><strong>시술별 합계</strong></div><div class="table-responsive">
    <table class="table mb-0"><thead><tr><th>시술</th><th class="revenue-num">결제 건수</th><th class="revenue-num">순 결제액</th></tr></thead><tbody>
    <?php if (!$treatments): ?><tr><td colspan="3" class="text-center text-body-secondary py-4">결제가 없습니다.</td></tr><?php endif; ?>
    <?php foreach ($treatments as $row): ?><tr><td><?=htmlspecialchars($row['treatment_name'])?></td><td class="revenue-num"><?=number_format($row['payments'])?></td><td class="revenue-num"><?=number_format($row['net_amount'])?>원</td></tr><?php endforeach; ?>
    </tbody></table>
  </div></div>
  <div class="card"><div class="card-header"><strong>환자별 결제 내역</strong> <small class="text-body-secondary">(최근 500건)</small></div><div class="table-responsive">
    <table class="table table-hover mb-0"><thead><tr><th>환자</th><th>연락처</th><th>시술</th><th class="revenue-num">승인액</th><th class="revenue-num">환불</th><th class="revenue-num">순액</th><th>수단</th><th class="revenue-num">수수료</th><th class="revenue-num">정산 금액</th><th>정산 상태</th><th>PG</th><th>결제일</th><th>예약일</th></tr></thead><tbody>
    <?php if (!$details): ?><tr><td colspan="13" class="text-center text-body-secondary py-4">결제가 없습니다.</td></tr><?php endif; ?>
    <?php foreach ($details as $row): ?><tr>
      <td><?=htmlspecialchars($row['patient_name'])?></td><td><?=htmlspecialchars(revenue_mask_phone($row['patient_phone']))?></td><td><?=htmlspecialchars($row['treatment_name'])?></td>
      <td class="revenue-num"><?=number_format($row['amount'])?></td><td class="revenue-num"><?=number_format($row['refunded_amount'])?></td><td class="revenue-num fw-bold"><?=number_format($row['net_amount'])?></td>
      <?php $dfig = $fig_by_id[(int)$row['id']] ?? null; $df = $dfig['f'] ?? null; ?>
      <td><?=revenue_h(platform_payment_method_label($dfig['pay_method'] ?? null))?></td>
      <td class="revenue-num"><?=$df && $df['fee'] !== null ? number_format($df['fee']) : '-'?></td>
      <td class="revenue-num fw-bold"><?=$df && $df['settlement'] !== null ? number_format($df['settlement']) : '-'?></td>
      <td><?php if ($df && $df['state'] === 'received'): ?><span class="badge text-bg-success">정산 완료</span> <?=revenue_h($df['payout_date'])?>
        <?php elseif ($df && $df['state'] === 'counted'): ?><span class="badge text-bg-primary">정산 예정</span> <?=revenue_h($df['payout_date'] ?? '')?>
        <?php elseif ($df && $df['state'] === 'refunded'): ?><span class="badge text-bg-secondary">환불</span><?php else: ?>-<?php endif; ?></td>
      <td><?=htmlspecialchars($row['provider'] ?: '-')?></td><td><?=htmlspecialchars($row['paid_at'])?></td><td><?=htmlspecialchars(trim(($row['booking_date']??'').' '.substr((string)($row['booking_time']??''),0,5)))?></td>
    </tr><?php endforeach; ?></tbody></table>
  </div></div>
  <?php endif; ?>

  <?php if ($is_super && $rates_table_exists): ?>
  <div class="card mt-4"><div class="card-header"><strong>결제 수단별 수수료율</strong>
    <small class="text-body-secondary">결제가 확정되는 날 적용 중인 요율로 한 번 계산되어 결제에 기록됩니다. 이후 요율이 바뀌어도 이미 기록된 결제는 변하지 않습니다.</small></div>
    <div class="card-body">
    <?php if ($fee_admin): ?>
    <form method="post" class="row g-2 align-items-end mb-3" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?=revenue_h($_SESSION['csrf_token'])?>">
      <input type="hidden" name="fee_action" value="add">
      <div class="col-sm-3"><label class="form-label">결제 수단</label><select class="form-select" name="pay_method" required>
        <option value="card">카드</option><option value="bank_transfer">계좌이체</option></select></div>
      <div class="col-sm-3"><label class="form-label">요율 (%)</label><input class="form-control" name="rate_percent" inputmode="decimal" pattern="\d{1,3}(\.\d{1,2})?" placeholder="예: 1.43" required></div>
      <div class="col-sm-3"><label class="form-label">적용 시작일</label><input class="form-control" type="date" name="effective_from" value="<?=revenue_h(date('Y-m-d', strtotime($today . ' +1 day')))?>" required></div>
      <div class="col-sm-3"><button class="btn btn-primary w-100" onclick="return confirm('이 요율을 저장할까요? 저장한 뒤에는 수정할 수 없고, 적용 전이면 삭제만 가능합니다.')">요율 추가</button></div>
    </form>
    <?php else: ?><div class="alert alert-light py-2">요율 설정은 꺼져 있습니다 (서버 설정 <code>G5_PAYMENT_FEES_ADMIN</code>).</div><?php endif; ?>
    <div class="table-responsive"><table class="table mb-0"><thead><tr><th>결제 수단</th><th class="revenue-num">요율</th><th>적용 시작일</th><th>설정한 관리자</th><th></th></tr></thead><tbody>
    <?php if (!$fee_rates): ?><tr><td colspan="5" class="text-center text-body-secondary py-3">등록된 요율이 없습니다. 요율이 없으면 해당 수단의 수수료가 기록되지 않습니다.</td></tr><?php endif; ?>
    <?php foreach ($fee_rates as $rate): $rate_from = substr((string)$rate['effective_from'], 0, 10); ?><tr>
      <td><?=revenue_h(platform_payment_method_label($rate['pay_method']))?></td>
      <td class="revenue-num"><?=revenue_h(number_format(((int)$rate['rate_bp']) / 100, 2))?>%</td>
      <td><?=revenue_h($rate_from)?> <?php if ($rate_from > $today): ?><span class="badge text-bg-light border">적용 전</span><?php endif; ?></td>
      <td><?=revenue_h($rate['created_by_name'])?></td>
      <td class="text-end"><?php if ($fee_admin && $rate_from > $today): ?>
        <form method="post" class="d-inline"><input type="hidden" name="csrf_token" value="<?=revenue_h($_SESSION['csrf_token'])?>"><input type="hidden" name="fee_action" value="delete"><input type="hidden" name="rate_id" value="<?=(int)$rate['id']?>">
          <button class="btn btn-sm btn-outline-danger" onclick="return confirm('적용 전 요율을 지울까요?')">삭제</button></form><?php endif; ?></td>
    </tr><?php endforeach; ?></tbody></table></div>
  </div></div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
