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
require_admin_login();
require_permission('booking_revenue');

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

$hospitals = [];
$details = [];
$treatments = [];
$totals = ['bookings' => 0, 'payments' => 0, 'patients' => 0, 'gross' => 0, 'refunded' => 0, 'net' => 0];
$booking_statuses = [];
$hospital_name = '';
$period_end = date('Y-m-t', strtotime($to));

if ($table_exists && ($is_super || !empty($scope_ids))) {
    $base_where = "p.payment_type='booking' AND p.funds_recipient='hospital'
                   AND p.status IN ('paid','partially_refunded')
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
                    COALESCE(p.payments,0) AS payments,COALESCE(p.patients,0) AS patients,
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
                       COUNT(DISTINCT COALESCE(NULLIF(patient_mb_id,''),CONCAT('phone:',patient_phone))) AS patients,
                       SUM(amount) AS gross,SUM(refunded_amount) AS refunded,
                       SUM(amount-refunded_amount) AS net,MAX(paid_at) AS last_paid_at
                FROM gangnamunni_platform_payments
                WHERE payment_type='booking' AND funds_recipient='hospital'
                  AND status IN ('paid','partially_refunded')
                  AND paid_at>=? AND paid_at<DATE_ADD(?,INTERVAL 1 DAY)
                GROUP BY hospital_id
             ) p ON p.hospital_id=h.id
             ORDER BY {$order_sql}",
            'ssss', [$from, $to, $from, $to]);
    } else {
        $hospitals = revenue_query($connect,
            "SELECT h.id AS hospital_id,h.name AS hospital_name,
                    COALESCE(b.bookings,0) AS bookings,0 AS payments,0 AS patients,
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
    $totals = ['bookings' => 0, 'payments' => 0, 'patients' => 0, 'gross' => 0, 'refunded' => 0, 'net' => 0];
    foreach ($hospitals as $row) {
        foreach (array_keys($totals) as $key) $totals[$key] += (int)$row[$key];
    }
}

require_once __DIR__ . '/includes/admin_header.php';
?>
<style>
.revenue-wrap{max-width:1280px;margin:auto}.revenue-num{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}
.revenue-link{cursor:pointer}.revenue-link:hover{background:var(--bs-primary-bg-subtle)}
</style>
<div class="revenue-wrap px-3 py-4">
  <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
    <div>
      <h4 class="mb-1"><i class="bi bi-cash-coin"></i> 예약 결제 매출<?php if ($selected_hospital): ?> / <?=htmlspecialchars($hospital_name)?><?php endif; ?></h4>
      <small class="text-body-secondary">PG에서 검증 완료된 실제 결제만 집계합니다.
        <span class="badge <?=$is_super?'bg-danger':'bg-info'?>"><?=$is_super?'전체 병원':'소속 병원만'?></span>
      </small>
    </div>
    <?php if ($is_super && $selected_hospital): ?>
      <a class="btn btn-sm btn-outline-secondary" href="?<?=htmlspecialchars(http_build_query(['from'=>$from,'to'=>$to,'sort'=>$sort]))?>"><i class="bi bi-arrow-left"></i> 병원 목록</a>
    <?php endif; ?>
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
    시술·치료 순매출은 월 마지막 날(<strong><?=htmlspecialchars($period_end)?></strong>) 병원에 정산되며,
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
      ['결제 건수', number_format($totals['payments']).'건', 'text-primary'],
      ['환자 수', number_format($totals['patients']).'명', ''],
      ['순 결제액', number_format($totals['net']).'원', 'text-success']
    ] as $card): ?>
    <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body text-center">
      <small class="text-body-secondary"><?=$card[0]?></small><div class="fs-4 fw-bold <?=$card[2]?>"><?=$card[1]?></div>
    </div></div></div>
    <?php endforeach; ?>
  </div>
  <?php if ($totals['refunded'] > 0): ?><div class="alert alert-light py-2">환불액: <strong><?=number_format($totals['refunded'])?>원</strong> (순 결제액에서 차감됨)</div><?php endif; ?>

  <?php if ($is_super && !$selected_hospital): ?>
  <div class="card"><div class="card-header d-flex justify-content-between"><strong>전체 병원 월간 현황</strong><small class="text-body-secondary"><?=count($hospitals)?>개 병원</small></div><div class="table-responsive">
    <table class="table table-hover mb-0"><thead><tr><th>병원</th><th class="revenue-num">예약</th><th class="revenue-num">결제</th><th class="revenue-num">결제 환자</th><th class="revenue-num">승인액</th><th class="revenue-num">환불</th><th class="revenue-num">순 결제액</th><th>최근 결제</th></tr></thead><tbody>
    <?php if (!$hospitals): ?><tr><td colspan="8" class="text-center text-body-secondary py-4">등록된 병원이 없습니다.</td></tr><?php endif; ?>
    <?php foreach ($hospitals as $row): $url='?'.http_build_query(['hospital_id'=>(int)$row['hospital_id'],'from'=>$from,'to'=>$to,'sort'=>$sort]); ?>
      <tr class="revenue-link" onclick="location.href='<?=htmlspecialchars($url,ENT_QUOTES)?>'">
        <td><a href="<?=htmlspecialchars($url)?>"><?=htmlspecialchars($row['hospital_name'] ?: '#'.$row['hospital_id'])?></a></td>
        <td class="revenue-num fw-semibold"><?=number_format($row['bookings'])?></td><td class="revenue-num"><?=number_format($row['payments'])?></td><td class="revenue-num"><?=number_format($row['patients'])?></td>
        <td class="revenue-num"><?=number_format($row['gross'])?></td><td class="revenue-num"><?=number_format($row['refunded'])?></td>
        <td class="revenue-num fw-bold text-success"><?=number_format($row['net'])?></td><td><?=htmlspecialchars($row['last_paid_at'] ?: '-')?></td>
      </tr>
    <?php endforeach; ?></tbody></table>
  </div></div>
  <?php elseif ($selected_hospital): ?>
  <div class="card mb-3"><div class="card-header"><strong>예약 상태 상세</strong></div><div class="card-body d-flex flex-wrap gap-2">
    <?php if (!$booking_statuses): ?><span class="text-body-secondary">해당 기간의 예약이 없습니다.</span><?php endif; ?>
    <?php foreach ($booking_statuses as $row): ?><span class="badge text-bg-light border"><?=htmlspecialchars($row['status'])?> <?=number_format($row['cnt'])?>건</span><?php endforeach; ?>
  </div></div>
  <div class="card mb-3"><div class="card-header"><strong>시술별 합계</strong></div><div class="table-responsive">
    <table class="table mb-0"><thead><tr><th>시술</th><th class="revenue-num">결제 건수</th><th class="revenue-num">순 결제액</th></tr></thead><tbody>
    <?php if (!$treatments): ?><tr><td colspan="3" class="text-center text-body-secondary py-4">결제가 없습니다.</td></tr><?php endif; ?>
    <?php foreach ($treatments as $row): ?><tr><td><?=htmlspecialchars($row['treatment_name'])?></td><td class="revenue-num"><?=number_format($row['payments'])?></td><td class="revenue-num"><?=number_format($row['net_amount'])?>원</td></tr><?php endforeach; ?>
    </tbody></table>
  </div></div>
  <div class="card"><div class="card-header"><strong>환자별 결제 내역</strong> <small class="text-body-secondary">(최근 500건)</small></div><div class="table-responsive">
    <table class="table table-hover mb-0"><thead><tr><th>환자</th><th>연락처</th><th>시술</th><th class="revenue-num">승인액</th><th class="revenue-num">환불</th><th class="revenue-num">순액</th><th>PG</th><th>결제일</th><th>예약일</th></tr></thead><tbody>
    <?php if (!$details): ?><tr><td colspan="9" class="text-center text-body-secondary py-4">결제가 없습니다.</td></tr><?php endif; ?>
    <?php foreach ($details as $row): ?><tr>
      <td><?=htmlspecialchars($row['patient_name'])?></td><td><?=htmlspecialchars(revenue_mask_phone($row['patient_phone']))?></td><td><?=htmlspecialchars($row['treatment_name'])?></td>
      <td class="revenue-num"><?=number_format($row['amount'])?></td><td class="revenue-num"><?=number_format($row['refunded_amount'])?></td><td class="revenue-num fw-bold"><?=number_format($row['net_amount'])?></td>
      <td><?=htmlspecialchars($row['provider'] ?: '-')?></td><td><?=htmlspecialchars($row['paid_at'])?></td><td><?=htmlspecialchars(trim(($row['booking_date']??'').' '.substr((string)($row['booking_time']??''),0,5)))?></td>
    </tr><?php endforeach; ?></tbody></table>
  </div></div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
